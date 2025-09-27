<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Test user registration directly
echo "Testing user registration...\n";

$username = 'testuser2';
$email = 'test2@example.com';
$password = 'password123';
$display_name = 'Test User 2';

echo "Attempting to register: $username, $email\n";

$user_id = VT_Auth::register($username, $email, $password, $display_name);

if ($user_id) {
    echo "SUCCESS: User registered with ID: $user_id\n";

    // Check if user was actually created
    $db = VT_Database::getInstance();
    $user = $db->getRow("SELECT * FROM vt_users WHERE id = $user_id");
    echo "User data: " . json_encode($user) . "\n";

} else {
    echo "FAILED: Registration returned false\n";

    // Check if user already exists
    $db = VT_Database::getInstance();
    $existing = $db->getVar("SELECT id FROM vt_users WHERE email = '$email' OR login = '$username'");
    echo "Existing user check returned: " . ($existing ?: 'none') . "\n";
}
?>