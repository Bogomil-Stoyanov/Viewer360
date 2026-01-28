<?php

require_once __DIR__ . '/autoload.php';

use App\Controllers\AuthController;
use App\Config;

$auth = new AuthController();
$auth->logout();

header('Location: ' . Config::url('login.php'));
exit;
