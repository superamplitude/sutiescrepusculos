<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  // CRM_AUTO_SYNC_AEL: mantém o CRM importado do AEL ligado aos dados reais do estabelecimento.
  try{(new \App\Services\CrmSyncService(currentCompanyId()))->syncAll();}catch(Throwable $crmSyncError){@file_put_contents(dirname(__DIR__,2).'/storage/logs/app.log',date('c').' CRM_SYNC '.$crmSyncError->getMessage().PHP_EOL,FILE_APPEND);}

  Auth::requireLogin();
  $crmCompanyId=currentCompanyId();
  $crmRepo=new \Crepusculo\CRM\CrmRepository($pdo);
  $crmSvc=new \Crepusculo\CRM\CrmService($crmRepo);
