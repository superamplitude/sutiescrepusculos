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
    $h=adminTitle('Quartos / Unidades','Gerencie as acomodações cadastradas.');
    $h.='<div class="module-actions"><a class="btn" href="index.php?p=rooms&view=new">+ Adicionar unidade</a></div>';
    $h.='<div class=card><h2>Unidades cadastradas</h2><table><tr><th>Código</th><th>Nome</th><th>Status</th><th>Capacidade</th><th>Diária</th><th>Ações</th></tr>';
    foreach($rooms as $r){
      $rid=(int)$r['id'];
      $h.='<tr><td>'.U::e($r['code']).'</td><td>'.U::e($r['name']).'</td><td>'.U::e(statusPt($r['status'])).'</td><td>'.(int)$r['capacity'].'</td><td>'.money((float)$r['base_price']).'</td><td><div class="table-actions"><a class="btn secondary small" href="index.php?p=rooms&view=edit&id='.$rid.'">Editar</a><form method="post" class="inline-form" onsubmit="return confirm(\'Excluir esta unidade? Reservas históricas serão preservadas.\')">'.Csrf::field().'<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.$rid.'"><button class="danger small" type="submit">Excluir</button></form></div></td></tr>';
    }
    $h.='</table></div>';render('Quartos / Unidades',$h);exit;
  }
  $h=adminTitle($edit?'Editar unidade':'Nova unidade',$edit?'Altere os dados desta acomodação.':'Cadastre uma nova acomodação.');
  $h.='<div class="module-actions"><a class="btn secondary" href="index.php?p=rooms">← Voltar para unidades</a></div>';
  $h.='<div class=stack-layout><form method=post enctype="multipart/form-data" class=card>'.Csrf::field().'<input type=hidden name=action value=save><input type=hidden name=id value="'.U::e($x['id']).'"><h2>'.($edit?'Editar unidade':'Nova unidade').'</h2><div class=grid2>'.row('Código interno','code',$x['code']).row('Nome público','name',$x['name']).'<label>Tipo de quarto<select name=type><option value="">Selecione</option>';foreach(['Suíte','Apartamento','Quarto duplo','Quarto triplo','Quarto família','Chalé','Cabana','Dormitório'] as $op)$h.='<option '.($x['type']===$op?'selected':'').'>'.U::e($op).'</option>';$h.='</select></label><label>Status operacional<select name=status><option value=inactive '.($x['status']==='inactive'?'selected':'').'>Inativo</option><option value=active '.($x['status']==='active'?'selected':'').'>Ativo</option><option value=maintenance '.($x['status']==='maintenance'?'selected':'').'>Manutenção</option></select></label></div>'.row('Descrição curta','short_description',$x['short_description'],'textarea').row('Descrição completa','description',$x['description'],'textarea').'<div class=grid3>'.row('Capacidade máxima','capacity',$x['capacity'],'number').row('Adultos','adults',$x['adults'],'number').row('Crianças','children',$x['children'],'number')."</div><h3>Camas</h3><div class=grid3>";
  foreach(['casal'=>'Casal','queen'=>'Queen','king'=>'King','solteiro'=>'Solteiro','solteiro_xl'=>'Solteiro XL','beliche'=>'Beliche','sofa_cama'=>'Sofá-cama','dobravel'=>'Dobrável','murphy'=>'Murphy'] as $b=>$lab)$h.=row($lab,"bed_$b",$beds[$b]??0,'number');
  $h.="</div><div class=grid3><label>Banheiro<select name=bathroom_type><option value=\"\">Selecione</option><option ".($x['bathroom_type']==='Privativo'?'selected':'').">Privativo</option><option ".($x['bathroom_type']==='Compartilhado'?'selected':'').">Compartilhado</option><option ".($x['bathroom_type']==='Adaptado'?'selected':'').">Adaptado</option></select></label>".row('Andar','floor_name',$x['floor_name']).row('Localização interna','internal_location',$x['internal_location']).row('Vista','view_name',$x['view_name']).row('Área m²','area_m2',$x['area_m2'],'number')."</div>".row('Varanda','balcony',$x['balcony'],'checkbox').row('Regras específicas','rules',$x['rules'],'textarea');
  $h.='<section class="form-section media-section"><h3>Fotos e vídeo da unidade</h3><p class=muted>Imagens JPG/PNG/WebP; vídeos MP4/WebM. O limite efetivo também depende do PHP do servidor.</p><div class=grid2><label>Foto principal<input type=file name=cover_image accept="image/jpeg,image/png,image/webp"></label><label>Fotos da galeria<input type=file name="gallery_images[]" accept="image/jpeg,image/png,image/webp" multiple></label><label>Upload de vídeo<input type=file name=video_file accept="video/mp4,video/webm"></label>'.row('Ou URL externa de vídeo','video_url',$x['video_url']).row('Legenda das novas fotos','gallery_caption').row('Texto alternativo','gallery_alt').'</div><div class=media-previews>'.mediaPreview($x['cover_image']).mediaPreview($x['video_path'],'video').'</div>';
  if($media){$h.='<div class=gallery>';foreach($media as $m){$src=$m['file_path']?:$m['url'];if(!$src)continue;$h.='<figure><img src="'.U::e(safeUrl($src)).'" alt="'.U::e($m['alt_text']).'"><figcaption>'.U::e($m['caption']).'</figcaption></figure>';}$h.='</div>';}
  $h.='</section><h3>Preços</h3><div class=grid3>'.row('Diária-base','base_price',$x['base_price'],'number').row('Hóspede extra','extra_guest_fee',$x['extra_guest_fee'],'number').row('Taxa de limpeza','cleaning_fee',$x['cleaning_fee'],'number').row('Noites mínimas','min_nights',$x['min_nights'],'number').row('Noites máximas','max_nights',$x['max_nights'],'number').'</div><h3>Comodidades</h3><div class=amenities>';
  $cat='';foreach($ams as $a){if($cat!==$a['category']){$cat=$a['category'];$h.='<h4>'.U::e($cat).'</h4>';}$h.='<label class=check><input type=checkbox name="amenities[]" value="'.$a['id'].'" '.(in_array($a['id'],$selected)?'checked':'').'> '.U::e($a['name']).'</label>';}
  $h.='</div>'.row('Publicar esta unidade','active',$x['active'],'checkbox').'<div class="form-actions"><button type="submit">'.($edit?'Salvar alterações':'Adicionar unidade').'</button><a class="btn secondary" href="index.php?p=rooms">Cancelar</a></div></form>';
  if($edit)$h.='<form method="post" class="card danger-zone" onsubmit="return confirm(\'Excluir esta unidade? Reservas históricas serão preservadas.\')">'.Csrf::field().'<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.(int)$edit['id'].'"><h3>Excluir unidade</h3><p class="muted">A unidade deixará de aparecer no cadastro e no site. Reservas históricas serão preservadas.</p><button class="danger" type="submit">Excluir unidade</button></form>';
  $h.='</div>';render($edit?'Editar unidade':'Nova unidade',$h);exit;
