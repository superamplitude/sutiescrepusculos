<?php
declare(strict_types=1);

namespace App\Services;

final class AelWaCallsService
{
    private const BASE_URL = 'http://127.0.0.1:18180';
    private const ENV_FILE = '/etc/crepusculo/wacalls.env';

    public function sessionName(int $companyId = 1): string
    {
        return 'crepusculo_company_'.max(1,$companyId);
    }

    public function health(): array
    {
        try {
            $r=$this->request('GET','/healthz',null,false,5);
            return ['ok'=>true,'data'=>$r];
        } catch (\Throwable $e) {
            return ['ok'=>false,'error'=>$e->getMessage()];
        }
    }

    public function status(int $companyId = 1): array
    {
        $name=$this->sessionName($companyId);
        $sessions=$this->listSessions();
        foreach($sessions as $s){
            if((string)($s['name']??'')!==$name)continue;
            return [
                'configured'=>true,
                'exists'=>true,
                'id'=>(string)($s['id']??$s['sessionId']??''),
                'name'=>$name,
                'paired'=>(bool)($s['paired']??false),
                'state'=>(string)($s['state']??'unknown'),
                'jid'=>(string)($s['jid']??''),
            ];
        }
        return ['configured'=>true,'exists'=>false,'id'=>'','name'=>$name,'paired'=>false,'state'=>'missing','jid'=>''];
    }

    public function pair(int $companyId = 1): array
    {
        $health=$this->health();
        if(empty($health['ok']))throw new \RuntimeException('Serviço WhatsApp AEL indisponível.');

        $name=$this->sessionName($companyId);
        $existing=$this->status($companyId);
        if(!empty($existing['paired'])){
            return ['connected'=>true,'status'=>$existing,'qr'=>null];
        }
        if(!empty($existing['id'])){
            try{$this->request('DELETE','/api/sessions/'.rawurlencode((string)$existing['id']));}catch(\Throwable){}
            usleep(300000);
        }

        $created=$this->request('POST','/api/sessions',['name'=>$name]);
        $sessionId=trim((string)($created['id']??$created['session']['id']??''));
        if($sessionId==='')throw new \RuntimeException('WaCalls não retornou a sessão de pareamento.');

        $event=$this->waitForAuthEvent($sessionId,$name,18);
        if(!empty($event['paired'])){
            return ['connected'=>true,'status'=>$this->status($companyId),'qr'=>null];
        }
        $qr=trim((string)($event['qr']??''));
        if($qr==='')throw new \RuntimeException('O WhatsApp ainda não forneceu o QR. Tente gerar novamente em alguns segundos.');
        return [
            'connected'=>false,
            'status'=>[
                'configured'=>true,'exists'=>true,'id'=>$sessionId,'name'=>$name,
                'paired'=>false,'state'=>(string)($event['state']??'pairing'),'jid'=>''
            ],
            'qr'=>$qr,
            'generated_at'=>time(),
        ];
    }

    public function disconnect(int $companyId = 1): void
    {
        $s=$this->status($companyId);
        $id=trim((string)($s['id']??''));
        if($id==='')return;
        try{$this->request('POST','/api/sessions/'.rawurlencode($id).'/logout',[]);}catch(\Throwable){}
        try{$this->request('DELETE','/api/sessions/'.rawurlencode($id));}catch(\Throwable){}
    }

    /** @return list<array<string,mixed>> */
    private function listSessions(): array
    {
        $d=$this->request('GET','/api/sessions');
        $rows=$d['sessions']??$d;
        return is_array($rows)&&array_is_list($rows)?array_values(array_filter($rows,'is_array')):[];
    }

