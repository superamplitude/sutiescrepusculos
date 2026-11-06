<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('content.publish');
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$upload=new UploadService();$type=$_POST['type']==='video'?'video':'image';$filePath=null;
    if(!empty($_FILES['media_file']['name']))$filePath=$type==='video'?$upload->video($_FILES['media_file'],'gallery'):$upload->image($_FILES['media_file'],'gallery');
    $url=trim($_POST['url']??'');if(!$filePath&&!$url)throw new RuntimeException('Envie um arquivo ou informe uma URL.');
    $scope=in_array($_POST['scope']??'property',['property','room'],true)?$_POST['scope']:'property';$entityId=$scope==='room'?(int)($_POST['room_id']??0):1;if($scope==='room'&&!$entityId)throw new RuntimeException('Selecione o quarto.');
    $isCover=isset($_POST['is_cover'])?1:0;$pdo->prepare("INSERT INTO media(scope,entity_id,type,file_path,url,caption,alt_text,sort_order,is_cover,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())")->execute([$scope,$entityId,$type,$filePath,$url,$_POST['caption'],$_POST['alt_text'],(int)$_POST['sort_order'],$isCover]);$mediaId=(int)$pdo->lastInsertId();$src=$filePath?:$url;
    if($isCover&&$src){
      if($scope==='property'){
        if($type==='image')$pdo->prepare("UPDATE property SET hero_media_type='image',hero_image=?,updated_at=NOW() WHERE id=1")->execute([$src]);
        else $pdo->prepare("UPDATE property SET hero_media_type='image',hero_video=?,updated_at=NOW() WHERE id=1")->execute([$src]);
      }elseif($scope==='room'&&$entityId){
        if($type==='image')$pdo->prepare("UPDATE rooms SET cover_image=?,updated_at=NOW() WHERE id=?")->execute([$src,$entityId]);
        else $pdo->prepare("UPDATE rooms SET video_path=?,updated_at=NOW() WHERE id=?")->execute([$src,$entityId]);
      }
    }
    Audit::log('media.create','media',$mediaId);U::redirect('index.php?p=gallery');
  }
