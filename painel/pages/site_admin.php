<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requireSuperAdmin();
  $upload=new UploadService();
  $site=Site::all(true);
  $ui=json_decode((string)($site['ui_json']??'{}'),true)?:[];

  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();
    $action=(string)($_POST['action']??'save');
    if(in_array($action,['remove_logo','remove_favicon'],true)){
      $field=$action==='remove_logo'?'logo_path':'favicon_path';
      $old=(string)($site[$field]??'');
      $pdo->prepare("UPDATE site_settings SET $field=NULL,updated_at=NOW() WHERE id=1")->execute();
      if($old!=='' && str_starts_with($old,'storage/uploads/site/')){ $full=dirname(__DIR__,2).'/'.$old; if(is_file($full))@unlink($full); }
      Site::resetCache();Audit::log('site.asset.remove','site_settings',1,['field'=>$field]);
      U::flash('ok',$field==='logo_path'?'Logomarca removida.':'Favicon removido.');U::redirect('index.php?p=site_admin');
    }

    $layout=in_array($_POST['layout_variant']??'layout1',['layout1','layout2','layout3','layout4'],true)?$_POST['layout_variant']:'layout1';
    $mode=in_array($_POST['chat_default_mode']??'hybrid',['human','hybrid','ai'],true)?$_POST['chat_default_mode']:'hybrid';
    $color=function(string $name,string $fallback):string{$v=trim((string)($_POST[$name]??''));return preg_match('/^#[0-9a-fA-F]{6}$/',$v)?strtolower($v):$fallback;};
    $fonts=['Inter','Arial','Verdana','Tahoma','Trebuchet MS','Georgia','Times New Roman','system-ui'];
    $bodyFont=in_array($_POST['body_font']??'Inter',$fonts,true)?$_POST['body_font']:'Inter';
    $headingFont=in_array($_POST['heading_font']??'Georgia',$fonts,true)?$_POST['heading_font']:'Georgia';

    $logo=(string)($site['logo_path']??'');
    $favicon=(string)($site['favicon_path']??'');
    if(!empty($_FILES['logo_file']['name'])){$new=$upload->image($_FILES['logo_file'],'site');if($new){if($logo&&str_starts_with($logo,'storage/uploads/site/')&&is_file(dirname(__DIR__,2).'/'.$logo))@unlink(dirname(__DIR__,2).'/'.$logo);$logo=$new;}}
    if(!empty($_FILES['favicon_file']['name'])){$new=$upload->image($_FILES['favicon_file'],'site');if($new){if($favicon&&str_starts_with($favicon,'storage/uploads/site/')&&is_file(dirname(__DIR__,2).'/'.$favicon))@unlink(dirname(__DIR__,2).'/'.$favicon);$favicon=$new;}}

    $uiKeys=['menu_home','menu_about','menu_accommodations','menu_gastronomy','menu_experiences','menu_offers','menu_contact','booking_cta','traveler_label','hero_kicker','hero_title','hero_description','hero_primary_cta','hero_secondary_cta','hero_search_cta','intro_eyebrow','intro_title','footer_bottom','chat_launcher_title','chat_launcher_subtitle'];
    $newUi=$ui;foreach($uiKeys as $k)$newUi[$k]=trim((string)($_POST['ui'][$k]??''));

    $sql="INSERT INTO site_settings(id,site_name,tagline,logo_path,favicon_path,layout_variant,primary_color,secondary_color,accent_color,body_font,heading_font,chat_enabled,chat_agent_name,chat_default_mode,chat_welcome,footer_text,footer_legal_text,ui_json,custom_css,updated_at)
          VALUES(1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
          ON DUPLICATE KEY UPDATE site_name=VALUES(site_name),tagline=VALUES(tagline),logo_path=VALUES(logo_path),favicon_path=VALUES(favicon_path),layout_variant=VALUES(layout_variant),primary_color=VALUES(primary_color),secondary_color=VALUES(secondary_color),accent_color=VALUES(accent_color),body_font=VALUES(body_font),heading_font=VALUES(heading_font),chat_enabled=VALUES(chat_enabled),chat_agent_name=VALUES(chat_agent_name),chat_default_mode=VALUES(chat_default_mode),chat_welcome=VALUES(chat_welcome),footer_text=VALUES(footer_text),footer_legal_text=VALUES(footer_legal_text),ui_json=VALUES(ui_json),custom_css=VALUES(custom_css),updated_at=NOW()";
    $pdo->prepare($sql)->execute([
      trim((string)($_POST['site_name']??'')),trim((string)($_POST['tagline']??'')),$logo?:null,$favicon?:null,$layout,
      $color('primary_color','#0d756d'),$color('secondary_color','#173f3b'),$color('accent_color','#bf8d39'),$bodyFont,$headingFont,
      isset($_POST['chat_enabled'])?1:0,trim((string)($_POST['chat_agent_name']??'Assistente IA')),$mode,trim((string)($_POST['chat_welcome']??'')),
      trim((string)($_POST['footer_text']??'')),trim((string)($_POST['footer_legal_text']??'')),json_encode($newUi,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),trim((string)($_POST['custom_css']??''))
    ]);
    Site::resetCache();Audit::log('site.settings.update','site_settings',1,['layout'=>$layout,'chat_mode'=>$mode]);U::flash('ok','Configurações globais do site salvas.');U::redirect('index.php?p=site_admin');
  }

  $site=Site::all(true);$ui=json_decode((string)($site['ui_json']??'{}'),true)?:[];
  $fontOptions=function(string $selected):string{$opts='';foreach(['Inter','Arial','Verdana','Tahoma','Trebuchet MS','Georgia','Times New Roman','system-ui'] as $f)$opts.='<option value="'.U::e($f).'" '.($selected===$f?'selected':'').'>'.U::e($f).'</option>';return $opts;};
  $h=adminTitle('Super Admin · Editor Global do Site','Controle de logomarca, identidade visual, quatro layouts, textos principais, agente de IA e atalhos de conteúdo.');
  $h.='<div class="site-admin-shortcuts"><a class="card" href="index.php?p=property"><b>Informações gerais</b><span>Nome, descrições, contato, localização e políticas</span></a><a class="card" href="index.php?p=hero"><b>Hero / capa</b><span>Fotos, vídeos e ordem da capa</span></a><a class="card" href="index.php?p=content"><b>Conteúdo / SEO</b><span>Páginas e textos longos</span></a><a class="card" href="index.php?p=gallery"><b>Imagens</b><span>Galeria e mídias das acomodações</span></a><a class="card" href="index.php?p=ai"><b>Agente IA</b><span>Modelo, chave e base de conhecimento</span></a><a class="card" href="index.php?p=attendance_settings"><b>Atendimento IA</b><span>Coleta de dados e políticas de reserva</span></a><a class="card" href="index.php?p=whatsapp_rnl"><b>WhatsApp VPS</b><span>Servidor, sessão, QR e integração operacional</span></a></div>';
  $h.='<form method="post" enctype="multipart/form-data" class="card site-global-form">'.Csrf::field().'<input type=hidden name=action value=save>';
  $h.='<section class=form-section><h2>1. Marca e identidade</h2><div class=grid2>'.row('Nome exibido do site','site_name',$site['site_name']).row('Slogan / linha curta','tagline',$site['tagline']).'<label>Logomarca (PNG/JPG/WebP)<input type=file name=logo_file accept="image/jpeg,image/png,image/webp"></label><label>Favicon (PNG/JPG/WebP)<input type=file name=favicon_file accept="image/jpeg,image/png,image/webp"></label></div>';
  if(!empty($site['logo_path']))$h.='<div class=site-asset-preview><img src="'.U::e(safeUrl($site['logo_path'])).'" alt="Logo atual"><button form="remove-logo-form" type=submit class="danger small">Remover logomarca</button></div>';
  if(!empty($site['favicon_path']))$h.='<div class=site-asset-preview favicon><img src="'.U::e(safeUrl($site['favicon_path'])).'" alt="Favicon atual"><button form="remove-favicon-form" type=submit class="danger small">Remover favicon</button></div>';
  $h.='</section>';
  $layouts=['layout1'=>['Boutique clássico','Editorial elegante, menu claro e cartões tradicionais.'],'layout2'=>['Minimalista moderno','Mais espaço em branco, tipografia limpa e hero centralizado.'],'layout3'=>['Premium escuro','Cabeçalho escuro, contraste alto e cartões sofisticados.'],'layout4'=>['Tropical clean','Visual leve, bordas suaves e áreas mais orgânicas.']];
  $h.='<section class=form-section><h2>2. Layout do portal</h2><div class=layout-choice-grid>';foreach($layouts as $key=>$info)$h.='<label class="layout-choice '.(($site['layout_variant']??'layout1')===$key?'selected':'').'"><input type=radio name=layout_variant value="'.$key.'" '.(($site['layout_variant']??'layout1')===$key?'checked':'').'><span class="layout-mini '.$key.'"><i></i><i></i><i></i></span><b>'.$info[0].'</b><small>'.$info[1].'</small></label>';$h.='</div><div class=grid3><label>Cor principal<input type=color name=primary_color value="'.U::e($site['primary_color']).'"></label><label>Cor secundária<input type=color name=secondary_color value="'.U::e($site['secondary_color']).'"></label><label>Cor de destaque<input type=color name=accent_color value="'.U::e($site['accent_color']).'"></label><label>Fonte do corpo<select name=body_font>'.$fontOptions((string)$site['body_font']).'</select></label><label>Fonte dos títulos<select name=heading_font>'.$fontOptions((string)$site['heading_font']).'</select></label></div></section>';
  $val=fn(string $k,string $d='')=>(string)($ui[$k]??$d);
  $h.='<section class=form-section><h2>3. Textos principais do portal</h2><div class=grid3>'.row('Menu · Início','ui[menu_home]',$val('menu_home','Início')).row('Menu · A pousada','ui[menu_about]',$val('menu_about','A Pousada')).row('Menu · Acomodações','ui[menu_accommodations]',$val('menu_accommodations','Acomodações')).row('Menu · Gastronomia','ui[menu_gastronomy]',$val('menu_gastronomy','Gastronomia')).row('Menu · Experiências','ui[menu_experiences]',$val('menu_experiences','Experiências')).row('Menu · Ofertas','ui[menu_offers]',$val('menu_offers','Ofertas')).row('Menu · Contato','ui[menu_contact]',$val('menu_contact','Contato')).row('Botão reservar','ui[booking_cta]',$val('booking_cta','Reservar')).row('Minha reserva','ui[traveler_label]',$val('traveler_label','Minha reserva')).'</div><div class=grid2>'.row('Hero · linha superior','ui[hero_kicker]',$val('hero_kicker','')).row('Hero · título opcional','ui[hero_title]',$val('hero_title','')).row('Hero · descrição opcional','ui[hero_description]',$val('hero_description',''),'textarea').row('Hero · botão principal','ui[hero_primary_cta]',$val('hero_primary_cta','Reservar agora')).row('Hero · botão secundário','ui[hero_secondary_cta]',$val('hero_secondary_cta','Conhecer a pousada')).row('Busca · botão disponibilidade','ui[hero_search_cta]',$val('hero_search_cta','Buscar disponibilidade')).row('Introdução · etiqueta','ui[intro_eyebrow]',$val('intro_eyebrow','Bem-vindo')).row('Introdução · título opcional','ui[intro_title]',$val('intro_title','')).row('Rodapé · linha inferior','ui[footer_bottom]',$val('footer_bottom','')).'</div>'.row('Texto institucional do rodapé','footer_text',$site['footer_text'],'textarea').row('Texto legal adicional','footer_legal_text',$site['footer_legal_text'],'textarea').'</section>';
  $h.='<section class=form-section><h2>4. Agente de IA no atendimento</h2><div class=grid2>'.row('Ativar chat público','chat_enabled',$site['chat_enabled'],'checkbox').row('Nome do agente','chat_agent_name',$site['chat_agent_name']).'<label>Modo padrão<select name=chat_default_mode><option value=human '.($site['chat_default_mode']==='human'?'selected':'').'>Humano</option><option value=hybrid '.($site['chat_default_mode']==='hybrid'?'selected':'').'>Híbrido</option><option value=ai '.($site['chat_default_mode']==='ai'?'selected':'').'>IA</option></select></label>'.row('Título do botão do chat','ui[chat_launcher_title]',$val('chat_launcher_title','Fale conosco')).row('Subtítulo do botão do chat','ui[chat_launcher_subtitle]',$val('chat_launcher_subtitle','Atendimento pelo site')).'</div>'.row('Mensagem inicial do agente','chat_welcome',$site['chat_welcome'],'textarea').'<p class=muted>Você pode usar {nome}, {pousada} e {agente}. Em modo Híbrido, a IA responde até um atendente assumir a conversa.</p></section>';
  $h.='<section class=form-section><h2>5. CSS avançado (opcional)</h2><p class=muted>Use apenas quando precisar de um ajuste visual específico. Os quatro layouts funcionam sem CSS adicional.</p>'.row('CSS personalizado','custom_css',$site['custom_css'],'textarea').'</section><button>Salvar todas as configurações do site</button></form>';
  $h.='<form id=remove-logo-form method=post class=hidden-form>'.Csrf::field().'<input type=hidden name=action value=remove_logo></form><form id=remove-favicon-form method=post class=hidden-form>'.Csrf::field().'<input type=hidden name=action value=remove_favicon></form>';
  render('Editor Global do Site',$h);exit;
