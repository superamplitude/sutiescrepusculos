<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  $t=$_SESSION['traveler']??null;if(!$t)U::redirect('index.php?p=traveler');
  $rid=(int)($_GET['id']??0);$q=$pdo->prepare("SELECT id FROM reservations WHERE id=? AND guest_id=?");$q->execute([$rid,$t['guest_id']]);
  if($q->fetchColumn()){$_SESSION['traveler']['reservation_id']=$rid;}U::redirect('index.php?p=traveler');
