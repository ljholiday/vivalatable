<?php
/**
 * VivalaTable Database Migration Runner
 * Run this script to apply database migrations
 */

// Include the database configuration
require_once 'includes/class-database.php';

// Initialize database connection
$db = VT_Database::getInstance();

echo "Running VivalaTable Database Migrations...\n\n";

// Migration 001: Rename login to username
echo "Migration 001: Rename login field to username\n";

try {
    // Check if login column exists
    $columns = $db->query("SHOW COLUMNS FROM vt_users LIKE 'login'")->fetchAll();

    if (count($columns) > 0) {
        echo "  - Found login column, renaming to username...\n";

        // Rename the column
        $db->query("ALTER TABLE vt_users CHANGE COLUMN login username varchar(60) NOT NULL DEFAULT ''");

        // Update the unique key constraint
        $db->query("DROP INDEX login");
        $db->query("CREATE UNIQUE INDEX username ON vt_users (username)");

        echo "  ✓ Successfully renamed login to username\n";
    } else {
        echo "  - Login column not found, checking for username column...\n";

        $username_columns = $db->query("SHOW COLUMNS FROM vt_users LIKE 'username'")->fetchAll();
        if (count($username_columns) > 0) {
            echo "  ✓ Username column already exists\n";
        } else {
            echo "  ✗ Neither login nor username column found!\n";
        }
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\nMigrations completed!\n";
echo "Username/Display Name convention has been established:\n";
echo "- 'username' field: Used for login and @mentions\n";
echo "- 'display_name' field: Used for all user-facing displays\n";
echo "- Profile editing allows users to set their preferred Display Name\n";
echo "- Member display always prioritizes Display Name over username\n";
?>