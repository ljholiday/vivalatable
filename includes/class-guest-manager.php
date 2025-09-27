<?php
/**
 * VivalaTable Guest Manager
 * Ported from PartyMinder Guest Manager
 */

class VT_Guest_Manager {

    public function processRsvp($rsvp_data) {
        $db = VT_Database::getInstance();

        // Validate required fields
        if (empty($rsvp_data['event_id']) || empty($rsvp_data['name']) || empty($rsvp_data['email'])) {
            return VT_Http::jsonError('Event ID, name, and email are required', 'missing_data');
        }

        // Validate email
        if (!filter_var($rsvp_data['email'], FILTER_VALIDATE_EMAIL)) {
            return VT_Http::jsonError('Please provide a valid email address', 'invalid_email');
        }

        // Validate status
        $valid_statuses = ['confirmed', 'declined', 'maybe', 'pending'];
        if (!in_array($rsvp_data['status'], $valid_statuses)) {
            $rsvp_data['status'] = 'pending';
        }

        // Check for existing guest
        $existing_guest = null;
        if (!empty($rsvp_data['existing_guest_id'])) {
            $existing_guest = $db->getRow("SELECT * FROM vt_guests WHERE id = " . VT_Sanitize::int($rsvp_data['existing_guest_id']));
        }

        // If no existing guest found by ID, check by event_id + email
        if (!$existing_guest) {
            $event_id = VT_Sanitize::int($rsvp_data['event_id']);
            $email = VT_Sanitize::email($rsvp_data['email']);
            $existing_guest = $db->getRow("SELECT * FROM vt_guests WHERE event_id = $event_id AND email = '$email'");
        }

        if ($existing_guest) {
            // Update existing RSVP
            $update_data = [
                'name' => VT_Sanitize::textField($rsvp_data['name']),
                'status' => VT_Sanitize::textField($rsvp_data['status']),
                'dietary_restrictions' => VT_Sanitize::textField($rsvp_data['dietary'] ?? ''),
                'notes' => VT_Sanitize::textField($rsvp_data['notes'] ?? ''),
            ];

            $result = $db->update('guests', $update_data, ['id' => $existing_guest->id]);
            $guest_id = $existing_guest->id;
        } else {
            // Create new guest record with token for RSVPs
            $rsvp_token = VT_Security::generateToken();
            $invitation_source = VT_Sanitize::textField($rsvp_data['invitation_source'] ?? 'direct');

            $result = $db->insert('guests', [
                'event_id' => VT_Sanitize::int($rsvp_data['event_id']),
                'name' => VT_Sanitize::textField($rsvp_data['name']),
                'email' => VT_Sanitize::email($rsvp_data['email']),
                'status' => VT_Sanitize::textField($rsvp_data['status']),
                'dietary_restrictions' => VT_Sanitize::textField($rsvp_data['dietary'] ?? ''),
                'notes' => VT_Sanitize::textField($rsvp_data['notes'] ?? ''),
                'rsvp_token' => $rsvp_token,
                'invitation_source' => $invitation_source,
            ]);

            $guest_id = $result;
        }

        if ($result !== false) {
            // Send confirmation email
            $this->sendRsvpConfirmation($guest_id, $rsvp_data['event_id'], $rsvp_data['status']);

            // Update profile stats for confirmed RSVP
            if ($rsvp_data['status'] === 'confirmed' && class_exists('VT_Profile_Manager')) {
                $user_id = $this->getUserIdByEmail($rsvp_data['email']);
                if ($user_id) {
                    VT_Profile_Manager::incrementEventsAttended($user_id);
                }
            }

            return [
                'success' => true,
                'message' => $this->getRsvpSuccessMessage($rsvp_data['status']),
                'guest_id' => $guest_id,
            ];
        } else {
            return VT_Http::jsonError('Failed to process RSVP. Please try again.', 'rsvp_failed');
        }
    }

    public function getEventGuests($event_id, $status = null) {
        $db = VT_Database::getInstance();

        $query = "SELECT * FROM vt_guests WHERE event_id = " . VT_Sanitize::int($event_id);

        if ($status) {
            $query .= " AND status = '" . VT_Sanitize::textField($status) . "'";
        }

        $query .= " ORDER BY rsvp_date DESC";

        return $db->getResults($query);
    }

