<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>LMS Login</title>

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
  body.auth {
  background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
    url("assets/img/about-2.jpg") ;
  background-color: #222; /* fallback */
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/inc/csrf.php';
?>
</head>

<body class="auth">
  <div class="login-card" data-aos="zoom-in">
    <div class="icon-box">
      <i data-lucide="log-in"></i>
    </div>
    <h2>Welcome Back</h2>
    <p class="text-muted mb-3">Login to continue learning</p>

    <form action="inc/auth.php" method="POST">
      <input type="hidden" name="action" value="login">
      <?php echo csrf_input(); ?>

      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="Enter your email" required>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Enter your password" required>

      <button type="submit"><i data-lucide="arrow-right"></i> Login</button>
    </form>

    <p>Don’t have an account? <a href="register.php">Register</a></p>
  </div>
  <!-- Vendor JS -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script> AOS.init(); </script>
  <script> lucide.createIcons(); </script>
</body>
</html>
