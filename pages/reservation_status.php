<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('reservations.manage');$service=new ReservationService();
  if($_SERVER['REQUEST_METHOD']==='POST'){Csrf::check();try{$service->changeStatus((int)$_POST['id'],$_POST['status'],$_POST['reason']??'');U::flash('ok','Status atualizado.');}catch(Throwable $e){U::flash('err',$e->getMessage());}U::redirect('index.php?p=reservations');}
