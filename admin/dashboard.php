<?php
// admin/dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// include DB (use __DIR__ to avoid path issues)
require_once __DIR__ . '/inc/db.php';

// simple auth guard (keeps behavior like your previous working file)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// helpers
function safe_money($val, $dec = 2) {
    return number_format((float) ($val ?? 0), $dec);
}
function safe_int($val) {
    return intval($val ?? 0);
}

// user/session
$uid = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';
$name = $_SESSION['name'] ?? 'User';
$status = $_SESSION['status'] ?? 'pending';

// ---------------------------
// GLOBAL / ADMIN METRICS
// ---------------------------
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalInstructors = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='instructor'")->fetchColumn();
$totalStudents = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalCourses = (int) $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$publishedCourses = (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE status='published'")->fetchColumn();

// Revenue and earnings
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='success'")->fetchColumn();
$earningsRow = $pdo->query("SELECT COALESCE(SUM(platform_share),0) AS platform_total, COALESCE(SUM(instructor_share),0) AS instructors_total FROM instructor_earnings")->fetch(PDO::FETCH_ASSOC);

// Enrollments
$activeEnrollments = (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status='active'")->fetchColumn();

// Recent enrollments (10)
$recentEnrollStmt = $pdo->prepare("
  SELECT e.enrolled_at, u.name AS student_name, c.title AS course_title
  FROM enrollments e
  JOIN users u ON e.user_id = u.id
  JOIN courses c ON e.course_id = c.id
  ORDER BY e.enrolled_at DESC
  LIMIT 10
");
$recentEnrollStmt->execute();
$recentEnrolls = $recentEnrollStmt->fetchAll(PDO::FETCH_ASSOC);

// Top courses by enrolls
$topCourses = $pdo->query("
  SELECT c.id, c.title, c.thumbnail, u.name AS instructor_name, COUNT(e.id) AS enroll_count
  FROM courses c
  LEFT JOIN enrollments e ON e.course_id = c.id
  LEFT JOIN users u ON c.instructor_id = u.id
  GROUP BY c.id
  ORDER BY enroll_count DESC
  LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------
// INSTRUCTOR METRICS (if instructor)
// ---------------------------
if ($role === 'instructor') {
    $instTotalsStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(instructor_share),0) AS total_earned,
            COALESCE(SUM(CASE WHEN status='pending' THEN instructor_share ELSE 0 END),0) AS pending,
            COALESCE(SUM(CASE WHEN status='paid' THEN instructor_share ELSE 0 END),0) AS paid
        FROM instructor_earnings
        WHERE instructor_id = ?
    ");
    $instTotalsStmt->execute([$uid]);
    $instTotals = $instTotalsStmt->fetch(PDO::FETCH_ASSOC);

    $instCoursesCount = (int) $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?")->execute([$uid]) ? (int)$pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?")->fetchColumn() : 0;
    // safer approach to get counts (two statements)
    $tmp = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
    $tmp->execute([$uid]);
    $instCoursesCount = (int) $tmp->fetchColumn();

    $tmp = $pdo->prepare("
        SELECT COUNT(DISTINCT e.user_id)
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE c.instructor_id = ? AND e.status='active'
    ");
    $tmp->execute([$uid]);
    $instStudentsCount = (int) $tmp->fetchColumn();

    // earnings this month
    $tmp = $pdo->prepare("
        SELECT COALESCE(SUM(instructor_share),0) FROM instructor_earnings
        WHERE instructor_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')
    ");
    $tmp->execute([$uid]);
    $instEarningsThisMonth = $tmp->fetchColumn();
}

// ---------------------------
// STUDENT METRICS (if student)
// ---------------------------
if ($role === 'student') {
    $inProgressStmt = $pdo->prepare("
        SELECT c.id, c.title, c.thumbnail,
            COUNT(l.id) AS total_lessons,
            SUM(COALESCE(lp.completed,0)) AS lessons_completed
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN lessons l ON l.course_id = c.id
        LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = e.user_id
        WHERE e.user_id = ? AND e.status='active'
        GROUP BY c.id
        HAVING total_lessons > 0
        LIMIT 10
    ");
    $inProgressStmt->execute([$uid]);
    $inProgressCourses = $inProgressStmt->fetchAll(PDO::FETCH_ASSOC);

    $certStmt = $pdo->prepare("SELECT * FROM certificates WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $certStmt->execute([$uid]);
    $certificates = $certStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------
// CHART DATA (last 6 months)
// ---------------------------
$chartLabels = [];
$chartRevenue = [];
$chartEnrolls = [];

$monthsStmt = $pdo->query("
  SELECT DATE_FORMAT(dt, '%Y-%m') AS ym, DATE_FORMAT(dt, '%b %Y') AS label FROM (
    SELECT (CURRENT_DATE - INTERVAL seq MONTH) AS dt FROM (
      SELECT 0 AS seq UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    ) AS seqs
  ) AS months
  ORDER BY ym ASC
");
$months = $monthsStmt->fetchAll(PDO::FETCH_ASSOC);

$rStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='success' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
$eStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE DATE_FORMAT(enrolled_at, '%Y-%m') = ?");

foreach ($months as $m) {
    $chartLabels[] = $m['label'];
    $rStmt->execute([$m['ym']]);
    $chartRevenue[] = (float) $rStmt->fetchColumn();
    $eStmt->execute([$m['ym']]);
    $chartEnrolls[] = (int) $eStmt->fetchColumn();
}

$chartLabelsJSON = json_encode($chartLabels);
$chartRevenueJSON = json_encode($chartRevenue);
$chartEnrollsJSON = json_encode($chartEnrolls);

// includes (header/sidebar/navbar are expected to exist in admin/inc/)
$pageTitle = 'Dashboard - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>

<div class="container-fluid pt-4 px-4">
  <div class="row g-4">

    <!-- Welcome -->
    <div class="col-12">
      <div class="bg-light rounded p-4 mb-3 d-flex justify-content-between align-items-center">
        <div>
          <h4 class="mb-0">Welcome back, <?= htmlspecialchars($name) ?> 👋</h4>
          <small class="text-muted">Role: <?= ucfirst(htmlspecialchars($role)) ?><?= $status ? ' • ' . htmlspecialchars($status) : '' ?></small>
        </div>
        <div>
          <a href="profile.php" class="btn btn-outline-primary">My Profile</a>
        </div>
      </div>
    </div>

    <!-- Admin top metrics -->
    <?php if (in_array($role, ['admin','ceo'])): ?>
      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Total Users</small>
          <h5 class="mt-2"><?= $totalUsers ?></h5>
          <small class="text-success">Instructors: <?= $totalInstructors ?> • Students: <?= $totalStudents ?></small>
        </div>
      </div>

      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Courses</small>
          <h5 class="mt-2"><?= $totalCourses ?></h5>
          <small class="text-success">Published: <?= $publishedCourses ?></small>
        </div>
      </div>

      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Active Enrollments</small>
          <h5 class="mt-2"><?= $activeEnrollments ?></h5>
          <small class="text-success">Recent: <?= count($recentEnrolls) ?></small>
        </div>
      </div>

      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Total Revenue</small>
          <h5 class="mt-2">₦<?= safe_money($totalRevenue) ?></h5>
          <small class="text-success">Platform: ₦<?= safe_money($earningsRow['platform_total'] ?? 0)?></small>
        </div>
      </div>
    <?php endif; ?>

    <!-- Instructor widgets -->
    <?php if ($role === 'instructor'): ?>
      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Your Courses</small>
          <h5 class="mt-2"><?= safe_int($instCoursesCount) ?></h5>
          <small class="text-success">Students: <?= safe_int($instStudentsCount) ?></small>
        </div>
      </div>

      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Total Earned</small>
          <h5 class="mt-2">₦<?= safe_money($instTotals['total_earned'] ?? 0) ?></h5>
          <small class="text-muted">This month: ₦<?= safe_money($instEarningsThisMonth ?? 0) ?></small>
        </div>
      </div>

      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Pending</small>
          <h5 class="mt-2">₦<?= safe_money($instTotals['pending'] ?? 0) ?></h5>
          <small class="text-muted">Awaiting payout</small>
        </div>
      </div>

      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Paid Out</small>
          <h5 class="mt-2">₦<?= safe_money($instTotals['paid'] ?? 0) ?></h5>
          <small class="text-muted">Total paid</small>
        </div>
      </div>
    <?php endif; ?>

    <!-- Student area -->
    <?php if ($role === 'student'): ?>
      <div class="col-12">
        <div class="card mb-3">
          <div class="card-body">
            <h5>Courses in Progress</h5>
            <div class="row">
              <?php if (!empty($inProgressCourses)): ?>
                <?php foreach ($inProgressCourses as $c):
                  $total = (int)$c['total_lessons'];
                  $done = (int)$c['lessons_completed'];
                  $percent = $total ? round(($done / $total) * 100) : 0;
                ?>
                <div class="col-md-4 mb-3">
                  <div class="p-3 border rounded">
                    <h6><?= htmlspecialchars($c['title']) ?></h6>
                    <div class="progress" style="height:10px;">
                      <div class="progress-bar" role="progressbar" style="width:<?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"><?= $percent ?>%</div>
                    </div>
                    <small><?= $done ?> / <?= $total ?> lessons</small>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="col-12"><small class="text-muted">No active courses yet. Browse courses to get started.</small></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
<?php if (in_array($role, ['admin','ceo'])): ?>
    <!-- Charts -->
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Monthly Revenue</h5>
          <canvas id="revenueChart" height="50"></canvas>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Monthly Enrollments</h5>
          <canvas id="enrollChart" height="50"></canvas>
        </div>
      </div>
    </div>
  <?php endif; ?>
  <!-- STUDENT PROGRESS -->
<div class="col-12">
  <div class="card mb-3">
    <div class="card-body">
      <h5>Courses in Progress</h5>
      <div class="table-responsive">
        <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>Course</th>
              <th>Lessons Completed</th>
              <th>Progress</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($inProgressCourses)): ?>
              <?php foreach ($inProgressCourses as $c):
                $total = (int)$c['total_lessons'];
                $done = (int)$c['lessons_completed'];
                $percent = $total ? round(($done / $total) * 100) : 0;
              ?>
              <tr>
                <td><?= htmlspecialchars($c['title']) ?></td>
                <td><?= $done ?> / <?= $total ?></td>
                <td>
                  <div class="progress" style="height:10px;">
                    <div class="progress-bar" role="progressbar" style="width:<?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"><?= $percent ?>%</div>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="3" class="text-center text-muted">No active courses yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- CERTIFICATES -->
<div class="col-12">
  <div class="card mb-3">
    <div class="card-body">
      <h5>Certificates</h5>
      <div class="table-responsive">
        <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>Course</th>
              <th>Date Issued</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($certificates)): ?>
              <?php foreach ($certificates as $cert): ?>
              <tr>
                <td><?= htmlspecialchars($cert['course_title'] ?? 'Unknown') ?></td>
                <td><?= date('d M Y', strtotime($cert['created_at'])) ?></td>
                <td><a href="certificate.php?id=<?= $cert['id'] ?>" target="_blank" class="btn btn-sm btn-primary">View</a></td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="3" class="text-center text-muted">No certificates yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


    <!-- Recent enrollments -->
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-body">
          <h5>Recent Enrollments</h5>
          <ul class="list-group list-group-flush">
            <?php if ($recentEnrolls): foreach ($recentEnrolls as $r): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <strong><?= htmlspecialchars($r['student_name']) ?></strong>
                  <div class="small text-muted"><?= htmlspecialchars($r['course_title']) ?></div>
                </div>
                <small class="text-muted"><?= htmlspecialchars($r['enrolled_at']) ?></small>
              </li>
            <?php endforeach; else: ?>
              <li class="list-group-item text-muted">No recent enrollments</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>

    <!-- Top courses -->
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-body">
          <h5>Top Courses</h5>
          <div class="row">
            <?php foreach ($topCourses as $c): ?>
              <div class="col-6 mb-3">
                <div class="d-flex">
                  <img src="<?= htmlspecialchars($c['thumbnail'] ?? 'placeholder.png') ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:6px;margin-right:8px;">
                  <div>
                    <div class="fw-bold"><?= htmlspecialchars($c['title']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($c['instructor_name']) ?></div>
                    <div class="small text-muted"><?= intval($c['enroll_count']) ?> enrolls</div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/inc/script.php'; ?>

<!-- Chart.js CDN (if your header already includes Chart.js, remove this) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
  const labels = <?= $chartLabelsJSON ?>;
  const revenue = <?= $chartRevenueJSON ?>;
  const enrolls = <?= $chartEnrollsJSON ?>;

  // Set fixed height via JS
  const revenueCanvas = document.getElementById('revenueChart');
  const enrollCanvas = document.getElementById('enrollChart');
  revenueCanvas.height = 250; // fixed height in pixels
  enrollCanvas.height = 250;

  // Revenue - line
  const ctxR = revenueCanvas.getContext('2d');
  new Chart(ctxR, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Revenue (₦)',
        data: revenue,
        tension: 0.3,
        fill: true,
        borderWidth: 2,
        backgroundColor: 'rgba(54,162,235,0.2)',
        borderColor: 'rgba(54,162,235,1)',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true, // keeps proportion
      scales: { 
        y: { 
          beginAtZero: true 
        } 
      },
      plugins: { 
        legend: { display: false } 
      }
    }
  });

  // Enrollments - bar
  const ctxE = enrollCanvas.getContext('2d');
  new Chart(ctxE, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Enrollments',
        data: enrolls,
        backgroundColor: 'rgba(255,99,132,0.6)',
        borderColor: 'rgba(255,99,132,1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      scales: { 
        y: { 
          beginAtZero: true, 
          precision: 0 
        } 
      },
      plugins: { 
        legend: { display: false } 
      }
    }
  });
})();
</script>

