<?php
session_start();
require_once "db.php";
require_once "email.php"; // ✨ include email helper

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 🧾 Registration (students & instructors)
    if ($action === 'register') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $status = ($role === 'instructor') ? 'pending' : 'approved';

        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $role, $status]);
            // ✉️ Send welcome email to user
            $welcomeSubject = "Welcome to Worldison Academy!";
            $welcomeMessage = "
                <h2>Welcome, $name! 🎓</h2>
                <p>Thank you for joining Worldison Academy.</p>
                <p>You can now log in and start learning.</p>
                <p><a href='https://worldison.org/login.php'>Login Here</a></p>
            ";
            sendEmail($email, $welcomeSubject, $welcomeMessage);


            // ✉️ Notify Admins if new instructor registered
            if ($role === 'instructor') {
                $adminQuery = $pdo->query("SELECT email FROM users WHERE role IN ('admin','ceo')");
                $admins = $adminQuery->fetchAll(PDO::FETCH_COLUMN);

                foreach ($admins as $adminEmail) {
                    $subject = "New Instructor Awaiting Approval";
                    $message = "
                        <h3>New Instructor Registration</h3>
                        <p><strong>Name:</strong> $name</p>
                        <p><strong>Email:</strong> $email</p>
                        <p>This instructor is awaiting your approval.</p>
                        <p>Go to your <a href='https://yourlms.com/admin/manage_users.php'>Admin Panel</a> to approve.</p>
                    ";
                    sendEmail($adminEmail, $subject, $message);
                }
            }

            echo "<script>alert('Registration successful! You can now log in.'); window.location='../login.php';</script>";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo "<script>alert('Email already exists.'); window.history.back();</script>";
            } else {
                echo "Error: " . $e->getMessage();
            }
        }
        exit;
    }

    // ----------- LOGIN LOGIC -----------
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

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
