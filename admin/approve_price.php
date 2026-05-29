<?php
session_start();
require_once "inc/db.php";
require_once "../inc/email.php";

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'ceo') {
    die("Access denied.");
}

if (isset($_GET['id'])) {
    $course_id = (int) $_GET['id'];

    // Get course + instructor
    $stmt = $pdo->prepare("SELECT c.*, u.email, u.name FROM courses c JOIN users u ON c.instructor_id = u.id WHERE c.id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($course) {
        // Approve course price
        $pdo->prepare("UPDATE courses SET price_status='approved' WHERE id=?")->execute([$course_id]);

        // Send email
        $subject = "Course Price Approved";
        $message = "
            <h3>Your Course Has Been Approved</h3>
            <p>Course: <strong>{$course['title']}</strong></p>
            <p>Your course price (\${$course['price']}) has been approved by the admin team.</p>
            <p>It’s now live and visible as a paid course.</p>
        ";
        sendEmail($course['email'], $subject, $message);
    }

    header("Location: manage_courses.php?approved=true");
    exit;
}
?>
