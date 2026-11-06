<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('messages.manage');
  $cid=(int)($_GET['id']??0);
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$cid=(int)($_POST['conversation_id']??0);$action=(string)($_POST['action']??'reply');
    if($action==='set_mode'){
      $mode=in_array($_POST['mode']??'hybrid',['human','hybrid','ai'],true)?$_POST['mode']:'hybrid';
      $pdo->prepare("UPDATE conversations SET mode=?,assigned_user_id=?,updated_at=NOW() WHERE id=?")->execute([$mode,$mode==='human'?Auth::user()['id']:null,$cid]);
      Audit::log('conversation.mode','conversation',$cid,['mode'=>$mode]);U::flash('ok','Modo do atendimento atualizado.');U::redirect('index.php?p=messages&id='.$cid);
    }
    $body=trim((string)($_POST['body']??''));
    if($body!==''){
      $cq=$pdo->prepare("SELECT channel,guest_id FROM conversations WHERE id=?");$cq->execute([$cid]);$conversation=$cq->fetch();
      if(($conversation['channel']??'')==='whatsapp'){
        $jq=$pdo->prepare("SELECT jid FROM suites_whatsapp_contacts WHERE guest_id=? LIMIT 1");$jq->execute([$conversation['guest_id']]);$jid=(string)$jq->fetchColumn();
        try{(new \App\Services\SuitesMessagesService())->sendText($jid,$body);}
        catch(Throwable){U::flash('err','A mensagem não foi enviada ao WhatsApp. Confira a conexão e tente novamente.');U::redirect('index.php?p=messages&id='.$cid);}
      }
      $pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'staff',?,NOW())")->execute([$cid,$body]);try{(new \App\Services\CrmSyncService(currentCompanyId()))->syncConversation($cid);}catch(Throwable $crmError){}
      // Ao responder manualmente, o humano assume a conversa e a IA pausa automaticamente.
      $pdo->prepare("UPDATE conversations SET assigned_user_id=?,mode='human',updated_at=NOW() WHERE id=?")->execute([Auth::user()['id'],$cid]);
      Audit::log('message.reply','conversation',$cid,['ai_paused'=>true]);U::redirect('index.php?p=messages&id='.$cid);
    }
  }
  $channel=(string)($_GET['channel']??'all');
  if(!in_array($channel,['all','site','whatsapp','phone','other'],true))$channel='all';
  $search=trim((string)($_GET['search']??''));
  $q=$pdo->prepare("SELECT c.*,g.name guest,g.email guest_email,g.whatsapp guest_whatsapp,r.code FROM conversations c LEFT JOIN guests g ON g.id=c.guest_id LEFT JOIN reservations r ON r.id=c.reservation_id WHERE (?='all' OR (?='other' AND c.channel NOT IN('site','whatsapp','phone')) OR c.channel=?) AND (?='' OR g.name LIKE ? OR g.whatsapp LIKE ? OR r.code LIKE ?) ORDER BY c.status='open' DESC,c.updated_at DESC LIMIT 400");
  $like='%'.$search.'%';$q->execute([$channel,$channel,$channel,$search,$like,$like,$like]);$xs=$q->fetchAll();
  $h=adminTitle('Central de Atendimento','Conversas do site, hóspedes e canais integrados em uma única fila.');
  $h.='<section class="card"><form method="get" class="map-editor-actions"><input type="hidden" name="p" value="messages"><label>Canal<select name="channel"><option value="all" '.($channel==='all'?'selected':'').'>Todos</option><option value="site" '.($channel==='site'?'selected':'').'>Site / Hóspedes</option><option value="whatsapp" '.($channel==='whatsapp'?'selected':'').'>WhatsApp</option><option value="phone" '.($channel==='phone'?'selected':'').'>Telefone</option><option value="other" '.($channel==='other'?'selected':'').'>Outras integrações</option></select></label><label>Localizar conversa<input type="search" name="search" value="'.U::e($search).'" placeholder="Nome, WhatsApp ou reserva"></label><button>Filtrar</button><a class="btn secondary" href="index.php?p=whatsapp_rnl">WhatsApp</a><a class="btn secondary" href="index.php?p=integrations">Integrações</a></form><p class="muted">Os canais aparecem quando suas mensagens entram na fila de conversas existente.</p></section>';
  $h.='<div class=split><div class=card><div class="table-scroll"><table><tr><th>Cliente</th><th>Contato</th><th>Reserva</th><th>Canal</th><th>Modo</th><th>Status</th><th></th></tr>';
  foreach($xs as $x){
    $contact=trim(($x['guest_whatsapp']??'').' '.($x['guest_email']??''));
    $modeLabel=['human'=>'Humano','hybrid'=>'Híbrido','ai'=>'IA'][$x['mode']??'hybrid']??'Híbrido';
    $h.='<tr><td>'.U::e($x['guest']?:'Visitante').'</td><td>'.U::e($contact?:'—').'</td><td>'.U::e($x['code']?:'—').'</td><td>'.U::e($x['channel']).'</td><td><span class="status-badge">'.U::e($modeLabel).'</span></td><td>'.U::e($x['status']==='open'?'Aberto':'Encerrado').'</td><td><a href="index.php?p=messages&id='.$x['id'].'">Abrir</a></td></tr>';
  }
  $h.='</table></div></div>';
  if($cid){
    $q=$pdo->prepare("SELECT m.*,c.mode,c.status,g.name guest,g.email guest_email,g.whatsapp guest_whatsapp,r.code FROM messages m JOIN conversations c ON c.id=m.conversation_id LEFT JOIN guests g ON g.id=c.guest_id LEFT JOIN reservations r ON r.id=c.reservation_id WHERE m.conversation_id=? ORDER BY m.id");
    $q->execute([$cid]);$msgs=$q->fetchAll();
    $h.='<div class=card><h2>Conversa</h2>';
    $q=$pdo->prepare('SELECT c.guest_id,c.reservation_id,g.name FROM conversations c LEFT JOIN guests g ON g.id=c.guest_id WHERE c.id=?');$q->execute([$cid]);$linked=$q->fetch();
    if($linked){
      $docs=(new \App\Services\GuestDocumentService())->listFor((int)$linked['guest_id'],$linked['reservation_id']!==null?(int)$linked['reservation_id']:null);
      $h.='<section class="guest-conversation-docs"><h3>Arquivos do cliente</h3>'.\App\Services\GuestDocumentService::links($docs).'<p><a href="index.php?p=guests&id='.(int)$linked['guest_id'].'">Ficha do hóspede</a></p></section>';
    }
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
