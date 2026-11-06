<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $prop=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  
    $q=$pdo->query("SELECT a.category,a.name FROM amenities a JOIN property_amenities pa ON pa.amenity_id=a.id WHERE pa.property_id=1 ORDER BY a.category,a.name");$xs=$q->fetchAll();
    $groups=[];foreach($xs as $x)$groups[$x['category']][]=$x['name'];
    $h='<h1>Estrutura e comodidades</h1><div class=card>';foreach($groups as $cat=>$items){$h.='<h2>'.U::e($cat).'</h2><div class=chips>';foreach($items as $name)$h.='<span>'.U::e($name).'</span>';$h.='</div>';}$h.='</div>';render('Comodidades',$h);exit;
  