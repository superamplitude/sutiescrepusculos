<?php
if(getenv('RUNNER_NAME')!=='gerente-vps')exit(64);
$root='/home/sasuites/htdocs/suitescrepusculo.com.br';
foreach(['index.php','app/bootstrap.php'] as $name){
 $lines=file($root.'/'.$name);
 echo "=== ".$name." HEADER ===\n";
 foreach(array_slice($lines,0,100,true) as $n=>$line)echo ($n+1).':'.$line;
 if($name==='index.php'){echo "=== FOOTER ===\n";foreach(array_slice($lines,-30,30,true) as $n=>$line)echo ($n+1).':'.$line;}
}
echo "=== APP FILES ===\n";
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app',FilesystemIterator::SKIP_DOTS));
foreach($it as $f)if($f->getExtension()==='php')echo substr($f->getPathname(),strlen($root)+1)."\n";
