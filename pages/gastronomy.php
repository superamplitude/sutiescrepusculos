<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $prop=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  
    $q=$pdo->prepare("SELECT * FROM contents WHERE slug='gastronomia' AND status='published' LIMIT 1");$q->execute();$c=$q->fetch();
    $q2=$pdo->query("SELECT a.name FROM amenities a JOIN property_amenities pa ON pa.amenity_id=a.id WHERE pa.property_id=1 AND a.name IN('Café da manhã','Restaurante','Bar','Serviço de quarto') ORDER BY a.name");$items=$q2->fetchAll(PDO::FETCH_COLUMN);
    $h='<h1>Gastronomia</h1><div class=card>';if($c)$h.='<h2>'.U::e($c['title']).'</h2><p>'.nl2br(U::e($c['body'])).'</p>';if($items){$h.='<div class=chips>';foreach($items as $i)$h.='<span>'.U::e($i).'</span>';$h.='</div>';}$h.='</div>';render($c['seo_title']??'Gastronomia',$h,$c['meta_description']??'');exit;
  