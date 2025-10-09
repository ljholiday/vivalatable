<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

final class AuthService
{
    private ?array $cachedUser = null;

    public function __construct(private Database $database)
    {
    }

    public function currentUserId(): ?int
    {
        $this->ensureSession();

        if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            $id = (int)$_SESSION['user_id'];
            if ($id > 0) {
                return $id;
            }
        }

        if (class_exists('\VT_Auth')) {
            $legacyId = (int)\VT_Auth::getCurrentUserId();
            if ($legacyId > 0) {
                return $legacyId;
            }
        }

        return null;
    }

    public function currentUserEmail(): ?string
    {
        $this->ensureSession();

        if (isset($_SESSION['user_email']) && is_string($_SESSION['user_email']) && $_SESSION['user_email'] !== '') {
            return (string)$_SESSION['user_email'];
        }

        $user = $this->getCurrentUser();
        if ($user !== null && isset($user->email) && $user->email !== '') {
            return (string)$user->email;
        }

        if (class_exists('\VT_Auth')) {
            $legacyUser = \VT_Auth::getCurrentUser();
            if ($legacyUser && isset($legacyUser->email) && $legacyUser->email !== '') {
                return (string)$legacyUser->email;
            }
        }

        return null;
    }

    public function getCurrentUser(): ?object
    {
        $id = $this->currentUserId();
        if ($id === null) {
            $this->cachedUser = null;
            return null;
        }

        if ($this->cachedUser !== null && (int)$this->cachedUser['id'] === $id) {
            return (object)$this->cachedUser;
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT id, username, email, display_name, status, created_at, updated_at
             FROM vt_users
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false || ($row['status'] ?? '') !== 'active') {
            $this->logout();
            return null;
        }

        $_SESSION['user_email'] = $row['email'];
        $this->cachedUser = $row;

        return (object)$row;
    }

    public function isLoggedIn(): bool
    {
        return $this->currentUserId() !== null;
    }

    public function currentUserCan(string $capability): bool
    {
        if (class_exists('\VT_Auth')) {
            try {
                return (bool)\VT_Auth::currentUserCan($capability);
            } catch (\Throwable $e) {
                // fall through to session-based check
            }
        }

        return false;
    }

    /**
     * @return array{
     *   success: bool,
     *   errors?: array<string,string>,
     *   user?: object
     * }
     */
    public function attemptLogin(string $identifier, string $password): array
    {
        $this->ensureSession();

        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return [
                'success' => false,
                'errors' => ['credentials' => 'Email and password are required.'],
            ];
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT id, username, email, password_hash, display_name, status, created_at, updated_at
             FROM vt_users
             WHERE (email = :email_identifier OR username = :username_identifier)
               AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute([
            ':email_identifier' => $identifier,
            ':username_identifier' => $identifier,
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $user === false ||
            !isset($user['password_hash']) ||
            !password_verify($password, (string)$user['password_hash'])
        ) {
            return [
                'success' => false,
                'errors' => ['credentials' => 'Invalid email or password.'],
            ];
        }

        $this->establishSession((int)$user['id'], (string)$user['email']);
        $this->cachedUser = $user;
        $this->updateLastLogin((int)$user['id']);

        return [
            'success' => true,
            'user' => (object)$user,
        ];
    }

    public function logout(): void
    {
        $this->ensureSession();
        $this->cachedUser = null;

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }

        session_destroy();
        session_start();
        session_regenerate_id(true);
    }

    /**
     * @param array<string,string>|string $input
     * @return array{
     *   success: bool,
     *   errors: array<string,string>,
     *   user_id?: int
     * }
     */
    public function register($input, ?string $email = null, ?string $password = null, ?string $displayName = null): array
    {
        $this->ensureSession();

        if (is_array($input)) {
            $username = trim($input['username'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = (string)($input['password'] ?? '');
            $displayName = trim($input['display_name'] ?? '');
        } else {
            $username = trim((string)$input);
            $email = trim((string)$email);
            $password = (string)$password;
            $displayName = trim((string)$displayName);
        }

        $errors = [];

        if ($displayName === '') {
            $errors['display_name'] = 'Display name is required.';
        }

        if ($username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,}$/', $username)) {
            $errors['username'] = 'Username must be at least 3 characters and contain only letters, numbers, dots, dashes, or underscores.';
        }

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please provide a valid email address.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($username !== '' && !isset($errors['username']) && $this->usernameExists($username)) {
            $errors['username'] = 'That username is already taken.';
        }

        if ($email !== '' && !isset($errors['email']) && $this->emailExists($email)) {
            $errors['email'] = 'That email is already registered.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $pdo = $this->database->pdo();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "INSERT INTO vt_users (
                username,
                email,
                password_hash,
                display_name,
                status,
                created_at,
                updated_at
            ) VALUES (
                :username,
                :email,
                :password_hash,
                :display_name,
                'active',
                :created_at,
                :updated_at
            )"
        );

        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':display_name' => $displayName,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $userId = (int)$pdo->lastInsertId();
        $this->createUserProfile($userId, $displayName);
        $this->createPersonalCommunities($userId);

        return [
            'success' => true,
            'errors' => [],
            'user_id' => $userId,
        ];
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function establishSession(int $userId, string $email): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
    }

    private function usernameExists(string $username): bool
    {
        if ($username === '') {
            return false;
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT 1 FROM vt_users WHERE username = :username LIMIT 1"
        );
        $stmt->execute([':username' => $username]);

        return $stmt->fetchColumn() !== false;
    }

    private function emailExists(string $email): bool
    {
        if ($email === '') {
            return false;
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT 1 FROM vt_users WHERE email = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email]);

        return $stmt->fetchColumn() !== false;
    }

    private function createUserProfile(int $userId, string $displayName): void
    {
        try {
            $stmt = $this->database->pdo()->prepare(
                "INSERT INTO vt_user_profiles (user_id, display_name)
                 VALUES (:user_id, :display_name)"
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':display_name' => $displayName,
            ]);
        } catch (\Throwable $e) {
            // Profiles are optional for now; ignore failures.
        }
    }

    private function createPersonalCommunities(int $userId): void
    {
        if (!class_exists('VT_Personal_Community_Service')) {
            return;
        }

        try {
            \VT_Personal_Community_Service::ensureBothCommunitiesForUser($userId);
        } catch (\Throwable $e) {
            // Ignore failures; legacy service is optional.
        }
    }

    private function updateLastLogin(int $userId): void
    {
        $stmt = $this->database->pdo()->prepare(
            "UPDATE vt_users SET last_login_at = :last_login_at WHERE id = :id"
        );
        $stmt->execute([
            ':last_login_at' => date('Y-m-d H:i:s'),
            ':id' => $userId,
        ]);
    }

    /**
     * Request password reset
     *
     * @return array{success:bool, errors?:array<string,string>, message?:string}
     */
    public function requestPasswordReset(string $email): array
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'errors' => ['email' => 'Valid email address required.'],
            ];
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare("SELECT id FROM vt_users WHERE email = :email AND status = 'active' LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return [
                'success' => true,
                'message' => 'If that email exists, a reset link has been sent.',
            ];
        }

        $userId = (int)$user['id'];
        $token = $this->generateSecureToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $insertStmt = $pdo->prepare(
            "INSERT INTO vt_password_reset_tokens (user_id, token, expires_at)
             VALUES (:user_id, :token, :expires_at)"
        );
        $insertStmt->execute([
            ':user_id' => $userId,
            ':token' => $token,
            ':expires_at' => $expiresAt,
        ]);

        $this->sendPasswordResetEmail($email, $token);

        return [
            'success' => true,
            'message' => 'If that email exists, a reset link has been sent.',
        ];
    }

    /**
     * Validate password reset token
     *
     * @return array{valid:bool, user_id?:int, error?:string}
     */
    public function validateResetToken(string $token): array
    {
        if ($token === '') {
            return ['valid' => false, 'error' => 'Token is required.'];
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            "SELECT id, user_id, expires_at, used_at
             FROM vt_password_reset_tokens
             WHERE token = :token
             LIMIT 1"
        );
        $stmt->execute([':token' => $token]);
        $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tokenRecord === false) {
            return ['valid' => false, 'error' => 'Invalid token.'];
        }

        if ($tokenRecord['used_at'] !== null) {
            return ['valid' => false, 'error' => 'Token already used.'];
        }

        if (strtotime($tokenRecord['expires_at']) < time()) {
            return ['valid' => false, 'error' => 'Token expired.'];
        }

        return [
            'valid' => true,
            'user_id' => (int)$tokenRecord['user_id'],
        ];
    }

    /**
     * Reset password with token
     *
     * @return array{success:bool, errors?:array<string,string>, message?:string}
     */
    public function resetPasswordWithToken(string $token, string $newPassword): array
    {
        $validation = $this->validateResetToken($token);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => ['token' => $validation['error'] ?? 'Invalid token.'],
            ];
        }

        if (strlen($newPassword) < 8) {
            return [
                'success' => false,
                'errors' => ['password' => 'Password must be at least 8 characters.'],
            ];
        }

        $userId = $validation['user_id'];
        $pdo = $this->database->pdo();

        $updateStmt = $pdo->prepare(
            "UPDATE vt_users
             SET password_hash = :password_hash, updated_at = :updated_at
             WHERE id = :id"
        );
        $updateStmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $userId,
        ]);

        $markUsedStmt = $pdo->prepare(
            "UPDATE vt_password_reset_tokens
             SET used_at = :used_at
             WHERE token = :token"
        );
        $markUsedStmt->execute([
            ':used_at' => date('Y-m-d H:i:s'),
            ':token' => $token,
        ]);

        return [
            'success' => true,
            'message' => 'Password reset successfully.',
        ];
    }

    /**
     * Send email verification
     *
     * @return array{success:bool, message?:string}
     */
    public function sendVerificationEmail(int $userId, string $email): array
    {
        $token = $this->generateSecureToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO vt_email_verification_tokens (user_id, email, token, expires_at)
             VALUES (:user_id, :email, :token, :expires_at)"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':email' => $email,
            ':token' => $token,
            ':expires_at' => $expiresAt,
        ]);

        $this->sendEmailVerificationEmail($email, $token);

        return [
            'success' => true,
            'message' => 'Verification email sent.',
        ];
    }

    /**
     * Verify email with token
     *
     * @return array{success:bool, errors?:array<string,string>, message?:string}
     */
    public function verifyEmail(string $token): array
    {
        if ($token === '') {
            return [
                'success' => false,
                'errors' => ['token' => 'Token is required.'],
            ];
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            "SELECT id, user_id, email, expires_at, verified_at
             FROM vt_email_verification_tokens
             WHERE token = :token
             LIMIT 1"
        );
        $stmt->execute([':token' => $token]);
        $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tokenRecord === false) {
            return [
                'success' => false,
                'errors' => ['token' => 'Invalid verification token.'],
            ];
        }

        if ($tokenRecord['verified_at'] !== null) {
            return [
                'success' => false,
                'errors' => ['token' => 'Email already verified.'],
            ];
        }

        if (strtotime($tokenRecord['expires_at']) < time()) {
            return [
                'success' => false,
                'errors' => ['token' => 'Verification token expired.'],
            ];
        }

        $markVerifiedStmt = $pdo->prepare(
            "UPDATE vt_email_verification_tokens
             SET verified_at = :verified_at
             WHERE id = :id"
        );
        $markVerifiedStmt->execute([
            ':verified_at' => date('Y-m-d H:i:s'),
            ':id' => $tokenRecord['id'],
        ]);

        return [
            'success' => true,
            'message' => 'Email verified successfully.',
        ];
    }

    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function sendPasswordResetEmail(string $email, string $token): void
    {
        if (!class_exists('\VT_Mail')) {
            $path = dirname(__DIR__, 2) . '/legacy/includes/includes/class-mail.php';
            if (is_file($path)) {
                require_once $path;
            }
        }

        $resetUrl = $this->getSiteUrl() . '/reset-password/' . $token;

        $variables = [
            'reset_url' => $resetUrl,
            'site_name' => 'VivalaTable',
            'subject' => 'Reset Your Password',
        ];

        if (class_exists('\VT_Mail')) {
            \VT_Mail::sendTemplate($email, 'password_reset', $variables);
        }
    }

    private function sendEmailVerificationEmail(string $email, string $token): void
    {
        if (!class_exists('\VT_Mail')) {
            $path = dirname(__DIR__, 2) . '/legacy/includes/includes/class-mail.php';
            if (is_file($path)) {
                require_once $path;
            }
        }

        $verifyUrl = $this->getSiteUrl() . '/verify-email/' . $token;

        $variables = [
            'verify_url' => $verifyUrl,
            'site_name' => 'VivalaTable',
            'subject' => 'Verify Your Email Address',
        ];

        if (class_exists('\VT_Mail')) {
            \VT_Mail::sendTemplate($email, 'email_verification', $variables);
        }
    }

    private function getSiteUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}
