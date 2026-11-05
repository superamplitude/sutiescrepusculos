<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('messages.manage');
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$id=(int)$_POST['id'];$status=in_array($_POST['status']??'new',['new','read','answered','archived'],true)?$_POST['status']:'new';
    $pdo->prepare("UPDATE contact_messages SET status=?,updated_at=NOW() WHERE id=?")->execute([$status,$id]);U::redirect('index.php?p=contacts_admin');
  }
  $xs=$pdo->query("SELECT * FROM contact_messages ORDER BY status='new' DESC,id DESC LIMIT 300")->fetchAll();
  $h=adminTitle('Contatos do site','Mensagens enviadas pelo formulário público.').'<div class=card><table><tr><th>Data</th><th>Nome</th><th>Contato</th><th>Assunto</th><th>Mensagem</th><th>Status</th></tr>';
  foreach($xs as $x)$h.='<tr><td>'.U::e($x['created_at']).'</td><td>'.U::e($x['name']).'</td><td>'.U::e($x['email']).'<br>'.U::e($x['phone']).'</td><td>'.U::e($x['subject']).'</td><td>'.U::e(mb_strimwidth($x['message'],0,120,'…')).'</td><td><form method=post>'.Csrf::field().'<input type=hidden name=id value="'.$x['id'].'"><select name=status><option value=new '.($x['status']==='new'?'selected':'').'>Novo</option><option value=read '.($x['status']==='read'?'selected':'').'>Lido</option><option value=answered '.($x['status']==='answered'?'selected':'').'>Respondido</option><option value=archived '.($x['status']==='archived'?'selected':'').'>Arquivado</option></select><button class=small>Salvar</button></form></td></tr>';
  $h.='</table></div>';render('Contatos do site',$h);exit;
