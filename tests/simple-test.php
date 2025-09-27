<?php
/**
 * Simple Database Test
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Turn on error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

$db = VT_Database::getInstance();

echo "Simple Database Test\n";
echo "===================\n";

// Test 1: Basic connection
echo "1. Testing connection: ";
$result = $db->getVar("SELECT 1");
echo ($result == 1) ? "✅ PASS\n" : "❌ FAIL\n";

// Test 2: Table exists
echo "2. Testing table existence: ";
$exists = $db->getVar("SHOW TABLES LIKE 'vt_config'");
echo $exists ? "✅ PASS (table: $exists)\n" : "❌ FAIL\n";

// Test 3: Direct SQL insert
echo "3. Testing direct SQL insert: ";
$test_option = 'test_' . time() . '_' . rand(100, 999);
$test_value = 'value_' . rand(1000, 9999);

try {
    $result = $db->query("INSERT INTO vt_config (option_name, option_value, autoload) VALUES ('$test_option', '$test_value', 'no')");

    if ($result) {
        echo "✅ PASS\n";

        // Verify
        $retrieved = $db->getVar("SELECT option_value FROM vt_config WHERE option_name = '$test_option'");
        echo "   Retrieved: $retrieved\n";

        // Cleanup
        $db->query("DELETE FROM vt_config WHERE option_name = '$test_option'");
    } else {
        echo "❌ FAIL (query method returned false)\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL (exception: " . $e->getMessage() . ")\n";
}

// Test 4: VT_Database insert method
echo "4. Testing VT_Database insert method: ";
$test_data = [
    'option_name' => 'vt_test_' . time() . '_' . rand(100, 999),
    'option_value' => 'vt_value_' . rand(1000, 9999),
    'autoload' => 'no'
];

try {
    $insert_id = $db->insert('config', $test_data);

    if ($insert_id) {
        echo "✅ PASS (ID: $insert_id)\n";

        // Verify
        $retrieved = $db->getVar("SELECT option_value FROM vt_config WHERE id = $insert_id");
        echo "   Retrieved: $retrieved\n";

        // Cleanup
        $db->delete('config', ['id' => $insert_id]);
    } else {
        echo "❌ FAIL (insert method returned false/0)\n";

        // Check if it was actually inserted
        $check = $db->getVar("SELECT COUNT(*) FROM vt_config WHERE option_name = '{$test_data['option_name']}'");
        echo "   Rows with this option_name: $check\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL (exception: " . $e->getMessage() . ")\n";
}

echo "\nDone.\n";