<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Site;
use App\Services\GeminiService;
use App\Services\ChatAvailabilityService;
use PDO;
use Throwable;

final class PublicChatController {
    public static function handle(): never {
        Auth::start();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');

        try {
            $method=$_SERVER['REQUEST_METHOD']??'GET';
            $input=[];
            if($method==='POST'){
                $raw=file_get_contents('php://input');
                $input=json_decode((string)$raw,true);
                if(!is_array($input))$input=$_POST;
            }
            $action=(string)($input['action']??$_GET['action']??'boot');

            if($action==='boot') self::boot();
            if($action==='start') self::startConversation($input);
            if($action==='send') self::send($input);
            if($action==='poll') self::poll();
            if($action==='close') self::close();

            self::json(['ok'=>false,'error'=>'Ação inválida.'],400);
        } catch(Throwable $e) {
            @file_put_contents(
                dirname(__DIR__,2).'/storage/logs/app.log',
                date('c').' PUBLIC_CHAT '.$e->__toString().PHP_EOL,
                FILE_APPEND
            );
            self::json(['ok'=>false,'error'=>'Não foi possível concluir o atendimento agora.'],500);
        }
    }

    private static function boot(): never {
        self::ensureToken();
        $pdo=DB::pdo();
        $prop=$pdo->query("SELECT name,whatsapp FROM property WHERE id=1")->fetch()?:[];
        $ai=$pdo->query("SELECT enabled FROM ai_config WHERE id=1")->fetchColumn();
        $site=Site::all();
        $conversation=self::currentConversation();

        self::json([
            'ok'=>true,
            'token'=>$_SESSION['public_chat_token'],
            'property_name'=>(string)($prop['name']??'Pousada'),
            'whatsapp'=>(string)($prop['whatsapp']??''),
            'ai_enabled'=>(bool)$ai,
            'agent_name'=>(string)($site['chat_agent_name']??'Assistente IA'),
            'default_mode'=>(string)($site['chat_default_mode']??'hybrid'),
            'conversation'=>$conversation ? [
                'active'=>true,
                'status'=>$conversation['status'],
                'guest_name'=>$conversation['guest_name']??''
            ] : ['active'=>false],
            'messages'=>$conversation ? self::messages((int)$conversation['id']) : []
        ]);
    }

