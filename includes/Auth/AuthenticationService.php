<?php
/**
 * Authentication Service
 * Modern replacement for VT_Auth static methods
 */

class VT_Auth_AuthenticationService {

    private VT_Auth_UserRepository $userRepository;
    private ?object $currentUser = null;
    private int $currentUserId = 0;
    private bool $initialized = false;

    public function __construct(VT_Auth_UserRepository $userRepository) {
        $this->userRepository = $userRepository;
    }

    /**
     * Initialize authentication system
     */
    public function init(): void {
        if ($this->initialized) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->loadCurrentUser();
        $this->initialized = true;
    }

    /**
     * Login user with email/username and password
     */
    public function login(string $emailOrUsername, string $password): bool {
        $user = $this->userRepository->findByEmailOrUsername($emailOrUsername);

        if ($user && password_verify($password, $user->password_hash)) {
            $_SESSION['user_id'] = $user->id;
            $this->currentUserId = $user->id;
            $this->currentUser = $user;

            // Update last login timestamp
            $this->userRepository->updateLastLogin($user->id);

            return true;
        }

        return false;
    }

    /**
     * Login as guest with token
     */
    public function loginAsGuest(string $guestToken): bool {
        $guest = $this->userRepository->findGuestByToken($guestToken);
        if ($guest) {
            $_SESSION['guest_token'] = $guestToken;
            $this->currentUser = $guest;
            return true;
        }
        return false;
    }

    /**
     * Logout current user
     */
    public function logout(): void {
        session_destroy();
        $this->currentUser = null;
        $this->currentUserId = 0;
    }

    /**
     * Logout and redirect
     */
    public function logoutAndRedirect(string $redirectUrl = '/'): void {
        $this->logout();
        header("Location: $redirectUrl");
        exit;
    }

    /**
     * Register new user
     */
    public function register(string $username, string $email, string $password, string $displayName = ''): int {
        // Check if user already exists
        if ($this->userRepository->existsByEmailOrUsername($email, $username)) {
            throw new InvalidArgumentException('User with this email or username already exists');
        }

        // Validate input
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters long');
        }

        if (empty($displayName)) {
            $displayName = $username;
        }

        $userData = [
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $displayName
        ];

        return $this->userRepository->create($userData);
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool {
        $this->init();
        return $this->currentUser !== null && $this->currentUserId > 0;
    }

    /**
     * Check if current user is guest
     */
    public function isGuest(): bool {
        $this->init();
        return $this->currentUser !== null && $this->currentUserId === 0;
    }

    /**
     * Get current user
     */
    public function getCurrentUser(): ?object {
        $this->init();
        return $this->currentUser;
    }

    /**
     * Get current user ID
     */
    public function getCurrentUserId(): int {
        $this->init();
        return $this->currentUserId;
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $userId): ?object {
        return $this->userRepository->findById($userId);
    }

    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?object {
        return $this->userRepository->findByEmail($email);
    }

    /**
     * Check if current user has capability
     */
    public function currentUserCan(string $capability): bool {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $capabilities = $this->userRepository->getUserCapabilities($this->currentUserId);
        return in_array($capability, $capabilities);
    }

    /**
     * Check if user has capability by ID
     */
    public function userCan(int $userId, string $capability): bool {
        $capabilities = $this->userRepository->getUserCapabilities($userId);
        return in_array($capability, $capabilities);
    }

    /**
     * Check if current user is admin
     */
    public function isAdmin(): bool {
        return $this->currentUserCan('manage_options');
    }

    /**
     * Check if current user is site admin
     */
    public function isSiteAdmin(): bool {
        return $this->isAdmin();
    }

    /**
     * Generate guest token
     */
    public function generateGuestToken(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * Create guest user
     */
    public function createGuest(string $email, array $additionalData = []): string {
        $token = $this->generateGuestToken();

        $guestData = array_merge([
            'guest_token' => $token,
            'email' => $email
        ], $additionalData);

        $this->userRepository->createGuest($guestData);
        return $token;
    }

    /**
     * Update user profile
     */
    public function updateUserProfile(int $userId, array $data): bool {
        // Remove sensitive fields that shouldn't be updated this way
        unset($data['password_hash'], $data['id'], $data['created_at']);

        return $this->userRepository->update($userId, $data);
    }

    /**
     * Change user password
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            return false;
        }

        // Verify current password
        if (!password_verify($currentPassword, $user->password_hash)) {
            return false;
        }

        // Validate new password
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters long');
        }

        return $this->userRepository->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $email, string $token, string $newPassword): bool {
        // This would typically involve a password reset tokens table
        // For now, implementing basic version
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            return false;
        }

        // Validate new password
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters long');
        }

        return $this->userRepository->update($user->id, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
    }

    /**
     * Search users
     */
    public function searchUsers(string $search, int $limit = 20): array {
        return $this->userRepository->searchUsers($search, $limit);
    }

    /**
     * Login user by ID
     */
    public function loginById(int $userId): bool {
        $user = $this->userRepository->findById($userId);
        if ($user) {
            $_SESSION['user_id'] = $user->id;
            $this->currentUserId = $user->id;
            $this->currentUser = $user;
            return true;
        }
        return false;
    }

    /**
     * Load current user from session
     */
    private function loadCurrentUser(): void {
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
            $this->currentUserId = $_SESSION['user_id'];
            $this->currentUser = $this->userRepository->findById($this->currentUserId);

            // If user doesn't exist in database, clear invalid session
            if (!$this->currentUser) {
                unset($_SESSION['user_id']);
                $this->currentUserId = 0;
            }
        } elseif (isset($_SESSION['guest_token'])) {
            // Handle guest sessions
            $this->currentUser = $this->userRepository->findGuestByToken($_SESSION['guest_token']);

            // If guest doesn't exist, clear invalid session
            if (!$this->currentUser) {
                unset($_SESSION['guest_token']);
            }
        }
    }
}