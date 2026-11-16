<?php
namespace App\Services;
use App\Core\DB;

final class ICalService {
    public function export(string $token): string {
        $pdo=DB::pdo();
        $q=$pdo->prepare("SELECT f.room_id,r.name FROM ical_feeds f JOIN rooms r ON r.id=f.room_id WHERE f.export_token=? AND f.active=1");
        $q->execute([$token]);$feed=$q->fetch();if(!$feed)throw new \RuntimeException('Feed inválido.');
        $rid=(int)$feed['room_id'];
        $q=$pdo->prepare("SELECT id,checkin start_date,checkout end_date FROM reservations WHERE room_id=? AND status IN('pre_reserva','aguardando_aprovacao','aguardando_pagamento','paga','confirmada') ORDER BY checkin");
        $q->execute([$rid]);$events=$q->fetchAll();
        $q=$pdo->prepare("SELECT id,start_date,end_date FROM room_blocks WHERE room_id=? ORDER BY start_date");$q->execute([$rid]);
        foreach($q->fetchAll() as $b)$events[]=['id'=>'b'.$b['id'],'start_date'=>$b['start_date'],'end_date'=>$b['end_date']];
        $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Pousada IA//Reservas//PT-BR','CALSCALE:GREGORIAN','METHOD:PUBLISH','X-WR-CALNAME:Disponibilidade '.self::esc($feed['name'])];
        foreach($events as $e){
            $lines[]='BEGIN:VEVENT';$lines[]='UID:room-'.$rid.'-'.$e['id'].'@pousada-ia';
            $lines[]='DTSTART;VALUE=DATE:'.str_replace('-','',$e['start_date']);$lines[]='DTEND;VALUE=DATE:'.str_replace('-','',$e['end_date']);
            $lines[]='SUMMARY:Indisponível';$lines[]='END:VEVENT';
        }
        $lines[]='END:VCALENDAR';
        return implode("\r\n",$lines)."\r\n";
    }

    public function sync(int $feedId): int {
        $pdo=DB::pdo();$q=$pdo->prepare("SELECT * FROM ical_feeds WHERE id=? AND active=1");$q->execute([$feedId]);$feed=$q->fetch();
        if(!$feed||empty($feed['import_url']))throw new \RuntimeException('URL de importação não configurada.');
        try{
            $raw=$this->fetchPublicUrl($feed['import_url']);$events=$this->parse($raw);
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM room_blocks WHERE room_id=? AND source='ical'")->execute([$feed['room_id']]);
            $ins=$pdo->prepare("INSERT INTO room_blocks(room_id,start_date,end_date,type,reason,created_by,source,external_uid,created_at) VALUES(?,?,?,'external','Bloqueio importado por iCal',NULL,'ical',?,NOW())");
            $count=0;foreach($events as $ev){if($ev['end']>$ev['start']){$ins->execute([$feed['room_id'],$ev['start'],$ev['end'],$ev['uid']]);$count++;}}
            $pdo->prepare("UPDATE ical_feeds SET last_sync_at=NOW(),last_error=NULL,updated_at=NOW() WHERE id=?")->execute([$feedId]);$pdo->commit();return $count;
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $pdo->prepare("UPDATE ical_feeds SET last_error=?,updated_at=NOW() WHERE id=?")->execute([mb_substr($e->getMessage(),0,1000),$feedId]);
            throw $e;
        }
    }

