<?php
session_start();
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/timetable-system');

$request = $_SERVER['REQUEST_URI'];
$base_path = '/timetable-system/';

// Remove base path and query string
$path = str_replace($base_path, '', parse_url($request, PHP_URL_PATH));
$parts = explode('/', $path);

// Route to appropriate page
if (empty($parts[0]) || $parts[0] == 'index.php') {
    require 'home.php';
} elseif ($parts[0] == 'share' && isset($parts[1]) && isset($parts[2])) {
    // /share/{section}/{day}
    $_GET['section'] = $parts[1];
    $_GET['day'] = $parts[2];
    require 'share.php';
} elseif ($parts[0] == 'invite' && isset($parts[1]) && isset($parts[2])) {
    // /invite/{section}/{code}
    $_GET['section'] = $parts[1];
    $_GET['code'] = $parts[2];
    require 'invite.php';
} elseif ($parts[0] == 'user') {
    require 'user.php';
} elseif ($parts[0] == 'admin') {
    require 'admin.php';
} elseif ($parts[0] == 'superadmin') {
    require 'superadmin.php';
} elseif ($parts[0] == 'auth.php') {
    require 'auth.php';
} elseif ($parts[0] == 'logout') {
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
} else {
    require 'home.php';
}
?>