<?php
declare(strict_types=1);
$root=$argv[1];require $root.'/app/bootstrap.php';
$routes=App\Core\PageRoutes::all();$seen=[];
foreach($routes as $name=>$r){
 if($r['file']!==$name.'.php'||isset($seen[$r['file']]))throw new RuntimeException('Non-independent page');
 $seen[$r['file']]=true;$s=file_get_contents($root.'/pages/'.$r['file']);
 if(!str_contains($s,'SUITES_PAGE_ENTRY'))throw new RuntimeException('Missing direct-access guard');
 if(!in_array($r['access'],['public','admin'],true))throw new RuntimeException('Missing access boundary');
}
if(count($routes)!==61)throw new RuntimeException('Unexpected route count');
if(strlen(file_get_contents($root.'/index.php'))>2500)throw new RuntimeException('Router became a monolith');
$values=['Total R$ 1.234,56, entrada em 12/12 às 14h.','Olá, posso ajudar?'];
foreach($values as $s)if(App\Services\VoiceProfileService::spokenText($s)!==$s)throw new RuntimeException('Changed facts');
$long=str_repeat('Esta é uma frase completa. ',70);$spoken=App\Services\VoiceProfileService::spokenText($long);
if(mb_strlen($spoken)>1200||!str_ends_with($spoken,'partes.'))throw new RuntimeException('Unsafe clipping');
$p=App\Services\VoiceProfileService::normalize(['tone'=>'injected','pace'=>'injected']);
if($p['tone']!=='natural'||$p['pace']!=='equilibrado')throw new RuntimeException('Unsafe voice profile');
echo "PAGE_VOICE_CONTRACT_OK: 61 independent routes; guarded modules; small router; exact values/dates; complete sentences; validated profile\n";
