<?php
/**
 * VivalaTable Guest Manager
 * Handles guest invitations, anonymous RSVP, and guest-to-user conversion
 * Ported from PartyMinder WordPress plugin - maintains 32-character token system
 */

class VT_Guest_Manager {

	private $db;

	public function __construct() {
		$this->db = VT_Database::getInstance();
	}

	/**
	 * Create RSVP invitation for a guest
	 * Generates 32-character token exactly like PartyMinder
	 */
	public function createRsvpInvitation($event_id, $email, $temporary_guest_id = '', $invitation_source = 'email') {
		$event_id = intval($event_id);
		if (!$event_id) {
			return new VT_Error('invalid_event', 'Invalid event ID');
		}

		$invitation_service = vt_service('invitation.service');

		// Validate email using shared service
		$validation = $invitation_service->validateInvitationData(array('email' => $email));
		if (is_vt_error($validation)) {
			return $validation;
		}

		// Generate tokens using shared service
		$rsvp_token = $invitation_service->generateToken(32);

		if (empty($temporary_guest_id)) {
			$temporary_guest_id = $invitation_service->generateToken(32);
		}

		// Check if guest already exists for this event
		$existing_guest = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}guests WHERE event_id = %d AND email = %s",
				$event_id, vt_service('validation.sanitizer')->email($email)
			)
		);

		if ($existing_guest) {
			// Update existing guest with new token
			$result = $this->db->update(
				'guests',
				array(
					'rsvp_token' => $rsvp_token,
					'temporary_guest_id' => $temporary_guest_id,
					'status' => 'pending'
				),
				array('id' => $existing_guest->id)
			);

			if ($result === false) {
				return new VT_Error('update_failed', 'Failed to update existing guest invitation');
			}

			$guest_id = $existing_guest->id;
		} else {
			// Create new guest
			$guest_data = array(
				'event_id' => $event_id,
				'email' => vt_service('validation.sanitizer')->email($email),
				'name' => '', // Will be filled when they RSVP
				'rsvp_token' => $rsvp_token,
				'temporary_guest_id' => $temporary_guest_id,
				'status' => 'pending',
				'invitation_source' => vt_service('validation.sanitizer')->textField($invitation_source)
			);

			$guest_id = $this->db->insert('guests', $guest_data);

			if (!$guest_id) {
				return new VT_Error('insert_failed', 'Failed to create guest invitation');
			}
		}

		// Send invitation email
		$email_result = $this->sendRSVPInvitation($event_id, $email, $rsvp_token);

		if (is_vt_error($email_result)) {
			return $email_result;
		}

		return array(
			'guest_id' => $guest_id,
			'token' => $rsvp_token,
			'temporary_guest_id' => $temporary_guest_id,
			'email_sent' => $email_result
		);
	}

	/**
	 * Process anonymous RSVP submission
	 * Core functionality for guest RSVP without registration
	 */
	public function processAnonymousRsvp($rsvp_token, $status, $guest_data = array()) {
		if (empty($rsvp_token) || strlen($rsvp_token) !== 32) {
			return new VT_Error('invalid_token', 'Invalid RSVP token');
		}

		if (!in_array($status, array('yes', 'no', 'maybe'))) {
			return new VT_Error('invalid_status', 'Invalid RSVP status');
		}

		// Find guest by token
		$guest = $this->db->getRow(
			$this->db->prepare(
				"SELECT g.*, e.title as event_title, e.slug as event_slug, e.event_date, e.guest_limit
				 FROM {$this->db->prefix}guests g
				 JOIN {$this->db->prefix}events e ON g.event_id = e.id
				 WHERE g.rsvp_token = %s",
				$rsvp_token
			)
		);

		if (!$guest) {
			return new VT_Error('guest_not_found', 'RSVP invitation not found');
		}

		// Check guest limit if saying yes
		if ($status === 'yes' && $guest->guest_limit > 0) {
			$current_yes_count = $this->getGuestCount($guest->event_id, 'yes');
			if ($current_yes_count >= $guest->guest_limit) {
				return new VT_Error('event_full', 'This event has reached its guest limit');
			}
		}

		// Sanitize guest data
		$sanitized_data = array(
			'status' => $status,
			'name' => vt_service('validation.sanitizer')->textField($guest_data['name'] ?? ''),
			'phone' => vt_service('validation.sanitizer')->textField($guest_data['phone'] ?? ''),
			'dietary_restrictions' => vt_service('validation.sanitizer')->textField($guest_data['dietary_restrictions'] ?? ''),
			'plus_one' => intval($guest_data['plus_one'] ?? 0),
			'plus_one_name' => vt_service('validation.sanitizer')->textField($guest_data['plus_one_name'] ?? ''),
			'notes' => vt_service('validation.sanitizer')->textField($guest_data['notes'] ?? ''),
			'rsvp_date' => VT_Time::currentTime('mysql'),
			'updated_at' => VT_Time::currentTime('mysql')
		);

		// Clear plus one data if not bringing one
		if ($sanitized_data['plus_one'] <= 0) {
			$sanitized_data['plus_one'] = 0;
			$sanitized_data['plus_one_name'] = '';
		}

		$result = $this->db->update('guests', $sanitized_data, array('id' => $guest->id));

		if ($result === false) {
			return new VT_Error('update_failed', 'Failed to save RSVP response');
		}

		// Clear any cached data
		VT_Cache::delete('event_guests_' . $guest->event_id);

		// Send confirmation email
		$this->sendRSVPConfirmation($guest->id, $status);

		return array(
			'guest_id' => $guest->id,
			'status' => $status,
			'event_title' => $guest->event_title,
			'event_slug' => $guest->event_slug
		);
	}

	/**
	 * Get guest by RSVP token
	 */
	public function getGuestByToken($rsvp_token) {
		if (empty($rsvp_token) || strlen($rsvp_token) !== 32) {
			return null;
		}

		return $this->db->getRow(
			$this->db->prepare(
				"SELECT g.*, e.title as event_title, e.slug as event_slug, e.event_date,
				        e.event_time, e.venue_info, e.description, e.host_email, e.featured_image
				 FROM {$this->db->prefix}guests g
				 JOIN {$this->db->prefix}events e ON g.event_id = e.id
				 WHERE g.rsvp_token = %s",
				$rsvp_token
			)
		);
	}

	/**
	 * Get guest by temporary guest ID
	 */
	public function getGuestByTemporaryId($temporary_guest_id) {
		if (empty($temporary_guest_id) || strlen($temporary_guest_id) !== 32) {
			return null;
		}

		return $this->db->getRow(
			$this->db->prepare(
				"SELECT g.*, e.title as event_title, e.slug as event_slug
				 FROM {$this->db->prefix}guests g
				 JOIN {$this->db->prefix}events e ON g.event_id = e.id
				 WHERE g.temporary_guest_id = %s",
				$temporary_guest_id
			)
		);
	}

	/**
	 * Convert guest to registered user
	 * Core feature for seamless guest-to-user conversion
	 */
	public function convertGuestToUser($guest_id, $user_data) {
		$guest_id = intval($guest_id);
		if (!$guest_id) {
			return new VT_Error('invalid_guest', 'Invalid guest ID');
		}

		$guest = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}guests WHERE id = %d",
				$guest_id
			)
		);

		if (!$guest) {
			return new VT_Error('guest_not_found', 'Guest not found');
		}

		// Validate user data
		if (empty($user_data['username']) || empty($user_data['password'])) {
			return new VT_Error('invalid_user_data', 'Username and password are required');
		}

		// Check if user already exists with this email
		$existing_user = $this->db->getRow(
			$this->db->prepare(
				"SELECT id FROM {$this->db->prefix}users WHERE email = %s",
				$guest->email
			)
		);

		if ($existing_user) {
			return new VT_Error('user_exists', 'An account already exists with this email address');
		}

		// Create user account
		$user_manager = new VT_User_Manager();
		$user_id = $user_manager->createUser(
			vt_service('validation.sanitizer')->textField($user_data['username']),
			$guest->email,
			$user_data['password'],
			$guest->name ?: vt_service('validation.sanitizer')->textField($user_data['display_name'] ?? '')
		);

		if (is_vt_error($user_id)) {
			return $user_id;
		}

		// Update guest record with converted user ID
		$this->db->update(
			'guests',
			array(
				'converted_user_id' => $user_id,
				'updated_at' => VT_Time::currentTime('mysql')
			),
			array('id' => $guest_id)
		);

		// Log the user in immediately
		vt_service('auth.service')->loginById($user_id);

		return $user_id;
	}

	/**
	 * Get all guests for an event
	 */
	public function getEventGuests($event_id, $status = null) {
		$event_id = intval($event_id);
		if (!$event_id) {
			return array();
		}

		$where_conditions = array("g.event_id = %d");
		$where_values = array($event_id);

		if ($status) {
			$where_conditions[] = "g.status = %s";
			$where_values[] = $status;
		}

		return $this->db->getResults(
			$this->db->prepare(
				"SELECT g.*, u.username, u.display_name as user_display_name
				 FROM {$this->db->prefix}guests g
				 LEFT JOIN {$this->db->prefix}users u ON g.converted_user_id = u.id
				 WHERE " . implode(' AND ', $where_conditions) . "
				 ORDER BY g.rsvp_date DESC, g.id DESC",
				$where_values
			)
		);
	}

	/**
	 * Get guest statistics for an event
	 */
	public function getGuestStats($event_id) {
		$event_id = intval($event_id);
		if (!$event_id) {
			return array();
		}

		$cache_key = 'guest_stats_' . $event_id;
		$stats = VT_Cache::get($cache_key);

		if ($stats === false) {
			$stats = array(
				'total' => 0,
				'yes' => 0,
				'no' => 0,
				'maybe' => 0,
				'pending' => 0,
				'plus_ones' => 0
			);

			$results = $this->db->getResults(
				$this->db->prepare(
					"SELECT status, COUNT(*) as count, SUM(plus_one) as plus_ones_count
					 FROM {$this->db->prefix}guests
					 WHERE event_id = %d
					 GROUP BY status",
					$event_id
				)
			);

			foreach ($results as $result) {
				$stats[$result->status] = intval($result->count);
				$stats['total'] += intval($result->count);
				if ($result->status === 'yes') {
					$stats['plus_ones'] += intval($result->plus_ones_count ?? 0);
				}
			}

			// Cache for 5 minutes
			VT_Cache::set($cache_key, $stats, 300);
		}

		return $stats;
	}

	/**
	 * Get guest count for specific status
	 */
	public function getGuestCount($event_id, $status = null) {
		$event_id = intval($event_id);
		if (!$event_id) {
			return 0;
		}

		if ($status) {
			return intval($this->db->getVar(
				$this->db->prepare(
					"SELECT COUNT(*) FROM {$this->db->prefix}guests WHERE event_id = %d AND status = %s",
					$event_id, $status
				)
			));
		} else {
			return intval($this->db->getVar(
				$this->db->prepare(
					"SELECT COUNT(*) FROM {$this->db->prefix}guests WHERE event_id = %d",
					$event_id
				)
			));
		}
	}

	/**
	 * Send RSVP invitation email
	 */
	private function sendRSVPInvitation($event_id, $email, $rsvp_token) {
		$invitation_service = vt_service('invitation.service');

		$event = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}events WHERE id = %d",
				$event_id
			)
		);

		if (!$event) {
			return new VT_Error('event_not_found', 'Event not found for invitation');
		}

		// Get host information
		$host = $this->db->getRow(
			$this->db->prepare(
				"SELECT u.display_name, up.display_name as profile_display_name
				 FROM {$this->db->prefix}users u
				 LEFT JOIN {$this->db->prefix}user_profiles up ON u.id = up.user_id
				 WHERE u.id = %d",
				$event->author_id
			)
		);

		$host_name = $host ? ($host->profile_display_name ?: $host->display_name) : 'Event Host';

		$rsvp_url = $invitation_service->buildInvitationUrl('event', $event->slug, $rsvp_token, array('token' => $rsvp_token));

		$subject = sprintf('%s invited you to %s', $host_name, $event->title);

		$body_html = $invitation_service->getEmailTemplate($this->getRSVPInvitationTemplate($event, $host_name, $rsvp_url));

		return $invitation_service->sendInvitationEmail(array(
			'to_email' => $email,
			'subject' => $subject,
			'body_html' => $body_html
		));
	}

	/**
	 * Get RSVP invitation email template
	 */
	private function getRSVPInvitationTemplate($event, $host_name, $rsvp_url) {
		ob_start();
		?>
		<html>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #2c5aa0;">You're invited to <?php echo vt_service('validation.validator')->escHtml($event->title); ?></h2>

				<p>Hi there!</p>

				<p><?php echo vt_service('validation.validator')->escHtml($host_name); ?> has invited you to join their event.</p>

				<div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
					<h3 style="margin-top: 0; color: #2c5aa0;"><?php echo vt_service('validation.validator')->escHtml($event->title); ?></h3>

					<p><strong>📅 Date:</strong> <?php echo date('l, F j, Y', strtotime($event->event_date)); ?></p>

					<?php if ($event->event_time): ?>
					<p><strong>🕐 Time:</strong> <?php echo vt_service('validation.validator')->escHtml($event->event_time); ?></p>
					<?php endif; ?>

					<?php if ($event->venue_info): ?>
					<p><strong>📍 Location:</strong> <?php echo vt_service('validation.validator')->escHtml($event->venue_info); ?></p>
					<?php endif; ?>

					<?php if ($event->description): ?>
					<div style="margin-top: 15px;">
						<strong>About this event:</strong>
						<p><?php echo nl2br(vt_service('validation.validator')->escHtml(substr($event->description, 0, 300))); ?>
						<?php if (strlen($event->description) > 300): ?>...<?php endif; ?></p>
					</div>
					<?php endif; ?>
				</div>

				<div style="text-align: center; margin: 30px 0;">
					<a href="<?php echo vt_service('validation.validator')->escUrl($rsvp_url); ?>"
					   style="background: #2c5aa0; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
						RSVP Now
					</a>
				</div>

				<p style="font-size: 14px; color: #666;">
					Can't click the button? Copy and paste this link into your browser:<br>
					<?php echo vt_service('validation.validator')->escUrl($rsvp_url); ?>
				</p>

				<hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

				<p style="font-size: 12px; color: #999;">
					This invitation was sent by <?php echo vt_service('validation.validator')->escHtml($host_name); ?> through VivalaTable.
					If you have questions about this event, please contact the host directly.
				</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Send RSVP confirmation email
	 */
	private function sendRSVPConfirmation($guest_id, $status) {
		$guest = $this->db->getRow(
			$this->db->prepare(
				"SELECT g.*, e.title as event_title, e.slug as event_slug, e.event_date,
				        e.event_time, e.venue_info, e.host_email
				 FROM {$this->db->prefix}guests g
				 JOIN {$this->db->prefix}events e ON g.event_id = e.id
				 WHERE g.id = %d",
				$guest_id
			)
		);

		if (!$guest) {
			return false;
		}

		$status_messages = array(
			'yes' => 'confirmed your attendance',
			'no' => 'declined the invitation',
			'maybe' => 'marked yourself as maybe attending'
		);

		$subject = sprintf('RSVP Confirmation: %s', $guest->event_title);

		$message = $this->getRSVPConfirmationTemplate($guest, $status, $status_messages[$status]);

		return VT_Mail::send($guest->email, $subject, $message);
	}

	/**
	 * Get RSVP confirmation email template
	 */
	private function getRSVPConfirmationTemplate($guest, $status, $status_message) {
		$event_url = VT_Config::get('site_url') . '/events/' . $guest->event_slug;

		ob_start();
		?>
		<html>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #2c5aa0;">RSVP Confirmation</h2>

				<p>Hi <?php echo vt_service('validation.validator')->escHtml($guest->name ?: 'there'); ?>!</p>

				<p>Thank you for your RSVP. You have <strong><?php echo $status_message; ?></strong> for:</p>

				<div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
					<h3 style="margin-top: 0; color: #2c5aa0;"><?php echo vt_service('validation.validator')->escHtml($guest->event_title); ?></h3>

					<p><strong>📅 Date:</strong> <?php echo date('l, F j, Y', strtotime($guest->event_date)); ?></p>

					<?php if ($guest->event_time): ?>
					<p><strong>🕐 Time:</strong> <?php echo vt_service('validation.validator')->escHtml($guest->event_time); ?></p>
					<?php endif; ?>

					<?php if ($guest->venue_info): ?>
					<p><strong>📍 Location:</strong> <?php echo vt_service('validation.validator')->escHtml($guest->venue_info); ?></p>
					<?php endif; ?>

					<p><strong>Your RSVP Status:</strong>
						<span style="text-transform: uppercase; font-weight: bold; color: <?php echo $status === 'yes' ? '#28a745' : ($status === 'no' ? '#dc3545' : '#ffc107'); ?>;">
							<?php echo $status; ?>
						</span>
					</p>

					<?php if ($guest->plus_one > 0): ?>
					<p><strong>Plus One:</strong> <?php echo vt_service('validation.validator')->escHtml($guest->plus_one_name ?: 'Yes'); ?></p>
					<?php endif; ?>
				</div>

				<?php if ($status === 'yes'): ?>
				<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; margin: 20px 0;">
					<p style="margin: 0; color: #155724;">
						<strong>Great!</strong> We're excited to see you at the event.
						<?php if ($guest->host_email): ?>
						If you have any questions, feel free to contact the host at <?php echo vt_service('validation.validator')->escHtml($guest->host_email); ?>.
						<?php endif; ?>
					</p>
				</div>
				<?php endif; ?>

				<div style="text-align: center; margin: 30px 0;">
					<a href="<?php echo vt_service('validation.validator')->escUrl($event_url); ?>"
					   style="background: #2c5aa0; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;">
						View Event Details
					</a>
				</div>

				<p style="font-size: 14px; color: #666;">
					Need to change your RSVP? Use the original invitation link or contact the event host.
				</p>

				<hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

				<p style="font-size: 12px; color: #999;">
					This confirmation was sent by VivalaTable.
					Event details are subject to change - please check the event page for updates.
				</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Delete guest (admin function)
	 */
	public function deleteGuest($guest_id) {
		$guest_id = intval($guest_id);
		if (!$guest_id) {
			return new VT_Error('invalid_guest', 'Invalid guest ID');
		}

		$guest = $this->db->getRow(
			$this->db->prepare(
				"SELECT event_id FROM {$this->db->prefix}guests WHERE id = %d",
				$guest_id
			)
		);

		if (!$guest) {
			return new VT_Error('guest_not_found', 'Guest not found');
		}

		$result = $this->db->delete('guests', array('id' => $guest_id));

		if ($result === false) {
			return new VT_Error('delete_failed', 'Failed to delete guest');
		}

		// Clear cache
		VT_Cache::delete('guest_stats_' . $guest->event_id);
		VT_Cache::delete('event_guests_' . $guest->event_id);

		return true;
	}

	/**
	 * Resend invitation to a guest
	 */
	public function resendInvitation($guest_id) {
		$guest_id = intval($guest_id);
		if (!$guest_id) {
			return new VT_Error('invalid_guest', 'Invalid guest ID');
		}

		$guest = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}guests WHERE id = %d",
				$guest_id
			)
		);

		if (!$guest) {
			return new VT_Error('guest_not_found', 'Guest not found');
		}

		// Generate new token
		$new_token = vt_service('security.service')->generateToken(32);

		// Update guest with new token
		$this->db->update(
			'guests',
			array(
				'rsvp_token' => $new_token,
				'updated_at' => VT_Time::currentTime('mysql')
			),
			array('id' => $guest_id)
		);

		// Send new invitation
		return $this->sendRSVPInvitation($guest->event_id, $guest->email, $new_token);
	}
}