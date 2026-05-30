<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "inc/db.php";
require_once "../inc/auth.php";

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$lesson_id = intval($_GET['id'] ?? 0);
if (!$lesson_id) die("Invalid lesson ID.");

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

$ids = array_column($all_lessons,'id');
$current_index = array_search($lesson_id,$ids);

$prev_id = $all_lessons[$current_index - 1]['id'] ?? null;
$next_id = $all_lessons[$current_index + 1]['id'] ?? null;

// Check test
$testCheck = $pdo->prepare("SELECT COUNT(*) FROM lesson_tests WHERE lesson_id=?");
$testCheck->execute([$lesson_id]);
$hasTest = $testCheck->fetchColumn() > 0;

// Next link
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
if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/))([^&?/]+)#', $video, $m)) {
    $ytID = $m[1];
}

// Detect lesson type
$hasVideo = (!empty($video) || !empty($ytID));
$hasText = !empty($lesson['content']);

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

<!-- SIDEBAR -->
<div class="col-lg-3 mb-3">
  <div class="bg-light p-3 rounded shadow-sm">
    <h5 class="fw-bold mb-3"><?= htmlspecialchars($lesson['course_title']) ?></h5>

    <ul class="list-group">
      <?php foreach ($all_lessons as $index => $l):
          $locked = ($index > 0 && !$all_lessons[$index-1]['completed']);
      ?>
      <a class="list-group-item list-group-item-action 
                <?= $l['id']==$lesson_id?'active':'' ?> 
                <?= $locked?'disabled':'' ?>"
         href="<?= $locked?'#':'watch_lesson.php?id='.$l['id'] ?>">
         <?= htmlspecialchars($l['title']) ?>
         <?= $l['completed'] ? " ✅" : "" ?>
      </a>
      <?php endforeach; ?>
    </ul>

  </div>
</div>

<!-- MAIN LESSON AREA -->
<div class="col-lg-9">
<style>
#readingProgress {
    position: fixed;
    top: 0; left: 0;
    height: 4px;
    background: #007bff;
    width: 0%;
    z-index: 99999;
}
</style>
<div id="readingProgress"></div>

<script>
document.addEventListener("scroll", function () {
    let scrollTop = document.documentElement.scrollTop;
    let height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    let progress = (scrollTop / height) * 100;
    document.getElementById("readingProgress").style.width = progress + "%";
});
</script>

<div class="bg-light p-4 rounded shadow-sm">

<h3><?= htmlspecialchars($lesson['title']) ?></h3>
<p class="text-muted">Instructor: <?= htmlspecialchars($lesson['instructor_name']) ?></p>

<!-- ========================= -->
<!-- TEXT-ONLY LESSON HANDLING -->
<!-- ========================= -->

<?php if (!$hasVideo && $hasText): ?>
    <div class="alert alert-info">This is a text-only lesson.</div>
<?php endif; ?>

<!-- VIDEO SECTION -->
<?php if ($hasVideo): ?>
    <?php if ($ytID): ?>
        <div class="ratio ratio-16x9 mb-4">
            <iframe src="https://www.youtube.com/embed/<?= $ytID ?>?rel=0" allowfullscreen></iframe>
        </div>
    <?php elseif ($video): ?>
        <div class="ratio ratio-16x9 mb-4">
            <video class="w-100 h-100" controls>
                <source src="<?= htmlspecialchars($video) ?>">
            </video>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- NOTES / TEXT CONTENT -->
<!-- NOTES / TEXT CONTENT -->
<?php if(!empty($lesson['content'])): ?>
<div class="bg-white p-3 rounded shadow-sm mb-3">

    <h4 class="fw-bold mb-3">Lesson Notes</h4>

    <!-- Table of Contents -->
    <div id="toc" class="mb-3 p-3 border rounded bg-light"></div>

    <!-- Content -->
    <div id="lessonContent">
        <?= $lesson['content'] ?>
    </div>

    <?php if (!$progress['completed'] ?? false): ?>
    <button id="textMarkComplete" class="btn btn-success mt-3">
        Mark Lesson Complete
    </button>
    <?php endif; ?>

    </div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const content = document.getElementById("lessonContent");
    const toc = document.getElementById("toc");

    if (!content) return;

    let headings = content.querySelectorAll("h1, h2, h3");
    if (headings.length === 0) return;

    // Build TOC
    let tocList = "<h5 class='fw-bold'>📘 Table of Contents</h5><ul>";
    headings.forEach((h, i) => {
        let id = "sec_" + i;
        h.id = id;
        tocList += `<li><a href="#${id}">${h.innerText}</a></li>`;
    });
    tocList += "</ul>";
    toc.innerHTML = tocList;

    // Collapsible Sections
    headings.forEach((h) => {
        let wrapper = document.createElement("div");
        wrapper.classList.add("mb-4");

        let toggleBtn = document.createElement("button");
        toggleBtn.classList.add("btn", "btn-sm", "btn-outline-primary", "mb-2");
        toggleBtn.innerText = "Toggle Section";

        let sectionBlock = document.createElement("div");
        sectionBlock.classList.add("border", "rounded", "p-3");

        // Move heading + next paragraphs until next heading
        sectionBlock.appendChild(h.cloneNode(true));

        let next = h.nextElementSibling;
        while (next && !["H1","H2","H3"].includes(next.tagName)) {
            let toMove = next;
            next = next.nextElementSibling;
            sectionBlock.appendChild(toMove);
        }

        wrapper.appendChild(toggleBtn);
        wrapper.appendChild(sectionBlock);
        toggleBtn.addEventListener("click", () => {
            sectionBlock.classList.toggle("d-none");
        });

        content.insertBefore(wrapper, h);
    });
});
</script>
<?php endif; ?>



