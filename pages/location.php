<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $x=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  
  $addr=trim(($x['address']??'').' '.($x['address_number']??'').' - '.($x['neighborhood']??'').' - '.($x['city']??'').'/'.($x['state']??''));
  $map='';
  if($x['latitude']!==null&&$x['longitude']!==null&&$x['latitude']!==''&&$x['longitude']!==''){
    $lat=(float)$x['latitude'];$lon=(float)$x['longitude'];$delta=.015;
    $bbox=($lon-$delta).','.($lat-$delta).','.($lon+$delta).','.($lat+$delta);
    $map='<iframe class="map-frame" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.openstreetmap.org/export/embed.html?bbox='.rawurlencode($bbox).'&layer=mapnik&marker='.rawurlencode($lat.','.$lon).'"></iframe>';
  }
  $h='<div class="public-page-hero"><span class=eyebrow>Localização</span><h1>Chegue com facilidade</h1><p>'.U::e($addr).'</p></div><div class=card><p>'.nl2br(U::e($x['directions'])).'</p>'.$map.($x['map_url']?'<a class=btn target=_blank rel=noopener href="'.U::e(safeUrl($x['map_url'])).'">Abrir rota</a>':'').'</div>';
  render('Localização',$h);exit;
