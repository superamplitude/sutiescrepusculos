<?php
declare(strict_types=1);

namespace Crepusculo\CRM;

final class CrmService
{
    public function __construct(private CrmRepository $repo) {}

    public function crm360(int $companyId): array
    {
        $dashboard = $this->repo->dashboard($companyId);
        return [
            'ok' => true,
            'company_id' => $companyId,
            'counts' => $dashboard,
            'next_action' => $this->nextBestAction($dashboard),
            'journey' => ['lead','qualification','quote','reservation','stay','post_sale','repurchase'],
        ];
    }

    public function nextBestAction(array $counts): array
    {
        if (($counts['overdue_tasks'] ?? 0) > 0) {
            return ['module'=>'tasks','priority'=>1,'action'=>'Executar follow-ups vencidos'];
        }
        if (($counts['open_conversations'] ?? 0) > 0) {
            return ['module'=>'conversations','priority'=>2,'action'=>'Responder atendimentos abertos'];
        }
        if (($counts['open_leads'] ?? 0) > 0) {
            return ['module'=>'leads','priority'=>3,'action'=>'Avançar oportunidades abertas'];
        }
        return ['module'=>'crm360','priority'=>9,'action'=>'Revisar relacionamento e recompra'];
    }
}
