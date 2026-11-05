<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('reports.view');
  $total=(int)$pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();$cancel=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status='cancelada'")->fetchColumn();$revenue=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetchColumn();$avg=(float)$pdo->query("SELECT COALESCE(AVG(total),0) FROM reservations WHERE status IN('paga','confirmada','concluida')")->fetchColumn();$rating=(float)$pdo->query("SELECT COALESCE(AVG(rating),0) FROM reviews WHERE status='published'")->fetchColumn();
  $h=adminTitle('Relatórios','Indicadores operacionais.').'<div class=stats><div class=stat><span>Reservas</span><b>'.$total.'</b></div><div class=stat><span>Cancelamentos</span><b>'.$cancel.'</b></div><div class=stat><span>Receita confirmada</span><b>'.money($revenue).'</b></div><div class=stat><span>Ticket médio</span><b>'.money($avg).'</b></div><div class=stat><span>Avaliação média</span><b>'.number_format($rating,1,',','.').'</b></div></div>';
  render('Relatórios',$h);exit;
