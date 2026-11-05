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

    $h.='<section class="superadmin-strip"><div><span class="eyebrow">SUPER ADMIN</span><h2>Operação em tempo real</h2><p>Todos os números abaixo vêm diretamente do banco publicado.</p></div><div class="quick-actions"><a class="btn" href="index.php?p=reservation_new">+ Pré-reserva</a><a class="btn secondary" href="index.php?p=tariffs">+ Tarifa</a><a class="btn secondary" href="index.php?p=rooms">Acomodações</a><a class="btn secondary" href="index.php?p=map">Mapa 15 dias</a></div></section>';

    $h.='<div class="admin-kpi-grid super-kpis">
      <a class="admin-kpi" href="index.php?p=map"><span>Ocupação hoje</span><b>'.$occupancyPct.'%</b><small>'.$occupiedToday.' ocupada(s) · '.$availableToday.' disponível(is)</small></a>
      <a class="admin-kpi" href="index.php?p=reservations"><span>Reservas do mês</span><b>'.$monthReservations.'</b><small>'.money($monthBooked).' em hospedagens</small></a>
      <a class="admin-kpi" href="index.php?p=reservations"><span>Aguardando ação</span><b>'.$pendingApproval.'</b><small>Pré-reserva / aprovação</small></a>
      <a class="admin-kpi" href="index.php?p=payments"><span>A receber</span><b>'.money($pendingPaymentAmount).'</b><small>'.$pendingPayments.' pagamento(s) pendente(s)</small></a>
      <a class="admin-kpi" href="index.php?p=payments"><span>Recebido no mês</span><b>'.money($revenue).'</b><small>Pagamentos confirmados</small></a>
      <a class="admin-kpi" href="index.php?p=tariffs"><span>Diária-base média</span><b>'.money($avgBase).'</b><small>'.money($minBase).' a '.money($maxBase).'</small></a>
      <a class="admin-kpi" href="index.php?p=tariffs"><span>Tarifas vigentes</span><b>'.$activeTariffs.'</b><small>Preços especiais ativos hoje</small></a>
      <a class="admin-kpi" href="index.php?p=messages"><span>Atendimentos</span><b>'.$openMessages.'</b><small>Conversas abertas</small></a>
    </div>';

    $periodStart=new DateTimeImmutable('today');
    $periodEnd=$periodStart->modify('+15 days');
    $q=$pdo->prepare("SELECT room_id,checkin,checkout FROM reservations WHERE status IN('paga','confirmada') AND checkin<? AND checkout>?");
    $q->execute([$periodEnd->format('Y-m-d'),$periodStart->format('Y-m-d')]);$occRows=$q->fetchAll();
    $q=$pdo->prepare("SELECT room_id,start_date,end_date FROM room_blocks WHERE start_date<? AND end_date>?");
    $q->execute([$periodEnd->format('Y-m-d'),$periodStart->format('Y-m-d')]);$blockRows=$q->fetchAll();
    $h.='<section class="card"><div class="card-head"><div><h2>Ocupação — próximos 15 dias</h2><p class="muted">Reservas confirmadas + bloqueios operacionais.</p></div><a href="index.php?p=map">Abrir mapa completo</a></div><div class="occupancy-grid">';
    for($i=0;$i<15;$i++){
      $d=$periodStart->modify("+$i days");$ds=$d->format('Y-m-d');$unavailable=[];$reserved=[];
      foreach($occRows as $or){if($ds>=$or['checkin']&&$ds<$or['checkout']){$unavailable[(int)$or['room_id']]=1;$reserved[(int)$or['room_id']]=1;}}
      foreach($blockRows as $br){if($ds>=$br['start_date']&&$ds<$br['end_date'])$unavailable[(int)$br['room_id']]=1;}
      $un=count($unavailable);$res=count($reserved);$avail=max(0,$totalRooms-$un);$pct=$totalRooms>0?round(($res/$totalRooms)*100):0;
      $h.='<div class="occupancy-day"><b>'.$d->format('d/m').'</b><small>'.$d->format('D').'</small><strong>'.$pct.'%</strong><span>'.$res.' reserv. · '.$avail.' livre(s)</span></div>';
    }
    $h.='</div></section>';

    $next=$pdo->query("SELECT r.id,r.code,r.checkin,r.checkout,r.status,r.total,r.source,g.name guest,rm.name room FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id WHERE r.checkout>=CURDATE() AND r.status IN('pre_reserva','aguardando_aprovacao','aguardando_pagamento','paga','confirmada') ORDER BY r.checkin,r.id LIMIT 12")->fetchAll();
    $h.='<div class="admin-dashboard-grid"><section class="card"><div class="card-head"><h2>Próximas reservas</h2><a href="index.php?p=reservations">Ver todas</a></div><div class="table-scroll"><table><tr><th>Código</th><th>Hóspede</th><th>Acomodação</th><th>Entrada</th><th>Saída</th><th>Total</th><th>Status</th></tr>';
    foreach($next as $r)$h.='<tr><td><a href="index.php?p=reservations&id='.$r['id'].'">'.U::e($r['code']).'</a></td><td>'.U::e($r['guest']).'</td><td>'.U::e($r['room']).'</td><td>'.U::e($r['checkin']).'</td><td>'.U::e($r['checkout']).'</td><td>'.money((float)$r['total']).'</td><td><span class="status-badge">'.U::e(statusPt($r['status'])).'</span></td></tr>';
    if(!$next)$h.='<tr><td colspan="7" class="muted">Nenhuma reserva futura cadastrada.</td></tr>';
    $h.='</table></div></section>';

    $rates=$pdo->query("SELECT r.id,r.name,r.base_price,r.min_nights,r.extra_guest_fee,r.cleaning_fee,(SELECT t.price FROM tariffs t WHERE t.room_id=r.id AND t.active=1 AND CURDATE() BETWEEN t.start_date AND t.end_date ORDER BY t.priority DESC,t.id DESC LIMIT 1) current_tariff FROM rooms r WHERE r.active=1 ORDER BY r.name")->fetchAll();
    $h.='<section class="card"><div class="card-head"><h2>Diárias e tarifas</h2><a href="index.php?p=tariffs">Gerenciar tarifas</a></div><div class="table-scroll"><table><tr><th>Acomodação</th><th>Diária-base</th><th>Tarifa hoje</th><th>Mín. noites</th><th>Hóspede extra</th><th>Limpeza</th></tr>';
    foreach($rates as $r)$h.='<tr><td><a href="index.php?p=rooms&id='.$r['id'].'">'.U::e($r['name']).'</a></td><td>'.money((float)$r['base_price']).'</td><td>'.($r['current_tariff']!==null?money((float)$r['current_tariff']):'<span class="muted">diária-base</span>').'</td><td>'.(int)$r['min_nights'].'</td><td>'.money((float)$r['extra_guest_fee']).'</td><td>'.money((float)$r['cleaning_fee']).'</td></tr>';
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
      <section class="card admin-module-card"><div><span class="module-icon">✦</span><h2>Conteúdo e IA</h2><p>Portal público, SEO, avaliações, IA e integrações externas.</p></div><div class="module-links"><a href="index.php?p=content">Conteúdo</a><a href="index.php?p=ai">IA</a><a href="index.php?p=integrations">Integrações</a></div></section>
    </div>';

    $recent=$pdo->query("SELECT r.code,r.checkin,r.checkout,r.status,g.name guest,rm.name room FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id ORDER BY r.id DESC LIMIT 8")->fetchAll();
    $h.='<section class="card admin-recent"><div class="card-head"><h2>Reservas recentes</h2><a href="index.php?p=reservations">Ver todas</a></div><table><tr><th>Reserva</th><th>Hóspede</th><th>Acomodação</th><th>Período</th><th>Status</th></tr>';
    foreach($recent as $r)$h.='<tr><td>'.U::e($r['code']).'</td><td>'.U::e($r['guest']).'</td><td>'.U::e($r['room']).'</td><td>'.U::e($r['checkin']).' → '.U::e($r['checkout']).'</td><td><span class="status-badge">'.U::e(statusPt($r['status'])).'</span></td></tr>';
    $h.='</table></section>';
  }

  render($headTitle,$h);exit;
