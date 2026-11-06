<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }


  $rs=$pdo->query("SELECT * FROM rooms WHERE active=1 AND status='active' ORDER BY name")->fetchAll();$h="<h1>Acomodações</h1><div class=cards>";
  foreach($rs as $r){$img=$r['cover_image']?'<img class="card-cover" src="'.U::e(safeUrl($r['cover_image'])).'" alt="'.U::e($r['name']).'">':'';$h.="<article class=\"card room-card\">$img<h2>".U::e($r['name'])."</h2><p>".U::e($r['short_description'])."</p><b>".money((float)$r['base_price'])."</b><a href=\"index.php?p=room&id={$r['id']}\">Detalhes</a></article>";}
  render('Acomodações',$h.'</div>');exit;
