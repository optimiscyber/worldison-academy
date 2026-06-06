<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "inc/db.php";
require_once "../inc/auth.php";
require_once __DIR__ . '/../app/Repositories/LessonRepository.php';
require_once __DIR__ . '/../app/Repositories/EnrollmentRepository.php';
require_once __DIR__ . '/../app/Repositories/ProgressRepository.php';
require_once __DIR__ . '/../app/Repositories/CourseRepository.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$lesson_id = intval($_GET['id'] ?? 0);
if (!$lesson_id) die("Invalid lesson ID.");

$useNewBackend = false;
if (class_exists('LessonRepository') && class_exists('EnrollmentRepository') && class_exists('ProgressRepository') && class_exists('CourseRepository')) {
    $lessonRepo = new LessonRepository($pdo);
    $enrollmentRepo = new EnrollmentRepository($pdo);
    $progressRepo = new ProgressRepository($pdo);
    $courseRepo = new CourseRepository($pdo);

    $lesson = $lessonRepo->getLessonById($lesson_id);
    if ($lesson) {
        $course_id = $lesson['course_id'];
        $progress = $progressRepo->getProgressByLesson($user_id, $lesson_id);
        if ($progress !== false) {
            $isEnrolled = $enrollmentRepo->isEnrolled($user_id, $course_id);
            $all_lessons = $lessonRepo->getLessonsByCourseId($course_id);
            $lesson_progress_rows = $progressRepo->getLessonsProgress($user_id, array_column($all_lessons, 'id'));
            $progress_map = [];
            foreach ($lesson_progress_rows as $row) {
                $progress_map[(int)$row['lesson_id']] = (int)$row['completed'];
            }
            foreach ($all_lessons as &$l) {
                $l['completed'] = $progress_map[(int)$l['id']] ?? 0;
            }
            unset($l);

            $attachments = $lessonRepo->getLessonAttachments($lesson_id);
            $course_type = strtolower(trim($lesson['type'] ?? 'free'));
            $prev_id = null;
            $next_id = null;
            $ids = array_column($all_lessons, 'id');
            $current_index = array_search($lesson_id, $ids);
            if ($current_index !== false) {
                $prev_id = $all_lessons[$current_index - 1]['id'] ?? null;
                $next_id = $all_lessons[$current_index + 1]['id'] ?? null;
            }
            $hasTest = $lessonRepo->hasLessonTest($lesson_id);
            $total_lessons = $courseRepo->getTotalLessonCount($course_id);
            $completed_lessons = $progressRepo->getCompletedLessonCount($user_id, $course_id);
            $useNewBackend = true;
        }
    }
}