    /**
     * Synchronize one OTA calendar without deleting another channel's blocks.
     * Legacy iCal feeds and locally created reservations/blocks remain untouched.
     */
    public function syncChannel(int $feedId): array {
        $pdo=DB::pdo();
        $q=$pdo->prepare("SELECT * FROM suites_ical_channel_feeds WHERE id=? AND active=1");
        $q->execute([$feedId]);$feed=$q->fetch();
        if(!$feed || empty($feed['import_url']))throw new \RuntimeException('Calendário do canal não encontrado.');
        try {
            $events=$this->parse($this->fetchPublicUrl((string)$feed['import_url']));
            if(count($events)>3000)throw new \RuntimeException('Calendário com mais de 3000 eventos.');
            $desired=[];
            foreach($events as $event){
                if($event['end']<=$event['start'])continue;
                $start=\DateTimeImmutable::createFromFormat('!Y-m-d',$event['start']);
                $end=\DateTimeImmutable::createFromFormat('!Y-m-d',$event['end']);
                if(!$start||!$end||$start->format('Y-m-d')!==$event['start']||$end->format('Y-m-d')!==$event['end'])throw new \RuntimeException('Período inválido na origem.');
                $uid='ch'.$feedId.':'.hash('sha256',(string)$event['uid']);
                if(isset($desired[$uid]))throw new \RuntimeException('UID duplicado no calendário do canal.');
                $desired[$uid]=['uid'=>$uid,'start'=>$event['start'],'end'=>$event['end']];
            }
            // A zero-event feed could represent a transient/partial provider response.
            // Keep existing blocks rather than clearing the occupancy silently.
            if(!$desired){
                $pdo->prepare("UPDATE suites_ical_channel_feeds SET last_error=?,updated_at=NOW() WHERE id=?")->execute(['Calendário sem períodos; bloqueios existentes preservados para revisão.', $feedId]);
                return ['imported'=>0,'conflicts'=>0,'preserved'=>true];
            }
            $pdo->beginTransaction();
            $prefix='ch'.$feedId.':%';
            $q=$pdo->prepare("SELECT id,external_uid,start_date,end_date FROM room_blocks WHERE room_id=? AND source='ical' AND external_uid LIKE ? FOR UPDATE");
            $q->execute([(int)$feed['room_id'],$prefix]);$prior=$q->fetchAll();
            $old=[];foreach($prior as $row)$old[$row['external_uid']]=$row;
            $conflicts=0;
            foreach($desired as $uid=>$event){
                $q=$pdo->prepare("SELECT COUNT(*) FROM reservations WHERE room_id=? AND status IN ('pre_reserva','aguardando_aprovacao','aguardando_pagamento','paga','confirmada') AND NOT(checkout<=? OR checkin>=?)");
                $q->execute([(int)$feed['room_id'],$event['start'],$event['end']]);
                $conflicts+=(int)$q->fetchColumn()>0?1:0;
                if(isset($old[$uid])){
                    $pdo->prepare("UPDATE room_blocks SET start_date=?,end_date=?,reason=? WHERE id=?")->execute([$event['start'],$event['end'],'Ocupado · '.mb_substr((string)$feed['channel'],0,60),(int)$old[$uid]['id']]);
                }else{
                    $pdo->prepare("INSERT INTO room_blocks(room_id,start_date,end_date,type,reason,created_by,source,external_uid,created_at) VALUES(?,?,?,'external',?,NULL,'ical',?,NOW())")
                        ->execute([(int)$feed['room_id'],$event['start'],$event['end'],'Ocupado · '.mb_substr((string)$feed['channel'],0,60),$uid]);
                }
            }
            foreach($old as $uid=>$row)if(!isset($desired[$uid])){
                $pdo->prepare("DELETE FROM room_blocks WHERE id=? AND room_id=? AND source='ical' AND external_uid=?")->execute([(int)$row['id'],(int)$feed['room_id'],$uid]);
            }
            $note=$conflicts?"Atenção: {$conflicts} período(s) sobrepõem reservas internas. Conferir mapa.":null;
            $pdo->prepare("UPDATE suites_ical_channel_feeds SET last_sync_at=NOW(),last_error=?,updated_at=NOW() WHERE id=?")->execute([$note,$feedId]);
            $pdo->commit();
            return ['imported'=>count($desired),'conflicts'=>$conflicts,'preserved'=>false];
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $pdo->prepare("UPDATE suites_ical_channel_feeds SET last_error=?,updated_at=NOW() WHERE id=?")->execute([mb_substr($e->getMessage(),0,600),$feedId]);
            throw $e;
        }
    }

    private function fetchPublicUrl(string $url): string {
        $u=parse_url($url);$scheme=strtolower($u['scheme']??'');$host=$u['host']??'';
        if(!in_array($scheme,['http','https'],true)||$host==='')throw new \RuntimeException('A URL iCal deve usar HTTP ou HTTPS.');
        $ip=gethostbyname($host);if($ip===$host)throw new \RuntimeException('Não foi possível resolver o host iCal.');
        if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new \RuntimeException('Host iCal não pode apontar para rede privada/reservada.');
        if(!function_exists('curl_init'))throw new \RuntimeException('cURL é necessário para importar iCal.');
        $port=$scheme==='https'?443:80;$ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_RESOLVE=>["$host:$port:$ip"],CURLOPT_USERAGENT=>'PousadaIA-iCal/1.0']);
        $raw=curl_exec($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($err||$http<200||$http>=300)throw new \RuntimeException('Falha ao importar iCal. HTTP '.$http.' '.$err);
        if(strlen((string)$raw)>5*1024*1024)throw new \RuntimeException('Arquivo iCal excede 5 MB.');
        return (string)$raw;
    }

    private function parse(string $raw): array {
        $raw=preg_replace("/\r\n[ \t]/",'',$raw);$lines=preg_split("/\r\n|\n|\r/",$raw);$events=[];$cur=null;
        foreach($lines as $line){
            if(trim($line)==='BEGIN:VEVENT'){$cur=['uid'=>'','start'=>'','end'=>''];continue;}
            if(trim($line)==='END:VEVENT'){if($cur&&$cur['start']&&$cur['end']){$cur['uid']=$cur['uid']?:hash('sha256',$cur['start'].$cur['end'].count($events));$events[]=$cur;}$cur=null;continue;}
            if(!$cur)continue;
            if(str_starts_with($line,'UID:'))$cur['uid']=mb_substr(trim(substr($line,4)),0,180);
            elseif(preg_match('/^DTSTART(?:;[^:]*)?:(\d{8})/',$line,$m))$cur['start']=substr($m[1],0,4).'-'.substr($m[1],4,2).'-'.substr($m[1],6,2);
            elseif(preg_match('/^DTEND(?:;[^:]*)?:(\d{8})/',$line,$m))$cur['end']=substr($m[1],0,4).'-'.substr($m[1],4,2).'-'.substr($m[1],6,2);
        }
        return $events;
    }

    private static function esc(string $v):string{return str_replace(["\\",",",";","\n"],["\\\\","\\,","\\;","\\n"],$v);}
}
