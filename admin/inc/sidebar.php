<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$role = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['name'] ?? 'User';
$userId = $_SESSION['user_id'] ?? 0;

$profilePic = "img/default-user.png";

if ($userId) {
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($u && !empty($u['profile_picture'])) {
        $profilePic = $u['profile_picture'];
    }
}

if (!isset($basePath)) {
    $basePath = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../' : './';
}

$currentPage = basename($_SERVER['PHP_SELF']);
function isActive($page, $currentPage) { return $page === $currentPage ? ' active' : ''; }
?>

<!-- SIDEBAR -->
<div class="sidebar pe-4 pb-3" id="sidebar">

    <nav class="navbar bg-light navbar-light h-100">

        <!-- TOP ROW: LOGO + TOGGLE -->
        <div class="d-flex justify-content-between align-items-center px-4 mb-3">
            <a href="#" class="sidebar-toggler flex-shrink-0" id="sidebarToggle" >
                <h3 class="text-primary m-0"><i class="fa fa-hashtag me-2"></i>WORLDISON</h3>
            </a>

        </div>

        <!-- PROFILE DROPDOWN -->
        <div class="dropdown ms-4 mb-4">
            <a
                class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle"
                href="#"
                id="sidebarProfileDropdown"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <img
                    src="<?= htmlspecialchars($profilePic) ?>"
                    alt="User"
                    class="rounded-circle"
                    style="width: 45px; height: 45px; object-fit: cover;"
                >
                <div class="ms-3">
                    <h6 class="mb-0 align-items-center"><?= htmlspecialchars($userName) ?></h6>
                    <small><?= htmlentities(ucfirst($role)) ?></small>
                </div>
            </a>

            <ul class="dropdown-menu dropdown-menu-start" aria-labelledby="sidebarProfileDropdown">
                <li><a class="dropdown-item" href="<?= $basePath ?>admin/profile.php"><i class="fa fa-user me-2"></i> Profile</a></li>
                <li><a class="dropdown-item" href="<?= $basePath ?>admin/settings.php"><i class="fa fa-user me-2"></i> Settings</a></li>
                <li><a class="dropdown-item" href="<?= $basePath ?>admin/payments.php"><i class="fa fa-cog me-2"></i> My payments</a></li>

                <?php if ($role === 'instructor'): ?>
                    <li><a class="dropdown-item" href="<?= $basePath ?>admin/upload_course.php"><i class="fa fa-upload me-2"></i> Upload Course</a></li>
                    <li><a class="dropdown-item" href="<?= $basePath ?>admin/my_courses.php"><i class="fa fa-book me-2"></i> My Courses</a></li>
                <?php endif; ?>

                <?php if (in_array($role, ['admin','ceo'])): ?>
                    <li><a class="dropdown-item" href="<?= $basePath ?>admin/all_payments.php"><i class="fa fa-cog me-2"></i> All Payments</a></li>
                <?php endif; ?>

                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= $basePath ?>logout.php"><i class="fa fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>

        <!-- MENU LINKS -->
        <div class="navbar-nav w-100">
            <a href="<?= $basePath ?>admin/dashboard.php" class="nav-item nav-link<?= isActive('dashboard.php',$currentPage) ?>"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a>

            <a href="<?= $basePath ?>admin/courses-view.php" class="nav-item nav-link<?= isActive('courses-view.php',$currentPage) ?>"><i class="fa fa-book me-2"></i>Courses</a>

            <?php if (in_array($role,['instructor','admin','ceo'])): ?>
                <a href="<?= $basePath ?>admin/upload_course.php" class="nav-item nav-link<?= isActive('upload_course.php',$currentPage) ?>"><i class="fa fa-upload me-2"></i>Upload Course</a>
                <a href="<?= $basePath ?>admin/my_courses.php" class="nav-item nav-link<?= isActive('my_courses.php',$currentPage) ?>"><i class="fa fa-book-open me-2"></i>My Courses</a>
            <?php endif; ?>

            <?php if (in_array($role,['admin','ceo'])): ?>
                <a href="<?= $basePath ?>admin/manage_users.php" class="nav-item nav-link<?= isActive('manage_users.php',$currentPage) ?>"><i class="fa fa-users me-2"></i>Manage Users</a>
                <a href="<?= $basePath ?>admin/manage_courses.php" class="nav-item nav-link<?= isActive('manage_courses.php',$currentPage) ?>"><i class="fa fa-book me-2"></i>Manage Courses</a>
                <a href="<?= $basePath ?>admin/add_categories.php" class="nav-item nav-link<?= isActive('add_categories.php',$currentPage) ?>"><i class="fa fa-folder me-2"></i>Add Categories</a>
                <a href="<?= $basePath ?>admin/certificates.php" class="nav-item nav-link<?= isActive('approve_certificate.php',$currentPage) ?>"><i class="fa fa-file-alt me-2"></i>Manage Certificate</a>
            <?php endif; ?>

            <a href="<?= $basePath ?>index.php" class="nav-item nav-link<?= isActive('index.php',$currentPage) ?>"><i class="fa fa-home me-2"></i>Landing Page</a>

            <a href="<?= $basePath ?>logout.php" class="nav-item nav-link text-danger"><i class="fa fa-sign-out-alt me-2"></i>Logout</a>
        </div>

    </nav>
</div>
