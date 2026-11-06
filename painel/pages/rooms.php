<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('rooms.manage');
  $view=$_GET['view']??'list';
  // Existing old unit URLs are preserved, but their implementation now lives in its own page file.
  if($_SERVER['REQUEST_METHOD']==='POST'||$view!=='list'){
    require __DIR__.'/room_manage.php';exit;
  }
  $rooms=$pdo->query("SELECT * FROM rooms WHERE code NOT LIKE '__deleted__%' ORDER BY active DESC,name")->fetchAll();
  if($view==='list'){
    $todayRates=[];
    $q=$pdo->query("SELECT room_id,name,price FROM tariffs WHERE active=1 AND start_date<=CURDATE() AND end_date>=DATE_ADD(CURDATE(),INTERVAL 1 DAY) ORDER BY priority DESC,id DESC");
    foreach($q->fetchAll() as $rate){$key=(int)$rate['room_id'];if(!isset($todayRates[$key]))$todayRates[$key]=$rate;}
    $galleryCovers=[];
    $q=$pdo->query("SELECT entity_id,file_path,url FROM media WHERE scope='room' AND type='image' ORDER BY is_cover DESC,id ASC");
    foreach($q->fetchAll() as $image){$roomKey=(int)$image['entity_id'];if(!isset($galleryCovers[$roomKey]))$galleryCovers[$roomKey]=(string)($image['file_path']?:$image['url']);}
    $h='<div class="module-actions rooms-list-actions"><a class="btn" href="index.php?p=room_manage&view=new">+ Adicionar unidade</a></div>';
    $h.='<div class="card rooms-list-card"><div class="rooms-table-scroll"><table class="rooms-list-table"><thead><tr><th>Foto</th><th>Nome</th><th>Status</th><th>Capacidade</th><th>Diária de hoje</th><th>Diária / Pacote</th><th>Ações</th></tr></thead><tbody>';
    foreach($rooms as $r){
      $rid=(int)$r['id'];
      $editUrl='index.php?p=room_manage&view=edit&id='.$rid;
      $photo=trim((string)($r['cover_image']?:($galleryCovers[$rid]??'')));
      $photoHtml=$photo!==''
        ?'<img src="'.U::e(safeUrl($photo)).'" loading="lazy" alt="Foto de '.U::e($r['name']).'">'
        :'<span class="room-photo-empty" aria-label="Sem foto">Sem foto</span>';
      $h.='<tr><td><a class="room-photo-link" href="'.$editUrl.'">'.$photoHtml.'</a></td>'
        .'<td><a class="room-name-link" href="'.$editUrl.'">'.U::e($r['name']).'</a><small>'.U::e($r['code']).'</small></td>'
        .'<td>'.U::e(statusPt($r['status'])).'</td><td>'.(int)$r['capacity'].'</td>'
        .'<td class="room-price-current">'.(isset($todayRates[$rid])?money((float)$todayRates[$rid]['price']):'Sem diária')
        .'<small>'.(isset($todayRates[$rid])?'Tarifa: '.U::e($todayRates[$rid]['name']):'Cadastre em Diária').'</small></td>'
        .'<td><div class="room-price-actions"><a class="btn secondary small" href="'.$editUrl.'&section=diaria#room-pricing">Diária</a><a class="btn secondary small" href="'.$editUrl.'&section=pacote#room-pricing">Pacote</a></div></td>'
        .'<td><div class="table-actions"><a class="btn secondary small" href="'.$editUrl.'&form=edit">Editar</a>'
        .'<form method="post" class="inline-form" onsubmit="return confirm(\'Excluir esta unidade? Reservas históricas serão preservadas.\')">'
        .Csrf::field().'<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.$rid.'">'
        .'<button class="danger small" type="submit">Excluir</button></form></div></td></tr>';
    }
    $h.='</tbody></table></div></div>';
    render('Quartos / Unidades',$h);exit;
  }
