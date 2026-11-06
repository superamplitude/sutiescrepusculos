<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $prop=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  
    $xs=$pdo->query("SELECT * FROM attractions WHERE active=1 ORDER BY category,name")->fetchAll();$h='<h1>O que fazer na região</h1><div class=cards>';foreach($xs as $x)$h.='<article class=card>'.($x['photo']?'<img class=card-cover src="'.U::e(safeUrl($x['photo'])).'" alt="'.U::e($x['name']).'">':'').'<h2>'.U::e($x['name']).'</h2><small>'.U::e($x['category']).' · '.U::e($x['distance_text']).'</small><p>'.U::e($x['description']).'</p>'.($x['url']?'<a target=_blank rel=noopener href="'.U::e(safeUrl($x['url'])).'">Ver rota / detalhes</a>':'').'</article>';render('O que fazer',$h.'</div>');exit;
  