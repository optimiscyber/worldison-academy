<?php
session_start();
require_once "inc/db.php";
require_once "../inc/auth.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate Course ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid course ID");
}

$course_id = (int) $_GET['id'];

// Fetch course details with instructor info
$stmt = $pdo->prepare("
    SELECT c.*, u.name AS instructor_name, u.profile_picture, u.bio
    FROM courses c
    JOIN users u ON c.instructor_id = u.id
    WHERE c.id = ? LIMIT 1
");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found.");
}

// Check enrollment status
$enroll_stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ? AND status='active'");
$enroll_stmt->execute([$user_id, $course_id]);
$is_enrolled = $enroll_stmt->fetch(PDO::FETCH_ASSOC);

// For paid courses, check payment
$payment_ok = true;
if ($course['type'] === 'paid' && !$is_enrolled) {
    $payment_ok = false;
}

// Fetch lessons with progress info
$lesson_stmt = $pdo->prepare("
    SELECT l.*, 
           COALESCE(lp.completed,0) AS completed
    FROM lessons l
    LEFT JOIN lesson_progress lp 
        ON lp.lesson_id = l.id AND lp.user_id = ?
    WHERE l.course_id = ? AND l.status='published'
    ORDER BY l.order_no ASC, l.id ASC
");
$lesson_stmt->execute([$user_id, $course_id]);
$lessons = $lesson_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_lessons = count($lessons);

$completed_lessons = 0;
foreach ($lessons as $l) {
    if (!empty($l['completed'])) {
        $completed_lessons++;
    }
}

$progress_percent = $total_lessons ? round(($completed_lessons / $total_lessons) * 100) : 0;

// Thumbnail path
$thumbnail = $course['thumbnail'] 
    ? (file_exists($course['thumbnail']) ? $course['thumbnail'] : "../uploads/thumbnails/" . basename($course['thumbnail'])) 
    : "../assets/img/default-course.jpg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title><?= htmlspecialchars($course['title']) ?> - Worldison Academy</title>
 <?php include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>
<div class="main-content" id="mainContent">
<div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <a href="courses-view.php" class="btn btn-outline-secondary btn-sm">
            <i data-lucide="arrow-left"></i> Back to Courses
          </a>
        </div>
    </div>
  <div class="page-title" data-aos="fade">
    <div class="heading text-center">
      <div class="container">
        <h1><?= htmlspecialchars($course['title']) ?></h1>
        <p><?= ucfirst($course['type']) ?> course by <?= htmlspecialchars($course['instructor_name']) ?></p>
        <p>Progress: <strong><?= $progress_percent ?>%</strong> completed</p>
        <div class="progress mb-3">
          <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress_percent ?>%;" aria-valuenow="<?= $progress_percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
      </div>
    </div>
  </div>

  <section id="course-details" class="course-details section">
    <div class="container" data-aos="fade-up">
      <div class="row gy-4">

        <!-- Main Content -->
        <div class="col-lg-8">
          <div class="course-detail">
            <img src="<?= htmlspecialchars($thumbnail) ?>" 
                 alt="Course Thumbnail" 
                 class="img-fluid rounded mb-4 w-100"
                 onerror="this.src='../assets/img/default-course.jpg'">

            <h3>About this course</h3>
            <p><?= ($course['description']) ?></p>

            <h4 class="mt-4 mb-3">Course Lessons</h4>

            <?php if (!empty($lessons)): ?>
              <div class="accordion" id="lessonsAccordion">
                <?php foreach ($lessons as $index => $lesson): ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?= $lesson['id'] ?>">
                      <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" 
                              type="button" 
                              data-bs-toggle="collapse" 
                              data-bs-target="#collapse<?= $lesson['id'] ?>" 
                              aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" 
                              aria-controls="collapse<?= $lesson['id'] ?>">
                        <?= htmlspecialchars($lesson['title']) ?>
                        <?php if ($lesson['duration']): ?>
                          <span class="badge bg-secondary ms-2"><?= htmlspecialchars($lesson['duration']) ?></span>
                        <?php endif; ?>
                        <?php if ($lesson['completed']): ?>
                          <span class="badge bg-success ms-2">Completed</span>
                        <?php endif; ?>
                      </button>
                    </h2>
                    <div id="collapse<?= $lesson['id'] ?>" 
                         class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" 
                         aria-labelledby="heading<?= $lesson['id'] ?>" 
                         data-bs-parent="#lessonsAccordion">
                      <div class="accordion-body">
                        <p><?= ($lesson['description']) ?></p>

                        <?php if (!empty($lesson['video_url'])): ?>
                          <?php 
                          // Lesson locking: only allow if previous lessons completed
                          $can_watch = true;
                          if ($index > 0 && !$lessons[$index - 1]['completed']) {
                              $can_watch = false;
                          }
                          ?>
                          <a href="<?= $can_watch && $payment_ok ? "watch_lesson.php?id={$lesson['id']}" : "#" ?>" 
                             class="btn btn-sm <?= $can_watch && $payment_ok ? 'btn-outline-primary' : 'btn-secondary disabled' ?>">
                             <i class="bi bi-play-circle"></i> <?= $can_watch && $payment_ok ? 'Watch Lesson' : ($payment_ok ? 'Complete previous lesson first' : 'Purchase required') ?>
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="text-muted">No lessons have been added to this course yet.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
          <div class="course-info p-4 border rounded shadow-sm">
            <h5 class="fw-bold mb-3">Course Info</h5>

            <p><i class="bi bi-cash me-2"></i>
              <?= $course['type'] === 'paid' ? '₦' . number_format($course['price'], 2) : 'Free' ?>
            </p>
            <p><i class="bi bi-calendar3 me-2"></i> 
              Created on <?= date("F j, Y", strtotime($course['created_at'])) ?>
            </p>
            <p><i class="bi bi-check-circle me-2"></i> 
              Status: <?= ucfirst($course['status']) ?>
            </p>

            <hr>

            <h6 class="fw-bold mb-3">Instructor</h6>
            <div class="d-flex align-items-center mb-3">
              <img src="<?= htmlspecialchars($course['profile_picture'] ?: '../assets/img/trainers/default.jpg') ?>" 
                   alt="Instructor" 
                   class="rounded-circle me-3" 
                   width="60" height="60"
                   style="object-fit: cover;">
              <div>
                <strong><?= htmlspecialchars($course['instructor_name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($course['bio'] ?: 'Instructor at Mentor Platform') ?></small>
              </div>
            </div>

            <a href="<?= $is_enrolled || $course['type'] === 'free' ? 'view_courses.php?id='.$course_id : '../payment/checkout.php?course_id='.$course_id ?>" 
               class="btn btn-primary w-100 mt-3">
              <i class="bi bi-mortarboard"></i> <?= $is_enrolled || $course['type'] === 'free' ? 'Go to Course' : 'Purchase / Enroll' ?>
            </a>
          </div>
        </div>

      </div>
    </div>
  </section>
</div>
<?php include 'inc/script.php'; ?>
</div>  
</body>
</html>
