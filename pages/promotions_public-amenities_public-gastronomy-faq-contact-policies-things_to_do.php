<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $prop=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  if($p==='promotions_public'){
    $xs=$pdo->query("SELECT * FROM promotions WHERE active=1 AND (start_date IS NULL OR start_date<=CURDATE()) AND (end_date IS NULL OR end_date>=CURDATE()) ORDER BY id DESC")->fetchAll();
    $h='<h1>Tarifas e promoções</h1><div class=cards>';foreach($xs as $x)$h.='<article class=card><h2>'.U::e($x['title']).'</h2><p>'.nl2br(U::e($x['description'])).'</p>'.($x['code']?'<p>Código: <b>'.U::e($x['code']).'</b></p>':'').'</article>';render('Promoções',$h.'</div>');exit;
  }
  if($p==='amenities_public'){
    $q=$pdo->query("SELECT a.category,a.name FROM amenities a JOIN property_amenities pa ON pa.amenity_id=a.id WHERE pa.property_id=1 ORDER BY a.category,a.name");$xs=$q->fetchAll();
    $groups=[];foreach($xs as $x)$groups[$x['category']][]=$x['name'];
    $h='<h1>Estrutura e comodidades</h1><div class=card>';foreach($groups as $cat=>$items){$h.='<h2>'.U::e($cat).'</h2><div class=chips>';foreach($items as $name)$h.='<span>'.U::e($name).'</span>';$h.='</div>';}$h.='</div>';render('Comodidades',$h);exit;
  }
  if($p==='gastronomy'){
    $q=$pdo->prepare("SELECT * FROM contents WHERE slug='gastronomia' AND status='published' LIMIT 1");$q->execute();$c=$q->fetch();
    $q2=$pdo->query("SELECT a.name FROM amenities a JOIN property_amenities pa ON pa.amenity_id=a.id WHERE pa.property_id=1 AND a.name IN('Café da manhã','Restaurante','Bar','Serviço de quarto') ORDER BY a.name");$items=$q2->fetchAll(PDO::FETCH_COLUMN);
    $h='<h1>Gastronomia</h1><div class=card>';if($c)$h.='<h2>'.U::e($c['title']).'</h2><p>'.nl2br(U::e($c['body'])).'</p>';if($items){$h.='<div class=chips>';foreach($items as $i)$h.='<span>'.U::e($i).'</span>';$h.='</div>';}$h.='</div>';render($c['seo_title']??'Gastronomia',$h,$c['meta_description']??'');exit;
  }
  if($p==='faq'){
    $xs=$pdo->query("SELECT title,content FROM knowledge_base WHERE active=1 ORDER BY id DESC LIMIT 100")->fetchAll();$h='<h1>Perguntas frequentes</h1>';foreach($xs as $x)$h.='<details class=card><summary>'.U::e($x['title']).'</summary><p>'.nl2br(U::e($x['content'])).'</p></details>';render('Perguntas frequentes',$h);exit;
  }
  if($p==='contact'){
    $sent=false;
    if($_SERVER['REQUEST_METHOD']==='POST'){
      Csrf::check();
      $name=trim($_POST['name']??'');$email=trim($_POST['email']??'');$message=trim($_POST['message']??'');
      if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$message==='')throw new RuntimeException('Preencha nome, e-mail e mensagem corretamente.');
      $pdo->prepare("INSERT INTO contact_messages(name,email,phone,subject,message,status,created_at) VALUES(?,?,?,?,?,'new',NOW())")
        ->execute([$name,$email,trim($_POST['phone']??''),trim($_POST['subject']??''),$message]);
      $sent=true;
    }
    $h='<div class="public-page-hero"><span class=eyebrow>Contato</span><h1>Fale com a pousada</h1><p>Tire dúvidas, peça informações ou envie uma mensagem antes de reservar.</p></div>';
    if($sent)$h.='<div class="alert success contact-success">Mensagem enviada. A equipe poderá responder pelos dados informados.</div>';
    $h.='<section class="contact-layout"><div class=contact-info><h2>Informações</h2><p><b>Telefone</b><br>'.U::e($prop['phone']).'</p><p><b>WhatsApp</b><br>'.U::e($prop['whatsapp']).'</p><p><b>E-mail</b><br>'.U::e($prop['email']).'</p><p><b>Endereço</b><br>'.U::e(trim(($prop['address']??'').' '.($prop['address_number']??'').' - '.($prop['city']??''))).'</p></div>';
    $h.='<form method=post class="card contact-form">'.Csrf::field().'<div class=grid2>'.row('Nome','name').row('E-mail','email','', 'email').row('Telefone / WhatsApp','phone').row('Assunto','subject').'</div>'.row('Mensagem','message','', 'textarea').'<button>Enviar mensagem</button></form></section>';
    render('Contato',$h);exit;
  }
  if($p==='policies'){
    $h='<h1>Políticas da hospedagem</h1><div class=card><h2>Cancelamento</h2><p>'.nl2br(U::e($prop['cancellation_policy'])).'</p><h2>Check-in</h2><p>'.nl2br(U::e($prop['checkin_rules'])).'</p><h2>Check-out</h2><p>'.nl2br(U::e($prop['checkout_rules'])).'</p><h2>Convivência</h2><p>'.nl2br(U::e($prop['house_rules'])).'</p></div>';render('Políticas',$h);exit;
  }
  if($p==='things_to_do'){
    $xs=$pdo->query("SELECT * FROM attractions WHERE active=1 ORDER BY category,name")->fetchAll();$h='<h1>O que fazer na região</h1><div class=cards>';foreach($xs as $x)$h.='<article class=card>'.($x['photo']?'<img class=card-cover src="'.U::e(safeUrl($x['photo'])).'" alt="'.U::e($x['name']).'">':'').'<h2>'.U::e($x['name']).'</h2><small>'.U::e($x['category']).' · '.U::e($x['distance_text']).'</small><p>'.U::e($x['description']).'</p>'.($x['url']?'<a target=_blank rel=noopener href="'.U::e(safeUrl($x['url'])).'">Ver rota / detalhes</a>':'').'</article>';render('O que fazer',$h.'</div>');exit;
  }
