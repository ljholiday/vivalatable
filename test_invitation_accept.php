<?php
/**
 * Test script for invitation acceptance
 */

// Bootstrap the application
require_once __DIR__ . '/includes/bootstrap.php';

// Start session and simulate being logged in as JJ (user_id = 2)
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
$_SESSION['user_id'] = 2;

// Force auth service to re-initialize with the new session
$authService = vt_service('auth.service');
$authService->loginById(2);

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['accept_invitation'] = '1';

$invitation_token = '9cf972b5d953f0bf63965c4a8cb16b518f7524cebc0ad14de20e628f348e3702';

$community_manager = new VT_Community_Manager();
$result = $community_manager->acceptInvitation($invitation_token);

echo "=== Test Result ===\n";
if (is_vt_error($result)) {
    echo "ERROR:\n";
    $codes = $result->getErrorCodes();
    echo "  Code: " . (is_array($codes) ? implode(', ', $codes) : $codes) . "\n";
    echo "  Message: " . $result->getErrorMessage() . "\n";
} else {
    echo "SUCCESS: Member ID = " . $result . "\n";
}
