<?php
session_start();
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/../inc/email.php'; // include your email function
require_once __DIR__ . '/../inc/csrf.php';

// Only CEO can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ceo') {
    header("Location: dashboard.php");
    exit;
}

$pageTitle = "Add User - LMS";
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // sanitize input
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // validation
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (!in_array($role, ['student', 'instructor', 'admin', 'ceo'])) $errors[] = "Invalid role selected";
    if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirmPassword) $errors[] = "Passwords do not match";

    // check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $errors[] = "Email already registered";

    // insert user if no errors
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, role, password) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $role, $hashedPassword])) {

            // Send welcome email
            $subject = "Welcome to Worldison Academy!";
            $body = "
                <p>Hi ".htmlspecialchars($name).",</p>
                <p>Welcome to <strong>Worldison Academy</strong>! Your account has been created successfully.</p>
                <p><strong>Email:</strong> {$email}<br>
                   <strong>Password:</strong> {$password}</p>
                <p>You can log in here: <a href='https://worldison.org/login.php'>Login</a></p>
                <p>We recommend you change your password after first login.</p>
                <br>
                <p>Best regards,<br>Worldison Academy Team</p>
            ";

            if (!sendEmail($email, $subject, $body, $name)) {
                $errors[] = "User created but failed to send email.";
            } else {
                $success = "User added successfully and welcome email sent!";
            }

            // clear form fields
            $name = $email = $password = $confirmPassword = '';

        } else {
            $errors[] = "Database error: could not add user";
        }
    }
}
?>

<div class="container-fluid pt-4 px-4">
    <div class="bg-light rounded p-4">

        <h3 class="mb-4">Add New User</h3>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="row g-3">
            <?php echo csrf_input(); ?>
            <div class="col-md-6">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($name ?? '') ?>" required>
            </div>

            <div class="col-md-6">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($email ?? '') ?>" required>
            </div>

            <div class="col-md-6">
                <label for="role" class="form-label">Role</label>
                <select name="role" id="role" class="form-select" required>
                    <option value="">-- Select Role --</option>
                    <option value="student" <?= (isset($role) && $role==='student')?'selected':'' ?>>Student</option>
                    <option value="instructor" <?= (isset($role) && $role==='instructor')?'selected':'' ?>>Instructor</option>
                    <option value="admin" <?= (isset($role) && $role==='admin')?'selected':'' ?>>Admin</option>
                    <option value="ceo" <?= (isset($role) && $role==='ceo')?'selected':'' ?>>CEO</option>
                </select>
            </div>

            <div class="col-md-6">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/inc/script.php'; ?>
