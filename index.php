<?php
define('SUITES_PAGE_ENTRY', true);

require __DIR__.'/app/bootstrap.php';
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;
use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
require_once __DIR__.'/modules/crm/bootstrap.php';

Auth::start();
$p=$_GET['p']??'home';
if(!is_file(__DIR__.'/config/config.php') && $p!=='install'){header('Location:install.php');exit;}

function render(string $title,string $html,string $meta=''):void{ob_start();echo $html;$content=ob_get_clean();$metaDescription=$meta;include __DIR__.'/app/Views/layout.php';}
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

try{
$pdo=DB::pdo();

if($p==='logout'){require __DIR__.'/pages/logout.php';}
if($p==='login'){require __DIR__.'/pages/login.php';}

if($p==='home'){require __DIR__.'/pages/home.php';}

if($p==='about'||$p==='location'){require __DIR__.'/pages/about-location.php';}
if($p==='accommodations'||$p==='room'){require __DIR__.'/pages/accommodations-room.php';}
if($p==='gallery_public'){require __DIR__.'/pages/gallery_public.php';}

if($p==='page'){require __DIR__.'/pages/page.php';}
if(in_array($p,['promotions_public','amenities_public','gastronomy','faq','contact','policies','things_to_do'],true)){require __DIR__.'/pages/promotions_public-amenities_public-gastronomy-faq-contact-policies-things_to_do.php';}
if($p==='availability'){require __DIR__.'/pages/availability.php';}
if($p==='reserve'&&$_SERVER['REQUEST_METHOD']==='POST'){require __DIR__.'/pages/reserve.php';}
if($p==='traveler_logout'){require __DIR__.'/pages/traveler_logout.php';}
if($p==='traveler_switch'){require __DIR__.'/pages/traveler_switch.php';}
if($p==='traveler'){require __DIR__.'/pages/traveler.php';}
if($p==='voucher'){require __DIR__.'/pages/voucher.php';}


requireAdmin();

if($p==='dashboard'){require __DIR__.'/pages/dashboard.php';}

if($p==='site_admin'){require __DIR__.'/pages/site_admin.php';}


if($p==='hero'){require __DIR__.'/pages/hero.php';}

if($p==='property'){require __DIR__.'/pages/property.php';}

if($p==='rooms'){require __DIR__.'/pages/rooms.php';}

if($p==='reservations'){require __DIR__.'/pages/reservations.php';}

if($p==='map'){require __DIR__.'/pages/map.php';}
if($p==='map_reservation_api'){require __DIR__.'/pages/map_reservation_api.php';}

if($p==='map_action'&&$_SERVER['REQUEST_METHOD']==='POST'){require __DIR__.'/pages/map_action.php';}

if($p==='block_edit'){require __DIR__.'/pages/block_edit.php';}

if($p==='reservation_edit'){require __DIR__.'/pages/reservation_edit.php';}

if($p==='reservation_new'){require __DIR__.'/pages/reservation_new.php';}

if($p==='guests'){require __DIR__.'/pages/guests.php';}

if($p==='tariffs'){require __DIR__.'/pages/tariffs.php';}

if($p==='promotions'){require __DIR__.'/pages/promotions.php';}

if($p==='payments'){require __DIR__.'/pages/payments.php';}

if($p==='contacts_admin'){require __DIR__.'/pages/contacts_admin.php';}

if($p==='content'){require __DIR__.'/pages/content.php';}

if($p==='media_delete'&&$_SERVER['REQUEST_METHOD']==='POST'){require __DIR__.'/pages/media_delete.php';}
if($p==='gallery'){require __DIR__.'/pages/gallery.php';}

if($p==='attractions'){require __DIR__.'/pages/attractions.php';}

if($p==='reviews'){require __DIR__.'/pages/reviews.php';}


if(in_array($p,['crm360','crm_contacts','crm_leads','crm_tasks'],true)){require __DIR__.'/pages/crm360-crm_contacts-crm_leads-crm_tasks.php';}

if($p==='crm360'){require __DIR__.'/pages/crm360.php';}

if($p==='crm_contacts'){require __DIR__.'/pages/crm_contacts.php';}

if($p==='crm_leads'){require __DIR__.'/pages/crm_leads.php';}

if($p==='crm_tasks'){require __DIR__.'/pages/crm_tasks.php';}
if($p==='reports'){require __DIR__.'/pages/reports.php';}

if($p==='ai'){require __DIR__.'/pages/ai.php';}
if($p==='ai_test'&&$_SERVER['REQUEST_METHOD']==='POST'){require __DIR__.'/pages/ai_test.php';}

if($p==='whatsapp_rnl'){require __DIR__.'/pages/whatsapp_rnl.php';}
if($p==='whatsapp_rnl_legacy'){require __DIR__.'/pages/whatsapp_rnl_legacy.php';}

if($p==='ical_save'&&$_SERVER['REQUEST_METHOD']==='POST'){require __DIR__.'/pages/ical_save.php';}
if($p==='ical_sync'&&$_SERVER['REQUEST_METHOD']==='POST'){require __DIR__.'/pages/ical_sync.php';}
if($p==='integrations'){require __DIR__.'/pages/integrations.php';}

if($p==='users'){require __DIR__.'/pages/users.php';}

if($p==='user_edit'){require __DIR__.'/pages/user_edit.php';}
if($p==='audit'){require __DIR__.'/pages/audit.php';}
if($p==='messages'){require __DIR__.'/pages/messages.php';}


http_response_code(404);render('Não encontrado','<h1>Página não encontrada</h1>');
}catch(Throwable $e){
  http_response_code(500);
  $debug=false;if(is_file(__DIR__.'/config/config.php')){$c=require __DIR__.'/config/config.php';$debug=(bool)($c['app']['debug']??false);}
  $msg=$debug?$e->getMessage():'O sistema encontrou um erro interno. Consulte storage/logs/app.log.';
  @file_put_contents(__DIR__.'/storage/logs/app.log',date('c').' '.$e->__toString().PHP_EOL,FILE_APPEND);
  render('Erro interno','<div class="alert error"><h2>Erro interno</h2><p>'.U::e($msg).'</p></div>');
}
