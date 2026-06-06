<?php
session_start();
require_once "../inc/db.php";

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    // not logged in — send to shared login
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$course_id) {
    echo "<script>alert('Invalid course.'); window.location='../dashboard.php';</script>";
    exit;
}

// Fetch course
$stmt = $pdo->prepare("SELECT id, title, type, price, price_status FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    echo "<script>alert('Course not found.'); window.location='../dashboard.php';</script>";
    exit;
}

// If course is paid -> redirect to payment init
if ($course['type'] === 'paid') {
    // If price pending admin approval, block purchase
    if ($course['price_status'] !== 'approved') {
        echo "<script>alert('This course price is awaiting admin approval and cannot be purchased yet.'); window.location='../dashboard.php';</script>";
        exit;
    }
    // Redirect to payment initialization with course_id
    header("Location: ../payment/initialize.php?course_id={$course_id}");
    exit;
}

// Course is free: attempt to enroll
try {
    // Insert enrollment only if not exists
    $stmt = $pdo->prepare("INSERT IGNORE INTO enrollments (user_id, course_id, enrolled_at, status) VALUES (?, ?, NOW(), 'active')");
    $stmt->execute([$user_id, $course_id]);

    // Check if row was inserted or existing enrollment present
    if ($stmt->rowCount() > 0) {
        // success: new enrollment
        echo "<script>alert('You have been enrolled in \"". addslashes($course['title']) ."\".'); window.location='course_view.php?id={$course_id}';</script>";
        exit;
    } else {
        // already enrolled
        echo "<script>alert('You are already enrolled in this course.'); window.location='course_view.php?id={$course_id}';</script>";
        exit;
    }
} catch (PDOException $e) {
    // In rare case of race-condition or DB error
    error_log("Enroll error: " . $e->getMessage());
    echo "<script>alert('An error occurred while trying to enroll. Please try again.'); window.location='../dashboard.php';</script>";
    exit;
}
