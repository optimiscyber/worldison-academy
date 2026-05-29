<?php
;session_start();
require_once "inc/db.php";
require_once "../inc/email.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$msg = "";

// Fetch current user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $bio = trim($_POST['bio']);
    $password = $_POST['password'] ?? '';

    // File upload
    if (!empty($_FILES['profile_picture']['name'])) {
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $fileName = "uploads/profile_" . $userId . "." . $ext;
        move_uploaded_file($_FILES['profile_picture']['tmp_name'], $fileName);
    } else {
        $fileName = $user['profile_picture'];
    }

    // Password hash if changed
    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    } else {
        $hash = $user['password'];
    }

    // Update DB
    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password=?, bio=?, profile_picture=? WHERE id=?");
    $stmt->execute([$name, $email, $hash, $bio, $fileName, $userId]);

    $msg = "Profile updated successfully!";
    // Refresh data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle self-deactivation
if (isset($_GET['deactivate']) && $_GET['deactivate'] == 1) {
    $stmt = $pdo->prepare("UPDATE users SET status='rejected' WHERE id=?");
    $stmt->execute([$userId]);
    session_destroy();
    header("Location: login.php");
    exit;
}

$pageTitle = "Settings - LMS";
include "inc/header.php";
include "inc/sidebar.php";
include "inc/navbar.php";
?>

<main class="container">
<div class="container-fluid p-4">
    <h2 class="mb-4"><i data-lucide="settings" class="me-2"></i> Account Settings</h2>

    <?php if($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>

        <div class="mb-3">
            <label>Password <small class="text-muted">(leave blank to keep current)</small></label>
            <input type="password" name="password" class="form-control">
        </div>

        <div class="mb-3">
            <label>Bio</label>
            <textarea name="bio" class="form-control"><?= htmlspecialchars($user['bio']) ?></textarea>
        </div>

        <div class="mb-3">
            <label>Profile Picture</label>
            <input type="file" name="profile_picture" class="form-control">
            <?php if($user['profile_picture']): ?>
                <img src="<?= htmlspecialchars($user['profile_picture']) ?>" width="80" class="mt-2">
            <?php endif; ?>
        </div>

        <button class="btn btn-primary" type="submit">Update Profile</button>
        <a href="?deactivate=1" class="btn btn-danger" onclick="return confirm('Deactivate your account?');">Deactivate Account</a>
    </form>
</div>
</main>

<?php include "inc/script.php"; ?>
