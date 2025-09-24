<?php
/**
 * Event Manager Class
 * Handles event creation, management, and RSVP functionality
 */

class EventManager {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create new event
     */
    public function create_event(array $data): ?int {
        $required_fields = ['title', 'event_date', 'host_id'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return null;
            }
        }

        $this->db->beginTransaction();

        try {
            $event_data = [
                'title' => vt_sanitize_text($data['title']),
                'description' => vt_sanitize_textarea($data['description'] ?? ''),
                'event_date' => $data['event_date'],
                'location' => vt_sanitize_text($data['location'] ?? ''),
                'max_guests' => (int) ($data['max_guests'] ?? 0),
                'host_id' => (int) $data['host_id'],
                'community_id' => !empty($data['community_id']) ? (int) $data['community_id'] : null,
                'event_type' => $data['event_type'] ?? 'social',
                'privacy_level' => $data['privacy_level'] ?? 'public',
                'guest_policy' => $data['guest_policy'] ?? 'open',
                'rsvp_deadline' => $data['rsvp_deadline'] ?? null,
                'menu' => $data['menu'] ?? null,
                'activities' => $data['activities'] ?? null,
                'special_instructions' => vt_sanitize_textarea($data['special_instructions'] ?? ''),
                'dietary_accommodations' => $data['dietary_accommodations'] ?? null,
                'accessibility_notes' => vt_sanitize_textarea($data['accessibility_notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $event_id = $this->db->insert('events', $event_data);

            if (!$event_id) {
                throw new Exception('Failed to create event');
            }

            // Create event settings
            $settings_data = [
                'event_id' => $event_id,
                'allow_guest_rsvp' => !empty($data['allow_guest_rsvp']) ? 1 : 0,
                'require_approval' => !empty($data['require_approval']) ? 1 : 0,
                'send_reminders' => !empty($data['send_reminders']) ? 1 : 0,
                'collect_dietary_info' => !empty($data['collect_dietary_info']) ? 1 : 0,
                'collect_accessibility_info' => !empty($data['collect_accessibility_info']) ? 1 : 0,
                'enable_conversations' => !empty($data['enable_conversations']) ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('event_settings', $settings_data);

            $this->db->commit();
            return $event_id;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('Event creation failed', ['error' => $e->getMessage(), 'data' => $data]);
            return null;
        }
    }

    /**
     * Get event by ID with all related data
     */
    public function get_event(int $event_id): ?object {
        $sql = "
            SELECT e.*, es.*, u.username, u.display_name as host_name, up.avatar_url as host_avatar,
                   c.name as community_name, c.slug as community_slug,
                   COUNT(CASE WHEN r.status = 'attending' THEN 1 END) as attending_count,
                   COUNT(CASE WHEN r.status = 'maybe' THEN 1 END) as maybe_count,
                   COUNT(CASE WHEN r.status = 'declined' THEN 1 END) as declined_count
            FROM " . Database::table('events') . " e
            LEFT JOIN " . Database::table('event_settings') . " es ON e.id = es.event_id
            LEFT JOIN " . Database::table('users') . " u ON e.host_id = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            LEFT JOIN " . Database::table('communities') . " c ON e.community_id = c.id
            LEFT JOIN " . Database::table('event_rsvps') . " r ON e.id = r.event_id
            WHERE e.id = :event_id AND e.event_status = 'active'
            GROUP BY e.id
            LIMIT 1
        ";

        $stmt = $this->db->query($sql, ['event_id' => $event_id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get events by criteria
     */
    public function get_events(array $criteria = [], int $limit = 20, int $offset = 0): array {
        $where_conditions = ['e.event_status = :status'];
        $params = ['status' => 'active'];

        if (!empty($criteria['host_id'])) {
            $where_conditions[] = 'e.host_id = :host_id';
            $params['host_id'] = (int) $criteria['host_id'];
        }

        if (!empty($criteria['community_id'])) {
            $where_conditions[] = 'e.community_id = :community_id';
            $params['community_id'] = (int) $criteria['community_id'];
        }

        if (!empty($criteria['upcoming'])) {
            $where_conditions[] = 'e.event_date >= NOW()';
        }

        if (!empty($criteria['past'])) {
            $where_conditions[] = 'e.event_date < NOW()';
        }

        if (!empty($criteria['privacy_level'])) {
            $where_conditions[] = 'e.privacy_level = :privacy_level';
            $params['privacy_level'] = $criteria['privacy_level'];
        }

        if (!empty($criteria['search'])) {
            $where_conditions[] = '(e.title LIKE :search OR e.description LIKE :search OR e.location LIKE :search)';
            $params['search'] = '%' . $criteria['search'] . '%';
        }

        $sql = "
            SELECT e.*, u.display_name as host_name, up.avatar_url as host_avatar,
                   c.name as community_name,
                   COUNT(CASE WHEN r.status = 'attending' THEN 1 END) as attending_count
            FROM " . Database::table('events') . " e
            LEFT JOIN " . Database::table('users') . " u ON e.host_id = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            LEFT JOIN " . Database::table('communities') . " c ON e.community_id = c.id
            LEFT JOIN " . Database::table('event_rsvps') . " r ON e.id = r.event_id
            WHERE " . implode(' AND ', $where_conditions) . "
            GROUP BY e.id
            ORDER BY e.event_date ASC
            LIMIT :limit OFFSET :offset
        ";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Update event
     */
    public function update_event(int $event_id, array $data): bool {
        $this->db->beginTransaction();

        try {
            $event_updates = [
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $event_fields = [
                'title', 'description', 'event_date', 'location', 'max_guests',
                'event_type', 'privacy_level', 'guest_policy', 'rsvp_deadline',
                'menu', 'activities', 'special_instructions', 'dietary_accommodations',
                'accessibility_notes'
            ];

            foreach ($event_fields as $field) {
                if (isset($data[$field])) {
                    if (in_array($field, ['description', 'special_instructions', 'accessibility_notes'])) {
                        $event_updates[$field] = vt_sanitize_textarea($data[$field]);
                    } elseif (in_array($field, ['menu', 'activities', 'dietary_accommodations'])) {
                        $event_updates[$field] = $data[$field];
                    } else {
                        $event_updates[$field] = vt_sanitize_text($data[$field]);
                    }
                }
            }

            $this->db->update('events', $event_updates, ['id' => $event_id]);

            // Update settings if provided
            $settings_updates = [];
            $settings_fields = [
                'allow_guest_rsvp', 'require_approval', 'send_reminders',
                'collect_dietary_info', 'collect_accessibility_info', 'enable_conversations'
            ];

            foreach ($settings_fields as $field) {
                if (isset($data[$field])) {
                    $settings_updates[$field] = !empty($data[$field]) ? 1 : 0;
                }
            }

            if (!empty($settings_updates)) {
                $settings_updates['updated_at'] = date('Y-m-d H:i:s');
                $this->db->update('event_settings', $settings_updates, ['event_id' => $event_id]);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('Event update failed', ['event_id' => $event_id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete/Cancel event
     */
    public function delete_event(int $event_id): bool {
        return $this->db->update('events', [
            'event_status' => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $event_id]) > 0;
    }

    /**
     * Create RSVP (user or guest)
     */
    public function create_rsvp(int $event_id, array $rsvp_data): bool {
        $required_fields = ['status'];
        foreach ($required_fields as $field) {
            if (empty($rsvp_data[$field])) {
                return false;
            }
        }

        $this->db->beginTransaction();

        try {
            // Check if RSVP already exists
            $existing_conditions = ['event_id' => $event_id];

            if (!empty($rsvp_data['user_id'])) {
                $existing_conditions['user_id'] = (int) $rsvp_data['user_id'];
            } else {
                $existing_conditions['email'] = $rsvp_data['email'];
            }

            $existing_rsvp = $this->db->selectOne('event_rsvps', $existing_conditions);

            $rsvp_record = [
                'event_id' => $event_id,
                'user_id' => !empty($rsvp_data['user_id']) ? (int) $rsvp_data['user_id'] : null,
                'guest_token' => $rsvp_data['guest_token'] ?? null,
                'email' => $rsvp_data['email'] ?? null,
                'guest_name' => vt_sanitize_text($rsvp_data['guest_name'] ?? ''),
                'status' => $rsvp_data['status'],
                'plus_one' => (int) ($rsvp_data['plus_one'] ?? 0),
                'dietary_restrictions' => vt_sanitize_textarea($rsvp_data['dietary_restrictions'] ?? ''),
                'accessibility_needs' => vt_sanitize_textarea($rsvp_data['accessibility_needs'] ?? ''),
                'notes' => vt_sanitize_textarea($rsvp_data['notes'] ?? ''),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($existing_rsvp) {
                $this->db->update('event_rsvps', $rsvp_record, ['id' => $existing_rsvp->id]);
            } else {
                $rsvp_record['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('event_rsvps', $rsvp_record);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('RSVP creation failed', ['event_id' => $event_id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get event RSVPs
     */
    public function get_event_rsvps(int $event_id, string $status = ''): array {
        $where_conditions = ['r.event_id = :event_id'];
        $params = ['event_id' => $event_id];

        if ($status) {
            $where_conditions[] = 'r.status = :status';
            $params['status'] = $status;
        }

        $sql = "
            SELECT r.*, u.display_name, u.username, up.avatar_url
            FROM " . Database::table('event_rsvps') . " r
            LEFT JOIN " . Database::table('users') . " u ON r.user_id = u.id
            LEFT JOIN " . Database::table('user_profiles') . " up ON u.id = up.user_id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY r.created_at ASC
        ";

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get user's RSVP for an event
     */
    public function get_user_rsvp(int $event_id, int $user_id = 0, string $email = ''): ?object {
        $conditions = ['event_id' => $event_id];

        if ($user_id > 0) {
            $conditions['user_id'] = $user_id;
        } elseif ($email) {
            $conditions['email'] = $email;
        } else {
            return null;
        }

        return $this->db->selectOne('event_rsvps', $conditions);
    }

    /**
     * Generate guest invitation token
     */
    public function generate_guest_token(int $event_id, string $email, string $name = ''): string {
        $token = Database::generateToken(32);

        $invitation_data = [
            'event_id' => $event_id,
            'email' => $email,
            'guest_name' => vt_sanitize_text($name),
            'token' => $token,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->db->insert('guest_invitations', $invitation_data);
        return $token;
    }

    /**
     * Verify guest token
     */
    public function verify_guest_token(string $token): ?object {
        $sql = "
            SELECT gi.*, e.title as event_title, e.event_date
            FROM " . Database::table('guest_invitations') . " gi
            INNER JOIN " . Database::table('events') . " e ON gi.event_id = e.id
            WHERE gi.token = :token AND gi.expires_at > NOW()
            LIMIT 1
        ";

        $stmt = $this->db->query($sql, ['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get event statistics
     */
    public function get_event_stats(int $event_id): array {
        $sql = "
            SELECT
                COUNT(CASE WHEN status = 'attending' THEN 1 END) as attending,
                COUNT(CASE WHEN status = 'maybe' THEN 1 END) as maybe,
                COUNT(CASE WHEN status = 'declined' THEN 1 END) as declined,
                SUM(plus_one) as total_plus_ones,
                COUNT(*) as total_responses
            FROM " . Database::table('event_rsvps') . "
            WHERE event_id = :event_id
        ";

        $stmt = $this->db->query($sql, ['event_id' => $event_id]);
        $stats = $stmt->fetch();

        return [
            'attending' => (int) ($stats->attending ?? 0),
            'maybe' => (int) ($stats->maybe ?? 0),
            'declined' => (int) ($stats->declined ?? 0),
            'total_plus_ones' => (int) ($stats->total_plus_ones ?? 0),
            'total_responses' => (int) ($stats->total_responses ?? 0),
            'total_expected' => (int) ($stats->attending ?? 0) + (int) ($stats->total_plus_ones ?? 0)
        ];
    }

    /**
     * Send event invitations
     */
    public function send_invitations(int $event_id, array $invitees, string $message = ''): array {
        $results = [];
        $event = $this->get_event($event_id);

        if (!$event) {
            return ['error' => 'Event not found'];
        }

        foreach ($invitees as $invitee) {
            $email = $invitee['email'];
            $name = $invitee['name'] ?? '';

            try {
                // Generate invitation token
                $token = $this->generate_guest_token($event_id, $email, $name);

                // Send invitation email
                $invitation_url = vt_base_url("/rsvp/{$token}");

                $subject = "You're invited to {$event->title}";
                $email_message = $this->build_invitation_email($event, $invitation_url, $message);

                if (vt_send_email($email, $subject, $email_message)) {
                    $results[] = ['email' => $email, 'status' => 'sent'];
                } else {
                    $results[] = ['email' => $email, 'status' => 'failed', 'error' => 'Email send failed'];
                }

            } catch (Exception $e) {
                $results[] = ['email' => $email, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Build invitation email HTML
     */
    private function build_invitation_email(object $event, string $invitation_url, string $personal_message = ''): string {
        $event_date = vt_format_date($event->event_date, 'F j, Y \a\t g:i A');

        return "
        <html>
        <head><title>You're invited to {$event->title}</title></head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2563eb;'>You're invited to {$event->title}</h1>

                <div style='background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h2 style='margin-top: 0;'>{$event->title}</h2>
                    <p><strong>When:</strong> {$event_date}</p>
                    " . ($event->location ? "<p><strong>Where:</strong> {$event->location}</p>" : "") . "
                    <p><strong>Host:</strong> {$event->host_name}</p>
                </div>

                " . ($event->description ? "<div style='margin: 20px 0;'><h3>About this event</h3><p>{$event->description}</p></div>" : "") . "

                " . ($personal_message ? "<div style='margin: 20px 0;'><h3>Personal message</h3><p style='font-style: italic;'>{$personal_message}</p></div>" : "") . "

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$invitation_url}' style='background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>RSVP Now</a>
                </div>

                <p style='color: #666; font-size: 14px;'>
                    You can RSVP without creating an account. Just click the link above to respond.
                </p>

                <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                    This invitation was sent via VivalaTable. If you don't want to receive event invitations,
                    please contact the event host directly.
                </p>
            </div>
        </body>
        </html>";
    }
}