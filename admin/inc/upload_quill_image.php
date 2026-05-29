<?php
// admin/upload_quill_image.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/inc/db.php'; // if you need DB or auth; adjust path if needed

// Basic permission check (optional)
$allowed = ['admin','ceo','instructor'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed)) {
    http_response_code(403);
    echo json_encode(['success' => 0, 'error' => 'Access denied']);
    exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => 0, 'error' => 'No file uploaded']);
    exit;
}

$img = $_FILES['image'];
$maxSize = 5 * 1024 * 1024; // 5MB limit (adjust as needed)
if ($img['size'] > $maxSize) {
    echo json_encode(['success' => 0, 'error' => 'File too large']);
    exit;
}

// Validate mime type (basic)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $img['tmp_name']);
finfo_close($finfo);
$allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
if (!in_array($mime, $allowedTypes)) {
    echo json_encode(['success' => 0, 'error' => 'Invalid image type']);
    exit;
}

// Save file
$uploadsDir = __DIR__ . '/../assets/uploads/quill_images/';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

$ext = pathinfo($img['name'], PATHINFO_EXTENSION);
$filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$dest = $uploadsDir . $filename;

if (!move_uploaded_file($img['tmp_name'], $dest)) {
    echo json_encode(['success' => 0, 'error' => 'Failed to move uploaded file']);
    exit;
}

// Return URL (use web-accessible path relative to public root)
$url = '../assets/uploads/quill_images/' . $filename; // adjust if necessary to match public path
echo json_encode(['success' => 1, 'url' => $url]);
exit;
