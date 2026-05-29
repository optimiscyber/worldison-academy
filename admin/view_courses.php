<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "inc/db.php";
require_once "../inc/auth.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch course info
$stmt = $pdo->prepare("
    SELECT c.*, u.name AS instructor_name, u.profile_picture, u.bio
    FROM courses c
    JOIN users u ON c.instructor_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) die("Course not found.");

// Fetch course outlines
$outlines_stmt = $pdo->prepare("SELECT * FROM course_outline WHERE course_id = ? ORDER BY order_no ASC, id ASC");
$outlines_stmt->execute([$course_id]);
$outlines = $outlines_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch lessons
$lessons_stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? AND status = 'published' ORDER BY order_no ASC, id ASC");
$lessons_stmt->execute([$course_id]);
$lessons = $lessons_stmt->fetchAll(PDO::FETCH_ASSOC);

// Map lessons by outline
$lessons_by_outline = [];
foreach ($lessons as $lesson) {
    $oid = $lesson['outline_id'] ?? 0; // 0 = no module
    $lessons_by_outline[$oid][] = $lesson;
}

// Fetch enrollment & payment info
$enrollment_stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
$enrollment_stmt->execute([$user_id, $course_id]);
$is_enrolled = $enrollment_stmt->fetch(PDO::FETCH_ASSOC);

$payment_status = null;
if ($course['type'] === 'paid') {
    $payment_stmt = $pdo->prepare("SELECT payment_status FROM transactions WHERE user_id = ? AND course_id = ?");
    $payment_stmt->execute([$user_id, $course_id]);
    $transaction = $payment_stmt->fetch(PDO::FETCH_ASSOC);
    $payment_status = $transaction['payment_status'] ?? null;
}

// Fetch lesson progress
$progress_stmt = $pdo->prepare("SELECT lesson_id, completed FROM lesson_progress WHERE user_id = ? AND course_id = ?");
$progress_stmt->execute([$user_id, $course_id]);
$progress_data = $progress_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Calculate progress
$total_lessons = count($lessons);
$completed_lessons = count(array_filter($progress_data));
$progress_percent = $total_lessons ? round(($completed_lessons / $total_lessons) * 100) : 0;
$all_completed = ($completed_lessons == $total_lessons);

// Check certificate
$certificate = null;
if ($all_completed) {
    $cert_stmt = $pdo->prepare("SELECT * FROM certificates WHERE user_id = ? AND course_id = ?");
    $cert_stmt->execute([$user_id, $course_id]);
    $certificate = $cert_stmt->fetch(PDO::FETCH_ASSOC);
}

// Access check
$can_access = ($is_enrolled || $course['type'] === 'free' || $payment_status === 'success');
?>

<?php
$pageTitle = 'Course Details - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>

<div class="container-fluid pt-4 px-4">
    <div class="row">
        <div class="col-12 col-lg-8 mx-auto">

            <!-- Course Thumbnail -->
            <?php $thumbnail = $course['thumbnail'] ?? 'assets/img/default-course.jpg'; ?>
            <img src="<?= htmlspecialchars($thumbnail) ?>" 
                 alt="<?= htmlspecialchars($course['title']) ?>" 
                 class="img-fluid rounded mb-4 shadow-sm">

                <!-- Course Info -->
                <div class="bg-light rounded p-4 shadow-sm mb-4">
                    <h2 class="fw-bold"><?= htmlspecialchars($course['title']) ?></h2>
                    <p class="text-muted mb-1">By <?= htmlspecialchars($course['instructor_name']) ?></p>

                    <!-- Quill content display -->
                    <div class="course-description">
                        <?= $course['description'] ?>
                    </div>

                    <?php if ($course['type'] === 'free'): ?>
                        <span class="badge bg-success">Free Course</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Paid Course</span>
                        <p class="mt-2"><strong>Price:</strong> ₦<?= number_format($course['price'], 2) ?></p>
                    <?php endif; ?>
                </div>


            <!-- Course Progress -->
            <?php if ($can_access && $total_lessons > 0): ?>
                <div class="my-3">
                    <label>Course Progress: <?= $progress_percent ?>%</label>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?= $progress_percent ?>%;" aria-valuenow="<?= $progress_percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            <?php endif; ?>
