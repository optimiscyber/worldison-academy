<?php
// cert_request.php
require_once "inc/db.php";
require_once "../inc/auth.php"; // loads session + user_id

$user_id = $_SESSION['user_id'] ?? 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$user_id) {
    die("You must be logged in.");
}

if (!$course_id) {
    die("Invalid course.");
}

/* --------------------------------------------------------
   1. Ensure user is enrolled
--------------------------------------------------------- */
// Get course type (free/paid)
$courseType = $pdo->prepare("SELECT type FROM courses WHERE id=?");
$courseType->execute([$course_id]);
$type = $courseType->fetchColumn();

if (!$type) {
    die("Invalid course.");
}

// Paid course → must be enrolled
if ($type === 'paid') {
    $stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE user_id=? AND course_id=?");
    $stmt->execute([$user_id, $course_id]);

    if (!$stmt->fetchColumn()) {
        die("You are not enrolled in this course.");
    }
}
// Free course → no enrollment required

/* --------------------------------------------------------
   2. Ensure user completed all lessons
--------------------------------------------------------- */
$lessonCount = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id=?");
$lessonCount->execute([$course_id]);
$total_lessons = $lessonCount->fetchColumn();

$progressCount = $pdo->prepare("
    SELECT COUNT(*) FROM lesson_progress 
    WHERE user_id=? AND course_id=? AND completed=1
");
$progressCount->execute([$user_id, $course_id]);
$completed_lessons = $progressCount->fetchColumn();

if ($completed_lessons < $total_lessons) {
    die("You must complete all lessons before requesting certificate.");
}

/* --------------------------------------------------------
   3. Check if already requested
--------------------------------------------------------- */
$reqCheck = $pdo->prepare("
    SELECT id, status 
    FROM certificate_requests 
    WHERE user_id=? AND course_id=? LIMIT 1
");
$reqCheck->execute([$user_id, $course_id]);
$existing = $reqCheck->fetch(PDO::FETCH_ASSOC);

if ($existing) {

    if ($existing['status'] === 'pending') {
        die("You already requested a certificate. Please wait for approval.");
    }

    if ($existing['status'] === 'approved') {
        die("Your certificate is already approved. Check your course page to download it.");
    }

    if ($existing['status'] === 'rejected') {
        die("Your previous request was rejected. Please contact support.");
    }
}

/* --------------------------------------------------------
   4. Create Certificate Request
--------------------------------------------------------- */
$insert = $pdo->prepare("
    INSERT INTO certificate_requests (user_id, course_id)
    VALUES (?, ?)
");
$insert->execute([$user_id, $course_id]);

/* --------------------------------------------------------
   5. Redirect back to a lesson page (not course)
--------------------------------------------------------- */
// find a lesson ID to redirect to (prefer the first lesson)
$lessonStmt = $pdo->prepare("SELECT id FROM lessons WHERE course_id=? ORDER BY order_no ASC, id ASC LIMIT 1");
$lessonStmt->execute([$course_id]);
$firstLessonId = $lessonStmt->fetchColumn() ?: 0;

if ($firstLessonId) {
    // safe redirect using PHP header (no mixed JS string bug)
    header("Location: watch_lesson.php?id=" . intval($firstLessonId));
    exit;
} else {
    // fallback: go to courses page
    header("Location: courses-view.php?id=" . intval($course_id));
    exit;
}
