<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
require __DIR__.'/messages.php';
$input=json_decode((string)file_get_contents('php://stdin'),true,512,JSON_THROW_ON_ERROR);
echo json_encode(processSuitesWhatsapp($input),JSON_UNESCAPED_UNICODE);
