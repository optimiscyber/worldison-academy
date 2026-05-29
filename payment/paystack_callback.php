<?php
require_once "../inc/db.php";
require_once "../config/paystack_config.php";  // <-- Add this

$ref = $_GET['reference'];

// Verify with Paystack
$curl = curl_init();
curl_setopt_array($curl, [
  CURLOPT_URL => PAYSTACK_BASE_URL . "/transaction/verify/$ref",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
      "Authorization: Bearer " . PAYSTACK_SECRET_KEY
  ],
]);

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);

if ($data['data']['status'] === "success") {

    // 1. Update payment
    $stmt = $pdo->prepare("UPDATE payments SET status='success' WHERE reference=?");
    $stmt->execute([$ref]);

    // 2. Fetch payment record
    $p = $pdo->prepare("SELECT * FROM payments WHERE reference=? LIMIT 1");
    $p->execute([$ref]);
    $pay = $p->fetch();

    // 3. Enroll user
    $en = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, status) VALUES (?,?, 'active')");
    $en->execute([$pay['user_id'], $pay['course_id']]);

    // 4. Redirect to the course
    header("Location: ../courses/course.php?id=" . $pay['course_id']);
    exit;
}

header("Location: ../payment_failed.php");
exit;
?>
