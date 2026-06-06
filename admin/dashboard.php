<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/../app/Services/DashboardService.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

function safe_money($val, $dec = 2) {
    return number_format((float) ($val ?? 0), $dec);
}

function safe_int($val) {
    return intval($val ?? 0);
}

$uid = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'student';
$name = $_SESSION['name'] ?? 'User';
$status = $_SESSION['status'] ?? 'pending';

$dashboardService = new DashboardService($pdo);
$adminOverview = in_array($role, ['admin', 'ceo'], true)
    ? $dashboardService->getAdminOverview()
    : [];
$instructorOverview = ($role === 'instructor')
    ? $dashboardService->getInstructorOverview($uid)
    : [];
$studentOverview = ($role === 'student')
    ? $dashboardService->getStudentOverview($uid)
    : [];
$chartData = $dashboardService->getMonthlyChartData(6);

$chartLabelsJSON = json_encode($chartData['labels']);
$chartRevenueJSON = json_encode($chartData['revenue']);
$chartEnrollsJSON = json_encode($chartData['enrollments']);

$pageTitle = 'Dashboard - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>

<div class="container-fluid pt-4 px-4">
  <div class="row g-4">
    <div class="col-12">
      <div class="bg-light rounded p-4 mb-3 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
        <div>
          <h4 class="mb-1">Welcome back, <?= htmlspecialchars($name) ?> 👋</h4>
          <p class="mb-0 text-muted">Role: <?= ucfirst(htmlspecialchars($role)) ?><?= $status ? ' • ' . htmlspecialchars($status) : '' ?></p>
        </div>
        <a href="profile.php" class="btn btn-outline-primary mt-3 mt-md-0">My Profile</a>
      </div>
    </div>

    <?php if (in_array($role, ['admin', 'ceo'], true)): ?>
      <?php $overview = $adminOverview; ?>
      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Total Users</small>
          <h5 class="mt-2"><?= safe_int($overview['total_users'] ?? 0) ?></h5>
          <small class="text-success">Instructors: <?= safe_int($overview['total_instructors'] ?? 0) ?> • Students: <?= safe_int($overview['total_students'] ?? 0) ?></small>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Courses</small>
          <h5 class="mt-2"><?= safe_int($overview['total_courses'] ?? 0) ?></h5>
          <small class="text-success">Published: <?= safe_int($overview['published_courses'] ?? 0) ?></small>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Active Enrollments</small>
          <h5 class="mt-2"><?= safe_int($overview['active_enrollments'] ?? 0) ?></h5>
          <small class="text-success">Recent: <?= safe_int(count($overview['recent_enrollments'] ?? [])) ?></small>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Revenue</small>
          <h5 class="mt-2">₦<?= safe_money($overview['total_revenue'] ?? 0) ?></h5>
          <small class="text-success">Platform: ₦<?= safe_money($overview['platform_earnings'] ?? 0) ?></small>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">Recent Enrollments</h5>
            <ul class="list-group list-group-flush">
              <?php foreach ($overview['recent_enrollments'] ?? [] as $enrollment): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <strong><?= htmlspecialchars($enrollment['student_name']) ?></strong>
                    <div class="small text-muted"><?= htmlspecialchars($enrollment['course_title']) ?></div>
                  </div>
                  <small class="text-muted"><?= htmlspecialchars($enrollment['enrolled_at']) ?></small>
                </li>
              <?php endforeach; ?>
              <?php if (empty($overview['recent_enrollments'])): ?>
                <li class="list-group-item text-center text-muted">No recent enrollments yet.</li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-8 col-md-6">
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">Top Courses</h5>
            <div class="row">
              <?php foreach ($overview['top_courses'] ?? [] as $course): ?>
                <div class="col-12 col-sm-6 mb-3">
                  <div class="d-flex gap-3 align-items-center">
                    <img src="<?= htmlspecialchars($course['thumbnail'] ?? 'placeholder.png') ?>" alt="" class="rounded" style="width:72px;height:72px;object-fit:cover;">
                    <div>
                      <div class="fw-bold mb-1"><?= htmlspecialchars($course['title']) ?></div>
                      <div class="small text-muted"><?= htmlspecialchars($course['instructor_name']) ?></div>
                      <div class="small text-muted"><?= safe_int($course['enroll_count']) ?> enrolls</div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if (empty($overview['top_courses'])): ?>
                <div class="col-12 text-center text-muted">No courses available yet.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card mb-3">
          <div class="card-body">
          <div class="chart-box">
            <canvas id="revenueChart"></canvas>
          </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-body">
            <div class="chart-box">
              <canvas id="enrollChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($role === 'instructor'): ?>
      <?php $overview = $instructorOverview; ?>
      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Your Courses</small>
          <h5 class="mt-2"><?= safe_int($overview['courses_count'] ?? 0) ?></h5>
          <small class="text-success">Students: <?= safe_int($overview['students_count'] ?? 0) ?></small>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Total Earned</small>
          <h5 class="mt-2">₦<?= safe_money($overview['earnings_summary']['total_earned'] ?? 0) ?></h5>
          <small class="text-muted">This month: ₦<?= safe_money($overview['earnings_this_month'] ?? 0) ?></small>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Pending</small>
          <h5 class="mt-2">₦<?= safe_money($overview['earnings_summary']['pending'] ?? 0) ?></h5>
          <small class="text-muted">Awaiting payout</small>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="bg-white rounded p-3 shadow-sm">
          <small class="text-muted">Paid Out</small>
          <h5 class="mt-2">₦<?= safe_money($overview['earnings_summary']['paid'] ?? 0) ?></h5>
          <small class="text-muted">Total paid</small>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($role === 'student'): ?>
      <?php $overview = $studentOverview; ?>
      <div class="col-12">
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">Courses in Progress</h5>
            <div class="row">
              <?php foreach ($overview['in_progress_courses'] ?? [] as $course):
                $total = safe_int($course['total_lessons']);
                $done = safe_int($course['lessons_completed']);
                $percent = $total ? round(($done / $total) * 100) : 0;
              ?>
                <div class="col-md-4 mb-3">
                  <div class="p-3 border rounded h-100">
                    <h6><?= htmlspecialchars($course['title']) ?></h6>
                    <div class="progress mb-2" style="height:10px;">
                      <div class="progress-bar" role="progressbar" style="width:<?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted"><?= $done ?> / <?= $total ?> lessons completed</small>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if (empty($overview['in_progress_courses'])): ?>
                <div class="col-12 text-center text-muted">No active courses yet. Enroll in a course to start learning.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">Certificates</h5>
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
                  <?php foreach ($overview['certificates'] ?? [] as $cert): ?>
                    <tr>
                      <td><?= htmlspecialchars($cert['course_title'] ?? 'Unknown') ?></td>
                      <td><?= htmlspecialchars(date('d M Y', strtotime($cert['created_at']))) ?></td>
                      <td><a href="certificate.php?id=<?= htmlspecialchars($cert['id']) ?>" target="_blank" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($overview['certificates'])): ?>
                    <tr><td colspan="3" class="text-center text-muted">No certificates yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/inc/script.php'; ?>

<?php if (in_array($role, ['admin', 'ceo'], true)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
  const labels = <?= $chartLabelsJSON ?>;
  const revenue = <?= $chartRevenueJSON ?>;
  const enrolls = <?= $chartEnrollsJSON ?>;

  const revenueCanvas = document.getElementById('revenueChart');
  const enrollCanvas = document.getElementById('enrollChart');

  if (revenueCanvas) {
    new Chart(revenueCanvas.getContext('2d'), {
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
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { display: false } }
      }
    });
  }

  if (enrollCanvas) {
    new Chart(enrollCanvas.getContext('2d'), {
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
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, precision: 0 } },
        plugins: { legend: { display: false } }
      }
    });
  }
})();
</script>
<?php endif; ?>
