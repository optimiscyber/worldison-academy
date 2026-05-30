<?php
require_once __DIR__ . '/env.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'worldxaz_db';
$pass = getenv('DB_PASS') ?: 'Admin123Admin';
$dbname = getenv('DB_NAME') ?: 'worldxaz_lms';

// Production safe defaults
if ((getenv('APP_DEBUG') === '0') || (getenv('APP_ENV') === 'production')) {
    ini_set('display_errors', '0');
    error_reporting(0);
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log error server-side if logging available; avoid leaking details to user
    error_log('DB connection failed: ' . $e->getMessage());
    die('Database connection error.');
}

if (!function_exists('safe_substr')) {
    function safe_substr(string $text, int $start, ?int $length = null): string {
        if ($length === null) {
            return function_exists('mb_substr') ? mb_substr($text, $start) : substr($text, $start);
        }
        return function_exists('mb_substr') ? mb_substr($text, $start, $length) : substr($text, $start, $length);
    }
}
?>
