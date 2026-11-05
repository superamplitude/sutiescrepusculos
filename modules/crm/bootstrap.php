<?php
declare(strict_types=1);

require_once __DIR__.'/src/CrmRepository.php';
require_once __DIR__.'/src/CrmService.php';

use Crepusculo\CRM\CrmRepository;
use Crepusculo\CRM\CrmService;

/**
 * Cria o CRM usando o PDO já existente da aplicação.
 * A integração final deve passar o company_id resolvido pela sessão/tenant.
 */
function crepusculo_crm(PDO $pdo): CrmService
{
    return new CrmService(new CrmRepository($pdo));
}
