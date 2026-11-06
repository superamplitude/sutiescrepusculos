<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requireSuperAdmin();
  $id=(int)($_GET['id']??0);
  $q=$pdo->prepare("SELECT id,name,email,role,active,last_login_at FROM users WHERE id=? LIMIT 1");$q->execute([$id]);$user=$q->fetch();
  if(!$user){U::flash('err','Usuário não encontrado.');U::redirect('index.php?p=users');}
  $me=Auth::user();$isSelf=(int)($me['id']??0)===$id;
  $allowedRoles=['super_admin','admin','gerente','recepcao','reservas','financeiro','marketing'];
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();
    $name=trim((string)($_POST['name']??''));
    $email=trim((string)($_POST['email']??''));
    $role=in_array($_POST['role']??$user['role'],$allowedRoles,true)?(string)($_POST['role']??$user['role']):(string)$user['role'];
    $active=isset($_POST['active'])?1:0;
    $password=(string)($_POST['password']??'');
    $password2=(string)($_POST['password_confirm']??'');
    if($name===''||$email===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){
      U::flash('err','Informe nome e e-mail válido.');U::redirect('index.php?p=user_edit&id='.$id);
    }
    if($isSelf){$role='super_admin';$active=1;}
    if($user['role']==='super_admin'&&(int)$user['active']===1&&($role!=='super_admin'||$active!==1)){
      $activeSupers=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin' AND active=1")->fetchColumn();
      if($activeSupers<=1){U::flash('err','Não é permitido desativar ou rebaixar o último Super Admin ativo.');U::redirect('index.php?p=user_edit&id='.$id);}
    }
    $dupe=$pdo->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");$dupe->execute([$email,$id]);
    if($dupe->fetchColumn()){U::flash('err','Já existe outro usuário com este e-mail.');U::redirect('index.php?p=user_edit&id='.$id);}
    $changePassword=$password!==''||$password2!=='';
    if($changePassword){
      if(strlen($password)<10){U::flash('err','A nova senha deve ter pelo menos 10 caracteres.');U::redirect('index.php?p=user_edit&id='.$id);}
      if($password!==$password2){U::flash('err','A nova senha e a confirmação não conferem.');U::redirect('index.php?p=user_edit&id='.$id);}
      $hash=password_hash($password,PASSWORD_DEFAULT);
      $pdo->prepare("UPDATE users SET name=?,email=?,role=?,active=?,password_hash=?,updated_at=NOW() WHERE id=?")
        ->execute([$name,$email,$role,$active,$hash,$id]);
    }else{
      $pdo->prepare("UPDATE users SET name=?,email=?,role=?,active=?,updated_at=NOW() WHERE id=?")
        ->execute([$name,$email,$role,$active,$id]);
    }
    if($isSelf){Auth::start();$_SESSION['user']['name']=$name;$_SESSION['user']['email']=$email;$_SESSION['user']['role']='super_admin';}
    Audit::log('user.update','user',$id,['role'=>$role,'active'=>$active,'password_changed'=>$changePassword]);
    U::flash('ok',$changePassword?'Usuário atualizado e nova senha gravada.':'Usuário atualizado com sucesso.');
    U::redirect('index.php?p=user_edit&id='.$id);
  }
  $roleOptions='';
  foreach(['super_admin'=>'Super Admin','admin'=>'Admin','gerente'=>'Gerente','recepcao'=>'Recepção','reservas'=>'Reservas','financeiro'=>'Financeiro','marketing'=>'Marketing'] as $value=>$label){
    $selected=$user['role']===$value?' selected':'';
    $disabled=$isSelf&&$value!=='super_admin'?' disabled':'';
    $roleOptions.='<option value="'.U::e($value).'"'.$selected.$disabled.'>'.U::e($label).'</option>';
  }
  $h=adminTitle('Editar usuário','Altere cadastro, perfil, status ou redefina a senha. Deixe os campos de senha em branco para manter a senha atual.');
  $h.='<form method=post class="card">'.Csrf::field().'<div class=grid3>'.row('Nome','name',$user['name']).row('E-mail','email',$user['email'],'email').'<label>Perfil<select name=role>'.$roleOptions.'</select></label>'.row('Usuário ativo','active',(int)$user['active'],'checkbox').row('Nova senha (mínimo 10 caracteres)','password','', 'password').row('Confirmar nova senha','password_confirm','', 'password').'</div><div class="map-editor-actions"><button>Salvar alterações</button><a class="btn secondary" href="index.php?p=users">Voltar para usuários</a></div></form>';
  if($isSelf)$h.='<div class="alert">Por segurança, seu próprio perfil permanece como Super Admin e ativo.</div>';
  render('Editar usuário',$h);exit;
