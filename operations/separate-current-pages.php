<?php
// Source-preserving page extraction; no DB access, backups or service changes.
if (getenv('RUNNER_NAME') !== 'gerente-vps') exit(64);
$root='/home/sasuites/htdocs/suitescrepusculo.com.br';
$source=file_get_contents($root.'/index.php');
$expected='3082b745d391618860df038d9799def4343339d9057eb9b0efac85c5725873e9';
if(hash('sha256',$source)!==$expected) throw new RuntimeException('Source changed; refuse stale transformation');
$imports='use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;';
$ts=token_get_all($source, TOKEN_PARSE); $offsets=[];$pos=0;
foreach($ts as $t){$offsets[]=$pos;$pos+=strlen(is_array($t)?$t[1]:$t);}
function tv($t){return is_array($t)?$t[1]:$t;}
function opens($t){return $t==='{' || (is_array($t)&&in_array($t[0],[T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES],true));}
function nextCode($ts,$i){while(isset($ts[$i])&&is_array($ts[$i])&&in_array($ts[$i][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$i++;return $i;}
$depth=0;$routes=[];$replacements=[];$pages=[];
for($i=0;$i<count($ts);$i++){
 $t=$ts[$i];
 if(is_array($t)&&$t[0]===T_IF){
  $j=nextCode($ts,$i+1);
  if(($ts[$j]??null)!=='(')throw new RuntimeException('Unexpected if syntax');
  $startCondition=$j;$par=0;
  do{$v=tv($ts[$j]);if($v==='(')$par++;if($v===')')$par--;$j++;}while($par>0);
  $endCondition=$j-1;
  $cond=substr($source,$offsets[$startCondition],$offsets[$endCondition]+1-$offsets[$startCondition]);
  // All route selectors occur at the outer try scope (depth 1).
  if($depth===1&&preg_match('/\\$p\\s*===|in_array\\(\\$p,/', $cond)){
   $b=nextCode($ts,$j);
   if(($ts[$b]??null)!=='{')throw new RuntimeException('Route requires explicit braces');
   $level=1;$e=$b+1;
   while($level&&isset($ts[$e])){if(opens($ts[$e]))$level++;if($ts[$e]==='}')$level--;$e++;}
   if($level)throw new RuntimeException('Unbalanced route');
   $after=nextCode($ts,$e);
   if(isset($ts[$after])&&is_array($ts[$after])&&in_array($ts[$after][0],[T_ELSE,T_ELSEIF],true))throw new RuntimeException('Route has else branch; manual extraction required');
   preg_match_all("/'([a-z][a-z0-9_]*)'/",$cond,$names);
   $name=implode('-',array_unique($names[1]));
   if(!$name||isset($pages[$name]))throw new RuntimeException('Ambiguous page name: '.$name.' line '.$t[2].' condition '.$cond);
   $body='';
   for($k=$b+1;$k<$e-1;$k++){
    $tok=$ts[$k];
    if(is_array($tok)&&$tok[0]===T_DIR)$body.="dirname(__DIR__)";
    elseif(is_array($tok)&&$tok[0]===T_FILE)$body.="(dirname(__DIR__).'/index.php')";
    elseif(is_array($tok)&&$tok[0]===T_LINE)$body.=(string)$tok[2];
    else $body.=tv($tok);
   }
   $pages[$name]="<?php\n".$imports."\nif (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }\n".$body;
   $replacement="if".$cond."{require __DIR__.'/pages/".$name.".php';}";
   $begin=$offsets[$i];$end=$offsets[$e-1]+1;
   $replacements[]=[$begin,$end,$replacement];
   $routes[]=['page'=>$name,'selectors'=>$names[1],'line'=>$t[2],'bytes'=>$end-$begin];
   $i=$e-1; continue;
  }
 }
 if(opens($t))$depth++;
 if($t==='}')$depth--;
}
if($depth!==0)throw new RuntimeException('Tokenizer scope mismatch: '.$depth);
if(count($pages)<40)throw new RuntimeException('Expected at least 40 route modules; got '.count($pages));
$candidate=$source;
foreach(array_reverse($replacements) as [$b,$e,$r])$candidate=substr_replace($candidate,$r,$b,$e-$b);
$phpOpen=strpos($candidate,'<?php');
if($phpOpen===false)throw new RuntimeException('Missing PHP entry tag');
$candidate=substr_replace($candidate,"\ndefine('SUITES_PAGE_ENTRY', true);\n",$phpOpen+5,0);
function lintText($s){
 $pipes=[];$p=proc_open([PHP_BINARY,'-l'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
 fwrite($pipes[0],$s);fclose($pipes[0]);$out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 if(proc_close($p)!==0)throw new RuntimeException('PHP lint failed: '.$out);
}
lintText($candidate);foreach($pages as $s)lintText($s);
echo "BASELINE_SHA256=".$expected."\nMODULE_COUNT=".count($pages)."\nENTRY_BYTES=".strlen($candidate)."\n";
foreach($routes as $r)echo json_encode($r,JSON_UNESCAPED_SLASHES)."\n";
echo "ALL_PHP_LINT_OK\n";
if(($argv[1]??'plan')!=='apply')exit;
function checkUrl($route){
 $url='https://suitescrepusculo.com.br/'.($route==='direct'?'pages/home.php':'index.php?p='.rawurlencode($route));
 $pipes=[];$p=proc_open(['curl','-sS','-L','--max-time','20','-w',"\n%{http_code}",$url],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
 fclose($pipes[0]);$body=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 if(proc_close($p)!==0)throw new RuntimeException('HTTP test failed');
 $split=strrpos($body,"\n");$status=(int)substr($body,$split+1);$html=substr($body,0,$split);
 preg_match('/<title[^>]*>(.*?)<\/title>/is',$html,$m);
 return ['status'=>$status,'title'=>$m[1]??'','bytes'=>strlen($html)];
}
$before=[];foreach(['home','about','accommodations','availability','login','map'] as $r){$before[$r]=checkUrl($r);if($before[$r]['status']!==200)throw new RuntimeException('Baseline HTTP failure: '.$r);}

if(is_dir($root.'/pages'))throw new RuntimeException('Existing pages directory; refuse overwrite');
$fp=fopen($root.'/index.php','r+b');if(!flock($fp,LOCK_EX))throw new RuntimeException('Cannot lock entry');
if(hash('sha256',stream_get_contents($fp))!==$expected)throw new RuntimeException('Concurrent source edit');
$created=[];
try{
 if(!mkdir($root.'/pages',0755))throw new RuntimeException('Cannot create pages');
 foreach($pages as $name=>$text){
  $path=$root.'/pages/'.$name.'.php';
  if(file_put_contents($path,$text,LOCK_EX)!==strlen($text))throw new RuntimeException('Incomplete page write');
  chown($path,fileowner($root.'/index.php'));chgrp($path,filegroup($root.'/index.php'));chmod($path,0644);$created[]=$path;
 }
 chown($root.'/pages',fileowner($root.'/index.php'));chgrp($root.'/pages',filegroup($root.'/index.php'));
 rewind($fp);if(fwrite($fp,$candidate)!==strlen($candidate))throw new RuntimeException('Incomplete entry write');
 ftruncate($fp,strlen($candidate));fflush($fp);fsync($fp);
 if(hash_file('sha256',$root.'/index.php')!==hash('sha256',$candidate))throw new RuntimeException('Entry integrity failure');
 // FPM opcode cache revalidates source timestamps; wait once before live checks.
 sleep(3);
 foreach($before as $r=>$baseline){
  $after=checkUrl($r);
  if($after['status']!==$baseline['status'] || $after['title']!==$baseline['title'] || abs($after['bytes']-$baseline['bytes'])>max(500,$baseline['bytes']*.05))throw new RuntimeException('HTTP regression: '.$r.' baseline='.json_encode($baseline).' after='.json_encode($after));
  echo "HTTP_PARITY_OK=".$r."\n";
 }
 if(checkUrl('direct')['status']!==404)throw new RuntimeException('Direct module access must be blocked');
 echo "DIRECT_MODULE_ACCESS=404\n";
 echo "PAGES_APPLIED=".count($pages)."\nDATABASE_WRITES=0\nBACKUPS_CREATED=0\nRESTARTS=0\n";
}catch(Throwable $e){
 rewind($fp);fwrite($fp,$source);ftruncate($fp,strlen($source));fflush($fp);fsync($fp);
 foreach($created as $path)unlink($path);if(is_dir($root.'/pages'))rmdir($root.'/pages');throw $e;
}finally{flock($fp,LOCK_UN);fclose($fp);}
