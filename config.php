<?php
session_start();
date_default_timezone_set('UTC');

header('Content-Type: text/html; charset=utf-8');

define('DB_HOST', 'localhost');
define('DB_NAME', 'tzynohab_quizo');
define('DB_USER', 'tzynohab_quizo');
define('DB_PASS', 'tzync@0306A');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isEmployee() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Route guard
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

if (!isLoggedIn() && $currentFile !== 'index.php' && $currentFile !== 'register.php') {
    redirect('index.php');
}

if ($currentDir === 'admin' && !isAdmin()) {
    redirect('../employee/index.php');
}

if ($currentDir === 'employee' && !isEmployee() && !isAdmin()) {
    redirect('../index.php');
}
?>