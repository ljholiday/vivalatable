<?php
/**
 * VivalaTable Database Operations Test
 * Unit tests for core database functionality
 */

// Load VivalaTable bootstrap
require_once dirname(__DIR__) . '/includes/bootstrap.php';

class VT_Database_Test {

    private $db;
    private $test_results = [];

    public function __construct() {
        $this->db = VT_Database::getInstance();
    }

    public function run_all_tests() {
        echo "🧪 Running VivalaTable Database Tests...\n\n";

        $this->test_database_connection();
        $this->test_table_structure();
        $this->test_basic_crud_operations();
        $this->test_event_operations();
        $this->test_guest_operations();
        $this->test_user_operations();

        $this->report_results();
    }

    private function test_database_connection() {
        echo "Testing database connection... ";
        try {
            $result = $this->db->getVar("SELECT 1");
            if ($result == 1) {
                $this->pass("Database connection successful");
            } else {
                $this->fail("Database connection failed - unexpected result");
            }
        } catch (Exception $e) {
            $this->fail("Database connection failed: " . $e->getMessage());
        }
    }

    private function test_table_structure() {
        echo "Testing table structure... ";

        $required_tables = [
            'vt_users', 'vt_sessions', 'vt_config',
            'vt_events', 'vt_guests', 'vt_communities',
            'vt_community_members', 'vt_conversations',
            'vt_event_invitations', 'vt_user_profiles'
        ];

        $missing_tables = [];
        foreach ($required_tables as $table) {
            $exists = $this->db->getVar("SHOW TABLES LIKE '$table'");
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }

        if (empty($missing_tables)) {
            $this->pass("All required tables exist");
        } else {
            $this->fail("Missing tables: " . implode(', ', $missing_tables));
        }
    }

    private function test_basic_crud_operations() {
        echo "Testing basic CRUD operations... ";

        try {
            // Test INSERT
            $test_data = [
                'option_name' => 'test_option_' . time(),
                'option_value' => 'test_value_' . rand(1000, 9999),
                'autoload' => 'no'
            ];

            $insert_id = $this->db->insert('config', $test_data);

            if (!$insert_id) {
                $this->fail("INSERT operation failed");
                return;
            }

            // Test SELECT
            $retrieved = $this->db->getRow("SELECT * FROM vt_config WHERE option_name = '{$test_data['option_name']}'");

            if (!$retrieved || $retrieved->option_value !== $test_data['option_value']) {
                $this->fail("SELECT operation failed");
                return;
            }

            // Test UPDATE
            $new_value = 'updated_value_' . rand(1000, 9999);
            $update_result = $this->db->update('config',
                ['option_value' => $new_value],
                ['option_name' => $test_data['option_name']]
            );

            if (!$update_result) {
                $this->fail("UPDATE operation failed");
                return;
            }

            // Verify update
            $updated = $this->db->getVar("SELECT option_value FROM vt_config WHERE option_name = '{$test_data['option_name']}'");

            if ($updated !== $new_value) {
                $this->fail("UPDATE verification failed");
                return;
            }

            // Test DELETE
            $delete_result = $this->db->delete('config', ['option_name' => $test_data['option_name']]);

            if (!$delete_result) {
                $this->fail("DELETE operation failed");
                return;
            }

            // Verify deletion
            $deleted = $this->db->getVar("SELECT COUNT(*) FROM vt_config WHERE option_name = '{$test_data['option_name']}'");

            if ($deleted != 0) {
                $this->fail("DELETE verification failed");
                return;
            }

            $this->pass("All CRUD operations successful");

        } catch (Exception $e) {
            $this->fail("CRUD test exception: " . $e->getMessage());
        }
    }

    private function test_event_operations() {
        echo "Testing event operations... ";

        try {
            // Create test user first
            $test_user_id = $this->createtest_user();

            // Test event creation
            $event_data = [
                'title' => 'Test Event ' . time(),
                'slug' => 'test-event-' . time(),
                'description' => 'Test event description',
                'event_date' => date('Y-m-d H:i:s', strtotime('+1 week')),
                'privacy' => 'public',
                'author_id' => $test_user_id,
                'created_by' => $test_user_id,
                'event_status' => 'active'
            ];

            $event_id = $this->db->insert('events', $event_data);

            if (!$event_id) {
                $this->fail("Event creation failed");
                return;
            }

            // Test event retrieval
            $event = $this->db->getRow("SELECT * FROM vt_events WHERE id = $event_id");

            if (!$event || $event->title !== $event_data['title']) {
                $this->fail("Event retrieval failed");
                return;
            }

            // Test event update
            $new_title = 'Updated Test Event ' . time();
            $update_result = $this->db->update('events',
                ['title' => $new_title],
                ['id' => $event_id]
            );

            if (!$update_result) {
                $this->fail("Event update failed");
                return;
            }

            // Clean up
            $this->db->delete('events', ['id' => $event_id]);
            $this->db->delete('users', ['id' => $test_user_id]);

            $this->pass("Event operations successful");

        } catch (Exception $e) {
            $this->fail("Event test exception: " . $e->getMessage());
        }
    }

