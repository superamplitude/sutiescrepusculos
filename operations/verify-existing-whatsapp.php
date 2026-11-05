<?php
if(getenv('RUNNER_NAME')!=='gerente-vps')exit(64);
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
$svc=new App\Services\AelWaCallsService();
$health=$svc->health();echo 'WHATSAPP_GATEWAY_HEALTH='.(int)!empty($health['ok'])."\n";
try{$s=$svc->status(1);echo json_encode(['session_exists'=>(bool)($s['exists']??false),'paired'=>(bool)($s['paired']??false),'state'=>$s['state']??'unknown'])."\n";}catch(Throwable $e){echo 'SESSION_STATUS_ERROR='.get_class($e)."\n";}
$pdo=App\Core\DB::pdo();$c=$pdo->query('SELECT enabled,api_key FROM ai_config WHERE id=1')->fetch();
echo 'SITE_AI_ENABLED='.(int)!empty($c['enabled'])."\nSITE_AI_KEY_PRESENT=".(int)!empty($c['api_key'])."\n";
