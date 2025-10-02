<?php
/**
 * VivalaTable Event Manager
 * Ported from PartyMinder Event Manager
 */

class VT_Event_Manager {

    public function __construct() {
        // Pure custom table system
    }

    public function createEvent($event_data) {
        $db = VT_Database::getInstance();

        // Validate required fields
        if (empty($event_data['title']) || empty($event_data['event_date'])) {
            return VT_Http::jsonError('Event title and date are required', 'missing_data');
        }

        // Generate unique slug
        $slug = $this->generateUniqueSlug($event_data['title']);

        // Determine privacy based on inheritance model
        $privacy = $this->determineEventPrivacy($event_data);
        if (is_array($privacy) && isset($privacy['error'])) {
            return $privacy;
        }

        // Insert event data
        $result = $db->insert('events', [
            'title' => vt_service('validation.sanitizer')->textField($event_data['title']),
            'slug' => $slug,
            'description' => vt_service('validation.sanitizer')->richText($event_data['description'] ?? ''),
            'excerpt' => vt_service('validation.sanitizer')->textField(substr(strip_tags($event_data['description'] ?? ''), 0, 250)),
            'event_date' => vt_service('validation.sanitizer')->textField($event_data['event_date']),
            'event_time' => vt_service('validation.sanitizer')->textField($event_data['event_time'] ?? ''),
            'guest_limit' => vt_service('validation.sanitizer')->integer($event_data['guest_limit'] ?? 0),
            'venue_info' => vt_service('validation.sanitizer')->textField($event_data['venue'] ?? ''),
            'host_email' => vt_service('validation.sanitizer')->email($event_data['host_email'] ?? ''),
            'host_notes' => vt_service('validation.sanitizer')->richText($event_data['host_notes'] ?? ''),
            'privacy' => $privacy,
            'event_status' => 'active',
            'author_id' => vt_service('auth.service')->getCurrentUserId() ?: 1,
            'community_id' => vt_service('validation.sanitizer')->integer($event_data['community_id'] ?? 0),
            'meta_title' => vt_service('validation.sanitizer')->textField($event_data['title']),
            'meta_description' => vt_service('validation.sanitizer')->textField(substr(strip_tags($event_data['description'] ?? ''), 0, 160)),
            'created_by' => vt_service('auth.service')->getCurrentUserId() ?: 1,
        ]);

        if ($result === false) {
            return VT_Http::jsonError('Failed to create event', 'creation_failed');
        }

        $event_id = $result;

        // Update profile stats for event creation
        if (class_exists('VT_Profile_Manager')) {
            $author_id = vt_service('validation.sanitizer')->integer($event_data['author_id'] ?? vt_service('auth.service')->getCurrentUserId());
            VT_Profile_Manager::incrementEventsHosted($author_id);
        }

        return $event_id;
    }

    public function createEventForm($event_data) {
        $db = VT_Database::getInstance();

        // Validate required fields
        if (empty($event_data['title']) || empty($event_data['event_date'])) {
            return ['error' => 'Event title and date are required'];
        }

        // Generate unique slug
        $slug = $this->generateUniqueSlug($event_data['title']);

        // Determine privacy based on inheritance model
        $privacy = $this->determineEventPrivacy($event_data);
        if (is_array($privacy) && isset($privacy['error'])) {
            return $privacy;
        }

        // Insert event data
        $result = $db->insert('events', [
            'title' => vt_service('validation.sanitizer')->textField($event_data['title']),
            'slug' => $slug,
            'description' => vt_service('validation.sanitizer')->richText($event_data['description'] ?? ''),
            'excerpt' => vt_service('validation.sanitizer')->textField(substr(strip_tags($event_data['description'] ?? ''), 0, 250)),
            'event_date' => vt_service('validation.sanitizer')->textField($event_data['event_date']),
            'event_time' => vt_service('validation.sanitizer')->textField($event_data['event_time'] ?? ''),
            'guest_limit' => vt_service('validation.sanitizer')->integer($event_data['guest_limit'] ?? 0),
            'venue_info' => vt_service('validation.sanitizer')->textField($event_data['venue'] ?? ''),
            'host_email' => vt_service('validation.sanitizer')->email($event_data['host_email'] ?? ''),
            'host_notes' => vt_service('validation.sanitizer')->richText($event_data['host_notes'] ?? ''),
            'privacy' => $privacy,
            'event_status' => 'active',
            'author_id' => vt_service('auth.service')->getCurrentUserId() ?: 1,
            'community_id' => vt_service('validation.sanitizer')->integer($event_data['community_id'] ?? 0),
            'meta_title' => vt_service('validation.sanitizer')->textField($event_data['title']),
            'meta_description' => vt_service('validation.sanitizer')->textField(substr(strip_tags($event_data['description'] ?? ''), 0, 160)),
            'created_by' => vt_service('auth.service')->getCurrentUserId() ?: 1,
        ]);

        if ($result === false) {
            return ['error' => 'Failed to create event'];
        }

        $event_id = $result;

        // Update profile stats for event creation
        if (class_exists('VT_Profile_Manager')) {
            $author_id = vt_service('validation.sanitizer')->integer($event_data['author_id'] ?? vt_service('auth.service')->getCurrentUserId());
            VT_Profile_Manager::incrementEventsHosted($author_id);
        }

        return ['success' => true, 'event_id' => $event_id];
    }

