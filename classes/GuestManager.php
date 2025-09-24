<?php
/**
 * Guest Manager Class
 * Handles guest invitations, RSVPs, and user conversions
 */

class GuestManager {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create guest invitation
     */
    public function create_invitation(int $event_id, string $email, string $name = '', array $options = []): ?string {
        // Check if invitation already exists
        $existing = $this->db->selectOne('guest_invitations', [
            'event_id' => $event_id,
            'email' => $email
        ]);

        if ($existing && $existing->expires_at > date('Y-m-d H:i:s')) {
            return $existing->token; // Return existing valid token
        }

        $token = Database::generateToken(32);

        $invitation_data = [
            'event_id' => $event_id,
            'email' => $email,
            'guest_name' => vt_sanitize_text($name),
            'token' => $token,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime($options['expires_in'] ?? '+30 days')),
            'invited_by' => $options['invited_by'] ?? null,
            'personal_message' => vt_sanitize_textarea($options['personal_message'] ?? ''),
            'created_at' => date('Y-m-d H:i:s')
        ];

        if ($existing) {
            // Update existing invitation
            $this->db->update('guest_invitations', $invitation_data, ['id' => $existing->id]);
        } else {
            // Create new invitation
            $this->db->insert('guest_invitations', $invitation_data);
        }

