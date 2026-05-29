<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; // Load PHPMailer from Composer

function sendEmail($to, $subject, $body, $toName = '') {
    $mail = new PHPMailer(true);

    try {
        // === SMTP SETTINGS ===
        $mail->isSMTP();
        $mail->Host       = 'smtp-relay.brevo.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = '9b2b9e001@smtp-brevo.com'; // Brevo login
        $mail->Password   =  'xsmtpsib-686c62349d531f429d1f4f650f1eb66cab4756659d9133fad03363a2c3191c0a-tx67Nqg3DftH7GVU'; // Brevo SMTP key
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // === SENDER INFO ===
        $mail->setFrom('noreply@worldison.org', 'Worldison Academy');
        $mail->addAddress($to, $toName ?: $to);

        // === CONTENT ===
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Email error ({$to}): " . $mail->ErrorInfo);
        return false;
    }
}

function sendPaymentReceipt($user_id, $course_id, $amount, $reference) {
    global $pdo;

    // Get user
    $u = $pdo->prepare("SELECT name, email FROM users WHERE id=? LIMIT 1");
    $u->execute([$user_id]);
    $user = $u->fetch();

    // Get course
    $c = $pdo->prepare("SELECT title FROM courses WHERE id=? LIMIT 1");
    $c->execute([$course_id]);
    $course = $c->fetch();

    $to = $user['email'];
    $subject = "Payment Receipt - " . $course['title'];

    $message = "
        <h2 style='color:#333;'>Payment Receipt</h2>
        <p>Hello <strong>{$user['name']}</strong>,</p>

        <p>Thank you for your payment for:</p>

        <h3>{$course['title']}</h3>

        <p><strong>Amount Paid:</strong> ₦" . number_format($amount) . "</p>
        <p><strong>Reference:</strong> {$reference}</p>
        <p><strong>Date:</strong> " . date("Y-m-d H:i A") . "</p>

        <p>Your access to this course is now active.</p>

        <p>Enjoy learning at <strong>Worldison Academy</strong>.</p>
    ";

    // Call the actual email function
    sendEmail($to, $subject, $message, $user['name']);
}

?>
