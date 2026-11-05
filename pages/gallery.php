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
  $ms=$pdo->query("SELECT m.*,r.name room_name FROM media m LEFT JOIN rooms r ON m.scope='room' AND r.id=m.entity_id ORDER BY m.sort_order,m.id DESC")->fetchAll();$roomsForMedia=$pdo->query("SELECT id,name FROM rooms ORDER BY name")->fetchAll();$h=adminTitle('Galeria','Envie fotos ou vídeos diretamente. Para fotos de atrações, use o módulo Atrações.').'<form method=post enctype="multipart/form-data" class=card>'.Csrf::field().'<div class=grid3><label>Onde usar<select name=scope id=mediaScope><option value=property>Pousada</option><option value=room>Quarto</option></select></label><label>Quarto<select name=room_id id=mediaRoom><option value="">Selecione quando for mídia de quarto</option>';foreach($roomsForMedia as $rr)$h.='<option value="'.$rr['id'].'">'.U::e($rr['name']).'</option>';$h.='</select></label><label>Tipo<select name=type><option value=image>Foto</option><option value=video>Vídeo</option></select></label><label>Upload<input type=file name=media_file accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"></label>'.row('Ou URL externa','url').row('Legenda','caption').row('Texto alternativo','alt_text').row('Ordem','sort_order',0,'number').'</div>'.row('Marcar como capa','is_cover',0,'checkbox').'<button>Adicionar mídia</button></form><div class=gallery>';
  foreach($ms as $m){$src=$m['file_path']?:$m['url'];if(!$src)continue;$h.='<figure>';if($m['type']==='video')$h.='<video controls src="'.U::e(safeUrl($src)).'"></video>';else $h.='<img src="'.U::e(safeUrl($src)).'" alt="'.U::e($m['alt_text']).'">';$h.='<figcaption><small>'.U::e($m['scope']==='room'?('Quarto: '.($m['room_name']??'')):'Pousada').'</small><br>'.U::e($m['caption']).'<form method=post action="index.php?p=media_delete" onsubmit="return confirm(\'Remover esta mídia?\')">'.Csrf::field().'<input type=hidden name=id value="'.$m['id'].'"><input type=hidden name=return_to value=gallery><button class="danger small">Remover</button></form></figcaption></figure>';}
  render('Galeria',$h.'</div>');exit;
