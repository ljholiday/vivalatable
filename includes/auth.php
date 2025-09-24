<?php
/**
 * Authentication Functions
 * User authentication and session management
 */

/**
 * Check if user is logged in
 */
function is_user_logged_in(): bool {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Get current user ID
 */
function get_current_user_id(): int {
    return (int) ($_SESSION['user_id'] ?? 0);
}

/**
 * Get current user object
 */
function vt_get_current_user(): ?object {
    if (!is_user_logged_in()) {
        return null;
    }

    static $current_user = null;

    if ($current_user === null) {
        $user = new User();
        $current_user = $user->get_by_id(get_current_user_id());
    }

    return $current_user;
}

/**
 * Login user with email and password
 */
function vt_login_user(string $email, string $password, bool $remember = false): bool {
    $db = Database::getInstance();

    $user = $db->selectOne('users', ['email' => $email, 'status' => 'active']);

    if (!$user || !password_verify($password, $user->password_hash)) {
        return false;
    }

    // Start session
    $_SESSION['user_id'] = $user->id;
    $_SESSION['user_email'] = $user->email;
    $_SESSION['user_display_name'] = $user->display_name;

    // Update last login
    $db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user->id]);

    // Set remember me cookie if requested
    if ($remember) {
        $token = Database::generateToken(64);
        setcookie('vt_remember_token', $token, time() + (86400 * 30), '/', '', true, true); // 30 days

        // Store token in database (implement remember token table if needed)
    }

    return true;
}

/**
 * Register new user
 */
