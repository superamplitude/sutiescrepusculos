<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requireSuperAdmin();$xs=$pdo->query("SELECT a.*,u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 500")->fetchAll();$h=adminTitle('Auditoria','Ações administrativas e críticas.').'<div class=card><table><tr><th>Data</th><th>Usuário</th><th>Ação</th><th>Entidade</th><th>IP</th></tr>';foreach($xs as $x)$h.="<tr><td>".U::e($x['created_at'])."</td><td>".U::e($x['user_name'])."</td><td>".U::e($x['action'])."</td><td>".U::e($x['entity'])." #".U::e($x['entity_id'])."</td><td>".U::e($x['ip'])."</td></tr>";render('Auditoria',$h.'</table></div>');exit;
