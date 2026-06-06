<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register | LMS</title>

  <!-- Favicon -->
  <link href="assets/img/favicon.png" rel="icon">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|Raleway:300,400,600,700" rel="stylesheet">

  <!-- Vendor CSS (Worldison Academy Template) -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">

  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- Template Main CSS -->
  <link href="assets/css/style.css" rel="stylesheet">

  <style>
    body {
      background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)),
                  url('assets/img/hero-bg.jpg') center/cover no-repeat;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: "Open Sans", sans-serif;
    }

    .register-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 6px 30px rgba(0,0,0,0.15);
      width: 100%;
      max-width: 430px;
      padding: 2.5rem;
      text-align: center;
    }

    .register-card h2 {
      font-weight: 700;
      margin-bottom: 1rem;
      color: #1c3faa;
    }

    .register-card label {
      font-weight: 600;
      margin-top: 1rem;
      display: block;
      text-align: left;
      color: #333;
    }

    .register-card input,
    .register-card select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #ddd;
      border-radius: 8px;
      margin-top: 0.3rem;
      transition: all 0.3s;
    }

    .register-card input:focus,
    .register-card select:focus {
      border-color: #1c3faa;
      box-shadow: 0 0 0 0.15rem rgba(28,63,170,0.2);
      outline: none;
    }

    .register-card button {
      margin-top: 1.5rem;
      width: 100%;
      background-color: #1c3faa;
      color: #fff;
      border: none;
      padding: 12px;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
    }

    .register-card button:hover {
      background-color: #0e2d89;
    }

    .register-card p {
      margin-top: 1rem;
      font-size: 0.9rem;
    }

    .register-card a {
      color: #1c3faa;
      text-decoration: none;
      font-weight: 600;
    }

    .register-card a:hover {
      text-decoration: underline;
    }

    .icon-box {
      background: #eef2ff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      margin-bottom: 1rem;
    }
  </style>
</head>

<body>
  <div class="register-card" data-aos="zoom-in">
    <div class="icon-box">
      <i data-lucide="user-plus"></i>
    </div>
    <h2>Create an Account</h2>
    <p class="text-muted mb-3">Join as a Student or Instructor</p>

    <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once __DIR__ . '/../inc/csrf.php';
    ?>
    <form action="inc/auth.php" method="POST">
      <input type="hidden" name="action" value="register">
      <?php echo csrf_input(); ?>

      <label for="name">Full Name</label>
      <input type="text" id="name" name="name" placeholder="Enter your full name" required>

      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="Enter your email" required>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Enter your password" required>

      <label for="role">Register As</label>
      <select id="role" name="role" required>
        <option value="" disabled selected>Select your role</option>
        <option value="student">Student</option>
        <option value="instructor">Instructor</option>
      </select>

      <button type="submit"><i data-lucide="arrow-right"></i> Register</button>
    </form>

    <p>Already have an account? <a href="login.php">Login</a></p>
  </div>

  <!-- Vendor JS -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script> AOS.init(); </script>
  <script> lucide.createIcons(); </script>
</body>
</html>
