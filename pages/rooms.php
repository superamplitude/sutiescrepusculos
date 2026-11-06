<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('rooms.manage');
  $view=$_GET['view']??'list';
  if(!in_array($view,['list','new','edit'],true))$view='list';
  $edit=null;
  if($view==='edit'){
    $id=(int)($_GET['id']??0);
    if($id<1){U::flash('err','Unidade inválida.');U::redirect('index.php?p=rooms');}
    $q=$pdo->prepare("SELECT * FROM rooms WHERE id=? AND code NOT LIKE '__deleted__%'");$q->execute([$id]);$edit=$q->fetch();
    if(!$edit){U::flash('err','Unidade não encontrada ou já excluída.');U::redirect('index.php?p=rooms');}
  }
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$upload=new UploadService();
    $id=(int)($_POST['id']??0);$action=$_POST['action']??'save';
    if(in_array($action,['tariff_add','package_add','tariff_toggle','package_toggle','tariff_update','package_update'],true)){
      Auth::requirePerm('prices.edit');
      $rid=(int)($_POST['room_id']??0);
      $q=$pdo->prepare("SELECT id FROM rooms WHERE id=? AND code NOT LIKE '__deleted__%' LIMIT 1");$q->execute([$rid]);
      if(!$q->fetchColumn())throw new RuntimeException('Unidade não encontrada.');
      $type=str_starts_with($action,'tariff_')?'diaria':'pacote';
      $back='index.php?p=rooms&view=edit&id='.$rid.'&section='.$type;
      if(in_array($action,['tariff_toggle','package_toggle'],true)){
        $record=(int)($_POST['record_id']??0);
        $table=$type==='diaria'?'tariffs':'room_packages';
        $q=$pdo->prepare("UPDATE $table SET active=IF(active=1,0,1) WHERE id=? AND room_id=?");
        $q->execute([$record,$rid]);
        if($q->rowCount()!==1)throw new RuntimeException('Registro de tarifa/pacote não encontrado.');
        Audit::log('room.'.$type.'.toggle','room',$rid,['record_id'=>$record]);
        U::flash('ok','Status atualizado.');U::redirect($back);
      }
      if(in_array($action,['tariff_update','package_update'],true)){
        $record=(int)($_POST['record_id']??0);
        $raw=trim((string)($_POST['price']??''));
        if($record<1||$raw===''||!is_numeric($raw))throw new RuntimeException('Informe um valor válido.');
        $price=(float)$raw;
        if(!is_finite($price)||$price<0||$price>1000000)throw new RuntimeException('Valor inválido.');
        $table=$action==='tariff_update'?'tariffs':'room_packages';
        $q=$pdo->prepare("UPDATE $table SET price=? WHERE id=? AND room_id=?");
        $q->execute([$price,$record,$rid]);
        if($q->rowCount()===0){
          $check=$pdo->prepare("SELECT id FROM $table WHERE id=? AND room_id=?");$check->execute([$record,$rid]);
          if(!$check->fetchColumn())throw new RuntimeException('Registro de diária ou pacote não encontrado.');
        }
        Audit::log('room.'.$type.'.price.update','room',$rid,['record_id'=>$record,'price'=>$price]);
        U::flash('ok',$type==='diaria'?'Valor da diária atualizado.':'Valor do pacote atualizado.');
        U::redirect($back.'#room-pricing');
      }
      $name=trim((string)($_POST['name']??''));
      $start=(string)($_POST['start_date']??'');
      $end=(string)($_POST['end_date']??'');
      $d1=DateTimeImmutable::createFromFormat('!Y-m-d',$start);
      $d2=DateTimeImmutable::createFromFormat('!Y-m-d',$end);
      if(mb_strlen($name)<3||mb_strlen($name)>120||!$d1||!$d2||$d1->format('Y-m-d')!==$start||$d2->format('Y-m-d')!==$end||$start>$end)
        throw new RuntimeException('Informe nome e período válidos.');
      $price=filter_var($_POST['price']??null,FILTER_VALIDATE_FLOAT);
      if($price===false||$price<0||$price>1000000)throw new RuntimeException('Informe um preço válido.');
      if($type==='diaria'){
        $nights=(int)($_POST['min_nights']??1);
        if($nights<1||$nights>365)throw new RuntimeException('Mínimo de noites inválido.');
        $priority=(int)($_POST['priority']??0);
        if($priority<0||$priority>10000)throw new RuntimeException('Prioridade inválida.');
        $q=$pdo->prepare('INSERT INTO tariffs(room_id,name,start_date,end_date,price,min_nights,priority,active) VALUES(?,?,?,?,?,?,?,1)');
        $q->execute([$rid,$name,$start,$end,$price,$nights,$priority]);
      }else{
        $nights=(int)($_POST['nights']??0);
        if($nights<1||$nights>365)throw new RuntimeException('Quantidade de noites inválida.');
        $description=mb_substr(trim((string)($_POST['description']??'')),0,3000);
        $q=$pdo->prepare('INSERT INTO room_packages(room_id,name,description,start_date,end_date,nights,price,active,created_at) VALUES(?,?,?,?,?,?,?,1,NOW())');
        $q->execute([$rid,$name,$description,$start,$end,$nights,$price]);
      }
      Audit::log('room.'.$type.'.add','room',$rid,['record_id'=>(int)$pdo->lastInsertId()]);
      U::flash('ok',$type==='diaria'?'Diária cadastrada para esta unidade.':'Pacote cadastrado para esta unidade.');
      U::redirect($back);
    }
    if($action==='base_price_update'){
      Auth::requirePerm('prices.edit');
      $q=$pdo->prepare("SELECT id FROM rooms WHERE id=? AND code NOT LIKE '__deleted__%' LIMIT 1");$q->execute([$id]);
      if($id<1||!$q->fetchColumn())throw new RuntimeException('Unidade não encontrada.');
      $raw=trim((string)($_POST['base_price']??''));
      if($raw===''||!is_numeric($raw))throw new RuntimeException('Informe uma diária-base válida.');
      $value=(float)$raw;
      if(!is_finite($value)||$value<0||$value>1000000)throw new RuntimeException('Valor inválido.');
      $pdo->prepare("UPDATE rooms SET base_price=?,updated_at=NOW() WHERE id=?")->execute([$value,$id]);
      Audit::log('room.base_price.update','room',$id,['base_price'=>$value]);
      U::flash('ok','Diária-base atualizada na unidade e no portal.');
      U::redirect('index.php?p=rooms&view=edit&id='.$id.'&section=diaria#room-pricing');
    }
    if($action==='room_media_add'){
      $q=$pdo->prepare("SELECT id,cover_image,video_path,video_url FROM rooms WHERE id=? AND code NOT LIKE '__deleted__%' LIMIT 1");
      $q->execute([$id]);$r=$q->fetch();
      if(!$r)throw new RuntimeException('Unidade inválida.');
      $newCover=(string)($r['cover_image']??'');$newVideo=(string)($r['video_path']??'');
      $changed=false;
      if(!empty($_FILES['cover_image']['name'])){$newCover=$upload->image($_FILES['cover_image'],'rooms');$changed=true;}
      if(!empty($_FILES['video_file']['name'])){$newVideo=$upload->video($_FILES['video_file'],'rooms');$changed=true;}
      $url=trim((string)($_POST['video_url']??''));
      if($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))throw new RuntimeException('Informe uma URL válida para o vídeo.');
      if($url!==''&&stripos($url,'https://')!==0)throw new RuntimeException('A URL do vídeo deve usar HTTPS.');
      if($url!==''&&$url!==(string)($r['video_url']??''))$changed=true;
      $pdo->prepare("UPDATE rooms SET cover_image=?,video_path=?,video_url=CASE WHEN ?<>'' THEN ? ELSE video_url END,updated_at=NOW() WHERE id=?")
        ->execute([$newCover?:null,$newVideo?:null,$url,$url,$id]);
      $imgs=$upload->images($_FILES['gallery_images']??[],'rooms/'.$id);
      if($imgs){
        $mi=$pdo->prepare("INSERT INTO media(scope,entity_id,type,file_path,caption,alt_text,sort_order,is_cover,created_at) VALUES('room',?,'image',?,?,?,0,0,NOW())");
        $caption=mb_substr(trim((string)($_POST['gallery_caption']??'')),0,180);
        $alt=mb_substr(trim((string)($_POST['gallery_alt']??'')),0,180);
        foreach($imgs as $img)$mi->execute([$id,$img,$caption,$alt]);
        $changed=true;
      }
      Audit::log('room.media.add','room',$id,['images'=>count($imgs),'changed'=>$changed]);
      U::flash('ok',$changed?'Mídias da unidade atualizadas.':'Nenhum novo arquivo selecionado.');
      U::redirect('index.php?p=rooms&view=edit&id='.$id);
    }
    if($action==='delete'){
      if($id<1)throw new RuntimeException('Unidade inválida para exclusão.');
      $q=$pdo->prepare("SELECT id,code,name FROM rooms WHERE id=? AND code NOT LIKE '__deleted__%'");$q->execute([$id]);$room=$q->fetch();
      if(!$room)throw new RuntimeException('Unidade não encontrada ou já excluída.');
      $deletedCode='__deleted__'.$id.'_'.date('YmdHis').'_'.$room['code'];
      $pdo->beginTransaction();
      try{
        $pdo->prepare("UPDATE rooms SET code=?,active=0,status='inactive',updated_at=NOW() WHERE id=?")->execute([$deletedCode,$id]);
        $check=$pdo->prepare("SELECT active,status,code FROM rooms WHERE id=?");$check->execute([$id]);$gone=$check->fetch();
        if(!$gone||(int)$gone['active']!==0||$gone['status']!=='inactive'||strpos((string)$gone['code'],'__deleted__')!==0)throw new RuntimeException('Falha ao confirmar exclusão da unidade.');
        $pdo->commit();
      }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
      Audit::log('room.delete','room',$id,['name'=>$room['name']]);U::flash('ok','Unidade excluída. Reservas históricas foram preservadas.');U::redirect('index.php?p=rooms');
    }
    if($action!=='save')throw new RuntimeException('Ação inválida.');
    if(trim($_POST['code']??'')===''||trim($_POST['name']??'')==='')throw new RuntimeException('Código e nome da unidade são obrigatórios.');
    if((int)($_POST['capacity']??0)<1||(int)($_POST['adults']??0)<1)throw new RuntimeException('Informe capacidade máxima e quantidade de adultos.');
    if(!isset($_POST['base_price'])||$_POST['base_price']===''||(float)$_POST['base_price']<0)throw new RuntimeException('Informe uma diária-base válida.');
    if((int)($_POST['min_nights']??0)<1)throw new RuntimeException('Informe o mínimo de noites (1 ou mais).');
    $beds=[];
    foreach(['casal','queen','king','solteiro','solteiro_xl','beliche','sofa_cama','dobravel','murphy'] as $b){$n=max(0,(int)($_POST["bed_$b"]??0));if($n>0)$beds[$b]=$n;}
    $old=['cover_image'=>null,'video_path'=>null];if($id){$q=$pdo->prepare("SELECT cover_image,video_path FROM rooms WHERE id=?");$q->execute([$id]);$old=$q->fetch()?:$old;}
    $cover=$old['cover_image'];$videoPath=$old['video_path'];
    if(!empty($_FILES['cover_image']['name']))$cover=$upload->image($_FILES['cover_image'],'rooms');
    if(!empty($_FILES['video_file']['name']))$videoPath=$upload->video($_FILES['video_file'],'rooms');
    $status=in_array($_POST['status']??'', ['active','inactive','maintenance'],true)?$_POST['status']:'inactive';
    $vals=[trim($_POST['code']),trim($_POST['name']),$_POST['type'],$_POST['short_description'],$_POST['description'],$status,(int)$_POST['capacity'],(int)$_POST['adults'],(int)$_POST['children'],array_sum($beds),json_encode($beds,JSON_UNESCAPED_UNICODE),$_POST['bathroom_type'],$_POST['floor_name'],$_POST['internal_location'],isset($_POST['balcony'])?1:0,$_POST['view_name'],$_POST['area_m2']?:null,$_POST['video_url'],$cover,$videoPath,$_POST['rules'],(float)$_POST['base_price'],(float)$_POST['extra_guest_fee'],(int)$_POST['min_nights'],$_POST['max_nights']!==''?(int)$_POST['max_nights']:null,(float)$_POST['cleaning_fee'],isset($_POST['active'])?1:0];
    if($id){
      $sql="UPDATE rooms SET code=?,name=?,type=?,short_description=?,description=?,status=?,capacity=?,adults=?,children=?,beds_count=?,beds_json=?,bathroom_type=?,floor_name=?,internal_location=?,balcony=?,view_name=?,area_m2=?,video_url=?,cover_image=?,video_path=?,rules=?,base_price=?,extra_guest_fee=?,min_nights=?,max_nights=?,cleaning_fee=?,active=?,updated_at=NOW() WHERE id=?";$vals[]=$id;$pdo->prepare($sql)->execute($vals);
    }else{
      $sql="INSERT INTO rooms(code,name,type,short_description,description,status,capacity,adults,children,beds_count,beds_json,bathroom_type,floor_name,internal_location,balcony,view_name,area_m2,video_url,cover_image,video_path,rules,base_price,extra_guest_fee,min_nights,max_nights,cleaning_fee,active,created_at,updated_at) VALUES(".implode(',',array_fill(0,27,'?')).",NOW(),NOW())";$pdo->prepare($sql)->execute($vals);$id=(int)$pdo->lastInsertId();
    }
    $pdo->prepare("DELETE FROM room_amenities WHERE room_id=?")->execute([$id]);$ins=$pdo->prepare("INSERT INTO room_amenities(room_id,amenity_id) VALUES(?,?)");foreach($_POST['amenities']??[] as $aid)$ins->execute([$id,(int)$aid]);
    $imgs=$upload->images($_FILES['gallery_images']??[],'rooms/'.$id);
    if($imgs){$mi=$pdo->prepare("INSERT INTO media(scope,entity_id,type,file_path,caption,alt_text,sort_order,is_cover,created_at) VALUES('room',?,'image',?,?,?,0,0,NOW())");foreach($imgs as $img)$mi->execute([$id,$img,$_POST['gallery_caption']??'',$_POST['gallery_alt']??'']);}
    Audit::log('room.save','room',$id);U::flash('ok',$edit?'Unidade atualizada com sucesso.':'Unidade adicionada com sucesso.');U::redirect('index.php?p=rooms&view=edit&id='.$id);
  }
  $rooms=$pdo->query("SELECT * FROM rooms WHERE code NOT LIKE '__deleted__%' ORDER BY active DESC,name")->fetchAll();$ams=$pdo->query("SELECT * FROM amenities WHERE scope='room' AND active=1 ORDER BY category,name")->fetchAll();$selected=[];
  if($edit){$q=$pdo->prepare("SELECT amenity_id FROM room_amenities WHERE room_id=?");$q->execute([$edit['id']]);$selected=$q->fetchAll(PDO::FETCH_COLUMN);}
  $x=$edit?:['id'=>'','code'=>'','name'=>'','type'=>'','short_description'=>'','description'=>'','status'=>'inactive','capacity'=>'','adults'=>'','children'=>0,'beds_json'=>'{}','bathroom_type'=>'','floor_name'=>'','internal_location'=>'','balcony'=>0,'view_name'=>'','area_m2'=>'','video_url'=>'','cover_image'=>'','video_path'=>'','rules'=>'','base_price'=>'','extra_guest_fee'=>0,'min_nights'=>'','max_nights'=>'','cleaning_fee'=>0,'active'=>0];
  $beds=json_decode($x['beds_json']?:'{}',true)?:[];$media=[];
  if($edit){$q=$pdo->prepare("SELECT * FROM media WHERE scope='room' AND entity_id=? ORDER BY id DESC");$q->execute([$edit['id']]);$media=$q->fetchAll();}
  if($view==='list'){
    $todayRates=[];
    $q=$pdo->query("SELECT room_id,name,price FROM tariffs WHERE active=1 AND start_date<=CURDATE() AND end_date>=DATE_ADD(CURDATE(),INTERVAL 1 DAY) ORDER BY priority DESC,id DESC");
    foreach($q->fetchAll() as $rate){$key=(int)$rate['room_id'];if(!isset($todayRates[$key]))$todayRates[$key]=$rate;}
    $galleryCovers=[];
    $q=$pdo->query("SELECT entity_id,file_path,url FROM media WHERE scope='room' AND type='image' ORDER BY is_cover DESC,id ASC");
    foreach($q->fetchAll() as $image){$roomKey=(int)$image['entity_id'];if(!isset($galleryCovers[$roomKey]))$galleryCovers[$roomKey]=(string)($image['file_path']?:$image['url']);}
    $h='<div class="module-actions rooms-list-actions"><a class="btn" href="index.php?p=rooms&view=new">+ Adicionar unidade</a></div>';
    $h.='<div class="card rooms-list-card"><div class="rooms-table-scroll"><table class="rooms-list-table"><thead><tr><th>Foto</th><th>Nome</th><th>Status</th><th>Capacidade</th><th>Diária de hoje</th><th>Diária / Pacote</th><th>Ações</th></tr></thead><tbody>';
    foreach($rooms as $r){
      $rid=(int)$r['id'];
      $editUrl='index.php?p=rooms&view=edit&id='.$rid;
      $photo=trim((string)($r['cover_image']?:($galleryCovers[$rid]??'')));
      $photoHtml=$photo!==''
        ?'<img src="'.U::e(safeUrl($photo)).'" loading="lazy" alt="Foto de '.U::e($r['name']).'">'
        :'<span class="room-photo-empty" aria-label="Sem foto">Sem foto</span>';
      $h.='<tr><td><a class="room-photo-link" href="'.$editUrl.'">'.$photoHtml.'</a></td>'
        .'<td><a class="room-name-link" href="'.$editUrl.'">'.U::e($r['name']).'</a><small>'.U::e($r['code']).'</small></td>'
        .'<td>'.U::e(statusPt($r['status'])).'</td><td>'.(int)$r['capacity'].'</td>'
        .'<td class="room-price-current">'.money((float)($todayRates[$rid]['price']??$r['base_price']))
        .'<small>'.(isset($todayRates[$rid])?'Tarifa: '.U::e($todayRates[$rid]['name']):'Diária-base').'</small></td>'
        .'<td><div class="room-price-actions"><a class="btn secondary small" href="'.$editUrl.'&section=diaria#room-pricing">Diária</a><a class="btn secondary small" href="'.$editUrl.'&section=pacote#room-pricing">Pacote</a></div></td>'
        .'<td><div class="table-actions"><a class="btn secondary small" href="'.$editUrl.'">Editar</a>'
        .'<form method="post" class="inline-form" onsubmit="return confirm(\'Excluir esta unidade? Reservas históricas serão preservadas.\')">'
        .Csrf::field().'<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.$rid.'">'
        .'<button class="danger small" type="submit">Excluir</button></form></div></td></tr>';
    }
    $h.='</tbody></table></div></div>';
    render('Quartos / Unidades',$h);exit;
  }
  $h='<div class="room-actions-toolbar"><a class="btn room-toolbar-back" href="index.php?p=rooms">← Voltar às unidades</a>';
  if($edit){
    $rid=(int)$edit['id'];
    $section=(string)($_GET['section']??'');
    $h.='<button type="button" class="btn" data-room-open="roomEditDialog">Editar unidade</button>'
      .'<button type="button" class="btn" data-room-open="roomPhotosDialog">Fotos da unidade</button>'
      .'<a class="btn room-section-button'.($section==='diaria'?' is-active':'').'" href="index.php?p=rooms&view=edit&id='.$rid.'&section=diaria#room-pricing">Diária</a>'
      .'<a class="btn room-section-button'.($section==='pacote'?' is-active':'').'" href="index.php?p=rooms&view=edit&id='.$rid.'&section=pacote#room-pricing">Pacote</a>'
      .'<form method="post" class="room-toolbar-delete" onsubmit="return confirm(\'Excluir esta unidade? Reservas históricas serão preservadas.\')">'
      .Csrf::field().'<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.$rid.'">'
      .'<button type="submit" class="btn room-delete-button">Excluir unidade</button></form>';
  }
  $h.='</div>';
  if($edit){
    $h.='<dialog id="roomEditDialog" class="room-admin-dialog" aria-labelledby="roomEditTitle"><div class="room-dialog-head"><h2 id="roomEditTitle">Editar unidade · '.U::e($edit['name']).'</h2><button type="button" data-room-close aria-label="Fechar">×</button></div>';
  }
  $h.='<div class=stack-layout><form method=post enctype="multipart/form-data" class="card room-edit-form">'.Csrf::field().'<input type=hidden name=action value=save><input type=hidden name=id value="'.U::e($x['id']).'">'.($edit?'':'<h2>Nova unidade</h2>').'<div class=grid2>'.row('Código interno','code',$x['code']).row('Nome público','name',$x['name']).'<label>Tipo de quarto<select name=type><option value="">Selecione</option>';foreach(['Suíte','Apartamento','Quarto duplo','Quarto triplo','Quarto família','Chalé','Cabana','Dormitório'] as $op)$h.='<option '.($x['type']===$op?'selected':'').'>'.U::e($op).'</option>';$h.='</select></label><fieldset class="room-status-switch"><legend>Status operacional</legend><div class="room-status-options"><label><input type="radio" name="status" value="active" '.($x['status']==='active'?'checked':'').'><span>Ligado</span></label><label><input type="radio" name="status" value="inactive" '.($x['status']==='inactive'?'checked':'').'><span>Desligado</span></label><label><input type="radio" name="status" value="maintenance" '.($x['status']==='maintenance'?'checked':'').'><span>Manutenção</span></label></div></fieldset></div>'.row('Descrição curta','short_description',$x['short_description'],'textarea').row('Descrição completa','description',$x['description'],'textarea').'<div class=grid3>'.row('Capacidade máxima','capacity',$x['capacity'],'number').row('Adultos','adults',$x['adults'],'number').row('Crianças','children',$x['children'],'number')."</div><h3>Camas</h3><div class=grid3>";
  foreach(['casal'=>'Casal','queen'=>'Queen','king'=>'King','solteiro'=>'Solteiro','solteiro_xl'=>'Solteiro XL','beliche'=>'Beliche','sofa_cama'=>'Sofá-cama','dobravel'=>'Dobrável','murphy'=>'Murphy'] as $b=>$lab)$h.=row($lab,"bed_$b",$beds[$b]??0,'number');
  $h.="</div><div class=grid3><label>Banheiro<select name=bathroom_type><option value=\"\">Selecione</option><option ".($x['bathroom_type']==='Privativo'?'selected':'').">Privativo</option><option ".($x['bathroom_type']==='Compartilhado'?'selected':'').">Compartilhado</option><option ".($x['bathroom_type']==='Adaptado'?'selected':'').">Adaptado</option></select></label>".row('Andar','floor_name',$x['floor_name']).row('Localização interna','internal_location',$x['internal_location']).row('Vista','view_name',$x['view_name']).row('Área m²','area_m2',$x['area_m2'],'number')."</div>".row('Varanda','balcony',$x['balcony'],'checkbox').row('Regras específicas','rules',$x['rules'],'textarea');
  if(!$edit){
  $h.='<section class="form-section media-section"><h3>Fotos e vídeo da unidade</h3><p class=muted>Imagens JPG/PNG/WebP; vídeos MP4/WebM. O limite efetivo também depende do PHP do servidor.</p><div class=grid2><label>Foto principal<input type=file name=cover_image accept="image/jpeg,image/png,image/webp"></label><label>Fotos da galeria<input type=file name="gallery_images[]" accept="image/jpeg,image/png,image/webp" multiple></label><label>Upload de vídeo<input type=file name=video_file accept="video/mp4,video/webm"></label>'.row('Ou URL externa de vídeo','video_url',$x['video_url']).row('Legenda das novas fotos','gallery_caption').row('Texto alternativo','gallery_alt').'</div><div class=media-previews>'.mediaPreview($x['cover_image']).mediaPreview($x['video_path'],'video').'</div>';
  if($media){$h.='<div class=gallery>';foreach($media as $m){$src=$m['file_path']?:$m['url'];if(!$src)continue;$h.='<figure><img src="'.U::e(safeUrl($src)).'" alt="'.U::e($m['alt_text']).'"><figcaption>'.U::e($m['caption']).'</figcaption></figure>';}$h.='</div>';}
  $h.='</section>';
  }
  $h.='<h3>Preços</h3><div class=grid3>'.row('Diária-base','base_price',$x['base_price'],'number').row('Hóspede extra','extra_guest_fee',$x['extra_guest_fee'],'number').row('Taxa de limpeza','cleaning_fee',$x['cleaning_fee'],'number').row('Noites mínimas','min_nights',$x['min_nights'],'number').row('Noites máximas','max_nights',$x['max_nights'],'number').'</div><h3>Comodidades</h3><div class=amenities>';
  $cat='';foreach($ams as $a){if($cat!==$a['category']){$cat=$a['category'];$h.='<h4>'.U::e($cat).'</h4>';}$h.='<label class=check><input type=checkbox name="amenities[]" value="'.$a['id'].'" '.(in_array($a['id'],$selected)?'checked':'').'> '.U::e($a['name']).'</label>';}
  $h.='</div>'.row('Publicar esta unidade','active',$x['active'],'checkbox').'<div class="form-actions"><button type="submit">'.($edit?'Salvar alterações':'Adicionar unidade').'</button>'.($edit?'<button type="button" class="btn secondary" data-room-close>Cancelar</button>':'<a class="btn secondary" href="index.php?p=rooms">Cancelar</a>').'</div></form>';
  if($edit){
    $h.='</div></dialog>';
    $h.='<dialog id="roomPhotosDialog" class="room-admin-dialog room-photo-dialog" aria-labelledby="roomPhotosTitle">'
      .'<div class="room-dialog-head"><h2 id="roomPhotosTitle">Fotos e vídeos · '.U::e($edit['name']).'</h2><button type="button" data-room-close aria-label="Fechar">×</button></div>'
      .'<form method="post" enctype="multipart/form-data" class="room-media-form">'
      .Csrf::field().'<input type="hidden" name="action" value="room_media_add"><input type="hidden" name="id" value="'.(int)$edit['id'].'">'
      .'<div class="grid2"><label>Foto principal<input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"></label>'
      .'<label>Adicionar fotos à galeria<input type="file" name="gallery_images[]" accept="image/jpeg,image/png,image/webp" multiple></label>'
      .'<label>Adicionar vídeo<input type="file" name="video_file" accept="video/mp4,video/webm"></label>'
      .row('URL do vídeo (HTTPS)','video_url',$x['video_url'])
      .row('Legenda das novas fotos','gallery_caption')
      .row('Texto alternativo','gallery_alt')
      .'</div><div class="room-dialog-actions"><button type="button" class="btn" data-room-close>Cancelar</button><button type="submit">Salvar mídias</button></div></form>'
      .'<div class="room-current-media"><h3>Fotos cadastradas</h3>';
    if($x['cover_image'])$h.='<figure><img src="'.U::e(safeUrl($x['cover_image'])).'" alt="Capa atual"><figcaption>Capa atual</figcaption></figure>';
    foreach($media as $m){
      $src=safeUrl($m['file_path']?:$m['url']);if(!$src)continue;
      $h.='<figure><img src="'.U::e($src).'" alt="'.U::e($m['alt_text']).'" loading="lazy"><figcaption>'.U::e($m['caption']?:'Foto da unidade').'</figcaption></figure>';
    }
    $h.='</div></dialog>';
  }

  if($edit){
    $rid=(int)$edit['id'];
    $section=(string)($_GET['section']??'');
    $h.='<section class="card rooms-pricing-card" id="room-pricing"><div class="room-pricing-head"><div><h2>Diárias e pacotes</h2><p class="muted">Valores e ofertas desta unidade: '.U::e($edit['name']).'.</p></div><div class="room-pricing-tabs"><a href="index.php?p=rooms&view=edit&id='.$rid.'&section=diaria#room-pricing" class="'.($section==='diaria'?'current':'').'">Diária</a><a href="index.php?p=rooms&view=edit&id='.$rid.'&section=pacote#room-pricing" class="'.($section==='pacote'?'current':'').'">Pacote</a></div></div>';
    if($section==='diaria'){
      $q=$pdo->prepare("SELECT id,name,start_date,end_date,price,min_nights,priority,active FROM tariffs WHERE room_id=? ORDER BY start_date DESC,id DESC");
      $q->execute([$rid]);$list=$q->fetchAll();
      $h.='<h3>Tarifas desta unidade</h3><p class="muted">Cadastre valores por período, incluindo alta e baixa temporada. A diária-base permanece no cadastro acima.</p>';
      $h.='<form method="post" class="rooms-pricing-form">'.Csrf::field().'<input type="hidden" name="action" value="tariff_add"><input type="hidden" name="room_id" value="'.$rid.'"><div class="grid3">'
        .row('Nome da tarifa','name')
        .row('Início','start_date','','date').row('Fim','end_date','','date')
        .'<label>Valor da diária (R$)<input type="number" name="price" min="0" step="0.01" required></label>'.row('Noites mínimas','min_nights',1,'number').row('Prioridade','priority',0,'number')
        .'</div><button type="submit">Adicionar diária</button></form>';
      $h.='<div class="rooms-pricing-table"><table><thead><tr><th>Tarifa</th><th>Período</th><th>Valor</th><th>Noites mín.</th><th>Status</th><th></th></tr></thead><tbody>';
      foreach($list as $rate){
        $h.='<tr><td>'.U::e($rate['name']).'</td><td>'.U::e($rate['start_date']).' – '.U::e($rate['end_date']).'</td><td>'.money((float)$rate['price']).'</td><td>'.(int)$rate['min_nights'].'</td><td>'.((int)$rate['active']?'Ativa':'Inativa').'</td><td><form method="post">'.Csrf::field().'<input type="hidden" name="action" value="tariff_toggle"><input type="hidden" name="room_id" value="'.$rid.'"><input type="hidden" name="record_id" value="'.(int)$rate['id'].'"><button type="submit" class="btn secondary small">'.((int)$rate['active']?'Desativar':'Ativar').'</button></form></td></tr>';
      }
      if(!$list)$h.='<tr><td colspan="6">Nenhuma tarifa por período cadastrada.</td></tr>';
      $h.='</tbody></table></div>';
    }elseif($section==='pacote'){
      $q=$pdo->prepare("SELECT id,name,description,start_date,end_date,nights,price,active FROM room_packages WHERE room_id=? ORDER BY start_date DESC,id DESC");
      $q->execute([$rid]);$list=$q->fetchAll();
      $h.='<h3>Pacotes desta unidade</h3><p class="muted">Cadastre pacotes por período e quantidade de noites. Os pacotes são ofertas para consulta e negociação; o cálculo automático de reservas continua pelas diárias e tarifas.</p>';
      $h.='<form method="post" class="rooms-pricing-form">'.Csrf::field().'<input type="hidden" name="action" value="package_add"><input type="hidden" name="room_id" value="'.$rid.'"><div class="grid3">'
        .row('Nome do pacote','name').row('Início','start_date','','date').row('Fim','end_date','','date')
        .row('Noites do pacote','nights',2,'number').'<label>Preço total do pacote (R$)<input type="number" name="price" min="0" step="0.01" required></label>'
        .'</div>'.row('O que inclui','description','','textarea').'<button type="submit">Adicionar pacote</button></form>';
      $h.='<div class="rooms-pricing-table"><table><thead><tr><th>Pacote</th><th>Período</th><th>Noites</th><th>Preço total</th><th>Status</th><th></th></tr></thead><tbody>';
      foreach($list as $package){
        $h.='<tr><td><b>'.U::e($package['name']).'</b><small>'.U::e($package['description']).'</small></td><td>'.U::e($package['start_date']).' – '.U::e($package['end_date']).'</td><td>'.(int)$package['nights'].'</td><td>'.money((float)$package['price']).'</td><td>'.((int)$package['active']?'Ativo':'Inativo').'</td><td><form method="post">'.Csrf::field().'<input type="hidden" name="action" value="package_toggle"><input type="hidden" name="room_id" value="'.$rid.'"><input type="hidden" name="record_id" value="'.(int)$package['id'].'"><button type="submit" class="btn secondary small">'.((int)$package['active']?'Desativar':'Ativar').'</button></form></td></tr>';
      }
      if(!$list)$h.='<tr><td colspan="6">Nenhum pacote cadastrado para esta unidade.</td></tr>';
      $h.='</tbody></table></div>';
    }else{
      $h.='<p class="muted">Selecione Diária ou Pacote para gerenciar os valores específicos desta unidade.</p>';
    }
    $h.='</section>';
  }
  $h.='</div>';render($edit?'Editar unidade':'Nova unidade',$h);exit;
