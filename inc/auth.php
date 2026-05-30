<?php
session_start();
require_once "db.php";
require_once "email.php"; // email helper
require_once __DIR__ . '/csrf.php';

// Password policy check
function valid_password_policy($p)
{
    if (strlen($p) < 8) return false;
    if (!preg_match('/[A-Z]/', $p)) return false;
    if (!preg_match('/[a-z]/', $p)) return false;
    if (!preg_match('/[0-9]/', $p)) return false;
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF for POST actions
    csrf_verify();

    $action = $_POST['action'] ?? '';

    // Registration (students & instructors only)
    if ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password_raw = $_POST['password'] ?? '';

        if (!valid_password_policy($password_raw)) {
            echo "<script>alert('Password must be minimum 8 chars, include upper+lowercase and a number.'); window.history.back();</script>";
            exit;
        }

        // Public registration cannot create admin/ceo accounts
        $role = ($_POST['role'] === 'instructor') ? 'instructor' : 'student';
        $status = ($role === 'instructor') ? 'pending' : 'approved';
        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $role, $status]);

            // Send welcome email
            $welcomeSubject = "Welcome to Worldison Academy!";
            $welcomeMessage = "<h2>Welcome, " . htmlspecialchars($name) . "!</h2><p>Thank you for joining.</p>";
            sendEmail($email, $welcomeSubject, $welcomeMessage);

            // Notify admins for instructor approval
            if ($role === 'instructor') {
                $adminQuery = $pdo->query("SELECT email FROM users WHERE role IN ('admin','ceo')");
                $admins = $adminQuery->fetchAll(PDO::FETCH_COLUMN);
                foreach ($admins as $adminEmail) {
                    sendEmail($adminEmail, 'New Instructor Awaiting Approval', "Instructor $name ($email) registered and awaits approval.");
                }
            }

            echo "<script>alert('Registration successful! You can now log in.'); window.location='../login.php';</script>";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo "<script>alert('Email already exists.'); window.history.back();</script>";
            } else {
                error_log('Registration error: ' . $e->getMessage());
                echo "<script>alert('Registration failed.'); window.history.back();</script>";
            }
        }
        exit;
    }

    // LOGIN
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Only approved users can login
        if ($user['status'] !== 'approved') {
            echo "<script>alert('Your account is not approved yet.'); window.history.back();</script>";
            exit;
        }

        // set session and regenerate id
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['status'] = $user['status'];

        header("Location: ../admin/dashboard.php");
        exit;
    } else {
        echo "<script>alert('Invalid email or password'); window.history.back();</script>";
    }
}
?>
