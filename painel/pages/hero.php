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
          $full=dirname(__DIR__,2).'/'.$file;
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

      if($file && str_starts_with($file,'storage/uploads/'))@unlink(dirname(__DIR__,2).'/'.$file);
      Audit::log('hero.media.delete','media',$id);
      U::flash('ok','Mídia removida do hero.');
      U::redirect('index.php?p=hero');
    }
  }

  $media=$pdo->query("SELECT * FROM media WHERE scope='property' AND entity_id=1 ORDER BY is_cover DESC,sort_order,id")->fetchAll();
  $h=adminTitle('Editor do Hero','Organize as fotos e vídeos da capa do site.');
  $h.='<div class="hero-admin-toolbar"><button type="button" class="hero-add-button" data-hero-open="heroAddDialog">+ Adicionar mídia</button>';
  if($media){
    $h.='<form method="post" class="hero-clear-form" onsubmit="return confirm(\'Remover TODAS as mídias do hero?\')">'
      .Csrf::field().'<input type="hidden" name="action" value="clear_all"><button type="submit" class="danger">Excluir todas as mídias do Hero</button></form>';
  }
  $h.='</div>';

  $h.='<dialog id="heroAddDialog" class="hero-admin-dialog" aria-labelledby="heroAddTitle">'
    .'<div class="hero-dialog-head"><div><h2 id="heroAddTitle">Adicionar mídias</h2><p>Envie fotos ou vídeos para a capa do site.</p></div>'
    .'<button type="button" class="hero-dialog-close" data-hero-close aria-label="Fechar janela">×</button></div>'
    .'<form method="post" enctype="multipart/form-data" class="hero-dialog-form">'
    .Csrf::field().'<input type="hidden" name="action" value="upload">'
    .'<div class="grid2">'
    .'<label>Fotos do hero<input type="file" name="hero_images[]" accept="image/jpeg,image/png,image/webp" multiple></label>'
    .'<label>Vídeo do hero<input type="file" name="hero_video_file" accept="video/mp4,video/webm"></label>'
    .row('Ou URL direta do vídeo','video_url')
    .row('Legenda padrão','caption')
    .row('Texto alternativo','alt_text')
    .row('Ordem inicial','sort_order',0,'number')
    .'</div><div class="hero-dialog-actions"><button type="button" class="hero-dialog-cancel" data-hero-close>Cancelar</button><button type="submit">Adicionar ao hero</button></div>'
    .'</form></dialog>';

  $h.='<div class="hero-admin-grid" aria-label="Mídias do Hero">';
  if(!$media)$h.='<div class="card empty-state"><h3>Nenhuma mídia no hero</h3><p>Clique em Adicionar mídia para enviar fotos ou vídeos.</p></div>';
  foreach($media as $m){
    $id=(int)$m['id'];
    $src=safeUrl($m['file_path']?:$m['url']);
    $isCover=(int)($m['is_cover']??0)===1;
    $isActive=(int)($m['active']??0)===1;
    $title=trim((string)($m['caption']??''))?:($m['type']==='video'?'Vídeo':'Foto').' #'.$id;
    $thumbnail=$m['type']==='video'
      ? '<video src="'.U::e($src).'" muted playsinline preload="metadata"></video><span class="hero-media-type">Vídeo</span>'
      : '<img src="'.U::e($src).'" loading="lazy" alt="'.U::e($m['alt_text']?:$title).'">';
    $h.='<article class="hero-media-tile'.($isCover?' is-cover':'').($isActive?'':' is-inactive').'">'
      .'<button type="button" class="hero-media-open" data-hero-open="heroEditDialog'.$id.'" aria-label="Editar '.U::e($title).'">'
      .'<span class="hero-media-preview">'.$thumbnail.($isCover?'<span class="cover-badge">CAPA PRINCIPAL</span>':'').'</span>'
      .'<span class="hero-tile-caption">'.U::e($title).'</span>'
      .'<span class="hero-tile-meta">'.($isActive?'Visível':'Oculta').' · Ordem '.(int)$m['sort_order'].'</span></button></article>';

    $largePreview=$m['type']==='video'
      ? '<video controls preload="metadata" src="'.U::e($src).'"></video>'
      : '<img src="'.U::e($src).'" alt="'.U::e($m['alt_text']?:$title).'">';
    $h.='<dialog id="heroEditDialog'.$id.'" class="hero-admin-dialog hero-edit-dialog" aria-labelledby="heroEditTitle'.$id.'">'
      .'<div class="hero-dialog-head"><div><h2 id="heroEditTitle'.$id.'">Editar mídia #'.$id.'</h2><p>Legenda, ordem, visibilidade e capa principal.</p></div>'
      .'<button type="button" class="hero-dialog-close" data-hero-close aria-label="Fechar janela">×</button></div>'
      .'<div class="hero-dialog-preview">'.$largePreview.'</div>'
      .'<form method="post" class="hero-dialog-form">'.Csrf::field().'<input type="hidden" name="action" value="save_media">'
      .'<input type="hidden" name="id" value="'.$id.'">'
      .'<div class="grid2">'.row('Legenda','caption',$m['caption'])
      .row('Texto alternativo','alt_text',$m['alt_text'])
      .row('Ordem','sort_order',$m['sort_order'],'number').'</div>'
      .'<div class="hero-toggle-row">'.row('Exibir no hero','active',$m['active']??1,'checkbox')
      .row('Usar como capa principal','is_cover',$m['is_cover'],'checkbox').'</div>'
      .'<div class="hero-dialog-actions"><button type="button" class="hero-dialog-cancel" data-hero-close>Cancelar</button><button type="submit">Salvar mídia</button></div></form>'
      .'<form method="post" class="hero-dialog-delete" onsubmit="return confirm(\'Remover esta mídia do hero?\')">'
      .Csrf::field().'<input type="hidden" name="action" value="delete">'
      .'<input type="hidden" name="id" value="'.$id.'">'
      .'<button type="submit" class="danger small">Remover mídia</button></form></dialog>';
  }
  $h.='</div>';
  $h.='<script defer src="assets/js/hero-admin.js?v=20261106"></script>';
  render('Editor do Hero',$h);exit;
