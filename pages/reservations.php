<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('reservations.manage');$service=new ReservationService();
  if($_SERVER['REQUEST_METHOD']==='POST'){Csrf::check();try{$service->changeStatus((int)$_POST['id'],$_POST['status'],$_POST['reason']??'');U::flash('ok','Status atualizado.');}catch(Throwable $e){U::flash('err',$e->getMessage());}U::redirect('index.php?p=reservations');}
  $rs=$pdo->query("SELECT r.*,g.name guest,rm.name room FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id ORDER BY r.id DESC")->fetchAll();
  $h=adminTitle('Reservas','Reservas reais e histórico de status.').'<div class="card reservations-list-card"><div class="reservations-scroll"><table class="reservations-list-table"><tr><th>Hóspede</th><th>Quarto</th><th>Período</th><th>Status</th><th>Total</th><th>Ação</th></tr>';
  $next=[
    'pre_reserva'=>['aguardando_aprovacao','aguardando_pagamento','recusada','expirada','cancelada'],
    'aguardando_aprovacao'=>['aguardando_pagamento','recusada','cancelada'],
    'aguardando_pagamento'=>['paga','cancelada','expirada'],
    'paga'=>['confirmada','reembolsada','cancelada'],
    'confirmada'=>['concluida','no_show','cancelada'],
    'cancelada'=>['reembolsada']
  ];
  foreach($rs as $r){$h.='<tr><td><a class="reservations-guest-link" data-reservation-open href="index.php?p=reservation_edit&id='.(int)$r['id'].'" title="Abrir ficha de '.U::e($r['guest']).'">'.U::e($r['guest']).'</a></td><td>'.U::e($r['room']).'</td><td>'.U::e($r['checkin']).' → '.U::e($r['checkout']).'</td><td>'.U::e(statusPt($r['status'])).'</td><td>'.money((float)$r['total']).'</td><td><div class="map-editor-actions"><a class="btn secondary small" data-reservation-open href="index.php?p=reservation_edit&id='.(int)$r['id'].'">Editar</a>';
    $opts=$next[$r['status']]??[];if($opts){$h.="<form method=post class=inline>".Csrf::field()."<input type=hidden name=id value={$r['id']}><select name=status><option value=''>Próxima etapa</option>";foreach($opts as $st)$h.="<option value=\"".U::e($st)."\">".U::e(statusPt($st))."</option>";$h.="</select><input name=reason placeholder=Motivo><button>Alterar</button></form>";}else{$h.="<span class=muted>Sem transição manual</span>";}$h.="</div></td></tr>";}
  $h.='</table></div></div>'
    .'<dialog id="reservationOverlay" class="reservation-overlay" aria-labelledby="reservationOverlayTitle">'
    .'<header class="reservation-overlay-head"><h2 id="reservationOverlayTitle">Detalhes da reserva</h2><button type="button" data-reservation-close aria-label="Fechar reserva">×</button></header>'
    .'<iframe class="reservation-overlay-frame" title="Ficha de reserva" loading="lazy"></iframe></dialog>';
  render('Reservas',$h);exit;
