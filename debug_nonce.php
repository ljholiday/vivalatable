<?php
require_once 'includes/bootstrap.php';

echo "Testing nonce generation and verification...\n\n";

$security = vt_service('security.service');
$auth = vt_service('auth.service');

// Test current user ID
$userId = $auth->getCurrentUserId();
echo "Current User ID: " . $userId . "\n";

// Test nonce creation
$nonce = $security->createNonce('vt_nonce', $userId);
echo "Generated nonce: " . $nonce . "\n";

// Test nonce verification immediately
$isValid = $security->verifyNonce($nonce, 'vt_nonce', $userId);
echo "Immediate verification: " . ($isValid ? 'PASS' : 'FAIL') . "\n";

// Test with wrong user ID
$isValidWrongUser = $security->verifyNonce($nonce, 'vt_nonce', 999);
echo "Wrong user ID verification: " . ($isValidWrongUser ? 'FAIL (should be false)' : 'PASS') . "\n";

// Test with wrong action
$isValidWrongAction = $security->verifyNonce($nonce, 'wrong_action', $userId);
echo "Wrong action verification: " . ($isValidWrongAction ? 'FAIL (should be false)' : 'PASS') . "\n";