<?php
require_once "../inc/env.php";
require_once "../inc/db.php";
require_once "../inc/email.php"; // your email sender

// Paystack requires a 200 response quickly
@http_response_code(200);

// Verify signature
$secret = getenv('PAYSTACK_SECRET') ?: '';
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? $_SERVER['HTTP_X-PAYSTACK-SIGNATURE'] ?? null;
$input = @file_get_contents('php://input');
if ($secret && $signature) {
    $computed = hash_hmac('sha512', $input, $secret);
    if (!hash_equals($computed, $signature)) {
        http_response_code(403);
        error_log('Paystack webhook signature mismatch');
        exit('Invalid signature');
    }
}

$event = json_decode($input, true);
if (!$event || !isset($event['event'])) { exit('Invalid event'); }

if ($event['event'] === 'charge.success') {
    $ref = $event['data']['reference'] ?? null;
    $amount = ($event['data']['amount'] ?? 0) / 100;

    if (!$ref) exit('No reference');

    // Fetch payment
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE reference=? LIMIT 1');
    $stmt->execute([$ref]);
    $payment = $stmt->fetch();
    if (!$payment) { exit('Payment not found'); }

    // Validate amount matches
    if (floatval($payment['amount']) !== floatval($amount)) {
        error_log('Paystack amount mismatch for ' . $ref);
        exit('Amount mismatch');
    }

    // Update payment status if not already done (idempotent)
    if ($payment['status'] !== 'success') {
        $pdo->prepare("UPDATE payments SET status='success' WHERE reference=?")->execute([$ref]);

        // Enroll the student (ensure not already enrolled)
        $check = $pdo->prepare('SELECT id FROM enrollments WHERE user_id=? AND course_id=? LIMIT 1');
        $check->execute([$payment['user_id'], $payment['course_id']]);
        if (!$check->fetch()) {
            $en = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, status) VALUES (?,?, 'active')");
            $en->execute([$payment['user_id'], $payment['course_id']]);
        }

        // Send receipt email
        sendPaymentReceipt($payment['user_id'], $payment['course_id'], $amount, $ref);
    }
}

echo 'OK';
?>
