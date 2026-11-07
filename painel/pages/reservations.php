<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('reservations.manage');$service=new ReservationService();
  if($_SERVER['REQUEST_METHOD']==='POST'){require __DIR__.'/reservation_status.php';exit;}
  $rs=$pdo->query("SELECT r.*,g.name guest,rm.name room FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id ORDER BY r.id DESC")->fetchAll();
  $h=adminTitle('Reservas','Consulte hóspedes, períodos e andamento das reservas.').'<div class="card reservations-list-card"><div class="reservations-scroll"><table class="reservations-list-table"><tr><th>Hóspede</th><th>Quarto</th><th>Período</th><th>Status</th><th>Total</th><th>Ação</th></tr>';
  $next=[
    'pre_reserva'=>['aguardando_aprovacao','aguardando_pagamento','recusada','expirada','cancelada'],
    'aguardando_aprovacao'=>['aguardando_pagamento','recusada','cancelada'],
    'aguardando_pagamento'=>['paga','cancelada','expirada'],
    'paga'=>['confirmada','reembolsada','cancelada'],
    'confirmada'=>['concluida','no_show','cancelada'],
    'cancelada'=>['reembolsada']
  ];
  foreach($rs as $r){
    $rid=(int)$r['id'];$name=U::e($r['guest']);
    $options=$next[$r['status']]??[];
    $dateIn=date('d/m/Y',strtotime((string)$r['checkin']));
    $dateOut=date('d/m/Y',strtotime((string)$r['checkout']));
    $h.='<tr class="reservation-list-row"><td class="reservation-guest">'
      .'<a class="reservations-guest-link" data-reservation-open href="index.php?p=reservation_edit&id='.$rid.'" title="Abrir ficha de '.$name.'">'.$name.'</a></td>'
      .'<td class="reservation-room">'.U::e($r['room']).'</td>'
      .'<td class="reservation-dates"><span>'.$dateIn.'</span><span class="reservation-date-arrow"> até </span><span>'.$dateOut.'</span></td>'
      .'<td class="reservation-status"><span class="reservation-status-text">'.U::e(statusPt($r['status'])).'</span></td>'
      .'<td class="reservation-total">'.money((float)$r['total']).'</td>'
      .'<td class="reservation-action-cell"><div class="reservation-row-actions">';
    if($options){
      $formId='reservationStatusForm'.$rid;
      $h.='<button class="btn secondary small reservation-inline-toggle" type="button" data-reservation-edit-toggle aria-expanded="false" aria-controls="'.$formId.'">Editar</button>'
        .'<form method="post" action="index.php?p=reservation_status" id="'.$formId.'" class="reservation-inline-form" data-reservation-inline-form hidden>'
        .Csrf::field().'<input type="hidden" name="id" value="'.$rid.'">'
        .'<label class="reservation-inline-field"><span>Status</span><select name="status" aria-label="Novo status de '.$name.'" required><option value="">Próxima etapa</option>';
      foreach($options as $nextStatus)$h.='<option value="'.U::e($nextStatus).'">'.U::e(statusPt($nextStatus)).'</option>';
      $h.='</select></label><label class="reservation-inline-field reservation-reason-field"><span>Motivo</span>'
        .'<input type="text" name="reason" placeholder="Motivo" aria-label="Motivo da alteração de '.$name.'" maxlength="255"></label>'
        .'<button type="submit" class="btn reservation-status-submit">Alterar</button></form>';
    }else{
      $h.='<a class="btn secondary small" data-reservation-open href="index.php?p=reservation_edit&id='.$rid.'">Ver ficha</a>';
    }
    $h.='</div></td></tr>';
  }
  $h.='</table></div></div>'
    .'<dialog id="reservationOverlay" class="reservation-overlay" aria-labelledby="reservationOverlayTitle">'
    .'<header class="reservation-overlay-head"><h2 id="reservationOverlayTitle">Detalhes da reserva</h2><button type="button" data-reservation-close aria-label="Fechar reserva">×</button></header>'
    .'<iframe class="reservation-overlay-frame" title="Ficha de reserva" loading="lazy"></iframe></dialog>';
  render('Reservas',$h);exit;
