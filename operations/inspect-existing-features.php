<?php
if(getenv('RUNNER_NAME')!=='gerente-vps')exit(64);
$root='/home/sasuites/htdocs/suitescrepusculo.com.br';
echo "ENTRY_SHA256=".hash_file('sha256',$root.'/index.php')."\n";
foreach(['app/Views/layout.php','app/Services/AelWaCallsService.php','app/Services/AiConversationService.php','app/Services/CrmSyncService.php','pages/map.php','pages/integrations.php'] as $name){
 echo "=== ".$name." ===\n";
 if(!is_file($root.'/'.$name))continue;
 foreach(file($root.'/'.$name) as $n=>$line){
  if(preg_match('/function |class |voice|tts|microphone|audio|panel|central|chatwoot|wacall|gemini|api\\.superamplitude|map-toolbar|mapnav|reservation_new|rooms|automation/i',$line)){
   echo ($n+1).':'.substr($line,0,1500)."\n";
  }
 }
}
