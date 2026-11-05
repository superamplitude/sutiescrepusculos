<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use PDO;

final class CrmSyncService
{
    public function __construct(private int $companyId=1)
    {
        $this->companyId=max(1,$companyId);
    }

    public function syncAll(): array
    {
        $pdo=DB::pdo();
        $counts=['contacts'=>0,'leads'=>0,'conversations'=>0,'messages'=>0,'tasks'=>0];

        foreach($pdo->query("SELECT * FROM guests ORDER BY id")->fetchAll()?:[] as $g){
            $this->syncGuest($g);$counts['contacts']++;
        }

        $sql="SELECT r.*,rm.name room_name,g.name guest_name,g.email guest_email,g.phone guest_phone,g.whatsapp guest_whatsapp
              FROM reservations r JOIN rooms rm ON rm.id=r.room_id JOIN guests g ON g.id=r.guest_id ORDER BY r.id";
        foreach($pdo->query($sql)->fetchAll()?:[] as $r){
            $this->syncReservation($r);$counts['leads']++;
        }

        foreach($pdo->query("SELECT id FROM conversations ORDER BY id")->fetchAll(PDO::FETCH_COLUMN)?:[] as $id){
            $out=$this->syncConversation((int)$id);
            $counts['conversations']++;
            $counts['messages']+=(int)($out['messages']??0);
        }

        $q=$pdo->prepare("SELECT COUNT(*) FROM crm_tasks WHERE company_id=? AND status='pending'");
        $q->execute([$this->companyId]);$counts['tasks']=(int)$q->fetchColumn();
        return $counts;
    }

