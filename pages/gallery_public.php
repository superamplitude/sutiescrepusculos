<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $ms=$pdo->query("SELECT * FROM media WHERE scope IN('property','room') AND active=1 ORDER BY sort_order,id DESC")->fetchAll();$h="<h1>Galeria</h1><div class=gallery>";
  foreach($ms as $m){$src=$m['file_path']?:$m['url'];if(!$src)continue;if($m['type']==='video')$h.="<figure><video controls src=\"".U::e(safeUrl($src))."\"></video><figcaption>".U::e($m['caption'])."</figcaption></figure>";else $h.="<figure><img src=\"".U::e(safeUrl($src))."\" alt=\"".U::e($m['alt_text'])."\"><figcaption>".U::e($m['caption'])."</figcaption></figure>";}
  render('Galeria',$h.'</div>');exit;
