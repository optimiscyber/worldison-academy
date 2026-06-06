<?php
session_start();
require_once "../inc/db.php";
require_once "../inc/auth.php";

// Ensure logged in and is student
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch enrolled courses
$stmt = $pdo->prepare("
    SELECT 
        c.id AS course_id,
        c.title,
        c.description,
        c.thumbnail,
        c.type,
        c.price,
        e.enrolled_at,
        t.payment_status
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN transactions t ON t.course_id = c.id AND t.user_id = e.user_id
    WHERE e.user_id = ?
    ORDER BY e.enrolled_at DESC
");
$stmt->execute([$user_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Enrollments | Student Dashboard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../inc/header.php'; ?>
<?php include "../inc/sidebar.php"; ?>
<div class=" main-content" id="mainContent">
  <h2 class="mb-4">🎓 My Enrolled Courses</h2>

  <?php if (empty($courses)): ?>
      <div class="alert alert-info">You haven’t enrolled in any courses yet.</div>
  <?php else: ?>
      <div class="row g-4">
        <?php foreach ($courses as $course): ?>
          <div class="col-md-4">
            <div class="card shadow-sm h-100">
              <img src="../<?= htmlspecialchars($course['thumbnail'] ?? 'assets/images/default-course.jpg') ?>" 
                   class="card-img-top" alt="<?= htmlspecialchars($course['title']) ?>">
              <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($course['title']) ?></h5>
                <p class="card-text text-muted">
                  <?= substr(htmlspecialchars($course['description']), 0, 100) ?>...
                </p>
                <p>
                  <?php if ($course['type'] === 'free'): ?>
                    <span class="badge bg-success">Free</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark">Paid</span>
                    <br>
                    <small>
                      <?= ($course['payment_status'] === 'success')
                        ? '✅ Payment Confirmed'
                        : '⏳ Awaiting Payment' ?>
                    </small>
                  <?php endif; ?>
                </p>
                <p class="small text-muted">Enrolled on <?= date("M j, Y", strtotime($course['enrolled_at'])) ?></p>
                <a href="course_view.php?id=<?= $course['course_id'] ?>" class="btn btn-primary w-100">
                  <?= ($course['payment_status'] === 'success' || $course['type'] === 'free')
                        ? 'Start Course'
                        : 'View Course' ?>
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
  <?php endif; ?>
</div>
<?php include '../inc/script.php'; ?>
</body>
</html>
