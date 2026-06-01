<?php
/**
 * API: GET /api/lesson.php?id=123
 * Get lesson details with progress and adjacent lessons.
 * Requires authentication.
 */
require_once __DIR__ . '/_bootstrap.php';

$method = http_method();

if ($method !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$user_id = require_auth();
$lesson_id = (int) ($_GET['id'] ?? 0);

if (!$lesson_id) {
    json_response(['ok' => false, 'error' => 'Lesson ID required'], 400);
}

try {
    $lessonService = new LessonService($pdo);
    $lesson = $lessonService->getLessonForUser($lesson_id, $user_id);

    if (!$lesson) {
        json_response(['ok' => false, 'error' => 'Lesson not found or access denied'], 404);
    }

    json_response([
        'ok' => true,
        'data' => $lesson,
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    json_response(['ok' => false, 'error' => 'Server error'], 500);
}
