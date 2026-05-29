<?php
session_start();
require_once "../inc/db.php";
require_once "../inc/auth.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Fetch course info
$stmt = $pdo->prepare("
    SELECT c.*, u.name AS instructor_name
    FROM courses c
    JOIN users u ON c.instructor_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
  die("Course not found.");
}

// Get user details (for prefilling billing info)
$user_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Checkout - <?= htmlspecialchars($course['title']) ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include '../inc/header.php'; ?>
<?php include "../inc/sidebar.php"; ?>

<div class="main-content" id="mainContent">
  <div class="container mt-5">
    <form method="POST" action="process_payment.php">
      <div class="row g-4">
        <!-- Billing Information -->
        <div class="col-lg-7">
          <div class="card shadow-sm p-4">
            <h4 class="mb-3">Billing Information</h4>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= htmlspecialchars($user['name']) ?>" required>
              </div>

              <div class="col-md-6 mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= htmlspecialchars($user['email']) ?>" required>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="text" class="form-control" id="phone" name="phone" placeholder="+234..." required>
              </div>

              <div class="col-md-6 mb-3">
                <label for="address" class="form-label">Billing Address</label>
                <input type="text" class="form-control" id="address" name="address" placeholder="Street, City" required>
              </div>
            </div>

            <div class="mb-3">
              <label for="country" class="form-label">Country</label>
              <input type="text" class="form-control" id="country" name="country" value="Nigeria" required>
            </div>

            <div class="mb-3">
              <label for="payment_method" class="form-label">Payment Method</label>
              <select class="form-control" id="payment_method" name="payment_method" required>
                <option value="paystack">Paystack</option>
                <option value="DefiGate">DeFiGate (crypto)</option>
                <option value="manual">Manual Transfer</option>
              </select>
            </div>

            <input type="hidden" name="course_id" value="<?= $course_id ?>">

          </div>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-5">
          <div class="card shadow-sm p-4">
            <h4 class="mb-3">Order Summary</h4>
            <div class="d-flex align-items-center mb-3">
              <img src="../<?= htmlspecialchars($course['thumbnail'] ?? 'assets/images/default-course.jpg') ?>" 
                   alt="<?= htmlspecialchars($course['title']) ?>" 
                   style="width: 90px; height: 60px; object-fit: cover; border-radius: 8px; margin-right: 15px;">
              <div>
                <h5 class="mb-1"><?= htmlspecialchars($course['title']) ?></h5>
                <p class="small text-muted">Instructor: <?= htmlspecialchars($course['instructor_name']) ?></p>
              </div>
            </div>
            <hr>
            <div class="d-flex justify-content-between">
              <p>Course Price:</p>
              <p><strong>₦<?= number_format($course['price'], 2) ?></strong></p>
            </div>
            <div class="d-flex justify-content-between">
              <p>Processing Fee:</p>
              <p>₦0.00</p>
            </div>
            <hr>
            <div class="d-flex justify-content-between">
              <p><strong>Total</strong></p>
              <p><strong>₦<?= number_format($course['price'], 2) ?></strong></p>
            </div>
            <button type="submit" class="btn btn-success w-100 mt-3">
              Proceed to Payment
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

</body>
</html>
