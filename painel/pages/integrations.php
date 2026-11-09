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
    $pdo->prepare("UPDATE integrations SET enabled=?,config_json=?,status=?,updated_at=NOW() WHERE type=?")->execute([isset($_POST['enabled'])?1:0,json_encode($clean,JSON_UNESCAPED_UNICODE),isset($_POST['enabled'])?'configured_unverified':'not_configured',$type]);U::flash('ok','Integração salva. Ainda precisa ser testada/homologada.');U::redirect('index.php?p=integrations');
  }
  $rows=$pdo->query("SELECT * FROM integrations WHERE type<>'whatsapp' ORDER BY FIELD(type,'pix','instagram','email','ical','maps'),type")->fetchAll();$by=[];foreach($rows as $r)$by[$r['type']]=$r;
  $definitions=[
    'pix'=>['title'=>'PIX','desc'=>'Gateway de pagamento. Não ativa cobrança automática sem credenciais reais.','fields'=>['provider'=>'Provedor (ex.: Mercado Pago / Efí)','access_token'=>'Access Token / credencial','public_key'=>'Public Key, se houver','webhook_secret'=>'Segredo do webhook','pix_key'=>'Chave PIX da pousada, quando aplicável']],
    'instagram'=>['title'=>'Meta · Facebook e Instagram','desc'=>'Campos para Meta Business, páginas, Instagram e API oficial. Salvar não autoriza publicação nem comprova conexão; requer permissões e teste OAuth/API.','fields'=>['instagram_account_id'=>'Instagram Business Account ID','page_id'=>'Facebook Page ID vinculado','business_id'=>'Meta Business ID','app_id'=>'Meta App ID','app_secret'=>'Meta App Secret','access_token'=>'Page Access Token / Token de acesso','verify_token'=>'Token de verificação do webhook','ad_account_id'=>'Meta Ads Account ID']],
    'email'=>['title'=>'E-mail SMTP','desc'=>'Servidor para notificações transacionais.','fields'=>['host'=>'Servidor SMTP','port'=>'Porta','encryption'=>'Segurança (ssl/tls)','username'=>'Usuário','password'=>'Senha','from_email'=>'E-mail remetente','from_name'=>'Nome do remetente']],
    'ical'=>['title'=>'iCal','desc'=>'Sincronização de calendários por unidade.','fields'=>['sync_mode'=>'Modo (manual/cron)','cron_secret'=>'Segredo do cron','notes'=>'Observações de configuração']],
    'maps'=>['title'=>'Google · Maps, Analytics, Search Console e SEO','desc'=>'Configurações do Google; cada produto requer API habilitada, permissões e homologação específica. Não enviar chaves para o navegador.','fields'=>['provider'=>'Provedor de mapas (OpenStreetMap/Google)','api_key'=>'Google Maps API Key','ga4_measurement_id'=>'GA4 Measurement ID (G-...)','gtm_container_id'=>'Google Tag Manager ID (GTM-...)','search_console_site_url'=>'Search Console: URL verificada da propriedade','google_site_verification'=>'Código de verificação Google','google_service_account_key'=>'Credencial de serviço Google (somente se exigida)']]
  ];
  $h=adminTitle('Integrações','Campos próprios por serviço. Nenhuma integração aparece como homologada sem teste real.').'<div class=cards integrations-grid>';
  foreach($definitions as $type=>$def){$r=$by[$type]??['enabled'=>0,'status'=>'not_configured','config_json'=>''];$cfg=json_decode($r['config_json']?:'{}',true)?:[];$h.='<form method=post class=card>'.Csrf::field().'<input type=hidden name=type value="'.U::e($type).'"><h2>'.U::e($def['title']).'</h2><p>'.U::e($def['desc']).'</p><p>Status: <span class=status-badge>'.U::e(statusPt($r['status'])).'</span></p>'.row('Habilitar','enabled',$r['enabled'],'checkbox');foreach($def['fields'] as $key=>$lab){$isSecret=str_contains($key,'password')||str_contains($key,'token')||str_contains($key,'secret')||str_contains($key,'key');$value=$isSecret?'':($cfg[$key]??'');$h.=row($lab,"cfg[$key]",$value,$isSecret?'password':'text');}$h.='<button>Salvar configuração</button></form>';}
  $h.='</div><section id=ical-rooms class=card><h2>Calendários iCal por quarto</h2><p class=muted>Cada quarto pode exportar seu calendário e importar bloqueios de outro canal. A sincronização manual grava os períodos no Mapa de Reservas.</p>';
  $roomsIcal=$pdo->query("SELECT r.id,r.name,f.id feed_id,f.export_token,f.import_url,f.active,f.last_sync_at,f.last_error FROM rooms r LEFT JOIN ical_feeds f ON f.room_id=r.id ORDER BY r.name")->fetchAll();
  foreach($roomsIcal as $ir){$export=$ir['export_token']?('ical.php?token='.$ir['export_token']):'Será criado ao salvar';$h.='<form method=post action="index.php?p=ical_save" class="ical-row">'.Csrf::field().'<input type=hidden name=room_id value="'.$ir['id'].'"><div><h3>'.U::e($ir['name']).'</h3><label>Feed de exportação<input readonly value="'.U::e($export).'"></label></div><div>'.row('URL iCal para importar','import_url',$ir['import_url']).row('Ativo','active',$ir['active']??1,'checkbox').'<button>Salvar iCal</button></div></form>';if($ir['feed_id']&&$ir['import_url'])$h.='<form method=post action="index.php?p=ical_sync" class=inline>'.Csrf::field().'<input type=hidden name=feed_id value="'.$ir['feed_id'].'"><button class=secondary>Sincronizar agora</button><span class=muted>Última: '.U::e($ir['last_sync_at']?:'nunca').'</span></form>';if($ir['last_error'])$h.='<div class="alert error">'.U::e($ir['last_error']).'</div>';}
  $h.='</section>';render('Integrações',$h);exit;
