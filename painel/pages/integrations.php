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
    $pdo->prepare("UPDATE integrations SET enabled=?,config_json=?,status=?,updated_at=NOW() WHERE type=?")->execute([isset($_POST['enabled'])?1:0,json_encode($clean,JSON_UNESCAPED_UNICODE),isset($_POST['enabled'])?'configured_unverified':'not_configured',$type]);U::flash('ok','Integração salva. Ainda precisa ser testada/homologada.');U::redirect($type==='ical'?'index.php?p=map_settings&tab=integrations':'index.php?p=integrations');
  }
  $rows=$pdo->query("SELECT * FROM integrations WHERE type<>'whatsapp' ORDER BY FIELD(type,'pix','instagram','email','ical','maps'),type")->fetchAll();$by=[];foreach($rows as $r)$by[$r['type']]=$r;
  $definitions=[
    'pix'=>['title'=>'PIX','desc'=>'Gateway de pagamento. Não ativa cobrança automática sem credenciais reais.','fields'=>['provider'=>'Provedor (ex.: Mercado Pago / Efí)','access_token'=>'Access Token / credencial','public_key'=>'Public Key, se houver','webhook_secret'=>'Segredo do webhook','pix_key'=>'Chave PIX da pousada, quando aplicável']],
    'instagram'=>['title'=>'Meta · Facebook e Instagram','desc'=>'Campos para Meta Business, páginas, Instagram e API oficial. Salvar não autoriza publicação nem comprova conexão; requer permissões e teste OAuth/API.','fields'=>['instagram_account_id'=>'Instagram Business Account ID','page_id'=>'Facebook Page ID vinculado','business_id'=>'Meta Business ID','app_id'=>'Meta App ID','app_secret'=>'Meta App Secret','access_token'=>'Page Access Token / Token de acesso','verify_token'=>'Token de verificação do webhook','ad_account_id'=>'Meta Ads Account ID']],
    'email'=>['title'=>'E-mail SMTP','desc'=>'Servidor para notificações transacionais.','fields'=>['host'=>'Servidor SMTP','port'=>'Porta','encryption'=>'Segurança (ssl/tls)','username'=>'Usuário','password'=>'Senha','from_email'=>'E-mail remetente','from_name'=>'Nome do remetente']],
    'maps'=>['title'=>'Google · Maps, Analytics, Search Console e SEO','desc'=>'Configurações do Google; cada produto requer API habilitada, permissões e homologação específica. Não enviar chaves para o navegador.','fields'=>['provider'=>'Provedor de mapas (OpenStreetMap/Google)','api_key'=>'Google Maps API Key','ga4_measurement_id'=>'GA4 Measurement ID (G-...)','gtm_container_id'=>'Google Tag Manager ID (GTM-...)','search_console_site_url'=>'Search Console: URL verificada da propriedade','google_site_verification'=>'Código de verificação Google','google_service_account_key'=>'Credencial de serviço Google (somente se exigida)']]
  ];
  // Keep every existing integration action and field; present them only in focused dialogs.
  $h=adminTitle('Integrações','Gerencie os canais sem expor formulários na página.');
  $h.='<style>
    .ig-board{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,205px),1fr));gap:12px;margin:12px 0 24px;align-items:stretch}
    .ig-tile{background:#fff;border:1px solid #a6b8b0;border-radius:11px;padding:13px;text-align:left;display:flex;flex-direction:column;gap:9px;min-height:125px;min-width:0;color:#173d34;box-shadow:0 2px 8px #16372a0a;cursor:pointer}
    .ig-tile:hover,.ig-tile:focus-visible{border-color:#117d6a;box-shadow:0 4px 15px #135d3928;outline-offset:2px}
    .ig-title{display:block;font-weight:700;font-size:14px;overflow-wrap:anywhere}
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
  $h.='<div class="ig-head"><h2>Serviços e integrações</h2></div><div class="ig-board">';
  $dialogs=[];
  foreach($definitions as $type=>$def){
    $r=$by[$type]??['enabled'=>0,'status'=>'not_configured','config_json'=>''];
    $cfg=json_decode($r['config_json']?:'{}',true)?:[];
    $id='ig-service-'.$type;
    $state=statusPt((string)$r['status']);
    $h.='<button class="ig-tile" type="button" data-ig-open="'.U::e($id).'"><span class="ig-title">'.U::e($def['title']).'</span><span class="ig-state">'.U::e($state).'</span><span class="ig-meta">'.((int)$r['enabled']?'Habilitado':'Desabilitado').' · Configurar</span></button>';
    $body='<p class="ig-description">'.U::e($def['desc']).'</p><form method="post">'.Csrf::field().'<input type="hidden" name="type" value="'.U::e($type).'">';
    $body.=row('Habilitar','enabled',$r['enabled'],'checkbox');
    foreach($def['fields'] as $key=>$label){
      $secret=str_ends_with($key,'password')||str_contains($key,'token')||str_contains($key,'secret')||str_contains($key,'key');
      $body.=row($label,'cfg['.$key.']',$secret?'':($cfg[$key]??''),$secret?'password':'text');
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
