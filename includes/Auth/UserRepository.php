<?php
/**
 * User Repository
 * Database access layer for user management
 */

class VT_Auth_UserRepository {

    private VT_Database_Connection $connection;

    public function __construct(VT_Database_Connection $connection) {
        $this->connection = $connection;
    }

    /**
     * Find user by email or username
     */
    public function findByEmailOrUsername(string $emailOrUsername): ?object {
        $query = "SELECT * FROM vt_users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1";
        return $this->connection->fetchRow($query, [$emailOrUsername, $emailOrUsername]);
    }

    /**
     * Find user by ID
     */
    public function findById(int $userId): ?object {
        $query = "SELECT * FROM vt_users WHERE id = ? AND status = 'active' LIMIT 1";
        return $this->connection->fetchRow($query, [$userId]);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?object {
        $query = "SELECT * FROM vt_users WHERE email = ? AND status = 'active' LIMIT 1";
        return $this->connection->fetchRow($query, [$email]);
    }

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?object {
        $query = "SELECT * FROM vt_users WHERE username = ? AND status = 'active' LIMIT 1";
        return $this->connection->fetchRow($query, [$username]);
    }

    /**
     * Check if user exists by email or username
     */
    public function existsByEmailOrUsername(string $email, string $username): bool {
        $query = "SELECT COUNT(*) as count FROM vt_users WHERE email = ? OR username = ?";
        $result = $this->connection->fetchRow($query, [$email, $username]);
        return $result && $result->count > 0;
    }

    /**
     * Create new user
     */
    public function create(array $userData): int {
        $requiredFields = ['username', 'email', 'password_hash', 'display_name'];
        foreach ($requiredFields as $field) {
            if (!isset($userData[$field])) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
        }

        $defaults = [
            'status' => 'active',
            'role' => 'user',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $userData = array_merge($defaults, $userData);
        return $this->connection->insert('users', $userData);
    }

    /**
     * Update user data
     */
    public function update(int $userId, array $data): bool {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $rowsAffected = $this->connection->update('users', $data, ['id' => $userId]);
        return $rowsAffected > 0;
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(int $userId): bool {
        return $this->update($userId, ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Deactivate user
     */
    public function deactivate(int $userId): bool {
        return $this->update($userId, ['status' => 'inactive']);
    }

    /**
     * Get user capabilities/permissions
     */
    public function getUserCapabilities(int $userId): array {
        $user = $this->findById($userId);
        if (!$user) {
            return [];
        }

        // Base capabilities by role
        $capabilities = [];
        switch ($user->role) {
            case 'admin':
                $capabilities = [
                    'manage_options',
                    'manage_users',
                    'edit_posts',
                    'delete_posts',
                    'delete_others_posts',
                    'publish_posts',
                    'moderate_comments'
                ];
                break;

            case 'moderator':
                $capabilities = [
                    'edit_posts',
                    'delete_posts',
                    'publish_posts',
                    'moderate_comments'
                ];
                break;

            case 'user':
            default:
                $capabilities = [
                    'edit_posts',
                    'delete_posts'
                ];
                break;
        }

        return $capabilities;
    }

    /**
     * Find guest by token
     */
    public function findGuestByToken(string $token): ?object {
        $query = "SELECT * FROM vt_guests WHERE guest_token = ? AND status = 'active' LIMIT 1";
        return $this->connection->fetchRow($query, [$token]);
    }

    /**
     * Create guest user
     */
    public function createGuest(array $guestData): int {
        $requiredFields = ['guest_token', 'email'];
        foreach ($requiredFields as $field) {
            if (!isset($guestData[$field])) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
        }

        $defaults = [
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $guestData = array_merge($defaults, $guestData);
        return $this->connection->insert('guests', $guestData);
    }

    /**
     * Update guest data
     */
    public function updateGuest(string $token, array $data): bool {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $rowsAffected = $this->connection->update('guests', $data, ['guest_token' => $token]);
        return $rowsAffected > 0;
    }

    /**
     * Get all users with pagination
     */
    public function getAllUsers(int $limit = 50, int $offset = 0): array {
        $query = "SELECT * FROM vt_users WHERE status = 'active' ORDER BY created_at DESC LIMIT ? OFFSET ?";
        return $this->connection->fetchAll($query, [$limit, $offset]);
    }

    /**
     * Count total users
     */
    public function countUsers(): int {
        $query = "SELECT COUNT(*) as count FROM vt_users WHERE status = 'active'";
        $result = $this->connection->fetchRow($query);
        return $result ? (int)$result->count : 0;
    }

    /**
     * Search users by display name or username
     */
    public function searchUsers(string $search, int $limit = 20): array {
        $searchTerm = '%' . $search . '%';
        $query = "SELECT * FROM vt_users
                  WHERE status = 'active'
                  AND (display_name LIKE ? OR username LIKE ?)
                  ORDER BY display_name
                  LIMIT ?";
        return $this->connection->fetchAll($query, [$searchTerm, $searchTerm, $limit]);
    }
}