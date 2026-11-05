<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Csrf::check();$pdo->beginTransaction();
  try{
    if(empty($_POST['consent']))throw new RuntimeException('É necessário aceitar o consentimento para criar a reserva.');
    $email=trim($_POST['email']);$s=$pdo->prepare("SELECT id FROM guests WHERE email=? ORDER BY id DESC LIMIT 1");$s->execute([$email]);$gid=(int)$s->fetchColumn();
    if($gid){$pdo->prepare("UPDATE guests SET name=?,whatsapp=?,privacy_consent=1,updated_at=NOW() WHERE id=?")->execute([trim($_POST['name']),trim($_POST['whatsapp']),$gid]);}
    else{$s=$pdo->prepare("INSERT INTO guests(name,email,whatsapp,privacy_consent,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())");$s->execute([trim($_POST['name']),$email,trim($_POST['whatsapp']),1]);$gid=(int)$pdo->lastInsertId();}
    $pdo->commit();
    $svc=new ReservationService();$id=$svc->create(['guest_id'=>$gid,'room_id'=>(int)$_POST['room_id'],'checkin'=>$_POST['checkin'],'checkout'=>$_POST['checkout'],'guests'=>(int)$_POST['guests'],'source'=>'site']);
    $s=$pdo->prepare("SELECT code,total FROM reservations WHERE id=?");$s->execute([$id]);$r=$s->fetch();
    $pdo->prepare("INSERT INTO payments(reservation_id,method,status,amount,provider,created_at,updated_at) VALUES(?,'pix','pending',?,'manual',NOW(),NOW())")->execute([$id,$r['total']]);
    $svc->changeStatus($id,'aguardando_pagamento','cobrança criada');
    render('Pré-reserva criada',"<div class=card><h1>Pré-reserva criada</h1><p>Código: <b>".U::e($r['code'])."</b></p><p>Total: <b>".money((float)$r['total'])."</b></p><p>O PIX automático só será exibido quando um provedor real for configurado. Até lá, a reserva permanece aguardando pagamento.</p><a class=btn href=\"index.php?p=traveler\">Abrir painel do viajante</a></div>");exit;
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();render('Erro',"<div class=\"alert error\">".U::e($e->getMessage())."</div>");exit;}
