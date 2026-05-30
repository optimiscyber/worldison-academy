<?php
// admin/upload_quill_image.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../inc/env.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/csrf.php';

// Basic permission check
$allowed = ['admin','ceo','instructor'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed)) {
    http_response_code(403);
    echo json_encode(['success' => 0, 'error' => 'Access denied']);
    exit;
}

// Validate CSRF token for AJAX upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => 0, 'error' => 'No file uploaded']);
    exit;
}

$img = $_FILES['image'];
$maxSize = 5 * 1024 * 1024; // 5MB
if ($img['size'] > $maxSize) {
    echo json_encode(['success' => 0, 'error' => 'File too large']);
    exit;
}

// Validate mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $img['tmp_name']);
finfo_close($finfo);
$allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
if (!in_array($mime, $allowedTypes)) {
    echo json_encode(['success' => 0, 'error' => 'Invalid image type']);
    exit;
}

// Whitelist extensions and block executable-like extensions
$ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
$blocked = ['php','phtml','phar','js','exe','sh'];
if (in_array($ext, $blocked)) {
    echo json_encode(['success' => 0, 'error' => 'Invalid file extension']);
    exit;
}

$uploadsDir = __DIR__ . '/../../assets/uploads/quill_images/';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

$filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$dest = $uploadsDir . $filename;

if (!move_uploaded_file($img['tmp_name'], $dest)) {
    echo json_encode(['success' => 0, 'error' => 'Failed to move uploaded file']);
    exit;
}

$url = '../assets/uploads/quill_images/' . $filename;
echo json_encode(['success' => 1, 'url' => $url]);
exit;
