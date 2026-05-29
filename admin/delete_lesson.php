<?php
session_start();
require_once "inc/db.php";

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'ceo') {
    die("Access denied.");
}
$instructor_id = $_SESSION['user_id'];
$lesson_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$lesson_id || !$course_id) {
    die("Missing parameters.");
}

// Verify instructor owns the course
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
$stmt->execute([$course_id, $instructor_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Unauthorized access.");
}

// Get lesson info for cleanup
$lessonStmt = $pdo->prepare("SELECT video_url FROM lessons WHERE id = ? AND course_id = ?");
$lessonStmt->execute([$lesson_id, $course_id]);
$lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);

if ($lesson) {
    // Optionally delete uploaded video file if exists (not YouTube)
    if (!empty($lesson['video_url']) && strpos($lesson['video_url'], 'uploads/media/') !== false) {
        $file_path = realpath($lesson['video_url']);
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Delete the lesson
    $delete = $pdo->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
    $delete->execute([$lesson_id, $course_id]);

    echo "<script>alert('Lesson deleted successfully.'); window.location='edit_course.php?id={$course_id}';</script>";
} else {
    echo "<script>alert('Lesson not found.'); window.location='edit_course.php?id={$course_id}';</script>";
}
