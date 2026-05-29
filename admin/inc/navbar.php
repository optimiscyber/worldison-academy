<?php
// Default user info
$userName = 'Guest';
$userRole = '';
$profilePic = 'img/default-user.png';
$loggedIn = false;

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/db.php';
    $stmt = $pdo->prepare("SELECT name, role, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userName = $user['name'];
        $userRole = $user['role'];
        $profilePic = $user['profile_picture'] ?: 'img/default-user.png';
        $loggedIn = true;
    }
}
?>
  <!-- Content Start -->
<div class="content">
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm mb-4">
    <div class="container-fluid">
    <a href="#" class="sidebar-toggler flex-shrink-0" id="sidebarToggle">
    <i class="fa fa-bars"></i>
</a>


    
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if ($loggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="rounded-circle me-2" width="35" height="35" style="object-fit:cover;">
                            <?= htmlspecialchars($userName) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i data-lucide="user" class="me-1"></i> Profile</a></li>
                            <?php if ($userRole === 'instructor'): ?>
                                <li><a class="dropdown-item" href="upload_course.php"><i data-lucide="upload-cloud" class="me-1"></i> Upload Course</a></li>
                                <li><a class="dropdown-item" href="my_courses.php"><i data-lucide="book-open" class="me-1"></i> My Courses</a></li>
                            <?php endif; ?>
                            <?php if (in_array($userRole, ['admin', 'ceo'])): ?>
                                <li><a class="dropdown-item" href="dashboard.php"><i data-lucide="settings" class="me-1"></i> Admin Dashboard</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i data-lucide="log-out" class="me-1"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="../login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="../register.php">Register</a></li>
                <?php endif; ?>
            </ul>   
    </div>
</nav>

<?php if (isset($userRole) && !empty($userRole)) echo '<script>if(typeof lucide!=="undefined") lucide.createIcons();</script>'; ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const toggleBtn = document.getElementById("sidebarToggle");
    const body = document.body;

    toggleBtn.addEventListener("click", function () {
        body.classList.toggle("sidebar-collapsed");
    });
});
</script>