    public function getGuestStats($event_id) {
        $db = VT_Database::getInstance();

        $event_id = VT_Sanitize::int($event_id);
        $stats = $db->getRow("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined,
                SUM(CASE WHEN status = 'maybe' THEN 1 ELSE 0 END) as maybe,
                SUM(CASE WHEN status IN ('pending') THEN 1 ELSE 0 END) as pending
            FROM vt_guests WHERE event_id = $event_id
        ");

        return $stats ?: (object) [
            'total' => 0,
            'confirmed' => 0,
            'declined' => 0,
            'maybe' => 0,
            'pending' => 0,
        ];
    }

    public function sendInvitation($guest_id, $event_id) {
        $guest = $this->getGuest($guest_id);
        if (class_exists('VT_Event_Manager')) {
            $event_manager = new VT_Event_Manager();
            $event = $event_manager->getEvent($event_id);
        }

        if (!$guest || !$event) {
            return false;
        }

        $subject = sprintf('You\'re invited to %s', $event->title);

        $rsvp_link = VT_Http::getBaseUrl() . '/events/' . $event->slug . '?guest_email=' . $guest->email;

        $message = sprintf(
            "Hi %s,\n\nYou're invited to: %s\n\nWhen: %s\nWhere: %s\n\n%s\n\nPlease RSVP: %s\n\nBest regards,\n%s",
            $guest->name,
            $event->title,
            VT_Time::formatDateTime(strtotime($event->event_date)),
            $event->venue_info,
            strip_tags($event->description),
            $rsvp_link,
            $event->host_email ?: VT_Config::get('site_title')
        );

        return VT_Mail::send($guest->email, $subject, $message);
    }

    private function sendRsvpConfirmation($guest_id, $event_id, $status) {
        $guest = $this->getGuest($guest_id);
        if (class_exists('VT_Event_Manager')) {
            $event_manager = new VT_Event_Manager();
            $event = $event_manager->getEvent($event_id);
        }

        if (!$guest || !$event) {
            return false;
        }

        $subject = sprintf('RSVP Confirmation for %s', $event->title);

        $status_messages = [
            'confirmed' => 'Thank you for confirming! We\'re excited to see you there.',
            'declined' => 'Thank you for letting us know. We\'ll miss you!',
            'maybe' => 'Thank you for your response. Please confirm when you can.',
            'pending' => 'We received your RSVP. Please confirm when you can.',
        ];

        $message = sprintf(
            "Hi %s,\n\n%s\n\nEvent: %s\nDate: %s\nYour Status: %s\n\nBest regards,\n%s",
            $guest->name,
            $status_messages[$status] ?? '',
            $event->title,
            VT_Time::formatDateTime(strtotime($event->event_date)),
            ucfirst($status),
            $event->host_email ?: VT_Config::get('site_title')
        );

        return VT_Mail::send($guest->email, $subject, $message);
    }

    private function getGuest($guest_id) {
        $db = VT_Database::getInstance();
        return $db->getRow("SELECT * FROM vt_guests WHERE id = " . VT_Sanitize::int($guest_id));
    }

    private function getRsvpSuccessMessage($status) {
        $messages = [
            'confirmed' => 'Thank you for confirming! We\'re excited to see you.',
            'declined' => 'Thank you for letting us know.',
            'maybe' => 'Thank you! Please confirm when you can.',
            'pending' => 'RSVP received. Please confirm when possible.',
        ];

        return $messages[$status] ?? 'RSVP updated successfully.';
    }

    public function createRsvpInvitation($event_id, $email, $temporary_guest_id = '', $invitation_source = 'email') {
        $db = VT_Database::getInstance();

        // Generate secure token
        $rsvp_token = VT_Security::generateToken();

        if (empty($temporary_guest_id)) {
            $temporary_guest_id = VT_Security::generateToken();
        }

        // Check if invitation already exists
        $event_id = VT_Sanitize::int($event_id);
        $email = VT_Sanitize::email($email);
        $existing_guest = $db->getRow("SELECT * FROM vt_guests WHERE event_id = $event_id AND email = '$email'");

        if ($existing_guest) {
            // Update token and reset status to pending for existing guest
            $db->update('guests', [
                'rsvp_token' => $rsvp_token,
                'temporary_guest_id' => $temporary_guest_id,
                'status' => 'pending',
                'invitation_source' => $invitation_source,
            ], ['id' => $existing_guest->id]);
        } else {
            // Create new anonymous guest record
            $db->insert('guests', [
                'rsvp_token' => $rsvp_token,
                'temporary_guest_id' => $temporary_guest_id,
                'event_id' => $event_id,
                'email' => $email,
                'name' => '', // Will be filled during RSVP
                'status' => 'pending',
                'invitation_source' => $invitation_source,
            ]);
        }

        // Get event details for URL generation
        if (class_exists('VT_Event_Manager')) {
            $event_manager = new VT_Event_Manager();
            $event = $event_manager->getEvent($event_id);
        }

        $invitation_url = $event ?
            VT_Http::getBaseUrl() . '/events/' . $event->slug . '?token=' . $rsvp_token :
            VT_Http::getBaseUrl() . '/events/join?token=' . $rsvp_token;

        return [
            'token' => $rsvp_token,
            'url' => $invitation_url
        ];
    }

    public function processAnonymousRsvp($rsvp_token, $status, $guest_data = []) {
        $db = VT_Database::getInstance();

        // Validate status
        $valid_statuses = ['confirmed', 'declined', 'maybe'];
        if (!in_array($status, $valid_statuses)) {
            return VT_Http::jsonError('Invalid RSVP status', 'invalid_status');
        }

        // Find guest by token
        $guest = $db->getRow("SELECT * FROM vt_guests WHERE rsvp_token = '" . VT_Sanitize::textField($rsvp_token) . "'");

        if (!$guest) {
            return VT_Http::jsonError('Invalid or expired RSVP link', 'invalid_token');
        }

        // Update RSVP
        $update_data = [
            'status' => $status,
        ];

        // Add guest data if provided (name, dietary restrictions, etc.)
        if (!empty($guest_data['name'])) {
            $update_data['name'] = VT_Sanitize::textField($guest_data['name']);
        }
        if (!empty($guest_data['dietary'])) {
            $update_data['dietary_restrictions'] = VT_Sanitize::textField($guest_data['dietary']);
        }
        if (!empty($guest_data['notes'])) {
            $update_data['notes'] = VT_Sanitize::textField($guest_data['notes']);
        }

        $result = $db->update('guests', $update_data, ['id' => $guest->id]);

        if ($result === false) {
            return VT_Http::jsonError('Failed to update RSVP', 'update_failed');
        }

        return [
            'success' => true,
            'message' => $this->getRsvpSuccessMessage($status),
            'guest_id' => $guest->id,
            'event_id' => $guest->event_id,
        ];
    }

    public function getGuestByToken($rsvp_token) {
        $db = VT_Database::getInstance();
        return $db->getRow("SELECT * FROM {$db->prefix}guests WHERE rsvp_token = '" . VT_Sanitize::textField($rsvp_token) . "'");
    }

    public function convertGuestToUser($guest_id, $user_data) {
        $db = VT_Database::getInstance();
        $guest = $db->getRow("SELECT * FROM vt_guests WHERE id = " . VT_Sanitize::int($guest_id));

        if (!$guest) {
            return VT_Http::jsonError('Guest not found', 'guest_not_found');
        }

        // Check if user already exists with this email
        $existing_user = $db->getRow("SELECT * FROM vt_users WHERE email = '" . $guest->email . "'");
        if ($existing_user) {
            // Link existing user
            $user_id = $existing_user->id;
        } else {
            // Create new user account
            $user_id = VT_Auth::register(
                $guest->email,
                $guest->email,
                VT_Security::generateToken(16),
                $guest->name ?: $user_data['name']
            );

            if (!$user_id) {
                return VT_Http::jsonError('Failed to create user account', 'user_creation_failed');
            }
        }

        // Update guest record with converted user ID
        $db->update('guests', ['converted_user_id' => $user_id], ['id' => $guest_id]);

        // Create/update user profile with dietary preferences
        if (class_exists('VT_Profile_Manager')) {
            $profile_data = [
                'dietary_restrictions' => $guest->dietary_restrictions
            ];

            if (!empty($user_data)) {
                $profile_data = array_merge($profile_data, $user_data);
            }

            VT_Profile_Manager::updateProfile($user_id, $profile_data);
        }

        return $user_id;
    }

    public function sendRsvpInvitation($event_id, $email, $host_name = '', $personal_message = '') {
        // Create the invitation
        $invitation_data = $this->createRsvpInvitation($event_id, $email);
        $rsvp_token = $invitation_data['token'];
        $invitation_url = $invitation_data['url'];

        if (class_exists('VT_Event_Manager')) {
            $event_manager = new VT_Event_Manager();
            $event = $event_manager->getEvent($event_id);
        }

        if (!$event) {
            return false;
        }

        // Create quick RSVP URLs
        $rsvp_yes_url = $invitation_url . '&response=confirmed';
        $rsvp_maybe_url = $invitation_url . '&response=maybe';
        $rsvp_no_url = $invitation_url . '&response=declined';

        $event_date = VT_Time::formatDate(strtotime($event->event_date));
        $event_time = VT_Time::formatTime(strtotime($event->event_date));
        $event_day = VT_Time::format('l', strtotime($event->event_date));

        $site_name = VT_Config::get('site_title');
        $host_name = $host_name ?: $site_name;

        $subject = sprintf('You\'re invited: %s', $event->title);

        // Use email template system
        $variables = [
            'event_title' => $event->title,
            'event_description' => $event->description,
            'event_date' => $event->event_date,
            'venue_info' => $event->venue_info,
            'from_name' => $host_name,
            'custom_message' => $personal_message,
            'invitation_url' => $invitation_url,
            'site_name' => $site_name,
            'site_url' => VT_Mail::getSiteUrl(),
            'subject' => $subject
        ];

        $sent = VT_Mail::sendTemplate($email, 'invitation', $variables);

        // Always return the invitation data - email failure shouldn't break the invitation system
        return [
            'success' => true,
            'email_sent' => $sent,
            'token' => $rsvp_token,
            'url' => $invitation_url
        ];
    }


    private function getUserIdByEmail($email) {
        $db = VT_Database::getInstance();
        return $db->getVar("SELECT id FROM vt_users WHERE email = '" . VT_Sanitize::email($email) . "'");
    }
}