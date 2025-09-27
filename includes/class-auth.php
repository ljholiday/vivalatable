<?php
/**
 * VivalaTable Authentication System
 * Replacement for WordPress user authentication
 */

class VT_Auth {
    private static $current_user = null;
    private static $current_user_id = 0;

    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        self::loadCurrentUser();
    }

    private static function loadCurrentUser() {
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
            self::$current_user_id = $_SESSION['user_id'];
            self::$current_user = self::getUserById(self::$current_user_id);
        } elseif (isset($_SESSION['guest_token'])) {
            // Handle guest sessions
            self::$current_user = self::getGuestByToken($_SESSION['guest_token']);
        }
    }

    public static function login($email_or_username, $password) {
        $db = VT_Database::getInstance();

        $query = "SELECT * FROM vt_users WHERE (email = ? OR login = ?) AND status = 'active' LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$email_or_username, $email_or_username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user->password_hash)) {
            $_SESSION['user_id'] = $user->id;
            self::$current_user_id = $user->id;
            self::$current_user = $user;

            // Update last login
            $db->update('users', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $user->id]);

            return true;
        }

        return false;
    }

    public static function loginAsGuest($guest_token) {
        $guest = self::getGuestByToken($guest_token);
        if ($guest) {
            $_SESSION['guest_token'] = $guest_token;
            self::$current_user = $guest;
            return true;
        }
        return false;
    }

    public static function logout() {
        session_destroy();
        self::$current_user = null;
        self::$current_user_id = 0;
    }

    public static function logoutAndRedirect($params = array()) {
        self::logout();
        // Redirect to home page after logout
        header('Location: /');
        exit;
    }

    public static function register($username, $email, $password, $display_name = '') {
        $db = VT_Database::getInstance();

        // Check if user exists
        $existing = $db->getVar("SELECT id FROM vt_users WHERE email = '$email' OR login = '$username'");
        if ($existing) {
            return false;
        }

        $user_data = [
            'login' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $display_name ?: $username,
            'status' => 'active'
        ];

        $user_id = $db->insert('users', $user_data);

        if ($user_id) {
            // Create user profile
            $db->insert('user_profiles', [
                'user_id' => $user_id,
                'display_name' => $display_name ?: $username
            ]);

            // Create member identity (if class exists)
            if (class_exists('VT_Member_Identity_Manager')) {
                $identity_manager = new VT_Member_Identity_Manager();
                $identity_manager->ensureIdentityExists($user_id, $email, $display_name ?: $username);
            }

            return $user_id;
        }

        return false;
    }

    public static function getCurrentUser() {
        return self::$current_user;
    }

    public static function getCurrentUserId() {
        return self::$current_user_id;
    }

    public static function isLoggedIn() {
        return self::$current_user_id > 0;
    }

    public static function isGuest() {
        return isset($_SESSION['guest_token']) && !self::isLoggedIn();
    }

    public static function currentUserCan($capability) {
        if (!self::isLoggedIn()) {
            return false;
        }

        // Basic capability mapping
        switch ($capability) {
            case 'manage_options':
                return self::isAdmin();
            case 'edit_posts':
            case 'delete_posts':
                return true; // All logged-in users can manage their own content
            case 'edit_others_posts':
            case 'delete_others_posts':
                return self::isAdmin();
            default:
                return false;
        }
    }

    public static function isAdmin() {
        if (!self::$current_user) {
            return false;
        }

        // Check if user has admin role in any community or is site admin
        $db = VT_Database::getInstance();
        $admin_count = $db->getVar(
            "SELECT COUNT(*) FROM vt_community_members
             WHERE user_id = " . self::$current_user_id . "
             AND role = 'admin'"
        );

        return $admin_count > 0 || self::isSiteAdmin();
    }

    public static function isSiteAdmin() {
        // Define site admin users (could be stored in config)
        $site_admins = VT_Config::get('site_admins', []);
        return in_array(self::$current_user_id, $site_admins);
    }

    public static function getUserById($user_id) {
        $db = VT_Database::getInstance();
        return $db->getRow("SELECT * FROM vt_users WHERE id = $user_id AND status = 'active'");
    }

    private static function getGuestByToken($token) {
        $db = VT_Database::getInstance();
        return $db->getRow("SELECT * FROM vt_guests WHERE rsvp_token = '$token' OR temporary_guest_id = '$token'");
    }

    public static function generateGuestToken() {
        return bin2hex(random_bytes(16)); // 32 character token
    }

    public static function convertGuestToUser($guest_token, $username, $password, $display_name = '') {
        $guest = self::getGuestByToken($guest_token);
        if (!$guest) {
            return false;
        }

        // Register new user
        $user_id = self::register($username, $guest->email, $password, $display_name);
        if (!$user_id) {
            return false;
        }

        // Update guest record
        $db = VT_Database::getInstance();
        $db->update('guests', ['converted_user_id' => $user_id], ['id' => $guest->id]);

        // Update any RSVPs or other records
        $db->update('event_rsvps', ['user_id' => $user_id], ['email' => $guest->email]);
        $db->update('rsvps', ['user_id' => $user_id], ['guest_email' => $guest->email]);

        return $user_id;
    }
}

// Initialize authentication on load
VT_Auth::init();