<?php
if(getenv('RUNNER_NAME')!=='gerente-vps')exit(64);
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
$svc=new App\Services\AelWaCallsService();$before=$svc->status(1);
if(!empty($before['paired'])){echo "WHATSAPP_ALREADY_PAIRED_PRESERVED=1\n";exit;}
if(!empty($before['exists'])){echo "EXISTING_UNPAIRED_SESSION_PRESERVED=1\n";exit;}
try{
 $result=$svc->pair(1);$qr=(string)($result['qr']??'');
 if(empty($result['connected'])&&strlen($qr)<30)throw new RuntimeException('Pairing response has no valid QR payload');
 echo 'PAIRING_QR_GENERATED='.(int)(strlen($qr)>=30)."\nPAIRING_QR_CHARACTERS=".strlen($qr)."\n";
 echo 'WHATSAPP_PAIRED='.(int)!empty($result['connected'])."\n";
 echo "EXTERNAL_MESSAGES_SENT=0\nEXTERNAL_CALLS_STARTED=0\n";
}catch(Throwable $e){
 $now=$svc->status(1);
 if(!empty($now['exists'])&&empty($now['paired']))$svc->disconnect(1);
 throw $e;
}
