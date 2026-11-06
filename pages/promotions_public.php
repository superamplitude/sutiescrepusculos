<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $prop=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  
    $xs=$pdo->query("SELECT * FROM promotions WHERE active=1 AND (start_date IS NULL OR start_date<=CURDATE()) AND (end_date IS NULL OR end_date>=CURDATE()) ORDER BY id DESC")->fetchAll();
    $h='<h1>Tarifas e promoções</h1><div class=cards>';foreach($xs as $x)$h.='<article class=card><h2>'.U::e($x['title']).'</h2><p>'.nl2br(U::e($x['description'])).'</p>'.($x['code']?'<p>Código: <b>'.U::e($x['code']).'</b></p>':'').'</article>';render('Promoções',$h.'</div>');exit;
  