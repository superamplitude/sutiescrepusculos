<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requireSuperAdmin();Csrf::check();
  if(($_POST['calendar_mode']??'')==='channel'){
    $feedId=(int)($_POST['feed_id']??0);
    try{
      $result=(new ICalService())->syncChannel($feedId);
      $note=($result['preserved']??false)?'Calendário vazio: bloqueios anteriores preservados.':($result['imported'].' período(s) sincronizado(s), '.$result['conflicts'].' conflito(s).');
      U::flash(($result['conflicts']??0)?'err':'ok',$note);
    }catch(Throwable $e){U::flash('err',$e->getMessage());}
    U::redirect('index.php?p=integrations#ical-channels');
  }
  $id=(int)$_POST['feed_id'];
  try{$count=(new ICalService())->sync($id);U::flash('ok',"iCal sincronizado: $count período(s) importado(s).");}catch(Throwable $e){U::flash('err',$e->getMessage());}
  U::redirect('index.php?p=integrations#ical-rooms');
