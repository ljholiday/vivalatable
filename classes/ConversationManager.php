<?php
/**
 * Conversation Manager Class
 * Handles threaded discussions and conversations
 */

class ConversationManager {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create new conversation
     */
    public function create_conversation(array $data): ?int {
        $required_fields = ['title', 'created_by'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return null;
            }
        }

        $this->db->beginTransaction();

        try {
            // Generate unique slug
            $slug = $this->generate_slug($data['title']);

            $conversation_data = [
                'title' => vt_sanitize_text($data['title']),
                'slug' => $slug,
                'content' => vt_sanitize_textarea($data['content'] ?? ''),
                'conversation_type' => $data['conversation_type'] ?? 'discussion',
                'privacy_level' => $data['privacy_level'] ?? 'public',
                'created_by' => (int) $data['created_by'],
                'community_id' => !empty($data['community_id']) ? (int) $data['community_id'] : null,
                'event_id' => !empty($data['event_id']) ? (int) $data['event_id'] : null,
                'tags' => $data['tags'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $conversation_id = $this->db->insert('conversations', $conversation_data);

            if (!$conversation_id) {
                throw new Exception('Failed to create conversation');
            }

            // Add creator as participant
            $participant_data = [
                'conversation_id' => $conversation_id,
                'user_id' => (int) $data['created_by'],
                'role' => 'creator',
                'status' => 'active',
                'joined_at' => date('Y-m-d H:i:s'),
                'last_read_at' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('conversation_participants', $participant_data);

            $this->db->commit();
            return $conversation_id;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('Conversation creation failed', ['error' => $e->getMessage(), 'data' => $data]);
            return null;
        }
    }

    /**
     * Generate unique slug for conversation
     */
    private function generate_slug(string $title): string {
        $base_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $base_slug = substr($base_slug, 0, 50); // Limit length
        $slug = $base_slug;
        $counter = 1;

        while ($this->db->selectOne('conversations', ['slug' => $slug])) {
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
     * Get conversation by ID or slug
     */
    public function get_conversation($identifier): ?object {
        $field = is_numeric($identifier) ? 'id' : 'slug';

        $sql = "
            SELECT c.*, u.display_name as creator_name, up.avatar_url as creator_avatar,
                   com.name as community_name, com.slug as community_slug,
                   e.title as event_title, e.slug as event_slug,
                   COUNT(DISTINCT cp.user_id) as participant_count,
                   COUNT(cr.id) as reply_count,
                   MAX(cr.created_at) as last_reply_at
            FROM " . Database::table('conversations') . " c
            LEFT JOIN " . Database::table('users') . " u ON c.created_by = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            LEFT JOIN " . Database::table('communities') . " com ON c.community_id = com.id
            LEFT JOIN " . Database::table('events') . " e ON c.event_id = e.id
            LEFT JOIN " . Database::table('conversation_participants') . " cp ON c.id = cp.conversation_id AND cp.status = 'active'
            LEFT JOIN " . Database::table('conversation_replies') . " cr ON c.id = cr.conversation_id AND cr.status = 'active'
            WHERE c.{$field} = :identifier AND c.status = 'active'
            GROUP BY c.id
            LIMIT 1
        ";

        $stmt = $this->db->query($sql, ['identifier' => $identifier]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get conversations by criteria
     */
    public function get_conversations(array $criteria = [], int $limit = 20, int $offset = 0): array {
        $where_conditions = ['c.status = :status'];
        $params = ['status' => 'active'];

        if (!empty($criteria['created_by'])) {
            $where_conditions[] = 'c.created_by = :created_by';
            $params['created_by'] = (int) $criteria['created_by'];
        }

        if (!empty($criteria['community_id'])) {
            $where_conditions[] = 'c.community_id = :community_id';
            $params['community_id'] = (int) $criteria['community_id'];
        }

        if (!empty($criteria['event_id'])) {
            $where_conditions[] = 'c.event_id = :event_id';
            $params['event_id'] = (int) $criteria['event_id'];
        }

        if (!empty($criteria['conversation_type'])) {
            $where_conditions[] = 'c.conversation_type = :conversation_type';
            $params['conversation_type'] = $criteria['conversation_type'];
        }

        if (!empty($criteria['privacy_level'])) {
            $where_conditions[] = 'c.privacy_level = :privacy_level';
            $params['privacy_level'] = $criteria['privacy_level'];
        }

        if (!empty($criteria['search'])) {
            $where_conditions[] = '(c.title LIKE :search OR c.content LIKE :search)';
            $params['search'] = '%' . $criteria['search'] . '%';
        }

        if (!empty($criteria['participant_id'])) {
            $where_conditions[] = 'EXISTS (
                SELECT 1 FROM ' . Database::table('conversation_participants') . ' cp
                WHERE cp.conversation_id = c.id AND cp.user_id = :participant_id AND cp.status = "active"
            )';
            $params['participant_id'] = (int) $criteria['participant_id'];
        }

        $order_by = 'c.created_at DESC';
        if (!empty($criteria['order_by'])) {
            switch ($criteria['order_by']) {
                case 'recent_activity':
                    $order_by = 'COALESCE(MAX(cr.created_at), c.created_at) DESC';
                    break;
                case 'popular':
                    $order_by = 'COUNT(cr.id) DESC, c.created_at DESC';
                    break;
                case 'title':
                    $order_by = 'c.title ASC';
                    break;
            }
        }

        $sql = "
            SELECT c.*, u.display_name as creator_name, up.avatar_url as creator_avatar,
                   com.name as community_name,
                   COUNT(DISTINCT cp.user_id) as participant_count,
                   COUNT(cr.id) as reply_count,
                   MAX(cr.created_at) as last_reply_at
            FROM " . Database::table('conversations') . " c
            LEFT JOIN " . Database::table('users') . " u ON c.created_by = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            LEFT JOIN " . Database::table('communities') . " com ON c.community_id = com.id
            LEFT JOIN " . Database::table('conversation_participants') . " cp ON c.id = cp.conversation_id AND cp.status = 'active'
            LEFT JOIN " . Database::table('conversation_replies') . " cr ON c.id = cr.conversation_id AND cr.status = 'active'
            WHERE " . implode(' AND ', $where_conditions) . "
            GROUP BY c.id
            ORDER BY {$order_by}
            LIMIT :limit OFFSET :offset
        ";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Add reply to conversation
     */
    public function add_reply(int $conversation_id, array $reply_data): ?int {
        $required_fields = ['content', 'created_by'];
        foreach ($required_fields as $field) {
            if (empty($reply_data[$field])) {
                return null;
            }
        }

        $this->db->beginTransaction();

        try {
            $reply_record = [
                'conversation_id' => $conversation_id,
                'parent_id' => !empty($reply_data['parent_id']) ? (int) $reply_data['parent_id'] : null,
                'content' => vt_sanitize_textarea($reply_data['content']),
                'created_by' => (int) $reply_data['created_by'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $reply_id = $this->db->insert('conversation_replies', $reply_record);

            if (!$reply_id) {
                throw new Exception('Failed to create reply');
            }

            // Update conversation last activity
            $this->db->update('conversations', [
                'last_activity_at' => date('Y-m-d H:i:s'),
                'reply_count' => $this->db->query(
                    'SELECT COUNT(*) as count FROM ' . Database::table('conversation_replies') . ' WHERE conversation_id = ? AND status = "active"',
                    [$conversation_id]
                )->fetch()->count
            ], ['id' => $conversation_id]);

            // Add user as participant if not already
            $this->add_participant($conversation_id, (int) $reply_data['created_by']);

            // Update participant's last read time
            $this->update_last_read($conversation_id, (int) $reply_data['created_by']);

            $this->db->commit();
            return $reply_id;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('Reply creation failed', ['conversation_id' => $conversation_id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get conversation replies
     */
    public function get_replies(int $conversation_id, int $limit = 50, int $offset = 0): array {
        $sql = "
            SELECT cr.*, u.display_name, u.username, up.avatar_url,
                   COUNT(child.id) as child_count
            FROM " . Database::table('conversation_replies') . " cr
            INNER JOIN " . Database::table('users') . " u ON cr.created_by = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            LEFT JOIN " . Database::table('conversation_replies') . " child ON cr.id = child.parent_id AND child.status = 'active'
            WHERE cr.conversation_id = :conversation_id AND cr.status = 'active'
            GROUP BY cr.id
            ORDER BY cr.created_at ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->query($sql, [
            'conversation_id' => $conversation_id,
            'limit' => $limit,
            'offset' => $offset
        ]);

        return $this->build_reply_tree($stmt->fetchAll());
    }

    /**
     * Build threaded reply tree
     */
    private function build_reply_tree(array $replies): array {
        $reply_map = [];
        $root_replies = [];

        // First pass: create map and identify root replies
        foreach ($replies as $reply) {
            $reply_map[$reply->id] = $reply;
            $reply->children = [];

            if ($reply->parent_id === null) {
                $root_replies[] = $reply;
            }
        }

        // Second pass: build tree structure
        foreach ($replies as $reply) {
            if ($reply->parent_id !== null && isset($reply_map[$reply->parent_id])) {
                $reply_map[$reply->parent_id]->children[] = $reply;
            }
        }

        return $root_replies;
    }

    /**
     * Add participant to conversation
     */
    public function add_participant(int $conversation_id, int $user_id, string $role = 'participant'): bool {
        // Check if already a participant
        $existing = $this->db->selectOne('conversation_participants', [
            'conversation_id' => $conversation_id,
            'user_id' => $user_id
        ]);

        if ($existing) {
            if ($existing->status === 'active') {
                return true; // Already active participant
            }
            // Reactivate existing participation
            return $this->db->update('conversation_participants', [
                'status' => 'active',
                'role' => $role,
                'joined_at' => date('Y-m-d H:i:s')
            ], ['id' => $existing->id]) > 0;
        }

        $participant_data = [
            'conversation_id' => $conversation_id,
            'user_id' => $user_id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => date('Y-m-d H:i:s'),
            'last_read_at' => date('Y-m-d H:i:s')
        ];

        return $this->db->insert('conversation_participants', $participant_data) > 0;
    }

    /**
     * Remove participant from conversation
     */
    public function remove_participant(int $conversation_id, int $user_id): bool {
        return $this->db->update('conversation_participants', [
            'status' => 'removed',
            'left_at' => date('Y-m-d H:i:s')
        ], [
            'conversation_id' => $conversation_id,
            'user_id' => $user_id
        ]) > 0;
    }

    /**
     * Get conversation participants
     */
    public function get_participants(int $conversation_id): array {
        $sql = "
            SELECT cp.*, u.username, u.display_name, up.avatar_url, up.location
            FROM " . Database::table('conversation_participants') . " cp
            INNER JOIN " . Database::table('users') . " u ON cp.user_id = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            WHERE cp.conversation_id = :conversation_id AND cp.status = 'active'
            ORDER BY cp.joined_at ASC
        ";

        $stmt = $this->db->query($sql, ['conversation_id' => $conversation_id]);
        return $stmt->fetchAll();
    }

    /**
     * Update user's last read time
     */
    public function update_last_read(int $conversation_id, int $user_id): bool {
        return $this->db->update('conversation_participants', [
            'last_read_at' => date('Y-m-d H:i:s')
        ], [
            'conversation_id' => $conversation_id,
            'user_id' => $user_id,
            'status' => 'active'
        ]) > 0;
    }

    /**
     * Get unread conversations for user
     */
    public function get_unread_conversations(int $user_id, int $limit = 20): array {
        $sql = "
            SELECT c.*, u.display_name as creator_name,
                   COUNT(cr.id) as unread_count,
                   MAX(cr.created_at) as last_reply_at
            FROM " . Database::table('conversations') . " c
            INNER JOIN " . Database::table('conversation_participants') . " cp ON c.id = cp.conversation_id
            LEFT JOIN " . Database::table('users') . " u ON c.created_by = u.id
            LEFT JOIN " . Database::table('conversation_replies') . " cr ON c.id = cr.conversation_id
                AND cr.created_at > cp.last_read_at AND cr.status = 'active'
            WHERE cp.user_id = :user_id AND cp.status = 'active' AND c.status = 'active'
                AND (cr.id IS NOT NULL OR c.created_at > cp.last_read_at)
            GROUP BY c.id
            HAVING unread_count > 0
            ORDER BY last_reply_at DESC, c.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->query($sql, ['user_id' => $user_id, 'limit' => $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Search conversations
     */
    public function search_conversations(string $query, array $filters = [], int $limit = 20): array {
        $where_conditions = ['c.status = :status', 'c.privacy_level = :privacy'];
        $params = [
            'status' => 'active',
            'privacy' => 'public'
        ];

        if ($query) {
            $where_conditions[] = '(c.title LIKE :query OR c.content LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if (!empty($filters['conversation_type'])) {
            $where_conditions[] = 'c.conversation_type = :conversation_type';
            $params['conversation_type'] = $filters['conversation_type'];
        }

        if (!empty($filters['community_id'])) {
            $where_conditions[] = 'c.community_id = :community_id';
            $params['community_id'] = (int) $filters['community_id'];
        }

        if (!empty($filters['has_replies'])) {
            $where_conditions[] = 'c.reply_count > 0';
        }

        $sql = "
            SELECT c.*, u.display_name as creator_name, up.avatar_url as creator_avatar,
                   com.name as community_name,
                   c.reply_count
            FROM " . Database::table('conversations') . " c
            LEFT JOIN " . Database::table('users') . " u ON c.created_by = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            LEFT JOIN " . Database::table('communities') . " com ON c.community_id = com.id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY c.reply_count DESC, c.created_at DESC
            LIMIT :limit
        ";

        $params['limit'] = $limit;

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Update conversation
     */
    public function update_conversation(int $conversation_id, array $data): bool {
        $conversation_updates = [
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $conversation_fields = ['title', 'content', 'privacy_level', 'tags'];

        foreach ($conversation_fields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['content'])) {
                    $conversation_updates[$field] = vt_sanitize_textarea($data[$field]);
                } elseif ($field === 'tags') {
                    $conversation_updates[$field] = $data[$field];
                } else {
                    $conversation_updates[$field] = vt_sanitize_text($data[$field]);
                }
            }
        }

        return $this->db->update('conversations', $conversation_updates, ['id' => $conversation_id]) > 0;
    }

    /**
     * Delete conversation (set status to deleted)
     */
    public function delete_conversation(int $conversation_id): bool {
        return $this->db->update('conversations', [
            'status' => 'deleted',
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $conversation_id]) > 0;
    }

    /**
     * Delete reply (set status to deleted)
     */
    public function delete_reply(int $reply_id): bool {
        return $this->db->update('conversation_replies', [
            'status' => 'deleted',
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $reply_id]) > 0;
    }

    /**
     * Get conversation statistics
     */
    public function get_conversation_stats(int $conversation_id): array {
        $sql = "
            SELECT
                COUNT(DISTINCT cp.user_id) as participant_count,
                COUNT(cr.id) as reply_count,
                MIN(cr.created_at) as first_reply_at,
                MAX(cr.created_at) as last_reply_at
            FROM " . Database::table('conversations') . " c
            LEFT JOIN " . Database::table('conversation_participants') . " cp ON c.id = cp.conversation_id AND cp.status = 'active'
            LEFT JOIN " . Database::table('conversation_replies') . " cr ON c.id = cr.conversation_id AND cr.status = 'active'
            WHERE c.id = :conversation_id
            GROUP BY c.id
        ";

        $stmt = $this->db->query($sql, ['conversation_id' => $conversation_id]);
        $stats = $stmt->fetch();

        return [
            'participant_count' => (int) ($stats->participant_count ?? 0),
            'reply_count' => (int) ($stats->reply_count ?? 0),
            'first_reply_at' => $stats->first_reply_at,
            'last_reply_at' => $stats->last_reply_at
        ];
    }
}