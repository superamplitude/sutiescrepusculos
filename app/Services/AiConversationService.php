<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class AiConversationService
{
    public function respondIfEnabled(int $conversationId): ?int
    {
        if($conversationId<=0)return null;
        $pdo=DB::pdo();
        $q=$pdo->prepare("SELECT * FROM conversations WHERE id=? LIMIT 1");
        $q->execute([$conversationId]);$conv=$q->fetch();
        if(!$conv)return null;

        $mode=(string)($conv['mode']??'hybrid');
        if($mode==='human')return null;
        if($mode==='hybrid'&&!empty($conv['assigned_user_id']))return null;

        $cfg=$pdo->query("SELECT enabled FROM ai_config WHERE id=1")->fetch();
        if(!$cfg||!(int)$cfg['enabled'])return null;

        $q=$pdo->prepare("SELECT sender_type,body,created_at FROM messages WHERE conversation_id=? ORDER BY id DESC LIMIT 12");
        $q->execute([$conversationId]);
        $history=array_reverse($q->fetchAll()?:[]);
        if(!$history)return null;

        $property=BusinessContextService::property();
        $kb=$pdo->query("SELECT title,content FROM knowledge_base WHERE active=1 ORDER BY id DESC LIMIT 20")->fetchAll()?:[];

        $prompt=BusinessContextService::instructions($property)."\n";
        $prompt.=(new VoiceProfileService())->conversationInstructions()."\n\n";
        $prompt.="ESTABELECIMENTO:\n".json_encode($property,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n\nBASE CONFIRMADA:\n";
        foreach($kb as $row)$prompt.="- ".trim((string)$row['title']).": ".trim((string)$row['content'])."\n";
        $prompt.="\nCONVERSA:\n";
        foreach($history as $m){
            $who=($m['sender_type']==='guest'?'Cliente':($m['sender_type']==='ai'?'IA':'Equipe'));
            $prompt.=$who.": ".trim((string)$m['body'])."\n";
        }
        $prompt.="\nResponda somente à última mensagem do cliente.";

        $lastGuest='';foreach(array_reverse($history) as $msg){if(($msg['sender_type']??'')==='guest'){$lastGuest=(string)$msg['body'];break;}}
        $verified=BusinessContextService::verifiedServiceAnswer($lastGuest);
        $r=$verified!==null?['ok'=>true,'answer'=>$verified]:(new GeminiService())->ask($prompt);
        if(empty($r['ok']))throw new \RuntimeException('IA indisponível: '.trim((string)($r['error']??'falha do provedor')));
        $answer=trim((string)($r['answer']??''));
        if($answer==='')throw new \RuntimeException('A IA retornou resposta vazia.');

        $st=$pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'ai',?,NOW())");
        $st->execute([$conversationId,$answer]);
        $id=(int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE conversations SET updated_at=NOW() WHERE id=?")->execute([$conversationId]);

        try{(new CrmSyncService())->syncConversation($conversationId);}catch(\Throwable){}
        return $id;
    }
}
