<?php
$host = "127.0.0.1";
$user = "worldxaz_db";
$pass = "Admin123Admin";
$dbname = "worldxaz_lms";

try {
    $pdo = new PDO("mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
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
