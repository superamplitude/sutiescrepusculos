<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $h="<h1>Painel do Viajante</h1>";
  if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'login')==='login'){
    Csrf::check();$now=time();$attempts=$_SESSION['_traveler_attempts']??['count'=>0,'until'=>0];
    if(($attempts['until']??0)>$now){U::flash('err','Muitas tentativas. Aguarde um minuto.');}
    else{
      $q=$pdo->prepare("SELECT r.id reservation_id,r.guest_id,g.email FROM reservations r JOIN guests g ON g.id=r.guest_id WHERE r.code=? AND g.email=? LIMIT 1");$q->execute([trim($_POST['code']),trim($_POST['email'])]);$auth=$q->fetch();
      if($auth){unset($_SESSION['_traveler_attempts']);$_SESSION['traveler']=['reservation_id'=>(int)$auth['reservation_id'],'guest_id'=>(int)$auth['guest_id'],'email'=>$auth['email']];U::redirect('index.php?p=traveler');}
      $count=(int)($attempts['count']??0)+1;$_SESSION['_traveler_attempts']=['count'=>$count,'until'=>$count>=5?$now+60:0];U::flash('err','Reserva não localizada.');
    }
  }
  $t=$_SESSION['traveler']??null;
  if(!$t){
    $h.="<form method=post class=\"card narrow\">".Csrf::field()."<input type=hidden name=action value=login>".row('E-mail da reserva','email','', 'email').row('Código da reserva','code')."<button>Acessar</button></form>";render('Painel do Viajante',$h);exit;
  }
  if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='message'){
    Csrf::check();$body=trim($_POST['body']??'');if($body!==''){
      $q=$pdo->prepare("SELECT id FROM conversations WHERE reservation_id=? AND guest_id=? AND status='open' ORDER BY id DESC LIMIT 1");$q->execute([$t['reservation_id'],$t['guest_id']]);$cid=$q->fetchColumn();
      if(!$cid){$pdo->prepare("INSERT INTO conversations(guest_id,reservation_id,channel,mode,status,created_at,updated_at) VALUES(?,?,'site','hybrid','open',NOW(),NOW())")->execute([$t['guest_id'],$t['reservation_id']]);$cid=(int)$pdo->lastInsertId();}
      $pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'guest',?,NOW())")->execute([$cid,$body]);try{(new \App\Services\CrmSyncService(currentCompanyId()))->syncConversation($cid);}catch(Throwable $crmError){}try{(new \App\Services\AiConversationService())->respondIfEnabled($cid);}catch(Throwable $aiError){@file_put_contents(dirname(__DIR__).'/storage/logs/app.log',date('c').' AI_CONVERSATION '.$aiError->getMessage().PHP_EOL,FILE_APPEND);}U::flash('ok','Mensagem enviada.');U::redirect('index.php?p=traveler');
    }
  }
  if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='profile'){
    Csrf::check();$pdo->prepare("UPDATE guests SET name=?,phone=?,whatsapp=?,updated_at=NOW() WHERE id=?")->execute([trim($_POST['name']),trim($_POST['phone']),trim($_POST['whatsapp']),$t['guest_id']]);U::flash('ok','Dados de contato atualizados.');U::redirect('index.php?p=traveler');
  }
  if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cancel_request'){
    Csrf::check();$body='Solicitação de cancelamento da reserva enviada pelo viajante.';
    $q=$pdo->prepare("SELECT id FROM conversations WHERE reservation_id=? AND guest_id=? AND status='open' ORDER BY id DESC LIMIT 1");$q->execute([$t['reservation_id'],$t['guest_id']]);$cid=$q->fetchColumn();
    if(!$cid){$pdo->prepare("INSERT INTO conversations(guest_id,reservation_id,channel,mode,status,priority,created_at,updated_at) VALUES(?,?,'site','hybrid','open','high',NOW(),NOW())")->execute([$t['guest_id'],$t['reservation_id']]);$cid=(int)$pdo->lastInsertId();}
    $pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'guest',?,NOW())")->execute([$cid,$body]);try{(new \App\Services\CrmSyncService(currentCompanyId()))->syncConversation($cid);}catch(Throwable $crmError){}try{(new \App\Services\AiConversationService())->respondIfEnabled($cid);}catch(Throwable $aiError){@file_put_contents(dirname(__DIR__).'/storage/logs/app.log',date('c').' AI_CONVERSATION '.$aiError->getMessage().PHP_EOL,FILE_APPEND);}U::flash('ok','Solicitação de cancelamento enviada à pousada.');U::redirect('index.php?p=traveler');
  }
  if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='favorite'){
    Csrf::check();$roomId=(int)$_POST['room_id'];$q=$pdo->prepare("SELECT id FROM favorites WHERE guest_id=? AND room_id=?");$q->execute([$t['guest_id'],$roomId]);
    if($q->fetchColumn())$pdo->prepare("DELETE FROM favorites WHERE guest_id=? AND room_id=?")->execute([$t['guest_id'],$roomId]);
    else $pdo->prepare("INSERT INTO favorites(guest_id,room_id,created_at) VALUES(?,?,NOW())")->execute([$t['guest_id'],$roomId]);
    U::redirect('index.php?p=traveler');
  }
  if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='review'){
    Csrf::check();$q=$pdo->prepare("SELECT status FROM reservations WHERE id=? AND guest_id=?");$q->execute([$t['reservation_id'],$t['guest_id']]);$st=$q->fetchColumn();
    if($st!=='concluida'){U::flash('err','A avaliação é liberada depois da hospedagem ser concluída.');U::redirect('index.php?p=traveler');}
    $rating=max(1,min(5,(int)$_POST['rating']));$pdo->prepare("INSERT INTO reviews(reservation_id,guest_id,rating,comment,status,created_at) VALUES(?,?,?,?, 'pending',NOW()) ON DUPLICATE KEY UPDATE rating=VALUES(rating),comment=VALUES(comment),status='pending'")->execute([$t['reservation_id'],$t['guest_id'],$rating,trim($_POST['comment'])]);U::flash('ok','Avaliação enviada para moderação.');U::redirect('index.php?p=traveler');
  }
  $q=$pdo->prepare("SELECT r.*,g.name guest_name,g.email,g.whatsapp,rm.name room_name,p.id payment_id,p.status payment_status,p.method,p.amount,pc.qr_code_text,pc.qr_code_image,v.code voucher_code FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id LEFT JOIN payments p ON p.reservation_id=r.id LEFT JOIN pix_charges pc ON pc.payment_id=p.id LEFT JOIN vouchers v ON v.reservation_id=r.id WHERE r.id=? AND r.guest_id=? ORDER BY p.id DESC LIMIT 1");$q->execute([$t['reservation_id'],$t['guest_id']]);$r=$q->fetch();
  if(!$r){unset($_SESSION['traveler']);U::flash('err','Reserva não está mais disponível.');U::redirect('index.php?p=traveler');}
  $prop=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  $allq=$pdo->prepare("SELECT r.id,r.code,r.checkin,r.checkout,r.status,rm.name room_name FROM reservations r JOIN rooms rm ON rm.id=r.room_id WHERE r.guest_id=? ORDER BY r.checkin DESC,r.id DESC");$allq->execute([$t['guest_id']]);$allReservations=$allq->fetchAll();
  $h.='<div class="traveler-head"><div><h2>'.U::e($r['room_name']).'</h2><p>Reserva '.U::e($r['code']).'</p></div><div><a href="index.php?p=faq">Ajuda</a> · <a href="index.php?p=traveler_logout">Sair</a></div></div>';
  $h.='<section class=card><h3>Minhas reservas</h3><div class=reservation-tabs>';foreach($allReservations as $ar)$h.='<a class="'.((int)$ar['id']===(int)$r['id']?'current':'').'" href="index.php?p=traveler_switch&id='.$ar['id'].'">'.U::e($ar['code']).' · '.U::e($ar['room_name']).' · '.U::e(statusPt($ar['status'])).'</a>';$h.='</div></section><div class=stats><div class=stat><span>Status</span><b>'.U::e(statusPt($r['status'])).'</b></div><div class=stat><span>Pagamento</span><b>'.U::e(statusPt($r['payment_status']?:'pending')).'</b></div><div class=stat><span>Total</span><b>'.money((float)$r['total']).'</b></div></div>';
  $h.='<div class=cards><section class=card><h3>Reserva</h3><p><b>Entrada:</b> '.U::e($r['checkin']).'</p><p><b>Saída:</b> '.U::e($r['checkout']).'</p><p><b>Hóspedes:</b> '.U::e($r['guests']).'</p><p><b>Check-in:</b> '.U::e($prop['checkin']).'</p><p><b>Check-out:</b> '.U::e($prop['checkout']).'</p>';if(in_array($r['status'],['pre_reserva','aguardando_aprovacao','aguardando_pagamento','paga','confirmada'],true))$h.='<form method=post onsubmit="return confirm(\'Enviar solicitação de cancelamento?\')">'.Csrf::field().'<input type=hidden name=action value=cancel_request><button class="secondary">Solicitar cancelamento</button></form>';$h.='</section>';
  $h.='<section class=card><h3>PIX / Pagamento</h3>';if($r['payment_status']==='pending'&&$r['qr_code_text'])$h.='<p>PIX copia e cola:</p><textarea readonly>'.U::e($r['qr_code_text']).'</textarea>';else $h.='<p>'.($r['payment_status']==='paid'?'Pagamento confirmado.':'A cobrança PIX automática aparecerá aqui quando o provedor estiver configurado.').'</p>';$h.='</section>';
  $h.='<section class=card><h3>Voucher</h3><p>'.U::e($r['voucher_code']?:'Disponível após confirmação da reserva.').'</p>'.($r['voucher_code']?'<a href="index.php?p=voucher">Abrir voucher</a>':'').'</section>';
  $h.='<section class=card><h3>Localização</h3><p>'.U::e(trim(($prop['address']??'').' '.($prop['address_number']??'').' - '.($prop['city']??'').'/'.($prop['state']??''))).'</p>'.($prop['map_url']?'<a target=_blank rel=noopener href="'.U::e(safeUrl($prop['map_url'])).'">Abrir rota</a>':'').'</section></div>';
  $fq=$pdo->prepare("SELECT f.room_id,rm.name,rm.cover_image,rm.base_price FROM favorites f JOIN rooms rm ON rm.id=f.room_id WHERE f.guest_id=? ORDER BY f.id DESC");$fq->execute([$t['guest_id']]);$favs=$fq->fetchAll();
  $isFav=false;foreach($favs as $fv)if((int)$fv['room_id']===(int)$r['room_id'])$isFav=true;
  $gq=$pdo->prepare("SELECT name,phone,whatsapp,email FROM guests WHERE id=?");$gq->execute([$t['guest_id']]);$gp=$gq->fetch();
  $h.='<section class=card><h3>Meu perfil</h3><form method=post>'.Csrf::field().'<input type=hidden name=action value=profile><div class=grid3>'.row('Nome','name',$gp['name']).row('Telefone','phone',$gp['phone']).row('WhatsApp','whatsapp',$gp['whatsapp']).'</div><p class=muted>E-mail da reserva: '.U::e($gp['email']).'</p><button>Atualizar contato</button></form></section>';
  $h.='<section class=card><h3>Favoritos</h3><form method=post>'.Csrf::field().'<input type=hidden name=action value=favorite><input type=hidden name=room_id value="'.$r['room_id'].'"><button class=secondary>'.($isFav?'Remover esta acomodação dos favoritos':'Salvar esta acomodação nos favoritos').'</button></form>';if($favs){$h.='<div class=chips>';foreach($favs as $fv)$h.='<span>'.U::e($fv['name']).'</span>';$h.='</div>';}$h.='</section>';
  $mq=$pdo->prepare("SELECT m.* FROM messages m JOIN conversations c ON c.id=m.conversation_id WHERE c.reservation_id=? AND c.guest_id=? ORDER BY m.id");$mq->execute([$t['reservation_id'],$t['guest_id']]);$travMsgs=$mq->fetchAll();
  $h.='<section class=card><h3>Mensagens</h3><div class=conversation>';if(!$travMsgs)$h.='<p class=muted>Nenhuma mensagem ainda.</p>';foreach($travMsgs as $m)$h.='<div class="bubble '.U::e($m['sender_type']).'"><small>'.U::e($m['sender_type']==='guest'?'Você':'Pousada').'</small><p>'.nl2br(U::e($m['body'])).'</p><time>'.U::e($m['created_at']).'</time></div>';$h.='</div><form method=post>'.Csrf::field().'<input type=hidden name=action value=message>'.row('Nova mensagem','body','', 'textarea').'<button>Enviar mensagem</button></form></section>';
  if($r['status']==='concluida')$h.='<form method=post class=card>'.Csrf::field().'<input type=hidden name=action value=review><label>Nota<select name=rating><option value=5>5 - Excelente</option><option value=4>4 - Muito bom</option><option value=3>3 - Bom</option><option value=2>2 - Regular</option><option value=1>1 - Ruim</option></select></label>'.row('Comentário','comment','', 'textarea').'<button>Enviar avaliação</button></form>';
  render('Painel do Viajante',$h);exit;
