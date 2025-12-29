<?php

require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;

$auth = new AuthController();
$auth->logout();

header('Location: /login.php');
exit;