if (!$useNewBackend) {
    // Legacy fallback path: preserve existing SQL behavior and writes
    require_once __DIR__ . '/../app/Services/LessonService.php';
    require_once __DIR__ . '/../app/Services/EnrollmentService.php';

    $lessonService = new LessonService($pdo);
    $enrollmentService = new EnrollmentService($pdo);
    $progressRepository = new ProgressRepository($pdo);

    // Fetch lesson + course
    $stmt = $pdo->prepare("
        SELECT l.*, c.title AS course_title, c.id AS course_id, c.type, u.name AS instructor_name
        FROM lessons l
        JOIN courses c ON l.course_id=c.id
        JOIN users u ON c.instructor_id=u.id
        WHERE l.id=?
    ");
    $stmt->execute([$lesson_id]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lesson) die("Lesson not found.");

    $course_id = $lesson['course_id'];
    $_SESSION['last_course_id'] = $course_id;

    // Use actual schema field for course type and fallback if not present
    $course_type = strtolower(trim($lesson['type'] ?? 'free'));

    // Check enrollment
    $enr = $pdo->prepare("SELECT 1 FROM enrollments WHERE user_id=? AND course_id=?");
    $enr->execute([$user_id, $course_id]);
    $isEnrolled = (bool)$enr->fetchColumn();

    if ($course_type === 'paid' && !$isEnrolled) {
        echo "<script>alert('You must enroll in this course first!');window.location='view_courses.php?id={$course_id}';</script>";
        exit;
    }

    // Progress
    $prog = $pdo->prepare("SELECT completed FROM lesson_progress WHERE user_id=? AND lesson_id=?");
    $prog->execute([$user_id,$lesson_id]);
    $progress = $prog->fetch(PDO::FETCH_ASSOC);

    if (!$progress) {
        $pdo->prepare("
            INSERT INTO lesson_progress (user_id, lesson_id, course_id, completed, completed_at)
            VALUES (?, ?, ?, 0, NULL)
        ")->execute([$user_id, $lesson_id, $course_id]);

        // Initialize $progress to avoid warnings
        $progress = ['completed' => 0];
    }

    // Sidebar lessons
    $lessons_stmt = $pdo->prepare("
        SELECT l.id, l.title, l.order_no,
            COALESCE(lp.completed,0) AS completed
        FROM lessons l
        LEFT JOIN lesson_progress lp 
          ON lp.lesson_id=l.id AND lp.user_id=?
        WHERE l.course_id=?
        ORDER BY l.order_no ASC, l.id ASC
    ");
    $lessons_stmt->execute([$user_id,$course_id]);
    $all_lessons = $lessons_stmt->fetchAll(PDO::FETCH_ASSOC);

    $attachments_stmt = $pdo->prepare("SELECT id, file_name, file_path, file_type FROM lesson_attachments WHERE lesson_id = ? ORDER BY created_at ASC");
    $attachments_stmt->execute([$lesson_id]);
    $attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);

    $ids = array_column($all_lessons,'id');
    $current_index = array_search($lesson_id,$ids);

    $prev_id = $all_lessons[$current_index - 1]['id'] ?? null;
    $next_id = $all_lessons[$current_index + 1]['id'] ?? null;

    // Check test
    $testCheck = $pdo->prepare("SELECT COUNT(*) FROM lesson_tests WHERE lesson_id=?");
    $testCheck->execute([$lesson_id]);
    $hasTest = $testCheck->fetchColumn() > 0;

    // Certificate progress
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id=?");
    $stmt->execute([$course_id]);
    $total_lessons = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM lesson_progress
        WHERE user_id=? AND course_id=? AND completed=1
    ");
    $stmt->execute([$user_id, $course_id]);
    $completed_lessons = (int)$stmt->fetchColumn();
}

if ($hasTest) {
    $nextLink = "take_test.php?lesson_id=" . $lesson_id;
} elseif ($next_id) {
    $nextLink = "watch_lesson.php?id=" . $next_id;
} else {
    $nextLink = "#";
}

// VIDEO PARSING
$video = trim($lesson['video_url']);
$ytID = null;
$ytOriginalUrl = null;
$ytEmbedUrl = null;
if (!empty($video)) {
    $ytOriginalUrl = $video;
    $videoUrl = trim($video);
    $videoUrl = preg_replace('#^https?://#i', 'https://', $videoUrl);

    $parts = parse_url($videoUrl);
    $host = strtolower($parts['host'] ?? '');
    $host = preg_replace('#^www\.?#', '', $host);
    $path = $parts['path'] ?? '';
    $query = $parts['query'] ?? '';

    if ($host === 'youtu.be') {
        $ytID = ltrim($path, '/');
    } elseif (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
        if (preg_match('#^/embed/([A-Za-z0-9_-]{11})#', $path, $m)
            || preg_match('#^/shorts/([A-Za-z0-9_-]{11})#', $path, $m)
        ) {
            $ytID = $m[1];
        } elseif (strpos($path, '/watch') === 0) {
            parse_str($query, $queryParams);
            $ytID = $queryParams['v'] ?? null;
        } elseif (preg_match('#^/([A-Za-z0-9_-]{11})$#', $path, $m)) {
            $ytID = $m[1];
        }
    }

    if (!empty($ytID) && preg_match('#^[A-Za-z0-9_-]{11}$#', $ytID)) {
        $origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
        $ytEmbedUrl = 'https://www.youtube.com/embed/' . $ytID . '?rel=0&modestbranding=1&playsinline=1';
    }
}

// Detect lesson type
$hasVideo = !empty($video);
$hasText = !empty($lesson['content']);

// Certificate progress
$total_lessons = $lesson['course_progress']['total'] ?? 0;
$completed_lessons = $lesson['course_progress']['completed'] ?? 0;

$all_completed = ($completed_lessons >= $total_lessons);

$certStmt = $pdo->prepare("
    SELECT id, status, certificate_url, reason 
    FROM certificate_requests
    WHERE user_id=? AND course_id=?
    LIMIT 1
");
$certStmt->execute([$user_id, $course_id]);
$certificate = $certStmt->fetch(PDO::FETCH_ASSOC);

include __DIR__.'/inc/header.php';
include __DIR__.'/inc/sidebar.php';
include __DIR__.'/inc/navbar.php';
?>

<div class="container-fluid pt-4 px-4">

<div class="d-flex justify-content-between align-items-center mb-3">
  <a href="courses-view.php" class="btn btn-outline-secondary btn-sm">
    <i data-lucide="arrow-left"></i> Back to Courses
  </a>
</div>

<div class="row">

<?php include __DIR__.'/inc/watch_lesson_sidebar.php'; ?>
<?php include __DIR__.'/inc/watch_lesson_main.php'; ?>

<?php include __DIR__.'/inc/watch_lesson_script.php'; ?>
