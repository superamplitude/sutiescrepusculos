<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('reservations.manage');$id=(int)($_GET['id']??0);$service=new ReservationService();
  $load=function()use($pdo,$id){$q=$pdo->prepare("SELECT r.*,g.name guest_name,g.email,g.phone,g.whatsapp,g.document,g.id guest_record_id,rm.name room_name FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id WHERE r.id=?");$q->execute([$id]);return $q->fetch();};
  $x=$load();if(!$x){http_response_code(404);render('Reserva','<div class="alert error">Reserva não encontrada.</div>');exit;}
  $next=[
    'pre_reserva'=>['aguardando_aprovacao','aguardando_pagamento','recusada','expirada','cancelada'],
    'aguardando_aprovacao'=>['aguardando_pagamento','recusada','cancelada'],
    'aguardando_pagamento'=>['paga','cancelada','expirada'],
    'paga'=>['confirmada','reembolsada','cancelada'],
    'confirmada'=>['concluida','no_show','cancelada'],
    'cancelada'=>['reembolsada']
  ];
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$action=$_POST['action']??'save';
    try{
      if($action==='status'){
        $service->changeStatus($id,(string)($_POST['status']??''),(string)($_POST['reason']??''));U::flash('ok','Status atualizado.');U::redirect('index.php?p=reservation_edit&id='.$id);
      }
      $room=(int)($_POST['room_id']??0);$in=(string)($_POST['checkin']??'');$out=(string)($_POST['checkout']??'');$guests=max(1,(int)($_POST['guests']??1));
      if(!$room||!$in||!$out||$out<=$in)throw new RuntimeException('Informe unidade e período válidos.');
      $terminal=in_array($x['status'],['cancelada','recusada','expirada','reembolsada','concluida','no_show'],true);
      if(!$terminal&&!$service->available($room,$in,$out,$id))throw new RuntimeException('O novo período conflita com outra reserva ou bloqueio.');
      $total=$service->price($room,$in,$out,$guests);
      $pdo->beginTransaction();
      $pdo->prepare("UPDATE guests SET name=?,email=?,phone=?,whatsapp=?,document=?,updated_at=NOW() WHERE id=?")->execute([trim((string)$_POST['guest_name']),trim((string)($_POST['email']??'')),trim((string)($_POST['phone']??'')),trim((string)($_POST['whatsapp']??'')),trim((string)($_POST['document']??'')),(int)$x['guest_record_id']]);
      $pdo->prepare("UPDATE reservations SET room_id=?,checkin=?,checkout=?,guests=?,total=?,notes=?,updated_at=NOW() WHERE id=?")->execute([$room,$in,$out,$guests,$total,trim((string)($_POST['notes']??'')),$id]);
      $pdo->commit();Audit::log('reservation.update','reservation',$id);U::flash('ok','Reserva atualizada.');U::redirect('index.php?p=reservation_edit&id='.$id);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();U::flash('err',$e->getMessage());U::redirect('index.php?p=reservation_edit&id='.$id);}
  }
  $x=$load();$rooms=$pdo->query("SELECT id,name FROM rooms WHERE active=1 ORDER BY name")->fetchAll();
  $h=adminTitle('Editar reserva '.U::e($x['code']),'Clique e altere hóspede, unidade, período ou quantidade. O total é recalculado automaticamente.');
  $h.='<div class=map-toolbar><a class="btn secondary" href="index.php?p=map&start='.U::e($x['checkin']).'">← Voltar ao mapa</a><a class="btn secondary" href="index.php?p=reservations">Lista de reservas</a></div>';
  $h.='<form method=post class=card>'.Csrf::field().'<input type=hidden name=action value=save><div class=reservation-edit-grid><label>Unidade<select name=room_id required>';foreach($rooms as $r)$h.='<option value="'.$r['id'].'" '.((int)$x['room_id']===(int)$r['id']?'selected':'').'>'.U::e($r['name']).'</option>';$h.='</select></label>'.row('Entrada','checkin',$x['checkin'],'date').row('Saída','checkout',$x['checkout'],'date').row('Hóspedes','guests',$x['guests'],'number').row('Nome do hóspede','guest_name',$x['guest_name']).row('Documento','document',$x['document']).row('E-mail','email',$x['email'],'email').row('Telefone','phone',$x['phone']).row('WhatsApp','whatsapp',$x['whatsapp']).'<label>Status atual<input value="'.U::e(statusPt($x['status'])).'" disabled></label><label>Total recalculado<input value="'.U::e(money((float)$x['total'])).'" disabled></label><div class=wide>'.row('Observações','notes',$x['notes'],'textarea').'</div></div><button>Salvar alterações</button></form>';
  $opts=$next[$x['status']]??[];$h.='<section class=card><h2>Alterar status</h2><p class=muted>Status atual: <b>'.U::e(statusPt($x['status'])).'</b></p>';
  if($opts){$h.='<form method=post class=inline>'.Csrf::field().'<input type=hidden name=action value=status><select name=status required><option value="">Próxima etapa</option>';foreach($opts as $st)$h.='<option value="'.U::e($st).'">'.U::e(statusPt($st)).'</option>';$h.='</select><input name=reason placeholder="Motivo / observação"><button>Alterar status</button></form>';}else{$h.='<p class=muted>Não há transição manual disponível para este status.</p>';}$h.='</section>';
  $documents=(new \App\Services\GuestDocumentService())->listFor((int)$x['guest_record_id'],(int)$id);
  $h.='<section class="card"><h2>Documentos do hóspede</h2><p class="muted">Documentos do painel do hóspede e do WhatsApp, acessíveis somente a usuários autorizados.</p>'.\App\Services\GuestDocumentService::links($documents).'</section>';
  render('Editar reserva',$h);exit;
