<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $subject === '' || $message === '') {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
    exit;
}

require_once __DIR__ . '/../inc/email.php';

$body = "<h2>Website Contact Request</h2>";
$body .= "<p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>";
$body .= "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
$body .= "<p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>";
$body .= "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>";

$to = 'info@worldison.org';
$sent = sendEmail($to, 'Contact Form Submission', $body, $name);

if ($sent) {
    echo json_encode(['status' => 'success', 'message' => 'Thank you! Your message has been sent.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send your message. Please try again later.']);
}
