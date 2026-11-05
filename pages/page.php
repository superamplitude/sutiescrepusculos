<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $slug=trim($_GET['slug']??'');$q=$pdo->prepare("SELECT * FROM contents WHERE slug=? AND status='published' LIMIT 1");$q->execute([$slug]);$c=$q->fetch();
  if(!$c){http_response_code(404);render('Conteúdo não encontrado','<h1>Conteúdo não encontrado</h1>');exit;}
  render($c['seo_title']?:$c['title'],'<article class="card content-page"><h1>'.U::e($c['title']).'</h1>'.nl2br(U::e($c['body'])).'</article>',$c['meta_description']?:'');exit;
