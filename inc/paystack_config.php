<?php
require_once __DIR__ . '/env.php';
// Read secrets from environment
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET') ?: '');
define('PAYSTACK_BASE_URL', 'https://api.paystack.co');
?>
