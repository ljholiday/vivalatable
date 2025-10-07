<?php
/**
 * VivalaTable Event AJAX Handler
 * Handles all AJAX requests for event operations
 * Ported from PartyMinder WordPress plugin
 */

class VT_Event_Ajax_Handler {

	private $event_manager;

	public function __construct() {
		$this->initRoutes();
	}

	/**
	 * Initialize AJAX routes for VivalaTable
	 */
	private function initRoutes() {
		// Register AJAX endpoints with VT_Ajax system
		VT_Ajax::register('create_event', array($this, 'ajax_create_event'));
		VT_Ajax::register('create_community_event', array($this, 'ajax_create_community_event'));
		VT_Ajax::register('update_event', array($this, 'ajax_update_event'));
		VT_Ajax::register('get_event_conversations', array($this, 'ajax_get_event_conversations'));
		VT_Ajax::register('send_event_invitation', array($this, 'ajax_send_event_invitation'));
		VT_Ajax::register('get_event_invitations', array($this, 'ajax_get_event_invitations'));
		VT_Ajax::register('cancel_event_invitation', array($this, 'ajax_cancel_event_invitation'));
		VT_Ajax::register('get_event_stats', array($this, 'ajax_get_event_stats'));
		VT_Ajax::register('get_event_guests', array($this, 'ajax_get_event_guests'));
		VT_Ajax::register('delete_event', array($this, 'ajax_delete_event'));
		VT_Ajax::register('admin_delete_event', array($this, 'ajax_admin_delete_event'));
	}

	private function getEventManager() {
		if (!$this->event_manager) {
			$this->event_manager = new VT_Event_Manager();
		}
		return $this->event_manager;
	}

