<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('content.publish');Csrf::check();$id=(int)$_POST['id'];
  $q=$pdo->prepare("SELECT file_path,url,scope,entity_id,type,is_cover FROM media WHERE id=?");$q->execute([$id]);$m=$q->fetch();$file=$m['file_path']??null;
  if($m&&$m['is_cover']){
    $src=$m['file_path']?:$m['url'];
    if($m['scope']==='property')$pdo->prepare("UPDATE property SET hero_image=IF(hero_image=?,NULL,hero_image),hero_video=IF(hero_video=?,NULL,hero_video),updated_at=NOW() WHERE id=1")->execute([$src,$src]);
    if($m['scope']==='room'&&$m['entity_id'])$pdo->prepare("UPDATE rooms SET cover_image=IF(cover_image=?,NULL,cover_image),video_path=IF(video_path=?,NULL,video_path),updated_at=NOW() WHERE id=?")->execute([$src,$src,(int)$m['entity_id']]);
  }
  $pdo->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
  if($file && str_starts_with($file,'storage/uploads/')){@unlink(dirname(__DIR__).'/'.$file);}
  $ret=in_array($_POST['return_to']??'gallery',['gallery','rooms'],true)?($_POST['return_to']??'gallery'):'gallery';Audit::log('media.delete','media',$id);U::redirect('index.php?p='.$ret);
