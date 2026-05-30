<?php
// admin/add_test.php
error_reporting(E_ALL);
ini_set('display_errors',1);
session_start();
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';

if (!isset($_SESSION['user_id'])) die('Access denied');
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['instructor','admin','ceo'])) die('Access denied');

$lesson_id = intval($_GET['lesson_id'] ?? 0);
if (!$lesson_id) die('Missing lesson id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
    $question = trim($_POST['question'] ?? '');
    $answers = array_map('trim', [$_POST['a'] ?? '', $_POST['b'] ?? '', $_POST['c'] ?? '', $_POST['d'] ?? '']);
    $correct = intval($_POST['correct'] ?? 0);

    if ($question === '' || empty($answers[$correct])) {
        $error = "Question and valid correct answer required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO lesson_tests (lesson_id, question, answers, correct_index, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$lesson_id, $question, json_encode($answers, JSON_UNESCAPED_UNICODE), $correct]);
        $success = "Question added.";
    }
}

// fetch lesson title
$ls = $pdo->prepare("SELECT title, course_id FROM lessons WHERE id = ?");
$ls->execute([$lesson_id]);
$lesson = $ls->fetch(PDO::FETCH_ASSOC);
if (!$lesson) die('Lesson not found.');

$pageTitle = 'Add Test - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>
<div class="container p-4">
  <h3>Add Test for: <?= htmlspecialchars($lesson['title']) ?></h3>
  <?php if(!empty($error)): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>
  <?php if(!empty($success)): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?>
  <form method="POST">
    <?php echo csrf_input(); ?>
    <div class="mb-3">
      <label class="form-label">Question</label>
      <textarea name="question" class="form-control" rows="3" required></textarea>
    </div>
    <div class="mb-2"><label class="form-label">Answer A</label><input name="a" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Answer B</label><input name="b" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Answer C</label><input name="c" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Answer D</label><input name="d" class="form-control" required></div>
    <div class="mb-3">
      <label class="form-label">Correct Answer</label>
      <select name="correct" class="form-select">
        <option value="0">A</option>
        <option value="1">B</option>
        <option value="2">C</option>
        <option value="3">D</option>
      </select>
    </div>
    <button class="btn btn-primary">Save Question</button>
    <a class="btn btn-secondary" href="edit_course.php?id=<?= (int)$lesson['course_id'] ?>">Back to Course</a>
  </form>
</div>
<?php include __DIR__ . '/inc/script.php'; ?>
