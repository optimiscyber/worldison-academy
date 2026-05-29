<?php
session_start();
require_once "../inc/db.php";
require_once "../inc/paystack_config.php";

if (!isset($_GET['ref'])) {
    die("No reference supplied");
}

$ref = $_GET['ref'];

// Verify transaction with Paystack
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => PAYSTACK_BASE_URL . "/transaction/verify/" . rawurlencode($ref),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY
    ],
]);
$response = curl_exec($curl);
curl_close($curl);

$result = json_decode($response, true);

if ($result['status'] && $result['data']['status'] === 'success') {
    // Mark transaction success
    $stmt = $pdo->prepare("UPDATE transactions SET payment_status='success' WHERE transaction_ref=?");
    $stmt->execute([$ref]);

    // Get transaction info
    $stmt = $pdo->prepare("
        SELECT t.id as txn_id, t.user_id, t.course_id, c.instructor_id, c.price
        FROM transactions t
        JOIN courses c ON t.course_id = c.id
        WHERE t.transaction_ref=?
    ");
    $stmt->execute([$ref]);
    $txn = $stmt->fetch(PDO::FETCH_ASSOC);

    // Enroll student
    $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
    $stmt->execute([$txn['user_id'], $txn['course_id']]);

    // Calculate earnings
    $platform_percent = 30; // platform keeps 30%
    $platform_share = ($txn['price'] * $platform_percent) / 100;
    $instructor_share = $txn['price'] - $platform_share;

    // Save earning record
    $stmt = $pdo->prepare("
        INSERT INTO instructor_earnings (instructor_id, course_id, transaction_id, student_id, amount, platform_share, instructor_share)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $txn['instructor_id'],
        $txn['course_id'],
        $txn['txn_id'],
        $txn['user_id'],
        $txn['price'],
        $platform_share,
        $instructor_share
    ]);

    echo "<script>alert('Payment successful! You are now enrolled in this course.'); window.location='../dashboard.php';</script>";
}

?>
