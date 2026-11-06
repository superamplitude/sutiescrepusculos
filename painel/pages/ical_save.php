<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requireSuperAdmin();Csrf::check();$room=(int)$_POST['room_id'];$url=trim($_POST['import_url']??'');$active=isset($_POST['active'])?1:0;
  $q=$pdo->prepare("SELECT id FROM rooms WHERE id=?");$q->execute([$room]);if(!$q->fetchColumn())throw new RuntimeException('Quarto inválido.');
  $q=$pdo->prepare("SELECT id FROM ical_feeds WHERE room_id=?");$q->execute([$room]);$id=$q->fetchColumn();
  if($id)$pdo->prepare("UPDATE ical_feeds SET import_url=?,active=?,updated_at=NOW() WHERE id=?")->execute([$url?:null,$active,$id]);
  else{$token=bin2hex(random_bytes(24));$pdo->prepare("INSERT INTO ical_feeds(room_id,export_token,import_url,active,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())")->execute([$room,$token,$url?:null,$active]);}
  Audit::log('ical.save','room',$room);U::flash('ok','Configuração iCal salva.');U::redirect('index.php?p=integrations#ical-rooms');
