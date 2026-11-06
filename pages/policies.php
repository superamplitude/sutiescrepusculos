<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $prop=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  
    $h='<h1>Políticas da hospedagem</h1><div class=card><h2>Cancelamento</h2><p>'.nl2br(U::e($prop['cancellation_policy'])).'</p><h2>Check-in</h2><p>'.nl2br(U::e($prop['checkin_rules'])).'</p><h2>Check-out</h2><p>'.nl2br(U::e($prop['checkout_rules'])).'</p><h2>Convivência</h2><p>'.nl2br(U::e($prop['house_rules'])).'</p></div>';render('Políticas',$h);exit;
  