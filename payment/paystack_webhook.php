<?php
require_once "../inc/db.php";
require_once "../config/paystack_config.php";
require_once "../inc/email.php"; // your email sender

// Paystack requires this
@http_response_code(200);

// Get raw input
$input = @file_get_contents("php://input");
$event = json_decode($input, true);

// Ensure it is a Paystack event
if (!$event || !isset($event['event'])) {
    exit("Invalid event");
}

if ($event['event'] === "charge.success") {

    $ref = $event['data']['reference'];
    $amount = $event['data']['amount'] / 100;

    // Fetch payment
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE reference=? LIMIT 1");
    $stmt->execute([$ref]);
    $payment = $stmt->fetch();

    if (!$payment) { exit("Payment not found"); }

    // Update payment status if not already done
    if ($payment['status'] !== "success") {
        $pdo->prepare("UPDATE payments SET status='success' WHERE reference=?")
            ->execute([$ref]);

        // Enroll the student
        $en = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, status)
                             VALUES (?,?, 'active')");
        $en->execute([$payment['user_id'], $payment['course_id']]);

        // Send receipt email
        sendPaymentReceipt($payment['user_id'], $payment['course_id'], $amount, $ref);
    }
}

echo "OK";
?>