function vt_register_user(string $username, string $email, string $password, string $display_name = ''): ?int {
    $db = Database::getInstance();

    // Check if user already exists
    $existing_user = $db->selectOne('users', ['email' => $email]);
    if ($existing_user) {
        return null; // User already exists
    }

    $existing_username = $db->selectOne('users', ['username' => $username]);
    if ($existing_username) {
        return null; // Username already exists
    }

    // Create user
    $user_data = [
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'display_name' => $display_name ?: $username,
        'registered' => date('Y-m-d H:i:s')
    ];

    $user_id = $db->insert('users', $user_data);

    if ($user_id) {
        // Create user profile
        $profile_data = [
            'user_id' => $user_id,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $db->insert('user_profiles', $profile_data);

        return $user_id;
    }

    return null;
}

/**
 * Convert guest to user account
 */
function vt_convert_guest_to_user(string $email, string $name, string $password = ''): ?int {
    $db = Database::getInstance();

    // Check if user already exists
    $existing_user = $db->selectOne('users', ['email' => $email]);
    if ($existing_user) {
        return $existing_user->id;
    }

    // Generate username from email
    $username = vt_generate_username_from_email($email);

    // Generate password if not provided
    if (empty($password)) {
        $password = wp_generate_password(12);
    }

    // Create user account
    $user_id = vt_register_user($username, $email, $password, $name);

    if ($user_id) {
        // Update any existing guest records to link to this user
        $db->update('event_rsvps', ['user_id' => $user_id], ['email' => $email]);

        // Send welcome email with login credentials
        vt_send_welcome_email($email, $name, $username, $password);

        return $user_id;
    }

    return null;
}

/**
 * Generate unique username from email
 */
function vt_generate_username_from_email(string $email): string {
    $base_username = strtolower(explode('@', $email)[0]);
    $base_username = preg_replace('/[^a-z0-9]/', '', $base_username);

    if (strlen($base_username) < 3) {
        $base_username = 'user' . rand(100, 999);
    }

    $db = Database::getInstance();
    $username = $base_username;
    $counter = 1;

    while (true) {
        $existing = $db->selectOne('users', ['username' => $username]);
        if (!$existing) {
            return $username;
        }

        $username = $base_username . $counter;
        $counter++;

        // Prevent infinite loops
        if ($counter > 1000) {
            return $base_username . uniqid();
        }
    }
}

/**
 * Send welcome email to new user
 */
function vt_send_welcome_email(string $email, string $name, string $username, string $password): void {
    $subject = 'Welcome to VivalaTable!';

    $message = '
    <html>
    <head><title>Welcome to VivalaTable!</title></head>
    <body>
        <h2>Welcome to VivalaTable, ' . vt_escape_html($name) . '!</h2>

        <p>Your account has been created. Here are your login details:</p>

        <p><strong>Username:</strong> ' . vt_escape_html($username) . '<br>
        <strong>Email:</strong> ' . vt_escape_html($email) . '<br>
        <strong>Password:</strong> ' . vt_escape_html($password) . '</p>

        <p><a href="' . vt_base_url('/login') . '">Login to VivalaTable</a></p>

        <p>You can change your password after logging in.</p>

        <p>Happy event planning!<br>
        The VivalaTable Team</p>
    </body>
    </html>';

    vt_send_email($email, $subject, $message);
}

/**
 * Logout user
 */
function vt_logout_user(): void {
    // Clear remember token cookie
    if (isset($_COOKIE['vt_remember_token'])) {
        setcookie('vt_remember_token', '', time() - 3600, '/', '', true, true);
    }

    // Clear session
    session_unset();
    session_destroy();

    // Start new session
    session_start();
}

/**
 * Check if current user has permission
 */
function vt_user_can(string $capability, int $object_id = 0): bool {
    $user = vt_get_current_user();
    if (!$user) {
        return false;
    }

    switch ($capability) {
        case 'create_event':
            return true; // All logged-in users can create events

        case 'edit_event':
            if ($object_id > 0) {
                $db = Database::getInstance();
                $event = $db->selectOne('events', ['id' => $object_id]);
                return $event && $event->host_id == $user->id;
            }
            return false;

        case 'create_community':
            return true; // All logged-in users can create communities

        case 'manage_community':
            if ($object_id > 0) {
                $db = Database::getInstance();
                $membership = $db->selectOne('community_members', [
                    'community_id' => $object_id,
                    'user_id' => $user->id
                ]);
                return $membership && in_array($membership->role, ['admin', 'moderator']);
            }
            return false;

        case 'delete_community':
            if ($object_id > 0) {
                $db = Database::getInstance();
                $community = $db->selectOne('communities', ['id' => $object_id]);
                return $community && $community->created_by == $user->id;
            }
            return false;

        default:
            return false;
    }
}

/**
 * Require user to be logged in
 */
function vt_require_login(string $redirect_to = ''): void {
    if (!is_user_logged_in()) {
        $redirect_url = '/login';
        if ($redirect_to) {
            $redirect_url .= '?redirect_to=' . urlencode($redirect_to);
        }
        vt_redirect(vt_base_url($redirect_url));
    }
}

/**
 * Require specific user capability
 */
function vt_require_capability(string $capability, int $object_id = 0): void {
    vt_require_login();

    if (!vt_user_can($capability, $object_id)) {
        http_response_code(403);
        die('Access denied');
    }
}

/**
 * Password reset token generation
 */
function vt_generate_password_reset_token(string $email): ?string {
    $db = Database::getInstance();

    $user = $db->selectOne('users', ['email' => $email, 'status' => 'active']);
    if (!$user) {
        return null;
    }

    $token = Database::generateToken(64);
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store token (you may need to create a password_reset_tokens table)
    $reset_data = [
        'user_id' => $user->id,
        'token' => $token,
        'expires_at' => $expires,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // For now, store in session or implement proper table
    $_SESSION['password_reset_token'] = $token;
    $_SESSION['password_reset_user_id'] = $user->id;
    $_SESSION['password_reset_expires'] = $expires;

    return $token;
}

/**
 * Verify password reset token
 */
function vt_verify_password_reset_token(string $token): ?int {
    if (!isset($_SESSION['password_reset_token']) ||
        $_SESSION['password_reset_token'] !== $token ||
        strtotime($_SESSION['password_reset_expires']) < time()) {
        return null;
    }

    return (int) $_SESSION['password_reset_user_id'];
}

/**
 * Reset user password
 */
function vt_reset_password(string $token, string $new_password): bool {
    $user_id = vt_verify_password_reset_token($token);
    if (!$user_id) {
        return false;
    }

    $db = Database::getInstance();
    $result = $db->update('users', [
        'password_hash' => password_hash($new_password, PASSWORD_DEFAULT)
    ], ['id' => $user_id]);

    if ($result) {
        // Clear reset token from session
        unset($_SESSION['password_reset_token']);
        unset($_SESSION['password_reset_user_id']);
        unset($_SESSION['password_reset_expires']);
        return true;
    }

    return false;
}