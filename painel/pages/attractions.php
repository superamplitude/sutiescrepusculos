<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('content.publish');
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$upload=new UploadService();$photo=trim($_POST['photo']??'');
    if(!empty($_FILES['photo_file']['name']))$photo=$upload->image($_FILES['photo_file'],'attractions');
    $pdo->prepare("INSERT INTO attractions(name,category,distance_text,time_text,address,latitude,longitude,description,photo,url,active) VALUES(?,?,?,?,?,?,?,?,?,?,1)")->execute([$_POST['name'],$_POST['category'],$_POST['distance_text'],$_POST['time_text'],$_POST['address'],$_POST['latitude']?:null,$_POST['longitude']?:null,$_POST['description'],$photo,$_POST['url']]);Audit::log('attraction.create','attraction',(int)$pdo->lastInsertId());U::redirect('index.php?p=attractions');
  }
  $xs=$pdo->query("SELECT * FROM attractions ORDER BY id DESC")->fetchAll();
  $h=adminTitle('Atrações e proximidades','Informações turísticas aprovadas.');
  $h.='<div class="attractions-toolbar"><button type="button" class="btn" data-attraction-open aria-haspopup="dialog" aria-controls="attractionAddDialog">+ Adicionar atração</button></div>';
  $h.='<dialog id="attractionAddDialog" class="attractions-dialog" aria-labelledby="attractionAddTitle">';
  $h.='<div class="attractions-dialog-head"><h2 id="attractionAddTitle">Adicionar atração</h2><button type="button" class="secondary" data-attraction-close aria-label="Fechar formulário">×</button></div>';
  $h.='<form method=post enctype="multipart/form-data" class="attractions-dialog-form">'.Csrf::field();
  $h.='<div class=grid3>'.row('Nome','name').row('Categoria','category').row('Distância','distance_text').row('Tempo estimado','time_text').row('Endereço','address').row('Latitude','latitude').row('Longitude','longitude').'<label>Upload da foto<input type=file name=photo_file accept="image/jpeg,image/png,image/webp"></label>'.row('Ou URL da foto','photo').row('Link/rota','url').'</div>'.row('Descrição','description','', 'textarea');
  $h.='<div class="attractions-dialog-actions"><button type="button" class="secondary" data-attraction-close>Cancelar</button><button type="submit">Salvar atração</button></div></form></dialog>';
  $h.='<div class=cards>';
  foreach($xs as $x)$h.='<div class=card>'.($x['photo']?'<img class=card-cover src="'.U::e(safeUrl($x['photo'])).'" alt="'.U::e($x['name']).'">':'').'<h3>'.U::e($x['name']).'</h3><small>'.U::e($x['category']).' · '.U::e($x['distance_text']).'</small><p>'.U::e($x['description']).'</p></div>';
  $h.='</div>';
  $h.='<style>.attractions-toolbar{display:flex;justify-content:flex-end;margin:0 0 16px}.attractions-dialog{width:min(900px,calc(100vw - 32px));max-height:min(90dvh,900px);padding:0;border:1px solid #8ea69c;border-radius:14px;box-shadow:0 22px 65px #0005;background:#fff;color:#152b23}.attractions-dialog::backdrop{background:rgba(13,28,23,.55)}.attractions-dialog-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 22px;border-bottom:1px solid #dce7e1}.attractions-dialog-head h2{font-size:19px;margin:0}.attractions-dialog-form{padding:20px 22px;overflow-y:auto;max-height:calc(90dvh - 76px)}.attractions-dialog-form .grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.attractions-dialog-actions{position:sticky;bottom:-20px;display:flex;justify-content:flex-end;gap:10px;background:white;padding:15px 0 0;margin-top:16px;border-top:1px solid #e1e8e4}@media(max-width:700px){.attractions-dialog-form .grid3{grid-template-columns:1fr}.attractions-dialog-head,.attractions-dialog-form{padding:15px}}</style>';
  $h.='<script>(function(){const dialog=document.getElementById("attractionAddDialog");if(!dialog)return;document.querySelectorAll("[data-attraction-open]").forEach(b=>b.addEventListener("click",()=>{if(!dialog.open)dialog.showModal();dialog.querySelector("input[name=name]")?.focus();}));dialog.querySelectorAll("[data-attraction-close]").forEach(b=>b.addEventListener("click",()=>dialog.close()));dialog.addEventListener("click",e=>{if(e.target===dialog)dialog.close();});})();</script>';
  render('Atrações',$h);exit;
