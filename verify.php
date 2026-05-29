<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// verify.php
require_once __DIR__.'/inc/db.php';

$cert_code = trim($_GET['code'] ?? '');
$course_id  = intval($_GET['course_id'] ?? 0);

$certificate = null;

if ($cert_code || $course_id) {

    if ($cert_code) {
        // Lookup by certificate code
        $stmt = $pdo->prepare("
            SELECT cv.cert_code, cv.issued_at, cr.user_id, cr.course_id, cr.certificate_url,
                   u.name AS student_name, c.title AS course_title
            FROM certificate_verifications cv
            JOIN certificate_requests cr ON cr.user_id = cv.user_id AND cr.course_id = cv.course_id
            JOIN users u ON u.id = cv.user_id
            JOIN courses c ON c.id = cv.course_id
            WHERE cv.cert_code = ? AND cr.status = 'approved'
            LIMIT 1
        ");
        $stmt->execute([$cert_code]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

    } elseif ($course_id) {
        // Lookup by course ID (last approved certificate)
        $stmt = $pdo->prepare("
            SELECT cv.cert_code, cv.issued_at, cr.user_id, cr.course_id, cr.certificate_url,
                   u.name AS student_name, c.title AS course_title
            FROM certificate_verifications cv
            JOIN certificate_requests cr ON cr.user_id = cv.user_id AND cr.course_id = cv.course_id
            JOIN users u ON u.id = cv.user_id
            JOIN courses c ON c.id = cv.course_id
            WHERE cr.course_id = ? AND cr.status = 'approved'
            ORDER BY cv.issued_at DESC
            LIMIT 1
        ");
        $stmt->execute([$course_id]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
        $cert_code = $certificate['cert_code'] ?? '';
    }

}
?>

<!DOCTYPE html>
<html>
<head>
<title>Verify Certificate</title>
<style>
body {
    background: #f2f2f2;
    font-family: Arial, sans-serif;
}
.container {
    width: 450px;
    margin: 5% auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,.1);
}
input {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 6px;
}
button {
    width: 100%;
    padding: 12px;
    background: #0073ff;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
.success {
    background: #dfffe2;
    padding: 15px;
    border-left: 5px solid #22b33c;
    margin-top: 20px;
}
.failed {
    background: #ffe0e0;
    padding: 15px;
    border-left: 5px solid #ff2d2d;
    margin-top: 20px;
}
</style>
</head>
<body>

<div class="container">
    <h2>Verify Certificate</h2>
    <form method="GET">
        <input type="text" name="code" placeholder="Enter Certificate Code" value="<?= htmlspecialchars($cert_code) ?>">
        <button type="submit">Verify</button>
    </form>

    <?php if ($cert_code || $course_id): ?>
        <?php if ($certificate): ?>
            <div class="success">
                <h3>✔ Certificate Verified</h3>
                <p><strong>Student:</strong> <?= htmlspecialchars($certificate['student_name']) ?></p>
                <p><strong>Course:</strong> <?= htmlspecialchars($certificate['course_title']) ?></p>
                <p><strong>Issued At:</strong> <?= htmlspecialchars($certificate['issued_at']) ?></p>
                <p><strong>Certificate ID:</strong> <?= htmlspecialchars($certificate['cert_code']) ?></p>
               <!--
                <?php if (!empty($certificate['certificate_url'])): ?>
                    <p>
                        <a href="admin/download_certificate.php?course_id=<?= $certificate['course_id'] ?>&user_id=<?= $certificate['user_id'] ?>" 
                        class="btn btn-primary">
                        Download Certificate
                        </a>
                    </p>
                <?php endif; ?>
                -->

            </div>
        <?php else: ?>
            <div class="failed">
                <h3>✖ Invalid Certificate</h3>
                <p>No certificate found for this code.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
