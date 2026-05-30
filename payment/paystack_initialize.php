<?php
require_once "../inc/env.php";
require_once "../inc/db.php";
require_once "../inc/paystack_config.php";
session_start();

$ref = $_GET['ref'];

$stmt = $pdo->prepare("SELECT p.*, u.email FROM payments p 
                       JOIN users u ON p.user_id = u.id 
                       WHERE reference=? LIMIT 1");
$stmt->execute([$ref]);
$pay = $stmt->fetch();

if (!$pay) { die("Invalid reference"); }

$curl = curl_init();
curl_setopt_array($curl, [
  CURLOPT_URL => PAYSTACK_BASE_URL . "/transaction/initialize",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
      "email" => $pay['email'],
      "amount" => $pay['amount'] * 100,
      "reference" => $ref,
      "callback_url" => getenv('PAYSTACK_CALLBACK') ?: 'https://yourwebsite.com/payment/paystack_callback.php'
    ]),
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
    "Cache-Control: no-cache",
  ],
]);

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);

header("Location: " . $data['data']['authorization_url']);
exit;
?>
