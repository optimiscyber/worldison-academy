<?php
session_start();
require_once "inc/db.php";

$allowed = ['admin', 'ceo', 'instructor'];

if (!in_array($_SESSION['role'], $allowed)) {
    die("Access denied.");
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid course ID.");
}

$course_id = (int) $_GET['id'];
$instructor_id = $_SESSION['user_id'];

// Verify course ownership
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
$stmt->execute([$course_id, $instructor_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found or access denied.");
}

// Delete course (lessons will auto-delete due to ON DELETE CASCADE)
$delete_stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
$delete_stmt->execute([$course_id]);

echo "<script>
    alert('Course deleted successfully!');
    window.location.href = 'my_courses.php';
</script>";
