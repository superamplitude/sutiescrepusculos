<?php
require __DIR__.'/app/bootstrap.php';
use App\Services\ICalService;
try{
  $token=trim($_GET['token']??'');if($token==='')throw new RuntimeException('Token ausente.');
  $ics=(new ICalService())->export($token);
  header('Content-Type: text/calendar; charset=utf-8');header('Content-Disposition: inline; filename="calendario.ics"');header('Cache-Control: no-store');
  echo $ics;
}catch(Throwable $e){http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Feed não disponível.';}
