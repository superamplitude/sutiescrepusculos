<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('property.edit');
  $upload=new UploadService();

  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();
    $action=$_POST['action']??'upload';

    if($action==='clear_all'){
      Auth::requirePerm('property.edit');
      $files=$pdo->query("SELECT file_path FROM media WHERE scope='property' AND entity_id=1")->fetchAll(PDO::FETCH_COLUMN);
      $pdo->beginTransaction();
      try{
        $pdo->exec("DELETE FROM media WHERE scope='property' AND entity_id=1");
        $pdo->exec("UPDATE property SET hero_media_type='none',hero_image=NULL,hero_video=NULL,hero_video_url=NULL,hero_poster=NULL,updated_at=NOW() WHERE id=1");
        $pdo->commit();
      }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
      }
      foreach($files as $file){
        if($file && str_starts_with((string)$file,'storage/uploads/')){
          $full=dirname(__DIR__).'/'.$file;
          if(is_file($full))@unlink($full);
        }
      }
      Audit::log('hero.media.clear_all','property',1);
      U::flash('ok','Todas as mídias do hero foram removidas.');
      U::redirect('index.php?p=hero');
    }

    if($action==='upload'){
      $caption=trim($_POST['caption']??'');
      $alt=trim($_POST['alt_text']??'');
      $order=(int)($_POST['sort_order']??0);
      $created=0;

      $images=$upload->images($_FILES['hero_images']??[],'property/hero');
      $ins=$pdo->prepare("INSERT INTO media(scope,entity_id,type,file_path,caption,alt_text,sort_order,is_cover,active,created_at) VALUES('property',1,'image',?,?,?,?,0,1,NOW())");
      foreach($images as $path){$ins->execute([$path,$caption,$alt,$order++]);$created++;}

      if(!empty($_FILES['hero_video_file']['name'])){
        $video=$upload->video($_FILES['hero_video_file'],'property/hero');
        $pdo->prepare("INSERT INTO media(scope,entity_id,type,file_path,caption,alt_text,sort_order,is_cover,active,created_at) VALUES('property',1,'video',?,?,?,?,0,1,NOW())")
          ->execute([$video,$caption,$alt,$order]);
        $created++;
      }

      $url=trim($_POST['video_url']??'');
      if($url!==''){
        if(!safeUrl($url))throw new RuntimeException('URL de vídeo inválida.');
        $pdo->prepare("INSERT INTO media(scope,entity_id,type,url,caption,alt_text,sort_order,is_cover,active,created_at) VALUES('property',1,'video',?,?,?,?,0,1,NOW())")
          ->execute([$url,$caption,$alt,$order]);
        $created++;
      }

      if($created===0)throw new RuntimeException('Selecione pelo menos uma foto, um vídeo ou informe uma URL de vídeo.');
      Audit::log('hero.media.upload','property',1,['count'=>$created]);
      U::flash('ok',"$created mídia(s) adicionada(s) ao hero.");
      U::redirect('index.php?p=hero');
    }

    if($action==='save_media'){
      $id=(int)$_POST['id'];
      $q=$pdo->prepare("SELECT * FROM media WHERE id=? AND scope='property' AND entity_id=1");$q->execute([$id]);$m=$q->fetch();
      if(!$m)throw new RuntimeException('Mídia não encontrada.');

      $caption=trim($_POST['caption']??'');
      $alt=trim($_POST['alt_text']??'');
      $order=(int)($_POST['sort_order']??0);
      $active=isset($_POST['active'])?1:0;

      $pdo->prepare("UPDATE media SET caption=?,alt_text=?,sort_order=?,active=? WHERE id=?")->execute([$caption,$alt,$order,$active,$id]);

      if(isset($_POST['is_cover'])){
        $pdo->prepare("UPDATE media SET is_cover=0 WHERE scope='property' AND entity_id=1")->execute();
        $pdo->prepare("UPDATE media SET is_cover=1,active=1 WHERE id=?")->execute([$id]);
        $src=$m['file_path']?:$m['url'];
        if($m['type']==='image'){
          $pdo->prepare("UPDATE property SET hero_media_type='image',hero_image=?,updated_at=NOW() WHERE id=1")->execute([$src]);
        }else{
          if($m['file_path'])$pdo->prepare("UPDATE property SET hero_media_type='video',hero_video=?,hero_video_url=NULL,updated_at=NOW() WHERE id=1")->execute([$src]);
          else $pdo->prepare("UPDATE property SET hero_media_type='video',hero_video=NULL,hero_video_url=?,updated_at=NOW() WHERE id=1")->execute([$src]);
        }
      }

      Audit::log('hero.media.update','media',$id);
      U::flash('ok','Mídia atualizada.');
      U::redirect('index.php?p=hero');
    }

    if($action==='delete'){
      $id=(int)$_POST['id'];
      $q=$pdo->prepare("SELECT id,type,file_path,url,is_cover FROM media WHERE id=? AND scope='property' AND entity_id=1");$q->execute([$id]);$m=$q->fetch();
      if(!$m)throw new RuntimeException('Mídia não encontrada.');

      $file=(string)($m['file_path']??'');
      $pdo->beginTransaction();
      try{
        $pdo->prepare("DELETE FROM media WHERE id=? AND scope='property' AND entity_id=1")->execute([$id]);

        if((int)$m['is_cover']===1){
          $pdo->exec("UPDATE media SET is_cover=0 WHERE scope='property' AND entity_id=1");
          $next=$pdo->query("SELECT id,type,file_path,url FROM media WHERE scope='property' AND entity_id=1 AND active=1 ORDER BY sort_order,id LIMIT 1")->fetch();
          if($next){
            $pdo->prepare("UPDATE media SET is_cover=1 WHERE id=?")->execute([(int)$next['id']]);
            if($next['type']==='image'){
              $src=$next['file_path']?:$next['url'];
              $pdo->prepare("UPDATE property SET hero_media_type='image',hero_image=?,hero_video=NULL,hero_video_url=NULL,updated_at=NOW() WHERE id=1")->execute([$src]);
            }else{
              $pdo->prepare("UPDATE property SET hero_media_type='video',hero_image=NULL,hero_video=?,hero_video_url=?,updated_at=NOW() WHERE id=1")
                ->execute([$next['file_path']?:null,$next['url']?:null]);
            }
          }else{
            $pdo->exec("UPDATE property SET hero_media_type='none',hero_image=NULL,hero_video=NULL,hero_video_url=NULL,updated_at=NOW() WHERE id=1");
          }
        }

        $pdo->commit();
      }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
      }

      if($file && str_starts_with($file,'storage/uploads/'))@unlink(dirname(__DIR__).'/'.$file);
      Audit::log('hero.media.delete','media',$id);
      U::flash('ok','Mídia removida do hero.');
      U::redirect('index.php?p=hero');
    }
  }

  if(!isset($media))$media=$pdo->query("SELECT * FROM media WHERE scope='property' AND entity_id=1 ORDER BY is_cover DESC,sort_order,id")->fetchAll();
  $h=adminTitle('Editor do Hero','Adicione, ordene, ligue/desligue e defina a mídia principal da capa.');
  if($media=$pdo->query("SELECT * FROM media WHERE scope='property' AND entity_id=1 ORDER BY is_cover DESC,sort_order,id")->fetchAll()){
    $h.='<form method=post class="hero-clear-form" onsubmit="return confirm(\'Remover TODAS as mídias do hero?\')">'.Csrf::field().'<input type=hidden name=action value=clear_all><button class="danger">Excluir todas as mídias do Hero</button></form>';
  }

  $h.='<form method=post enctype="multipart/form-data" class="card hero-upload-card">'.Csrf::field().'<input type=hidden name=action value=upload>
    <div class=card-head><div><h2>Adicionar mídias</h2><p>Você pode enviar várias fotos de uma vez e também vídeo.</p></div></div>
    <div class=grid2>
      <label>Fotos do hero<input type=file name="hero_images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
      <label>Vídeo do hero<input type=file name=hero_video_file accept="video/mp4,video/webm"></label>
      '.row('Ou URL direta do vídeo','video_url').row('Legenda padrão','caption').row('Texto alternativo','alt_text').row('Ordem inicial','sort_order',0,'number').'
    </div><button>Adicionar ao hero</button></form>';

  $h.='<div class="hero-admin-grid">';
  if(!$media)$h.='<div class="card empty-state"><h3>Nenhuma mídia no hero</h3><p>Envie fotos ou vídeo usando o formulário acima.</p></div>';
  foreach($media as $m){
    $src=safeUrl($m['file_path']?:$m['url']);
    $preview=$m['type']==='video'
      ? '<video controls preload=metadata src="'.U::e($src).'"></video>'
      : '<img src="'.U::e($src).'" alt="'.U::e($m['alt_text']).'">';

    $h.='<article class="card hero-media-card '.($m['is_cover']?'is-cover':'').'">
      <div class=hero-media-preview>'.$preview.($m['is_cover']?'<span class=cover-badge>CAPA PRINCIPAL</span>':'').'</div>
      <form method=post>'.Csrf::field().'<input type=hidden name=action value=save_media><input type=hidden name=id value="'.$m['id'].'">
        <div class=grid2>'.row('Legenda','caption',$m['caption']).row('Texto alternativo','alt_text',$m['alt_text']).row('Ordem','sort_order',$m['sort_order'],'number').'</div>
        <div class=hero-toggle-row>'.row('Exibir no hero','active',$m['active']??1,'checkbox').row('Usar como capa principal','is_cover',$m['is_cover'],'checkbox').'</div>
        <button>Salvar mídia</button>
      </form>
      <form method=post onsubmit="return confirm(\'Remover esta mídia do hero?\')">'.Csrf::field().'<input type=hidden name=action value=delete><input type=hidden name=id value="'.$m['id'].'"><button class="danger small">Remover</button></form>
    </article>';
  }
  $h.='</div>';
  render('Editor do Hero',$h);exit;