<!-- Course Outlines & Lessons -->
<div class="bg-light rounded p-4 shadow-sm">
    <?php if ($can_access): ?>
        <h4 class="mb-3">Course Outline</h4>

        <?php
        $prev_completed = true;

        // Lessons without module
        if (!empty($lessons_by_outline[0])): ?>
            <div class="mb-3">
                <h5>No Module</h5>
                <ul class="list-group mb-3">
                    <?php foreach ($lessons_by_outline[0] as $lesson):
                        $completed = !empty($progress_data[$lesson['id']]);
                        $disabled = !$prev_completed ? 'disabled' : '';
                        $prev_completed = $completed;
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($lesson['title']) ?></strong>
                                <?php if ($completed): ?>
                                    <span class="badge bg-success ms-2">Completed</span>
                                <?php endif; ?>
                                <?php if (!empty($lesson['duration'])): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($lesson['duration']) ?></small>
                                <?php endif; ?>
                            </div>
                            <a href="watch_lesson.php?id=<?= $lesson['id'] ?>" class="btn btn-sm btn-primary <?= $disabled ?>">Watch</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php foreach ($outlines as $outline): ?>
            <div class="mb-3">
                <h5><?= htmlspecialchars($outline['title']) ?></h5>
                <?php if (!empty($lessons_by_outline[$outline['id']])): ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($lessons_by_outline[$outline['id']] as $lesson):
                            $completed = !empty($progress_data[$lesson['id']]);
                            $disabled = !$prev_completed ? 'disabled' : '';
                            $prev_completed = $completed;
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($lesson['title']) ?></strong>
                                    <?php if ($completed): ?>
                                        <span class="badge bg-success ms-2">Completed</span>
                                    <?php endif; ?>
                                    <?php if (!empty($lesson['duration'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($lesson['duration']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <a href="watch_lesson.php?id=<?= $lesson['id'] ?>" class="btn btn-sm btn-primary <?= $disabled ?>">Watch</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No lessons in this module.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

<!-- Certificate Section -->
<?php if ($all_completed): ?>
    <div class="mt-4">
        <?php
        // Fetch latest certificate request
        $cert_req_stmt = $pdo->prepare("SELECT * FROM certificate_requests WHERE user_id = ? AND course_id = ? ORDER BY id DESC LIMIT 1");
        $cert_req_stmt->execute([$user_id, $course_id]);
        $cert_request = $cert_req_stmt->fetch(PDO::FETCH_ASSOC);
        ?>

        <?php if (!$cert_request): ?>
            <!-- No request yet -->
            <a href="cert_request.php?course_id=<?= $course_id ?>" class="btn btn-success">Request Certificate</a>

        <?php else: ?>
            <p>Certificate Status: <strong><?= ucfirst($cert_request['status']) ?></strong></p>

            <?php if ($cert_request['status'] === 'approved'): ?>
                <!-- Use download_certificate.php instead of direct URL -->
                <a href="download_certificate.php?id=<?= $cert_request['id'] ?>" class="btn btn-primary">Download Certificate</a>

            <?php elseif ($cert_request['status'] === 'pending'): ?>
                <div class="alert alert-info mt-2">Your certificate request is being reviewed.</div>

            <?php elseif ($cert_request['status'] === 'rejected'): ?>
                <div class="alert alert-danger mt-2">
                    Certificate request rejected.
                    <?php if (!empty($cert_request['reason'])): ?>
                        <br><strong>Reason:</strong> <?= htmlspecialchars($cert_request['reason']) ?>
                    <?php endif; ?>
                </div>
                <a href="cert_request.php?course_id=<?= $course_id ?>" class="btn btn-warning mt-2">Request Again</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>



    <?php else: ?>
        <div class="alert alert-info">
            <p>You are not enrolled in this course.</p>
            <?php if ($course['type'] === 'paid'): ?>
                <a href="../payment/checkout.php?course_id=<?= $course_id ?>" class="btn btn-success">Purchase Course</a>
            <?php else: ?>
                <a href="enroll.php?course_id=<?= $course_id ?>" class="btn btn-primary">Enroll for Free</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

        </div>
    </div>
</div>

<?php include 'inc/script.php'; ?>
