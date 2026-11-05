<?php
require __DIR__.'/app/bootstrap.php';
use App\Core\Auth;
Auth::start();
header('Location: index.php?p='.(Auth::user() ? 'dashboard' : 'login'));
exit;
