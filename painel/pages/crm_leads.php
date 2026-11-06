<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();
    try{
      $id=$crmRepo->createLead($crmCompanyId,$_POST);
      Audit::log('crm.lead.create','crm_leads',$id,['company_id'=>$crmCompanyId]);
      U::flash('ok','Oportunidade criada no CRM.');
    }catch(Throwable $e){U::flash('err','Não foi possível criar o lead: '.$e->getMessage());}
    U::redirect('index.php?p=crm_leads');
  }
  $rows=$crmRepo->leads($crmCompanyId);
  $contacts=$crmRepo->contacts($crmCompanyId,500);
  $h=adminTitle('CRM · Leads / Funil','Oportunidades comerciais por empresa, do interesse inicial à reserva.');
  $h.='<div class=split><div class=card><table><tr><th>Contato</th><th>Oportunidade</th><th>Etapa</th><th>Status</th><th>Valor</th><th>Entrada</th></tr>';
  foreach($rows as $r)$h.='<tr><td>'.U::e($r['contact_name']).'</td><td>'.U::e($r['title']).'</td><td>'.U::e($r['stage']).'</td><td>'.U::e($r['status']).'</td><td>'.($r['value']!==null?money((float)$r['value']):'—').'</td><td>'.U::e($r['expected_checkin']).'</td></tr>';
  $h.='</table></div><form method=post class=card>'.Csrf::field().'<h2>Novo lead</h2><label>Contato<select name=contact_id required><option value="">Selecione</option>';
  foreach($contacts as $c)$h.='<option value="'.(int)$c['id'].'">'.U::e($c['name']?:($c['phone']?:$c['email'])).'</option>';
  $h.='</select></label>'.row('Título da oportunidade','title').'<div class=grid2><label>Etapa<select name=stage><option value=new>Novo</option><option value=qualification>Qualificação</option><option value=quote>Cotação</option><option value=reservation>Reserva</option><option value=won>Ganho</option><option value=lost>Perdido</option></select></label>'.row('Origem','source','site').row('Valor estimado','value','', 'number').row('Entrada prevista','expected_checkin','', 'date').row('Saída prevista','expected_checkout','', 'date').row('Hóspedes','guests','2','number').'</div><button>Salvar oportunidade</button></form></div>';
  render('CRM · Leads',$h);exit;
