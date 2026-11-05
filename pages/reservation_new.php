<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('reservations.manage');
  $service=new ReservationService();
  $rid=(int)($_GET['room_id']??($_POST['room_id']??0));
  $in=(string)($_GET['checkin']??($_POST['checkin']??''));
  $out=(string)($_GET['checkout']??($_POST['checkout']??''));
  $mode=(string)($_GET['mode']??($_POST['mode']??'pre_reserva'));
  if(!in_array($mode,['pre_reserva','reserva'],true))$mode='pre_reserva';
  $rooms=$pdo->query("SELECT id,name FROM rooms WHERE active=1 AND status='active' ORDER BY name")->fetchAll();
  $isConfirmed=$mode==='reserva';
  $title=$isConfirmed?'Nova reserva confirmada':'Nova pré-reserva';
  $subtitle=$isConfirmed?'Cadastre diretamente uma reserva confirmada. Informe o valor já pago; o saldo restante ficará pendente.':'Cadastre uma pré-reserva com validade inicial de 30 minutos.';
  $h=adminTitle($title,$subtitle);

  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();
    try{
      $guestName=trim((string)($_POST['guest_name']??''));
      $roomId=(int)($_POST['room_id']??0);
      $checkin=(string)($_POST['checkin']??'');
      $checkout=(string)($_POST['checkout']??'');
      $guestCount=max(1,(int)($_POST['guests']??1));
      if($guestName==='')throw new RuntimeException('Informe o nome do hóspede.');
      if(!$roomId||!$checkin||!$checkout||$checkout<=$checkin)throw new RuntimeException('Selecione unidade, entrada e saída válidas.');
      if(!$service->available($roomId,$checkin,$checkout))throw new RuntimeException('O período selecionado não está mais disponível.');

      $pdo->beginTransaction();
      $q=$pdo->prepare("INSERT INTO guests(name,email,phone,whatsapp,privacy_consent,created_at,updated_at) VALUES(?,?,?,?,1,NOW(),NOW())");
      $q->execute([$guestName,trim((string)($_POST['email']??'')),trim((string)($_POST['phone']??'')),trim((string)($_POST['whatsapp']??''))]);
      $gid=(int)$pdo->lastInsertId();

      $id=$service->create([
        'guest_id'=>$gid,'room_id'=>$roomId,'checkin'=>$checkin,'checkout'=>$checkout,'guests'=>$guestCount,
        'source'=>'painel','initial_status'=>$mode==='reserva'?'confirmada':'pre_reserva',
        'payment_method'=>trim((string)($_POST['payment_method']??'a_definir')),
        'amount_paid'=>(float)($_POST['amount_paid']??0),'notes'=>trim((string)($_POST['notes']??''))
      ]);
      $pdo->commit();

      U::flash('ok',$mode==='reserva'?'Reserva confirmada cadastrada com sucesso.':'Pré-reserva cadastrada com sucesso.');
      U::redirect('index.php?p=reservation_edit&id='.$id);
    }catch(Throwable $e){
      if($pdo->inTransaction())$pdo->rollBack();
      $h.='<div class="alert error">'.U::e($e->getMessage()).'</div>';
    }
  }

  $postedRoom=(int)($_POST['room_id']??$rid);$postedIn=(string)($_POST['checkin']??$in);$postedOut=(string)($_POST['checkout']??$out);
  $postedGuests=(int)($_POST['guests']??2);$postedName=(string)($_POST['guest_name']??'');$postedEmail=(string)($_POST['email']??'');
  $postedPhone=(string)($_POST['phone']??'');$postedWhatsapp=(string)($_POST['whatsapp']??'');$postedMethod=(string)($_POST['payment_method']??'a_definir');
  $postedPaid=(string)($_POST['amount_paid']??'0');$postedNotes=(string)($_POST['notes']??'');

  $h.='<form method=post class=card>'.Csrf::field().'<input type=hidden name=mode value="'.U::e($mode).'">';
  $h.='<div class="reservation-mode-switch"><a class="btn '.($mode==='pre_reserva'?'':'secondary').'" href="index.php?p=reservation_new&mode=pre_reserva&room_id='.$postedRoom.'&checkin='.rawurlencode($postedIn).'&checkout='.rawurlencode($postedOut).'">Pré-reserva</a><a class="btn '.($mode==='reserva'?'':'secondary').'" href="index.php?p=reservation_new&mode=reserva&room_id='.$postedRoom.'&checkin='.rawurlencode($postedIn).'&checkout='.rawurlencode($postedOut).'">Reserva confirmada</a></div>';
  $h.='<div class=grid3><label>Quarto<select name=room_id required><option value="">Selecione</option>';
  foreach($rooms as $r)$h.='<option value="'.$r['id'].'" '.($postedRoom===(int)$r['id']?'selected':'').'>'.U::e($r['name']).'</option>';
  $h.='</select></label>'.row('Entrada','checkin',$postedIn,'date').row('Saída','checkout',$postedOut,'date').row('Hóspedes','guests',$postedGuests,'number').row('Nome do hóspede','guest_name',$postedName).row('E-mail','email',$postedEmail,'email').row('Telefone','phone',$postedPhone).row('WhatsApp','whatsapp',$postedWhatsapp).'</div>';
  if($isConfirmed){
    $methods=['a_definir'=>'A definir','pix'=>'PIX','dinheiro'=>'Dinheiro','debito'=>'Débito','credito'=>'Crédito','transferencia'=>'Transferência','outro'=>'Outro'];
    $h.='<section class=form-section><h2>Pagamento da reserva</h2><div class=grid2><label>Meio de pagamento<select name=payment_method>';
    foreach($methods as $value=>$label)$h.='<option value="'.U::e($value).'" '.($postedMethod===$value?'selected':'').'>'.U::e($label).'</option>';
    $h.='</select></label><label>Valor já pago<input type="number" name="amount_paid" min="0" step="0.01" value="'.U::e($postedPaid).'" inputmode="decimal"></label></div><p class=muted>Se o valor pago for menor que o total, o sistema registra automaticamente o saldo restante como pendente.</p></section>';
  }
  $h.=row('Observações','notes',$postedNotes,'textarea');
  $h.='<button>'.($isConfirmed?'Confirmar e cadastrar reserva':'Criar pré-reserva').'</button></form>';
  render($title,$h);exit;
