<?php
require_once "../inc/env.php";
require_once "../inc/db.php";
session_start();

$ref = $_GET['ref'] ?? '';

if (!$ref) { http_response_code(400); exit('Invalid reference'); }

$stmt = $pdo->prepare("SELECT p.*, u.email FROM payments p 
                       JOIN users u ON p.user_id = u.id 
                       WHERE reference=? LIMIT 1");
$stmt->execute([$ref]);
$pay = $stmt->fetch();

if (!$pay) { http_response_code(404); exit('Invalid reference'); }

// Server-side validate amount and reference uniqueness
if (!is_numeric($pay['amount']) || $pay['amount'] <= 0) { http_response_code(400); exit('Invalid payment amount'); }

$secret_key = getenv('PAYSTACK_SECRET') ?: ''; 
if (!$secret_key) { error_log('Paystack secret not configured'); http_response_code(500); exit('Payment provider misconfigured'); }

$curl = curl_init();
curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => http_build_query([
      "email" => $pay['email'],
      "amount" => $pay['amount'] * 100, 
      "reference" => $ref,
      "callback_url" => (getenv('PAYSTACK_CALLBACK') ?: "")
  ]),
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer $secret_key",
    "Cache-Control: no-cache",
  ],
]);

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);
if (empty($data['status']) || !$data['status']) { error_log('Paystack init failed: ' . $response); exit('Payment initialization failed'); }

header("Location: " . $data['data']['authorization_url']);
exit;
?>
