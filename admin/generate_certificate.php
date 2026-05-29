<?php
// generate_certificate.php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php'; // ensure admin or system user
require_once __DIR__.'/../vendor/autoload.php';

function generateCertificateCode() {
    return 'WLD-' . strtoupper(substr(md5(uniqid('', true)), 0, 10));
}

function certificateExists($pdo, $user_id, $course_id) {
    $s = $pdo->prepare("SELECT id FROM certificates WHERE user_id=? AND course_id=? LIMIT 1");
    $s->execute([$user_id, $course_id]);
    return (bool)$s->fetchColumn();
}

$user_id = intval($_POST['user_id'] ?? 0);
$course_id = intval($_POST['course_id'] ?? 0);
$autoApprove = ($_POST['auto'] ?? '1') === '1';

if (!$user_id || !$course_id) {
    http_response_code(400);
    echo 'Missing';
    exit;
}

if (certificateExists($pdo, $user_id, $course_id)) {
    echo 'Already exists';
    exit;
}

// fetch user and course
$u = $pdo->prepare("SELECT name, email FROM users WHERE id=?");
$u->execute([$user_id]);
$user = $u->fetch(PDO::FETCH_ASSOC);

$c = $pdo->prepare("SELECT title FROM courses WHERE id=?");
$c->execute([$course_id]);
$course = $c->fetch(PDO::FETCH_ASSOC);

$cert_code = generateCertificateCode();

// Prepare PDF HTML - create a nice template or require an external template file
$html = "
<html><body style='font-family: sans-serif'>
  <div style='text-align:center; padding:40px; border:5px solid #2a9d8f;'>
    <h1>Certificate of Completion</h1>
    <h2 style='margin-top:30px'>{$course['title']}</h2>
    <p style='margin-top:20px'>This is to certify that</p>
    <h3 style='margin-top:10px'>{$user['name']}</h3>
    <p style='margin-top:10px'>has completed the course.</p>
    <p style='margin-top:30px'>Issued: ".date('F j, Y')."</p>
    <p style='margin-top:10px'>Certificate Code: <strong>{$cert_code}</strong></p>
  </div>
</body></html>
";

// generate file path
$dir = __DIR__ . '/../assets/uploads/certificates';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = 'cert_' . $cert_code . '_' . $user_id . '_' . $course_id . '.pdf';
$filepath = $dir . '/' . $filename;

// create pdf
$mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/../tmp']);
$mpdf->WriteHTML($html);
$mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

// we want to store a public URL (adjust base URL)
$public_url = '/assets/uploads/certificates/' . $filename;

// insert into certificates table
$stmt = $pdo->prepare("
    INSERT INTO certificates (user_id, course_id, status, admin_id, issued_at, created_at, certificate_url)
    VALUES (?, ?, ?, ?, NOW(), NOW(), ?)
");
// status: if autoApprove true -> 'approved', else 'pending'
$status = $autoApprove ? 'approved' : 'pending';
// admin id: if automated, set to NULL or the system admin id
$admin_id = $_SESSION['user_id'] ?? null;
$stmt->execute([$user_id, $course_id, $status, $admin_id, $public_url]);

// insert into certificate_verifications
$cv = $pdo->prepare("
    INSERT INTO certificate_verifications (cert_code, user_id, course_id, issued_at)
    VALUES (?, ?, ?, NOW())
");
$cv->execute([$cert_code, $user_id, $course_id]);

// update course_completions
$cc = $pdo->prepare("
    INSERT INTO course_completions (user_id, course_id, completed_at, certificate_path)
    VALUES (?, ?, NOW(), ?)
    ON DUPLICATE KEY UPDATE certificate_path = VALUES(certificate_path), completed_at = NOW()
");
$cc->execute([$user_id, $course_id, $public_url]);

echo json_encode(['ok'=>true, 'url'=>$public_url, 'code'=>$cert_code]);
