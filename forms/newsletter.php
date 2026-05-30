<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'A valid email address is required.']);
    exit;
}

require_once __DIR__ . '/../inc/email.php';

$body = "<h2>Newsletter Subscription</h2>";
$body .= "<p>A new user has signed up for the newsletter:</p>";
$body .= "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";

$to = 'info@worldison.org';
$sent = sendEmail($to, 'Newsletter Subscription', $body);

if ($sent) {
    echo json_encode(['status' => 'success', 'message' => 'Thanks for subscribing!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unable to process your subscription at this time.']);
}
