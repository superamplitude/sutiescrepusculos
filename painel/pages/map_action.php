<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('calendar.block');Csrf::check();$service=new ReservationService();
  $room=(int)($_POST['room_id']??0);$a=(string)($_POST['start_date']??'');$b=(string)($_POST['end_date']??'');
  try{
    if(!$room||!$a||!$b||$b<=$a)throw new RuntimeException('Selecione unidade e período válidos.');
    if(!$service->available($room,$a,$b))throw new RuntimeException('O período escolhido já possui reserva ou bloqueio.');
    $type=in_array($_POST['type']??'', ['blocked','maintenance'],true)?$_POST['type']:'blocked';
    $pdo->prepare("INSERT INTO room_blocks(room_id,start_date,end_date,type,reason,created_by,created_at) VALUES(?,?,?,?,?,?,NOW())")->execute([$room,$a,$b,$type,trim((string)($_POST['reason']??'')),Auth::user()['id']]);
    $id=(int)$pdo->lastInsertId();Audit::log('calendar.block','room_block',$id);U::flash('ok','Período gravado no mapa.');
  }catch(Throwable $e){U::flash('err',$e->getMessage());}
  U::redirect('index.php?p=map&start='.urlencode($a?:date('Y-m-d')));