        return $token;
    }

    /**
     * Verify and get invitation by token
     */
    public function get_invitation_by_token(string $token): ?object {
        $sql = "
            SELECT gi.*, e.title as event_title, e.event_date, e.location as event_location,
                   u.display_name as host_name, u.email as host_email
            FROM " . Database::table('guest_invitations') . " gi
            INNER JOIN " . Database::table('events') . " e ON gi.event_id = e.id
            INNER JOIN " . Database::table('users') . " u ON e.host_id = u.id
            WHERE gi.token = :token AND gi.expires_at > NOW() AND e.event_status = 'active'
            LIMIT 1
        ";

        $stmt = $this->db->query($sql, ['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create guest RSVP
     */
    public function create_guest_rsvp(string $token, array $rsvp_data): bool {
        $invitation = $this->get_invitation_by_token($token);
        if (!$invitation) {
            return false;
        }

        $this->db->beginTransaction();

        try {
            // Check if user account exists with this email
            $existing_user = $this->db->selectOne('users', ['email' => $invitation->email]);

            $rsvp_record = [
                'event_id' => $invitation->event_id,
                'user_id' => $existing_user ? $existing_user->id : null,
                'guest_token' => $token,
                'email' => $invitation->email,
                'guest_name' => vt_sanitize_text($rsvp_data['guest_name'] ?? $invitation->guest_name),
                'status' => $rsvp_data['status'],
                'plus_one' => (int) ($rsvp_data['plus_one'] ?? 0),
                'dietary_restrictions' => vt_sanitize_textarea($rsvp_data['dietary_restrictions'] ?? ''),
                'accessibility_needs' => vt_sanitize_textarea($rsvp_data['accessibility_needs'] ?? ''),
                'notes' => vt_sanitize_textarea($rsvp_data['notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Check if RSVP already exists
            $existing_rsvp = $this->db->selectOne('event_rsvps', [
                'event_id' => $invitation->event_id,
                'email' => $invitation->email
            ]);

            if ($existing_rsvp) {
                $rsvp_record['updated_at'] = date('Y-m-d H:i:s');
                $this->db->update('event_rsvps', $rsvp_record, ['id' => $existing_rsvp->id]);
            } else {
                $this->db->insert('event_rsvps', $rsvp_record);
            }

            // Update invitation status
            $this->db->update('guest_invitations', [
                'status' => 'responded',
                'responded_at' => date('Y-m-d H:i:s')
            ], ['id' => $invitation->id]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('Guest RSVP creation failed', ['token' => $token, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Convert guest to user account
     */
    public function convert_guest_to_user(string $email, string $password, array $profile_data = []): ?int {
        // Check if user already exists
        $existing_user = $this->db->selectOne('users', ['email' => $email]);
        if ($existing_user) {
            return $existing_user->id;
        }

        $this->db->beginTransaction();

        try {
            // Generate username from email
            $username = $this->generate_username_from_email($email);

            // Create user account
            $user_data = [
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'display_name' => vt_sanitize_text($profile_data['display_name'] ?? $this->extract_name_from_email($email)),
                'registered' => date('Y-m-d H:i:s'),
                'status' => 'active'
            ];

            $user_id = $this->db->insert('users', $user_data);

            if (!$user_id) {
                throw new Exception('Failed to create user account');
            }

            // Create user profile
            $profile_defaults = [
                'user_id' => $user_id,
                'bio' => vt_sanitize_textarea($profile_data['bio'] ?? ''),
                'location' => vt_sanitize_text($profile_data['location'] ?? ''),
                'timezone' => $profile_data['timezone'] ?? 'America/New_York',
                'hosting_style' => $profile_data['hosting_style'] ?? 'casual',
                'hosting_experience' => $profile_data['hosting_experience'] ?? 'beginner',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('user_profiles', $profile_defaults);

            // Update all guest records to link to this user
            $this->link_guest_data_to_user($email, $user_id);

            $this->db->commit();
            return $user_id;

        } catch (Exception $e) {
            $this->db->rollback();
            vt_log_error('Guest to user conversion failed', ['email' => $email, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Link all guest data to user account
     */
    private function link_guest_data_to_user(string $email, int $user_id): void {
        // Update RSVPs
        $this->db->update('event_rsvps', ['user_id' => $user_id], ['email' => $email]);

        // Update guest invitations
        $this->db->update('guest_invitations', ['converted_user_id' => $user_id], ['email' => $email]);

        // Update any conversation participants
        $this->db->update('conversation_participants', ['user_id' => $user_id], ['email' => $email]);
    }

    /**
     * Generate unique username from email
     */
    private function generate_username_from_email(string $email): string {
        $base_username = strtolower(explode('@', $email)[0]);
        $base_username = preg_replace('/[^a-z0-9]/', '', $base_username);

        if (strlen($base_username) < 3) {
            $base_username = 'user' . rand(100, 999);
        }

        $username = $base_username;
        $counter = 1;

        while ($this->db->selectOne('users', ['username' => $username])) {
            $username = $base_username . $counter;
            $counter++;

            if ($counter > 1000) {
                $username = $base_username . uniqid();
                break;
            }
        }

        return $username;
    }

    /**
     * Extract display name from email
     */
    private function extract_name_from_email(string $email): string {
        $name_part = explode('@', $email)[0];
        $name_part = str_replace(['.', '_', '-'], ' ', $name_part);
        return ucwords($name_part);
    }

    /**
     * Get guest invitations for an event
     */
    public function get_event_invitations(int $event_id, string $status = ''): array {
        $where_conditions = ['gi.event_id = :event_id'];
        $params = ['event_id' => $event_id];

        if ($status) {
            $where_conditions[] = 'gi.status = :status';
            $params['status'] = $status;
        }

        $sql = "
            SELECT gi.*, r.status as rsvp_status, r.plus_one, r.notes,
                   u.display_name as converted_user_name
            FROM " . Database::table('guest_invitations') . " gi
            LEFT JOIN " . Database::table('event_rsvps') . " r ON gi.event_id = r.event_id AND gi.email = r.email
            LEFT JOIN " . Database::table('users') . " u ON gi.converted_user_id = u.id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY gi.created_at DESC
        ";

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get guest RSVP data
     */
    public function get_guest_rsvp(string $token): ?object {
        $sql = "
            SELECT r.*, gi.guest_name, gi.personal_message,
                   e.title as event_title, e.event_date, e.location as event_location
            FROM " . Database::table('guest_invitations') . " gi
            LEFT JOIN " . Database::table('event_rsvps') . " r ON gi.event_id = r.event_id AND gi.email = r.email
            INNER JOIN " . Database::table('events') . " e ON gi.event_id = e.id
            WHERE gi.token = :token AND gi.expires_at > NOW()
            LIMIT 1
        ";

        $stmt = $this->db->query($sql, ['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Send guest invitation email
     */
    public function send_invitation_email(int $event_id, string $email, string $name = '', string $personal_message = ''): bool {
        $token = $this->create_invitation($event_id, $email, $name, [
            'personal_message' => $personal_message
        ]);

        if (!$token) {
            return false;
        }

        $invitation = $this->get_invitation_by_token($token);
        if (!$invitation) {
            return false;
        }

        $invitation_url = vt_base_url("/rsvp/{$token}");
        $event_date = vt_format_date($invitation->event_date, 'F j, Y \a\t g:i A');

        $subject = "You're invited to {$invitation->event_title}";

        $message = "
        <html>
        <head><title>You're invited to {$invitation->event_title}</title></head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2563eb;'>You're invited!</h1>

                <div style='background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h2 style='margin-top: 0;'>{$invitation->event_title}</h2>
                    <p><strong>When:</strong> {$event_date}</p>
                    " . ($invitation->event_location ? "<p><strong>Where:</strong> {$invitation->event_location}</p>" : "") . "
                    <p><strong>Host:</strong> {$invitation->host_name}</p>
                </div>

                " . ($personal_message ? "<div style='margin: 20px 0;'><h3>Personal message</h3><p style='font-style: italic;'>{$personal_message}</p></div>" : "") . "

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$invitation_url}' style='background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>RSVP Now</a>
                </div>

                <div style='background: #f0f9ff; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    <p style='margin: 0; color: #0369a1;'>
                        <strong>No account required!</strong> You can RSVP without creating an account.
                        If you'd like to create an account later to manage your RSVPs and create your own events,
                        you can do so at any time.
                    </p>
                </div>

                <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                    This invitation was sent via VivalaTable. If you don't want to receive event invitations,
                    please contact {$invitation->host_name} directly.
                </p>
            </div>
        </body>
        </html>";

        return vt_send_email($email, $subject, $message);
    }

    /**
     * Bulk send invitations
     */
    public function bulk_send_invitations(int $event_id, array $invitees, string $personal_message = ''): array {
        $results = [];

        foreach ($invitees as $invitee) {
            $email = $invitee['email'];
            $name = $invitee['name'] ?? '';

            try {
                if ($this->send_invitation_email($event_id, $email, $name, $personal_message)) {
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
     * Get guest statistics for an event
     */
    public function get_guest_stats(int $event_id): array {
        $sql = "
            SELECT
                COUNT(*) as total_invitations,
                COUNT(CASE WHEN gi.status = 'pending' THEN 1 END) as pending_invitations,
                COUNT(CASE WHEN gi.status = 'responded' THEN 1 END) as responded_invitations,
                COUNT(CASE WHEN r.status = 'attending' THEN 1 END) as attending_guests,
                COUNT(CASE WHEN r.status = 'maybe' THEN 1 END) as maybe_guests,
                COUNT(CASE WHEN r.status = 'declined' THEN 1 END) as declined_guests,
                COUNT(CASE WHEN gi.converted_user_id IS NOT NULL THEN 1 END) as converted_users
            FROM " . Database::table('guest_invitations') . " gi
            LEFT JOIN " . Database::table('event_rsvps') . " r ON gi.event_id = r.event_id AND gi.email = r.email
            WHERE gi.event_id = :event_id
        ";

        $stmt = $this->db->query($sql, ['event_id' => $event_id]);
        $stats = $stmt->fetch();

        return [
            'total_invitations' => (int) ($stats->total_invitations ?? 0),
            'pending_invitations' => (int) ($stats->pending_invitations ?? 0),
            'responded_invitations' => (int) ($stats->responded_invitations ?? 0),
            'attending_guests' => (int) ($stats->attending_guests ?? 0),
            'maybe_guests' => (int) ($stats->maybe_guests ?? 0),
            'declined_guests' => (int) ($stats->declined_guests ?? 0),
            'converted_users' => (int) ($stats->converted_users ?? 0),
            'response_rate' => ($stats->total_invitations > 0)
                ? round(($stats->responded_invitations / $stats->total_invitations) * 100, 1)
                : 0
        ];
    }

    /**
     * Clean up expired invitations
     */
    public function cleanup_expired_invitations(): int {
        return $this->db->update('guest_invitations', [
            'status' => 'expired',
            'updated_at' => date('Y-m-d H:i:s')
        ], [
            'expires_at <' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ]);
    }

    /**
     * Resend invitation
     */
    public function resend_invitation(string $token, string $personal_message = ''): bool {
        $invitation = $this->get_invitation_by_token($token);
        if (!$invitation) {
            return false;
        }

        // Extend expiration
        $this->db->update('guest_invitations', [
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'personal_message' => vt_sanitize_textarea($personal_message),
            'updated_at' => date('Y-m-d H:i:s')
        ], ['token' => $token]);

        return $this->send_invitation_email(
            $invitation->event_id,
            $invitation->email,
            $invitation->guest_name,
            $personal_message
        );
    }
}