<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }


    $s=$pdo->prepare("SELECT * FROM rooms WHERE id=? AND active=1 AND status='active'");$s->execute([(int)($_GET['id']??0)]);$r=$s->fetch();if(!$r){http_response_code(404);render('Não encontrado','<h1>Quarto não encontrado</h1>');exit;}
    $rateQuery=$pdo->prepare("SELECT name,price FROM tariffs WHERE room_id=? AND active=1 AND start_date<=CURDATE() AND end_date>=DATE_ADD(CURDATE(),INTERVAL 1 DAY) ORDER BY priority DESC,id DESC LIMIT 1");
    $rateQuery->execute([(int)$r['id']]);$todayRate=$rateQuery->fetch()?:null;
    $displayRate=$todayRate?(float)$todayRate['price']:null;
    $ams=$pdo->prepare("SELECT a.name FROM amenities a JOIN room_amenities ra ON ra.amenity_id=a.id WHERE ra.room_id=? ORDER BY a.name");$ams->execute([$r['id']]);
    $med=$pdo->prepare("SELECT * FROM media WHERE scope='room' AND entity_id=? AND active=1 ORDER BY is_cover DESC,sort_order,id");$med->execute([$r['id']]);$media=$med->fetchAll();
    $h="<h1>".U::e($r['name'])."</h1>";
    if($media){$h.='<div class="gallery room-gallery">';foreach($media as $m){$src=$m['file_path']?:$m['url'];if(!$src)continue;if($m['type']==='video')$h.='<video controls src="'.U::e(safeUrl($src)).'"></video>';else $h.='<figure><img src="'.U::e(safeUrl($src)).'" alt="'.U::e($m['alt_text']).'"><figcaption>'.U::e($m['caption']).'</figcaption></figure>';}$h.='</div>';}
    elseif($r['cover_image'])$h.=mediaPreview($r['cover_image']);
    if($r['video_path'])$h.=mediaPreview($r['video_path'],'video');elseif($r['video_url'])$h.='<p><a target="_blank" rel="noopener" href="'.U::e(safeUrl($r['video_url'])).'">Assistir vídeo da acomodação</a></p>';
    $h.="<div class=card><p>".nl2br(U::e($r['description']))."</p><div class=\"detail-grid\"><p><b>Capacidade</b><br>{$r['capacity']} hóspedes</p><p><b>Adultos</b><br>{$r['adults']}</p><p><b>Crianças</b><br>{$r['children']}</p><p><b>Banheiro</b><br>".U::e($r['bathroom_type'])."</p><p><b>Vista</b><br>".U::e($r['view_name'])."</p><p><b>Área</b><br>".U::e($r['area_m2'])." m²</p></div><p><b>Diária de hoje:</b> ".($displayRate!==null?money($displayRate):"A consultar").($todayRate?" <small>(Tarifa: ".U::e($todayRate['name']).")</small>":"")."</p><h3>Comodidades</h3><div class=chips>";
    foreach($ams->fetchAll() as $a)$h.="<span>".U::e($a['name'])."</span>";
    $h.="</div><h3>Regras específicas</h3><p>".nl2br(U::e($r['rules']))."</p><a class=btn href=\"index.php?p=availability&room={$r['id']}\">Reservar</a></div>";
    render($r['name'],$h);exit;
  