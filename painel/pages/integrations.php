<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requireSuperAdmin();
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$type=(string)($_POST['type']??'');$cfg=$_POST['cfg']??[];
    if(!in_array($type,['pix','instagram','email','ical','maps'],true)||!is_array($cfg))throw new RuntimeException('Integração ou configuração inválida.');
    $q=$pdo->prepare("SELECT config_json FROM integrations WHERE type=?");$q->execute([$type]);$current=json_decode($q->fetchColumn()?:'{}',true)?:[];$clean=$current;
    foreach($cfg as $k=>$v){
      $isSecret=str_ends_with($k,'password')||str_contains($k,'token')||str_contains($k,'secret')||str_contains($k,'key');
      $v=trim((string)$v);
      if($isSecret){if($v==='')continue;$v=Secret::encrypt($v);}
      $clean[$k]=$v;
    }
    $scope=(string)($_POST['form_scope']??'legacy');
    // Cards for the same provider save only their fields; do not toggle unrelated
    // Meta/Google services because one sub-card was edited.
    $q=$pdo->prepare("SELECT enabled FROM integrations WHERE type=?");$q->execute([$type]);$previousEnabled=(int)($q->fetchColumn()?:0);
    $enabled=$scope==='split'?$previousEnabled:(isset($_POST['enabled'])?1:0);
    $pdo->prepare("UPDATE integrations SET enabled=?,config_json=?,status=?,updated_at=NOW() WHERE type=?")->execute([$enabled,json_encode($clean,JSON_UNESCAPED_UNICODE),$enabled?'configured_unverified':'not_configured',$type]);
    U::flash('ok','Configuração salva; conexão ainda não homologada.');
    U::redirect($type==='ical'?'index.php?p=map_settings&tab=integrations':($type==='pix'?'/painel/financeiro.php':'/painel/integrations.php'));
  }
  $rows=$pdo->query("SELECT * FROM integrations WHERE type<>'whatsapp' ORDER BY FIELD(type,'pix','instagram','email','ical','maps'),type")->fetchAll();$by=[];foreach($rows as $r)$by[$r['type']]=$r;
  // Each card has its own presentation and form; Meta and Google credential stores
  // remain the existing shared provider records to avoid losing saved secrets.
  $cards=[
    'instagram'=>['type'=>'instagram','title'=>'Instagram','icon'=>'instagram','desc'=>'Mensagens, comentários e recursos da conta profissional. Exige permissões Meta e teste real.','fields'=>['instagram_account_id'=>'Instagram Business Account ID','business_id'=>'Meta Business ID','app_id'=>'Meta App ID','app_secret'=>'Meta App Secret','access_token'=>'Token de acesso','verify_token'=>'Token de verificação webhook']],
    'facebook'=>['type'=>'instagram','title'=>'Facebook','icon'=>'facebook','desc'=>'Página, Messenger e comentários com credenciais Meta compartilhadas. Não comprova conexão ativa.','fields'=>['page_id'=>'Facebook Page ID','business_id'=>'Meta Business ID','app_id'=>'Meta App ID','app_secret'=>'Meta App Secret','access_token'=>'Page Access Token','verify_token'=>'Token de verificação webhook','ad_account_id'=>'Meta Ads Account ID']],
    'email'=>['type'=>'email','title'=>'E-mail SMTP','icon'=>'email','desc'=>'Servidor de notificações transacionais.','fields'=>['host'=>'Servidor SMTP','port'=>'Porta','encryption'=>'Segurança (ssl/tls)','username'=>'Usuário','password'=>'Senha','from_email'=>'E-mail remetente','from_name'=>'Nome do remetente']],
    'google_maps'=>['type'=>'maps','title'=>'Google Maps','icon'=>'maps','desc'=>'Mapa e localização. Requer API habilitada e chave autorizada.','fields'=>['provider'=>'Provedor (OpenStreetMap/Google)','api_key'=>'Google Maps API Key']],
    'google_analytics'=>['type'=>'maps','title'=>'Google Analytics','icon'=>'analytics','desc'=>'Medição GA4 por ID da propriedade.','fields'=>['ga4_measurement_id'=>'GA4 Measurement ID (G-...)']],
    'google_tag'=>['type'=>'maps','title'=>'Google Tag Manager','icon'=>'tag','desc'=>'Gerenciador de tags com identificador do contêiner.','fields'=>['gtm_container_id'=>'Google Tag Manager ID (GTM-...)']],
    'google_search'=>['type'=>'maps','title'=>'Google Search Console','icon'=>'search','desc'=>'Validação e propriedade no Search Console.','fields'=>['search_console_site_url'=>'URL verificada da propriedade','google_site_verification'=>'Código de verificação Google']],
    'google_service'=>['type'=>'maps','title'=>'Google · Credenciais','icon'=>'key','desc'=>'Credencial de serviço, somente quando necessária. Não concede acesso automaticamente.','fields'=>['google_service_account_key'=>'Credencial de serviço Google']]
  ];
  $pix=['type'=>'pix','title'=>'PIX','icon'=>'pix','desc'=>'Gateway e credenciais financeiras. Configuração separada das redes sociais e Google.','fields'=>['provider'=>'Provedor (Mercado Pago/Efí)','access_token'=>'Token / credencial','public_key'=>'Public Key','webhook_secret'=>'Segredo do webhook','pix_key'=>'Chave PIX']];
  $isPixPage=($_GET['service']??'')==='pix';
  if($isPixPage)$cards=['pix'=>$pix];
  // Keep every existing integration action and field; present them only in focused dialogs.
  $h=adminTitle('Integrações','Gerencie os canais sem expor formulários na página.');
  $h.='<style>
    .ig-board{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,205px),1fr));gap:12px;margin:12px 0 24px;align-items:stretch}
    .ig-tile{background:#fff;border:1px solid #a6b8b0;border-radius:11px;padding:13px;text-align:left;display:flex;flex-direction:column;gap:9px;min-height:125px;min-width:0;color:#173d34;box-shadow:0 2px 8px #16372a0a;cursor:pointer}
    .ig-tile:hover,.ig-tile:focus-visible{border-color:#117d6a;box-shadow:0 4px 15px #135d3928;outline-offset:2px}
    .ig-title{display:flex;align-items:center;gap:9px;font-weight:700;font-size:14px;overflow-wrap:anywhere}
    .ig-meta{color:#54695e;font-size:12px;line-height:1.45;overflow-wrap:anywhere}
    .ig-state{display:inline-block;align-self:flex-start;padding:3px 8px;border:1px solid #c6d4cd;border-radius:20px;font-size:11px;background:#f4f8f5}
    .ig-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:22px 0 6px}
    .ig-head h2{margin:0;font-size:17px}
    .ig-dialog{border:1px solid #8fa89d;border-radius:15px;box-sizing:border-box;padding:0;color:#203b32;background:white;box-shadow:0 25px 80px #132c2d55;width:min(550px,calc(100vw - 24px));max-height:min(88dvh,850px);overflow:hidden}
    .ig-dialog::backdrop{background:rgba(10,29,27,.63)}
    .ig-dialog[open]{display:flex;flex-direction:column}
    .ig-dialog-head{padding:16px 20px;border-bottom:1px solid #d7e2dc;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-shrink:0}
    .ig-dialog-head h2{margin:0;font-size:17px}
    .ig-dialog-close{border:1px solid #cad9d0;border-radius:8px;background:#fff;padding:6px 11px;font-size:20px;cursor:pointer}
    .ig-dialog-body{overflow-y:auto;overscroll-behavior:contain;padding:18px 20px 22px;min-height:0}
    .ig-dialog-body form{display:grid;gap:10px;min-width:0}
    .ig-dialog-body form label{display:block;min-width:0}
    .ig-dialog-body input:not([type=checkbox]),.ig-dialog-body select,.ig-dialog-body textarea{box-sizing:border-box;max-width:100%;width:100%}
    .ig-dialog-body form button[type=submit]{justify-self:start}
    .ig-dialog-body .ig-division{border:0;border-top:1px solid #dce6df;margin:16px 0}
    .ig-dialog-body .ig-description{font-size:12px;color:#50685d;margin:0 0 12px}
    @media(max-width:620px){.ig-board{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.ig-tile{min-height:115px;padding:11px}.ig-dialog{max-height:94dvh}.ig-dialog-body{padding:12px}}
  </style>';
  $makeDialog=function(string $id,string $title,string $body)use(&$h):void{
    $h.='<dialog class="ig-dialog" id="'.U::e($id).'" aria-label="'.U::e($title).'"><header class="ig-dialog-head"><h2>'.U::e($title).'</h2><button class="ig-dialog-close" type="button" data-ig-close aria-label="Fechar">×</button></header><div class="ig-dialog-body">'.$body.'</div></dialog>';
  };
  $h.='<div class="ig-head"><h2>'.($isPixPage?'Configuração PIX':'Serviços e integrações').'</h2></div>';
  if($isPixPage)$h.='<p><a href="/painel/financeiro.php">← Voltar ao Financeiro</a></p>';
  $h.='<div class="ig-board">';
  $dialogs=[];
  foreach($cards as $key=>$def){
    $type=$def['type'];
    $r=$by[$type]??['enabled'=>0,'status'=>'not_configured','config_json'=>''];
    $cfg=json_decode($r['config_json']?:'{}',true)?:[];
    $id='ig-service-'.$key;
    $hasConfig=false;
    foreach(array_keys($def['fields']) as $field)if(trim((string)($cfg[$field]??''))!==''){$hasConfig=true;break;}
    $state=$hasConfig?'Configurado, não testado':'Não configurado';
    $h.='<button class="ig-tile" type="button" data-ig-open="'.U::e($id).'"><span class="ig-title">'.panelCardIcon($def['icon']).'<span>'.U::e($def['title']).'</span></span><span class="ig-state">'.U::e($state).'</span><span class="ig-meta">Configurar</span></button>';
    $body='<p class="ig-description">'.U::e($def['desc']).'</p><form method="post" action="/painel/integrations.php'.($isPixPage?'?service=pix':'').'">'.Csrf::field().'<input type="hidden" name="type" value="'.U::e($type).'"><input type="hidden" name="form_scope" value="split">';
    foreach($def['fields'] as $field=>$label){
      $secret=str_ends_with($field,'password')||str_contains($field,'token')||str_contains($field,'secret')||str_contains($field,'key');
      $body.=row($label,'cfg['.$field.']',$secret?'':($cfg[$field]??''),$secret?'password':'text');
    }
    $body.='<button type="submit">Salvar configuração</button></form>';
    $dialogs[]=[$id,$def['title'],$body];
  }
  $h.='</div>';
  foreach($dialogs as [$id,$title,$body])$makeDialog($id,$title,$body);
  $h.='<script>
  (()=>{const dialogs=[...document.querySelectorAll(".ig-dialog")];let opener=null;
  document.addEventListener("click",event=>{
    const button=event.target.closest("[data-ig-open]");
    if(button){const dialog=document.getElementById(button.getAttribute("data-ig-open"));if(!dialog||typeof dialog.showModal!=="function")return;opener=button;dialog.showModal();return;}
    const close=event.target.closest("[data-ig-close]");if(close){close.closest("dialog")?.close();}
  });
  for(const dialog of dialogs){
    dialog.addEventListener("click",event=>{if(event.target===dialog)dialog.close();});
    dialog.addEventListener("close",()=>{if(opener&&document.contains(opener))opener.focus();});
  }
  })();
  </script>';
  render('Integrações',$h);exit;