<!-- NAVIGATION + CERTIFICATE -->
<div class="d-flex justify-content-between align-items-center mt-4">

    <?php if($prev_id): ?>
        <a class="btn btn-outline-secondary" href="watch_lesson.php?id=<?= $prev_id ?>">&laquo; Previous</a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>

    <div>
    <?php if ($next_id): ?>
        <a class="btn btn-primary" href="<?= $nextLink ?>">Next &raquo;</a>

    <?php else: ?>
        <!-- LAST LESSON -->
        <?php if ($all_completed): ?>
        <div class="p-3 border rounded bg-light text-center">
            <h4 class="fw-bold mb-3">🎉 Course Completed – Certificate</h4>

            <?php if (!$certificate): ?>
                <a href="cert_request.php?course_id=<?= $course_id ?>" class="btn btn-success">
                    Request Certificate
                </a>

            <?php else: ?>
                <p class="mb-2">Certificate Status: <strong><?= ucfirst($certificate['status']) ?></strong></p>

                <?php if ($certificate['status']==='rejected' && !empty($certificate['reason'])): ?>
                <div class="alert alert-danger">
                    <strong>Reason:</strong> <?= htmlspecialchars($certificate['reason']) ?>
                </div>
                <?php endif; ?>

                <?php if ($certificate['status']==='approved' && $certificate['certificate_url']): ?>
                <a href="download_certificate.php?id=<?= $certificate['id'] ?>" class="btn btn-primary">
                    Download Certificate
                </a>
                <?php endif; ?>

                <?php if ($certificate['status']==='pending'): ?>
                <div class="alert alert-info mt-2">
                    Your certificate request is being reviewed.
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php else: ?>
            <button class="btn btn-secondary" disabled>Complete all lessons to unlock Certificate</button>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>

</div>
</div>
</div>
</div>

<script>
(function(){
    const lessonId = <?= json_encode($lesson_id) ?>;
    const markUrl = 'ajax/mark_complete.php';

    async function markCompleted() {
        try {
            const response = await fetch(markUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'lesson_id=' + encodeURIComponent(lessonId)
            });
            const data = await response.json();

            if (!data.ok) {
                alert('Error: ' + (data.error || 'Unknown error'));
                return;
            }

            // Disable text button if exists
            const textBtn = document.getElementById('textMarkComplete');
            if(textBtn) {
                textBtn.disabled = true;
                textBtn.innerText = 'Marked';
            }

            // Disable YouTube button if exists
            const ytBtn = document.getElementById('markCompleteBtn');
            if(ytBtn) {
                ytBtn.disabled = true;
                ytBtn.innerText = 'Marked';
            }

            // Show alert if course completed
            if(data.course_completed){
                alert('🎉 Congrats — you completed all lessons for this course!');
                location.reload();
            }

        } catch(err) {
            console.error(err);
            alert('Failed to mark lesson complete.');
        }
    }

    // Text-only / regular button
    const textBtn = document.getElementById('textMarkComplete');
    if(textBtn){
        textBtn.addEventListener('click', markCompleted);
    }

    // Video auto-complete
    const video = document.querySelector('video');
    if(video){
        video.addEventListener('ended', markCompleted, {once:true});
    }

    // YouTube button (if exists)
    <?php if ($ytID): ?>
    const c = document.createElement('div');
    c.style.marginTop = '12px';
    c.innerHTML = '<button id="markCompleteBtn" class="btn btn-success btn-sm">Mark Lesson Complete</button>';
    const player = document.querySelector('.ratio');
    if(player) player.parentNode.insertBefore(c, player.nextSibling);

    const ytBtn = document.getElementById('markCompleteBtn');
    if(ytBtn){
        ytBtn.addEventListener('click', markCompleted);
    }
    <?php endif; ?>

    // Scroll auto-complete for text-only lessons
    if(!video && !<?= json_encode($ytID) ?>){
        let done = false;
        window.addEventListener('scroll', function(){
            const scrollTop = document.documentElement.scrollTop;
            const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const progress = scrollTop / scrollHeight;

            if(!done && progress > 0.7){
                done = true;
                markCompleted();
            }
        });
    }

})();
</script>


<?php include __DIR__.'/inc/script.php'; ?>
