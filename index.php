<?php
define('SUITES_PAGE_ENTRY', true);

require __DIR__.'/app/bootstrap.php';
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;
use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
require_once __DIR__.'/modules/crm/bootstrap.php';

Auth::start();
$p=$_GET['p']??'home';
if(!is_file(__DIR__.'/config/config.php') && $p!=='install'){header('Location:install.php');exit;}

require __DIR__.'/app/Views/helpers.php';
try{
$pdo=DB::pdo();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($file=\App\Core\PageRoutes::resolve($p,$method,'public'))require $file;
requireAdmin();
if(in_array($p,['crm360','crm_contacts','crm_leads','crm_tasks'],true))require __DIR__.'/modules/crm/route-access.php';
if($file=\App\Core\PageRoutes::resolve($p,$method,'admin'))require $file;
http_response_code(404);render('Não encontrado','<h1>Página não encontrada</h1>');
}catch(Throwable $e){
  http_response_code(500);
  $debug=false;if(is_file(__DIR__.'/config/config.php')){$c=require __DIR__.'/config/config.php';$debug=(bool)($c['app']['debug']??false);}
  $msg=$debug?$e->getMessage():'O sistema encontrou um erro interno. Consulte storage/logs/app.log.';
  @file_put_contents(__DIR__.'/storage/logs/app.log',date('c').' '.$e->__toString().PHP_EOL,FILE_APPEND);
  render('Erro interno','<div class="alert error"><h2>Erro interno</h2><p>'.U::e($msg).'</p></div>');
}
