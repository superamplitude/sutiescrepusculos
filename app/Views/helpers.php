<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

function render(string $title,string $html,string $meta=''):void{
  $content=$html;
  $metaDescription=$meta;
  if(defined('SUITES_PANEL_ENTRY')){
    if(($_GET['p']??'')==='login' && !Auth::user()){include __DIR__.'/panel-login.php';return;}
    $list=\App\Core\PageRoutes::all();
    // All buttons and forms target their real module pages under /painel/.
    // Public links still resolve against the public application root.
    $names=array_keys($list);
    usort($names,static fn($a,$b)=>strlen($b)<=>strlen($a));
    foreach($names as $name){
      $target=$list[$name]['access']==='admin'?'/painel/?p='.$name:'/index.php?p='.$name;
      $content=preg_replace('~(?<![a-zA-Z0-9/])index\.php\?p='.preg_quote($name,'~').'(?=[&"\'\s<>#]|$)~',$target,$content);
    }
    $content=preg_replace('~\b(src|href|poster)=(["\'])((?:assets|storage)/)~','$1=$2/$3',$content);
  }
  include __DIR__.'/layout.php';
}
function row(string $label,string $name,mixed $value='',string $type='text'):string{
  $e=htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
  if($type==='textarea')return "<label>$label<textarea name=\"$name\">$e</textarea></label>";
  if($type==='checkbox')return "<label class=\"toggle-field\"><span class=\"toggle-label\">$label</span><span class=\"toggle-control\"><input type=\"checkbox\" name=\"$name\" value=\"1\" ".($value?'checked':'')."><span class=\"toggle-slider\"></span><span class=\"toggle-state toggle-no\">Não</span><span class=\"toggle-state toggle-yes\">Sim</span></span></label>";
  return "<label>$label<input type=\"$type\" name=\"$name\" value=\"$e\"></label>";
}
function adminTitle(string $t,string $sub=''):string{return "<div class=\"page-head\"><div><h1>$t</h1><p>$sub</p></div></div>";}
function statusPt(string $s):string{
  return [
    'active'=>'Ativo','inactive'=>'Inativo','maintenance'=>'Manutenção',
    'pre_reserva'=>'Pré-reserva','aguardando_aprovacao'=>'Aguardando aprovação',
    'aguardando_pagamento'=>'Aguardando pagamento','paga'=>'Paga','confirmada'=>'Confirmada',
    'recusada'=>'Recusada','expirada'=>'Expirada','cancelada'=>'Cancelada','reembolsada'=>'Reembolsada',
    'concluida'=>'Concluída','no_show'=>'Não compareceu','pending'=>'Pendente','paid'=>'Pago',
    'published'=>'Publicado','rejected'=>'Rejeitado','draft'=>'Rascunho',
    'not_configured'=>'Não configurado','configured_unverified'=>'Configurado, não testado','verified'=>'Testado'
  ][$s]??$s;
}
function money(float $value):string{
  static $currency=null;
  if($currency===null){try{$currency=(string)(DB::pdo()->query("SELECT currency FROM property WHERE id=1")->fetchColumn()?:'BRL');}catch(Throwable $e){$currency='BRL';}}
  return U::money($value,$currency);
}
function safeUrl(?string $url):string{
  $url=trim((string)$url);if($url==='')return '';
  if(str_starts_with($url,'storage/uploads/')||str_starts_with($url,'assets/'))return $url;
  $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
  return in_array($scheme,['http','https'],true)?$url:'';
}
function mediaPreview(?string $path,string $type='image'):string{
  $path=safeUrl($path);if(!$path)return '';
  $e=U::e($path);
  return $type==='video' ? "<video class=\"media-preview\" controls src=\"$e\"></video>" : "<img class=\"media-preview\" src=\"$e\" alt=\"Pré-visualização\">";
}
function requireAdmin():void{Auth::requireLogin();}
function currentCompanyId():int{$u=Auth::user();return max(1,(int)($u['company_id']??1));}
function jsonOut(array $data,int $status=200):void{
  http_response_code($status);
  header('Content-Type: application/json; charset=UTF-8');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
