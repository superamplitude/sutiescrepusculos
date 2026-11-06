<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $prop=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  
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
  