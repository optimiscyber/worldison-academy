<?php
session_start();
require_once "../inc/db.php";
require_once "../inc/auth.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = intval($_POST['course_id']);
$method = $_POST['payment_method'];

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id=? LIMIT 1");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) { die("Course not found"); }

$amount = $course['price'];
$reference = uniqid("PS_");

// Insert initial payment record
$stmt = $pdo->prepare("INSERT INTO payments (user_id, course_id, amount, reference, method) VALUES (?,?,?,?,?)");
$stmt->execute([$user_id, $course_id, $amount, $reference, $method]);

if ($method === 'paystack') {
    header("Location: paystack_redirect.php?ref=$reference");
    exit;
}

if ($method === 'manual') {
    header("Location: manual_transfer.php?ref=$reference");
    exit;
}

die("Invalid payment method selected.");
?>
