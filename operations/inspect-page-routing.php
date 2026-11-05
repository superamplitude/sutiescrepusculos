<?php
if (getenv('RUNNER_NAME') !== 'gerente-vps') { exit(64); }
$p='/home/sasuites/htdocs/suitescrepusculo.com.br/index.php';
$s=file_get_contents($p);
echo "SOURCE_SHA256=".hash('sha256',$s)."\n";
echo "PHP_VERSION=".PHP_VERSION."\n";
echo "SOURCE_BYTES=".strlen($s)."\n";
$tokens=token_get_all($s); $depth=0; $line=1; $routes=0;
foreach($tokens as $i=>$t) {
 $v=is_array($t)?$t[1]:$t;
 if(is_array($t)) $line=$t[2];
 if(is_array($t) && $t[0]===T_IF) {
  $cond=''; $par=0;
  for($j=$i+1;$j<count($tokens);$j++) {
   $v2=is_array($tokens[$j])?$tokens[$j][1]:$tokens[$j];
   if($v2==='(') $par++;
   if($v2===')') $par--;
   $cond.=$v2;
   if($v2===')' && $par===0) break;
  }
  if(preg_match('/\\$(p|page|path|route|uri)\\b/', $cond)) {
   echo "DEPTH=".$depth." ROUTE_LINE=".$line." CONDITION=".preg_replace('/\\s+/',' ',substr($cond,0,400))."\n"; $routes++;
  }
 }
 if(!is_array($t) && $v==='{') $depth++;
 if(!is_array($t) && $v==='}') $depth--;
}
echo "ROUTES=".$routes."\n";
echo "EXISTING_PAGE_DIR=".(is_dir(dirname($p).'/pages')?'yes':'no')."\n";
