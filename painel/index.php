<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
\App\Core\Auth::start();
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$route=(string)($_GET['p']??'');
if($route===''||$route==='login'){
 if(\App\Core\Auth::user()){header('Location: /index.php?p=dashboard',true,302);exit;}
 $_GET['p']='login';
 define('SUITES_PANEL_ENTRY',true);
 require dirname(__DIR__).'/index.php';
 exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');exit;}
if(!preg_match('/^[a-z][a-z0-9_]*$/D',$route)){http_response_code(404);exit;}
if(!\App\Core\Auth::user()){header('Location: /painel/',true,302);exit;}
$args=$_GET;unset($args['p']);
$target='/index.php?p='.rawurlencode($route);
if($args)$target.='&'.http_build_query($args,'','&',PHP_QUERY_RFC3986);
header('Location: '.$target,true,302);
exit;
