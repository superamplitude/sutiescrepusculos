<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $x=$crmSvc->crm360($crmCompanyId);
  $c=$x['counts'];
  $next=$x['next_action'];
  $h=adminTitle('CRM 360°','Relacionamento, oportunidades, atendimento e follow-up da empresa atual.');
  $h.='<div class=stats>'
    .'<div class=stat><span>Contatos</span><b>'.(int)$c['contacts'].'</b></div>'
    .'<div class=stat><span>Leads abertos</span><b>'.(int)$c['open_leads'].'</b></div>'
    .'<div class=stat><span>Tarefas pendentes</span><b>'.(int)$c['pending_tasks'].'</b></div>'
    .'<div class=stat><span>Follow-ups vencidos</span><b>'.(int)$c['overdue_tasks'].'</b></div>'
    .'<div class=stat><span>Conversas abertas</span><b>'.(int)$c['open_conversations'].'</b></div>'
    .'</div>';
  $h.='<div class=card><h2>Próxima melhor ação</h2><p><b>'.U::e($next['action']).'</b></p><p class=muted>Prioridade '.(int)$next['priority'].' · módulo '.U::e($next['module']).'</p></div>';
  $h.='<div class=cards><a class=card href="index.php?p=crm_contacts"><h3>Contatos</h3><p>Cadastro unificado de hóspedes, clientes e interessados.</p></a>'
    .'<a class=card href="index.php?p=crm_leads"><h3>Leads / Funil</h3><p>Oportunidades da primeira conversa até a reserva.</p></a>'
    .'<a class=card href="index.php?p=crm_tasks"><h3>Tarefas</h3><p>Follow-ups, retornos e pendências da equipe.</p></a>'
    .'<a class=card href="index.php?p=messages"><h3>Atendimento</h3><p>Conversas operacionais já existentes no portal.</p></a></div>';
  render('CRM 360°',$h);exit;
