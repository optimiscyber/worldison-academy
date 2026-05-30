<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../inc/env.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';

// Verify user
if (!isset($_SESSION['user_id'])) {
	http_response_code(403); exit('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405); exit('Method not allowed');
}

csrf_verify();

$reference = $_POST['reference'] ?? '';
if (!$reference) { echo "<script>alert('Invalid reference'); window.history.back();</script>"; exit; }

if (empty($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
	echo "<script>alert('No file uploaded'); window.history.back();</script>"; exit;
}

$file = $_FILES['proof'];
if ($file['size'] > 5 * 1024 * 1024) { echo "<script>alert('File too large'); window.history.back();</script>"; exit; }

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
if (!in_array($mime, $allowed)) { echo "<script>alert('Invalid file type'); window.history.back();</script>"; exit; }

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$blocked = ['php','phtml','phar','js','exe','sh'];
if (in_array($ext, $blocked)) { echo "<script>alert('Invalid file extension'); window.history.back();</script>"; exit; }

$uploads = __DIR__ . '/../assets/uploads/payproof/';
if (!is_dir($uploads)) mkdir($uploads, 0755, true);
$name = bin2hex(random_bytes(8)) . '.' . $ext;
$targetPath = $uploads . $name;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
	echo "<script>alert('Upload failed'); window.history.back();</script>"; exit;
}

$webPath = '../assets/uploads/payproof/' . $name;

$stmt = $pdo->prepare("UPDATE payments SET proof=?, status='pending' WHERE reference=? AND user_id=?");
$stmt->execute([$webPath, $reference, $_SESSION['user_id']]);

echo "<script>alert('Payment proof uploaded. We will verify shortly.'); window.location='../dashboard.php';</script>";
?>
