<?php
/**
 * VivalaTable Permission System Test
 * Test authentication and authorization
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

class VT_Permission_Test {

    private $db;
    private $test_results = [];

    public function __construct() {
        $this->db = VT_Database::getInstance();
    }

    public function run_all_tests() {
        echo "🔐 Running VivalaTable Permission System Tests...\n\n";

        $this->test_user_registration();
        $this->test_user_login();
        $this->test_guest_tokens();
        $this->test_permission_levels();
        $this->test_admin_capabilities();

        $this->report_results();
    }

    private function test_user_registration() {
        echo "Testing user registration... ";

        try {
            $username = 'testuser_' . time();
            $email = 'test' . time() . '@example.com';
            $password = 'testpass123';
            $display_name = 'Test User';

            $user_id = VT_Auth::register($username, $email, $password, $display_name);

            if ($user_id) {
                // Verify user was created
                $user = $this->db->getRow("SELECT * FROM vt_users WHERE id = $user_id");

                if ($user && $user->email === $email && password_verify($password, $user->password_hash)) {
                    $this->pass("User registration successful");

                    // Clean up
                    $this->db->delete('users', ['id' => $user_id]);
                } else {
                    $this->fail("User registration verification failed");
                }
            } else {
                $this->fail("User registration returned false");
            }

        } catch (Exception $e) {
            $this->fail("User registration exception: " . $e->getMessage());
        }
    }

    private function test_user_login() {
        echo "Testing user login... ";

        try {
            // Create test user
            $username = 'testuser_' . time();
            $email = 'test' . time() . '@example.com';
            $password = 'testpass123';

            $user_id = VT_Auth::register($username, $email, $password, 'Test User');

            if (!$user_id) {
                $this->fail("Could not create test user for login test");
                return;
            }

            // Clear current session
            session_destroy();
            session_start();

            // Test login
            $login_success = VT_Auth::login($email, $password);

            if ($login_success) {
                $current_user_id = VT_Auth::getCurrentUserId();
                $current_user = VT_Auth::getCurrentUser();

                if ($current_user_id == $user_id && $current_user->email === $email) {
                    $this->pass("User login successful");
                } else {
                    $this->fail("Login successful but user data incorrect");
                }
            } else {
                $this->fail("User login failed");
            }

            // Clean up
            $this->db->delete('users', ['id' => $user_id]);

        } catch (Exception $e) {
            $this->fail("User login exception: " . $e->getMessage());
        }
    }

    private function test_guest_tokens() {
        echo "Testing guest token system... ";

        try {
            // Test token generation
            $token1 = VT_Auth::generateGuestToken();
            $token2 = VT_Auth::generateGuestToken();

            if (strlen($token1) === 32 && strlen($token2) === 32 && $token1 !== $token2) {
                $this->pass("Guest token generation successful (32-char unique tokens)");
            } else {
                $this->fail("Guest token generation failed (length: " . strlen($token1) . ", unique: " . ($token1 !== $token2 ? 'yes' : 'no') . ")");
            }

        } catch (Exception $e) {
            $this->fail("Guest token exception: " . $e->getMessage());
        }
    }

    private function test_permission_levels() {
        echo "Testing permission levels... ";

        try {
            // Create regular user
            $user_id = $this->createtest_user();

            // Simulate login
            $_SESSION['user_id'] = $user_id;
            VT_Auth::init(); // Reload current user

            // Test basic permissions
            $can_edit_posts = VT_Auth::currentUserCan('edit_posts');
            $cannot_manage_options = !VT_Auth::currentUserCan('manage_options');
            $cannot_delete_others = !VT_Auth::currentUserCan('delete_others_posts');

            if ($can_edit_posts && $cannot_manage_options && $cannot_delete_others) {
                $this->pass("Permission levels working correctly for regular user");
            } else {
                $this->fail("Permission levels incorrect (edit_posts: $can_edit_posts, manage_options: " . VT_Auth::currentUserCan('manage_options') . ", delete_others: " . VT_Auth::currentUserCan('delete_others_posts') . ")");
            }

            // Clean up
            $this->db->delete('users', ['id' => $user_id]);
            session_destroy();
            session_start();

        } catch (Exception $e) {
            $this->fail("Permission levels exception: " . $e->getMessage());
        }
    }

    private function test_admin_capabilities() {
        echo "Testing admin capabilities... ";

        try {
            // Create admin user by adding to community as admin
            $user_id = $this->createtest_user();
            $community_id = $this->createtest_community($user_id);

            // Add user as community admin
            $this->db->insert('community_members', [
                'community_id' => $community_id,
                'user_id' => $user_id,
                'email' => 'admin@example.com',
                'display_name' => 'Admin User',
                'role' => 'admin',
                'status' => 'active'
            ]);

            // Simulate login
            $_SESSION['user_id'] = $user_id;
            VT_Auth::init();

            // Test admin capabilities
            $is_admin = VT_Auth::isAdmin();

            if ($is_admin) {
                $this->pass("Admin capabilities working correctly");
            } else {
                $this->fail("Admin capabilities not working");
            }

            // Clean up
            $this->db->delete('community_members', ['community_id' => $community_id]);
            $this->db->delete('communities', ['id' => $community_id]);
            $this->db->delete('users', ['id' => $user_id]);
            session_destroy();
            session_start();

        } catch (Exception $e) {
            $this->fail("Admin capabilities exception: " . $e->getMessage());
        }
    }

    private function create_test_user() {
        $user_data = [
            'username' => 'testuser_' . time() . '_' . rand(100, 999),
            'email' => 'test' . time() . rand(100, 999) . '@example.com',
            'password_hash' => password_hash('testpass', PASSWORD_DEFAULT),
            'display_name' => 'Test User',
            'status' => 'active'
        ];

        return $this->db->insert('users', $user_data);
    }

    private function create_test_community($creator_id) {
        $community_data = [
            'name' => 'Test Community ' . time(),
            'slug' => 'test-community-' . time(),
            'description' => 'Test community',
            'visibility' => 'public',
            'creator_id' => $creator_id,
            'creator_email' => 'creator@example.com',
            'is_active' => 1,
            'member_count' => 1
        ];

        return $this->db->insert('communities', $community_data);
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
        echo "PERMISSION SYSTEM TEST RESULTS\n";
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
            echo "🎉 Excellent! Permission system is working well.\n";
        } elseif ($success_rate >= 70) {
            echo "⚠️  Good, but some permission issues need attention.\n";
        } else {
            echo "🚨 Critical permission system issues found.\n";
        }
    }
}

// Run tests
$test = new VT_Permission_Test();
$test->run_all_tests();