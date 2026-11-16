<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requireLogin();
  $today=date('Y-m-d');
  $totalRooms=(int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE active=1")->fetchColumn();
  $activeReservations=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status IN('pre_reserva','aguardando_aprovacao','aguardando_pagamento','paga','confirmada')")->fetchColumn();
  $todayCheckins=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE checkin=CURDATE() AND status IN('paga','confirmada')")->fetchColumn();
  $todayCheckouts=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE checkout=CURDATE() AND status IN('paga','confirmada')")->fetchColumn();
  $pendingPayments=(int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
  $newContacts=(int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn();
  // Pending = open conversation whose latest message came from the guest;
  // a conversation with a staff reply does not count as unattended.
  $pendingChat=(int)$pdo->query("SELECT COUNT(*) FROM conversations c WHERE c.status='open' AND NOT EXISTS (SELECT 1 FROM suites_chat_visibility v WHERE v.conversation_id=c.id AND (v.archived_at IS NOT NULL OR v.deleted_at IS NOT NULL)) AND (SELECT m.sender_type FROM messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1)='guest'")->fetchColumn();
  $openMessages=(int)$pdo->query("SELECT COUNT(*) FROM conversations WHERE status='open'")->fetchColumn();
  $revenue=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND DATE_FORMAT(paid_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();

  $headTitle=Auth::isSuperAdmin()?'Central Super Admin':'Visão Geral';
  $headSub=Auth::isSuperAdmin()?'Controle total da operação, reservas, diárias, ocupação, financeiro, atendimento e configurações.':'Resumo da operação da pousada.';
  $h=adminTitle($headTitle,$headSub);

  if(Auth::isSuperAdmin()){
    $pendingApproval=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status IN('pre_reserva','aguardando_aprovacao')")->fetchColumn();
    $pendingPaymentAmount=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='pending'")->fetchColumn();
    $monthBooked=(float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM reservations WHERE status IN('paga','confirmada','concluida') AND DATE_FORMAT(checkin,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();
    $monthReservations=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE DATE_FORMAT(checkin,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') AND status NOT IN('cancelada','recusada','expirada')")->fetchColumn();
    $occupiedToday=(int)$pdo->query("SELECT COUNT(DISTINCT room_id) FROM reservations WHERE CURDATE()>=checkin AND CURDATE()<checkout AND status IN('paga','confirmada')")->fetchColumn();
    $blockedToday=(int)$pdo->query("SELECT COUNT(DISTINCT room_id) FROM room_blocks WHERE CURDATE()>=start_date AND CURDATE()<end_date")->fetchColumn();
    $unavailableToday=min($totalRooms,$occupiedToday+$blockedToday);
    $availableToday=max(0,$totalRooms-$unavailableToday);
    $occupancyPct=$totalRooms>0?round(($occupiedToday/$totalRooms)*100):0;
    $avgBase=(float)$pdo->query("SELECT COALESCE(AVG(base_price),0) FROM rooms WHERE active=1")->fetchColumn();
    $minBase=(float)$pdo->query("SELECT COALESCE(MIN(base_price),0) FROM rooms WHERE active=1")->fetchColumn();
    $maxBase=(float)$pdo->query("SELECT COALESCE(MAX(base_price),0) FROM rooms WHERE active=1")->fetchColumn();
    $activeTariffs=(int)$pdo->query("SELECT COUNT(*) FROM tariffs WHERE active=1 AND CURDATE() BETWEEN start_date AND end_date")->fetchColumn();
    $roomsNoPrice=(int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE active=1 AND base_price<=0")->fetchColumn();
    $icalErrors=(int)$pdo->query("SELECT COUNT(*) FROM ical_feeds WHERE active=1 AND last_error IS NOT NULL AND last_error<>''")->fetchColumn();

    $h.='<section class="superadmin-strip"><div><span class="eyebrow">SUPER ADMIN</span><h2>Operação em tempo real</h2><p>Todos os números abaixo vêm diretamente do banco publicado.</p></div><div class="quick-actions"><a class="btn" href="index.php?p=reservation_new">+ Pré-reserva</a><a class="btn" style="background:#ef7c21;border-color:#dc670f;color:#fff;font-weight:700" href="index.php?p=messages" aria-label="Abrir atendimento, '.$pendingChat.' conversa(s) aguardando resposta">'.panelCardIcon('chat').' Atendimento <span style="display:inline-flex;align-items:center;justify-content:center;min-width:25px;height:25px;margin-left:5px;border-radius:14px;background:#fff;color:#a94c0a;padding:0 5px">'.(int)$pendingChat.'</span></a><a class="btn secondary" href="index.php?p=rooms">Acomodações</a><a class="btn secondary" href="index.php?p=map">Mapa 15 dias</a></div></section>';

    $h.='<style>
    .super-kpis{display:grid!important;grid-template-columns:repeat(8,minmax(0,1fr))!important;gap:7px!important;margin-bottom:14px!important}
    .super-kpis .admin-kpi{padding:10px 9px!important;min-height:83px;display:flex;flex-direction:column;gap:2px;min-width:0;border-radius:9px!important}
    .super-kpis .admin-kpi span{font-size:10px!important;line-height:1.25;white-space:normal}
    .super-kpis .admin-kpi b{font-size:19px!important;line-height:1.16;margin:1px 0!important;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .super-kpis .admin-kpi small{font-size:9px!important;line-height:1.2!important}
    .daily-map-scroll{overflow-x:auto;max-width:100%;border:1px solid #d9e4de;border-radius:10px}
    .daily-map-grid{display:grid;grid-template-columns:minmax(145px,1.7fr) repeat(15,minmax(51px,1fr));min-width:920px;gap:1px;background:#dce8e2}
    .daily-map-cell{padding:7px 4px;min-height:34px;text-align:center;background:white;font-size:10px;display:flex;align-items:center;justify-content:center;box-sizing:border-box}
    .daily-map-unit{justify-content:flex-start;text-align:left;font-weight:600;padding-left:10px;position:sticky;left:0;background:#fff;z-index:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .daily-map-head{background:#edf4ef;font-weight:700}
    .daily-map-reserved{background:#d7e8ff;color:#18446e}
    .daily-map-block{background:#fbe4c7;color:#7c4609}
    .daily-map-available{color:#507866}
    .daily-map-legend{display:flex;gap:14px;flex-wrap:wrap;margin-top:9px;font-size:11px;color:#516960}
    .daily-map-legend b{display:inline-block;width:11px;height:11px;margin-right:4px;vertical-align:-1px;border:1px solid #b8c9bf;border-radius:3px}
    @media(max-width:1450px){.super-kpis{grid-template-columns:repeat(8,minmax(105px,1fr))!important;overflow-x:auto;scrollbar-width:thin}}
    @media(max-width:720px){.super-kpis{grid-template-columns:repeat(8,minmax(104px,1fr))!important}}
    </style>';
    $h.='<div class="admin-kpi-grid super-kpis">
      <a class="admin-kpi" href="index.php?p=map"><span>Ocupação hoje</span><b>'.$occupancyPct.'%</b><small>'.$occupiedToday.' ocupada(s) · '.$availableToday.' disponível(is)</small></a>
      <a class="admin-kpi" href="index.php?p=reservations"><span>Reservas do mês</span><b>'.$monthReservations.'</b><small>'.money($monthBooked).' em hospedagens</small></a>
      <a class="admin-kpi" href="index.php?p=reservations"><span>Aguardando ação</span><b>'.$pendingApproval.'</b><small>Pré-reserva / aprovação</small></a>
      <a class="admin-kpi" href="index.php?p=payments"><span>A receber</span><b>'.money($pendingPaymentAmount).'</b><small>'.$pendingPayments.' pagamento(s) pendente(s)</small></a>
      <a class="admin-kpi" href="index.php?p=payments"><span>Recebido no mês</span><b>'.money($revenue).'</b><small>Pagamentos confirmados</small></a>
      <a class="admin-kpi" href="index.php?p=rooms"><span>Diária-base média</span><b>'.money($avgBase).'</b><small>'.money($minBase).' a '.money($maxBase).'</small></a>
      <a class="admin-kpi" href="index.php?p=rooms"><span>Tarifas vigentes</span><b>'.$activeTariffs.'</b><small>Preços especiais ativos hoje</small></a>
      <a class="admin-kpi" href="index.php?p=messages"><span>Atendimentos</span><b>'.$openMessages.'</b><small>Conversas abertas</small></a>
    </div>';

    $periodStart=new DateTimeImmutable('today');
    $periodEnd=$periodStart->modify('+15 days');
    $unitRows=$pdo->query("SELECT id,name FROM rooms WHERE active=1 AND status='active' AND code NOT LIKE '__deleted__%' ORDER BY name")->fetchAll();
    $q=$pdo->prepare("SELECT room_id,checkin,checkout,status FROM reservations WHERE status NOT IN('cancelada','recusada','expirada') AND checkin<? AND checkout>?");
    $q->execute([$periodEnd->format('Y-m-d'),$periodStart->format('Y-m-d')]);$occRows=$q->fetchAll();
    $q=$pdo->prepare("SELECT room_id,start_date,end_date FROM room_blocks WHERE start_date<? AND end_date>?");
    $q->execute([$periodEnd->format('Y-m-d'),$periodStart->format('Y-m-d')]);$blockRows=$q->fetchAll();
    $reservationDays=[];$blockedDays=[];
    foreach($occRows as $entry){
      for($day=0;$day<15;$day++){
        $ds=$periodStart->modify("+$day days")->format('Y-m-d');
        if($ds>=$entry['checkin']&&$ds<$entry['checkout'])$reservationDays[(int)$entry['room_id']][$day]=1;
      }
    }
    foreach($blockRows as $entry){
      for($day=0;$day<15;$day++){
        $ds=$periodStart->modify("+$day days")->format('Y-m-d');
        if($ds>=$entry['start_date']&&$ds<$entry['end_date'])$blockedDays[(int)$entry['room_id']][$day]=1;
      }
    }
    $h.='<section class="card"><div class="card-head"><div><h2>Ocupação — próximos 15 dias</h2><p class="muted">Reservas confirmadas + bloqueios operacionais.</p></div><a href="index.php?p=map">Abrir mapa completo</a></div><div class="daily-map-scroll"><div class="daily-map-grid" role="table" aria-label="Mapa de diárias dos próximos quinze dias">';
    $h.='<div class="daily-map-cell daily-map-head daily-map-unit" role="columnheader">Acomodação</div>';
    for($day=0;$day<15;$day++){
      $date=$periodStart->modify("+$day days");
      $h.='<div class="daily-map-cell daily-map-head" role="columnheader">'.$date->format('d/m').'</div>';
    }
    foreach($unitRows as $room){
      $rid=(int)$room['id'];
      $h.='<div class="daily-map-cell daily-map-unit" role="rowheader">'.U::e($room['name']).'</div>';
      for($day=0;$day<15;$day++){
        $reserved=!empty($reservationDays[$rid][$day]);
        $blocked=!empty($blockedDays[$rid][$day]);
        $date=$periodStart->modify("+$day days")->format('Y-m-d');
        $state=$reserved?'Reservado':($blocked?'Bloqueado':'Livre');
        $class=$reserved?'daily-map-reserved':($blocked?'daily-map-block':'daily-map-available');
        $h.='<div class="daily-map-cell '.$class.'" role="cell" title="'.U::e($room['name']).' · '.$date.' · '.$state.'">'.($reserved?'●':($blocked?'■':'–')).'</div>';
      }
    }
    if(!$unitRows)$h.='<div class="daily-map-cell">Nenhuma acomodação ativa.</div>';
    $h.='</div></div><div class="daily-map-legend"><span><b style="background:#d7e8ff"></b>Reservado</span><span><b style="background:#fbe4c7"></b>Bloqueado</span><span><b style="background:#fff"></b>Livre</span></div></section>';

    $next=$pdo->query("SELECT r.id,r.code,r.checkin,r.checkout,r.status,r.total,r.source,g.name guest,rm.name room FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id WHERE r.checkout>=CURDATE() AND r.status IN('pre_reserva','aguardando_aprovacao','aguardando_pagamento','paga','confirmada') ORDER BY r.checkin,r.id LIMIT 12")->fetchAll();
    $h.='<div class="admin-dashboard-grid"><section class="card"><div class="card-head"><h2>Próximas reservas</h2><a href="index.php?p=reservations">Ver todas</a></div><div class="table-scroll"><table><tr><th>Código</th><th>Hóspede</th><th>Acomodação</th><th>Entrada</th><th>Saída</th><th>Total</th><th>Status</th></tr>';
    foreach($next as $r)$h.='<tr><td><a href="index.php?p=reservations&id='.$r['id'].'">'.U::e($r['code']).'</a></td><td>'.U::e($r['guest']).'</td><td>'.U::e($r['room']).'</td><td>'.U::e($r['checkin']).'</td><td>'.U::e($r['checkout']).'</td><td>'.money((float)$r['total']).'</td><td><span class="status-badge">'.U::e(statusPt($r['status'])).'</span></td></tr>';
    if(!$next)$h.='<tr><td colspan="7" class="muted">Nenhuma reserva futura cadastrada.</td></tr>';
    $h.='</table></div></section>';

    $rates=$pdo->query("SELECT r.id,r.name,r.base_price,r.min_nights,r.extra_guest_fee,r.cleaning_fee,(SELECT t.price FROM tariffs t WHERE t.room_id=r.id AND t.active=1 AND CURDATE() BETWEEN t.start_date AND t.end_date ORDER BY t.priority DESC,t.id DESC LIMIT 1) current_tariff FROM rooms r WHERE r.active=1 ORDER BY r.name")->fetchAll();
    $h.='<section class="card"><div class="card-head"><h2>Diárias e tarifas</h2><a href="index.php?p=rooms">Gerenciar nas acomodações</a></div><div class="table-scroll"><table><tr><th>Acomodação</th><th>Diária-base</th><th>Tarifa hoje</th><th>Mín. noites</th><th>Hóspede extra</th><th>Limpeza</th></tr>';
    foreach($rates as $r)$h.='<tr><td><a href="index.php?p=room_manage&view=edit&id='.$r['id'].'&section=diaria#room-pricing">'.U::e($r['name']).'</a></td><td>'.money((float)$r['base_price']).'</td><td>'.($r['current_tariff']!==null?money((float)$r['current_tariff']):'<span class="muted">diária-base</span>').'</td><td>'.(int)$r['min_nights'].'</td><td>'.money((float)$r['extra_guest_fee']).'</td><td>'.money((float)$r['cleaning_fee']).'</td></tr>';
    if(!$rates)$h.='<tr><td colspan="6" class="muted">Nenhuma acomodação ativa.</td></tr>';
    $h.='</table></div></section></div>';

    $checkins=$pdo->query("SELECT r.code,r.checkin,g.name guest,rm.name room FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id WHERE r.checkin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND r.status IN('paga','confirmada') ORDER BY r.checkin LIMIT 10")->fetchAll();
    $checkouts=$pdo->query("SELECT r.code,r.checkout,g.name guest,rm.name room FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id WHERE r.checkout BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND r.status IN('paga','confirmada') ORDER BY r.checkout LIMIT 10")->fetchAll();
    $h.='<div class="admin-dashboard-grid"><section class="card"><h2>Check-ins — 7 dias</h2><div class="event-list">';foreach($checkins as $x)$h.='<a href="index.php?p=reservations"><b>'.U::e($x['checkin']).'</b><span>'.U::e($x['guest']).' · '.U::e($x['room']).'</span><small>'.U::e($x['code']).'</small></a>';if(!$checkins)$h.='<p class="muted">Nenhum check-in previsto.</p>';$h.='</div></section>';
    $h.='<section class="card"><h2>Check-outs — 7 dias</h2><div class="event-list">';foreach($checkouts as $x)$h.='<a href="index.php?p=reservations"><b>'.U::e($x['checkout']).'</b><span>'.U::e($x['guest']).' · '.U::e($x['room']).'</span><small>'.U::e($x['code']).'</small></a>';if(!$checkouts)$h.='<p class="muted">Nenhum check-out previsto.</p>';$h.='</div></section></div>';

    $alerts=[];
    if($pendingApproval>0)$alerts[]=['Reservas aguardando aprovação',$pendingApproval,'reservations'];
    if($pendingPayments>0)$alerts[]=['Pagamentos pendentes',$pendingPayments,'payments'];
    if($roomsNoPrice>0)$alerts[]=['Acomodações sem diária-base',$roomsNoPrice,'rooms'];
    if($icalErrors>0)$alerts[]=['Integrações iCal com erro',$icalErrors,'integrations'];
    if($newContacts>0)$alerts[]=['Novos contatos do site',$newContacts,'contacts_admin'];
    $h.='<section class="card"><div class="card-head"><h2>Alertas operacionais</h2><span class="muted">Itens que exigem atenção</span></div><div class="alert-list">';
    if(!$alerts)$h.='<div class="alert success">Nenhuma pendência crítica detectada.</div>';
    foreach($alerts as $a)$h.='<a href="index.php?p='.$a[2].'"><span>'.U::e($a[0]).'</span><b>'.$a[1].'</b></a>';
    $h.='</div></section>';
  }else{
    $h.='<div class="admin-kpi-grid">
      <a class="admin-kpi" href="index.php?p=rooms"><span>Acomodações ativas</span><b>'.$totalRooms.'</b><small>Gerenciar unidades</small></a>
      <a class="admin-kpi" href="index.php?p=reservations"><span>Reservas em andamento</span><b>'.$activeReservations.'</b><small>Acompanhar reservas</small></a>
      <a class="admin-kpi" href="index.php?p=map"><span>Check-ins hoje</span><b>'.$todayCheckins.'</b><small>Check-outs: '.$todayCheckouts.'</small></a>
      <a class="admin-kpi" href="index.php?p=payments"><span>Pagamentos pendentes</span><b>'.$pendingPayments.'</b><small>Receita no mês: '.money($revenue).'</small></a>
      <a class="admin-kpi" href="index.php?p=messages"><span>Atendimentos abertos</span><b>'.$openMessages.'</b><small>Ver conversas</small></a>
      <a class="admin-kpi" href="index.php?p=contacts_admin"><span>Novos contatos</span><b>'.$newContacts.'</b><small>Formulário do site</small></a>
    </div>';

    $h.='<div class="admin-dashboard-grid">
      <section class="card admin-module-card"><div><span class="module-icon">🏨</span><h2>Pousada</h2><p>Informações, unidades, tarifas, promoções, fotos e atrações.</p></div><div class="module-links"><a href="index.php?p=property">Informações</a><a href="index.php?p=rooms">Quartos</a><a href="index.php?p=gallery">Galeria</a></div></section>
      <section class="card admin-module-card"><div><span class="module-icon">📅</span><h2>Reservas</h2><p>Mapa, reservas, hóspedes e pagamentos em um único fluxo.</p></div><div class="module-links"><a href="index.php?p=map">Mapa</a><a href="index.php?p=reservations">Reservas</a><a href="index.php?p=guests">Hóspedes</a></div></section>
      <section class="card admin-module-card"><div><span class="module-icon">💬</span><h2>Atendimento</h2><p>Mensagens do viajante e contatos recebidos pelo portal público.</p></div><div class="module-links"><a href="index.php?p=messages">Conversas</a><a href="index.php?p=contacts_admin">Contatos</a></div></section>
      <section class="card admin-module-card"><div><span class="module-icon">✦</span><h2>Conteúdo e IA</h2><p>Portal público, SEO, avaliações, IA e integrações externas.</p></div><div class="module-links"><a href="index.php?p=content">Conteúdo</a><a href="index.php?p=ai">IA</a><a href="index.php?p=attendance_settings">Configurar atendimento</a><a href="index.php?p=integrations">Integrações</a></div></section>
    </div>';

    $recent=$pdo->query("SELECT r.code,r.checkin,r.checkout,r.status,g.name guest,rm.name room FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id ORDER BY r.id DESC LIMIT 8")->fetchAll();
    $h.='<section class="card admin-recent"><div class="card-head"><h2>Reservas recentes</h2><a href="index.php?p=reservations">Ver todas</a></div><table><tr><th>Reserva</th><th>Hóspede</th><th>Acomodação</th><th>Período</th><th>Status</th></tr>';
    foreach($recent as $r)$h.='<tr><td>'.U::e($r['code']).'</td><td>'.U::e($r['guest']).'</td><td>'.U::e($r['room']).'</td><td>'.U::e($r['checkin']).' → '.U::e($r['checkout']).'</td><td><span class="status-badge">'.U::e(statusPt($r['status'])).'</span></td></tr>';
    $h.='</table></section>';
  }

  render($headTitle,$h);exit;
