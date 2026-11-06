<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('content.publish');
  if($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action']??'add')==='update')){
    Csrf::check();
    $mediaId=(int)($_POST['id']??0);
    $q=$pdo->prepare("SELECT * FROM media WHERE id=? LIMIT 1");$q->execute([$mediaId]);$existing=$q->fetch();
    if(!$existing)throw new RuntimeException('Mídia não encontrada.');
    $type=(string)$existing['type'];
    if(!in_array($type,['image','video'],true))throw new RuntimeException('Tipo de mídia inválido.');
    $caption=mb_substr(trim((string)($_POST['caption']??'')),0,180);
    $alt=mb_substr(trim((string)($_POST['alt_text']??'')),0,180);
    $sort=(int)($_POST['sort_order']??0);
    if(abs($sort)>100000)throw new RuntimeException('Ordem inválida.');
    $file=$existing['file_path'];$url=$existing['url'];
    $newFile=$_FILES['media_file']??null;
    if($newFile && ($newFile['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
      if(($newFile['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new RuntimeException('Falha no envio da nova mídia.');
      $upload=new UploadService();
      $file=$type==='video'?$upload->video($newFile,'gallery'):$upload->image($newFile,'gallery');
      $url=null;
    }else{
      $replacement=trim((string)($_POST['replacement_url']??''));
      if($replacement!==''){
        if(!filter_var($replacement,FILTER_VALIDATE_URL) || !str_starts_with(strtolower($replacement),'https://'))
          throw new RuntimeException('Informe uma URL HTTPS válida.');
        $url=$replacement;$file=null;
      }
    }
    $source=$file?:$url;
    if(!$source)throw new RuntimeException('Esta mídia precisa de uma imagem, vídeo ou URL.');
    $pdo->prepare("UPDATE media SET file_path=?,url=?,caption=?,alt_text=?,sort_order=? WHERE id=?")
      ->execute([$file,$url,$caption,$alt,$sort,$mediaId]);
    if((int)$existing['is_cover']===1 && ($existing['file_path']?:$existing['url'])!==$source){
      if($existing['scope']==='room'){
        $field=$type==='video'?'video_path':'cover_image';
        $pdo->prepare("UPDATE rooms SET $field=?,updated_at=NOW() WHERE id=?")->execute([$source,(int)$existing['entity_id']]);
      }elseif($existing['scope']==='property'){
        $field=$type==='video'?'hero_video':'hero_image';
        $pdo->prepare("UPDATE property SET $field=?,updated_at=NOW() WHERE id=1")->execute([$source]);
      }
    }
    Audit::log('media.update','media',$mediaId,['image_replaced'=>$source!==($existing['file_path']?:$existing['url'])]);
    U::flash('ok','Mídia atualizada.');
    U::redirect('index.php?p=gallery');
  }
  throw new RuntimeException('Ação de edição inválida.');
