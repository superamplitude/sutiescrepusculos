<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
use App\Controllers\PublicChatController;
if (($_GET['action']??'')==='panel_voice') \App\Controllers\PanelVoiceController::handle();
PublicChatController::handle();
