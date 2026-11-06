<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('reservations.manage');
  $service=new ReservationService();
  $loadReservation=function(int $id)use($pdo):array{
    $q=$pdo->prepare("SELECT r.id,r.code,r.room_id,r.checkin,r.checkout,r.guests,r.status,r.total,r.expires_at,r.notes,rm.name room_name,g.name guest_name,g.document,g.email,g.phone,g.whatsapp,COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.reservation_id=r.id AND p.status='paid'),0) amount_paid FROM reservations r JOIN rooms rm ON rm.id=r.room_id JOIN guests g ON g.id=r.guest_id WHERE r.id=? LIMIT 1");
    $q->execute([$id]);$r=$q->fetch();
    if(!$r)throw new RuntimeException('Reserva não encontrada.');
    $r['status_label']=statusPt((string)$r['status']);
    return $r;
  };
  try{
    if($_SERVER['REQUEST_METHOD']==='GET'){
      $id=(int)($_GET['id']??0);if(!$id)throw new RuntimeException('Reserva inválida.');
      jsonOut(['ok'=>true,'reservation'=>$loadReservation($id)]);
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')jsonOut(['ok'=>false,'message'=>'Método não permitido.'],405);
    Csrf::check();$action=(string)($_POST['action']??'');
    if($action==='create_pre'){
      $guestName=trim((string)($_POST['guest_name']??''));$roomId=(int)($_POST['room_id']??0);$checkin=(string)($_POST['checkin']??'');$checkout=(string)($_POST['checkout']??'');$guests=max(1,(int)($_POST['guests']??1));
      if($guestName==='')throw new RuntimeException('Informe o nome do hóspede.');
      if(!$roomId||!$checkin||!$checkout||$checkout<=$checkin)throw new RuntimeException('Informe unidade, entrada e saída válidas.');
      if(!$service->available($roomId,$checkin,$checkout))throw new RuntimeException('O período selecionado não está mais disponível.');
      $pdo->beginTransaction();
      try{
        $q=$pdo->prepare("INSERT INTO guests(name,document,email,phone,whatsapp,privacy_consent,created_at,updated_at) VALUES(?,?,?,?,?,1,NOW(),NOW())");
        $q->execute([$guestName,trim((string)($_POST['document']??'')),trim((string)($_POST['email']??'')),trim((string)($_POST['phone']??'')),trim((string)($_POST['whatsapp']??''))]);
        $gid=(int)$pdo->lastInsertId();
        $id=$service->create(['guest_id'=>$gid,'room_id'=>$roomId,'checkin'=>$checkin,'checkout'=>$checkout,'guests'=>$guests,'source'=>'painel','initial_status'=>'pre_reserva','notes'=>trim((string)($_POST['notes']??''))]);
        $pdo->commit();
      }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
      jsonOut(['ok'=>true,'message'=>'Pré-reserva criada.','reservation'=>$loadReservation($id)]);
    }
    if($action==='confirm'){
      $id=(int)($_POST['reservation_id']??0);if(!$id)throw new RuntimeException('Pré-reserva inválida.');
      $service->confirmPreReservation($id,trim((string)($_POST['payment_method']??'a_definir')),(float)($_POST['amount_paid']??0));
      jsonOut(['ok'=>true,'message'=>'Reserva confirmada com sucesso.','reservation'=>$loadReservation($id)]);
    }
    if($action==='cancel'){
      $id=(int)($_POST['reservation_id']??0);if(!$id)throw new RuntimeException('Reserva inválida.');
      $r=$loadReservation($id);
      if((string)$r['status']!=='cancelada')$service->changeStatus($id,'cancelada','Cancelada pelo mapa de diárias');
      jsonOut(['ok'=>true,'message'=>'Reserva cancelada.','reservation'=>$loadReservation($id)]);
    }
    throw new RuntimeException('Ação inválida.');
  }catch(Throwable $e){jsonOut(['ok'=>false,'message'=>$e->getMessage()],422);}