    private function generateUniqueSlug($title) {
        $db = VT_Database::getInstance();

        $base_slug = vt_service('validation.sanitizer')->slug($title);
        $slug = $base_slug;
        $counter = 1;

        while ($db->getVar("SELECT id FROM vt_events WHERE slug = '$slug'")) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function getEvent($event_id) {
        $db = VT_Database::getInstance();

        $event = $db->getRow("SELECT * FROM vt_events WHERE id = $event_id");

        if (!$event) {
            return null;
        }

        // Get guest stats
        $event->guest_stats = $this->getGuestStats($event_id);

        return $event;
    }

    public function getEventBySlug($slug) {
        $db = VT_Database::getInstance();

        $event = $db->getRow("SELECT * FROM vt_events WHERE slug = '$slug' AND event_status = 'active'");

        if (!$event) {
            return null;
        }

        // Get guest stats
        $event->guest_stats = $this->getGuestStats($event->id);

        return $event;
    }

    public function canUserViewEvent($event) {
        if (!$event) {
            return false;
        }

        // Public events can be viewed by anyone
        if ($event->privacy === 'public') {
            return true;
        }

        // Private events can only be viewed by the creator
        $current_user_id = vt_service('auth.service')->getCurrentUserId();
        if ($current_user_id && $event->author_id == $current_user_id) {
            return true;
        }

        // Check if current user is an invited guest (RSVP'd)
        if (vt_service('auth.service')->isLoggedIn()) {
            $current_user = vt_service('auth.service')->getCurrentUser();
            $user_email = $current_user->email;

            $db = VT_Database::getInstance();

            $guest_record = $db->getVar("
                SELECT id FROM vt_guests
                WHERE event_id = $event->id AND email = '$user_email'
            ");

            if ($guest_record) {
                return true;
            }

            // Also check if user has a pending invitation
            $invitation_record = $db->getVar("
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

    public function getUpcomingEvents($limit = 10) {
        $db = VT_Database::getInstance();
        $current_user_id = vt_service('auth.service')->getCurrentUserId();

        // Restore proper conditional logic but fix the SQL
        if ($current_user_id && vt_service('auth.service')->isLoggedIn()) {
            // For logged-in users: show their events + public events
            $query = "SELECT DISTINCT e.* FROM {$db->prefix}events e
                     WHERE e.event_status = 'active'
                     AND (
                        e.privacy = 'public' OR
                        e.author_id = " . intval($current_user_id) . "
                     )
                     ORDER BY e.event_date ASC
                     LIMIT " . intval($limit);
            $results = $db->getResults($query);
        } else {
            // Not logged in: only show public events
            $query = "SELECT e.* FROM {$db->prefix}events e
                     WHERE e.event_status = 'active'
                     AND e.privacy = 'public'
                     ORDER BY e.event_date ASC
                     LIMIT " . intval($limit);
            $results = $db->getResults($query);
        }

        // Add guest stats to each event
        if ($results && is_array($results)) {
            foreach ($results as $event) {
                $event->guest_stats = $this->getGuestStats($event->id);
            }
        } else {
            $results = array(); // Return empty array instead of null
        }

        return $results;
    }

    public function getGuestStats($event_id) {
        if (class_exists('VT_Guest_Manager')) {
            $guest_manager = new VT_Guest_Manager();
            return $guest_manager->getGuestStats($event_id);
        }
        return ['total' => 0, 'attending' => 0, 'declined' => 0, 'pending' => 0];
    }

    /**
     * Check if user can edit an event
     * Event creator, community admin, or site admin can edit
     */
    public function canEditEvent($event_id, $user_id = null) {
        if (!$user_id) {
            $user_id = vt_service('auth.service')->getCurrentUserId();
        }

        if (!$user_id) {
            return false;
        }

        // Site admins can edit anything
        if (vt_service('auth.service')->isSiteAdmin()) {
            return true;
        }

        // Get event
        $event = $this->getEvent($event_id);
        if (!$event) {
            return false;
        }

        // Event creator can edit
        if ($event->author_id == $user_id) {
            return true;
        }

        // Community admin can edit events in their community
        if ($event->community_id) {
            $community_manager = new VT_Community_Manager();
            if ($community_manager->canManageCommunity($event->community_id, $user_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can delete an event
     * Event creator can delete if no RSVPs, site admin can always delete
     */
    public function canDeleteEvent($event_id, $user_id = null) {
        if (!$user_id) {
            $user_id = vt_service('auth.service')->getCurrentUserId();
        }

        if (!$user_id) {
            return false;
        }

        // Site admins can delete anything
        if (vt_service('auth.service')->isSiteAdmin()) {
            return true;
        }

        // Get event
        $event = $this->getEvent($event_id);
        if (!$event) {
            return false;
        }

        // Event creator can delete only if no confirmed RSVPs
        if ($event->author_id == $user_id) {
            $db = VT_Database::getInstance();
            $confirmed_count = $db->getVar(
                $db->prepare(
                    "SELECT COUNT(*) FROM vt_guests WHERE event_id = %d AND status = 'confirmed'",
                    $event_id
                )
            );
            return $confirmed_count == 0;
        }

        return false;
    }

    public function updateEvent($event_id, $event_data) {
        $db = VT_Database::getInstance();

        // Validate required fields
        if (empty($event_data['title']) || empty($event_data['event_date'])) {
            return VT_Http::jsonError('Event title and date are required', 'missing_data');
        }

        // Get current event
        $current_event = $this->getEvent($event_id);
        if (!$current_event) {
            return VT_Http::jsonError('Event not found', 'event_not_found');
        }

        // Check permissions - only event host (author) can update events
        $current_user_id = vt_service('auth.service')->getCurrentUserId();
        if ($current_event->author_id != $current_user_id && !vt_service('auth.service')->currentUserCan('edit_others_posts')) {
            return VT_Http::jsonError('Only the event host can update this event', 'permission_denied');
        }

        // Generate unique slug if title changed
        $slug = $current_event->slug;
        if ($current_event->title !== $event_data['title']) {
            $slug = $this->generateUniqueSlug($event_data['title']);
        }

        // Update event data
        $update_data = [
            'title' => vt_service('validation.sanitizer')->textField($event_data['title']),
            'slug' => $slug,
            'description' => vt_service('validation.sanitizer')->richText($event_data['description'] ?? ''),
            'excerpt' => vt_service('validation.sanitizer')->textField(substr(strip_tags($event_data['description'] ?? ''), 0, 250)),
            'event_date' => vt_service('validation.sanitizer')->textField($event_data['event_date']),
            'event_time' => vt_service('validation.sanitizer')->textField($event_data['event_time'] ?? ''),
            'guest_limit' => vt_service('validation.sanitizer')->integer($event_data['guest_limit'] ?? 0),
            'venue_info' => vt_service('validation.sanitizer')->textField($event_data['venue'] ?? ''),
            'host_email' => vt_service('validation.sanitizer')->email($event_data['host_email'] ?? ''),
            'host_notes' => vt_service('validation.sanitizer')->richText($event_data['host_notes'] ?? ''),
            'privacy' => $this->validatePrivacySetting($event_data['privacy'] ?? 'public'),
            'meta_title' => vt_service('validation.sanitizer')->textField($event_data['title']),
            'meta_description' => vt_service('validation.sanitizer')->textField(substr(strip_tags($event_data['description'] ?? ''), 0, 160)),
        ];

        $result = $db->update('events', $update_data, ['id' => $event_id]);

        if ($result === false) {
            return VT_Http::jsonError('Failed to update event data', 'db_error');
        }

        return $event_id;
    }

    public function sendEventInvitation($event_id, $email, $message = '') {
        $db = VT_Database::getInstance();

        // Get event
        $event = $this->getEvent($event_id);
        if (!$event) {
            return VT_Http::jsonError('Event not found', 'event_not_found');
        }

        // Check permissions - only event host can send invitations
        $current_user = vt_service('auth.service')->getCurrentUser();
        if (!$current_user) {
            return VT_Http::jsonError('You must be logged in', 'user_required');
        }

        if ($event->author_id != $current_user->id && !vt_service('auth.service')->currentUserCan('edit_others_posts')) {
            return VT_Http::jsonError('Only the event host can send invitations', 'permission_denied');
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return VT_Http::jsonError('Please provide a valid email address', 'invalid_email');
        }

        // Check if user is already RSVP'd
        $existing_rsvp = $db->getRow("
            SELECT * FROM vt_guests
            WHERE event_id = $event_id AND email = '$email'
        ");

        if ($existing_rsvp) {
            return VT_Http::jsonError('This person has already RSVP\'d to the event', 'already_rsvpd');
        }

        // Generate invitation token
        $token = vt_service('security.service')->generateToken();

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
            'message' => vt_service('validation.sanitizer')->richText($message),
            'status' => 'pending',
            'expires_at' => $expires_at,
        ]);

        if ($result === false) {
            return VT_Http::jsonError('Failed to create invitation', 'invitation_failed');
        }

        $invitation_id = $result;

        // Send invitation email
        $email_sent = $this->sendevent_invitation_email($event, $current_user, $email, $token, $message);

        return [
            'invitation_id' => $invitation_id,
            'token' => $token,
            'expires_at' => $expires_at,
            'email_sent' => $email_sent,
        ];
    }

    private function sendEventInvitationEmail($event, $inviter, $email, $token, $message = '') {
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

    public function getEventInvitations($event_id, $limit = 20, $offset = 0) {
        $db = VT_Database::getInstance();

        // Get event
        $event = $this->getEvent($event_id);
        if (!$event) {
            return VT_Http::jsonError('Event not found', 'event_not_found');
        }

        $invitations = $db->getResults("
            SELECT * FROM vt_event_invitations
            WHERE event_id = $event_id AND status = 'pending'
            ORDER BY created_at DESC
            LIMIT $limit OFFSET $offset
        ");

        return $invitations ?: [];
    }

    public function cancelEventInvitation($event_id, $invitation_id) {
        $db = VT_Database::getInstance();

        // Get event
        $event = $this->getEvent($event_id);
        if (!$event) {
            return VT_Http::jsonError('Event not found', 'event_not_found');
        }

        // Check permissions - only event host can cancel invitations
        $current_user = vt_service('auth.service')->getCurrentUser();
        if (!$current_user) {
            return VT_Http::jsonError('You must be logged in', 'user_required');
        }

        if ($event->author_id != $current_user->id && !vt_service('auth.service')->currentUserCan('edit_others_posts')) {
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

    public function getInvitationByToken($token) {
        $db = VT_Database::getInstance();

        return $db->getRow("
            SELECT g.*, e.title as event_title, e.slug as event_slug, e.description as event_description,
                e.event_date, e.venue_info
            FROM vt_event_invitations g
            LEFT JOIN vt_events e ON g.event_id = e.id
            WHERE g.invitation_token = '$token'
        ");
    }

    public function acceptEventInvitation($token, $user_id, $guest_data = []) {
        $db = VT_Database::getInstance();

        // Get invitation
        $invitation = $this->getinvitation_by_token($token);
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
        $existing_rsvp = $db->getRow("
            SELECT * FROM vt_guests
            WHERE event_id = $invitation->event_id AND email = '$invitation->invited_email'
        ");

        if ($existing_rsvp) {
            return VT_Http::jsonError('You have already RSVP\'d to this event', 'already_rsvpd');
        }

        // Get user info
        $user = $db->getRow("SELECT * FROM vt_users WHERE id = $user_id");
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

    public function deleteEvent($event_id) {
        $db = VT_Database::getInstance();

        // Get event first to check if it exists
        $event = $this->getEvent($event_id);
        if (!$event) {
            return VT_Http::jsonError('Event not found', 'event_not_found');
        }

        // Check permissions - only event creator or admin can delete
        $current_user_id = vt_service('auth.service')->getCurrentUserId();
        if (!$current_user_id) {
            return VT_Http::jsonError('You must be logged in', 'user_required');
        }

        if ($event->author_id != $current_user_id && !vt_service('auth.service')->currentUserCan('delete_others_posts')) {
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

    public function getUserEvents($user_id, $limit = 6) {
        $db = VT_Database::getInstance();

        $events = $db->getResults("
            SELECT e.id, e.title, e.slug, e.description, e.event_date, e.event_time, e.venue_info
            FROM vt_events e
            WHERE e.author_id = $user_id AND e.event_status = 'active'
            ORDER BY e.event_date ASC
            LIMIT $limit
        ");

        // Add guest stats to each event
        foreach ($events as $event) {
            $event->guest_stats = $this->getGuestStats($event->id);
        }

        return $events;
    }

    public function getCommunityEvents($community_id, $limit = 20) {
        $db = VT_Database::getInstance();
        $current_user_id = vt_service('auth.service')->getCurrentUserId();

        // Get community to check privacy
        if (class_exists('VT_Community_Manager')) {
            $community_manager = new VT_Community_Manager();
            $community = $community_manager->getCommunity($community_id);

            if (!$community) {
                return [];
            }

            // Check access to community
            $can_access_community = false;

            if ($community->privacy === 'public') {
                $can_access_community = true;
            } elseif ($current_user_id && vt_service('auth.service')->isLoggedIn()) {
                if ($community->creator_id == $current_user_id ||
                    $community_manager->isMember($community_id, $current_user_id)) {
                    $can_access_community = true;
                }
            }

            if (!$can_access_community) {
                return [];
            }
        }

        $events = $db->getResults("
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
            $event->guest_stats = $this->getGuestStats($event->id);
        }

        return $events;
    }

    private function determineEventPrivacy($event_data) {
        $community_id = vt_service('validation.sanitizer')->integer($event_data['community_id'] ?? 0);

        // For community events, inherit privacy from community
        if ($community_id) {
            if (class_exists('VT_Community_Manager')) {
                $community_manager = new VT_Community_Manager();
                $community = $community_manager->getCommunity($community_id);

                if (!$community) {
                    return ['error' => 'Community not found'];
                }

                return $community->privacy;
            }
        }

        // For non-community events, use provided privacy or default to public
        return $this->validatePrivacySetting($event_data['privacy'] ?? 'public');
    }

    public function validateEventPrivacyInheritance($event_data) {
        $community_id = vt_service('validation.sanitizer')->integer($event_data['community_id'] ?? 0);
        $provided_privacy = $event_data['privacy'] ?? null;

        if ($community_id && $provided_privacy) {
            if (class_exists('VT_Community_Manager')) {
                $community_manager = new VT_Community_Manager();
                $community = $community_manager->getCommunity($community_id);

                if ($community && $community->privacy !== $provided_privacy) {
                    return VT_Http::jsonError(
                        sprintf('Event privacy must match community privacy (%s)', $community->privacy),
                        'privacy_mismatch'
                    );
                }
            }
        }

        return true;
    }

    public function getEventPrivacy($event) {
        // If event is part of a community, inherit community privacy
        if ($event->community_id) {
            if (class_exists('VT_Community_Manager')) {
                $community_manager = new VT_Community_Manager();
                $community = $community_manager->getCommunity($event->community_id);

                if ($community) {
                    return $community->privacy;
                }
            }
        }

        return $event->privacy;
    }

    private function validatePrivacySetting($privacy) {
        $allowed_privacy_settings = ['public', 'private'];

        $privacy = vt_service('validation.sanitizer')->textField($privacy);

        if (!in_array($privacy, $allowed_privacy_settings)) {
            return 'public';
        }

        return $privacy;
    }

    private function getUserIdByEmail($email) {
        $db = VT_Database::getInstance();
        return $db->getVar("SELECT id FROM vt_users WHERE email = '$email'");
    }
}