<?php
require_once __DIR__ . '/../autoload.php';
header('Location: ' . \App\Config::url('admin/dashboard.php'));
exit;
