<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "inc/db.php";

try {
    if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'instructor') {
        // Instructor: show only their courses
        $stmt = $pdo->prepare("
            SELECT c.*, u.name AS instructor_name, cat.name AS category_name
            FROM courses c
            JOIN users u ON c.instructor_id = u.id
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE c.instructor_id = ?
            ORDER BY c.id DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        // Public / other roles: show all courses
        $stmt = $pdo->prepare("
            SELECT c.*, u.name AS instructor_name, cat.name AS category_name
            FROM courses c
            JOIN users u ON c.instructor_id = u.id
            LEFT JOIN categories cat ON c.category_id = cat.id
            ORDER BY c.id DESC
        ");
        $stmt->execute();
    }
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB error in courses-view.php: " . $e->getMessage());
    $courses = [];
}

$pageTitle = 'Courses - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>

<div class="main-content" id="mainContent">

    <!-- Page Header -->
    <div class="bg-body-light py-4 mb-4">
        <div class="container text-center">
            <h1 class="h3 fw-bold mb-1">Available Courses</h1>
            <p class="fs-sm text-muted">Explore free and premium courses by expert instructors.</p>
            <input type="text" id="courseSearch" class="form-control w-50 mx-auto mt-2" placeholder="Search by title, category, or instructor">
        </div>
    </div>

    <!-- Courses Section -->
    <section id="courses" class="courses section">
        <div class="container">
            <div class="row gy-4" id="coursesContainer">

                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $course): 
                        $thumb = $course['thumbnail'] ?? '';
                        $thumbnail = '../assets/img/default-course.jpg';
                        if (!empty($thumb)) {
                            if (preg_match('#^(https?://|../|assets/uploads/)#i', $thumb)) {
                                $thumbnail = $thumb;
                            } else {
                                $thumbPath = '../assets/uploads/thumbnails/' . ltrim($thumb, '/');
                                if (file_exists(__DIR__ . '/' . $thumbPath)) $thumbnail = $thumbPath;
                            }
                        }

                        $categoryName = $course['category_name'] ?? 'Uncategorized';
                        $instructorName = $course['instructor_name'] ?? 'Unknown';
                    ?>

                    <div class="col-lg-4 col-md-6 d-flex align-items-stretch course-card" 
                         data-title="<?= strtolower($course['title']) ?>" 
                         data-category="<?= strtolower($categoryName) ?>" 
                         data-instructor="<?= strtolower($instructorName) ?>">

                        <div class="card shadow-sm w-100">
                            <img src="<?= htmlspecialchars($thumbnail) ?>" 
                                 class="card-img-top" 
                                 alt="<?= htmlspecialchars($course['title']) ?>" 
                                 onerror="this.src='../assets/img/default-course.jpg'" 
                                 style="max-height:220px; object-fit:cover; width:100%;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($categoryName)) ?></span>
                                    <span class="fw-bold"><?= $course['type'] === 'paid' ? '₦' . number_format($course['price'],2) : 'Free' ?></span>
                                </div>

                                <h5 class="card-title">
                                    <a href="course-details.php?id=<?= (int)$course['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($course['title']) ?>
                                    </a>
                                </h5>

                                <p class="card-text text-muted small">
                                    <?= htmlspecialchars(mb_substr(strip_tags($course['description']),0,140)) ?>...
                                </p>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="d-flex align-items-center">
                                        <img src="../assets/img/trainers/default.jpg" class="rounded-circle me-2" width="40" height="40" style="object-fit:cover;" alt="Trainer">
                                        <a href="course-details.php?id=<?= (int)$course['id'] ?>" class="fw-semibold text-dark">
                                            <?= htmlspecialchars($instructorName) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-person"></i> <?= rand(10,100) ?> &nbsp;
                                        <i class="bi bi-heart"></i> <?= rand(5,100) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <p class="text-muted mb-3">No courses available yet.</p>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'instructor'): ?>
                            <a href="upload_course.php" class="btn btn-primary">Upload your first course</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </section>
</div>

<?php include 'inc/script.php'; ?>

<!-- Client-side Search Filter -->
<script>
const searchInput = document.getElementById('courseSearch');
const courseCards = document.querySelectorAll('.course-card');

searchInput.addEventListener('input', () => {
    const term = searchInput.value.toLowerCase().trim();
    courseCards.forEach(card => {
        const title = card.dataset.title;
        const category = card.dataset.category;
        const instructor = card.dataset.instructor;
        card.style.display = (title.includes(term) || category.includes(term) || instructor.includes(term)) ? 'block' : 'none';
    });
});
</script>

</body>
</html>
