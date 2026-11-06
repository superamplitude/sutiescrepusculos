<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();
    try{
      $id=$crmRepo->createContact($crmCompanyId,$_POST);
      Audit::log('crm.contact.create','crm_contacts',$id,['company_id'=>$crmCompanyId]);
      U::flash('ok','Contato criado no CRM.');
    }catch(Throwable $e){U::flash('err','Não foi possível criar o contato: '.$e->getMessage());}
    U::redirect('index.php?p=crm_contacts');
  }
  $rows=$crmRepo->contacts($crmCompanyId,250);
  $h=adminTitle('CRM · Contatos','Base por empresa, preparada para site, WhatsApp, e-mail e reservas.');
  $h.='<div class=split><div class=card><table><tr><th>Nome</th><th>Telefone</th><th>E-mail</th><th>Cliente</th><th>Atualizado</th></tr>';
  foreach($rows as $r)$h.='<tr><td>'.U::e($r['name']).'</td><td>'.U::e($r['phone']).'</td><td>'.U::e($r['email']).'</td><td>'.(!empty($r['is_client'])?'Sim':'Não').'</td><td>'.U::e($r['updated_at']).'</td></tr>';
  $h.='</table></div><form method=post class=card>'.Csrf::field().'<h2>Novo contato</h2><div class=grid2>'.row('Nome','name').row('Telefone','phone').row('E-mail','email','', 'email').'<label>Canal<select name=channel><option value=direct>Direto</option><option value=site>Site</option><option value=whatsapp>WhatsApp</option><option value=email>E-mail</option></select></label></div>'.row('Já é cliente','is_client',0,'checkbox').row('Observações','notes','','textarea').'<button>Salvar contato</button></form></div>';
  render('CRM · Contatos',$h);exit;
