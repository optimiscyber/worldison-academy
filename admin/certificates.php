<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "inc/db.php";
require_once "../inc/auth.php";

// Only admin or CEO
if(!in_array($_SESSION['role'], ['admin','ceo'])) {
    die("Access denied.");
}

// Fetch all certificate requests
$stmt = $pdo->query("
    SELECT cr.id, cr.user_id, cr.course_id, cr.status, cr.created_at, cr.certificate_url,
           u.name AS student_name, u.email,
           c.title AS course_title
    FROM certificate_requests cr
    JOIN users u ON cr.user_id = u.id
    JOIN courses c ON cr.course_id = c.id
    ORDER BY cr.created_at DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$pageTitle = 'Courses - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>
<div class="container mt-4">
    <h3>Certificate Requests</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Course</th>
                <th>Status</th>
                <th>Submitted At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($requests as $r): ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['student_name']) ?> (<?= $r['email'] ?>)</td>
                <td><?= htmlspecialchars($r['course_title']) ?></td>
                <td><?= ucfirst($r['status']) ?></td>
                <td><?= $r['created_at'] ?></td>
                <td>
                    <?php if($r['status'] === 'pending'): ?>
                        <a href="certificate_action.php?id=<?= $r['id'] ?>&action=approve" class="btn btn-success btn-sm">Approve</a>
                        <a href="certificate_action.php?id=<?= $r['id'] ?>&action=reject" class="btn btn-danger btn-sm">Reject</a>
                    <?php elseif($r['status'] === 'approved' && !empty($r['certificate_url'])): ?>
                        <a href="download_certificate.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">Preview</a>
                    <?php else: ?>
                        <em>No action</em>
                    <?php endif; ?>
                </td>

            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__.'/inc/script.php'; ?>