	public function ajaxCreateEvent() {
		vt_service('security.service')->verifyNonce('create_vt_event', 'vt_event_nonce');

		$form_errors = array();

		// Load form handler if it exists
		if (class_exists('VT_Event_Form_Handler')) {
			// Validate form data
			$form_errors = VT_Event_Form_Handler::validateEventForm($_POST);

			if (!empty($form_errors)) {
				VT_Ajax::sendError(implode(' ', $form_errors));
			}

			$event_data = VT_Event_Form_Handler::processEventFormData($_POST);
		} else {
			// Basic validation
			if (empty($_POST['event_title'])) {
				$form_errors[] = 'Event title is required.';
			}
			if (empty($_POST['event_date'])) {
				$form_errors[] = 'Event date is required.';
			}
			if (empty($_POST['host_email'])) {
				$form_errors[] = 'Host email is required.';
			}

			if (!empty($form_errors)) {
				VT_Ajax::sendError(implode(' ', $form_errors));
			}

			$event_data = array(
				'title' => vt_service('validation.sanitizer')->textField($_POST['event_title']),
				'description' => vt_service('validation.sanitizer')->richText($_POST['event_description'] ?? ''),
				'event_date' => vt_service('validation.sanitizer')->textField($_POST['event_date']),
				'venue' => vt_service('validation.sanitizer')->textField($_POST['venue_info'] ?? ''),
				'guest_limit' => intval($_POST['guest_limit'] ?? 0),
				'host_email' => vt_service('validation.sanitizer')->email($_POST['host_email']),
				'host_notes' => vt_service('validation.sanitizer')->richText($_POST['host_notes'] ?? ''),
				'privacy' => vt_service('validation.sanitizer')->textField($_POST['privacy'] ?? 'public'),
				'community_id' => intval($_POST['community_id'] ?? 0),
			);
		}

		$event_manager = $this->getEventManager();
		$event_id = $event_manager->createEvent($event_data);

		if (!is_vt_error($event_id)) {
			// Handle cover image upload
			if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
				$upload_result = $this->handleCoverImageUpload($_FILES['cover_image'], $event_id);
				if (is_vt_error($upload_result)) {
					// Log error but don't fail the event creation
					error_log('Cover image upload failed: ' . $upload_result->getErrorMessage());
				}
			}

			$created_event = $event_manager->getEvent($event_id);

			$creation_data = array(
				'event_id' => $event_id,
				'event_url' => VT_Config::get('site_url') . '/events/' . $created_event->slug,
				'event_title' => $created_event->title,
			);

			VT_Transient::set('vt_event_created_' . vt_service('auth.service')->getCurrentUserId(), $creation_data, 300);

			VT_Ajax::sendSuccess(array(
				'event_id' => $event_id,
				'message' => 'Event created successfully!',
				'event_url' => VT_Config::get('site_url') . '/events/' . $created_event->slug,
			));
		} else {
			VT_Ajax::sendError($event_id->getErrorMessage());
		}
	}

	public function ajaxUpdateEvent() {
		vt_service('security.service')->verifyNonce('edit_vt_event', 'vt_edit_event_nonce');

		$event_id = intval($_POST['event_id']);
		if (!$event_id) {
			VT_Ajax::sendError('Event ID is required.');
		}

		$event_manager = $this->getEventManager();
		$event = $event_manager->getEvent($event_id);
		if (!$event) {
			VT_Ajax::sendError('Event not found.');
		}

		$current_user = vt_service('auth.service')->getCurrentUser();
		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		$can_edit = false;

		if (vt_service('auth.service')->currentUserCan('edit_posts') ||
			(vt_service('auth.service')->isLoggedIn() && $current_user_id == $event->author_id) ||
			($current_user->email == $event->host_email)) {
			$can_edit = true;
		}

		if (!$can_edit) {
			VT_Ajax::sendError('You do not have permission to edit this event.');
		}

		$form_errors = array();

		// Load form handler if it exists
		if (class_exists('VT_Event_Form_Handler')) {
			$form_errors = VT_Event_Form_Handler::validateEventForm($_POST);
			$event_data = VT_Event_Form_Handler::processEventFormData($_POST);
		} else {
			// Basic validation and processing
			if (empty($_POST['event_title'])) {
				$form_errors[] = 'Event title is required.';
			}
			if (empty($_POST['event_date'])) {
				$form_errors[] = 'Event date is required.';
			}
			if (empty($_POST['host_email'])) {
				$form_errors[] = 'Host email is required.';
			}

			$event_data = array(
				'title' => vt_service('validation.sanitizer')->textField($_POST['event_title']),
				'description' => vt_service('validation.sanitizer')->richText($_POST['event_description'] ?? ''),
				'event_date' => vt_service('validation.sanitizer')->textField($_POST['event_date']),
				'venue' => vt_service('validation.sanitizer')->textField($_POST['venue_info'] ?? ''),
				'guest_limit' => intval($_POST['guest_limit'] ?? 0),
				'host_email' => vt_service('validation.sanitizer')->email($_POST['host_email']),
				'host_notes' => vt_service('validation.sanitizer')->richText($_POST['host_notes'] ?? ''),
				'privacy' => vt_service('validation.sanitizer')->textField($_POST['privacy'] ?? 'public'),
			);
		}

		if (!empty($form_errors)) {
			VT_Ajax::sendError(implode(' ', $form_errors));
		}

		$result = $event_manager->updateEvent($event_id, $event_data);

		if ($result !== false) {
			// Handle cover image upload
			if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
				$upload_result = $this->handleCoverImageUpload($_FILES['cover_image'], $event_id);
				if (is_vt_error($upload_result)) {
					error_log('Cover image upload failed: ' . $upload_result->getErrorMessage());
				}
			}

			// Handle cover image removal
			if (isset($_POST['remove_cover_image']) && $_POST['remove_cover_image'] === '1') {
				$event_manager->updateEvent($event_id, array('featured_image' => ''));
			}

			$updated_event = $event_manager->getEvent($event_id);
			VT_Ajax::sendSuccess(array(
				'message' => 'Event updated successfully!',
				'event_url' => VT_Config::get('site_url') . '/events/' . $updated_event->slug,
			));
		} else {
			VT_Ajax::sendError('Failed to update event. Please try again.');
		}
	}

	public function ajaxGetEventConversations() {
		vt_service('security.service')->verifyNonce('vt_nonce', 'nonce');

		$event_id = intval($_POST['event_id']);
		if (!$event_id) {
			VT_Ajax::sendError('Event ID is required.');
		}

		$current_user = vt_service('auth.service')->getCurrentUser();
		$user_id = 0;

		if (vt_service('auth.service')->isLoggedIn()) {
			$user_email = $current_user->email;
			$user_name = $current_user->display_name;
			$user_id = vt_service('auth.service')->getCurrentUserId();
		} else {
			$user_email = vt_service('validation.sanitizer')->email($_POST['guest_email'] ?? '');
			$user_name = vt_service('validation.sanitizer')->textField($_POST['guest_name'] ?? '');

			if (empty($user_email) || empty($user_name)) {
				VT_Ajax::sendError('Email and name are required for guest access.');
			}
		}

		$conversation_manager = new VT_Conversation_Manager();
		$conversations = $conversation_manager->getEventConversations($event_id);

		VT_Ajax::sendSuccess(array(
			'conversations' => $conversations,
			'user_email' => $user_email,
			'user_name' => $user_name,
			'user_id' => $user_id,
		));
	}

	public function ajaxSendEventInvitation() {
		vt_service('security.service')->verifyNonce('vt_nonce', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$event_id = intval($_POST['event_id']);
		$email = vt_service('validation.sanitizer')->email($_POST['email']);
		$message = vt_service('validation.sanitizer')->textarea($_POST['message'] ?? '');

		if (!$event_id || !$email) {
			VT_Ajax::sendError('Event ID and email are required.');
		}

		$event_manager = $this->getEventManager();
		$event = $event_manager->getEvent($event_id);

		if (!$event) {
			VT_Ajax::sendError('Event not found.');
		}

		$current_user = vt_service('auth.service')->getCurrentUser();
		$current_user_id = vt_service('auth.service')->getCurrentUserId();

		if ($event->author_id != $current_user_id && !vt_service('auth.service')->currentUserCan('edit_others_posts')) {
			VT_Ajax::sendError('Only the event host can send invitations.');
		}

		// Check if guest already exists
		$guest_manager = new VT_Guest_Manager();
		$db = VT_Database::getInstance();

		$existing_guest = $db->getRow(
			$db->prepare(
				"SELECT * FROM {$db->prefix}guests WHERE event_id = %d AND email = %s",
				$event_id, $email
			)
		);

		if ($existing_guest && $existing_guest->status !== 'declined') {
			VT_Ajax::sendError('This email has already been invited.');
		}

		// Send RSVP invitation using Guest Manager
		$result = $guest_manager->sendRsvpInvitation(
			$event_id,
			$email,
			$current_user->display_name,
			$message
		);

		if ($result['success']) {
			$response_message = 'RSVP invitation created successfully!';
			if (!$result['email_sent']) {
				$response_message .= ' Note: Email delivery may have failed.';
			}

			VT_Ajax::sendSuccess(array(
				'message' => $response_message,
				'invitation_url' => $result['url']
			));
		} else {
			VT_Ajax::sendError('Failed to create invitation.');
		}
	}

	public function ajaxGetEventInvitations() {
		vt_service('security.service')->verifyNonce('vt_event_action', 'nonce');

		$event_id = intval($_POST['event_id']);
		if (!$event_id) {
			VT_Ajax::sendError('Event ID is required.');
		}

		$event_manager = $this->getEventManager();
		$event = $event_manager->getEvent($event_id);

		if (!$event) {
			VT_Ajax::sendError('Event not found.');
		}

		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		if ($event->author_id != $current_user_id && !vt_service('auth.service')->currentUserCan('edit_others_posts')) {
			VT_Ajax::sendError('Only the event host can view invitations.');
		}

		$db = VT_Database::getInstance();
		$guests = $db->getResults(
			$db->prepare(
				"SELECT * FROM {$db->prefix}guests
				WHERE event_id = %d
				ORDER BY rsvp_date DESC",
				$event_id
			)
		);

		// Add invitation URLs to each guest
		foreach ($guests as &$guest) {
			if (!empty($guest->rsvp_token)) {
				$guest->invitation_url = VT_Config::get('site_url') . '/events/' . $event->slug . '?token=' . $guest->rsvp_token;
			} else {
				$guest->invitation_url = VT_Config::get('site_url') . '/events/' . $event->slug . '?rsvp=1';
			}
		}
		unset($guest);

		// Generate HTML for invitations list
		$html = '';
		if (empty($guests)) {
			$html = '<div class="vt-text-center vt-text-muted">No RSVP invitations sent yet.</div>';
		} else {
			foreach ($guests as $guest) {
				$status_class = '';
				$status_text = '';
				switch ($guest->status) {
					case 'confirmed':
						$status_class = 'success';
						$status_text = 'Confirmed';
						break;
					case 'declined':
						$status_class = 'danger';
						$status_text = 'Declined';
						break;
					case 'maybe':
						$status_class = 'warning';
						$status_text = 'Maybe';
						break;
					default:
						$status_class = 'secondary';
						$status_text = 'Pending';
				}

				$html .= '<div class="vt-invitation-item">';
				$html .= '<div class="vt-invitation-badges">';
				$html .= '<span class="vt-badge vt-badge-' . $status_class . '">' . vt_service('validation.validator')->escHtml($status_text) . '</span>';

				$source = $guest->invitation_source ?? 'direct';
				$source_badges = array(
					'direct' => array('label' => 'Direct', 'class' => 'vt-badge-primary'),
					'email' => array('label' => 'Email', 'class' => 'vt-badge-secondary'),
					'bluesky' => array('label' => 'BlueSky', 'class' => 'vt-badge-info'),
				);
				$source_info = $source_badges[$source] ?? $source_badges['direct'];
				$html .= '<span class="vt-badge ' . $source_info['class'] . '">' . vt_service('validation.validator')->escHtml($source_info['label']) . '</span>';
				$html .= '</div>';

				$html .= '<div class="vt-invitation-details">';
				$html .= '<h4>' . vt_service('validation.validator')->escHtml($guest->email) . '</h4>';
				if (!empty($guest->name)) {
					$html .= '<div class="vt-text-muted">' . vt_service('validation.validator')->escHtml($guest->name) . '</div>';
				}
				$html .= '<div class="vt-text-muted">';
				$html .= sprintf('Invited on %s', date('M j, Y', strtotime($guest->rsvp_date)));
				$html .= '</div>';
				if (!empty($guest->dietary_restrictions)) {
					$html .= '<div class="vt-text-muted"><strong>Dietary:</strong> ' . vt_service('validation.validator')->escHtml($guest->dietary_restrictions) . '</div>';
				}
				if (!empty($guest->notes)) {
					$html .= '<div class="vt-text-muted"><em>"' . vt_service('validation.validator')->escHtml($guest->notes) . '"</em></div>';
				}
				$html .= '</div>';

				$html .= '<div class="vt-invitation-actions">';
				$html .= '<button type="button" class="vt-btn vt-btn-sm vt-btn-secondary" onclick="copyInvitationUrl(\'' . vt_service('validation.sanitizer')->escapeAttribute($guest->invitation_url) . '\')">Copy Link</button>';
				if ($guest->status === 'pending') {
					$html .= '<button type="button" class="vt-btn vt-btn-sm vt-btn-danger cancel-event-invitation" data-invitation-id="' . vt_service('validation.validator')->escAttr($guest->id) . '">Remove</button>';
				}
				$html .= '</div>';

				$html .= '</div>';
			}
		}

		VT_Ajax::sendSuccess(array(
			'invitations' => $guests,
			'html' => $html,
		));
	}

	public function ajaxCancelEventInvitation() {
		vt_service('security.service')->verifyNonce('vt_event_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$guest_id = intval($_POST['invitation_id']);
		if (!$guest_id) {
			VT_Ajax::sendError('Guest ID is required.');
		}

		$db = VT_Database::getInstance();
		$guest = $db->getRow(
			$db->prepare(
				"SELECT * FROM {$db->prefix}guests WHERE id = %d",
				$guest_id
			)
		);

		if (!$guest) {
			VT_Ajax::sendError('Guest invitation not found.');
		}

		$event_manager = $this->getEventManager();
		$event = $event_manager->getEvent($guest->event_id);

		if (!$event) {
			VT_Ajax::sendError('Event not found.');
		}

		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		if ($event->author_id != $current_user_id && !vt_service('auth.service')->currentUserCan('edit_others_posts')) {
			VT_Ajax::sendError('Only the event host can remove guests.');
		}

		$result = $db->delete('guests', array('id' => $guest_id));

		if ($result !== false) {
			VT_Ajax::sendSuccess(array(
				'message' => 'Guest removed successfully.',
			));
		} else {
			VT_Ajax::sendError('Failed to remove guest.');
		}
	}

	public function ajaxGetEventStats() {
		vt_service('security.service')->verifyNonce('vt_event_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$event_id = intval($_POST['event_id']);
		if (!$event_id) {
			VT_Ajax::sendError('Event ID is required.');
		}

		$event_manager = $this->getEventManager();
		$event = $event_manager->getEvent($event_id);

		if (!$event) {
			VT_Ajax::sendError('Event not found.');
		}

		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		if ($event->author_id != $current_user_id && !vt_service('auth.service')->currentUserCan('edit_others_posts')) {
			VT_Ajax::sendError('Only the event host can view statistics.');
		}

		$db = VT_Database::getInstance();

		$stats = array(
			'total_rsvps' => $db->getVar(
				$db->prepare(
					"SELECT COUNT(*) FROM {$db->prefix}guests WHERE event_id = %d",
					$event_id
				)
			),
			'attending' => $db->getVar(
				$db->prepare(
					"SELECT COUNT(*) FROM {$db->prefix}guests WHERE event_id = %d AND status = 'confirmed'",
					$event_id
				)
			),
			'not_attending' => $db->getVar(
				$db->prepare(
					"SELECT COUNT(*) FROM {$db->prefix}guests WHERE event_id = %d AND status = 'declined'",
					$event_id
				)
			),
			'maybe' => $db->getVar(
				$db->prepare(
					"SELECT COUNT(*) FROM {$db->prefix}guests WHERE event_id = %d AND status = 'maybe'",
					$event_id
				)
			),
			'invitations_sent' => $db->getVar(
				$db->prepare(
					"SELECT COUNT(*) FROM {$db->prefix}guests WHERE event_id = %d",
					$event_id
				)
			),
		);

		VT_Ajax::sendSuccess($stats);
	}

	public function ajaxGetEventGuests() {
		vt_service('security.service')->verifyNonce('vt_event_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$event_id = intval($_POST['event_id']);
		if (!$event_id) {
			VT_Ajax::sendError('Event ID is required.');
		}

		$event_manager = $this->getEventManager();
		$event = $event_manager->getEvent($event_id);

		if (!$event) {
			VT_Ajax::sendError('Event not found.');
		}

		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		if ($event->author_id != $current_user_id && !vt_service('auth.service')->currentUserCan('edit_others_posts')) {
			VT_Ajax::sendError('Only the event host can view the guest list.');
		}

		$db = VT_Database::getInstance();
		$guests = $db->getResults(
			$db->prepare(
				"SELECT * FROM {$db->prefix}guests WHERE event_id = %d ORDER BY rsvp_date DESC",
				$event_id
			)
		);

		VT_Ajax::sendSuccess(array(
			'guests' => $guests,
		));
	}

	public function ajaxDeleteEvent() {
		vt_service('security.service')->verifyNonce('vt_event_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$event_id = intval($_POST['event_id']);
		if (!$event_id) {
			VT_Ajax::sendError('Event ID is required.');
		}

		$event_manager = $this->getEventManager();
		$event = $event_manager->getEvent($event_id);

		if (!$event) {
			VT_Ajax::sendError('Event not found.');
		}

		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		if ($event->author_id != $current_user_id && !vt_service('auth.service')->currentUserCan('edit_others_posts')) {
			VT_Ajax::sendError('You do not have permission to delete this event.');
		}

		$result = $event_manager->deleteEvent($event_id);

		if ($result) {
			VT_Ajax::sendSuccess(array(
				'message' => 'Event deleted successfully.',
				'redirect_url' => VT_Config::get('site_url') . '/my-events',
			));
		} else {
			VT_Ajax::sendError('Failed to delete event.');
		}
	}

	public function ajaxAdminDeleteEvent() {
		vt_service('security.service')->verifyNonce('vt_event_action', 'nonce');

		if (!vt_service('auth.service')->currentUserCan('delete_others_posts')) {
			VT_Ajax::sendError('You do not have permission to delete events.');
		}

		$event_id = intval($_POST['event_id']);
		if (!$event_id) {
			VT_Ajax::sendError('Event ID is required.');
		}

		$event_manager = $this->getEventManager();
		$result = $event_manager->deleteEvent($event_id);

		if ($result) {
			VT_Ajax::sendSuccess(array(
				'message' => 'Event deleted successfully.',
			));
		} else {
			VT_Ajax::sendError('Failed to delete event.');
		}
	}

	public function ajaxCreateCommunityEvent() {
		vt_service('security.service')->verifyNonce('create_vt_community_event', 'vt_community_event_nonce');

		$form_errors = array();
		if (empty($_POST['event_title'])) {
			$form_errors[] = 'Event title is required.';
		}
		if (empty($_POST['event_date'])) {
			$form_errors[] = 'Event date is required.';
		}
		if (empty($_POST['host_email'])) {
			$form_errors[] = 'Host email is required.';
		}
		if (empty($_POST['community_id'])) {
			$form_errors[] = 'Community ID is required.';
		}

		if (!empty($form_errors)) {
			VT_Ajax::sendError(implode(' ', $form_errors));
		}

		// Verify user is a member of the community
		$community_manager = new VT_Community_Manager();
		$community_id = intval($_POST['community_id']);

		if (!$community_manager->isMember($community_id, vt_service('auth.service')->getCurrentUserId())) {
			VT_Ajax::sendError('You must be a member of the community to create events.');
		}

		$event_data = array(
			'title' => vt_service('validation.sanitizer')->textField($_POST['event_title']),
			'description' => vt_service('validation.sanitizer')->richText($_POST['event_description'] ?? ''),
			'event_date' => vt_service('validation.sanitizer')->textField($_POST['event_date']),
			'venue' => vt_service('validation.sanitizer')->textField($_POST['venue_info'] ?? ''),
			'guest_limit' => intval($_POST['guest_limit'] ?? 0),
			'host_email' => vt_service('validation.sanitizer')->email($_POST['host_email']),
			'host_notes' => vt_service('validation.sanitizer')->richText($_POST['host_notes'] ?? ''),
			'community_id' => $community_id,
			// Privacy will be inherited from community - no need to pass it
		);

		$event_manager = $this->getEventManager();
		$event_id = $event_manager->createEvent($event_data);

		if (!is_vt_error($event_id)) {
			$created_event = $event_manager->getEvent($event_id);

			$creation_data = array(
				'event_id' => $event_id,
				'event_url' => VT_Config::get('site_url') . '/events/' . $created_event->slug,
				'event_title' => $created_event->title,
			);

			VT_Transient::set('vt_community_event_created_' . vt_service('auth.service')->getCurrentUserId(), $creation_data, 300);

			VT_Ajax::sendSuccess(array(
				'event_id' => $event_id,
				'message' => 'Community event created successfully!',
				'event_url' => VT_Config::get('site_url') . '/events/' . $created_event->slug,
			));
		} else {
			VT_Ajax::sendError($event_id->getErrorMessage());
		}
	}

	private function handleCoverImageUpload($file, $event_id) {
		// Validate file
		if (class_exists('VT_Upload')) {
			$validation_result = VT_Upload::validateFile($file);
			if (is_vt_error($validation_result)) {
				return $validation_result;
			}

			$uploaded_file = VT_Upload::handleUpload($file);

			if ($uploaded_file && !isset($uploaded_file['error'])) {
				// Update event with the image URL
				$event_manager = $this->getEventManager();
				$event_manager->updateEvent($event_id, array('featured_image' => $uploaded_file['url']));
				return $uploaded_file;
			} else {
				return new VT_Error('upload_failed', 'File upload failed.');
			}
		} else {
			// Basic file handling without validation
			$upload_dir = VT_Config::get('upload_dir', '/uploads/');
			$filename = basename($file['name']);
			$target_path = $_SERVER['DOCUMENT_ROOT'] . $upload_dir . $filename;

			if (move_uploaded_file($file['tmp_name'], $target_path)) {
				$file_url = VT_Config::get('site_url') . $upload_dir . $filename;
				$event_manager = $this->getEventManager();
				$event_manager->updateEvent($event_id, array('featured_image' => $file_url));
				return array('url' => $file_url);
			} else {
				return new VT_Error('upload_failed', 'File upload failed.');
			}
		}
	}

	/**
	 * REST API handler for sending event invitations
	 */
	public static function handleSendInvitation($params) {
		if (!vt_service('auth.service')->isLoggedIn()) {
			http_response_code(401);
			echo json_encode(['success' => false, 'message' => 'You must be logged in']);
			exit;
		}

		$event_id = intval($params['id'] ?? 0);
		$email = vt_service('validation.sanitizer')->email($_POST['email'] ?? '');
		$message = vt_service('validation.sanitizer')->textarea($_POST['message'] ?? '');

		if (!$event_id || !$email) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Event ID and email are required']);
			exit;
		}

		$event_manager = new VT_Event_Manager();
		$event = $event_manager->getEvent($event_id);

		if (!$event) {
			http_response_code(404);
			echo json_encode(['success' => false, 'message' => 'Event not found']);
			exit;
		}

		$current_user = vt_service('auth.service')->getCurrentUser();
		$current_user_id = vt_service('auth.service')->getCurrentUserId();

		if ($event->author_id != $current_user_id) {
			http_response_code(403);
			echo json_encode(['success' => false, 'message' => 'Only the event host can send invitations']);
			exit;
		}

		$guest_manager = new VT_Guest_Manager();
		$db = VT_Database::getInstance();

		$existing_guest = $db->getRow(
			$db->prepare(
				"SELECT * FROM {$db->prefix}guests WHERE event_id = %d AND email = %s",
				$event_id, $email
			)
		);

		if ($existing_guest && $existing_guest->status !== 'declined') {
			http_response_code(409);
			echo json_encode(['success' => false, 'message' => 'This email has already been invited']);
			exit;
		}

		$result = $guest_manager->createRsvpInvitation($event_id, $email);

		if (is_vt_error($result)) {
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => $result->getErrorMessage()]);
			exit;
		}

		echo json_encode(['success' => true, 'message' => 'Invitation sent successfully!']);
		exit;
	}

	/**
	 * REST API handler for getting event invitations
	 */
	public static function handleGetInvitations($params) {
		if (!vt_service('auth.service')->isLoggedIn()) {
			http_response_code(401);
			echo json_encode(['success' => false, 'message' => 'You must be logged in']);
			exit;
		}

		$event_id = intval($params['id'] ?? 0);
		if (!$event_id) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Event ID is required']);
			exit;
		}

		$event_manager = new VT_Event_Manager();
		$event = $event_manager->getEvent($event_id);

		if (!$event) {
			http_response_code(404);
			echo json_encode(['success' => false, 'message' => 'Event not found']);
			exit;
		}

		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		if ($event->author_id != $current_user_id) {
			http_response_code(403);
			echo json_encode(['success' => false, 'message' => 'Only the event host can view invitations']);
			exit;
		}

		$db = VT_Database::getInstance();
		$guests = $db->getResults(
			$db->prepare(
				"SELECT * FROM {$db->prefix}guests
				WHERE event_id = %d
				ORDER BY rsvp_date DESC",
				$event_id
			)
		);

		foreach ($guests as &$guest) {
			if (!empty($guest->rsvp_token)) {
				$guest->invitation_url = VT_Config::get('site_url') . '/events/' . $event->slug . '?token=' . $guest->rsvp_token;
			} else {
				$guest->invitation_url = VT_Config::get('site_url') . '/events/' . $event->slug . '?rsvp=1';
			}
		}
		unset($guest);

		echo json_encode(['success' => true, 'invitations' => $guests]);
		exit;
	}

	/**
	 * REST API handler for canceling event invitations
	 */
	public static function handleCancelInvitation($params) {
		if (!vt_service('auth.service')->isLoggedIn()) {
			http_response_code(401);
			echo json_encode(['success' => false, 'message' => 'You must be logged in']);
			exit;
		}

		$event_id = intval($params['id'] ?? 0);
		$invitation_id = intval($params['invitation_id'] ?? 0);

		if (!$invitation_id) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Invitation ID is required']);
			exit;
		}

		$db = VT_Database::getInstance();
		$guest = $db->getRow(
			$db->prepare(
				"SELECT * FROM {$db->prefix}guests WHERE id = %d AND event_id = %d",
				$invitation_id, $event_id
			)
		);

		if (!$guest) {
			http_response_code(404);
			echo json_encode(['success' => false, 'message' => 'Guest invitation not found']);
			exit;
		}

		$event_manager = new VT_Event_Manager();
		$event = $event_manager->getEvent($event_id);

		if (!$event) {
			http_response_code(404);
			echo json_encode(['success' => false, 'message' => 'Event not found']);
			exit;
		}

		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		if ($event->author_id != $current_user_id) {
			http_response_code(403);
			echo json_encode(['success' => false, 'message' => 'Only the event host can remove guests']);
			exit;
		}

		$result = $db->delete('guests', ['id' => $invitation_id]);

		if ($result !== false) {
			echo json_encode(['success' => true, 'message' => 'Guest removed successfully']);
		} else {
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => 'Failed to remove guest']);
		}
		exit;
	}

	/**
	 * Generate HTML email template for event invitations
	 */
	private static function generateInvitationEmailHtml($data) {
		$event = $data['event'];
		$invitation_url = $data['invitation_url'];
		$rsvp_yes_url = $data['rsvp_yes_url'];
		$rsvp_maybe_url = $data['rsvp_maybe_url'];
		$rsvp_no_url = $data['rsvp_no_url'];
		$host_name = $data['host_name'];
		$personal_message = $data['personal_message'];
		$invited_email = $data['invited_email'];

		$event_date = date('F j, Y', strtotime($event->event_date));
		$event_time = date('g:i A', strtotime($event->event_date));
		$event_day = date('l', strtotime($event->event_date));

		$site_name = VT_Config::get('site_name', 'VivalaTable');
		$site_url = VT_Config::get('site_url');

		// Create inline CSS for better email client compatibility
		$styles = array(
			'container' => 'max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; line-height: 1.6; color: #333;',
			'header' => 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center;',
			'body' => 'background: #ffffff; padding: 30px 20px;',
			'event_card' => 'background: #f8f9ff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;',
			'btn_primary' => 'display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 8px 8px 8px 0;',
			'btn_secondary' => 'display: inline-block; background: #e2e8f0; color: #4a5568; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 8px 8px 8px 0;',
			'btn_danger' => 'display: inline-block; background: #f56565; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 8px 8px 8px 0;',
			'footer' => 'background: #f7fafc; color: #718096; padding: 20px; text-align: center; font-size: 12px;',
		);

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo vt_service('validation.validator')->escHtml($event->title); ?> - Event Invitation</title>
</head>
<body>
	<div>
		<!-- Header -->
		<div>
			<h1>You're Invited!</h1>
			<p><?php echo vt_service('validation.validator')->escHtml($event->title); ?></p>
		</div>

		<!-- Main Content -->
		<div>
			<p>Hi there!</p>

			<p><strong><?php echo vt_service('validation.validator')->escHtml($host_name); ?></strong> has invited you to their event. Here are all the details:</p>

			<!-- Event Details Card -->
			<div>
				<h2><?php echo vt_service('validation.validator')->escHtml($event->title); ?></h2>

				<div>
					<p><strong>When:</strong> <?php echo $event_day; ?>, <?php echo $event_date; ?> at <?php echo $event_time; ?></p>
					<?php if ($event->venue_info) : ?>
					<p><strong>Where:</strong> <?php echo vt_service('validation.validator')->escHtml($event->venue_info); ?></p>
					<?php endif; ?>
					<?php if ($event->description) : ?>
					<p><strong>Details:</strong></p>
					<p><?php echo nl2br(vt_service('validation.validator')->escHtml($event->description)); ?></p>
					<?php endif; ?>
				</div>

				<?php if ($personal_message) : ?>
				<div>
					<p><strong>Personal message from <?php echo vt_service('validation.validator')->escHtml($host_name); ?>:</strong></p>
					<p>"<?php echo vt_service('validation.validator')->escHtml($personal_message); ?>"</p>
				</div>
				<?php endif; ?>
			</div>

			<!-- RSVP Buttons -->
			<div>
				<p>Can you make it?</p>
				<div>
					<a href="<?php echo vt_service('validation.validator')->escUrl($rsvp_yes_url); ?>">
						Yes, I'll be there!
					</a>
					<a href="<?php echo vt_service('validation.validator')->escUrl($rsvp_maybe_url); ?>">
						Maybe
					</a>
					<a href="<?php echo vt_service('validation.validator')->escUrl($rsvp_no_url); ?>">
						Can't make it
					</a>
				</div>
				<p>
					Or <a href="<?php echo vt_service('validation.validator')->escUrl($invitation_url); ?>">click here to RSVP with more details</a>
				</p>
			</div>

			<!-- Host Contact -->
			<div>
				<p>
					Questions about the event? Just reply to this email to reach <?php echo vt_service('validation.validator')->escHtml($host_name); ?> directly.
				</p>
			</div>
		</div>

		<!-- Footer -->
		<div>
			<p>This invitation was sent through <a href="<?php echo vt_service('validation.validator')->escUrl($site_url); ?>"><?php echo vt_service('validation.validator')->escHtml($site_name); ?></a></p>
			<p>If you can't click the buttons above, copy and paste this link: <br><?php echo vt_service('validation.validator')->escUrl($invitation_url); ?></p>
		</div>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}