<?php
use App\Core\Auth;use App\Core\Csrf;use App\Core\U;
if(!defined('SUITES_PAGE_ENTRY')){http_response_code(404);exit;}
Auth::requireSuperAdmin();
$tab=($_GET['tab']??'general')==='integrations'?'integrations':'general';
$units=$pdo->query("SELECT id,name FROM rooms WHERE code NOT LIKE '__deleted__%' AND active=1 ORDER BY name")->fetchAll();
$h=adminTitle('Configurações do Mapa','Configuração e distribuição de disponibilidade das unidades cadastradas.');
$h.='<style>
.ms-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 18px}.ms-tabs a{display:inline-flex;align-items:center;border:1px solid #9cb7a9;padding:9px 14px;border-radius:9px;background:#fff;color:#174a3d;text-decoration:none;font-weight:600}.ms-tabs a[aria-current=page]{background:#145c4e;color:#fff;border-color:#145c4e}
.ms-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,215px),1fr));gap:12px;margin:12px 0 24px}
.ms-card{border:1px solid #a6b8b0;border-radius:10px;background:#fff;padding:13px;min-height:108px;display:flex;flex-direction:column;gap:9px;min-width:0;box-sizing:border-box;color:#173d34}
button.ms-card{text-align:left;cursor:pointer}button.ms-card:hover,button.ms-card:focus-visible{border-color:#117d6a;box-shadow:0 4px 14px #135d3928}
.ms-card strong{display:flex;align-items:center;gap:8px;font-size:14px;overflow-wrap:anywhere}.ms-card small{color:#54695e;font-size:12px;overflow-wrap:anywhere}.ms-pill{display:inline-block;align-self:flex-start;padding:3px 9px;background:#f2f7f4;border:1px solid #b8d0c3;border-radius:16px;font-size:11px}
.ms-block{margin:18px 0}.ms-block h2{font-size:17px;margin:0 0 9px}.ms-block p{font-size:12px;color:#536b60;margin:0 0 10px}
.ms-modal{width:min(550px,calc(100vw - 24px));max-height:88dvh;box-sizing:border-box;overflow:hidden;border:1px solid #96b0a4;border-radius:14px;box-shadow:0 24px 70px #16362a55;background:#fff;color:#193e32;padding:0}
.ms-modal::backdrop{background:#101f1ccc}.ms-modal[open]{display:flex;flex-direction:column}
.ms-modal header{display:flex;align-items:center;justify-content:space-between;padding:15px 19px;border-bottom:1px solid #d8e6dd;gap:12px}.ms-modal header h2{font-size:17px;margin:0}
.ms-close{background:#fff;border:1px solid #bacfc3;border-radius:8px;padding:6px 12px;font-size:18px;cursor:pointer}
.ms-modal-body{padding:17px 20px 21px;overflow-y:auto;min-height:0;overscroll-behavior:contain}
.ms-modal-body form{display:grid;gap:11px}.ms-modal-body form input:not([type=checkbox]),.ms-modal-body form select,.ms-modal-body form textarea{box-sizing:border-box;width:100%;max-width:100%}
.ms-modal-body form button{justify-self:start}.ms-modal-body label{display:block}.ms-modal-body hr{border:0;border-top:1px solid #dae5de;margin:14px 0}
@media(max-width:620px){.ms-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.ms-modal{max-height:94dvh}}
</style>';
$h.='<nav class="ms-tabs" aria-label="Configurações do mapa"><a href="index.php?p=map_settings&tab=general"'.($tab==='general'?' aria-current="page"':'').'>Geral</a><a href="index.php?p=map_settings&tab=integrations"'.($tab==='integrations'?' aria-current="page"':'').'>Integrações de calendários</a><a href="index.php?p=map">Voltar ao mapa</a></nav>';
$dialogs=[];
if($tab==='general'){
  $h.='<section class="ms-block"><h2>Configuração do mapa</h2><div class="ms-grid">';
  $h.='<a class="ms-card" href="index.php?p=rooms"><strong>'.panelCardIcon('home').' Unidades cadastradas</strong><small>'.count($units).' unidade(s) ativa(s). Os nomes são definidos exclusivamente no cadastro do estabelecimento.</small></a>';
  $h.='<a class="ms-card" href="index.php?p=tariffs"><strong>'.panelCardIcon('calendar').' Diárias e tarifas</strong><small>Valores utilizados no cálculo das reservas e exibidos no mapa.</small></a>';
  $h.='<a class="ms-card" href="index.php?p=reservations"><strong>'.panelCardIcon('payments').' Reservas</strong><small>Gerenciar entradas, saídas e disponibilidade.</small></a>';
  $h.='<a class="ms-card" href="index.php?p=map_settings&tab=integrations"><strong>'.panelCardIcon('calendar').' Integrações de calendários</strong><small>Conectar canais às unidades já cadastradas.</small></a>';
  $h.='</div></section>';
}else{
  $h.='<section class="ms-block"><h2>Conexões com canais</h2><p>Os nomes das unidades são carregados do cadastro existente. Nenhum quarto é criado ou renomeado aqui. O iCal troca disponibilidade, não mensagens ou tarifas.</p><div class="ms-grid">';
  $feeds=$pdo->query("SELECT f.*,r.name room_name FROM suites_ical_channel_feeds f JOIN rooms r ON r.id=f.room_id ORDER BY r.name,f.channel,f.id")->fetchAll();
  $h.='<button type="button" class="ms-card" data-ms-open="ms-add"><strong>'.panelCardIcon('calendar').' Adicionar canal</strong><span class="ms-pill">Nova conexão</span><small>Selecionar uma unidade e vincular o calendário externo.</small></button>';
  $new='<form method="post" action="index.php?p=ical_save">'.Csrf::field().'<input type="hidden" name="calendar_mode" value="channel"><label>Unidade cadastrada<select name="room_id" required>';
  foreach($units as $u)$new.='<option value="'.(int)$u['id'].'">'.U::e($u['name']).'</option>';
  $new.='</select></label><label>Canal<select name="channel"><option value="airbnb">Airbnb</option><option value="booking">Booking.com</option><option value="expedia">Expedia</option><option value="other">Outro</option></select></label><label>URL iCal HTTPS<input type="url" name="import_url" maxlength="1900" required placeholder="https://..."></label><label><input type="checkbox" name="active" checked> Ativo</label><button type="submit">Adicionar canal</button></form>';
  $dialogs[]=['ms-add','Adicionar canal',$new];
  foreach($feeds as $feed){
    $id='ms-channel-'.(int)$feed['id'];
    $name=trim((string)$feed['room_name']).' · '.ucfirst((string)$feed['channel']);
    $h.='<button type="button" class="ms-card" data-ms-open="'.$id.'"><strong>'.U::e($name).'</strong><span class="ms-pill">'.((int)$feed['active']?'Ativo':'Desativado').'</span><small>Última sincronização: '.U::e($feed['last_sync_at']?:'nunca').'</small></button>';
    $body='';
    if($feed['last_error'])$body.='<p class="alert error">'.U::e($feed['last_error']).'</p>';
    $body.='<form method="post" action="index.php?p=ical_save">'.Csrf::field().'<input type="hidden" name="calendar_mode" value="channel"><input type="hidden" name="channel_feed_id" value="'.(int)$feed['id'].'"><input type="hidden" name="room_id" value="'.(int)$feed['room_id'].'"><input type="hidden" name="channel" value="'.U::e($feed['channel']).'"><label>URL do calendário<input name="import_url" type="url" maxlength="1900" value="'.U::e($feed['import_url']).'" required></label><label><input type="checkbox" name="active"'.((int)$feed['active']?' checked':'').'> Ativo</label><button type="submit">Salvar canal</button></form>';
    if($feed['active'])$body.='<hr><form method="post" action="index.php?p=ical_sync">'.Csrf::field().'<input type="hidden" name="calendar_mode" value="channel"><input type="hidden" name="feed_id" value="'.(int)$feed['id'].'"><button type="submit" class="secondary">Sincronizar agora</button></form>';
    $dialogs[]=[$id,$name,$body];
  }
  $h.='</div></section>';
  $legacy=$pdo->query("SELECT r.id,r.name,f.id feed_id,f.export_token,f.import_url,f.active,f.last_sync_at,f.last_error FROM rooms r JOIN ical_feeds f ON f.room_id=r.id WHERE f.import_url IS NOT NULL AND f.import_url<>'' ORDER BY r.name")->fetchAll();
  $h.='<section class="ms-block"><h2>Calendários legados configurados</h2><p>Somente conexões já existentes. Não duplicar estes feeds em novos canais sem antes conferir a disponibilidade.</p><div class="ms-grid">';
  foreach($legacy as $feed){
    $id='ms-legacy-'.(int)$feed['id'];
    $h.='<button type="button" class="ms-card" data-ms-open="'.$id.'"><strong>'.U::e($feed['name']).'</strong><span class="ms-pill">'.((int)$feed['active']?'Ativo':'Desativado').'</span><small>Última: '.U::e($feed['last_sync_at']?:'nunca').'</small></button>';
    $body='';
    if($feed['last_error'])$body.='<p class="alert error">'.U::e($feed['last_error']).'</p>';
    $body.='<form method="post" action="index.php?p=ical_save">'.Csrf::field().'<input type="hidden" name="room_id" value="'.(int)$feed['id'].'"><label>Feed exportação<input readonly value="'.U::e('ical.php?token='.$feed['export_token']).'"></label><label>URL iCal de importação<input type="url" name="import_url" value="'.U::e($feed['import_url']).'"></label><label><input type="checkbox" name="active"'.((int)$feed['active']?' checked':'').'> Ativo</label><button type="submit">Salvar iCal</button></form>';
    if($feed['active'])$body.='<hr><form method="post" action="index.php?p=ical_sync">'.Csrf::field().'<input type="hidden" name="feed_id" value="'.(int)$feed['feed_id'].'"><button type="submit" class="secondary">Sincronizar legado</button></form>';
    $dialogs[]=[$id,$feed['name'],$body];
  }
  if(!$legacy)$h.='<p>Nenhuma conexão legada configurada.</p>';
  $h.='</div></section>';
  $cfgRow=$pdo->query("SELECT * FROM integrations WHERE type='ical' LIMIT 1")->fetch();
  $cfg=$cfgRow?json_decode((string)$cfgRow['config_json'],true):[];
  if(!is_array($cfg))$cfg=[];
  $h.='<section class="ms-block"><h2>Configuração iCal</h2><div class="ms-grid"><button class="ms-card" type="button" data-ms-open="ms-config"><strong>'.panelCardIcon('settings').' Preferências de sincronização</strong><span class="ms-pill">'.(($cfgRow&&$cfgRow['enabled'])?'Habilitada':'Desabilitada').'</span><small>Modo, segredo e observações. Salvar não ativa tarefas automáticas sem serviço homologado.</small></button></div></section>';
  $body='<form method="post" action="index.php?p=integrations">'.Csrf::field().'<input type="hidden" name="type" value="ical"><label><input type="checkbox" name="enabled"'.(($cfgRow&&$cfgRow['enabled'])?' checked':'').'> Habilitar</label><label>Modo (manual/cron)<input name="cfg[sync_mode]" value="'.U::e($cfg['sync_mode']??'').'"></label><label>Segredo do cron<input type="password" name="cfg[cron_secret]" autocomplete="new-password" value="" placeholder="Manter atual se vazio"></label><label>Observações<textarea name="cfg[notes]" rows="3">'.U::e($cfg['notes']??'').'</textarea></label><button type="submit">Salvar configuração</button></form>';
  $dialogs[]=['ms-config','Configuração iCal',$body];
  foreach($dialogs as [$id,$title,$body])$h.='<dialog class="ms-modal" id="'.U::e($id).'" aria-label="'.U::e($title).'"><header><h2>'.U::e($title).'</h2><button type="button" class="ms-close" data-ms-close aria-label="Fechar">×</button></header><div class="ms-modal-body">'.$body.'</div></dialog>';
  $h.='<script>(()=>{let opener=null;document.addEventListener("click",e=>{const trigger=e.target.closest("[data-ms-open]");if(trigger){const d=document.getElementById(trigger.dataset.msOpen);if(d&&typeof d.showModal==="function"){opener=trigger;d.showModal();}return;}if(e.target.closest("[data-ms-close]"))e.target.closest("dialog")?.close();});document.querySelectorAll(".ms-modal").forEach(d=>{d.addEventListener("click",e=>{if(e.target===d)d.close();});d.addEventListener("close",()=>{if(opener&&document.contains(opener))opener.focus();});});})();</script>';
}
render('Configurações do Mapa',$h);exit;
