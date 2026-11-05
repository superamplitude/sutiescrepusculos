<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $prop=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  $site=Site::all();
  $rooms=$pdo->query("SELECT * FROM rooms WHERE active=1 AND status='active' ORDER BY base_price,name LIMIT 6")->fetchAll();
  $promos=$pdo->query("SELECT * FROM promotions WHERE active=1 AND (start_date IS NULL OR start_date<=CURDATE()) AND (end_date IS NULL OR end_date>=CURDATE()) ORDER BY id DESC LIMIT 3")->fetchAll();
  $attractions=$pdo->query("SELECT * FROM attractions WHERE active=1 ORDER BY id DESC LIMIT 4")->fetchAll();
  $reviews=$pdo->query("SELECT rv.*,g.name guest FROM reviews rv JOIN guests g ON g.id=rv.guest_id WHERE rv.status='published' ORDER BY rv.id DESC LIMIT 4")->fetchAll();
  $amenities=$pdo->query("SELECT a.name FROM amenities a JOIN property_amenities pa ON pa.amenity_id=a.id WHERE pa.property_id=1 ORDER BY FIELD(a.name,'Café da manhã','Piscina','Wi-Fi','Estacionamento','Jardim','Transfer'),a.name LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
  $faqs=$pdo->query("SELECT title,content FROM knowledge_base WHERE active=1 ORDER BY id DESC LIMIT 5")->fetchAll();

  $name=trim((string)($site['site_name']??''))?:($prop['name']?:'Pousada');
  $desc=Site::ui('hero_description',$prop['short_description']?:'Reserve sua hospedagem diretamente.');
  $city=trim((string)($prop['city']??''))?:'sua região';
  $isDemo=trim((string)($prop['name']??''))==='Pousada Brisa do Mar';
  $propertyImages=$pdo->query("SELECT file_path,url,alt_text FROM media WHERE scope='property' AND entity_id=1 AND active=1 AND type='image' ORDER BY is_cover DESC,sort_order,id LIMIT 5")->fetchAll();
  $propertyImagePaths=[];foreach($propertyImages as $pi){$src=safeUrl($pi['file_path']?:$pi['url']);if($src)$propertyImagePaths[]=$src;}
  $qFam=$pdo->query("SELECT cover_image FROM rooms WHERE active=1 AND status='active' AND capacity>=4 AND cover_image IS NOT NULL AND cover_image<>'' ORDER BY capacity DESC,id LIMIT 1");
  $familyImage=safeUrl((string)($qFam->fetchColumn()?:''));
  $qCouple=$pdo->query("SELECT cover_image FROM rooms WHERE active=1 AND status='active' AND capacity<=2 AND cover_image IS NOT NULL AND cover_image<>'' ORDER BY base_price DESC,id LIMIT 1");
  $coupleImage=safeUrl((string)($qCouple->fetchColumn()?:''));
  $lifestyleImage=$propertyImagePaths[1]??$propertyImagePaths[0]??safeUrl((string)($prop['hero_image']??''));
  if($isDemo){
    if(!$familyImage)$familyImage='assets/demo/real/apartamento-familia.jpg';
    if(!$coupleImage)$coupleImage='assets/demo/real/chale-romance.jpg';
    if(!$lifestyleImage)$lifestyleImage='assets/demo/real/piscina.jpg';
  }
  $familyImg=$familyImage?'<img src="'.U::e($familyImage).'" alt="Acomodações para famílias">':'';
  $coupleImg=$coupleImage?'<img src="'.U::e($coupleImage).'" alt="Acomodações para casais">':'';
  $lifestyleImg=$lifestyleImage?'<div class="lifestyle-image"><img src="'.U::e($lifestyleImage).'" alt="Área da pousada"></div>':'';
  $heroClass='portal-hero';
  $heroMediaItems=$pdo->query("SELECT type,file_path,url,caption,alt_text FROM media WHERE scope='property' AND entity_id=1 AND active=1 AND type IN('image','video') ORDER BY is_cover DESC,sort_order,id LIMIT 8")->fetchAll();

  // O hero público é controlado pelo Editor do Hero no painel.
  if(!$heroMediaItems){
    if(!empty($prop['hero_video'])||!empty($prop['hero_video_url']))$heroMediaItems[]=['type'=>'video','file_path'=>$prop['hero_video'],'url'=>$prop['hero_video_url'],'caption'=>$name,'alt_text'=>$name];
    if(!empty($prop['hero_image']))$heroMediaItems[]=['type'=>'image','file_path'=>$prop['hero_image'],'url'=>'','caption'=>$name,'alt_text'=>$name];
  }
  $media='<div class="portal-hero-slider" data-hero-slider>';
  $heroDots='';
  foreach($heroMediaItems as $i=>$item){
    $rawSrc=$item['file_path']?:$item['url'];
    $src=safeUrl($rawSrc);
    if(!$src)continue;
    if(str_starts_with($src,'assets/demo/') && is_file(dirname(__DIR__).'/'.$src))$src.='?v='.filemtime(dirname(__DIR__).'/'.$rawSrc);
    $active=$i===0?' active':'';
    $media.='<div class="hero-slide'.$active.'" data-hero-slide>';
    if(($item['type']??'image')==='video'){
      $poster=!empty($prop['hero_poster'])?' poster="'.U::e(safeUrl($prop['hero_poster'])).'"':'';
      $media.='<video class="portal-hero-media" autoplay muted loop playsinline'.$poster.' src="'.U::e($src).'"></video>';
    }else{
      $media.='<img class="portal-hero-media" src="'.U::e($src).'" alt="'.U::e($item['alt_text']?:$name).'">';
    }
    $media.='</div>';
    $heroDots.='<button type="button" class="hero-dot'.$active.'" data-hero-dot aria-label="Slide '.($i+1).'"></button>';
  }
  $media.='</div>';
  if($heroDots!=='')$media.='<div class="hero-dots">'.$heroDots.'</div>';

  $heroKicker=Site::ui('hero_kicker','Hospedagem em '.$city);$heroTitle=Site::ui('hero_title',$name);$heroPrimary=Site::ui('hero_primary_cta','Reservar agora');$heroSecondary=Site::ui('hero_secondary_cta','Conhecer a pousada');
  $h='<section class="'.$heroClass.'">'.$media.'<div class="portal-hero-shade"></div><div class="portal-hero-content hero-copy-clean"><div class="portal-kicker">'.U::e($heroKicker).'</div><h1>'.U::e($heroTitle).'</h1><p>'.U::e($desc).'</p><div class="hero-actions"><a class="btn light" href="index.php?p=availability">'.U::e($heroPrimary).'</a><a class="btn ghost" href="index.php?p=about">'.U::e($heroSecondary).'</a></div></div>';
  $h.='<form method="post" action="index.php?p=availability" class="booking-bar">'.Csrf::field().'<div><span>Entrada</span><input type="date" name="checkin" required></div><div><span>Saída</span><input type="date" name="checkout" required></div><div><span>Adultos</span><input type="number" min="1" name="adults" value="2" required></div><div><span>Crianças</span><input type="number" min="0" name="children" value="0"></div><button>'.U::e(Site::ui('hero_search_cta','Buscar disponibilidade')).'</button></form></section>';

  $introEyebrow=Site::ui('intro_eyebrow','Bem-vindo');$introTitle=Site::ui('intro_title','Uma pousada para descansar e viver '.$city);
  $h.='<section class="portal-intro"><div><span class="eyebrow">'.U::e($introEyebrow).'</span><h2>'.U::e($introTitle).'</h2><p>'.nl2br(U::e($prop['description'])).'</p><a class="text-link" href="index.php?p=about">Conheça nossa história →</a></div><div class="feature-list">';
  foreach(array_slice($amenities,0,6) as $a)$h.='<div class="feature-item"><span>✓</span><b>'.U::e($a).'</b></div>';
  $h.='</div></section>';

  $h.='<section class="stay-profiles"><a href="index.php?p=availability" class="stay-profile family">'.$familyImg.'<div><span>Viaje em família</span><h2>Espaço para todos ficarem à vontade</h2><p>Suítes e apartamentos para famílias, com estrutura pensada para uma rotina prática durante a viagem.</p><b>Encontrar acomodação →</b></div></a><a href="index.php?p=availability" class="stay-profile couple">'.$coupleImg.'<div><span>Escape a dois</span><h2>Conforto e atmosfera tranquila para casais</h2><p>Escolha acomodações com mais privacidade e aproveite uma estadia especial em '.U::e($city).'.</p><b>Ver opções para casais →</b></div></a></section>';

  $h.='<section class="portal-section"><div class="section-head portal-title"><div><span class="eyebrow">Acomodações</span><h2>Escolha o seu espaço</h2></div><a href="index.php?p=accommodations">Ver todas as acomodações</a></div><div class="room-grid">';
  foreach($rooms as $r){
    $img=$r['cover_image']?'<img src="'.U::e(safeUrl($r['cover_image'])).'" alt="'.U::e($r['name']).'">':'';
    $h.='<article class="public-room-card">'.$img.'<div class="public-room-body"><div class="room-meta"><span>'.U::e($r['type']).'</span><span>até '.U::e($r['capacity']).' hóspedes</span></div><h3>'.U::e($r['name']).'</h3><p>'.U::e($r['short_description']).'</p><div class="room-price"><span>a partir de</span><b>'.money((float)$r['base_price']).'</b><small>/ diária</small></div><a class="btn" href="index.php?p=room&id='.$r['id'].'">Ver acomodação</a></div></article>';
  }
  $h.='</div></section>';

  if($promos){
    $h.='<section class="portal-section promo-wrap"><div class="portal-title"><span class="eyebrow">Ofertas especiais</span><h2>Reserve direto e aproveite condições exclusivas</h2></div><div class="promo-grid">';
    foreach($promos as $pr)$h.='<article class="promo-card"><span class="promo-code">'.U::e($pr['code']).'</span><h3>'.U::e($pr['title']).'</h3><p>'.U::e($pr['description']).'</p><a href="index.php?p=promotions_public">Ver condição</a></article>';
    $h.='</div></section>';
  }

  $h.='<section class="portal-section amenities-showcase"><div><span class="eyebrow">Conforto</span><h2>Tudo para uma estadia tranquila</h2><p>Estrutura pensada para você aproveitar '.U::e($city).' com praticidade.</p><a class="text-link" href="index.php?p=amenities_public">Ver todas as comodidades →</a></div><div class="amenity-cloud">';
  foreach($amenities as $a)$h.='<span>'.U::e($a).'</span>';
  $h.='</div></section>';

  $h.='<section class="lifestyle-section '.($lifestyleImg?'':'no-image').'">'.$lifestyleImg.'<div class="lifestyle-copy"><span class="eyebrow">Mais do que hospedagem</span><h2>Uma estadia tranquila e '.U::e($city).' para explorar</h2><p>Aproveite a estrutura da pousada e monte seu roteiro de acordo com as experiências da região.</p><div class="lifestyle-links"><a href="index.php?p=gastronomy">Nossa gastronomia</a><a href="index.php?p=things_to_do">Explorar experiências</a><a href="index.php?p=location">Ver localização</a></div></div></section>';

  if($attractions){
    $h.='<section class="portal-section"><div class="section-head portal-title"><div><span class="eyebrow">Experiências</span><h2>'.U::e($city).' começa logo ali</h2></div><a href="index.php?p=things_to_do">Ver roteiro completo</a></div><div class="experience-grid">';
    foreach($attractions as $a)$h.='<article class="experience-card">'.($a['photo']?'<img src="'.U::e(safeUrl($a['photo'])).'" alt="'.U::e($a['name']).'">':'').'<div><span>'.U::e($a['category']).' · '.U::e($a['distance_text']).'</span><h3>'.U::e($a['name']).'</h3><p>'.U::e($a['description']).'</p></div></article>';
    $h.='</div></section>';
  }

  if($reviews){
    $avg=(float)$pdo->query("SELECT COALESCE(AVG(rating),0) FROM reviews WHERE status='published'")->fetchColumn();
    $h.='<section class="portal-section reviews-section"><div class="portal-title"><span class="eyebrow">Quem já ficou</span><h2>Avaliações dos hóspedes</h2><p class="rating-big">'.number_format($avg,1,',','.').' / 5</p></div><div class="review-grid">';
    foreach($reviews as $rv)$h.='<blockquote class="review-card"><div class="stars">'.str_repeat('★',(int)$rv['rating']).'</div><p>“'.U::e($rv['comment']).'”</p><footer>'.U::e($rv['guest']).'</footer></blockquote>';
    $h.='</div></section>';
  }

  if($faqs){
    $h.='<section class="portal-section faq-home"><div class="portal-title"><span class="eyebrow">Dúvidas frequentes</span><h2>Antes de reservar</h2></div><div>';
    foreach($faqs as $f)$h.='<details><summary>'.U::e($f['title']).'</summary><p>'.U::e($f['content']).'</p></details>';
    $h.='<a class="text-link" href="index.php?p=faq">Ver todas as perguntas →</a></div></section>';
  }

  $h.='<section class="portal-cta"><div><span class="eyebrow">Reserve direto</span><h2>Pronto para viver '.U::e($city).'?</h2><p>Consulte as datas disponíveis e escolha a acomodação ideal.</p></div><a class="btn light" href="index.php?p=availability">Ver disponibilidade</a></section>';

  render($name,$h,$desc);exit;
