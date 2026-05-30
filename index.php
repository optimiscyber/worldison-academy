<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "inc/db.php";

try {
    // If instructor is logged in show their courses (including drafts) for preview/testing
    if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'instructor') {
        $stmt = $pdo->prepare("
            SELECT c.*, u.name AS instructor_name
            FROM courses c
            JOIN users u ON c.instructor_id = u.id
            WHERE c.instructor_id = ?
            ORDER BY c.id DESC
            LIMIT 3
        ");
        $stmt->execute([$_SESSION['user_id']]);
      } else {
        // Public visitors: show all published + test courses
        $stmt = $pdo->prepare("
            SELECT c.*, u.name AS instructor_name
            FROM courses c
            JOIN users u ON c.instructor_id = u.id
            ORDER BY c.id DESC
            LIMIT 3
        ");
        $stmt->execute();
    }
    

    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // fallback to empty array and log error (for local dev we echo)
    error_log("DB error in courses.php: " . $e->getMessage());
    $courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Worldison Academy - Professional Training in Safety, Pest Control & Industrial Cleaning</title>

  <?php include 'inc/header_public.php'; // Header with nav ?>
</head>

<body>
  <main class="main">
    <!-- Hero Section -->
    <section id="hero" class="hero section dark-background">
      <img src="assets/img/hero-bg.jpg" alt="Worldison Academy Training" data-aos="fade-in" />
      <div class="container">
        <h2 data-aos="fade-up" data-aos-delay="100">
          Learn Skills That Keep the World Safe & Clean
        </h2>
        <p data-aos="fade-up" data-aos-delay="200">
          Specialized training in Safety Management, Fire Drill, Pest Control, Industrial Cleaning, and more.
        </p>
        <div class="d-flex mt-4" data-aos="fade-up" data-aos-delay="300">
          <a href="courses.php" class="btn-get-started">Explore Courses</a>
        </div>
      </div>
    </section>
    <!-- /Hero Section -->

    <!-- About Section -->
    <section id="about" class="about section">
      <div class="container">
        <div class="row gy-4">
          <div class="col-lg-6 order-1 order-lg-2" data-aos="fade-up" data-aos-delay="100">
            <img src="assets/img/about.jpg" class="img-fluid" alt="About Worldison Academy" />
          </div>

          <div class="col-lg-6 order-2 order-lg-1 content" data-aos="fade-up" data-aos-delay="200">
            <h3>About Worldison Academy</h3>
            <p class="fst-italic">
              Worldison Academy is a professional training institution dedicated to equipping individuals and organizations with practical, industry-standard skills.
            </p>
            <ul>
              <li><i class="bi bi-check-circle"></i> <span>Accredited instructors with hands-on experience.</span></li>
              <li><i class="bi bi-check-circle"></i> <span>Comprehensive courses in Safety, Fire Drill, Pest Control, and Industrial Cleaning.</span></li>
              <li><i class="bi bi-check-circle"></i> <span>Certification upon successful course completion.</span></li>
            </ul>
            <a href="about.php" class="read-more"><span>Read More</span><i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
      </div>
    </section>
    <!-- /About Section -->

    <!-- Counts Section -->
    <section id="counts" class="section counts light-background">
      <div class="container" data-aos="fade-up" data-aos-delay="100">
        <div class="row gy-4 text-center">
          <div class="col-lg-3 col-md-6">
            <div class="stats-item">
              <span data-purecounter-start="0" data-purecounter-end="520" data-purecounter-duration="1" class="purecounter"></span>
              <p>Students Trained</p>
            </div>
          </div>

          <div class="col-lg-3 col-md-6">
            <div class="stats-item">
              <span data-purecounter-start="0" data-purecounter-end="12" data-purecounter-duration="1" class="purecounter"></span>
              <p>Courses Offered</p>
            </div>
          </div>

          <div class="col-lg-3 col-md-6">
            <div class="stats-item">
              <span data-purecounter-start="0" data-purecounter-end="8" data-purecounter-duration="1" class="purecounter"></span>
              <p>Certified Trainers</p>
            </div>
          </div>

          <div class="col-lg-3 col-md-6">
            <div class="stats-item">
              <span data-purecounter-start="0" data-purecounter-end="5" data-purecounter-duration="1" class="purecounter"></span>
              <p>Partner Organizations</p>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- /Counts Section -->

    <!-- Why Us Section -->
    <section id="why-us" class="section why-us">
      <div class="container">
        <div class="row gy-4">
          <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
            <div class="why-box">
              <h3>Why Choose Worldison Academy?</h3>
              <p>
                We focus on practical learning, ensuring that every participant leaves with applicable knowledge and real-world confidence.
              </p>
              <div class="text-center">
                <a href="about.php" class="more-btn"><span>Learn More</span> <i class="bi bi-chevron-right"></i></a>
              </div>
            </div>
          </div>

          <div class="col-lg-8 d-flex align-items-stretch">
            <div class="row gy-4" data-aos="fade-up" data-aos-delay="200">
              <div class="col-xl-4">
                <div class="icon-box text-center">
                  <i class="bi bi-fire"></i>
                  <h4>Safety & Fire Training</h4>
                  <p>Master emergency response, fire safety, and workplace safety standards.</p>
                </div>
              </div>

              <div class="col-xl-4" data-aos="fade-up" data-aos-delay="300">
                <div class="icon-box text-center">
                  <i class="bi bi-bug"></i>
                  <h4>Pest Control Management</h4>
                  <p>Learn integrated pest management and advanced fumigation techniques.</p>
                </div>
              </div>

              <div class="col-xl-4" data-aos="fade-up" data-aos-delay="400">
                <div class="icon-box text-center">
                  <i class="bi bi-droplet-half"></i>
                  <h4>Industrial Cleaning</h4>
                  <p>Professional cleaning procedures and sanitation management for industries.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- /Why Us Section -->

    <!-- Courses Section -->
    <section id="courses" class="courses section">
      <div class="container section-title" data-aos="fade-up">
        <h2>Courses</h2>
        <p>Our Latest Courses</p>
      </div>

      <div class="container">
        <div class="row">
        <?php if (!empty($courses)): ?>
          <?php foreach ($courses as $course): ?>
            <?php
              $thumbDb = $course['thumbnail'] ?? null;

              if (!empty($thumbDb)) {
                  // If it's already a full web URL or correct relative path, use as-is
                  if (preg_match('#^(https?://|/|assets/uploads/)#i', $thumbDb)) {
                      $thumbnail = $thumbDb;
                  } else {
                      // otherwise prepend the correct web directory
                      $thumbnail = 'assets/uploads/thumbnails/' . ltrim($thumbDb, '/');
                  }
              } else {
                  $thumbnail = 'assets/img/default-course.jpg';
              }

              // Double-check that file exists in the server path (optional for localhost dev)
              $serverPath = __DIR__ . '/' . $thumbnail;
              if (!file_exists($serverPath)) {
                  $thumbnail = 'assets/img/default-course.jpg';
              }
              ?>


            <div class="col-lg-4 col-md-6 d-flex align-items-stretch" data-aos="zoom-in" data-aos-delay="100">
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
                    <p class="category mb-0"><?= htmlspecialchars(ucfirst($course['category'] ?: 'Uncategorized')) ?></p>
                    <p class="price mb-0">
                      <?= $course['type'] === 'paid' ? '₦' . number_format($course['price'], 2) : 'Free' ?>
                    </p>
                  </div>

                  <h5 class="fw-bold mb-2">
                    <a href="course-details.php?id=<?= (int)$course['id'] ?>" class="text-decoration-none">
                      <?= htmlspecialchars($course['title']) ?>
                    </a>
                  </h5>

                  <p class="description small text-muted mb-3">
                    <?= htmlspecialchars(safe_substr(strip_tags($course['description']), 0, 140)) ?>...
                  </p>

                  <div class="trainer d-flex justify-content-between align-items-center">
                    <div class="trainer-profile d-flex align-items-center">
                      <img src="assets/img/trainers/default.jpg" class="img-fluid rounded-circle me-2" alt="Trainer" width="40" height="40" style="object-fit:cover;">
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
    <!-- /Courses Section -->
  </main>

  <?php include 'inc/footer_public.php'; ?>
</body>
</html>
