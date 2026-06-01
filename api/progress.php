<?php
/**
 * API: POST /api/progress.php
 * Mark lesson complete and get updated course progress.
 * Requires authentication.
 */
require_once __DIR__ . '/_bootstrap.php';

$method = http_method();

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$user_id = require_auth();
$data = get_json_body();
$lesson_id = (int) ($data['lesson_id'] ?? 0);

if (!$lesson_id) {
    json_response(['ok' => false, 'error' => 'Lesson ID required'], 400);
}

try {
    $lessonService = new LessonService($pdo);
    $result = $lessonService->markLessonComplete($lesson_id, $user_id);

    if (!$result) {
        json_response(['ok' => false, 'error' => 'Lesson not found'], 404);
    }

    json_response([
        'ok' => true,
        'data' => $result,
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    json_response(['ok' => false, 'error' => 'Server error'], 500);
}