    private function test_guest_operations() {
        echo "Testing guest operations... ";

        try {
            // Create test event
            $test_user_id = $this->createtest_user();
            $event_id = $this->createtest_event($test_user_id);

            // Test guest creation
            $guest_data = [
                'event_id' => $event_id,
                'name' => 'Test Guest',
                'email' => 'test.guest' . time() . '@example.com',
                'status' => 'pending',
                'rsvp_token' => VT_Security::generateToken(),
                'temporary_guest_id' => VT_Security::generateToken()
            ];

            $guest_id = $this->db->insert('guests', $guest_data);

            if (!$guest_id) {
                $this->fail("Guest creation failed");
                return;
            }

            // Test guest token retrieval
            $guest = $this->db->getRow("SELECT * FROM vt_guests WHERE rsvp_token = '{$guest_data['rsvp_token']}'");

            if (!$guest || $guest->email !== $guest_data['email']) {
                $this->fail("Guest token retrieval failed");
                return;
            }

            // Test RSVP status update
            $update_result = $this->db->update('guests',
                ['status' => 'confirmed'],
                ['id' => $guest_id]
            );

            if (!$update_result) {
                $this->fail("Guest RSVP update failed");
                return;
            }

            // Test guest stats
            $stats = $this->db->getRow("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed
                FROM vt_guests WHERE event_id = $event_id
            ");

            if (!$stats || $stats->total != 1 || $stats->confirmed != 1) {
                $this->fail("Guest stats calculation failed");
                return;
            }

            // Clean up
            $this->db->delete('guests', ['id' => $guest_id]);
            $this->db->delete('events', ['id' => $event_id]);
            $this->db->delete('users', ['id' => $test_user_id]);

            $this->pass("Guest operations successful");

        } catch (Exception $e) {
            $this->fail("Guest test exception: " . $e->getMessage());
        }
    }

    private function test_user_operations() {
        echo "Testing user operations... ";

        try {
            // Test user creation
            $user_data = [
                'username' => 'testuser' . time(),
                'email' => 'test' . time() . '@example.com',
                'password_hash' => password_hash('testpass123', PASSWORD_DEFAULT),
                'display_name' => 'Test User',
                'status' => 'active'
            ];

            $user_id = $this->db->insert('users', $user_data);

            if (!$user_id) {
                $this->fail("User creation failed");
                return;
            }

            // Test user authentication data
            $user = $this->db->getRow("SELECT * FROM vt_users WHERE email = '{$user_data['email']}'");

            if (!$user || !password_verify('testpass123', $user->password_hash)) {
                $this->fail("User password verification failed");
                return;
            }

            // Test user profile creation
            $profile_data = [
                'user_id' => $user_id,
                'display_name' => 'Test User Profile',
                'bio' => 'Test bio',
                'events_hosted' => 0,
                'events_attended' => 0
            ];

            $profile_id = $this->db->insert('user_profiles', $profile_data);

            if (!$profile_id) {
                $this->fail("User profile creation failed");
                return;
            }

            // Clean up
            $this->db->delete('user_profiles', ['id' => $profile_id]);
            $this->db->delete('users', ['id' => $user_id]);

            $this->pass("User operations successful");

        } catch (Exception $e) {
            $this->fail("User test exception: " . $e->getMessage());
        }
    }

    private function create_test_user() {
        $user_data = [
            'username' => 'testuser' . time() . rand(100, 999),
            'email' => 'test' . time() . rand(100, 999) . '@example.com',
            'password_hash' => password_hash('testpass', PASSWORD_DEFAULT),
            'display_name' => 'Test User',
            'status' => 'active'
        ];

        return $this->db->insert('users', $user_data);
    }

    private function create_test_event($user_id) {
        $event_data = [
            'title' => 'Test Event ' . time(),
            'slug' => 'test-event-' . time() . '-' . rand(100, 999),
            'description' => 'Test event',
            'event_date' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'privacy' => 'public',
            'author_id' => $user_id,
            'created_by' => $user_id,
            'event_status' => 'active'
        ];

        return $this->db->insert('events', $event_data);
    }

    private function pass($message) {
        echo "✅ PASS\n";
        $this->test_results[] = ['status' => 'PASS', 'message' => $message];
    }

    private function fail($message) {
        echo "❌ FAIL\n";
        $this->test_results[] = ['status' => 'FAIL', 'message' => $message];
    }

    private function report_results() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "TEST RESULTS SUMMARY\n";
        echo str_repeat("=", 50) . "\n";

        $total = count($this->test_results);
        $passed = array_filter($this->test_results, function($result) {
            return $result['status'] === 'PASS';
        });
        $failed = array_filter($this->test_results, function($result) {
            return $result['status'] === 'FAIL';
        });

        echo "Total Tests: $total\n";
        echo "Passed: " . count($passed) . " ✅\n";
        echo "Failed: " . count($failed) . " ❌\n";

        if (count($failed) > 0) {
            echo "\nFAILED TESTS:\n";
            foreach ($failed as $test) {
                echo "  ❌ " . $test['message'] . "\n";
            }
        }

        echo "\nSUCCESSFUL TESTS:\n";
        foreach ($passed as $test) {
            echo "  ✅ " . $test['message'] . "\n";
        }

        $success_rate = round((count($passed) / $total) * 100, 1);
        echo "\nSuccess Rate: $success_rate%\n";

        if ($success_rate >= 90) {
            echo "🎉 Excellent! Database operations are working well.\n";
        } elseif ($success_rate >= 70) {
            echo "⚠️  Good, but some issues need attention.\n";
        } else {
            echo "🚨 Critical issues found. Database needs fixes.\n";
        }
    }
}

// Run tests
$test = new VT_Database_Test();
$test->run_all_tests();