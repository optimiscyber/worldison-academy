<?php
require_once "inc/db.php";
require_once "../inc/auth.php";

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';
$cert_id = intval($_GET['id'] ?? 0);

if (!$cert_id) die("Invalid certificate ID.");

// If admin or ceo, allow any certificate; else only their own
if (in_array($role, ['admin','ceo'])) {
    $stmt = $pdo->prepare("SELECT certificate_url FROM certificate_requests WHERE id=? AND status='approved'");
    $stmt->execute([$cert_id]);
} else {
    $stmt = $pdo->prepare("SELECT certificate_url FROM certificate_requests WHERE id=? AND user_id=? AND status='approved'");
    $stmt->execute([$cert_id, $user_id]);
}

$cert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cert) {
    die("Certificate not found or access denied.");
}

// Fix path to account for admin folder
$filepath = __DIR__ . '/../' . ltrim($cert['certificate_url'], '/');

if (!file_exists($filepath)) {
    die("Certificate file not found.");
}

$filename = basename($filepath);

// Force download
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
