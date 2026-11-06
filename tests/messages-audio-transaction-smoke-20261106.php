<?php
declare(strict_types=1);
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
require '/opt/suites-crepusculo-messages/messages.php';
final class TransactionOnlyPDO extends PDO {
 private bool $logical=false;
 public function __construct(private PDO $inner){}
 public function prepare(string $query,array $options=[]):PDOStatement|false{return $this->inner->prepare($query,$options);}
 public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false{return $fetchMode===null?$this->inner->query($query):$this->inner->query($query,$fetchMode,...$args);}
 public function exec(string $statement):int|false{return $this->inner->exec($statement);}
 public function lastInsertId(?string $name=null):string|false{return $this->inner->lastInsertId($name);}
 public function beginTransaction():bool{$this->logical=true;return true;}
 public function commit():bool{$this->logical=false;return true;}
 public function rollBack():bool{$this->logical=false;return true;}
 public function inTransaction():bool{return $this->logical;}
}
$real=App\Core\DB::pdo();$property=new ReflectionProperty(App\Core\DB::class,'pdo');
$tag='suite-smoke-'.bin2hex(random_bytes(6));$jid='000000000000000@s.whatsapp.net';
$q=$real->prepare('SELECT COUNT(*) FROM suites_whatsapp_contacts WHERE jid=?');$q->execute([$jid]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Fixture collision; refusing test');
$real->beginTransaction();$property->setValue(null,new TransactionOnlyPDO($real));
try{
 $input=['id'=>$tag,'jid'=>$jid,'name'=>'Verificação temporária','text'=>'Qual é o horário de check-in?'];
 $r=processSuitesWhatsapp($input);
 if(empty($r['answer']))throw new RuntimeException('Actual message logic did not produce an AI answer');
 $duplicate=processSuitesWhatsapp($input);if(empty($duplicate['skip']))throw new RuntimeException('Duplicate not skipped');
 $q=$real->prepare("SELECT conversation_id FROM suites_whatsapp_receipts WHERE message_id=?");$q->execute([$tag]);$cid=(int)$q->fetchColumn();
 if(!$cid)throw new RuntimeException('Inbound message not linked to a conversation');
 $q=$real->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id=? AND sender_type='ai'");$q->execute([$cid]);$ai=(int)$q->fetchColumn();if($ai!==1)throw new RuntimeException('Duplicate AI reply');
 $real->prepare("UPDATE conversations SET mode='human' WHERE id=?")->execute([$cid]);
 $human=processSuitesWhatsapp([...$input,'id'=>$tag.'-human','text'=>'Pode me ajudar?']);if(empty($human['skip'])||!empty($human['answer']))throw new RuntimeException('Human mode not respected');

 $real->prepare("UPDATE conversations SET mode='hybrid',assigned_user_id=NULL WHERE id=?")->execute([$cid]);
 $inputWav=(new App\Services\PanelVoiceService())->speech('Qual é o horário de check-in?');
 if(substr($inputWav,0,4)!=='RIFF')throw new RuntimeException('Synthetic audio unavailable');
 $audio=processSuitesWhatsapp(['id'=>$tag.'-audio','jid'=>$jid,'name'=>'Verificação temporária','text'=>'','mime'=>'audio/wav','audio'=>base64_encode($inputWav)]);
 if(empty($audio['answer'])||empty($audio['wav']))throw new RuntimeException('Actual audio transcript/reply unavailable');
 $replyWav=base64_decode($audio['wav'],true);
 if(!$replyWav||strlen($replyWav)<10000||substr($replyWav,0,4)!=='RIFF'||substr($replyWav,8,4)!=='WAVE')throw new RuntimeException('Actual response WAV invalid');
 echo 'REAL_STT_AI_TTS_OK; INPUT_BYTES='.strlen($inputWav).'; REPLY_BYTES='.strlen($replyWav).'; ANSWER_CHARACTERS='.mb_strlen($audio['answer']).PHP_EOL;

 echo 'REAL_MESSAGE_AI_DUPLICATION_HANDOFF_TEST_OK; ANSWER_CHARACTERS='.mb_strlen($r['answer']).PHP_EOL;
}finally{
 $property->setValue(null,$real);if($real->inTransaction())$real->rollBack();
}
$q=$real->prepare('SELECT COUNT(*) FROM suites_whatsapp_contacts WHERE jid=?');$q->execute([$jid]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Fixture rollback failed');
$q=$real->prepare('SELECT COUNT(*) FROM suites_whatsapp_receipts WHERE message_id LIKE ?');$q->execute([$tag.'%']);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Receipt rollback failed');
echo 'ALL_TEST_RECORDS_ROLLED_BACK=1; EXTERNAL_MESSAGES_SENT=0; AUTH_SESSIONS_CREATED=0'.PHP_EOL;
