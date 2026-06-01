<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "inc/db.php";

try {
    // Ensure PDO throws exceptions (recommended to have in inc/db.php)
    // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Prepare queries with category name via LEFT JOIN
    if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'instructor') {
        // Instructor: show their courses (including drafts)
        $stmt = $pdo->prepare("
             SELECT c.*, 
               u.name AS instructor_name,
               u.profile_picture,
               cat.name AS category_name
            FROM courses c
            JOIN users u ON c.instructor_id = u.id
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE c.instructor_id = ?
            ORDER BY c.id DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        // Public visitors: show all published + test courses
        $stmt = $pdo->prepare("
             SELECT c.*, 
               u.name AS instructor_name,
               u.profile_picture,
               cat.name AS category_name
            FROM courses c
            JOIN users u ON c.instructor_id = u.id
            LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.status = 'published'
        ORDER BY c.id DESC
        ");
        $stmt->execute();
    }

    // Safe fetch: if $stmt is set we'll fetch, otherwise fallback to empty array
    $courses = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

} catch (PDOException $e) {
    error_log("DB error in courses-view.php: " . $e->getMessage());
    $courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Courses - Worldison Academy</title>
  <?php include 'inc/header_public.php'; // header with nav ?>
</head>
<body>

<main class="main">

  <!-- Page Title -->
  <div class="page-title" data-aos="fade">
    <div class="heading text-center">
      <div class="container">
        <h1>Available Courses</h1>
        <p>Explore our free and premium courses by expert instructors.</p>
      </div>
    </div>
  </div>

  <!-- Courses Section -->
  <section id="courses" class="courses section">
    <div class="container">
      <div class="row gy-4">

      <?php if (!empty($courses)): ?>
            <?php foreach ($courses as $course): 
              ?>
              
              <?php
                $thumbDb = $course['thumbnail'] ?? null;
                $thumbnail = resolveWebImagePath($thumbDb, 'assets/uploads/thumbnails', 'assets/img/default-course.jpg');

                // Safe category
                $categoryName = !empty($course['category_name']) ? $course['category_name'] : 'Uncategorized';
            ?>

            <div class="col-lg-4 col-md-6 d-flex align-items-stretch">
              <div class="course-item shadow-sm rounded-3 w-100">
                <img 
                  src="<?= htmlspecialchars($thumbnail) ?>" 
                  class="img-fluid rounded-top" 
                  alt="<?= htmlspecialchars($course['title']) ?>"
                  onerror="this.src='assets/img/default-course.jpg'"
                  style="max-height:220px; object-fit:cover; width:100%;"
                >
                
                <div class="course-content p-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <p class="category mb-0"><?= htmlspecialchars(ucfirst($categoryName)) ?></p>
                    <p class="price mb-0">
                      <?= $course['type'] === 'paid' ? '₦' . number_format($course['price'], 2) : 'Free' ?>
                    </p>
                  </div>

                  <h5 class="fw-bold mb-2">
                    <a href="admin/course-details.php?id=<?= (int)$course['id'] ?>" class="text-decoration-none">
                      <?= htmlspecialchars($course['title']) ?>
                    </a>
                  </h5>

                  <p class="description small text-muted mb-3">
                    <?= htmlspecialchars(safe_substr(strip_tags($course['description']), 0, 140)) ?>...
                  </p>

                  <div class="trainer d-flex justify-content-between align-items-center">
                    <?php
                    $profileImage = resolveWebImagePath($course['profile_picture'] ?? '', 'assets/uploads/profiles', 'assets/img/trainers/default.jpg');
                  ?>
                  <div class="trainer-profile d-flex align-items-center">
                          <img src="<?= htmlspecialchars($profileImage) ?>" class="img-fluid rounded-circle me-2" alt="Trainer" width="40" height="40" style="object-fit:cover;">
                      <a href="#" class="trainer-link fw-semibold"><?= htmlspecialchars($course['instructor_name']) ?></a>
                    </div>
                    <div class="trainer-rank text-muted small">
                      <i class="bi bi-person"></i> <?= rand(10, 100) ?> &nbsp;
                      <i class="bi bi-heart"></i> <?= rand(5, 100) ?>
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
              <a href="admin/upload_course.php" class="btn btn-primary">Upload your first course</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </section>

</main>

<?php include 'inc/footer_public.php'; ?>
</body>
</html>
