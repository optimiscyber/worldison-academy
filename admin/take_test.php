<?php
require_once "inc/db.php";
require_once "../inc/auth.php";
require_once __DIR__ . '/../inc/csrf.php';

$user_id = $_SESSION['user_id'] ?? 0;
$lesson_id = intval($_GET['lesson_id'] ?? 0);
if(!$lesson_id) die("Invalid lesson ID.");

// fetch lesson
$lesson = $pdo->prepare("SELECT id,course_id,title FROM lessons WHERE id=?");
$lesson->execute([$lesson_id]);
$lesson = $lesson->fetch(PDO::FETCH_ASSOC);
if(!$lesson) die("Lesson not found.");

$course_id = $lesson['course_id'];

// fetch test
$tests_stmt = $pdo->prepare("SELECT * FROM lesson_tests WHERE lesson_id=?");
$tests_stmt->execute([$lesson_id]);
$tests = $tests_stmt->fetchAll(PDO::FETCH_ASSOC);

// *** SUBMIT TEST ***
if($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();

    $answers = [];
    $score = 0;

    foreach($tests as $q) {
        $qid = $q['id'];
        $ans = $_POST["q$qid"] ?? null;

        $answers[$qid] = $ans;

        if($ans !== null && $ans == $q['correct_index']) {
            $score++;
        }
    }

    // save results
    $json = json_encode($answers);
    $pdo->prepare("
        INSERT INTO lesson_test_results(user_id, lesson_id, test_data, score)
        VALUES(?,?,?,?)
        ON DUPLICATE KEY UPDATE 
           test_data=?, score=?, submitted_at=NOW()
    ")->execute([$user_id,$lesson_id,$json,$score,$json,$score]);

    // mark lesson completed
    $pdo->prepare("UPDATE lesson_progress 
                   SET completed=1, completed_at=NOW()
                   WHERE user_id=? AND lesson_id=?")
        ->execute([$user_id,$lesson_id]);

    // find next lesson
    $next = $pdo->prepare("
        SELECT id FROM lessons 
        WHERE course_id=? 
          AND order_no > (SELECT order_no FROM lessons WHERE id=?)
        ORDER BY order_no ASC 
        LIMIT 1
    ");
    $next->execute([$course_id,$lesson_id]);
    $next_id = $next->fetchColumn();

    if ($next_id) {
        header("Location: watch_lesson.php?id=$next_id");
    } else {
        header("Location: courses-view.php?id=".$course_id);
    }
    exit;
}
?>

<?php include __DIR__.'/inc/header.php'; ?>
<?php include __DIR__.'/inc/sidebar.php'; ?>
<?php include __DIR__.'/inc/navbar.php'; ?>

<div class="container-fluid pt-4 px-4">
<div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <a href="courses-view.php" class="btn btn-outline-secondary btn-sm">
            <i data-lucide="arrow-left"></i> Back to Courses
          </a>
        </div>
    </div>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="bg-white p-4 rounded shadow-sm">

<h3 class="fw-bold">Test: <?= htmlspecialchars($lesson['title']) ?></h3>
<hr>

<?php if(empty($tests)): ?>
    <div class="alert alert-warning">No test available for this lesson.</div>
<?php else: ?>
<form method="POST">
    <?php echo csrf_input(); ?>

<?php foreach($tests as $q): 
        $answers = json_decode($q['answers'],true);
?>
<div class="mb-4 p-3 border rounded">
    <p class="fw-semibold"><?= htmlspecialchars($q['question']) ?></p>

    <?php foreach($answers as $i=>$ans): ?>
        <label class="d-block mb-1">
            <input type="radio" name="q<?= $q['id'] ?>" value="<?= $i ?>" required>
            <?= chr(65+$i) ?>. <?= htmlspecialchars($ans) ?>
        </label>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<button class="btn btn-primary">Submit Test</button>
</form>
<?php endif; ?>

</div>
</div>
</div>
</div>

<?php include __DIR__.'/inc/script.php'; ?>
