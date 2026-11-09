<?php
declare(strict_types=1);
// Direct physical module entry: this file is included by /painel/<module>.php.
// Shared authentication/bootstrap only; the module is loaded from its own file.
if(!defined('SUITES_DIRECT_MODULE')||!is_string($p??null)){http_response_code(404);exit;}
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
$_GET['p']=$p;
if(!is_file(dirname(__DIR__).'/config/config.php')){http_response_code(503);exit('Sistema ainda não configurado.');}
require dirname(__DIR__).'/app/Views/helpers.php';
try {
 $pdo=DB::pdo();$method=(string)($_SERVER['REQUEST_METHOD']??'GET');
 if($p==='login'){
  if(Auth::user())U::redirect('/painel/dashboard.php');
  $file=\App\Core\PageRoutes::resolve('login',$method,'public');
 }else{
  Auth::requireLogin();
  if(in_array($p,['crm360','crm_contacts','crm_leads','crm_tasks'],true))require dirname(__DIR__).'/modules/crm/route-access.php';
  $file=\App\Core\PageRoutes::resolve($p,$method,'admin');
 }
 if(!$file){http_response_code(404);exit('Módulo inexistente.');}
 require $file;
}catch(\Throwable $e){
 http_response_code(500);
 @file_put_contents(dirname(__DIR__).'/storage/logs/app.log',date('c').' PANEL_MODULE '.$e->__toString().PHP_EOL,FILE_APPEND);
 $message='Ocorreu um erro interno. Verifique o registro de erros.';
 render('Erro interno','<div class="alert error">'.U::e($message).'</div>');
}
