<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('messages.manage');
  $cid=(int)($_GET['id']??0);
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$cid=(int)($_POST['conversation_id']??0);$action=(string)($_POST['action']??'reply');
    if($action==='set_mode'){
      $mode=in_array($_POST['mode']??'hybrid',['human','hybrid','ai'],true)?$_POST['mode']:'hybrid';
      $pdo->prepare("UPDATE conversations SET mode=?,assigned_user_id=?,updated_at=NOW() WHERE id=?")->execute([$mode,Auth::user()['id'],$cid]);
      Audit::log('conversation.mode','conversation',$cid,['mode'=>$mode]);U::flash('ok','Modo do atendimento atualizado.');U::redirect('index.php?p=messages&id='.$cid);
    }
    $body=trim((string)($_POST['body']??''));
    if($body!==''){
      $pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'staff',?,NOW())")->execute([$cid,$body]);try{(new \App\Services\CrmSyncService(currentCompanyId()))->syncConversation($cid);}catch(Throwable $crmError){}
      // Ao responder manualmente, o humano assume a conversa e a IA pausa automaticamente.
      $pdo->prepare("UPDATE conversations SET assigned_user_id=?,mode='human',updated_at=NOW() WHERE id=?")->execute([Auth::user()['id'],$cid]);
      Audit::log('message.reply','conversation',$cid,['ai_paused'=>true]);U::redirect('index.php?p=messages&id='.$cid);
    }
  }
  $xs=$pdo->query("SELECT c.*,g.name guest,g.email guest_email,g.whatsapp guest_whatsapp,r.code FROM conversations c LEFT JOIN guests g ON g.id=c.guest_id LEFT JOIN reservations r ON r.id=c.reservation_id ORDER BY c.status='open' DESC,c.updated_at DESC")->fetchAll();
  $h=adminTitle('Atendimento','Conversas do site, hóspedes e reservas em uma única fila.').'<div class=split><div class=card><table><tr><th>Cliente</th><th>Contato</th><th>Reserva</th><th>Canal</th><th>Modo</th><th>Status</th><th></th></tr>';
  foreach($xs as $x){
    $contact=trim(($x['guest_whatsapp']??'').' '.($x['guest_email']??''));
    $modeLabel=['human'=>'Humano','hybrid'=>'Híbrido','ai'=>'IA'][$x['mode']??'hybrid']??'Híbrido';
    $h.='<tr><td>'.U::e($x['guest']?:'Visitante').'</td><td>'.U::e($contact?:'—').'</td><td>'.U::e($x['code']?:'—').'</td><td>'.U::e($x['channel']).'</td><td><span class="status-badge">'.U::e($modeLabel).'</span></td><td>'.U::e($x['status']==='open'?'Aberto':'Encerrado').'</td><td><a href="index.php?p=messages&id='.$x['id'].'">Abrir</a></td></tr>';
  }
  $h.='</table></div>';
  if($cid){
    $q=$pdo->prepare("SELECT m.*,c.mode,c.status,g.name guest,g.email guest_email,g.whatsapp guest_whatsapp,r.code FROM messages m JOIN conversations c ON c.id=m.conversation_id LEFT JOIN guests g ON g.id=c.guest_id LEFT JOIN reservations r ON r.id=c.reservation_id WHERE m.conversation_id=? ORDER BY m.id");
    $q->execute([$cid]);$msgs=$q->fetchAll();
    $h.='<div class=card><h2>Conversa</h2>';
    if($msgs){
      $first=$msgs[0];$currentMode=(string)($first['mode']??'hybrid');
      $h.='<p class=muted>'.U::e($first['guest']?:'Visitante').' · '.U::e(trim(($first['guest_whatsapp']??'').' '.($first['guest_email']??''))).'</p>';
      $h.='<form method=post class="conversation-mode-bar">'.Csrf::field().'<input type=hidden name=action value=set_mode><input type=hidden name=conversation_id value="'.$cid.'"><span>Controle:</span><button name=mode value=human class="'.($currentMode==='human'?'active':'secondary').'">Humano</button><button name=mode value=hybrid class="'.($currentMode==='hybrid'?'active':'secondary').'">Híbrido</button><button name=mode value=ai class="'.($currentMode==='ai'?'active':'secondary').'">IA</button><small>Ao responder manualmente, a IA pausa. Use Híbrido ou IA para devolver o atendimento ao agente.</small></form>';
    }
    $h.='<div class=conversation>';
    foreach($msgs as $m){
      $label=$m['sender_type']==='guest'?'Cliente':($m['sender_type']==='ai'?(trim((string)Site::get('chat_agent_name','Assistente IA'))?:'Assistente IA'):'Equipe');
      $h.='<div class="bubble '.U::e($m['sender_type']).'"><small>'.U::e($label).'</small><p>'.nl2br(U::e($m['body'])).'</p><time>'.U::e($m['created_at']).'</time></div>';
    }
    $h.='</div><form method=post>'.Csrf::field().'<input type=hidden name=action value=reply><input type=hidden name=conversation_id value="'.$cid.'">'.row('Resposta','body','', 'textarea').'<button>Responder e assumir atendimento</button></form></div>';
  }
  render('Atendimento',$h.'</div>');exit;
