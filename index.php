<?php
// Heroku-specific configurations
session_start();

// Set maximum execution time
set_time_limit(30);

// Error reporting (off in production)
if (getenv('APP_ENV') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Define BASE_URL dynamically for Heroku
if (!defined('BASE_URL')) {
    if (getenv('BASE_URL')) {
        define('BASE_URL', getenv('BASE_URL'));
    } else {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        define('BASE_URL', $protocol . '://' . $host);
    }
}

// Database connection
require_once 'conn.php';

// Routing
$request = $_SERVER['REQUEST_URI'];

// Remove query string
$request = strtok($request, '?');

// Remove base path (for Heroku subdirectory if needed)
$base_path = '/';
$path = str_replace($base_path, '', $request);

// Handle root request
if (empty($path) || $path == '/' || $path == 'index.php') {
    require 'home.php';
    exit;
}

// Parse path
$parts = explode('/', trim($path, '/'));

// Route to appropriate page
if ($parts[0] == 'share' && isset($parts[1]) && isset($parts[2])) {
    // /share/{section}/{day}
    $_GET['section'] = urldecode($parts[1]);
    $_GET['day'] = urldecode($parts[2]);
    require 'share.php';
} elseif ($parts[0] == 'invite' && isset($parts[1]) && isset($parts[2])) {
    // /invite/{section}/{code}
    $_GET['section'] = urldecode($parts[1]);
    $_GET['code'] = urldecode($parts[2]);
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
} elseif ($parts[0] == 'setup') {
    require 'setup.php';
} else {
    // Default to home
    require 'home.php';
}
?>
