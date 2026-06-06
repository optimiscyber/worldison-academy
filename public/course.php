<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../inc/db.php';

// Validate Course ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid course ID");
}

$course_id = (int) $_GET['id'];

// Fetch course with instructor info
$stmt = $pdo->prepare(
    "SELECT c.*, u.name AS instructor_name, u.profile_picture, u.bio
     FROM courses c
     JOIN users u ON c.instructor_id = u.id
     WHERE c.id = ? LIMIT 1"
);
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) die("Course not found.");

// Fetch lessons
$lesson_stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? AND status = 'published' ORDER BY order_no ASC, id ASC");
$lesson_stmt->execute([$course_id]);
$lessons = $lesson_stmt->fetchAll(PDO::FETCH_ASSOC);

$categoryName = '';
$catStmt = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
$catStmt->execute([$course['category_id']]);
$categoryName = $catStmt->fetchColumn() ?: 'Uncategorized';

// Resolve images
$thumbnail = resolveWebImagePath($course['thumbnail'] ?? null, 'assets/uploads/thumbnails', 'assets/img/default-course.jpg');
$profileImage = resolveWebImagePath($course['profile_picture'] ?? '', 'assets/uploads/profiles', 'assets/img/trainers/default.jpg');

// Determine first lesson and user enrollment/progress if logged in
$first_lesson_id = $lessons[0]['id'] ?? null;
$logged_in = !empty($_SESSION['user_id']);
$isEnrolled = false;
$lessonProgress = [];
if ($logged_in) {
    $uid = (int) $_SESSION['user_id'];
    $enr = $pdo->prepare('SELECT 1 FROM enrollments WHERE user_id=? AND course_id=? LIMIT 1');
    $enr->execute([$uid, $course_id]);
    $isEnrolled = (bool) $enr->fetchColumn();

    // Fetch progress for listed lessons
    $ids = array_map(function($l){ return (int)$l['id']; }, $lessons);
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$uid], $ids);
        $progStmt = $pdo->prepare("SELECT lesson_id, completed FROM lesson_progress WHERE user_id=? AND lesson_id IN ($in)");
        $progStmt->execute($params);
        $rows = $progStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $lessonProgress[(int)$r['lesson_id']] = (int)$r['completed'];
    }
}

include __DIR__ . '/../inc/header_public.php';
?>
<main class="main">
  <section class="course-detail section">
    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-8">
          <img src="<?= htmlspecialchars($thumbnail) ?>" class="img-fluid rounded mb-4 w-100" alt="<?= htmlspecialchars($course['title']) ?>" onerror="this.src='assets/img/default-course.jpg'">
          <h1><?= htmlspecialchars($course['title']) ?></h1>
          <p class="text-muted"><?= htmlspecialchars(ucfirst($categoryName)) ?> — <?= $course['type'] === 'paid' ? 'Paid' : 'Free' ?></p>
          <div class="course-description mb-4"><?= $course['description'] ?></div>

          <h4>Lessons</h4>
          <?php if (!empty($lessons)): ?>
            <ul class="list-group mb-4">
              <?php foreach ($lessons as $lesson): ?>
                <?php $completed = ($logged_in && !empty($lessonProgress[(int)$lesson['id']])); ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <strong><?= htmlspecialchars($lesson['title']) ?></strong>
                    <?php if (!empty($lesson['duration'])): ?>
                      <div class="small text-muted"><?= htmlspecialchars($lesson['duration']) ?></div>
                    <?php endif; ?>
                  </div>
                  <div>
                    <?php if ($logged_in && $isEnrolled): ?>
                      <a href="admin/watch_lesson.php?id=<?= (int)$lesson['id'] ?>" class="btn btn-sm btn-outline-primary"><?= $completed ? 'Resume' : 'Start' ?></a>
                    <?php else: ?>
                      <?php if ($course['type'] === 'paid'): ?>
                        <a href="payment/checkout.php?course_id=<?= $course_id ?>" class="btn btn-sm btn-success">Purchase</a>
                      <?php else: ?>
                        <?php if ($logged_in): ?>
                          <a href="enroll.php?course_id=<?= $course_id ?>" class="btn btn-sm btn-primary">Enroll</a>
                        <?php else: ?>
                          <a href="login.php" class="btn btn-sm btn-outline-secondary">Login to Enroll</a>
                        <?php endif; ?>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-muted">No lessons found for this course.</p>
          <?php endif; ?>
        </div>

        <div class="col-lg-4">
          <div class="card p-3 mb-3">
            <div class="d-flex align-items-center mb-3">
              <img src="<?= htmlspecialchars($profileImage) ?>" width="64" height="64" class="rounded-circle me-3" style="object-fit:cover;" alt="Instructor">
              <div>
                <strong><?= htmlspecialchars($course['instructor_name']) ?></strong>
                <div class="small text-muted"><?= htmlspecialchars($course['bio'] ?: '') ?></div>
              </div>
            </div>
            <p><strong>Price:</strong> <?= $course['type'] === 'paid' ? '₦' . number_format($course['price'],2) : 'Free' ?></p>
            <?php if ($course['type'] === 'paid'): ?>
              <a href="payment/checkout.php?course_id=<?= $course_id ?>" class="btn btn-success w-100">Purchase / Enroll</a>
            <?php else: ?>
              <?php if ($logged_in && $isEnrolled && $first_lesson_id): ?>
                <a href="admin/watch_lesson.php?id=<?= $first_lesson_id ?>" class="btn btn-primary w-100">Continue Course</a>
              <?php elseif ($logged_in && !$isEnrolled): ?>
                <a href="enroll.php?course_id=<?= $course_id ?>" class="btn btn-primary w-100">Enroll for Free</a>
              <?php else: ?>
                <a href="login.php" class="btn btn-outline-secondary w-100">Login to Enroll</a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../inc/footer_public.php'; ?>

<?php
// EOF
