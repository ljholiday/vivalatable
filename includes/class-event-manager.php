<?php
/**
 * VivalaTable Event Manager
 * Ported from PartyMinder Event Manager
 */

class VT_Event_Manager {

    public function __construct() {
        // Pure custom table system
    }

    public function create_event($event_data) {
        $db = VT_Database::getInstance();

        // Validate required fields
        if (empty($event_data['title']) || empty($event_data['event_date'])) {
            return VT_Http::jsonError('Event title and date are required', 'missing_data');
        }

        // Generate unique slug
        $slug = $this->generate_unique_slug($event_data['title']);

        // Determine privacy based on inheritance model
        $privacy = $this->determine_event_privacy($event_data);
        if (is_array($privacy) && isset($privacy['error'])) {
            return $privacy;
        }

        // Insert event data
        $result = $db->insert('events', [
            'title' => VT_Sanitize::textField($event_data['title']),
            'slug' => $slug,
            'description' => VT_Sanitize::post($event_data['description'] ?? ''),
            'excerpt' => VT_Sanitize::textField(substr(strip_tags($event_data['description'] ?? ''), 0, 250)),
            'event_date' => VT_Sanitize::textField($event_data['event_date']),
            'event_time' => VT_Sanitize::textField($event_data['event_time'] ?? ''),
            'guest_limit' => VT_Sanitize::int($event_data['guest_limit'] ?? 0),
            'venue_info' => VT_Sanitize::textField($event_data['venue'] ?? ''),
            'host_email' => VT_Sanitize::email($event_data['host_email'] ?? ''),
            'host_notes' => VT_Sanitize::post($event_data['host_notes'] ?? ''),
            'privacy' => $privacy,
            'event_status' => 'active',
            'author_id' => VT_Auth::getCurrentUserId() ?: 1,
            'community_id' => VT_Sanitize::int($event_data['community_id'] ?? 0),
            'meta_title' => VT_Sanitize::textField($event_data['title']),
            'meta_description' => VT_Sanitize::textField(substr(strip_tags($event_data['description'] ?? ''), 0, 160)),
            'created_by' => VT_Auth::getCurrentUserId() ?: 1
        ]);

        if ($result === false) {
            return VT_Http::jsonError('Failed to create event', 'creation_failed');
        }

        $event_id = $result;

        // Update profile stats for event creation
        if (class_exists('VT_Profile_Manager')) {
            $author_id = VT_Sanitize::int($event_data['author_id'] ?? VT_Auth::getCurrentUserId());
            VT_Profile_Manager::increment_events_hosted($author_id);
        }

        return $event_id;
    }

    public function create_event_form($event_data) {
        $db = VT_Database::getInstance();

        // Validate required fields
        if (empty($event_data['title']) || empty($event_data['event_date'])) {
            return ['error' => 'Event title and date are required'];
        }

        // Generate unique slug
        $slug = $this->generate_unique_slug($event_data['title']);

        // Determine privacy based on inheritance model
        $privacy = $this->determine_event_privacy($event_data);
        if (is_array($privacy) && isset($privacy['error'])) {
            return $privacy;
        }

        // Generate post_id (using timestamp + random for uniqueness)
        $post_id = time() . rand(100, 999);

        // Insert event data
        $result = $db->insert('events', [
            'title' => VT_Sanitize::textField($event_data['title']),
            'slug' => $slug,
            'description' => VT_Sanitize::post($event_data['description'] ?? ''),
            'excerpt' => VT_Sanitize::textField(substr(strip_tags($event_data['description'] ?? ''), 0, 250)),
            'event_date' => VT_Sanitize::textField($event_data['event_date']),
            'event_time' => VT_Sanitize::textField($event_data['event_time'] ?? ''),
            'guest_limit' => VT_Sanitize::int($event_data['guest_limit'] ?? 0),
            'venue_info' => VT_Sanitize::textField($event_data['venue'] ?? ''),
            'host_email' => VT_Sanitize::email($event_data['host_email'] ?? ''),
            'host_notes' => VT_Sanitize::post($event_data['host_notes'] ?? ''),
            'privacy' => $privacy,
            'event_status' => 'active',
            'author_id' => VT_Auth::getCurrentUserId() ?: 1,
            'community_id' => VT_Sanitize::int($event_data['community_id'] ?? 0),
            'meta_title' => VT_Sanitize::textField($event_data['title']),
            'meta_description' => VT_Sanitize::textField(substr(strip_tags($event_data['description'] ?? ''), 0, 160)),
            'created_by' => VT_Auth::getCurrentUserId() ?: 1,
            'post_id' => $post_id
        ]);

        if ($result === false) {
            return ['error' => 'Failed to create event'];
        }

        $event_id = $result;

        // Update profile stats for event creation
        if (class_exists('VT_Profile_Manager')) {
            $author_id = VT_Sanitize::int($event_data['author_id'] ?? VT_Auth::getCurrentUserId());
            VT_Profile_Manager::increment_events_hosted($author_id);
        }

        return ['success' => true, 'event_id' => $event_id];
    }

