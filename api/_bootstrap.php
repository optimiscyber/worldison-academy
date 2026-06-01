<?php
/**
 * API Bootstrap
 * Include this in every API endpoint for consistent setup.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../app/Services/CourseService.php';
require_once __DIR__ . '/../app/Services/LessonService.php';
require_once __DIR__ . '/../app/Services/EnrollmentService.php';

// JSON responses only
header('Content-Type: application/json; charset=utf-8');

// Helper: JSON response
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// Helper: Check authenticated
function require_auth() {
    if (empty($_SESSION['user_id'])) {
        json_response(['ok' => false, 'error' => 'Not authenticated'], 401);
    }
    return (int) $_SESSION['user_id'];
}

// Helper: Get request method
function http_method() {
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

// Helper: Get JSON body
function get_json_body() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?: [];
}
