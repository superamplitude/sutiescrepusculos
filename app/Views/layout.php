<?php use App\Core\U; use App\Core\Auth; use App\Core\Site; $siteSettings=Site::all(); $siteLayout=(string)($siteSettings['layout_variant']??'layout1'); $siteLogo=(string)($siteSettings['logo_path']??''); ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=U::e($title??'Pousada IA')?></title><?php if(!empty($metaDescription)):?><meta name="description" content="<?=U::e($metaDescription)?>"><?php endif;?><?php if(!empty($siteSettings['favicon_path'])):?><link rel="icon" href="<?=U::e($siteSettings['favicon_path'])?>"><?php endif;?><?php $cssV=@filemtime(__DIR__.'/../../assets/css/app.css')?:time(); ?><style id="crepusculo-room-actions">
.table-actions{display:flex!important;align-items:center!important;gap:8px!important;flex-wrap:nowrap!important;white-space:nowrap!important}
.table-actions form{display:inline-flex!important;margin:0!important;padding:0!important;width:auto!important}
.table-actions .btn.secondary.small,
.table-actions a.btn.secondary.small{
  display:inline-flex!important;align-items:center!important;justify-content:center!important;
  width:auto!important;min-width:72px!important;height:34px!important;
  padding:0 14px!important;margin:0!important;border:1px solid #218838!important;
  border-radius:8px!important;background:#28a745!important;color:#fff!important;
  font-weight:600!important;text-decoration:none!important;line-height:1!important
}
.table-actions .danger.small,
.table-actions button.danger.small{
  display:inline-flex!important;align-items:center!important;justify-content:center!important;
  width:auto!important;min-width:72px!important;height:34px!important;
  padding:0 14px!important;margin:0!important;border:1px solid #e76800!important;
  border-radius:8px!important;background:#f47b20!important;color:#fff!important;
  font-weight:600!important;line-height:1!important
}
.table-actions .btn.secondary.small:hover,
.table-actions a.btn.secondary.small:hover{background:#218838!important}
.table-actions .danger.small:hover,
.table-actions button.danger.small:hover{background:#dc6508!important}
</style><link rel="stylesheet" href="assets/css/app.css?v=<?=$cssV?>"><style>:root{--site-primary:<?=U::e(Site::color('primary_color','#0d756d'))?>;--site-secondary:<?=U::e(Site::color('secondary_color','#173f3b'))?>;--site-accent:<?=U::e(Site::color('accent_color','#bf8d39'))?>;--site-body-font:'<?=U::e(Site::font('body_font','Inter'))?>';--site-heading-font:'<?=U::e(Site::font('heading_font','Georgia'))?>';}</style></head>
<body class="<?=Auth::user()?'admin-mode':'public-mode '.U::e($siteLayout)?>">
<?php if(Auth::user()): ?>
<aside class="admin-sidebar">
  <div class="admin-brand">
    <?php if($siteLogo):?><img class="admin-brand-logo" src="<?=U::e($siteLogo)?>" alt="Logomarca"><?php else:?><span class="admin-brand-mark">B</span><?php endif;?>
    <span><b><?=U::e($siteSettings['site_name']?:'POUSADA IA')?></b><small>Painel administrativo</small></span>
  </div>

  <nav class="admin-nav">
    <?php $adminPage=$_GET['p']??'dashboard'; $propertySection=$_GET['section']??'identity'; ?>
    <a href="index.php?p=dashboard" class="admin-dashboard-link <?=$adminPage==='dashboard'?'active':''?>">⌂ <span><?=Auth::isSuperAdmin()?'Painel Mestre':'Dashboard'?></span></a>

    <details name="admin-menu" class="admin-nav-group" <?=in_array($adminPage,['property','hero','rooms','tariffs','promotions','gallery','attractions'],true)?'open':''?>>
      <summary>Estabelecimento</summary>
      <div class="admin-nav-group-links">
        <span class="admin-nav-sublabel">Informações do Estabelecimento</span>
        <?php foreach([
          'identity'=>'Identidade',
          'profile'=>'Perfil / Conteúdo',
          'cover'=>'Capa do site',
          'location'=>'Localização',
          'contact'=>'Contato',
          'operation'=>'Operação',
          'rules'=>'Regras / Políticas',
          'amenities'=>'Comodidades'
        ] as $sk=>$sv): ?>
          <a class="admin-subitem <?=($adminPage==='property'&&$propertySection===$sk)?'active':''?>" href="index.php?p=property&section=<?=$sk?>"><span class="nav-dot"></span><span><?=U::e($sv)?></span></a>
        <?php endforeach;?>
        <a href="index.php?p=hero" class="<?=$adminPage==='hero'?'active':''?>"><span class="nav-dot"></span><span>Editor do Hero</span></a>
        <a href="index.php?p=rooms" class="<?=$adminPage==='rooms'?'active':''?>"><span class="nav-dot"></span><span>Quartos / Unidades</span></a>
        <a href="index.php?p=tariffs" class="<?=$adminPage==='tariffs'?'active':''?>"><span class="nav-dot"></span><span>Tarifas</span></a>
        <a href="index.php?p=promotions" class="<?=$adminPage==='promotions'?'active':''?>"><span class="nav-dot"></span><span>Promoções</span></a>
        <a href="index.php?p=gallery" class="<?=$adminPage==='gallery'?'active':''?>"><span class="nav-dot"></span><span>Galeria</span></a>
        <a href="index.php?p=attractions" class="<?=$adminPage==='attractions'?'active':''?>"><span class="nav-dot"></span><span>Atrações</span></a>
      </div>
    </details>

    <details name="admin-menu" class="admin-nav-group" <?=in_array($adminPage,['reservations','map','guests','payments'],true)?'open':''?>>
      <summary>Reservas</summary>
      <div class="admin-nav-group-links">
        <a href="index.php?p=reservations" class="<?=$adminPage==='reservations'?'active':''?>"><span class="nav-dot"></span><span>Reservas</span></a>
        <a href="index.php?p=map" class="<?=$adminPage==='map'?'active':''?>"><span class="nav-dot"></span><span>Mapa de Reservas</span></a>
        <a href="index.php?p=guests" class="<?=$adminPage==='guests'?'active':''?>"><span class="nav-dot"></span><span>Hóspedes</span></a>
        <a href="index.php?p=payments" class="<?=$adminPage==='payments'?'active':''?>"><span class="nav-dot"></span><span>Pagamentos / PIX</span></a>
      </div>
    </details>

    <details name="admin-menu" class="admin-nav-group" <?=in_array($adminPage,['messages','whatsapp_rnl','contacts_admin','content','reviews'],true)?'open':''?>>
      <summary>Atendimento e conteúdo</summary>
      <div class="admin-nav-group-links">
        <a href="index.php?p=messages" class="<?=$adminPage==='messages'?'active':''?>"><span class="nav-dot"></span><span>Atendimento</span></a>
        <a href="index.php?p=whatsapp_rnl" class="<?=$adminPage==='whatsapp_rnl'?'active':''?>"><span class="nav-dot"></span><span>WhatsApp</span></a>
        <a href="index.php?p=contacts_admin" class="<?=$adminPage==='contacts_admin'?'active':''?>"><span class="nav-dot"></span><span>Contatos do site</span></a>
        <a href="index.php?p=content" class="<?=$adminPage==='content'?'active':''?>"><span class="nav-dot"></span><span>Conteúdo / SEO</span></a>
        <a href="index.php?p=reviews" class="<?=$adminPage==='reviews'?'active':''?>"><span class="nav-dot"></span><span>Avaliações</span></a>
      </div>
    </details>

    <details name="admin-menu" class="admin-nav-group" <?=in_array($adminPage,['ai','voice_settings','reports','integrations'],true)?'open':''?>>
      <summary>IA e resultados</summary>
      <div class="admin-nav-group-links">
        <a href="index.php?p=ai" class="<?=$adminPage==='ai'?'active':''?>"><span class="nav-dot"></span><span>Inteligência Artificial</span></a>
        <a href="index.php?p=voice_settings" class="<?=$adminPage==='voice_settings'?'active':''?>"><span class="nav-dot"></span><span>Voz e atendimento</span></a>
        <a href="index.php?p=reports" class="<?=$adminPage==='reports'?'active':''?>"><span class="nav-dot"></span><span>Relatórios</span></a>
        <?php if(Auth::isSuperAdmin()):?><a href="index.php?p=integrations" class="<?=$adminPage==='integrations'?'active':''?>"><span class="nav-dot"></span><span>Integrações</span></a><?php endif;?>
      </div>
    </details>

    <?php if(Auth::isSuperAdmin()):?>
    <details name="admin-menu" class="admin-nav-group" <?=in_array($adminPage,['site_admin','users','audit'],true)?'open':''?>>
      <summary>Controle Total</summary>
      <div class="admin-nav-group-links">
        <a href="index.php?p=site_admin" class="<?=$adminPage==='site_admin'?'active':''?>"><span class="nav-dot"></span><span>Editor Global do Site</span></a>
        <a href="index.php?p=users" class="<?=$adminPage==='users'?'active':''?>"><span class="nav-dot"></span><span>Usuários</span></a>
        <a href="index.php?p=audit" class="<?=$adminPage==='audit'?'active':''?>"><span class="nav-dot"></span><span>Auditoria</span></a>
        <a href="demo.php"><span class="nav-dot"></span><span>Conteúdo Demo</span></a>
        <a href="sincronizar_demo.php"><span class="nav-dot"></span><span>Restaurar Mídia Demo</span></a>
        <a href="diagnostico.php"><span class="nav-dot"></span><span>Diagnóstico</span></a>
      </div>
    </details>
    <?php endif;?>
  </nav>
</aside>

<div class="admin-shell">
  <header class="admin-topbar">
    <button type="button" class="admin-menu-toggle" data-admin-menu-toggle aria-label="Abrir menu">☰</button>
    <div class="admin-topbar-copy">
      <b><?=U::e($title??'Painel')?></b>
      <small><?=Auth::isSuperAdmin()?'Controle total da empresa':'Gestão da empresa'?></small>
    </div>
    <div class="admin-topbar-actions">
      <button type="button" id="suitesVoiceToggle" class="suites-voice-toggle" aria-expanded="false" aria-controls="suitesPanelVoice">Chat e voz</button>
      <a href="index.php" target="_blank" class="admin-view-site">Ver portal</a>
      <div class="admin-user">
        <span class="admin-user-avatar"><?=strtoupper(substr(Auth::user()['name']??'A',0,1))?></span>
        <span><b><?=U::e(Auth::user()['name']??'Administrador')?></b><small><?=U::e(Auth::user()['role']??'')?></small></span>
      </div>
      <a href="index.php?p=logout" class="admin-logout">Sair</a>
    </div>
  </header>
  <main class="admin-main">
<?php else: ?>
<?php
  $publicProp=null;
  try{$publicProp=\App\Core\DB::pdo()->query("SELECT name,city,state,phone,whatsapp,email,instagram,facebook,address,address_number,neighborhood,zip,hero_image,announcement_enabled,announcement_text,announcement_link_text,announcement_link_url FROM property WHERE id=1")->fetch();}catch(Throwable $e){}
  $publicName=trim((string)($siteSettings['site_name']??''))?:($publicProp['name']??'Pousada IA');
?>
<?php if(!isset($publicProp['announcement_enabled']) || (int)$publicProp['announcement_enabled']===1):?>
<div class="public-announcement"><?=U::e($publicProp['announcement_text']?:'Reserva direta • Consulte disponibilidade e condições no próprio site')?><?php if(!empty($publicProp['announcement_link_text']) && !empty($publicProp['announcement_link_url'])):?> · <a href="<?=U::e($publicProp['announcement_link_url'])?>"><?=U::e($publicProp['announcement_link_text'])?></a><?php endif;?></div>
<?php endif;?>
<header class="portal-header">
  <a class="portal-brand" href="index.php">
    <?php if($siteLogo):?><img class="portal-brand-logo" src="<?=U::e($siteLogo)?>" alt="<?=U::e($publicName)?>"><?php else:?><span class="portal-brand-mark">B</span><?php endif;?>
    <span><b><?=U::e($publicName)?></b><small><?=U::e($siteSettings['tagline']?:trim(($publicProp['city']??'').' · '.($publicProp['state']??''),' ·'))?></small></span>
  </a>
  <button class="mobile-menu-btn" type="button" aria-label="Abrir menu" data-menu-toggle>☰</button>
  <nav class="portal-nav" data-menu>
    <a href="index.php"><?=U::e(Site::ui('menu_home','Início'))?></a>
    <a href="index.php?p=about"><?=U::e(Site::ui('menu_about','A Pousada'))?></a>
    <a href="index.php?p=accommodations"><?=U::e(Site::ui('menu_accommodations','Acomodações'))?></a>
    <a href="index.php?p=gastronomy"><?=U::e(Site::ui('menu_gastronomy','Gastronomia'))?></a>
    <a href="index.php?p=things_to_do"><?=U::e(Site::ui('menu_experiences','Experiências'))?></a>
    <a href="index.php?p=promotions_public"><?=U::e(Site::ui('menu_offers','Ofertas'))?></a>
    <a href="index.php?p=contact"><?=U::e(Site::ui('menu_contact','Contato'))?></a>
  </nav>
  <div class="portal-header-actions">
    <a class="traveler-link" href="index.php?p=traveler"><?=U::e(Site::ui('traveler_label','Minha reserva'))?></a>
    <a class="btn" href="index.php?p=availability"><?=U::e(Site::ui('booking_cta','Reservar'))?></a>
  </div>
</header><main class="public-main portal-public">
<?php endif; ?>
<?php if($m=U::flash('ok')):?><div class="alert success"><?=U::e($m)?></div><?php endif;?>
<?php if($m=U::flash('err')):?><div class="alert error"><?=U::e($m)?></div><?php endif;?>
<?=$content??''?>
</main>
<?php if(Auth::user()): ?></div><?php endif;?>
<?php if(!Auth::user()): ?>
<footer class="portal-footer">
  <div class="footer-main">
    <div class="footer-brand">
      <div class="portal-brand footer-logo"><?php if($siteLogo):?><img class="portal-brand-logo" src="<?=U::e($siteLogo)?>" alt="<?=U::e($publicName)?>"><?php else:?><span class="portal-brand-mark">B</span><?php endif;?><span><b><?=U::e($publicName)?></b><small><?=U::e($publicProp['city']??'')?></small></span></div>
      <p><?=nl2br(U::e($siteSettings['footer_text']?:'Hospedagem, conforto e experiências para aproveitar cada momento da viagem.'))?></p>
      <div class="footer-social">
        <?php if(!empty($publicProp['instagram'])):?><span>Instagram: <?=U::e($publicProp['instagram'])?></span><?php endif;?>
        <?php if(!empty($publicProp['facebook'])):?><span>Facebook: <?=U::e($publicProp['facebook'])?></span><?php endif;?>
      </div>
    </div>
    <div><h4>Hospedagem</h4><a href="index.php?p=accommodations">Acomodações</a><a href="index.php?p=availability">Reservar</a><a href="index.php?p=promotions_public">Ofertas</a><a href="index.php?p=policies">Políticas</a></div>
    <div><h4>Experiência</h4><a href="index.php?p=about">A Pousada</a><a href="index.php?p=amenities_public">Estrutura</a><a href="index.php?p=gastronomy">Gastronomia</a><a href="index.php?p=things_to_do">O que fazer</a><a href="index.php?p=gallery_public">Galeria</a></div>
    <div><h4>Fale conosco</h4>
      <?php if(!empty($publicProp['whatsapp'])):?><p>WhatsApp<br><b><?=U::e($publicProp['whatsapp'])?></b></p><?php endif;?>
      <?php if(!empty($publicProp['email'])):?><p>E-mail<br><b><?=U::e($publicProp['email'])?></b></p><?php endif;?>
      <a href="index.php?p=location">Localização e rota</a><a href="index.php?p=faq">Perguntas frequentes</a>
    </div>
  </div>
  <div class="footer-bottom"><span><?=U::e(Site::ui('footer_bottom','Conteúdo e informações administrados pelo próprio estabelecimento.'))?></span><span><?=U::e($siteSettings['footer_legal_text']?:'Reservas e atendimento geridos pelo portal.')?></span></div>
</footer>
<?php endif; ?>

<?php
  $publicPage=$_GET['p']??'home';
  $showPublicChat=!Auth::user() && !in_array($publicPage,['login'],true) && !empty($siteSettings['chat_enabled']);
?>
<?php if($showPublicChat):?>
<div class="public-chat" data-public-chat>
  <button class="public-chat-launcher" type="button" data-chat-toggle aria-label="Abrir atendimento">
    <span class="public-chat-launcher-icon">💬</span>
    <span class="public-chat-launcher-copy"><b><?=U::e(Site::ui('chat_launcher_title','Fale conosco'))?></b><small><?=U::e(Site::ui('chat_launcher_subtitle','Atendimento pelo site'))?></small></span>
  </button>

  <section class="public-chat-panel" data-chat-panel hidden aria-label="Chat de atendimento">
    <header class="public-chat-head">
      <div><b data-chat-property><?=U::e($publicName)?></b><small><?=U::e($siteSettings['chat_agent_name']?:'Atendimento')?></small></div>
      <button type="button" data-chat-toggle class="public-chat-close" aria-label="Fechar chat">×</button>
    </header>

    <div class="public-chat-body" data-chat-body>
      <div class="public-chat-loading">Carregando atendimento…</div>
    </div>

    <div class="public-chat-error" data-chat-error hidden></div>
  </section>
</div>
<?php endif;?>
<?php if(!Auth::user() && !empty($siteSettings['custom_css'])):?><style><?=str_replace('</style','<\/style',(string)$siteSettings['custom_css'])?></style><?php endif;?><?php $jsV=@filemtime(__DIR__.'/../../assets/js/app.js')?:time(); ?><script src="assets/js/app.js?v=<?=$jsV?>"></script><?php if(Auth::user()): if(empty($_SESSION['_panel_voice_greeting']))$_SESSION['_panel_voice_greeting']=bin2hex(random_bytes(8)); ?>
<link rel="stylesheet" href="assets/css/panel-voice.css?v=20261105">
<section id="suitesPanelVoice" class="suites-voice-panel" data-greeting="<?=U::e($_SESSION['_panel_voice_greeting'])?>" hidden aria-label="Chat e voz">
  <header><strong>Chat e voz</strong><button type="button" data-voice-close aria-label="Fechar assistente">×</button></header>
  <?=\App\Core\Csrf::field()?>
  <p class="suites-voice-status" data-voice-status role="status" aria-live="polite">Consultando voz…</p>
  <div class="suites-voice-answer" data-voice-answer aria-live="polite"></div>
  <label>Mensagem<textarea maxlength="550" placeholder="Fale ou escreva sua pergunta"></textarea></label>
  <div class="suites-voice-actions"><button type="button" data-voice-send>Enviar</button><button type="button" data-voice-mic>Microfone</button><button type="button" data-voice-repeat>Repetir</button><button type="button" data-voice-stop>Parar</button><button type="button" data-voice-test>Testar voz</button></div>
</section>
<script defer src="assets/js/panel-voice.js?v=20261106"></script>
<?php endif;?>
</body></html>
