<?php
session_start();
require_once "../inc/db.php";

$reference = $_GET['ref'];

$stmt = $pdo->prepare("SELECT p.*, c.title, c.price 
                       FROM payments p 
                       JOIN courses c ON p.course_id = c.id 
                       WHERE reference=? LIMIT 1");
$stmt->execute([$reference]);
$pay = $stmt->fetch();

if (!$pay) { die("Invalid reference"); }
?>
<!DOCTYPE html>
<html>
<head>
<title>Manual Transfer</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Bank Transfer Payment</h3>
<p>Please transfer <b>₦<?= number_format($pay['amount'],2) ?></b> to:</p>

<div class="card p-3 mb-3">
  <b>Bank Name:</b> Access Bank<br>
  <b>Account Name:</b> Worldison Academy<br>
  <b>Account Number:</b> 0000000000<br>
</div>

<form action="upload_proof.php" method="POST" enctype="multipart/form-data">
  <label>Upload Receipt</label>
  <input type="file" name="proof" class="form-control mb-3" required>

  <input type="hidden" name="reference" value="<?= $reference ?>">

  <button class="btn btn-primary w-100">Submit Payment Proof</button>
</form>

</body>
</html>
