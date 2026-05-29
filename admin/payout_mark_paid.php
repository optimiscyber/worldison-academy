<?php
require_once "inc/db.php";
$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $pdo->prepare("UPDATE instructor_earnings SET status='paid', paid_at=NOW() WHERE id=?");
    $stmt->execute([$id]);
}
header("Location: payouts.php");
exit;
?>
