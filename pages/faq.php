<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $prop=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  
    $xs=$pdo->query("SELECT title,content FROM knowledge_base WHERE active=1 ORDER BY id DESC LIMIT 100")->fetchAll();$h='<h1>Perguntas frequentes</h1>';foreach($xs as $x)$h.='<details class=card><summary>'.U::e($x['title']).'</summary><p>'.nl2br(U::e($x['content'])).'</p></details>';render('Perguntas frequentes',$h);exit;
  