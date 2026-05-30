<?php
session_start();
require_once "../inc/env.php";
require_once "../inc/db.php";
require_once "../inc/auth.php";
require_once "../inc/csrf.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_verify();

$course_id = intval($_POST['course_id'] ?? 0);
$method = $_POST['payment_method'] ?? '';

// Validate payment method
$allowed_methods = ['paystack','manual'];
if (!in_array($method, $allowed_methods)) { die('Invalid payment method'); }

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id=? LIMIT 1");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) { die("Course not found"); }

$amount = $course['price'];
// ensure unique reference
$reference = bin2hex(random_bytes(8));

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
