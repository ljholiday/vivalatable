<?php
/**
 * VT_Invitation_Service
 * Shared invitation service for communities and events
 * Handles common operations: validation, token generation, email sending
 */

class VT_Invitation_Service {

	private $db;

	public function __construct() {
		$this->db = VT_Database::getInstance();
	}

	/**
	 * Validate invitation data
	 * Common validation for both communities and events
	 *
	 * @param array $data Invitation data containing email, etc.
	 * @return true|VT_Error True if valid, VT_Error if invalid
	 */
	public function validateInvitationData($data) {
		// Handle both 'email' and 'invited_email' field names
		$email = $data['email'] ?? $data['invited_email'] ?? '';

		if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return new VT_Error('invalid_email', 'Valid email address required');
		}

		return true;
	}

	/**
	 * Generate invitation token
	 *
	 * @param int $length Token length (will be doubled due to hex encoding)
	 * @return string
	 */
	public function generateToken($length = 32) {
		return vt_service('security.service')->generateToken($length);
	}

	/**
	 * Send invitation email
	 * Generic email sending for both communities and events
	 *
	 * @param array $args Email parameters
	 * @return bool True if sent successfully
	 */
	public function sendInvitationEmail($args) {
		$defaults = array(
			'to_email' => '',
			'to_name' => '',
			'subject' => '',
			'body_html' => '',
			'body_text' => '',
		);

		$args = array_merge($defaults, $args);

		if (empty($args['to_email']) || empty($args['subject'])) {
			return false;
		}

		return VT_Mail::send(
			$args['to_email'],
			$args['subject'],
			$args['body_html']
		);
	}

	/**
	 * Build invitation URL
	 *
	 * @param string $entity_type 'community' or 'event'
	 * @param string $entity_slug Slug for URL
	 * @param string $token Invitation token
	 * @param array $extra_params Additional query parameters
	 * @return string
	 */
	public function buildInvitationUrl($entity_type, $entity_slug, $token, $extra_params = array()) {
		$base_url = VT_Http::getBaseUrl();

		if ($entity_type === 'community') {
			$url = "{$base_url}/communities/{$entity_slug}";
			$params = array_merge(array('invitation' => $token), $extra_params);
		} elseif ($entity_type === 'event') {
			$url = "{$base_url}/events/{$entity_slug}";
			$params = array_merge(array('rsvp' => $token), $extra_params);
		} else {
			return '';
		}

		// Build query string
		if (!empty($params)) {
			$query_string = http_build_query($params);
			$url .= '?' . $query_string;
		}

		return $url;
	}

	/**
	 * Check if email has already been invited
	 *
	 * @param string $entity_type 'community' or 'event'
	 * @param int $entity_id Community or event ID
	 * @param string $email Email to check
	 * @return bool True if already invited
	 */
	public function isAlreadyInvited($entity_type, $entity_id, $email) {
		$email = vt_service('validation.sanitizer')->email($email);

		if ($entity_type === 'community') {
			$existing = $this->db->getVar(
				$this->db->prepare(
					"SELECT id FROM {$this->db->prefix}community_invitations
					WHERE community_id = %d AND invited_email = %s AND status = 'pending'",
					$entity_id, $email
				)
			);
		} elseif ($entity_type === 'event') {
			$existing = $this->db->getRow(
				$this->db->prepare(
					"SELECT id FROM {$this->db->prefix}guests
					WHERE event_id = %d AND email = %s AND status != 'declined'",
					$entity_id, $email
				)
			);
		} else {
			return false;
		}

		return !empty($existing);
	}

	/**
	 * Get email template wrapper
	 * Common HTML structure for invitation emails
	 *
	 * @param string $content HTML content to wrap
	 * @return string
	 */
	public function getEmailTemplate($content) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<?php echo $content; ?>

				<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0; color: #666; font-size: 14px;">
					<p>This invitation was sent from VivalaTable. If you believe you received this in error, please disregard this message.</p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Format invitation response for REST API
	 *
	 * @param bool $success Whether operation succeeded
	 * @param string $message Response message
	 * @param array $data Additional data
	 * @return array
	 */
	public function formatResponse($success, $message, $data = array()) {
		$response = array(
			'success' => $success,
			'message' => $message
		);

		if (!empty($data)) {
			$response = array_merge($response, $data);
		}

		return $response;
	}

	/**
	 * Sanitize invitation message
	 *
	 * @param string $message Raw message
	 * @return string Sanitized message
	 */
	public function sanitizeMessage($message) {
		return vt_service('validation.sanitizer')->textarea($message);
	}
}
