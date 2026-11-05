<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requireSuperAdmin();
  $allowedRoles=['admin','gerente','recepcao','reservas','financeiro','marketing'];
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();
    $name=trim((string)($_POST['name']??''));
    $email=trim((string)($_POST['email']??''));
    $password=(string)($_POST['password']??'');
    $password2=(string)($_POST['password_confirm']??'');
    $role=in_array($_POST['role']??'admin',$allowedRoles,true)?(string)$_POST['role']:'admin';
    if($name===''||$email===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){
      U::flash('err','Informe nome e e-mail válido.');U::redirect('index.php?p=users');
    }
    if(strlen($password)<10){U::flash('err','A senha deve ter pelo menos 10 caracteres.');U::redirect('index.php?p=users');}
    if($password!==$password2){U::flash('err','As senhas não conferem.');U::redirect('index.php?p=users');}
    $dupe=$pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");$dupe->execute([$email]);
    if($dupe->fetchColumn()){U::flash('err','Já existe um usuário com este e-mail.');U::redirect('index.php?p=users');}
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users(name,email,password_hash,role,active,created_at,updated_at) VALUES(?,?,?,?,1,NOW(),NOW())")
      ->execute([$name,$email,$hash,$role]);
    Audit::log('user.create','user',(int)$pdo->lastInsertId(),['role'=>$role]);
    U::flash('ok','Usuário criado com sucesso.');
    U::redirect('index.php?p=users');
  }
  $us=$pdo->query("SELECT id,name,email,role,active,last_login_at FROM users ORDER BY FIELD(role,'super_admin','admin','gerente','recepcao','reservas','financeiro','marketing'),name")->fetchAll();
  $h=adminTitle('Usuários e Perfis','O Super Admin pode criar, editar, ativar/desativar usuários e redefinir senhas.');
  $h.='<form method=post class=card>'.Csrf::field().'<h2>Novo usuário</h2><div class=grid3>'.row('Nome','name').row('E-mail','email','', 'email').row('Senha (mínimo 10 caracteres)','password','', 'password').row('Confirmar senha','password_confirm','', 'password').'<label>Perfil<select name=role><option value=admin>Admin</option><option value=gerente>Gerente</option><option value=recepcao>Recepção</option><option value=reservas>Reservas</option><option value=financeiro>Financeiro</option><option value=marketing>Marketing</option></select></label></div><button>Criar usuário</button></form><div class=card><table><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Último acesso</th><th>Ações</th></tr>';
  foreach($us as $u){
    $roleLabel=$u['role']==='super_admin'?'Super Admin':ucfirst(str_replace('_',' ',$u['role']));
    $status=(int)$u['active']===1?'Ativo':'Inativo';
    $h.='<tr><td>'.U::e($u['name']).'</td><td>'.U::e($u['email']).'</td><td>'.U::e($roleLabel).'</td><td>'.U::e($status).'</td><td>'.U::e($u['last_login_at']?:'—').'</td><td><a class="btn secondary" href="index.php?p=user_edit&id='.(int)$u['id'].'">Editar / senha</a></td></tr>';
  }
  render('Usuários',$h.'</table></div>');exit;