    private static function startConversation(array $input): never {
        self::requireToken($input);
        self::rateLimit('start',4,300);

        $name=trim((string)($input['name']??''));
        $email=strtolower(trim((string)($input['email']??'')));
        $whatsapp=preg_replace('/\D+/','',(string)($input['whatsapp']??''));
        $consent=!empty($input['privacy_consent']);

        if(mb_strlen($name)<2)self::json(['ok'=>false,'error'=>'Informe seu nome.'],422);
        if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))self::json(['ok'=>false,'error'=>'Informe um e-mail válido.'],422);
        if($whatsapp!=='' && (strlen($whatsapp)<10 || strlen($whatsapp)>15))self::json(['ok'=>false,'error'=>'Informe um WhatsApp válido com DDD.'],422);
        if($email==='' && $whatsapp==='')self::json(['ok'=>false,'error'=>'Informe e-mail ou WhatsApp para contato.'],422);
        if(!$consent)self::json(['ok'=>false,'error'=>'Confirme o consentimento para iniciar o atendimento.'],422);

        if(self::currentConversation()){
            self::poll();
        }

        $pdo=DB::pdo();
        $pdo->beginTransaction();
        try{
            $guestId=0;
            if($email!==''){
                $q=$pdo->prepare("SELECT id FROM guests WHERE LOWER(email)=? ORDER BY id DESC LIMIT 1");
                $q->execute([$email]);
                $guestId=(int)$q->fetchColumn();
            }
            if(!$guestId && $whatsapp!==''){
                $q=$pdo->prepare("SELECT id FROM guests WHERE REPLACE(REPLACE(REPLACE(REPLACE(whatsapp,'+',''),' ',''),'-',''),'(', '') LIKE ? ORDER BY id DESC LIMIT 1");
                $q->execute(['%'.substr($whatsapp,-10)]);
                $guestId=(int)$q->fetchColumn();
            }

            if($guestId){
                $pdo->prepare("UPDATE guests SET name=?,email=CASE WHEN ?<>'' THEN ? ELSE email END,whatsapp=CASE WHEN ?<>'' THEN ? ELSE whatsapp END,privacy_consent=1,updated_at=NOW() WHERE id=?")
                    ->execute([$name,$email,$email,$whatsapp,$whatsapp,$guestId]);
            }else{
                $pdo->prepare("INSERT INTO guests(name,email,whatsapp,privacy_consent,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())")
                    ->execute([$name,$email?:null,$whatsapp?:null,1]);
                $guestId=(int)$pdo->lastInsertId();
            }

            $site=Site::all();
            $defaultMode=in_array((string)($site['chat_default_mode']??'hybrid'),['human','hybrid','ai'],true)?(string)$site['chat_default_mode']:'hybrid';
            $pdo->prepare("INSERT INTO conversations(guest_id,reservation_id,channel,mode,status,priority,created_at,updated_at) VALUES(?,NULL,'site',?,'open','normal',NOW(),NOW())")
                ->execute([$guestId,$defaultMode]);
            $cid=(int)$pdo->lastInsertId();

            $propertyName=(string)($pdo->query("SELECT COALESCE(NULLIF(name,''),'Pousada') FROM property WHERE id=1")->fetchColumn()?:'Pousada');
            $site=Site::all();$agent=trim((string)($site['chat_agent_name']??'Assistente IA'))?:'Assistente IA';
            $welcomeTpl=trim((string)($site['chat_welcome']??''));
            if($welcomeTpl==='')$welcomeTpl='Olá, {nome}! Você está falando com {pousada}. Eu sou {agente} e vou iniciar seu atendimento.';
            $welcome=strtr($welcomeTpl,['{nome}'=>$name,'{pousada}'=>$propertyName,'{agente}'=>$agent]);
            $pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'staff',?,NOW())")
                ->execute([$cid,$welcome]);

            $pdo->commit();
            $_SESSION['public_chat_conversation_id']=$cid;
            $_SESSION['public_chat_guest_id']=$guestId;
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }

        self::json([
            'ok'=>true,
            'conversation'=>['active'=>true,'status'=>'open','guest_name'=>$name],
            'messages'=>self::messages((int)$_SESSION['public_chat_conversation_id'])
        ]);
    }

    private static function send(array $input): never {
        self::requireToken($input);
        self::rateLimit('send',12,60);

        $conversation=self::currentConversation();
        if(!$conversation)self::json(['ok'=>false,'error'=>'Inicie o atendimento primeiro.'],409);
        if(($conversation['status']??'')!=='open')self::json(['ok'=>false,'error'=>'Esta conversa foi encerrada.'],409);

        $body=trim((string)($input['body']??''));
        if($body==='')self::json(['ok'=>false,'error'=>'Digite uma mensagem.'],422);
        if(mb_strlen($body)>2500)self::json(['ok'=>false,'error'=>'A mensagem deve ter no máximo 2500 caracteres.'],422);

        $pdo=DB::pdo();
        $cid=(int)$conversation['id'];
        $pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'guest',?,NOW())")
            ->execute([$cid,$body]);
        $pdo->prepare("UPDATE conversations SET updated_at=NOW(),status='open' WHERE id=?")->execute([$cid]);

        // Disponibilidade é dado operacional: consulta reservas/bloqueios antes do Gemini.
        $availabilityHandled=false;
        try{
            $hq=$pdo->prepare("SELECT sender_type,body,created_at FROM messages WHERE conversation_id=? ORDER BY id DESC LIMIT 16");
            $hq->execute([$cid]);
            $history=array_reverse($hq->fetchAll());

            $availability=(new ChatAvailabilityService())->answer($body,$history);
            if($availability && !empty($availability['handled'])){
                $text=trim((string)($availability['answer']??''));
                if($text!==''){
                    $pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'ai',?,NOW())")
                        ->execute([$cid,$text]);
                    $availabilityHandled=true;
                }
                if(isset($availability['ok']) && !$availability['ok'] && !empty($availability['error'])){
                    @file_put_contents(
                        dirname(__DIR__,2).'/storage/logs/app.log',
                        date('c').' PUBLIC_CHAT_AVAILABILITY '.$availability['error'].PHP_EOL,
                        FILE_APPEND
                    );
                }
            }
        }catch(Throwable $e){
            // Se a regra operacional falhar, não deixar o Gemini fingir que vai consultar depois.
            $fallback='Não consegui consultar o mapa de reservas neste instante. A equipe já recebeu sua pergunta no painel e pode continuar o atendimento sem você repetir.';
            $pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'ai',?,NOW())")
                ->execute([$cid,$fallback]);
            $availabilityHandled=true;
            @file_put_contents(
                dirname(__DIR__,2).'/storage/logs/app.log',
                date('c').' PUBLIC_CHAT_AVAILABILITY_FATAL '.$e->__toString().PHP_EOL,
                FILE_APPEND
            );
        }

        // Gemini atende linguagem/dúvidas gerais, nunca substitui a consulta operacional de disponibilidade.
        if(!$availabilityHandled){
            $aiEnabled=(int)$pdo->query("SELECT enabled FROM ai_config WHERE id=1")->fetchColumn()===1;
            $mode=(string)($conversation['mode']??'hybrid');
            if($aiEnabled && in_array($mode,['hybrid','ai'],true)){
                try{
                    $answer=(new GeminiService())->ask($body);
                    $text=trim((string)($answer['answer']??''));
                    if($text!==''){
                        $pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'ai',?,NOW())")
                            ->execute([$cid,$text]);
                    }
                }catch(Throwable $e){
                    @file_put_contents(
                        dirname(__DIR__,2).'/storage/logs/app.log',
                        date('c').' PUBLIC_CHAT_AI '.$e->getMessage().PHP_EOL,
                        FILE_APPEND
                    );
                }
            }
        }

        $pdo->prepare("UPDATE conversations SET updated_at=NOW() WHERE id=?")->execute([$cid]);

        self::json(['ok'=>true,'messages'=>self::messages($cid)]);
    }

    private static function poll(): never {
        $conversation=self::currentConversation();
        if(!$conversation){
            self::json(['ok'=>true,'conversation'=>['active'=>false],'messages'=>[]]);
        }
        self::json([
            'ok'=>true,
            'conversation'=>[
                'active'=>true,
                'status'=>$conversation['status'],
                'guest_name'=>$conversation['guest_name']??''
            ],
            'messages'=>self::messages((int)$conversation['id'])
        ]);
    }

    private static function close(): never {
        $conversation=self::currentConversation();
        if($conversation){
            DB::pdo()->prepare("UPDATE conversations SET status='closed',updated_at=NOW() WHERE id=?")->execute([(int)$conversation['id']]);
        }
        unset($_SESSION['public_chat_conversation_id'],$_SESSION['public_chat_guest_id']);
        self::json(['ok'=>true]);
    }

    private static function currentConversation(): ?array {
        $cid=(int)($_SESSION['public_chat_conversation_id']??0);
        $gid=(int)($_SESSION['public_chat_guest_id']??0);
        if(!$cid || !$gid)return null;

        $q=DB::pdo()->prepare("SELECT c.*,g.name guest_name FROM conversations c JOIN guests g ON g.id=c.guest_id WHERE c.id=? AND c.guest_id=? LIMIT 1");
        $q->execute([$cid,$gid]);
        $row=$q->fetch();
        if(!$row){
            unset($_SESSION['public_chat_conversation_id'],$_SESSION['public_chat_guest_id']);
            return null;
        }
        return $row;
    }

    private static function messages(int $cid): array {
        $q=DB::pdo()->prepare("SELECT id,sender_type,body,created_at FROM messages WHERE conversation_id=? ORDER BY id ASC LIMIT 300");
        $q->execute([$cid]);
        $out=[];
        foreach($q->fetchAll() as $m){
            $sender=(string)$m['sender_type'];
            $out[]=[
                'id'=>(int)$m['id'],
                'sender'=>$sender,
                'label'=>$sender==='guest'?'Você':($sender==='ai'?(trim((string)Site::get('chat_agent_name','Assistente IA'))?:'Assistente IA'):'Pousada'),
                'body'=>(string)($m['body']??''),
                'created_at'=>(string)$m['created_at']
            ];
        }
        return $out;
    }

    private static function ensureToken(): void {
        if(empty($_SESSION['public_chat_token'])){
            $_SESSION['public_chat_token']=bin2hex(random_bytes(24));
        }
    }

    private static function requireToken(array $input): void {
        self::ensureToken();
        $token=(string)($input['token']??'');
        if($token==='' || !hash_equals((string)$_SESSION['public_chat_token'],$token)){
            self::json(['ok'=>false,'error'=>'Sessão do chat expirou. Atualize a página.'],419);
        }
    }

    private static function rateLimit(string $bucket,int $max,int $seconds): void {
        $now=time();
        $key='public_chat_rate_'.$bucket;
        $events=array_values(array_filter($_SESSION[$key]??[],fn($t)=>($now-(int)$t)<$seconds));
        if(count($events)>=$max)self::json(['ok'=>false,'error'=>'Muitas tentativas. Aguarde um pouco e tente novamente.'],429);
        $events[]=$now;
        $_SESSION[$key]=$events;
    }

    private static function json(array $data,int $status=200): never {
        http_response_code($status);
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
}
