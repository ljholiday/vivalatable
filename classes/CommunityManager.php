<?php
/**
 * Community Manager Class
 * Handles community creation, membership, and management
 */

class CommunityManager {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create new community
     */
    public function create_community(array $data): ?int {
        $required_fields = ['name', 'created_by'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return null;
            }
        }

        $this->db->beginTransaction();

        try {
            // Generate unique slug
            $slug = $this->generate_slug($data['name']);

            $community_data = [
                'name' => vt_sanitize_text($data['name']),
                'slug' => $slug,
                'description' => vt_sanitize_textarea($data['description'] ?? ''),
                'community_type' => $data['community_type'] ?? 'general',
                'privacy_level' => $data['privacy_level'] ?? 'public',
                'member_limit' => !empty($data['member_limit']) ? (int) $data['member_limit'] : null,
                'location' => vt_sanitize_text($data['location'] ?? ''),
                'website' => filter_var($data['website'] ?? '', FILTER_VALIDATE_URL) ?: null,
                'social_links' => $data['social_links'] ?? null,
                'rules' => vt_sanitize_textarea($data['rules'] ?? ''),
                'welcome_message' => vt_sanitize_textarea($data['welcome_message'] ?? ''),
                'tags' => $data['tags'] ?? null,
                'created_by' => (int) $data['created_by'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $community_id = $this->db->insert('communities', $community_data);

            if (!$community_id) {
                throw new Exception('Failed to create community');
            }

            // Add creator as admin member
            $membership_data = [
                'community_id' => $community_id,
                'user_id' => (int) $data['created_by'],
                'role' => 'admin',
                'status' => 'active',
                'joined_at' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('community_members', $membership_data);

            // Create community settings
            $settings_data = [
                'community_id' => $community_id,
                'allow_member_events' => !empty($data['allow_member_events']) ? 1 : 0,
                'require_event_approval' => !empty($data['require_event_approval']) ? 1 : 0,
                'allow_discussions' => !empty($data['allow_discussions']) ? 1 : 0,
                'auto_approve_members' => !empty($data['auto_approve_members']) ? 1 : 0,
                'enable_member_directory' => !empty($data['enable_member_directory']) ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('community_settings', $settings_data);

            $this->db->commit();
            return $community_id;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('Community creation failed', ['error' => $e->getMessage(), 'data' => $data]);
            return null;
        }
    }

    /**
     * Generate unique slug for community
     */
    private function generate_slug(string $name): string {
        $base_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $slug = $base_slug;
        $counter = 1;

        while ($this->db->selectOne('communities', ['slug' => $slug])) {
            $slug = $base_slug . '-' . $counter;
            $counter++;

            if ($counter > 1000) {
                $slug = $base_slug . '-' . uniqid();
                break;
            }
        }

        return $slug;
    }

    /**
     * Get community by ID or slug
     */
    public function get_community($identifier): ?object {
        $field = is_numeric($identifier) ? 'id' : 'slug';

        $sql = "
            SELECT c.*, cs.*, u.display_name as creator_name, up.avatar_url as creator_avatar,
                   COUNT(cm.id) as member_count,
                   COUNT(CASE WHEN e.event_date >= NOW() THEN 1 END) as upcoming_events_count
            FROM " . Database::table('communities') . " c
            LEFT JOIN " . Database::table('community_settings') . " cs ON c.id = cs.community_id
            LEFT JOIN " . Database::table('users') . " u ON c.created_by = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            LEFT JOIN " . Database::table('community_members') . " cm ON c.id = cm.community_id AND cm.status = 'active'
            LEFT JOIN " . Database::table('events') . " e ON c.id = e.community_id AND e.event_status = 'active'
            WHERE c.{$field} = :identifier AND c.status = 'active'
            GROUP BY c.id
            LIMIT 1
        ";

        $stmt = $this->db->query($sql, ['identifier' => $identifier]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get communities by criteria
     */
    public function get_communities(array $criteria = [], int $limit = 20, int $offset = 0): array {
        $where_conditions = ['c.status = :status'];
        $params = ['status' => 'active'];

        if (!empty($criteria['created_by'])) {
            $where_conditions[] = 'c.created_by = :created_by';
            $params['created_by'] = (int) $criteria['created_by'];
        }

        if (!empty($criteria['privacy_level'])) {
            $where_conditions[] = 'c.privacy_level = :privacy_level';
            $params['privacy_level'] = $criteria['privacy_level'];
        }

        if (!empty($criteria['community_type'])) {
            $where_conditions[] = 'c.community_type = :community_type';
            $params['community_type'] = $criteria['community_type'];
        }

        if (!empty($criteria['location'])) {
            $where_conditions[] = 'c.location LIKE :location';
            $params['location'] = '%' . $criteria['location'] . '%';
        }

        if (!empty($criteria['search'])) {
            $where_conditions[] = '(c.name LIKE :search OR c.description LIKE :search)';
            $params['search'] = '%' . $criteria['search'] . '%';
        }

        if (!empty($criteria['user_id'])) {
            $where_conditions[] = 'EXISTS (
                SELECT 1 FROM ' . Database::table('community_members') . ' cm
                WHERE cm.community_id = c.id AND cm.user_id = :user_id AND cm.status = "active"
            )';
            $params['user_id'] = (int) $criteria['user_id'];
        }

        $sql = "
            SELECT c.*, u.display_name as creator_name,
                   COUNT(cm.id) as member_count
            FROM " . Database::table('communities') . " c
            LEFT JOIN " . Database::table('users') . " u ON c.created_by = u.id
            LEFT JOIN " . Database::table('community_members') . " cm ON c.id = cm.community_id AND cm.status = 'active'
            WHERE " . implode(' AND ', $where_conditions) . "
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Update community
     */
    public function update_community(int $community_id, array $data): bool {
        $this->db->beginTransaction();

        try {
            $community_updates = [
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $community_fields = [
                'name', 'description', 'community_type', 'privacy_level',
                'member_limit', 'location', 'website', 'social_links',
                'rules', 'welcome_message', 'tags'
            ];

            foreach ($community_fields as $field) {
                if (isset($data[$field])) {
                    if (in_array($field, ['description', 'rules', 'welcome_message'])) {
                        $community_updates[$field] = vt_sanitize_textarea($data[$field]);
                    } elseif ($field === 'website') {
                        $community_updates[$field] = filter_var($data[$field], FILTER_VALIDATE_URL) ?: null;
                    } elseif (in_array($field, ['social_links', 'tags'])) {
                        $community_updates[$field] = $data[$field];
                    } else {
                        $community_updates[$field] = vt_sanitize_text($data[$field]);
                    }
                }
            }

            // Update slug if name changed
            if (isset($data['name']) && $data['name']) {
                $community_updates['slug'] = $this->generate_slug($data['name']);
            }

            $this->db->update('communities', $community_updates, ['id' => $community_id]);

            // Update settings if provided
            $settings_updates = [];
            $settings_fields = [
                'allow_member_events', 'require_event_approval', 'allow_discussions',
                'auto_approve_members', 'enable_member_directory'
            ];

            foreach ($settings_fields as $field) {
                if (isset($data[$field])) {
                    $settings_updates[$field] = !empty($data[$field]) ? 1 : 0;
                }
            }

            if (!empty($settings_updates)) {
                $settings_updates['updated_at'] = date('Y-m-d H:i:s');
                $this->db->update('community_settings', $settings_updates, ['community_id' => $community_id]);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('Community update failed', ['community_id' => $community_id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Add member to community
     */
    public function add_member(int $community_id, int $user_id, string $role = 'member'): bool {
        // Check if already a member
        $existing = $this->db->selectOne('community_members', [
            'community_id' => $community_id,
            'user_id' => $user_id
        ]);

        if ($existing) {
            if ($existing->status === 'active') {
                return true; // Already active member
            }
            // Reactivate existing membership
            return $this->db->update('community_members', [
                'status' => 'active',
                'role' => $role,
                'joined_at' => date('Y-m-d H:i:s')
            ], ['id' => $existing->id]) > 0;
        }

        $membership_data = [
            'community_id' => $community_id,
            'user_id' => $user_id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => date('Y-m-d H:i:s')
        ];

        return $this->db->insert('community_members', $membership_data) > 0;
    }

    /**
     * Remove member from community
     */
    public function remove_member(int $community_id, int $user_id): bool {
        return $this->db->update('community_members', [
            'status' => 'removed',
            'left_at' => date('Y-m-d H:i:s')
        ], [
            'community_id' => $community_id,
            'user_id' => $user_id
        ]) > 0;
    }

    /**
     * Update member role
     */
    public function update_member_role(int $community_id, int $user_id, string $role): bool {
        return $this->db->update('community_members', [
            'role' => $role,
            'updated_at' => date('Y-m-d H:i:s')
        ], [
            'community_id' => $community_id,
            'user_id' => $user_id,
            'status' => 'active'
        ]) > 0;
    }

    /**
     * Get community members
     */
    public function get_members(int $community_id, string $role = '', int $limit = 50): array {
        $where_conditions = ['cm.community_id = :community_id', 'cm.status = :status'];
        $params = ['community_id' => $community_id, 'status' => 'active'];

        if ($role) {
            $where_conditions[] = 'cm.role = :role';
            $params['role'] = $role;
        }

        $sql = "
            SELECT cm.*, u.username, u.display_name, u.email, up.avatar_url, up.location
            FROM " . Database::table('community_members') . " cm
            INNER JOIN " . Database::table('users') . " u ON cm.user_id = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY cm.joined_at DESC
            LIMIT :limit
        ";

        $params['limit'] = $limit;

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get user's membership in community
     */
    public function get_membership(int $community_id, int $user_id): ?object {
        return $this->db->selectOne('community_members', [
            'community_id' => $community_id,
            'user_id' => $user_id,
            'status' => 'active'
        ]);
    }

    /**
     * Check if user can perform action in community
     */
    public function can_user_perform_action(int $user_id, int $community_id, string $action): bool {
        $membership = $this->get_membership($community_id, $user_id);

        if (!$membership) {
            return false;
        }

        switch ($action) {
            case 'view':
                return true; // Any active member can view

            case 'create_event':
                $community = $this->get_community($community_id);
                return $community && $community->allow_member_events;

            case 'moderate':
            case 'manage_members':
                return in_array($membership->role, ['admin', 'moderator']);

            case 'admin':
            case 'delete':
            case 'change_settings':
                return $membership->role === 'admin';

            default:
                return false;
        }
    }

    /**
     * Get community events
     */
    public function get_community_events(int $community_id, bool $upcoming_only = true, int $limit = 20): array {
        $where_conditions = ['e.community_id = :community_id', 'e.event_status = :status'];
        $params = ['community_id' => $community_id, 'status' => 'active'];

        if ($upcoming_only) {
            $where_conditions[] = 'e.event_date >= NOW()';
        }

        $sql = "
            SELECT e.*, u.display_name as host_name, up.avatar_url as host_avatar,
                   COUNT(CASE WHEN r.status = 'attending' THEN 1 END) as attending_count
            FROM " . Database::table('events') . " e
            LEFT JOIN " . Database::table('users') . " u ON e.host_id = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            LEFT JOIN " . Database::table('event_rsvps') . " r ON e.id = r.event_id
            WHERE " . implode(' AND ', $where_conditions) . "
            GROUP BY e.id
            ORDER BY e.event_date ASC
            LIMIT :limit
        ";

        $params['limit'] = $limit;

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get community statistics
     */
    public function get_community_stats(int $community_id): array {
        $sql = "
            SELECT
                COUNT(DISTINCT cm.user_id) as member_count,
                COUNT(DISTINCT CASE WHEN e.event_date >= NOW() THEN e.id END) as upcoming_events,
                COUNT(DISTINCT CASE WHEN e.event_date < NOW() THEN e.id END) as past_events,
                COUNT(DISTINCT conv.id) as total_conversations
            FROM " . Database::table('communities') . " c
            LEFT JOIN " . Database::table('community_members') . " cm ON c.id = cm.community_id AND cm.status = 'active'
            LEFT JOIN " . Database::table('events') . " e ON c.id = e.community_id AND e.event_status = 'active'
            LEFT JOIN " . Database::table('conversations') . " conv ON c.id = conv.community_id AND conv.status = 'active'
            WHERE c.id = :community_id
            GROUP BY c.id
        ";

        $stmt = $this->db->query($sql, ['community_id' => $community_id]);
        $stats = $stmt->fetch();

        return [
            'member_count' => (int) ($stats->member_count ?? 0),
            'upcoming_events' => (int) ($stats->upcoming_events ?? 0),
            'past_events' => (int) ($stats->past_events ?? 0),
            'total_events' => ((int) ($stats->upcoming_events ?? 0)) + ((int) ($stats->past_events ?? 0)),
            'total_conversations' => (int) ($stats->total_conversations ?? 0)
        ];
    }

    /**
     * Search communities
     */
    public function search_communities(string $query, array $filters = [], int $limit = 20): array {
        $where_conditions = ['c.status = :status', 'c.privacy_level = :privacy'];
        $params = [
            'status' => 'active',
            'privacy' => 'public'
        ];

        if ($query) {
            $where_conditions[] = '(c.name LIKE :query OR c.description LIKE :query OR c.location LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if (!empty($filters['community_type'])) {
            $where_conditions[] = 'c.community_type = :community_type';
            $params['community_type'] = $filters['community_type'];
        }

        if (!empty($filters['location'])) {
            $where_conditions[] = 'c.location LIKE :location';
            $params['location'] = '%' . $filters['location'] . '%';
        }

        $sql = "
            SELECT c.*, u.display_name as creator_name,
                   COUNT(cm.id) as member_count
            FROM " . Database::table('communities') . " c
            LEFT JOIN " . Database::table('users') . " u ON c.created_by = u.id
            LEFT JOIN " . Database::table('community_members') . " cm ON c.id = cm.community_id AND cm.status = 'active'
            WHERE " . implode(' AND ', $where_conditions) . "
            GROUP BY c.id
            ORDER BY member_count DESC, c.created_at DESC
            LIMIT :limit
        ";

        $params['limit'] = $limit;

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Delete community (set status to deleted)
     */
    public function delete_community(int $community_id): bool {
        return $this->db->update('communities', [
            'status' => 'deleted',
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $community_id]) > 0;
    }
}