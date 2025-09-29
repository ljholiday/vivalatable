<?php
/**
 * Create personal community for existing user
 * Run this once to fix missing personal communities
 */

require_once 'includes/bootstrap.php';

// Get your user ID (replace with actual ID if different)
$user_email = 'lonn@ljholiday.com';
$db = VT_Database::getInstance();

$user = $db->getRow($db->prepare(
    "SELECT * FROM vt_users WHERE email = %s",
    $user_email
));

if (!$user) {
    echo "User not found with email: $user_email\n";
    exit(1);
}

echo "Found user: {$user->display_name} (ID: {$user->id})\n";

// Create personal community
if (class_exists('VT_Personal_Community_Service')) {
    echo "Creating personal community...\n";
    $result = VT_Personal_Community_Service::ensurePersonalCommunityForUser($user->id);

    if ($result) {
        echo "Personal community created successfully!\n";
    } else {
        echo "Failed to create personal community. Check error logs.\n";
    }
} else {
    echo "VT_Personal_Community_Service class not found.\n";
}
?>