    private function waitForAuthEvent(string $sessionId,string $name,int $seconds): array
    {
        $token=$this->token();
        $ch=curl_init($this->baseUrl().'/api/events');
        if($ch===false)throw new \RuntimeException('Não foi possível abrir o stream de pareamento.');
        $buffer='';
        $found=null;
        $deadline=microtime(true)+max(5,min(25,$seconds));
        curl_setopt_array($ch,[
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: text/event-stream'],
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>4,
            CURLOPT_TIMEOUT=>max(7,min(30,$seconds+3)),
            CURLOPT_WRITEFUNCTION=>function($curl,string $chunk)use(&$buffer,&$found,$sessionId,$name,$deadline):int{
                $buffer.=$chunk;
                while(($cut=strpos($buffer,"\n\n"))!==false){
                    $frame=substr($buffer,0,$cut);
                    $buffer=substr($buffer,$cut+2);
                    $parts=[];
                    foreach(preg_split('/\r?\n/',$frame)?:[] as $line){
                        if(str_starts_with($line,'data:'))$parts[]=trim(substr($line,5));
                    }
                    $raw=implode("\n",$parts);
                    if($raw==='')continue;
                    $evt=json_decode($raw,true);
                    if(!is_array($evt))continue;
                    $type=(string)($evt['type']??'');
                    $sid=(string)($evt['sessionId']??$evt['session_id']??'');
                    $evtName=(string)($evt['name']??'');
                    if($type==='auth-state' && (($sid!==''&&hash_equals($sessionId,$sid)) || ($evtName!==''&&$evtName===$name))){
                        if(!empty($evt['paired']) || !empty($evt['qr'])){
                            $found=$evt;
                            return 0;
                        }
                    }
                }
                return microtime(true)>$deadline?0:strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $err=(string)curl_error($ch);
        curl_close($ch);
        if(is_array($found))return $found;

        // The stream may have emitted the QR just before connection; check paired state before failing.
        $sessions=$this->listSessions();
        foreach($sessions as $s){
            if((string)($s['name']??'')===$name && !empty($s['paired'])){
                return ['paired'=>true,'state'=>(string)($s['state']??'connected')];
            }
        }
        if($err!==''&&!str_contains(strtolower($err),'callback aborted')&&!str_contains(strtolower($err),'timeout')){
            throw new \RuntimeException('Stream WhatsApp AEL: '.$err);
        }
        throw new \RuntimeException('QR ainda não recebido pelo serviço AEL. Gere novamente.');
    }

    private function request(string $method,string $path,?array $payload=null,bool $auth=true,int $timeout=15): array
    {
        if(!function_exists('curl_init'))throw new \RuntimeException('cURL não está disponível no PHP.');
        $headers=['Accept: application/json'];
        if($auth)$headers[]='Authorization: Bearer '.$this->token();
        $body=null;
        if($payload!==null){
            $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $headers[]='Content-Type: application/json';
        }
        $ch=curl_init($this->baseUrl().$path);
        if($ch===false)throw new \RuntimeException('Falha ao iniciar comunicação com WaCalls.');
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CUSTOMREQUEST=>$method,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_CONNECTTIMEOUT=>4,
            CURLOPT_TIMEOUT=>$timeout,
            CURLOPT_FOLLOWLOCATION=>false,
        ]);
        if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $raw=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $err=(string)curl_error($ch);
        curl_close($ch);
        if($raw===false||$err!=='')throw new \RuntimeException('WaCalls não respondeu: '.$err);
        $data=json_decode((string)$raw,true);
        if($status<200||$status>=300){
            $msg=is_array($data)?(string)($data['error']??$data['message']??''):substr((string)$raw,0,300);
            throw new \RuntimeException('WaCalls HTTP '.$status.($msg!==''?': '.$msg:''));
        }
        return is_array($data)?$data:[];
    }

    private function baseUrl(): string
    {
        if(is_readable(self::ENV_FILE)){
            foreach(file(self::ENV_FILE,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
                if(str_starts_with($line,'WACALLS_API_BASE_URL=')){
                    $url=rtrim(trim(substr($line,strlen('WACALLS_API_BASE_URL='))),'/');
                    if($url==='http://127.0.0.1:18180')return $url;
                    throw new \RuntimeException('O endereço configurado não corresponde ao gateway local autorizado.');
                }
            }
        }
        return self::BASE_URL;
    }

    private function token(): string
    {
        if(!is_readable(self::ENV_FILE))throw new \RuntimeException('Credencial local do WaCalls não está acessível ao aplicativo.');
        $lines=file(self::ENV_FILE,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];
        foreach($lines as $line){
            if(str_starts_with($line,'WACALLS_API_TOKEN=')){
                $v=trim(substr($line,strlen('WACALLS_API_TOKEN=')));
                if($v!=='')return $v;
            }
        }
        throw new \RuntimeException('Token local do WaCalls não encontrado.');
    }
}
