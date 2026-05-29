<?php
session_start();
require_once "inc/db.php";
require_once "../inc/email.php";

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'ceo')) {
    die("Access denied.");
}

if (isset($_GET['id'])) {
    $instructor_id = (int) $_GET['id'];

    // Update status to approved
    $stmt = $pdo->prepare("UPDATE users SET status='approved' WHERE id=? AND role='instructor'");
    $stmt->execute([$instructor_id]);

    // Get instructor email
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id=?");
    $stmt->execute([$instructor_id]);
    $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($instructor) {
        $subject = "Your Instructor Account Has Been Approved!";
        $message = "
            <h3>Congratulations, {$instructor['name']}!</h3>
            <p>Your instructor account has been approved. You can now upload and manage courses.</p>
            <p><a href='https://worldison_learn/login.php'>Login here</a></p>
        ";
        sendEmail($instructor['email'], $subject, $message);
    }

    header("Location: manage_users.php?success=approved");
    exit;
}
?>
