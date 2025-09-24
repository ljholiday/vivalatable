<?php
/**
 * User Class
 * User management and profile handling
 */

class User {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get user by ID
     */
    public function get_by_id(int $user_id): ?object {
        $sql = "
            SELECT u.*, p.*
            FROM " . Database::table('users') . " u
            LEFT JOIN " . Database::table('user_profiles') . " p ON u.id = p.user_id
            WHERE u.id = :user_id AND u.status = 'active'
            LIMIT 1
        ";

        $stmt = $this->db->query($sql, ['user_id' => $user_id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get user by email
     */
    public function get_by_email(string $email): ?object {
        $sql = "
            SELECT u.*, p.*
            FROM " . Database::table('users') . " u
            LEFT JOIN " . Database::table('user_profiles') . " p ON u.id = p.user_id
            WHERE u.email = :email AND u.status = 'active'
            LIMIT 1
        ";

        $stmt = $this->db->query($sql, ['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get user by username
     */
    public function get_by_username(string $username): ?object {
        $sql = "
            SELECT u.*, p.*
            FROM " . Database::table('users') . " u
            LEFT JOIN " . Database::table('user_profiles') . " p ON u.id = p.user_id
            WHERE u.username = :username AND u.status = 'active'
            LIMIT 1
        ";

        $stmt = $this->db->query($sql, ['username' => $username]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create new user
     */
    public function create(array $data): ?int {
        // Validate required fields
        $required_fields = ['username', 'email', 'password'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return null;
            }
        }

        // Check for existing user
        if ($this->email_exists($data['email'])) {
            return null;
        }

        if ($this->username_exists($data['username'])) {
            return null;
        }

        $this->db->beginTransaction();

        try {
            // Create user record
            $user_data = [
                'username' => vt_sanitize_text($data['username']),
                'email' => filter_var($data['email'], FILTER_VALIDATE_EMAIL),
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'display_name' => vt_sanitize_text($data['display_name'] ?? $data['username']),
                'registered' => date('Y-m-d H:i:s'),
                'status' => 'active'
            ];

            $user_id = $this->db->insert('users', $user_data);

            if (!$user_id) {
                throw new Exception('Failed to create user');
            }

            // Create user profile
            $profile_data = [
                'user_id' => $user_id,
                'bio' => vt_sanitize_textarea($data['bio'] ?? ''),
                'location' => vt_sanitize_text($data['location'] ?? ''),
                'timezone' => $data['timezone'] ?? 'America/New_York',
                'hosting_style' => $data['hosting_style'] ?? 'casual',
                'hosting_experience' => $data['hosting_experience'] ?? 'beginner',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('user_profiles', $profile_data);

            $this->db->commit();
            return $user_id;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('User creation failed', ['error' => $e->getMessage(), 'data' => $data]);
            return null;
        }
    }

    /**
     * Update user profile
     */
    public function update_profile(int $user_id, array $data): bool {
        $this->db->beginTransaction();

        try {
            // Update user table if needed
            $user_updates = [];
            if (isset($data['display_name'])) {
                $user_updates['display_name'] = vt_sanitize_text($data['display_name']);
            }

            if (!empty($user_updates)) {
                $user_updates['updated_at'] = date('Y-m-d H:i:s');
                $this->db->update('users', $user_updates, ['id' => $user_id]);
            }

            // Update profile table
            $profile_updates = [
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $profile_fields = [
                'bio', 'location', 'website', 'phone', 'timezone', 'avatar_url',
                'hosting_style', 'hosting_experience', 'dietary_preferences',
                'accessibility_needs', 'privacy_settings', 'notification_settings'
            ];

            foreach ($profile_fields as $field) {
                if (isset($data[$field])) {
                    if (in_array($field, ['bio', 'dietary_preferences', 'accessibility_needs'])) {
                        $profile_updates[$field] = vt_sanitize_textarea($data[$field]);
                    } else {
                        $profile_updates[$field] = vt_sanitize_text($data[$field]);
                    }
                }
            }

            $this->db->update('user_profiles', $profile_updates, ['user_id' => $user_id]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('Profile update failed', ['user_id' => $user_id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if email already exists
     */
    public function email_exists(string $email): bool {
        $user = $this->db->selectOne('users', ['email' => $email]);
        return $user !== null;
    }

    /**
     * Check if username already exists
     */
    public function username_exists(string $username): bool {
        $user = $this->db->selectOne('users', ['username' => $username]);
        return $user !== null;
    }

    /**
     * Change user password
     */
    public function change_password(int $user_id, string $current_password, string $new_password): bool {
        $user = $this->get_by_id($user_id);
        if (!$user) {
            return false;
        }

        if (!password_verify($current_password, $user->password_hash)) {
            return false;
        }

        return $this->db->update('users', [
            'password_hash' => password_hash($new_password, PASSWORD_DEFAULT)
        ], ['id' => $user_id]) > 0;
    }

    /**
     * Get user's hosting statistics
     */
    public function get_hosting_stats(int $user_id): array {
        $sql = "
            SELECT
                COUNT(CASE WHEN e.event_date >= NOW() THEN 1 END) as upcoming_events,
                COUNT(CASE WHEN e.event_date < NOW() THEN 1 END) as past_events,
                COUNT(DISTINCT r.id) as total_rsvps,
                COUNT(CASE WHEN r.status = 'attending' THEN 1 END) as attending_count,
                AVG(CASE WHEN e.event_date < NOW() THEN 5 ELSE NULL END) as avg_rating
            FROM " . Database::table('events') . " e
            LEFT JOIN " . Database::table('event_rsvps') . " r ON e.id = r.event_id
            WHERE e.host_id = :user_id AND e.event_status = 'active'
        ";

        $stmt = $this->db->query($sql, ['user_id' => $user_id]);
        $stats = $stmt->fetch();

        return [
            'events_created' => ($stats->upcoming_events ?? 0) + ($stats->past_events ?? 0),
            'upcoming_events' => $stats->upcoming_events ?? 0,
            'past_events' => $stats->past_events ?? 0,
            'total_rsvps' => $stats->total_rsvps ?? 0,
            'attending_count' => $stats->attending_count ?? 0,
            'avg_rating' => round($stats->avg_rating ?? 0, 1)
        ];
    }

    /**
     * Get user's community memberships
     */
    public function get_communities(int $user_id): array {
        $sql = "
            SELECT c.*, cm.role, cm.joined_at
            FROM " . Database::table('communities') . " c
            INNER JOIN " . Database::table('community_members') . " cm ON c.id = cm.community_id
            WHERE cm.user_id = :user_id AND cm.status = 'active' AND c.status = 'active'
            ORDER BY cm.joined_at DESC
        ";

        $stmt = $this->db->query($sql, ['user_id' => $user_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get user's upcoming events (as host or attendee)
     */
    public function get_upcoming_events(int $user_id, int $limit = 5): array {
        $sql = "
            SELECT e.*, 'host' as relationship
            FROM " . Database::table('events') . " e
            WHERE e.host_id = :user_id
                AND e.event_date >= NOW()
                AND e.event_status = 'active'

            UNION

            SELECT e.*, 'attendee' as relationship
            FROM " . Database::table('events') . " e
            INNER JOIN " . Database::table('event_rsvps') . " r ON e.id = r.event_id
            WHERE r.user_id = :user_id
                AND r.status = 'attending'
                AND e.event_date >= NOW()
                AND e.event_status = 'active'
                AND e.host_id != :user_id

            ORDER BY event_date ASC
            LIMIT :limit
        ";

        $stmt = $this->db->query($sql, [
            'user_id' => $user_id,
            'limit' => $limit
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Delete user account
     */
    public function delete_account(int $user_id): bool {
        $this->db->beginTransaction();

        try {
            // Set user status to deleted (don't actually delete due to foreign keys)
            $this->db->update('users', [
                'status' => 'deleted',
                'email' => 'deleted_' . time() . '@deleted.local',
                'username' => 'deleted_' . time(),
                'password_hash' => '',
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $user_id]);

            // Clear profile data
            $this->db->update('user_profiles', [
                'bio' => '',
                'location' => '',
                'website' => '',
                'phone' => '',
                'avatar_url' => '',
                'dietary_preferences' => '',
                'accessibility_needs' => '',
                'social_links' => '',
                'updated_at' => date('Y-m-d H:i:s')
            ], ['user_id' => $user_id]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('Account deletion failed', ['user_id' => $user_id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get users by search criteria
     */
    public function search(string $query, int $limit = 20): array {
        $search_query = '%' . $query . '%';

        $sql = "
            SELECT u.id, u.username, u.email, u.display_name, p.avatar_url, p.location
            FROM " . Database::table('users') . " u
            LEFT JOIN " . Database::table('user_profiles') . " p ON u.id = p.user_id
            WHERE u.status = 'active'
                AND (
                    u.display_name LIKE :query
                    OR u.username LIKE :query
                    OR u.email LIKE :query
                    OR p.location LIKE :query
                )
            ORDER BY u.display_name ASC
            LIMIT :limit
        ";

        $stmt = $this->db->query($sql, [
            'query' => $search_query,
            'limit' => $limit
        ]);

        return $stmt->fetchAll();
    }
}