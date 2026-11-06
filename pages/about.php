<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $x=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  
    $isDemoAbout=trim((string)($x['name']??''))==='Pousada Brisa do Mar';
    $aboutMedia=$pdo->query("SELECT file_path,url FROM media WHERE scope='property' AND entity_id=1 AND active=1 AND type='image' ORDER BY is_cover DESC,sort_order,id LIMIT 3")->fetchAll();
    $aboutPaths=[];foreach($aboutMedia as $m){$src=safeUrl($m['file_path']?:$m['url']);if($src)$aboutPaths[]=$src;}
    if(!$aboutPaths && !empty($x['hero_image'])){$src=safeUrl($x['hero_image']);if($src)$aboutPaths[]=$src;}
    if(!$aboutPaths && $isDemoAbout)$aboutPaths[]='assets/demo/real/piscina.jpg';
    $aboutImg1=$aboutPaths[0]??'';$aboutImg2=$aboutPaths[1]??$aboutImg1;
    $aboutPhoto1=$aboutImg1?'<div class="editorial-photo"><img src="'.U::e($aboutImg1).'" alt="Área da pousada"></div>':'';
    $aboutPhoto2=$aboutImg2?'<div class="editorial-photo"><img src="'.U::e($aboutImg2).'" alt="Estrutura da pousada"></div>':'';
    $h='<div class="public-page-hero"><span class=eyebrow>A pousada</span><h1>'.U::e($x['name']?:'Pousada').'</h1><p>'.U::e($x['short_description']).'</p></div>';
    $h.='<section class="public-editorial-grid '.($aboutPhoto1?'':'no-image').'"><div><span class=eyebrow>Nossa história</span><h2>Receber bem faz parte da viagem</h2><p>'.nl2br(U::e($x['history'])).'</p></div>'.$aboutPhoto1.'</section>';
    $h.='<section class="public-editorial-grid reverse '.($aboutPhoto2?'':'no-image').'"><div><span class=eyebrow>Sobre</span><h2>Conforto, localização e atendimento simples</h2><p>'.nl2br(U::e($x['concept'])).'</p><p>'.nl2br(U::e($x['region_info'])).'</p></div>'.$aboutPhoto2.'</section>';
    $h.='<section class="portal-section"><div class=portal-title><span class=eyebrow>Para quem é</span><h2>Uma hospedagem para diferentes formas de viajar</h2></div><div class=cards><div class=card><h3>Casais</h3><p>Suítes confortáveis, opções com varanda e clima tranquilo para uma viagem a dois.</p></div><div class=card><h3>Famílias</h3><p>Acomodações para até cinco hóspedes, piscina e estrutura prática para viajar com crianças.</p></div><div class=card><h3>Fim de semana</h3><p>Localização conveniente para aproveitar Centro Histórico, praias, gastronomia e passeios.</p></div></div></section>';
    render('A Pousada',$h,$x['short_description']?:'');exit;
  