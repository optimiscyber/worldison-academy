<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "inc/db.php";

$allowed = ['admin', 'ceo', 'instructor'];

if (!in_array($_SESSION['role'], $allowed)) {
    die("Access denied.");
}


$instructor_id = $_SESSION['user_id'];

// Fetch all courses by this instructor
$stmt = $pdo->prepare("SELECT * FROM courses WHERE instructor_id = ? ORDER BY id DESC");
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$pageTitle = 'Dashboard - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>
<style>
.course-row { background: #fff; margin-bottom: 10px; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.course-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f9fafb; cursor: pointer; }
.course-header:hover { background: #f1f5f9; }
.course-title { font-weight: 600; font-size: 16px; color: #333; }
.course-meta { font-size: 14px; color: #555; margin-left: 8px; }
.course-actions a { margin-left: 8px; text-decoration: none; }
.course-lessons { display: none; padding: 15px; background: #fff; border-top: 1px solid #eaeaea; }
.lesson-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee; }
.lesson-row:last-child { border-bottom: none; }
.lesson-actions a { margin-left: 8px; text-decoration: none; font-size: 14px; }
.btn-add-lesson { display: inline-block; margin-top: 10px; background: #007bff; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; }
.btn-add-lesson:hover { background: #0056b3; }
</style>

<div class="main-content" id="mainContent">
  <div class="courses-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i data-lucide="book-open" class="me-2"></i> My Courses</h2>
      <a href="upload_course.php" class="upload-btn btn btn-primary">
        <i data-lucide="upload"></i> Upload New Course
      </a>
    </div>

    <?php if (empty($courses)): ?>
      <p>No courses found. <a href="upload_course.php">Upload a new course</a>.</p>
    <?php else: ?>
      <?php foreach ($courses as $course): ?>
        <?php
          $stmt2 = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY id ASC");
          $stmt2->execute([$course['id']]);
          $lessons = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="course-row">
          <div class="course-header" onclick="toggleLessons(<?= $course['id'] ?>)">
            <div>
              <span class="course-title"><?= htmlspecialchars($course['title']) ?></span>
              <span class="course-meta">
                (<?= ucfirst($course['type']) ?> | 
                <?= $course['type'] === 'paid' ? '₦'.number_format($course['price'],2) : 'Free' ?> |
                <?= htmlspecialchars($course['category_id'] ?? 'Uncategorized') ?>

              </span>
            </div>
            <div class="course-actions">
              <a href="edit_course.php?id=<?= $course['id'] ?>" class="text-primary"><i data-lucide="edit-3"></i> Edit</a>
              <a href="delete_course.php?id=<?= $course['id'] ?>" class="text-danger" onclick="return confirm('Delete this course and all its lessons?')"><i data-lucide="trash-2"></i> Delete</a>
            </div>
          </div>

          <div class="course-lessons" id="lessons-<?= $course['id'] ?>">
            <?php if (!empty($lessons)): ?>
              <?php foreach ($lessons as $l): ?>
                <div class="lesson-row">
                  <div><i data-lucide="play-circle" class="me-1 text-primary"></i><?= htmlspecialchars($l['title']) ?></div>
                  <div class="lesson-actions">
                    <a href="edit_lesson.php?id=<?= $l['id'] ?>" class="text-primary"><i data-lucide="edit"></i> Edit</a>
                    <a href="delete_lesson.php?id=<?= $l['id'] ?>&course_id=<?= $course['id'] ?>" class="text-danger" onclick="return confirm('Delete this lesson?')"><i data-lucide="trash"></i> Delete</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-muted">No lessons yet for this course.</p>
            <?php endif; ?>
            <a href="add_lesson.php?course_id=<?= $course['id'] ?>" class="btn-add-lesson"><i data-lucide="plus"></i> Add Lesson</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include 'inc/script.php'; ?>
<script>
function toggleLessons(id) {
  const section = document.getElementById('lessons-' + id);
  section.style.display = (section.style.display === 'block') ? 'none' : 'block';
  if (typeof lucide !== 'undefined') lucide.createIcons();
}
if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
