<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('content.publish');
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$upload=new UploadService();$photo=trim($_POST['photo']??'');
    if(!empty($_FILES['photo_file']['name']))$photo=$upload->image($_FILES['photo_file'],'attractions');
    $pdo->prepare("INSERT INTO attractions(name,category,distance_text,time_text,address,latitude,longitude,description,photo,url,active) VALUES(?,?,?,?,?,?,?,?,?,?,1)")->execute([$_POST['name'],$_POST['category'],$_POST['distance_text'],$_POST['time_text'],$_POST['address'],$_POST['latitude']?:null,$_POST['longitude']?:null,$_POST['description'],$photo,$_POST['url']]);Audit::log('attraction.create','attraction',(int)$pdo->lastInsertId());U::redirect('index.php?p=attractions');
  }
  $xs=$pdo->query("SELECT * FROM attractions ORDER BY id DESC")->fetchAll();$h=adminTitle('Atrações e proximidades','Informações turísticas aprovadas.').'<form method=post enctype="multipart/form-data" class=card>'.Csrf::field().'<div class=grid3>'.row('Nome','name').row('Categoria','category').row('Distância','distance_text').row('Tempo estimado','time_text').row('Endereço','address').row('Latitude','latitude').row('Longitude','longitude').'<label>Upload da foto<input type=file name=photo_file accept="image/jpeg,image/png,image/webp"></label>'.row('Ou URL da foto','photo').row('Link/rota','url').'</div>'.row('Descrição','description','', 'textarea').'<button>Adicionar</button></form><div class=cards>';foreach($xs as $x)$h.='<div class=card>'.($x['photo']?'<img class=card-cover src="'.U::e(safeUrl($x['photo'])).'" alt="'.U::e($x['name']).'">':'').'<h3>'.U::e($x['name']).'</h3><small>'.U::e($x['category']).' · '.U::e($x['distance_text']).'</small><p>'.U::e($x['description']).'</p></div>';render('Atrações',$h.'</div>');exit;
