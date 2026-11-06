<?php
if(!defined('SUITES_PAGE_ENTRY')){http_response_code(404);exit;}
\App\Core\Auth::start();
(new \App\Services\GuestDocumentService())->download((int)($_GET['id']??0));
