<?php
// certificate_action.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('pcre.jit', '0');
session_start();
require_once "inc/db.php";

// ✅ Manual login & role check instead of including auth.php
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    die("Access denied: not logged in.");
}

if (!in_array($_SESSION['role'], ['admin', 'ceo'])) {
    die("Access denied: insufficient permissions.");
}

// now $_SESSION['user_id'] and $_SESSION['role'] are safe to use
$id = intval($_GET['id'] ?? 0);
$action = strtolower($_GET['action'] ?? '');
$allowed_actions = ['approve', 'reject'];

if (!$id || !in_array($action, $allowed_actions)) {
    die("Invalid request.");
}



// fetch request
$stmt = $pdo->prepare("SELECT * FROM certificate_requests WHERE id = ?");
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$request) {
    die("Request not found.");
}

$actionMap = [
    'approve' => 'approved',
    'reject' => 'rejected'
];
$status = $actionMap[$action];

// Handle reject quickly
if ($action === 'reject') {
    $update = $pdo->prepare("UPDATE certificate_requests SET status = ?, admin_id = ?, approved_at = NOW() WHERE id = ?");
    $update->execute([$status, $_SESSION['user_id'] ?? null, $id]);
    header("Location: certificates.php");
    exit;
}

/* --------------------------
   APPROVE: generate PDF
   -------------------------- */
require_once __DIR__ . '/../vendor/autoload.php'; // mPDF

function createCertificateMpdf(): \Mpdf\Mpdf {
    $tempDir = __DIR__ . '/tmp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $mpdf = new \Mpdf\Mpdf([
        'tempDir' => $tempDir,
        'format' => 'A4-L',
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
    ]);
    $mpdf->showImageErrors = true;
    $mpdf->SetCompression(false);

    return $mpdf;
}

function writeCertificateHtmlDebug(string $html, string $path): void {
    if (@file_put_contents($path, $html) === false) {
        error_log('Certificate debug HTML could not be written: ' . $path);
    }
}

function certificateDataUrl(string $path): string {
    $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
}

function buildCertificateTableHtml(array $user, array $course, string $issueDate, string $certCode): string {
    $name = htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($course['title'] ?? '', ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars($issueDate, ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars($certCode, ENT_QUOTES, 'UTF-8');
    $verifyUrl = 'https://academy.worldison.org/verify.php?code=' . rawurlencode($certCode);
    $verify = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: DejaVu Sans, sans-serif;
      color: #ffffff;
    }
    table.certificate {
      width: 100%;
      height: 210mm;
      border-collapse: collapse;
    }
    td {
      text-align: center;
      vertical-align: middle;
    }
    .top-space { height: 56mm; }
    .title { font-size: 42px; font-weight: bold; height: 18mm; }
    .name { font-size: 38px; font-weight: bold; height: 24mm; }
    .copy { font-size: 20px; height: 13mm; }
    .course { font-size: 24px; font-weight: bold; height: 20mm; }
    .verify-label { font-size: 18px; height: 8mm; }
    .verify { font-size: 16px; height: 12mm; }
    .code { font-size: 16px; height: 14mm; }
    .date { font-size: 18px; height: 20mm; }
    .bottom-space { height: 25mm; }
  </style>
</head>
<body>
  <table class="certificate" cellpadding="0" cellspacing="0">
    <tr><td class="top-space">&nbsp;</td></tr>
    <tr><td class="title">Certificate of Completion</td></tr>
    <tr><td class="name">{$name}</td></tr>
    <tr><td class="copy">has successfully completed the course</td></tr>
    <tr><td class="course">{$title}</td></tr>
    <tr><td class="verify-label">Verify at</td></tr>
    <tr><td class="verify">{$verify}</td></tr>
    <tr><td class="code">Certificate Code: <strong>{$code}</strong></td></tr>
    <tr><td class="date">{$date}</td></tr>
    <tr><td class="bottom-space">&nbsp;</td></tr>
  </table>
</body>
</html>
HTML;
}

// fetch user and course info
$u = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$u->execute([$request['user_id']]);
$user = $u->fetch(PDO::FETCH_ASSOC);

$c = $pdo->prepare("SELECT id, title FROM courses WHERE id = ?");
$c->execute([$request['course_id']]);
$course = $c->fetch(PDO::FETCH_ASSOC);

// ensure directories exist
$tempDir = __DIR__ . '/tmp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}
$uploadDir = __DIR__ . '/uploads/certificates';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$debugDir = $uploadDir . '/debug';
if (!is_dir($debugDir)) { mkdir($debugDir, 0755, true); }

// Generate a unique cert code and ensure uniqueness
do {
    $cert_code = 'WLD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $chk = $pdo->prepare("SELECT id FROM certificate_verifications WHERE cert_code = ?");
    $chk->execute([$cert_code]);
    $exists = (bool)$chk->fetchColumn();
} while ($exists);

// nice filename
$filename = sprintf('cert_%s_u%d_c%d.pdf', $cert_code, $user['id'], $course['id']);
$filepath = $uploadDir . '/' . $filename;
$public_url = '/admin/uploads/certificates/' . $filename; // adjust if your base path differs

// Build styled HTML certificate (simple, responsive)
$issueDate = date('F j, Y');
$bgPath = __DIR__ . '/img/certificate_bg.png';
$html = buildCertificateTableHtml($user, $course, $issueDate, $cert_code);
writeCertificateHtmlDebug($html, $debugDir . '/certificate_final_table_watermark.html');


// generate PDF
try {
    $mpdf = createCertificateMpdf();
    $mpdf->SetWatermarkImage($bgPath);
    $mpdf->showWatermarkImage = true;
    $mpdf->WriteHTML($html);
    $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
} catch (\Mpdf\MpdfException $e) {
    error_log('mPDF error: ' . $e->getMessage());
    ob_end_clean();
    die('Failed to generate certificate PDF. Please check the PHP extensions and server configuration. Details: ' . $e->getMessage());
} catch (Throwable $e) {
    error_log('Certificate generation unexpected error: ' . $e->getMessage());
    ob_end_clean();
    die('Failed to generate certificate PDF due to an unexpected server error. Please check the PHP extensions and server configuration.');
}

// Insert verification record
$verify = $pdo->prepare("
    INSERT INTO certificate_verifications (cert_code, user_id, course_id, issued_at)
    VALUES (?, ?, ?, NOW())
");
$verify->execute([$cert_code, $user['id'], $course['id']]);

// Update the certificate_request row: set approved and the URL and approved_at
$update = $pdo->prepare("
    UPDATE certificate_requests
    SET status = 'approved', admin_id = ?, certificate_url = ?, approved_at = NOW()
    WHERE id = ?
");
$update->execute([$_SESSION['user_id'] ?? null, $public_url, $id]);

// Optionally: create or update course_completions (useful)
$cc = $pdo->prepare("
    INSERT INTO course_completions (user_id, course_id, completed_at, certificate_path)
    VALUES (?, ?, NOW(), ?)
    ON DUPLICATE KEY UPDATE certificate_path = VALUES(certificate_path), completed_at = VALUES(completed_at)
");
$cc->execute([$user['id'], $course['id'], $public_url]);

// done - redirect back
header("Location: certificates.php");
exit;
