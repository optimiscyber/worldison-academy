<?php
session_start();
require_once "inc/db.php";
require_once __DIR__ . '/../inc/csrf.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

$success = $error = '';
$passSuccess = $passError = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_verify();
    $name = trim($_POST['name']);
    $bio = trim($_POST['bio']);

    $profile_picture = $user['profile_picture'];
    if (!empty($_FILES['profile_picture']['name'])) {
        $file = $_FILES['profile_picture'];
        if ($file['error'] === UPLOAD_ERR_OK && $file['size'] <= 3 * 1024 * 1024) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed = ['image/jpeg','image/png','image/webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $blocked = ['php','phtml','phar','js','exe','sh'];
            if (in_array($mime, $allowed) && !in_array($ext, $blocked)) {
                $upload_dir = __DIR__ . '/../assets/uploads/profiles/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = bin2hex(random_bytes(8)) . '.' . $ext;
                $target_path = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $profile_picture = '../assets/uploads/profiles/' . $filename;
                } else {
                    $error = "Failed to upload profile picture.";
                }
            } else {
                $error = "Invalid profile picture file.";
            }
        } else {
            $error = "Profile picture upload error or file too large.";
        }
    }

    if (!$error) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, bio = ?, profile_picture = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $bio, $profile_picture, $user['id']]);
        $success = "Profile updated successfully!";
        $user['name'] = $name;
        $user['bio'] = $bio;
        $user['profile_picture'] = $profile_picture;
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user['password'])) {
        $passError = "Current password is incorrect.";
    } elseif ($new !== $confirm) {
        $passError = "New passwords do not match.";
    } elseif (strlen($new) < 6) {
        $passError = "New password must be at least 6 characters.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hash, $user['id']]);
        $passSuccess = "Password changed successfully!";
    }
}
?>

<?php
$pageTitle = 'My Profile - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid p-4">
        <h1 class="h3 mb-4"><i data-lucide="user" class="me-2"></i> My Profile</h1>

        <!-- Profile Messages -->
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($passSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($passSuccess) ?></div><?php endif; ?>
        <?php if ($passError): ?><div class="alert alert-danger"><?= htmlspecialchars($passError) ?></div><?php endif; ?>

        <div class="row">
            <!-- Profile Info -->
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <img src="<?= htmlspecialchars($user['profile_picture'] ?: '../assets/img/default-user.png') ?>" 
                             class="img-fluid rounded-circle mb-3" 
                             alt="Profile Picture" 
                             width="150" height="150" 
                             style="object-fit:cover;">
                        <h5 class="card-title"><?= htmlspecialchars($user['name']) ?></h5>
                        <p class="text-muted"><?= htmlspecialchars(ucfirst($user['role'])) ?></p>
                    </div>
                </div>
            </div>

            <!-- Profile & Password Forms -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="update_profile">
                            <h5 class="card-title mb-3">Profile Information</h5>
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email (cannot change)</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Bio</label>
                                <textarea name="bio" class="form-control" rows="4"><?= htmlspecialchars($user['bio']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Profile Picture</label>
                                <input type="file" name="profile_picture" accept="image/*" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Update Profile</button>
                        </form>
                    </div>
                </div>

                <!-- Password Change -->
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="change_password">
                            <h5 class="card-title mb-3">Change Password</h5>
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-warning"><i data-lucide="key"></i> Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'inc/script.php'; ?>
<script>
if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