    private function generate_unique_slug($title) {
        $db = VT_Database::getInstance();

        $base_slug = VT_Sanitize::slug($title);
        $slug = $base_slug;
        $counter = 1;

        while ($db->get_var("SELECT id FROM vt_events WHERE slug = '$slug'")) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function get_event($event_id) {
        $db = VT_Database::getInstance();

        $event = $db->get_row("SELECT * FROM vt_events WHERE id = $event_id");

        if (!$event) {
            return null;
        }

        // Get guest stats
        $event->guest_stats = $this->get_guest_stats($event_id);

        return $event;
    }

    public function get_event_by_slug($slug) {
        $db = VT_Database::getInstance();

        $event = $db->get_row("SELECT * FROM vt_events WHERE slug = '$slug' AND event_status = 'active'");

        if (!$event) {
            return null;
        }

        // Get guest stats
        $event->guest_stats = $this->get_guest_stats($event->id);

        return $event;
    }

    public function can_user_view_event($event) {
        if (!$event) {
            return false;
        }

        // Public events can be viewed by anyone
        if ($event->privacy === 'public') {
            return true;
        }

        // Private events can only be viewed by the creator
        $current_user_id = VT_Auth::getCurrentUserId();
        if ($current_user_id && $event->author_id == $current_user_id) {
            return true;
        }

        // Check if current user is an invited guest (RSVP'd)
        if (VT_Auth::isLoggedIn()) {
            $current_user = VT_Auth::getCurrentUser();
            $user_email = $current_user->email;

            $db = VT_Database::getInstance();

            $guest_record = $db->get_var("
                SELECT id FROM vt_guests
                WHERE event_id = $event->id AND email = '$user_email'
            ");

            if ($guest_record) {
                return true;
            }

            // Also check if user has a pending invitation
            $invitation_record = $db->get_var("
                SELECT id FROM vt_guests
                WHERE event_id = $event->id AND email = '$user_email'
                AND status = 'pending' AND rsvp_token != ''
            ");

            if ($invitation_record) {
                return true;
            }
        }

        return false;
    }

    public function get_upcoming_events($limit = 10) {
        $db = VT_Database::getInstance();
        $current_user_id = VT_Auth::getCurrentUserId();

        // Enhanced privacy logic that respects inheritance
        if ($current_user_id && VT_Auth::isLoggedIn()) {
            // For logged-in users: show ALL events they have permission to view
            $query = "SELECT DISTINCT e.* FROM vt_events e
                     LEFT JOIN vt_communities c ON e.community_id = c.id
                     LEFT JOIN vt_guests g ON e.id = g.event_id AND g.email = (SELECT email FROM vt_users WHERE id = $current_user_id)
                     WHERE e.event_status = 'active'
                     AND (
                        e.author_id = $current_user_id OR
                        g.event_id IS NOT NULL OR
                        ((e.community_id IS NULL OR e.community_id = 0) AND e.privacy = 'public') OR
                        (e.community_id IS NOT NULL AND e.community_id != 0 AND (
                            c.visibility = 'public' OR
                            c.creator_id = $current_user_id OR
                            EXISTS(
                                SELECT 1 FROM vt_community_members m
                                WHERE m.community_id = c.id AND m.user_id = $current_user_id
                                AND m.status = 'active'
                            )
                        ))
                    )
                     ORDER BY e.event_date ASC
                     LIMIT $limit";

            $results = $db->get_results($query);
        } else {
            // Not logged in: only show public events and events from public communities
            $query = "SELECT DISTINCT e.* FROM vt_events e
                     LEFT JOIN vt_communities c ON e.community_id = c.id
                     WHERE e.event_status = 'active'
                     AND (
                        ((e.community_id IS NULL OR e.community_id = 0) AND e.privacy = 'public') OR
                        (e.community_id IS NOT NULL AND e.community_id != 0 AND c.visibility = 'public')
                    )
                     ORDER BY e.event_date ASC
                     LIMIT $limit";

            $results = $db->get_results($query);
        }

        // Add guest stats to each event
        foreach ($results as $event) {
            $event->guest_stats = $this->get_guest_stats($event->id);
        }

        return $results;
    }

    public function get_guest_stats($event_id) {
        if (class_exists('VT_Guest_Manager')) {
            $guest_manager = new VT_Guest_Manager();
            return $guest_manager->get_guest_stats($event_id);
        }
        return ['total' => 0, 'attending' => 0, 'declined' => 0, 'pending' => 0];
    }

    public function update_event($event_id, $event_data) {
        $db = VT_Database::getInstance();

        // Validate required fields
        if (empty($event_data['title']) || empty($event_data['event_date'])) {
            return VT_Http::jsonError('Event title and date are required', 'missing_data');
        }

        // Get current event
        $current_event = $this->get_event($event_id);
        if (!$current_event) {
            return VT_Http::jsonError('Event not found', 'event_not_found');
        }

        // Check permissions - only event host (author) can update events
        $current_user_id = VT_Auth::getCurrentUserId();
        if ($current_event->author_id != $current_user_id && !VT_Auth::currentUserCan('edit_others_posts')) {
            return VT_Http::jsonError('Only the event host can update this event', 'permission_denied');
        }

        // Generate unique slug if title changed
        $slug = $current_event->slug;
        if ($current_event->title !== $event_data['title']) {
            $slug = $this->generate_unique_slug($event_data['title']);
        }

        // Update event data
        $update_data = [
            'title' => VT_Sanitize::textField($event_data['title']),
            'slug' => $slug,
            'description' => VT_Sanitize::post($event_data['description'] ?? ''),
            'excerpt' => VT_Sanitize::textField(substr(strip_tags($event_data['description'] ?? ''), 0, 250)),
            'event_date' => VT_Sanitize::textField($event_data['event_date']),
            'event_time' => VT_Sanitize::textField($event_data['event_time'] ?? ''),
            'guest_limit' => VT_Sanitize::int($event_data['guest_limit'] ?? 0),
            'venue_info' => VT_Sanitize::textField($event_data['venue'] ?? ''),
            'host_email' => VT_Sanitize::email($event_data['host_email'] ?? ''),
            'host_notes' => VT_Sanitize::post($event_data['host_notes'] ?? ''),
            'privacy' => $this->validate_privacy_setting($event_data['privacy'] ?? 'public'),
            'meta_title' => VT_Sanitize::textField($event_data['title']),
            'meta_description' => VT_Sanitize::textField(substr(strip_tags($event_data['description'] ?? ''), 0, 160)),
        ];

        $result = $db->update('events', $update_data, ['id' => $event_id]);

        if ($result === false) {
            return VT_Http::jsonError('Failed to update event data', 'db_error');
        }

        return $event_id;
    }

    public function send_event_invitation($event_id, $email, $message = '') {
        $db = VT_Database::getInstance();

        // Get event
        $event = $this->get_event($event_id);
        if (!$event) {
            return VT_Http::jsonError('Event not found', 'event_not_found');
        }

        // Check permissions - only event host can send invitations
        $current_user = VT_Auth::getCurrentUser();
        if (!$current_user) {
            return VT_Http::jsonError('You must be logged in', 'user_required');
        }

        if ($event->author_id != $current_user->id && !VT_Auth::currentUserCan('edit_others_posts')) {
            return VT_Http::jsonError('Only the event host can send invitations', 'permission_denied');
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return VT_Http::jsonError('Please provide a valid email address', 'invalid_email');
        }

        // Check if user is already RSVP'd
        $existing_rsvp = $db->get_row("
            SELECT * FROM vt_guests
            WHERE event_id = $event_id AND email = '$email'
        ");

        if ($existing_rsvp) {
            return VT_Http::jsonError('This person has already RSVP\'d to the event', 'already_rsvpd');
        }

        // Generate invitation token
        $token = VT_Security::generateToken();

        // Set expiration (event date or 30 days, whichever is sooner)
        $event_date = strtotime($event->event_date);
        $thirty_days = strtotime('+30 days');
        $expires_at = date('Y-m-d H:i:s', min($event_date, $thirty_days));

        // Insert invitation
        $result = $db->insert('event_invitations', [
            'event_id' => $event_id,
            'invited_by_user_id' => $current_user->id,
            'invited_email' => $email,
            'invited_user_id' => $this->getUserIdByEmail($email),
            'invitation_token' => $token,
            'message' => VT_Sanitize::post($message),
            'status' => 'pending',
            'expires_at' => $expires_at,
        ]);

        if ($result === false) {
            return VT_Http::jsonError('Failed to create invitation', 'invitation_failed');
        }

        $invitation_id = $result;

        // Send invitation email
        $email_sent = $this->send_event_invitation_email($event, $current_user, $email, $token, $message);

        return [
            'invitation_id' => $invitation_id,
            'token' => $token,
            'expires_at' => $expires_at,
            'email_sent' => $email_sent,
        ];
    }

    private function send_event_invitation_email($event, $inviter, $email, $token, $message = '') {
        $site_name = VT_Config::get('site_title', 'VivalaTable');
        $invitation_url = VT_Http::getBaseUrl() . '/events/' . $event->slug . '?token=' . $token;

        $subject = sprintf('[%s] You\'re invited to %s', $site_name, $event->title);

        $event_date = VT_Time::formatDate(strtotime($event->event_date));
        $event_time = $event->event_time ? ' at ' . $event->event_time : '';

        $email_message = sprintf(
            'Hello!

%s has invited you to attend "%s" on %s%s.

%s

%s

To RSVP for this event, click the link below:
%s

This invitation will expire on %s.

If you don\'t want to attend this event, you can safely ignore this email.

Best regards,
The %s Team',
            $inviter->display_name,
            $event->title,
            $event_date,
            $event_time,
            $event->description ? "Event Details:\n" . strip_tags($event->description) . "\n" : '',
            $message ? "\nPersonal message from " . $inviter->display_name . ":\n" . $message . "\n" : '',
            $invitation_url,
            VT_Time::formatDate(strtotime($event->expires_at ?? '+30 days')),
            $site_name
        );

        return VT_Mail::send($email, $subject, $email_message);
    }

    public function get_event_invitations($event_id, $limit = 20, $offset = 0) {
        $db = VT_Database::getInstance();

        // Get event
        $event = $this->get_event($event_id);
        if (!$event) {
            return VT_Http::jsonError('Event not found', 'event_not_found');
        }

        $invitations = $db->get_results("
            SELECT * FROM vt_event_invitations
            WHERE event_id = $event_id AND status = 'pending'
            ORDER BY created_at DESC
            LIMIT $limit OFFSET $offset
        ");

        return $invitations ?: [];
    }

    public function cancel_event_invitation($event_id, $invitation_id) {
        $db = VT_Database::getInstance();

        // Get event
        $event = $this->get_event($event_id);
        if (!$event) {
            return VT_Http::jsonError('Event not found', 'event_not_found');
        }

        // Check permissions - only event host can cancel invitations
        $current_user = VT_Auth::getCurrentUser();
        if (!$current_user) {
            return VT_Http::jsonError('You must be logged in', 'user_required');
        }

        if ($event->author_id != $current_user->id && !VT_Auth::currentUserCan('edit_others_posts')) {
            return VT_Http::jsonError('Only the event host can cancel invitations', 'permission_denied');
        }

        // Delete invitation record
        $result = $db->delete('event_invitations', [
            'id' => $invitation_id,
            'event_id' => $event_id,
            'status' => 'pending',
        ]);

        if ($result === false) {
            return VT_Http::jsonError('Failed to cancel invitation', 'cancel_failed');
        }

        return true;
    }

    public function get_invitation_by_token($token) {
        $db = VT_Database::getInstance();

        return $db->get_row("
            SELECT g.*, e.title as event_title, e.slug as event_slug, e.description as event_description,
                e.event_date, e.venue_info
            FROM vt_event_invitations g
            LEFT JOIN vt_events e ON g.event_id = e.id
            WHERE g.invitation_token = '$token'
        ");
    }

    public function accept_event_invitation($token, $user_id, $guest_data = []) {
        $db = VT_Database::getInstance();

        // Get invitation
        $invitation = $this->get_invitation_by_token($token);
        if (!$invitation) {
            return VT_Http::jsonError('Invitation not found', 'invitation_not_found');
        }

        if ($invitation->status !== 'pending') {
            return VT_Http::jsonError('This invitation has already been processed', 'invitation_processed');
        }

        if (strtotime($invitation->expires_at) < time()) {
            return VT_Http::jsonError('This invitation has expired', 'invitation_expired');
        }

        // Check if user already RSVP'd
        $existing_rsvp = $db->get_row("
            SELECT * FROM vt_guests
            WHERE event_id = $invitation->event_id AND email = '$invitation->invited_email'
        ");

        if ($existing_rsvp) {
            return VT_Http::jsonError('You have already RSVP\'d to this event', 'already_rsvpd');
        }

        // Get user info
        $user = $db->get_row("SELECT * FROM vt_users WHERE id = $user_id");
        if (!$user) {
            return VT_Http::jsonError('User not found', 'user_not_found');
        }

        // Create RSVP
        $rsvp_data = array_merge([
            'event_id' => $invitation->event_id,
            'name' => $user->display_name,
            'email' => $invitation->invited_email,
            'status' => 'attending',
            'user_id' => $user_id,
        ], $guest_data);

        $rsvp_result = $db->insert('guests', $rsvp_data);

        if ($rsvp_result === false) {
            return VT_Http::jsonError('Failed to create RSVP', 'rsvp_failed');
        }

        // Update invitation status
        $db->update('event_invitations',
            ['status' => 'accepted', 'responded_at' => VT_Time::current('mysql')],
            ['id' => $invitation->id]
        );

        return $rsvp_result;
    }

    public function delete_event($event_id) {
        $db = VT_Database::getInstance();

        // Get event first to check if it exists
        $event = $this->get_event($event_id);
        if (!$event) {
            return VT_Http::jsonError('Event not found', 'event_not_found');
        }

        // Check permissions - only event creator or admin can delete
        $current_user_id = VT_Auth::getCurrentUserId();
        if (!$current_user_id) {
            return VT_Http::jsonError('You must be logged in', 'user_required');
        }

        if ($event->author_id != $current_user_id && !VT_Auth::currentUserCan('delete_others_posts')) {
            return VT_Http::jsonError('Only the event host or admin can delete this event', 'permission_denied');
        }

        // Start transaction
        $db->query('START TRANSACTION');

        try {
            // Delete related data first
            $db->delete('guests', ['event_id' => $event_id]);
            $db->delete('event_invitations', ['event_id' => $event_id]);

            if (class_exists('VT_Conversation_Manager')) {
                $db->delete('conversations', ['event_id' => $event_id]);
            }

            // Delete the event itself
            $result = $db->delete('events', ['id' => $event_id]);

            if ($result === false) {
                throw new Exception('Failed to delete event');
            }

            $db->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $db->query('ROLLBACK');
            return VT_Http::jsonError($e->getMessage(), 'deletion_failed');
        }
    }

    public function get_user_events($user_id, $limit = 6) {
        $db = VT_Database::getInstance();

        $events = $db->get_results("
            SELECT e.id, e.title, e.slug, e.event_date, e.event_time, e.venue_info
            FROM vt_events e
            WHERE e.author_id = $user_id AND e.event_status = 'active'
            ORDER BY e.event_date ASC
            LIMIT $limit
        ");

        // Add guest stats to each event
        foreach ($events as $event) {
            $event->guest_stats = $this->get_guest_stats($event->id);
        }

        return $events;
    }

    public function get_community_events($community_id, $limit = 20) {
        $db = VT_Database::getInstance();
        $current_user_id = VT_Auth::getCurrentUserId();

        // Get community to check privacy
        if (class_exists('VT_Community_Manager')) {
            $community_manager = new VT_Community_Manager();
            $community = $community_manager->get_community($community_id);

            if (!$community) {
                return [];
            }

            // Check access to community
            $can_access_community = false;

            if ($community->visibility === 'public') {
                $can_access_community = true;
            } elseif ($current_user_id && VT_Auth::isLoggedIn()) {
                if ($community->creator_id == $current_user_id ||
                    $community_manager->is_member($community_id, $current_user_id)) {
                    $can_access_community = true;
                }
            }

            if (!$can_access_community) {
                return [];
            }
        }

        $events = $db->get_results("
            SELECT DISTINCT e.* FROM vt_events e
            WHERE e.event_status = 'active'
            AND e.community_id = $community_id
            ORDER BY e.event_date DESC
            LIMIT $limit
        ");

        if (!$events) {
            return [];
        }

        // Add guest stats to each event
        foreach ($events as $event) {
            $event->guest_stats = $this->get_guest_stats($event->id);
        }

        return $events;
    }

    private function determine_event_privacy($event_data) {
        $community_id = VT_Sanitize::int($event_data['community_id'] ?? 0);

        // For community events, inherit privacy from community
        if ($community_id) {
            if (class_exists('VT_Community_Manager')) {
                $community_manager = new VT_Community_Manager();
                $community = $community_manager->get_community($community_id);

                if (!$community) {
                    return ['error' => 'Community not found'];
                }

                return $community->privacy;
            }
        }

        // For non-community events, use provided privacy or default to public
        return $this->validate_privacy_setting($event_data['privacy'] ?? 'public');
    }

    public function validate_event_privacy_inheritance($event_data) {
        $community_id = VT_Sanitize::int($event_data['community_id'] ?? 0);
        $provided_privacy = $event_data['privacy'] ?? null;

        if ($community_id && $provided_privacy) {
            if (class_exists('VT_Community_Manager')) {
                $community_manager = new VT_Community_Manager();
                $community = $community_manager->get_community($community_id);

                if ($community && $community->visibility !== $provided_privacy) {
                    return VT_Http::jsonError(
                        sprintf('Event privacy must match community privacy (%s)', $community->privacy),
                        'privacy_mismatch'
                    );
                }
            }
        }

        return true;
    }

    public function get_event_privacy($event) {
        // If event is part of a community, inherit community privacy
        if ($event->community_id) {
            if (class_exists('VT_Community_Manager')) {
                $community_manager = new VT_Community_Manager();
                $community = $community_manager->get_community($event->community_id);

                if ($community) {
                    return $community->privacy;
                }
            }
        }

        return $event->privacy;
    }

    private function validate_privacy_setting($privacy) {
        $allowed_privacy_settings = ['public', 'private'];

        $privacy = VT_Sanitize::textField($privacy);

        if (!in_array($privacy, $allowed_privacy_settings)) {
            return 'public';
        }

        return $privacy;
    }

    private function getUserIdByEmail($email) {
        $db = VT_Database::getInstance();
        return $db->get_var("SELECT id FROM vt_users WHERE email = '$email'");
    }
}