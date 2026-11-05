<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $service=new ReservationService();
  $selectedRoom=(int)($_GET['room']??0);$selected=isset($_GET['selected']);
  $prefIn=$_GET['checkin']??'';$prefOut=$_GET['checkout']??'';$prefAdults=max(1,(int)($_GET['adults']??2));$prefChildren=max(0,(int)($_GET['children']??0));
  $h="<h1>Consultar disponibilidade</h1>";
  if($selected && $selectedRoom && $prefIn && $prefOut){
    try{
      $guests=$prefAdults+$prefChildren;
      if(!$service->available($selectedRoom,$prefIn,$prefOut))throw new RuntimeException('Essa acomodação não está mais disponível no período.');
      $total=$service->price($selectedRoom,$prefIn,$prefOut,$guests);
      $q=$pdo->prepare("SELECT name FROM rooms WHERE id=? AND active=1");$q->execute([$selectedRoom]);$roomName=$q->fetchColumn();if(!$roomName)throw new RuntimeException('Acomodação não encontrada.');
      $h.='<div class="card selected-room"><h2>'.U::e($roomName).'</h2><p>'.U::e($prefIn).' a '.U::e($prefOut).' · '.$prefAdults.' adulto(s) · '.$prefChildren.' criança(s)</p><p><b>Total estimado: '.money($total).'</b></p></div>';
      $h.="<form method=post action=\"index.php?p=reserve\" class=card>".Csrf::field()."<input type=hidden name=room_id value=$selectedRoom><input type=hidden name=checkin value=\"".U::e($prefIn)."\"><input type=hidden name=checkout value=\"".U::e($prefOut)."\"><input type=hidden name=guests value=$guests><input type=hidden name=adults value=$prefAdults><input type=hidden name=children value=$prefChildren>".row('Nome','name').row('E-mail','email','', 'email').row('WhatsApp','whatsapp').'<label class=check><input type=checkbox name=consent value=1 required> Concordo com o tratamento dos dados necessários para esta reserva.</label><button>Criar pré-reserva</button></form>';
    }catch(Throwable $e){$h.='<div class="alert error">'.U::e($e->getMessage()).'</div>';}
  }
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();
    try{
      $in=$_POST['checkin'];$out=$_POST['checkout'];$adults=max(1,(int)($_POST['adults']??1));$children=max(0,(int)($_POST['children']??0));$guests=$adults+$children;$type=trim($_POST['room_type']??'');$maxPrice=(float)($_POST['max_price']??0);
      if(!$in||!$out||$out<=$in)throw new RuntimeException('Informe datas válidas.');
      $sql="SELECT * FROM rooms WHERE active=1 AND status='active' AND capacity>=?";$args=[$guests];if($type!==''){$sql.=" AND type=?";$args[]=$type;}if($maxPrice>0){$sql.=" AND base_price<=?";$args[]=$maxPrice;}$sql.=" ORDER BY base_price,name";
      $q=$pdo->prepare($sql);$q->execute($args);$candidates=$q->fetchAll();$available=[];
      foreach($candidates as $r)if($service->available((int)$r['id'],$in,$out)){$r['_total']=$service->price((int)$r['id'],$in,$out,$guests);$available[]=$r;}
      if(!$available)$h.='<div class="alert error">Não há acomodação disponível para os filtros informados.</div>';
      else{$h.='<h2>Acomodações disponíveis</h2><div class=cards>';foreach($available as $r){$img=$r['cover_image']?'<img class=card-cover src="'.U::e(safeUrl($r['cover_image'])).'" alt="'.U::e($r['name']).'">':'';$url='index.php?p=availability&selected=1&room='.$r['id'].'&checkin='.rawurlencode($in).'&checkout='.rawurlencode($out).'&adults='.$adults.'&children='.$children;$h.='<article class="card room-card">'.$img.'<h3>'.U::e($r['name']).'</h3><p>'.U::e($r['short_description']).'</p><p>Capacidade: '.U::e($r['capacity']).'</p><b>'.money((float)$r['_total']).' no período</b><a class=btn href="'.U::e($url).'">Escolher</a></article>';}$h.='</div>';}
    }catch(Throwable $e){$h.='<div class="alert error">'.U::e($e->getMessage()).'</div>';}
  }
  $types=$pdo->query("SELECT DISTINCT type FROM rooms WHERE active=1 AND status='active' AND type IS NOT NULL AND type<>'' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
  $h.="<form method=post class=card>".Csrf::field().'<div class=grid3>'.row('Entrada','checkin',$prefIn,'date').row('Saída','checkout',$prefOut,'date').row('Adultos','adults',$prefAdults,'number').row('Crianças','children',$prefChildren,'number').'<label>Tipo de acomodação<select name=room_type><option value="">Todos</option>';foreach($types as $t)$h.='<option>'.U::e($t).'</option>';$h.='</select></label>'.row('Diária-base máxima (opcional)','max_price','', 'number')."</div><button>Buscar acomodações</button></form>";
  render('Reservar',$h);exit;
