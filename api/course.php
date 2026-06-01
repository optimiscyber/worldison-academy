<?php
/**
 * API: GET /api/course.php?id=123
 * Get course details with enrollment + progress info.
 */
require_once __DIR__ . '/_bootstrap.php';

$method = http_method();

if ($method !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$course_id = (int) ($_GET['id'] ?? 0);
if (!$course_id) {
    json_response(['ok' => false, 'error' => 'Course ID required'], 400);
}

try {
    $courseService = new CourseService($pdo);
    $user_id = $_SESSION['user_id'] ?? null;

    $course = $courseService->getCourseDetail($course_id, $user_id);
    if (!$course) {
        json_response(['ok' => false, 'error' => 'Course not found'], 404);
    }

    json_response([
        'ok' => true,
        'data' => $course,
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    json_response(['ok' => false, 'error' => 'Server error'], 500);
}
