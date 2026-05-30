<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "inc/db.php";
require_once "../inc/auth.php";
require_once __DIR__ . '/../inc/csrf.php';

// Only existing admin/CEO users can create new admin/CEO accounts
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','ceo'])) {
    // block public access
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role     = trim($_POST['role']); // admin or ceo only

    if (!in_array($role, ['admin', 'ceo'])) {
        $error = "Invalid role!";
    } else {

        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $error = "This email is already registered.";
        } else {

            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Change status to 'approved' for automatic approval
            $insert = $pdo->prepare("
                INSERT INTO users (name, email, password, role, status)
                VALUES (?, ?, ?, ?, 'approved')
            ");

            if ($insert->execute([$name, $email, $hash, $role])) {
                header("Location: ../login.php?registered=1");
                exit;
            } else {
                $error = "Failed to create account. Try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin / CEO Signup - LMS</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <!-- Dashmin CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container-fluid">
    <div class="row h-100 align-items-center justify-content-center" style="min-height:100vh;">

        <div class="col-12 col-sm-8 col-md-6 col-lg-4">
            <div class="bg-white rounded p-4 p-sm-5 shadow">

                <h3 class="text-center mb-4">Admin / CEO Signup</h3>

                <?php if ($error): ?>
                    <div class="alert alert-danger text-center"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrf_input(); ?>

                    <!-- Full Name -->
                    <div class="form-floating mb-3">
                        <input type="text" name="name" class="form-control" id="name" placeholder="Full Name" required>
                        <label for="name">Full Name</label>
                    </div>

                    <!-- Email -->
                    <div class="form-floating mb-3">
                        <input type="email" name="email" class="form-control" id="email" placeholder="Email Address" required>
                        <label for="email">Email Address</label>
                    </div>

                    <!-- Role -->
                    <div class="form-floating mb-3">
                        <select name="role" class="form-select" id="role" required>
                            <option value="admin">Admin</option>
                            <option value="ceo">CEO</option>
                        </select>
                        <label for="role">Select Role</label>
                    </div>

                    <!-- Password -->
                    <div class="form-floating mb-4">
                        <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                        <label for="password">Create Password</label>
                    </div>

                    <button type="submit" class="btn btn-primary py-3 w-100 mb-3">Create Account</button>

                    <div class="text-center">
                        <small>Already have an account? <a href="login.php">Login</a></small>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>

<!-- Dashmin JS -->
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>

</body>
</html>
