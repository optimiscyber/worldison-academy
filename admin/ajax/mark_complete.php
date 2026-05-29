<?php
// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);



// Include database and auth files
require_once __DIR__ . '/../../inc/db.php';       // Adjust path relative to this file
require_once __DIR__ . '/../../inc/auth.php';

// Return JSON response
header('Content-Type: application/json');

// Get user and lesson IDs
$user_id   = $_SESSION['user_id'] ?? 0;
$lesson_id = intval($_POST['lesson_id'] ?? 0);

if (!$user_id || !$lesson_id) {
    echo json_encode(['ok' => false, 'error' => 'missing user or lesson']);
    exit;
}

// Fetch lesson and its course
$stmt = $pdo->prepare("SELECT id, course_id FROM lessons WHERE id = ?");
$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    echo json_encode(['ok' => false, 'error' => 'lesson not found']);
    exit;
}

$course_id = (int)$lesson['course_id'];

// Insert or update lesson progress
$up = $pdo->prepare("
    INSERT INTO lesson_progress (user_id, lesson_id, course_id, completed, completed_at)
    VALUES (?, ?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()
");
$up->execute([$user_id, $lesson_id, $course_id]);

// Count total lessons in course
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
$totalStmt->execute([$course_id]);
$total = (int)$totalStmt->fetchColumn();

// Count completed lessons by this user
$doneStmt = $pdo->prepare("
    SELECT COUNT(*) FROM lesson_progress 
    WHERE user_id = ? AND course_id = ? AND completed = 1
");
$doneStmt->execute([$user_id, $course_id]);
$done = (int)$doneStmt->fetchColumn();

// Determine if the course is completed
$course_completed = ($total > 0 && $done >= $total);

// Return JSON response
echo json_encode([
    'ok'                => true,
    'lesson_id'         => $lesson_id,
    'course_id'         => $course_id,
    'total_lessons'     => $total,
    'completed_lessons' => $done,
    'course_completed'  => $course_completed
]);
exit;
