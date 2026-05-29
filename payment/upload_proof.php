<?php
session_start();
require_once "../inc/db.php";

$reference = $_POST['reference'];

$target = "../assets/uploads/payproof/" . uniqid() . ".jpg";
move_uploaded_file($_FILES['proof']['tmp_name'], $target);

$stmt = $pdo->prepare("UPDATE payments SET proof=?, status='pending' WHERE reference=?");
$stmt->execute([$target, $reference]);

echo "<script>alert('Payment proof uploaded. We will verify shortly.'); 
window.location='../dashboard.php';</script>";
?>
