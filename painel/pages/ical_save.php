<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requireSuperAdmin();Csrf::check();
  if(($_POST['calendar_mode']??'')==='channel'){
    $room=(int)($_POST['room_id']??0);$feedId=(int)($_POST['channel_feed_id']??0);
    $channel=strtolower(trim((string)($_POST['channel']??'')));
    $url=trim((string)($_POST['import_url']??''));
    $active=isset($_POST['active'])?1:0;
    if(!in_array($channel,['airbnb','booking','expedia','other'],true))throw new RuntimeException('Canal não permitido.');
    if(strlen($url)>1900||!preg_match('~^https://[^/]+/~i',$url))throw new RuntimeException('Informe URL HTTPS do calendário.');
    $host=parse_url($url,PHP_URL_HOST);
    if(!$host||filter_var($host,FILTER_VALIDATE_IP)||strtolower($host)==='localhost'||str_ends_with(strtolower($host),'.local'))throw new RuntimeException('Host do calendário inválido.');
    $q=$pdo->prepare("SELECT id FROM rooms WHERE id=?");$q->execute([$room]);if(!$q->fetchColumn())throw new RuntimeException('Unidade inválida.');
    if($feedId){
      $q=$pdo->prepare("UPDATE suites_ical_channel_feeds SET channel=?,import_url=?,active=?,updated_at=NOW() WHERE id=? AND room_id=?");
      $q->execute([$channel,$url,$active,$feedId,$room]);
      if($q->rowCount()===0){$q=$pdo->prepare("SELECT id FROM suites_ical_channel_feeds WHERE id=? AND room_id=?");$q->execute([$feedId,$room]);if(!$q->fetchColumn())throw new RuntimeException('Canal inexistente.');}
    }else{
      $q=$pdo->prepare("SELECT COUNT(*) FROM suites_ical_channel_feeds WHERE room_id=? AND import_url=?");
      $q->execute([$room,$url]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Calendário já cadastrado nesta unidade.');
      $pdo->prepare("INSERT INTO suites_ical_channel_feeds(room_id,channel,import_url,active,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())")->execute([$room,$channel,$url,$active]);
    }
    Audit::log('ical.channel.save','room',$room,['channel'=>$channel,'feed_id'=>$feedId]);
    U::flash('ok','Canal registrado. Use Sincronizar agora para importar os bloqueios.');
    U::redirect('index.php?p=integrations#ical-channels');
  }
  $room=(int)$_POST['room_id'];$url=trim($_POST['import_url']??'');$active=isset($_POST['active'])?1:0;
  $q=$pdo->prepare("SELECT id FROM rooms WHERE id=?");$q->execute([$room]);if(!$q->fetchColumn())throw new RuntimeException('Quarto inválido.');
  $q=$pdo->prepare("SELECT id FROM ical_feeds WHERE room_id=?");$q->execute([$room]);$id=$q->fetchColumn();
  if($id)$pdo->prepare("UPDATE ical_feeds SET import_url=?,active=?,updated_at=NOW() WHERE id=?")->execute([$url?:null,$active,$id]);
  else{$token=bin2hex(random_bytes(24));$pdo->prepare("INSERT INTO ical_feeds(room_id,export_token,import_url,active,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())")->execute([$room,$token,$url?:null,$active]);}
  Audit::log('ical.save','room',$room);U::flash('ok','Configuração iCal salva.');U::redirect('index.php?p=integrations#ical-rooms');
