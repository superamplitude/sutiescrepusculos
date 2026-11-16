<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

/**
 * Rewrite legacy links without losing the first query-string delimiter.
 * Otherwise index.php?p=messages&id=123 becomes /painel/messages.php&id=123.
 */
function panelRewriteModuleLinks(string $content,array $routes):string {
  $names=array_keys($routes);
  usort($names,static fn($a,$b)=>strlen($b)<=>strlen($a));
  foreach($names as $name){
    if(($routes[$name]['access']??'')!=='admin')continue;
    $pattern='~(?<![a-zA-Z0-9/])index\.php\?p='.preg_quote($name,'~').'(?=&(?:amp;)?|["\x27\s<>#]|$)~';
    $content=preg_replace($pattern,'/painel/'.$name.'.php',$content);
    $content=preg_replace('~(/painel/'.preg_quote($name,'~').'\.php)&(?:amp;)?(?=[a-zA-Z_][a-zA-Z0-9_]*=)~','$1?',$content);
  }
  return $content;
}

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
    $content=panelRewriteModuleLinks($content,$list);
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
// Standard compact icon for actionable administration cards; independent of CDN/fonts.
function panelCardIcon(string $name):string{
  $paths=[
    'payments'=>'<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'pix'=>'<path d="m12 2 4 4-4 4-4-4zM12 14l4 4-4 4-4-4zM2 12l4-4 4 4-4 4zM14 12l4-4 4 4-4 4z"/>',
    'cash'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 7h8M8 12h8M8 17h5"/>',
    'instagram'=>'<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor" stroke="none"/>',
    'facebook'=>'<path d="M15 21v-8h3l.5-4H15V7c0-1 .5-2 2-2h2V2h-3c-4 0-5 2-5 5v2H8v4h3v8"/>',
    'email'=>'<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m3 6 9 7 9-7"/>',
    'maps'=>'<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',
    'analytics'=>'<path d="M4 20V12M10 20V8M16 20V4M22 20V11"/>',
    'search'=>'<circle cx="10" cy="10" r="7"/><path d="m15 15 6 6"/>',
    'tag'=>'<path d="M3 4h10l8 8-9 9-9-9z"/><circle cx="8.5" cy="8.5" r="1"/>',
    'key'=>'<circle cx="8" cy="15" r="5"/><path d="M12 11 21 2l1 4-3 1-1 3-3 1"/>',
    'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6M17 2v6M3 10h18"/>',
    'home'=>'<path d="m3 11 9-8 9 8v10h-7v-6h-4v6H3z"/>',
    'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19 13a7 7 0 0 0 0-2l2-2-2-3-3 1a7 7 0 0 0-2-1l-1-3h-4L8 6a7 7 0 0 0-2 1L3 6 1 9l2 2a7 7 0 0 0 0 2l-2 2 2 3 3-1a7 7 0 0 0 2 1l1 3h4l1-3a7 7 0 0 0 2-1l3 1 2-3z"/>',
    'chat'=>'<path d="M20 4H4a2 2 0 0 0-2 2v11h5l5 4 5-4h3a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/><path d="M7 9h10M7 13h7"/>',
    'video'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3z"/>'
  ];
  $drawing=$paths[$name]??$paths['settings'];
  return '<span class="panel-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$drawing.'</svg></span>';
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
  // Media must resolve at the public origin even when opened from /painel/.
  if(str_starts_with($url,'storage/uploads/')||str_starts_with($url,'assets/'))return '/'.$url;
  if(str_starts_with($url,'/storage/uploads/')||str_starts_with($url,'/assets/'))return $url;
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
