<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Test nonce generation and verification
$action = 'vt_register';
$generated_nonce = VT_Security::createNonce($action);
echo "Generated nonce: $generated_nonce\n";

$is_valid = VT_Security::verifyNonce($generated_nonce, $action);
echo "Verification result: " . ($is_valid ? 'VALID' : 'INVALID') . "\n";

$user_id = VT_Auth::getCurrentUserId();
echo "Current user ID: $user_id\n";

// Test with different user ID scenarios
echo "\nTesting nonce components:\n";
echo "User ID: $user_id\n";
echo "Action: $action\n";
echo "Time: " . time() . "\n";

$token = $user_id . '|' . $action . '|' . time();
echo "Token: $token\n";

$hash = VT_Security::hash($token);
echo "Full hash: $hash\n";
echo "Truncated (nonce): " . substr($hash, 0, 10) . "\n";
?>