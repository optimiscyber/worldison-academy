<?php
/**
 * API: POST /api/enroll.php
 * Enroll user in free course.
 * Requires authentication.
 */
require_once __DIR__ . '/_bootstrap.php';

$method = http_method();

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$user_id = require_auth();
$data = get_json_body();
$course_id = (int) ($data['course_id'] ?? 0);

if (!$course_id) {
    json_response(['ok' => false, 'error' => 'Course ID required'], 400);
}

try {
    $enrollmentService = new EnrollmentService($pdo);
    $result = $enrollmentService->enrollFree($user_id, $course_id);

    if (!$result['success']) {
        json_response(['ok' => false, 'error' => $result['error']], 400);
    }

    json_response([
        'ok' => true,
        'data' => $result,
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    json_response(['ok' => false, 'error' => 'Server error'], 500);
}
