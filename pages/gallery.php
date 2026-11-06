<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('content.publish');
  if($_SERVER['REQUEST_METHOD']==='POST'){
    // Preserve legacy form URLs while keeping each media action in its own route.
    if(($_POST['action']??'add')==='update')require __DIR__.'/gallery_media_update.php';
    else require __DIR__.'/gallery_media_add.php';
    exit;
  }
  $ms=$pdo->query("SELECT m.*,r.name room_name FROM media m LEFT JOIN rooms r ON m.scope='room' AND r.id=m.entity_id ORDER BY m.sort_order,m.id DESC")->fetchAll();
  $roomsForMedia=$pdo->query("SELECT id,name FROM rooms ORDER BY name")->fetchAll();
  $openForm=(($_GET['form']??'')==='add');
  $h='<div class="gallery-toolbar"><a class="btn" href="index.php?p=gallery&form=add" data-gallery-open="galleryAddDialog">+ Adicionar mídia</a></div>';
  $h.='<dialog id="galleryAddDialog" class="gallery-admin-dialog" aria-labelledby="galleryAddTitle"'.($openForm?' open data-gallery-auto="1"':'').'>'
    .'<div class="gallery-dialog-heading"><div><h2 id="galleryAddTitle">Adicionar mídia</h2><p>Fotos e vídeos para a pousada ou unidades.</p></div>'
    .'<a href="index.php?p=gallery" data-gallery-close aria-label="Fechar janela">×</a></div>'
    .'<form method="post" action="index.php?p=gallery_media_add" enctype="multipart/form-data" class="gallery-dialog-form">'.Csrf::field()
    .'<div class="gallery-form-grid"><label>Onde usar<select name="scope" id="mediaScope"><option value="property">Pousada</option><option value="room">Quarto</option></select></label>'
    .'<label>Quarto<select name="room_id" id="mediaRoom"><option value="">Selecione quando for mídia de quarto</option>';
  foreach($roomsForMedia as $rr)$h.='<option value="'.(int)$rr['id'].'">'.U::e($rr['name']).'</option>';
  $h.='</select></label><label>Tipo<select name="type"><option value="image">Foto</option><option value="video">Vídeo</option></select></label>'
    .'<label>Upload<input type="file" name="media_file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"></label>'
    .row('Ou URL externa','url').row('Legenda','caption').row('Texto alternativo','alt_text')
    .row('Ordem','sort_order',0,'number').'</div>'
    .row('Marcar como capa','is_cover',0,'checkbox')
    .'<div class="gallery-dialog-actions"><a href="index.php?p=gallery" class="btn secondary" data-gallery-close>Cancelar</a><button type="submit">Adicionar mídia</button></div>'
    .'</form></dialog><div class="gallery">';
  $editDialogs='';
  foreach($ms as $m){
    $id=(int)$m['id'];
    $src=(string)($m['file_path']?:$m['url']);
    if($src==='')continue;
    $safe=U::e(safeUrl($src));
    $kind=(string)$m['type'];
    $scope=$m['scope']==='room'?('Quarto: '.($m['room_name']??'')):'Pousada';
    $title=trim((string)$m['caption'])?:($kind==='video'?'Vídeo':'Foto').' #'.$id;
    $openUrl='index.php?p=gallery&form=edit&id='.$id;
    $thumbnail=$kind==='video'?'<video src="'.$safe.'" muted playsinline preload="metadata"></video>':'<img src="'.$safe.'" alt="'.U::e($m['alt_text']?:$title).'" loading="lazy">';
    $h.='<figure class="gallery-edit-tile"><a class="gallery-photo-open" href="'.$openUrl.'" data-gallery-open="galleryEditDialog'.$id.'" aria-label="Editar '.U::e($title).'">'.$thumbnail.'</a>'
      .'<figcaption><small>'.U::e($scope).'</small><br>'.U::e($m['caption']).'<form method=post action="index.php?p=media_delete" onsubmit="return confirm(\'Remover esta mídia?\')">'.Csrf::field()
      .'<input type=hidden name=id value="'.$id.'"><input type=hidden name=return_to value=gallery><button class="danger small">Remover</button></form></figcaption></figure>';
    $preview=$kind==='video'?'<video controls src="'.$safe.'" preload="metadata"></video>':'<img src="'.$safe.'" alt="'.U::e($m['alt_text']?:$title).'">';
    $editDialogs.='<dialog id="galleryEditDialog'.$id.'" class="gallery-admin-dialog gallery-edit-dialog" aria-labelledby="galleryEditTitle'.$id.'"'
      .((($_GET['form']??'')==='edit' && (int)($_GET['id']??0)===$id)?' open data-gallery-auto="1"':'').'>'
      .'<div class="gallery-dialog-heading"><div><h2 id="galleryEditTitle'.$id.'">Editar mídia</h2><p>'.U::e($scope).'</p></div>'
      .'<a href="index.php?p=gallery" data-gallery-close aria-label="Fechar">×</a></div>'
      .'<div class="gallery-edit-preview">'.$preview.'</div>'
      .'<form method="post" action="index.php?p=gallery_media_update" enctype="multipart/form-data" class="gallery-dialog-form">'.Csrf::field()
      .'<input type="hidden" name="action" value="update"><input type="hidden" name="id" value="'.$id.'">'
      .'<div class="gallery-form-grid"><label>Substituir '.($kind==='video'?'vídeo':'foto')
      .'<input type="file" name="media_file" accept="'.($kind==='video'?'video/mp4,video/webm':'image/jpeg,image/png,image/webp').'"></label>'
      .'<label>Nova URL externa (opcional)<input type="url" name="replacement_url" placeholder="https://"></label>'
      .'<label>Legenda<input type="text" name="caption" maxlength="180" value="'.U::e($m['caption']).'"></label>'
      .'<label>Texto alternativo<input type="text" name="alt_text" maxlength="180" value="'.U::e($m['alt_text']).'"></label>'
      .'<label>Ordem<input type="number" name="sort_order" min="-100000" max="100000" value="'.(int)$m['sort_order'].'"></label></div>'
      .'<div class="gallery-dialog-actions"><a href="index.php?p=gallery" class="btn secondary" data-gallery-close>Cancelar</a>'
      .'<button type="submit">Salvar alterações</button></div></form></dialog>';
  }
  $h.='</div>'.$editDialogs.'<script defer src="assets/js/gallery-admin.js?v=20261106b"></script>';
  render('Galeria',$h);exit;
