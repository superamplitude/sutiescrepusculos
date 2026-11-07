<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$now=time();$attempts=$_SESSION['_login_attempts']??['count'=>0,'until'=>0];
    if(($attempts['until']??0)>$now){U::flash('err','Muitas tentativas. Aguarde um minuto e tente novamente.');}
    else{
      $s=$pdo->prepare("SELECT * FROM users WHERE email=? AND active=1");$s->execute([trim($_POST['email']??'')]);$u=$s->fetch();
      if($u&&password_verify($_POST['password']??'',$u['password_hash'])){
        unset($_SESSION['_login_attempts']);$perms=[];$r=$pdo->prepare("SELECT permissions_json FROM roles WHERE name=?");$r->execute([$u['role']]);$j=$r->fetchColumn();if($j)$perms=json_decode($j,true)?:[];
        Auth::login(['id'=>$u['id'],'company_id'=>(int)($u['company_id']??1),'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role'],'permissions'=>$perms]);$pdo->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$u['id']]);Audit::log('login');U::redirect('/painel/?p=dashboard');
      }
      $count=(int)($attempts['count']??0)+1;$_SESSION['_login_attempts']=['count'=>$count,'until'=>$count>=5?$now+60:0];U::flash('err','E-mail ou senha inválidos.');
    }
  }
  render('Login',adminTitle('Acesso administrativo','Entre com sua conta.').'<form method="post" class="card narrow">'.Csrf::field().row('E-mail','email','', 'email').row('Senha','password','', 'password').'<button>Entrar</button></form>');exit;
