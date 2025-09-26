<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Test login functionality
echo "Testing login functionality...\n";

// Test with the user we created
$email = 'finaltest@example.com';
$username = 'finaltest';
$password = 'password123';

echo "Attempting to login with email: $email\n";
$result1 = VT_Auth::login($email, $password);
echo "Result: " . ($result1 ? 'SUCCESS' : 'FAILED') . "\n";

echo "\nAttempting to login with username: $username\n";
$result2 = VT_Auth::login($username, $password);
echo "Result: " . ($result2 ? 'SUCCESS' : 'FAILED') . "\n";

echo "\nAttempting to login with wrong password\n";
$result3 = VT_Auth::login($email, 'wrongpassword');
echo "Result: " . ($result3 ? 'SUCCESS' : 'FAILED') . "\n";

// Check current user
echo "\nCurrent user ID: " . VT_Auth::getCurrentUserId() . "\n";
echo "Is logged in: " . (VT_Auth::isLoggedIn() ? 'YES' : 'NO') . "\n";
?>