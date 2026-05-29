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

// fetch user and course info
$u = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$u->execute([$request['user_id']]);
$user = $u->fetch(PDO::FETCH_ASSOC);

$c = $pdo->prepare("SELECT id, title FROM courses WHERE id = ?");
$c->execute([$request['course_id']]);
$course = $c->fetch(PDO::FETCH_ASSOC);

// ensure directories exist
$uploadDir = __DIR__ . '/uploads/certificates';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

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
// Path to your background image

$bgPath = realpath(__DIR__ . '/img/certificate_bg.png');
if ($bgPath === false) {
    ob_end_clean();
    die('Background image not found');
}

$bg = 'file://' . $bgPath;

$issueDate = date('F j, Y');

$html = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <style>
body {
  margin: 0;
  padding: 0;
  font-family: DejaVu Sans, sans-serif;
  text-align: center;
}

h1 { font-size: 40px; margin: 4px 0; }
h2 { font-size: 20px; margin: 5px 0; }
.name { font-size: 35px; margin: 8px 0; font-weight: bold; }
.footer { margin-top: 10px; font-size: 20px; }
.code { margin-top: 10px; padding: 6px 12px; }
body, h1, h2, .name, .footer, .code {
  color: #ffffff;
}

</style>
</head>
<body>

<img src="$bg" style="
  position:fixed;
  top:0; left:0;
  width:297mm;
  height:210mm;
  z-index:-1;
">

<div style="
  position:fixed;
  top:36mm;
  left:110px;
  width:100%;
  text-align:left;
">

  <h1>of Completion</h1>
</div>

<div style="position:fixed; top:80mm; width:100%; text-align:center;">
  <div class="name">{$user['name']}</div>
</div>

<div style="position:fixed; top:100mm; width:100%; text-align:center;">
  <h2>on successfully completed the course</h2>
</div>

<div style="position:fixed; top:110mm; width:100%; text-align:center;">
  <h2>"{$course['title']}"</h2>
</div>

<div style="position:fixed; top:173mm; width:100%; left: 145px;; text-align:center;">
  <div class="footer">{$issueDate}</div>
</div>

<div style="position:fixed; top:145mm; width:100%; text-align:center;">
  <div class="code">Certificate Code: <strong>{$cert_code}</strong></div>
</div>
<div style="position:fixed; top:125mm; width:100%; text-align:center;">
  <h2>Verify at:</h2>
</div>
<div style="position:fixed; top:130mm; width:100%; left:40; text-align:center;">
  <div class="footer">
    https://academy.worldison.org/verify.php?code={$cert_code}
  </div>
</div>


</body>


</html>
HTML;


// generate PDF
try {
  $mpdf = new \Mpdf\Mpdf([
    'tempDir' => __DIR__ . '/tmp',
    'format' => 'A4-L',
    'margin_left' => 0,
    'margin_right' => 0,
    'margin_top' => 0,
    'margin_bottom' => 0,
]);

$mpdf->showImageErrors = true;

    $mpdf->WriteHTML($html);
    $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
} catch (\Mpdf\MpdfException $e) {
    error_log("mPDF error: " . $e->getMessage());
    die("Failed to generate certificate PDF: " . $e->getMessage());
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
