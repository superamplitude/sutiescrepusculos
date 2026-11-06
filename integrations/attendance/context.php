<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
use App\Core\DB;use App\Core\Site;
$action=$argv[1]??'';$input=json_decode($argv[2]??'{}',true,512,JSON_THROW_ON_ERROR);
if($action==='context'){
 $pdo=DB::pdo();$enabled=(bool)$pdo->query("SELECT enabled FROM ai_config WHERE id=1")->fetchColumn();
 $enabled=$enabled && Site::get('chat_default_mode','hybrid')!=='human';
 $phone=preg_replace('/\D/','',explode('@',(string)($input['peer']??''))[0]);
 if($phone!==''){
  $q=$pdo->prepare("SELECT c.mode,c.assigned_user_id FROM conversations c JOIN guests g ON g.id=c.guest_id WHERE g.whatsapp=? OR g.phone=? ORDER BY c.id DESC LIMIT 1");
  $q->execute([$phone,$phone]);$c=$q->fetch();
  if($c && ($c['mode']==='human'||($c['mode']==='hybrid'&&!empty($c['assigned_user_id']))))$enabled=false;
 }
 if(!$enabled){echo json_encode(['enabled'=>false]);exit;}
 $voice=json_decode((string)file_get_contents('/etc/crepusculo/voice.json'),true,512,JSON_THROW_ON_ERROR);
 $property=\App\Services\BusinessContextService::property();
 $kb=$pdo->query("SELECT title,content FROM knowledge_base WHERE active=1 ORDER BY id DESC LIMIT 30")->fetchAll();
 $profile=new \App\Services\VoiceProfileService();
 $prompt=$profile->conversationInstructions()."\n\n";
 $prompt.=\App\Services\BusinessContextService::instructions($property)."\nBASE CONFIRMADA:\n".json_encode(['property'=>$property,'knowledge'=>$kb],JSON_UNESCAPED_UNICODE);
 echo json_encode(['enabled'=>true,'api_key'=>$voice['provider_api_key']??'','prompt'=>$prompt],JSON_UNESCAPED_UNICODE);exit;
}
if($action==='availability'){
 $property=\App\Services\BusinessContextService::property();
 if(!\App\Services\BusinessContextService::isLodging($property)) {echo json_encode(['handled'=>false,'answer'=>'Consulta de hospedagem não habilitada para esta empresa.'],JSON_UNESCAPED_UNICODE);exit;}
 $answer=(new App\Services\ChatAvailabilityService())->answer(mb_substr((string)($input['question']??''),0,1000),[]);
 echo json_encode($answer?:['handled'=>false,'answer'=>'Informe as datas de entrada e saída e o número de hóspedes.'],JSON_UNESCAPED_UNICODE);exit;
}
throw new RuntimeException('Invalid local action');
