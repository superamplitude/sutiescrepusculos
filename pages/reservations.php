<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('reservations.manage');$service=new ReservationService();
  if($_SERVER['REQUEST_METHOD']==='POST'){Csrf::check();try{$service->changeStatus((int)$_POST['id'],$_POST['status'],$_POST['reason']??'');U::flash('ok','Status atualizado.');}catch(Throwable $e){U::flash('err',$e->getMessage());}U::redirect('index.php?p=reservations');}
  $rs=$pdo->query("SELECT r.*,g.name guest,rm.name room FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id ORDER BY r.id DESC")->fetchAll();
  $h=adminTitle('Reservas','Reservas reais e histórico de status.').'<div class=card><table><tr><th>Código</th><th>Hóspede</th><th>Quarto</th><th>Período</th><th>Status</th><th>Total</th><th>Ação</th></tr>';
  $next=[
    'pre_reserva'=>['aguardando_aprovacao','aguardando_pagamento','recusada','expirada','cancelada'],
    'aguardando_aprovacao'=>['aguardando_pagamento','recusada','cancelada'],
    'aguardando_pagamento'=>['paga','cancelada','expirada'],
    'paga'=>['confirmada','reembolsada','cancelada'],
    'confirmada'=>['concluida','no_show','cancelada'],
    'cancelada'=>['reembolsada']
  ];
  foreach($rs as $r){$h.='<tr><td><a href="index.php?p=reservation_edit&id='.(int)$r['id'].'"><b>'.U::e($r['code']).'</b></a></td><td><a href="index.php?p=reservation_edit&id='.(int)$r['id'].'">'.U::e($r['guest']).'</a></td><td>'.U::e($r['room']).'</td><td>'.U::e($r['checkin']).' → '.U::e($r['checkout']).'</td><td>'.U::e(statusPt($r['status'])).'</td><td>'.money((float)$r['total']).'</td><td><div class="map-editor-actions"><a class="btn secondary small" href="index.php?p=reservation_edit&id='.(int)$r['id'].'">Editar</a>';
    $opts=$next[$r['status']]??[];if($opts){$h.="<form method=post class=inline>".Csrf::field()."<input type=hidden name=id value={$r['id']}><select name=status><option value=''>Próxima etapa</option>";foreach($opts as $st)$h.="<option value=\"".U::e($st)."\">".U::e(statusPt($st))."</option>";$h.="</select><input name=reason placeholder=Motivo><button>Alterar</button></form>";}else{$h.="<span class=muted>Sem transição manual</span>";}$h.="</div></td></tr>";}
  render('Reservas',$h.'</table></div>');exit;
