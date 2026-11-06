<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();
    try{
      $id=$crmRepo->createTask($crmCompanyId,$_POST);
      Audit::log('crm.task.create','crm_tasks',$id,['company_id'=>$crmCompanyId]);
      U::flash('ok','Tarefa criada no CRM.');
    }catch(Throwable $e){U::flash('err','Não foi possível criar a tarefa: '.$e->getMessage());}
    U::redirect('index.php?p=crm_tasks');
  }
  $rows=$crmRepo->tasks($crmCompanyId);
  $contacts=$crmRepo->contacts($crmCompanyId,500);
  $leads=$crmRepo->leads($crmCompanyId);
  $h=adminTitle('CRM · Tarefas e Follow-up','Retornos, pendências e próximos passos da equipe.');
  $h.='<div class=split><div class=card><table><tr><th>Tarefa</th><th>Contato</th><th>Lead</th><th>Prazo</th><th>Prioridade</th><th>Status</th></tr>';
  foreach($rows as $r)$h.='<tr><td>'.U::e($r['title']).'</td><td>'.U::e($r['contact_name']).'</td><td>'.U::e($r['lead_title']).'</td><td>'.U::e($r['due_at']).'</td><td>'.U::e($r['priority']).'</td><td>'.U::e($r['status']).'</td></tr>';
  $h.='</table></div><form method=post class=card>'.Csrf::field().'<h2>Nova tarefa</h2>'.row('Título','title').'<label>Contato<select name=contact_id><option value="">Opcional</option>';
  foreach($contacts as $c)$h.='<option value="'.(int)$c['id'].'">'.U::e($c['name']?:($c['phone']?:$c['email'])).'</option>';
  $h.='</select></label><label>Lead<select name=lead_id><option value="">Opcional</option>';
  foreach($leads as $l)$h.='<option value="'.(int)$l['id'].'">'.U::e($l['title']).' · '.U::e($l['contact_name']).'</option>';
  $h.='</select></label><div class=grid2>'.row('Prazo','due_at','', 'datetime-local').'<label>Prioridade<select name=priority><option value=normal>Normal</option><option value=high>Alta</option><option value=urgent>Urgente</option></select></label></div>'.row('Descrição','description','','textarea').'<button>Salvar tarefa</button></form></div>';
  render('CRM · Tarefas',$h);exit;
