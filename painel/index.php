<?php
declare(strict_types=1);
// One real administrator dispatcher. Shares the original DB, auth and services,
// but renders the files physically housed under /painel/pages/ without redirecting.
define('SUITES_PAGE_ENTRY',true);
define('SUITES_PANEL_ENTRY',true);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\DB;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\U;
use App\Core\Audit;
use App\Core\Secret;
use App\Core\Site;
use App\Services\ReservationService;
use App\Services\GeminiService;
use App\Services\UploadService;
use App\Services\ICalService;
use App\Controllers\WhatsappController;
require_once dirname(__DIR__).'/modules/crm/bootstrap.php';
Auth::start();
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$p=(string)($_GET['p']??(Auth::user()?'dashboard':'login'));
if($p==='')$p=Auth::user()?'dashboard':'login';
if(!is_file(dirname(__DIR__).'/config/config.php')){
 header('Location: /install.php',true,302);exit;
}
require dirname(__DIR__).'/app/Views/helpers.php';
try{
 $pdo=DB::pdo();
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($p==='login'){
   if(Auth::user()){U::redirect('/painel/?p=dashboard');}
   if($file=\App\Core\PageRoutes::resolve('login',$method,'public')){require $file;exit;}
 }else{
   Auth::requireLogin();
   if(in_array($p,['crm360','crm_contacts','crm_leads','crm_tasks'],true))require dirname(__DIR__).'/modules/crm/route-access.php';
   if($file=\App\Core\PageRoutes::resolve($p,$method,'admin')){require $file;exit;}
 }
 http_response_code(404);render('Não encontrado','<h2>Módulo não encontrado</h2>');
}catch(\Throwable $e){
 http_response_code(500);
 $debug=false;
 if(is_file(dirname(__DIR__).'/config/config.php')){
   $cfg=require dirname(__DIR__).'/config/config.php';
   $debug=(bool)($cfg['app']['debug']??false);
 }
 $msg=$debug?$e->getMessage():'Ocorreu um erro interno. Consulte o registro de erros do sistema.';
 @file_put_contents(dirname(__DIR__).'/storage/logs/app.log',date('c').' '.$e->__toString().PHP_EOL,FILE_APPEND);
 render('Erro interno','<div class="alert error"><h2>Erro interno</h2><p>'.U::e($msg).'</p></div>');
}