    public function syncConversation(int $conversationId): array
    {
        $pdo=DB::pdo();
        $q=$pdo->prepare("SELECT c.*,g.name guest_name,g.email guest_email,g.phone guest_phone,g.whatsapp guest_whatsapp
                          FROM conversations c LEFT JOIN guests g ON g.id=c.guest_id WHERE c.id=? LIMIT 1");
        $q->execute([$conversationId]);$c=$q->fetch();
        if(!$c)return ['messages'=>0];

        $contactId=0;
        if(!empty($c['guest_id'])){
            $gq=$pdo->prepare("SELECT * FROM guests WHERE id=?");$gq->execute([(int)$c['guest_id']]);
            if($g=$gq->fetch())$contactId=$this->syncGuest($g);
        }
        if(!$contactId){
            $contactId=$this->ensureContact(
                'conversation:'.$conversationId,
                (string)($c['guest_name']?:'Visitante'),
                $c['guest_phone']??$c['guest_whatsapp']??null,
                $c['guest_email']??null,
                null,
                'site'
            );
        }

        $channel=trim((string)($c['channel']??'site'))?:'site';

        // AEL core did not originally have an external conversation id.
        // One CRM conversation per contact/channel is stable and idempotent.
        $q=$pdo->prepare("SELECT id FROM crm_conversations WHERE company_id=? AND contact_id=? AND channel=? ORDER BY id DESC LIMIT 1");
        $q->execute([$this->companyId,$contactId,$channel]);$crmConversationId=(int)($q->fetchColumn()?:0);

        $last=$pdo->prepare("SELECT MAX(created_at) FROM messages WHERE conversation_id=?");
        $last->execute([$conversationId]);$lastAt=$last->fetchColumn()?:date('Y-m-d H:i:s');
        $status=(string)($c['status']??'open');

        if($crmConversationId){
            $pdo->prepare("UPDATE crm_conversations SET assigned_user_id=?,status=?,last_message_at=?,updated_at=NOW() WHERE id=? AND company_id=?")
                ->execute([$c['assigned_user_id']??null,$status,$lastAt,$crmConversationId,$this->companyId]);
        }else{
            $pdo->prepare("INSERT INTO crm_conversations(company_id,contact_id,assigned_user_id,channel,status,last_message_at,created_at,updated_at)
                           VALUES(?,?,?,?,?,?,NOW(),NOW())")
                ->execute([$this->companyId,$contactId,$c['assigned_user_id']??null,$channel,$status,$lastAt]);
            $crmConversationId=(int)$pdo->lastInsertId();
        }

        $mq=$pdo->prepare("SELECT * FROM messages WHERE conversation_id=? ORDER BY id");
        $mq->execute([$conversationId]);$count=0;
        foreach($mq->fetchAll()?:[] as $m){
            $external='crepusculo-message:'.(int)$m['id'];
            $exists=$pdo->prepare("SELECT id FROM crm_messages WHERE company_id=? AND external_message_id=? LIMIT 1");
            $exists->execute([$this->companyId,$external]);
            if($exists->fetchColumn())continue;

            $sender=(string)($m['sender_type']??'guest');
            $direction=$sender==='guest'?'in':($sender==='internal'?'internal':'out');
            $payload=json_encode([
                'source'=>'crepusculo',
                'source_conversation_id'=>$conversationId,
                'source_message_id'=>(int)$m['id'],
                'sender_type'=>$sender
            ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

            $pdo->prepare("INSERT INTO crm_messages(company_id,conversation_id,contact_id,external_message_id,direction,message_type,body,payload_json,sent_at)
                           VALUES(?,?,?,?,?,'conversation',?,?,?)")
                ->execute([$this->companyId,$crmConversationId,$contactId,$external,$direction,$m['body']??null,$payload,$m['created_at']??date('Y-m-d H:i:s')]);
            $count++;
        }

        return ['messages'=>$count,'crm_conversation_id'=>$crmConversationId];
    }

    private function syncGuest(array $g): int
    {
        return $this->ensureContact(
            'guest:'.(int)$g['id'],
            (string)($g['name']??'Hóspede'),
            $g['phone']?:($g['whatsapp']??null),
            $g['email']??null,
            $g['document']??null,
            'direct',
            true,
            $g['notes']??null
        );
    }

    private function ensureContact(
        string $externalId,
        string $name,
        ?string $phone,
        ?string $email,
        ?string $document,
        string $channel='direct',
        bool $client=false,
        ?string $notes=null
    ): int {
        $pdo=DB::pdo();
        $q=$pdo->prepare("SELECT id FROM crm_contacts WHERE company_id=? AND channel=? AND external_id=? LIMIT 1");
        $q->execute([$this->companyId,$channel,$externalId]);$id=(int)($q->fetchColumn()?:0);

        if(!$id && $email){
            $q=$pdo->prepare("SELECT id FROM crm_contacts WHERE company_id=? AND email=? ORDER BY id LIMIT 1");
            $q->execute([$this->companyId,$email]);$id=(int)($q->fetchColumn()?:0);
        }

        if($id){
            $pdo->prepare("UPDATE crm_contacts SET external_id=COALESCE(external_id,?),channel=?,name=?,phone=?,email=?,document=?,is_client=?,client_since=IF(?=1,COALESCE(client_since,NOW()),client_since),notes=?,updated_at=NOW()
                           WHERE id=? AND company_id=?")
                ->execute([$externalId,$channel,$name,$phone,$email,$document,$client?1:0,$client?1:0,$notes,$id,$this->companyId]);
            return $id;
        }

        $pdo->prepare("INSERT INTO crm_contacts(company_id,channel,external_id,name,phone,email,document,is_client,client_since,notes,created_at,updated_at)
                       VALUES(?,?,?,?,?,?,?, ?,IF(?=1,NOW(),NULL),?,NOW(),NOW())")
            ->execute([$this->companyId,$channel,$externalId,$name,$phone,$email,$document,$client?1:0,$client?1:0,$notes]);
        return (int)$pdo->lastInsertId();
    }

    private function syncReservation(array $r): int
    {
        $pdo=DB::pdo();
        $gq=$pdo->prepare("SELECT * FROM guests WHERE id=?");$gq->execute([(int)$r['guest_id']]);
        $g=$gq->fetch()?:[];
        $contactId=$g?$this->syncGuest($g):0;
        if(!$contactId)throw new \RuntimeException('Não foi possível vincular contato à reserva '.(int)$r['id']);

        $source='reservation:'.(int)$r['id'];
        $q=$pdo->prepare("SELECT id FROM crm_leads WHERE company_id=? AND source=? LIMIT 1");
        $q->execute([$this->companyId,$source]);$id=(int)($q->fetchColumn()?:0);

        $stage=match((string)$r['status']){
            'pre_reserva'=>'new',
            'aguardando_aprovacao'=>'qualified',
            'aguardando_pagamento'=>'proposal',
            'paga','confirmada','concluida'=>'won',
            'cancelada','recusada','expirada','reembolsada','no_show'=>'lost',
            default=>'new'
        };
        $leadStatus=in_array($stage,['won','lost'],true)?$stage:'open';
        $title='Reserva '.($r['code']??('#'.$r['id'])).' · '.($r['room_name']??'Acomodação');

        if($id){
            $pdo->prepare("UPDATE crm_leads SET contact_id=?,title=?,stage=?,status=?,value=?,currency='BRL',expected_checkin=?,expected_checkout=?,guests=?,updated_at=NOW()
                           WHERE id=? AND company_id=?")
                ->execute([$contactId,$title,$stage,$leadStatus,$r['total']??null,$r['checkin']??null,$r['checkout']??null,$r['guests']??null,$id,$this->companyId]);
        }else{
            $pdo->prepare("INSERT INTO crm_leads(company_id,contact_id,conversation_id,owner_user_id,title,stage,status,source,value,currency,expected_checkin,expected_checkout,guests,created_at,updated_at)
                           VALUES(?,?,NULL,NULL,?,?,?,?,?,'BRL',?,?,?,NOW(),NOW())")
                ->execute([$this->companyId,$contactId,$title,$stage,$leadStatus,$source,$r['total']??null,$r['checkin']??null,$r['checkout']??null,$r['guests']??null]);
            $id=(int)$pdo->lastInsertId();
        }

        $taskTitle='Follow-up · '.$title;
        if($leadStatus==='open'){
            $t=$pdo->prepare("SELECT id FROM crm_tasks WHERE company_id=? AND lead_id=? AND title=? AND status='pending' LIMIT 1");
            $t->execute([$this->companyId,$id,$taskTitle]);
            if(!$t->fetchColumn()){
                $pdo->prepare("INSERT INTO crm_tasks(company_id,lead_id,contact_id,assigned_user_id,title,description,due_at,priority,status,created_at,updated_at)
                               VALUES(?,?,?,NULL,?,NULL,DATE_ADD(NOW(),INTERVAL 30 MINUTE),'high','pending',NOW(),NOW())")
                    ->execute([$this->companyId,$id,$contactId,$taskTitle]);
            }
        }else{
            $pdo->prepare("UPDATE crm_tasks SET status='done',updated_at=NOW() WHERE company_id=? AND lead_id=? AND status='pending'")
                ->execute([$this->companyId,$id]);
        }

        return $id;
    }
}
