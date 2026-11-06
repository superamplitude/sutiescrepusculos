<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Site;
if(!defined('SUITES_PAGE_ENTRY')){http_response_code(404);exit;}
Auth::requirePerm('messages.manage');
$channel=(string)($_GET['channel']??'all');
if(!in_array($channel,['all','site','whatsapp','phone','other'],true))$channel='all';
$search=mb_substr(trim((string)($_GET['search']??'')),0,90);
$cid=(int)($_GET['id']??0);
$pdo=DB::pdo();

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 Csrf::check();
 $cid=(int)($_POST['conversation_id']??0);
 $action=(string)($_POST['action']??'reply');
 $q=$pdo->prepare('SELECT id,channel,guest_id,status FROM conversations WHERE id=? LIMIT 1');$q->execute([$cid]);$conversation=$q->fetch();
 if(!$conversation){U::flash('err','Conversa não encontrada.');U::redirect('index.php?p=messages');}
 $back='index.php?p=messages&id='.$cid;
 if($action==='set_mode'){
  $mode=(string)($_POST['mode']??'');
  if(!in_array($mode,['human','hybrid','ai'],true)){U::flash('err','Modo inválido.');U::redirect($back);}
  $pdo->prepare("UPDATE conversations SET mode=?,assigned_user_id=?,updated_at=NOW() WHERE id=?")->execute([$mode,$mode==='human'?(int)Auth::user()['id']:null,$cid]);
  Audit::log('conversation.mode','conversation',$cid,['mode'=>$mode]);U::flash('ok','Modo do atendimento atualizado.');U::redirect($back);
 }
 if($action!=='reply'){U::flash('err','Ação inválida.');U::redirect($back);}
 $body=trim((string)($_POST['body']??''));
 if($body===''||mb_strlen($body)>4000){U::flash('err','Escreva uma resposta de até 4.000 caracteres.');U::redirect($back);}
 if((string)$conversation['status']!=='open'){U::flash('err','A conversa está encerrada.');U::redirect($back);}
 $origin=(string)$conversation['channel'];
 if($origin==='whatsapp'){
  $q=$pdo->prepare("SELECT jid FROM suites_whatsapp_contacts WHERE guest_id=? LIMIT 1");$q->execute([(int)$conversation['guest_id']]);
  $jid=(string)$q->fetchColumn();
  try{
   if($jid==='')throw new RuntimeException('Contato não pareado.');
   (new \App\Services\SuitesMessagesService())->sendText($jid,$body);
  }catch(Throwable $e){
   U::flash('err','Não foi possível entregar pelo WhatsApp. A mensagem não foi registrada como enviada. Confira a conexão.');
   U::redirect($back);
  }
 }elseif($origin!=='site'){
  U::flash('err','Este canal ainda não possui envio integrado nesta central. Nenhuma resposta foi registrada como enviada.');
  U::redirect($back);
 }
 $pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,'staff',?,NOW())")->execute([$cid,$body]);
 $pdo->prepare("UPDATE conversations SET assigned_user_id=?,mode='human',updated_at=NOW() WHERE id=?")->execute([(int)Auth::user()['id'],$cid]);
 try{(new \App\Services\CrmSyncService(currentCompanyId()))->syncConversation($cid);}catch(Throwable $e){}
 Audit::log('message.reply','conversation',$cid,['channel'=>$origin,'ai_paused'=>true]);
 U::flash('ok',$origin==='whatsapp'?'Resposta entregue ao WhatsApp.':'Resposta registrada na conversa do site.');
 U::redirect($back);
}
$q=$pdo->prepare("SELECT c.id,c.guest_id,c.reservation_id,c.channel,c.mode,c.status,c.priority,c.updated_at,
 g.name guest,g.email guest_email,g.whatsapp guest_whatsapp,r.code,
 (SELECT m.body FROM messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) last_message,
 (SELECT m.sender_type FROM messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) last_sender
 FROM conversations c
 LEFT JOIN guests g ON g.id=c.guest_id LEFT JOIN reservations r ON r.id=c.reservation_id
 WHERE (?='all' OR (?='other' AND c.channel NOT IN('site','whatsapp','phone')) OR c.channel=?)
 AND (?='' OR g.name LIKE ? OR g.whatsapp LIKE ? OR g.email LIKE ? OR r.code LIKE ?)
 ORDER BY c.status='open' DESC,c.updated_at DESC LIMIT 400");
$like='%'.$search.'%';
$q->execute([$channel,$channel,$channel,$search,$like,$like,$like,$like]);
$threads=$q->fetchAll();
if(!$cid&&$threads)$cid=(int)$threads[0]['id'];
$selected=null;
if($cid){
 $q=$pdo->prepare("SELECT c.*,g.name guest,g.email guest_email,g.whatsapp guest_whatsapp,g.phone guest_phone,r.code
 FROM conversations c LEFT JOIN guests g ON g.id=c.guest_id LEFT JOIN reservations r ON r.id=c.reservation_id
 WHERE c.id=? LIMIT 1");$q->execute([$cid]);$selected=$q->fetch()?:null;
}
$channels=['site'=>'Site','whatsapp'=>'WhatsApp','phone'=>'Telefone'];
$modeNames=['human'=>'Humano','hybrid'=>'Híbrido','ai'=>'IA'];
$escape=fn($v)=>U::e((string)$v);
$linkContext='&channel='.rawurlencode($channel).'&search='.rawurlencode($search);
$h='';
$h.='<div class="unified-inbox" data-unified-inbox>';
$h.='<aside class="inbox-sidebar" aria-label="Lista de conversas">';
$h.='<div class="inbox-list-head"><div><h2>Conversas</h2><span>'.count($threads).' encontradas</span></div><a href="index.php?p=messages" class="inbox-refresh" aria-label="Atualizar conversas" title="Atualizar">↻</a></div>';
$h.='<form method="get" class="inbox-search"><input type="hidden" name="p" value="messages">'
    .'<label class="inbox-search-label" for="inboxSearch">Pesquisar</label><input id="inboxSearch" name="search" type="search" value="'.$escape($search).'" placeholder="Nome, telefone ou reserva">'
    .'<label class="inbox-search-label" for="inboxChannel">Canal</label><select id="inboxChannel" name="channel">';
foreach(['all'=>'Todos os canais','site'=>'Site','whatsapp'=>'WhatsApp','phone'=>'Telefone','other'=>'Outros canais'] as $key=>$value){
 $h.='<option value="'.$key.'"'.($channel===$key?' selected':'').'>'.$value.'</option>';
}
$h.='</select><button type="submit" class="inbox-filter-button">Filtrar</button></form>';
$h.='<div class="inbox-threads" role="list">';
foreach($threads as $t){
 $id=(int)$t['id'];$active=$selected&&(int)$selected['id']===$id;
 $name=trim((string)($t['guest']??''))?:'Visitante';
 $origin=(string)$t['channel'];$originName=$channels[$origin]??ucfirst($origin);
 $preview=trim((string)($t['last_message']??''))?:'Sem mensagens recentes';
 $meta=trim((string)($t['guest_whatsapp']??''))?:trim((string)($t['guest_email']??''));
 $time=(string)($t['updated_at']??'');
 $label=$originName.' · '.($modeNames[$t['mode']??'']??'Híbrido');
 $h.='<a role="listitem" class="inbox-thread'.($active?' is-active':'').'" href="index.php?p=messages&id='.$id.$linkContext.'"'.($active?' aria-current="page"':'').'>'
     .'<span class="inbox-thread-avatar" aria-hidden="true">'.mb_strtoupper(mb_substr($name,0,1)).'</span>'
     .'<span class="inbox-thread-content"><span class="inbox-thread-top"><strong>'.$escape($name).'</strong><time>'.$escape(substr($time,5,11)).'</time></span>'
     .'<span class="inbox-thread-preview">'.$escape($preview).'</span>'
     .'<span class="inbox-thread-bottom"><span class="inbox-channel-label">'.$escape($label).'</span><span class="inbox-thread-contact">'.$escape($meta).'</span></span></span>'
     .'</a>';
}
if(!$threads)$h.='<div class="inbox-empty-list">Nenhuma conversa encontrada neste filtro.</div>';
$h.='</div></aside>';
$h.='<section class="inbox-detail" aria-label="Conversa selecionada">';
if(!$selected){
 $h.='<div class="inbox-empty-detail"><strong>Selecione uma conversa</strong><p>As mensagens e respostas aparecerão aqui.</p></div>';
}else{
 $name=trim((string)($selected['guest']??''))?:'Visitante';
 $origin=(string)$selected['channel'];$originName=$channels[$origin]??ucfirst($origin);
 $contact=trim((string)($selected['guest_whatsapp']??''))?:trim((string)($selected['guest_email']??''))?:trim((string)($selected['guest_phone']??''));
 $mode=(string)$selected['mode'];$active=(string)$selected['status']==='open';
 $h.='<header class="inbox-detail-head"><div class="inbox-person"><span class="inbox-person-avatar">'.mb_strtoupper(mb_substr($name,0,1)).'</span><span><strong>'.$escape($name).'</strong><small>'.$escape($contact?:'Contato não informado').'</small></span></div>'
     .'<div class="inbox-detail-tags"><span class="inbox-channel-label">'.$escape($originName).'</span><span class="inbox-state'.($active?' is-open':'').'">'.($active?'Em atendimento':'Encerrada').'</span></div></header>';
 $h.='<div class="inbox-info"><div class="inbox-context-row"><span>Canal: <b>'.$escape($originName).'</b></span>';
 if(!empty($selected['code']))$h.='<a href="index.php?p=reservation_edit&id='.(int)$selected['reservation_id'].'">Reserva '.$escape($selected['code']).'</a>';
 if(!empty($selected['guest_id']))$h.='<a href="index.php?p=guests&id='.(int)$selected['guest_id'].'">Ficha do hóspede</a>';
 $h.='</div>';
 $h.='<form method="post" class="inbox-mode-controls">'.Csrf::field().'<input type="hidden" name="action" value="set_mode"><input type="hidden" name="conversation_id" value="'.$cid.'"><span>Atendimento:</span>';
 foreach(['human'=>'Humano','hybrid'=>'Híbrido','ai'=>'IA'] as $key=>$label){
   $h.='<button type="submit" name="mode" value="'.$key.'" class="'.($mode===$key?'is-current':'').'"'.(!$active?' disabled':'').'>'.$label.'</button>';
 }
 $h.='</form>';
 if(!empty($selected['guest_id'])){
  $docs=(new \App\Services\GuestDocumentService())->listFor((int)$selected['guest_id'],$selected['reservation_id']!==null?(int)$selected['reservation_id']:null);
  $h.='<details class="inbox-files"><summary>Arquivos do cliente <span>'.count($docs).'</span></summary>'.\App\Services\GuestDocumentService::links($docs).'</details>';
 }
 $h.='</div>';
 $q=$pdo->prepare("SELECT id,sender_type,body,created_at FROM messages WHERE conversation_id=? ORDER BY id ASC LIMIT 600");$q->execute([$cid]);$messages=$q->fetchAll();
 $h.='<div class="inbox-message-scroll" role="log" aria-label="Histórico da conversa"><div class="inbox-message-stack">';
 foreach($messages as $m){
  $side=($m['sender_type']==='guest')?'customer':'outgoing';
  $label=($m['sender_type']==='guest')?'Cliente':(($m['sender_type']==='ai')?(trim((string)Site::get('chat_agent_name','Assistente IA'))?:'Assistente IA'):'Equipe');
  $h.='<div class="inbox-message '.$side.'"><div class="inbox-message-who">'.$escape($label).'</div><p>'.nl2br($escape($m['body'])).'</p><time>'.$escape($m['created_at']).'</time></div>';
 }
 if(!$messages)$h.='<div class="inbox-no-messages">Esta conversa ainda não tem mensagens.</div>';
 $h.='</div></div>';
 $canReply=$active&&in_array($origin,['site','whatsapp'],true);
 $h.='<form method="post" class="inbox-reply">'.Csrf::field().'<input type="hidden" name="action" value="reply"><input type="hidden" name="conversation_id" value="'.$cid.'">'
    .'<label for="inboxReply">Responder pelo '.$escape($originName).'</label>';
 if(!$canReply)$h.='<p class="inbox-delivery-warning">'.($active?'Envio para este canal ainda não integrado. As mensagens não serão marcadas como enviadas.':'A conversa está encerrada.').'</p>';
 $h.='<textarea id="inboxReply" name="body" maxlength="4000" rows="3" placeholder="Digite sua mensagem..."'.($canReply?' required':' disabled').'></textarea>'
    .'<div class="inbox-compose-footer"><span>'.($origin==='whatsapp'?'A resposta será enviada pelo WhatsApp conectado.':($origin==='site'?'A resposta ficará disponível no atendimento do site.':'')).'</span>'
    .'<button type="submit"'.($canReply?'':' disabled').'>Enviar resposta</button></div></form>';
}
$h.='</section></div>';
render('Atendimento',$h);exit;
