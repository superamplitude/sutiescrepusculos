<?php
declare(strict_types=1);
// Materialize the 1:1 physical admin entrypoints from the versioned route registry.
// This does not issue redirects, create another server, or alter public pages.
$root=dirname(__DIR__);
$routes=json_decode((string)file_get_contents($root.'/config/page-routes.json'),true,512,JSON_THROW_ON_ERROR);
if(!is_array($routes))throw new RuntimeException('Route registry inválido.');
$created=[];
foreach($routes as $name=>$route){
 if(($route['access']??'')!=='admin'&&$name!=='login')continue;
 if(!preg_match('/^[a-z][a-z0-9_]*$/D',(string)$name))throw new RuntimeException('Route inválida.');
 $target=$root.'/painel/'.$name.'.php';
 $body="<?php\ndeclare(strict_types=1);\n\$p='".str_replace("'","",(string)$name)."';\ndefine('SUITES_DIRECT_MODULE',true);\nrequire __DIR__.'/_direct.php';\n";
 if(is_file($target)){
   if(file_get_contents($target)!==$body)throw new RuntimeException('Arquivo físico não gerenciado: '.$name);
   continue;
 }
 if(file_exists($target))throw new RuntimeException('Destino inesperado: '.$name);
 if(file_put_contents($target,$body,LOCK_EX)===false)throw new RuntimeException('Não foi possível criar '.$name);
 chmod($target,0644);$created[]=$target;
}
echo 'ADMIN_PHYSICAL_PAGES_CREATED='.count($created)."\n";
