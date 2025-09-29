<?php
/**
 * VivalaTable Community AJAX Handler
 * Handles all AJAX requests for community operations
 * Ported from PartyMinder WordPress plugin
 */

class VT_Community_Ajax_Handler {

	private $community_manager;

	public function __construct() {
		$this->initRoutes();
	}

	/**
	 * Initialize AJAX routes for VivalaTable
	 */
	private function initRoutes() {
		// Register AJAX endpoints with VT_Ajax system
		VT_Ajax::register('join_community', array($this, 'ajax_join_community'));
		VT_Ajax::register('leave_community', array($this, 'ajax_leave_community'));
		VT_Ajax::register('create_community', array($this, 'ajax_create_community'));
		VT_Ajax::register('update_community', array($this, 'ajax_update_community'));
		VT_Ajax::register('get_community_members', array($this, 'ajax_get_community_members'));
		VT_Ajax::register('update_member_role', array($this, 'ajax_update_member_role'));
		VT_Ajax::register('remove_member', array($this, 'ajax_remove_member'));
		VT_Ajax::register('send_invitation', array($this, 'ajax_send_invitation'));
		VT_Ajax::register('get_community_invitations', array($this, 'ajax_get_community_invitations'));
		VT_Ajax::register('cancel_invitation', array($this, 'ajax_cancel_invitation'));
		VT_Ajax::register('accept_invitation', array($this, 'ajax_accept_invitation'));
		VT_Ajax::register('load_community_invitation_form', array($this, 'ajax_load_community_invitation_form'));
		VT_Ajax::register('accept_community_invitation', array($this, 'ajax_accept_community_invitation'));
		VT_Ajax::register('load_community_join_form', array($this, 'ajax_load_community_join_form'));
	}

	private function getCommunityManager() {
		if (!$this->community_manager) {
			$this->community_manager = new VT_Community_Manager();
		}
		return $this->community_manager;
	}

	/**
	 * Handle community join via REST API
	 */
	public static function handleJoin() {
		// Get community ID from URL path
		$path_parts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
		$community_id = 0;

		foreach ($path_parts as $index => $part) {
			if ($part === 'communities' && isset($path_parts[$index + 1])) {
				$community_id = intval($path_parts[$index + 1]);
				break;
			}
		}

		if (!$community_id) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'Invalid community ID']);
			exit;
		}

		// Add community_id to POST for compatibility with existing method
		$_POST['community_id'] = $community_id;

		// Call existing join logic
		$handler = new self();
		$handler->ajaxJoinCommunity();
	}

	public function ajaxJoinCommunity() {
		// Check nonce from either source (direct AJAX or REST API)
		$nonce = $_POST['nonce'] ?? '';
		if (!vt_service('security.service')->verifyNonce($nonce, 'vt_nonce')) {
			VT_Ajax::sendError('Security check failed.');
			return;
		}

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in to join a community.');
		}

		$community_id = intval($_POST['community_id']);
		$current_user = vt_service('auth.service')->getCurrentUser();
		$current_user_id = vt_service('auth.service')->getCurrentUserId();

		if (!$community_id) {
			VT_Ajax::sendError('Invalid community.');
		}

		$community_manager = $this->getCommunityManager();
		$community = $community_manager->getCommunity($community_id);
		if (!$community) {
			VT_Ajax::sendError('Community not found.');
		}

		if ($community_manager->isMember($community_id, $current_user_id)) {
			VT_Ajax::sendError('You are already a member of this community.');
		}

		$member_data = array(
			'user_id' => $current_user_id,
			'email' => $current_user->email,
			'display_name' => $current_user->display_name,
			'role' => 'member',
			'status' => 'active',
		);

		$result = $community_manager->addMember($community_id, $member_data, true);

		if (is_vt_error($result)) {
			VT_Ajax::sendError($result->getErrorMessage());
		} else {
			VT_Ajax::sendSuccess(array(
				'message' => sprintf('Welcome to %s!', $community->name),
				'redirect_url' => VT_Config::get('site_url') . '/communities/' . $community->slug,
				'community_slug' => $community->slug
			));
		}
	}

	public function ajaxLeaveCommunity() {
		vt_service('security.service')->verifyNonce('vt_community_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$community_id = intval($_POST['community_id']);
		$current_user_id = vt_service('auth.service')->getCurrentUserId();

		if (!$community_id) {
			VT_Ajax::sendError('Invalid community.');
		}

		$community_manager = $this->getCommunityManager();

		if (!$community_manager->isMember($community_id, $current_user_id)) {
			VT_Ajax::sendError('You are not a member of this community.');
		}

		$user_role = $community_manager->getMemberRole($community_id, $current_user_id);
		if ($user_role === 'admin') {
			$admin_count = $community_manager->getadmin_count($community_id);
			if ($admin_count <= 1) {
				VT_Ajax::sendError('You cannot leave as you are the only admin. Please promote another member first.');
			}
		}

		$result = $community_manager->remove_member($community_id, $current_user_id);

		if ($result) {
			VT_Ajax::sendSuccess(array(
				'message' => 'You have left the community.',
				'redirect_url' => VT_Config::get('site_url') . '/communities',
			));
		} else {
			VT_Ajax::sendError('Failed to leave community. Please try again.');
		}
	}

	public function ajaxCreateCommunity() {
		vt_service('security.service')->verifyNonce('create_vt_community', 'vt_community_nonce');

		$form_errors = array();
		if (empty($_POST['name'])) {
			$form_errors[] = 'Community name is required.';
		}

		if (!empty($form_errors)) {
			VT_Ajax::sendError(implode(' ', $form_errors));
		}

		$community_data = array(
			'name' => vt_service('validation.validator')->textField($_POST['name']),
			'description' => vt_service('validation.sanitizer')->richText($_POST['description'] ?? ''),
			'visibility' => vt_service('validation.validator')->textField($_POST['visibility'] ?? 'public'),
		);

		$community_manager = $this->getCommunityManager();
		$community_id = $community_manager->createCommunity($community_data);

		if (!is_vt_error($community_id)) {
			// Handle cover image upload
			if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
				$upload_result = $this->handleCoverImageUpload($_FILES['cover_image'], $community_id);
				if (is_vt_error($upload_result)) {
					error_log('Cover image upload failed: ' . $upload_result->getErrorMessage());
				}
			}

			$created_community = $community_manager->getCommunity($community_id);

			$creation_data = array(
				'community_id' => $community_id,
				'community_url' => VT_Config::get('site_url') . '/communities/' . $created_community->slug,
				'community_name' => $created_community->name,
			);

			VT_Transient::set('vt_community_created_' . vt_service('auth.service')->getCurrentUserId(), $creation_data, 300);

			VT_Ajax::sendSuccess(array(
				'community_id' => $community_id,
				'message' => 'Community created successfully!',
				'community_url' => VT_Config::get('site_url') . '/communities/' . $created_community->slug,
			));
		} else {
			VT_Ajax::sendError($community_id->getErrorMessage());
		}
	}

	public function ajaxUpdateCommunity() {
		vt_service('security.service')->verifyNonce('vt_community_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$community_id = intval($_POST['community_id']);
		if (!$community_id) {
			VT_Ajax::sendError('Community ID is required.');
		}

		$community_manager = $this->getCommunityManager();
		$community = $community_manager->getCommunity($community_id);
		if (!$community) {
			VT_Ajax::sendError('Community not found.');
		}

		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		$user_role = $community_manager->getMemberRole($community_id, $current_user_id);

		if ($user_role !== 'admin' && !vt_service('auth.service')->currentUserCan('manage_options')) {
			VT_Ajax::sendError('You do not have permission to update this community.');
		}

		$form_errors = array();
		if (empty($_POST['name'])) {
			$form_errors[] = 'Community name is required.';
		}

		if (!empty($form_errors)) {
			VT_Ajax::sendError(implode(' ', $form_errors));
		}

		$community_data = array(
			'name' => vt_service('validation.validator')->textField($_POST['name']),
			'description' => vt_service('validation.sanitizer')->richText($_POST['description'] ?? ''),
			'visibility' => vt_service('validation.validator')->textField($_POST['visibility'] ?? 'public'),
		);

		$result = $community_manager->updateCommunity($community_id, $community_data);

		if ($result !== false) {
			$updated_community = $community_manager->getCommunity($community_id);
			VT_Ajax::sendSuccess(array(
				'message' => 'Community updated successfully!',
				'community_url' => VT_Config::get('site_url') . '/communities/' . $updated_community->slug,
			));
		} else {
			VT_Ajax::sendError('Failed to update community. Please try again.');
		}
	}

	public function ajaxGetCommunityMembers() {
		vt_service('security.service')->verifyNonce('vt_community_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$community_id = intval($_POST['community_id']);
		if (!$community_id) {
			VT_Ajax::sendError('Community ID is required.');
		}

		$community_manager = $this->getCommunityManager();
		$current_user_id = vt_service('auth.service')->getCurrentUserId();

		if (!$community_manager->isMember($community_id, $current_user_id)) {
			VT_Ajax::sendError('You must be a member to view the member list.');
		}

		$members = $community_manager->getCommunityMembers($community_id);
		$user_role = $community_manager->getMemberRole($community_id, $current_user_id);

		// Generate HTML for each member
		$members_html = '';
		if (!empty($members)) {
			$members_html = '<div class="vt-grid vt-grid-2 vt-gap">';
			foreach ($members as $member) {
				$bio_text = !empty($member->bio) ?
					(strlen($member->bio) > 80 ? substr($member->bio, 0, 80) . '...' : $member->bio) :
					'Community member';

				$members_html .= '<div class="vt-section" data-member-id="' . vt_service('validation.validator')->escAttr($member->id) . '">';
				$members_html .= '<div class="vt-flex vt-flex-between">';
				$members_html .= '<div class="vt-member-info">';

				// Member display (simplified version)
				if (class_exists('VT_Member_Display')) {
					$members_html .= VT_Member_Display::getMemberDisplay($member->user_id, array('avatar_size' => 40));
				} else {
					$members_html .= '<div class="vt-flex vt-gap-2"><div class="vt-avatar"></div>';
					$members_html .= '<div><strong>' . vt_service('validation.validator')->escHtml($member->display_name ?: $member->email) . '</strong></div></div>';
				}

				$members_html .= '<div class="vt-text-muted vt-text-sm vt-mt-1">' . vt_service('validation.validator')->escHtml($bio_text) . '</div>';
				$members_html .= '</div>';
				$members_html .= '<div class="vt-ml-4">';
				$members_html .= '<div class="vt-mb-2"><span class="vt-badge vt-badge-' . ($member->role === 'admin' ? 'primary' : 'secondary') . '">' . vt_service('validation.validator')->escHtml($member->role) . '</span></div>';
				$members_html .= '<div><button class="vt-btn vt-btn-danger vt-btn-sm remove-btn" data-member-id="' . vt_service('validation.validator')->escAttr($member->id) . '" data-member-name="' . vt_service('validation.validator')->escAttr($member->display_name ?: $member->email) . '">Remove</button></div>';
				$members_html .= '</div>';
				$members_html .= '</div>';
				$members_html .= '</div>';
			}
			$members_html .= '</div>';
		}

		VT_Ajax::sendSuccess(array(
			'members' => $members,
			'members_html' => $members_html,
			'user_role' => $user_role,
			'can_manage' => ($user_role === 'admin' || vt_service('auth.service')->currentUserCan('manage_options')),
		));
	}

	public function ajaxUpdateMemberRole() {
		vt_service('security.service')->verifyNonce('vt_community_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$community_id = intval($_POST['community_id']);
		$member_id = intval($_POST['member_id']);
		$new_role = vt_service('validation.validator')->textField($_POST['role']);

		if (!$community_id || !$member_id || !$new_role) {
			VT_Ajax::sendError('All fields are required.');
		}

		if (!in_array($new_role, array('member', 'moderator', 'admin'))) {
			VT_Ajax::sendError('Invalid role.');
		}

		$community_manager = $this->getCommunityManager();
		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		$user_role = $community_manager->getMemberRole($community_id, $current_user_id);

		if ($user_role !== 'admin' && !vt_service('auth.service')->currentUserCan('manage_options')) {
			VT_Ajax::sendError('You do not have permission to change member roles.');
		}

		$result = $community_manager->updatemember_role($community_id, $member_id, $new_role);

		if ($result) {
			VT_Ajax::sendSuccess(array(
				'message' => 'Member role updated successfully.',
			));
		} else {
			VT_Ajax::sendError('Failed to update member role.');
		}
	}

	public function ajaxRemoveMember() {
		vt_service('security.service')->verifyNonce('vt_community_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$community_id = intval($_POST['community_id']);
		$member_id = intval($_POST['member_id']);

		if (!$community_id || !$member_id) {
			VT_Ajax::sendError('Community ID and member ID are required.');
		}

		$community_manager = $this->getCommunityManager();
		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		$user_role = $community_manager->getMemberRole($community_id, $current_user_id);

		if ($user_role !== 'admin' && !vt_service('auth.service')->currentUserCan('manage_options')) {
			VT_Ajax::sendError('You do not have permission to remove members.');
		}

		$member_role = $community_manager->getMemberRole($community_id, $member_id);
		if ($member_role === 'admin') {
			$admin_count = $community_manager->getadmin_count($community_id);
			if ($admin_count <= 1) {
				VT_Ajax::sendError('Cannot remove the only admin. Please promote another member first.');
			}
		}

		$result = $community_manager->remove_member($community_id, $member_id);

		if ($result) {
			VT_Ajax::sendSuccess(array(
				'message' => 'Member removed successfully.',
			));
		} else {
			VT_Ajax::sendError('Failed to remove member.');
		}
	}

	public function ajaxSendInvitation() {
		vt_service('security.service')->verifyNonce('vt_community_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$community_id = intval($_POST['community_id']);
		$email = vt_service('validation.validator')->email($_POST['email']);

		if (!$community_id || !$email) {
			VT_Ajax::sendError('Community ID and email are required.');
		}

		$community_manager = $this->getCommunityManager();
		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		$user_role = $community_manager->getMemberRole($community_id, $current_user_id);

		if (!in_array($user_role, array('admin', 'moderator')) && !vt_service('auth.service')->currentUserCan('manage_options')) {
			VT_Ajax::sendError('You do not have permission to send invitations.');
		}

		$community = $community_manager->getCommunity($community_id);
		if (!$community) {
			VT_Ajax::sendError('Community not found.');
		}

		$db = VT_Database::getInstance();

		$existing = $db->getVar(
			$db->prepare(
				"SELECT id FROM {$db->prefix}community_invitations WHERE community_id = %d AND invited_email = %s AND status = 'pending'",
				$community_id, $email
			)
		);

		if ($existing) {
			VT_Ajax::sendError('This email has already been invited.');
		}

		if ($community_manager->isMember($community_id, null, $email)) {
			VT_Ajax::sendError('This email is already a member of the community.');
		}

		$invitation_data = array(
			'invited_email' => $email,
			'personal_message' => vt_service('validation.validator')->textarea($_POST['personal_message'] ?? ''),
		);

		$result = $community_manager->sendinvitation($community_id, $invitation_data);

		if (!is_vt_error($result)) {
			VT_Ajax::sendSuccess(array(
				'message' => 'Invitation sent successfully!',
			));
		} else {
			VT_Ajax::sendError($result->getErrorMessage());
		}
	}

	public function ajaxGetCommunityInvitations() {
		vt_service('security.service')->verifyNonce('vt_community_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$community_id = intval($_POST['community_id']);
		if (!$community_id) {
			VT_Ajax::sendError('Community ID is required.');
		}

		$community_manager = $this->getCommunityManager();
		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		$user_role = $community_manager->getMemberRole($community_id, $current_user_id);

		if (!in_array($user_role, array('admin', 'moderator')) && !vt_service('auth.service')->currentUserCan('manage_options')) {
			VT_Ajax::sendError('You do not have permission to view invitations.');
		}

		$db = VT_Database::getInstance();

		$invitations = $db->getResults(
			$db->prepare(
				"SELECT ci.*, u.display_name as invited_by_name
				FROM {$db->prefix}community_invitations ci
				LEFT JOIN {$db->prefix}users u ON ci.invited_by_member_id = u.id
				WHERE ci.community_id = %d
				ORDER BY ci.created_at DESC",
				$community_id
			)
		);

		VT_Ajax::sendSuccess(array(
			'invitations' => $invitations,
		));
	}

	public function ajaxCancelInvitation() {
		vt_service('security.service')->verifyNonce('vt_community_action', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in.');
		}

		$invitation_id = intval($_POST['invitation_id']);
		if (!$invitation_id) {
			VT_Ajax::sendError('Invitation ID is required.');
		}

		$db = VT_Database::getInstance();

		$invitation = $db->getRow(
			$db->prepare(
				"SELECT * FROM {$db->prefix}community_invitations WHERE id = %d",
				$invitation_id
			)
		);

		if (!$invitation) {
			VT_Ajax::sendError('Invitation not found.');
		}

		$community_manager = $this->getCommunityManager();
		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		$user_role = $community_manager->getMemberRole($invitation->community_id, $current_user_id);

		if (!in_array($user_role, array('admin', 'moderator')) && !vt_service('auth.service')->currentUserCan('manage_options')) {
			VT_Ajax::sendError('You do not have permission to cancel invitations.');
		}

		$result = $db->delete('community_invitations', array('id' => $invitation_id));

		if ($result !== false) {
			VT_Ajax::sendSuccess(array(
				'message' => 'Invitation cancelled successfully.',
			));
		} else {
			VT_Ajax::sendError('Failed to cancel invitation.');
		}
	}

	public function ajaxAcceptInvitation() {
		vt_service('security.service')->verifyNonce('vt_accept_invitation', 'nonce');

		$token = vt_service('validation.validator')->textField($_POST['token'] ?? '');
		$community_id = intval($_POST['community_id'] ?? 0);

		if (!$token || !$community_id) {
			VT_Ajax::sendError('Invalid invitation parameters.');
		}

		$community_manager = $this->getCommunityManager();
		$invitation = $community_manager->getinvitation_by_token($token);

		if (!$invitation) {
			VT_Ajax::sendError('Invalid or expired invitation.');
		}

		$community = $community_manager->getCommunity($community_id);
		if (!$community) {
			VT_Ajax::sendError('Community not found.');
		}

		// Handle both logged-in and non-logged-in users
		if (vt_service('auth.service')->isLoggedIn()) {
			$current_user = vt_service('auth.service')->getCurrentUser();
			$current_user_id = vt_service('auth.service')->getCurrentUserId();

			// Check if the logged-in user's email matches the invitation
			if ($current_user->email !== $invitation->invited_email) {
				VT_Ajax::sendError(sprintf('This invitation is for %s. Please log in with that account.', $invitation->invited_email));
			}

			// Check if already a member
			if ($community_manager->isMember($community_id, $current_user_id)) {
				VT_Ajax::sendError('You are already a member of this community.');
			}

			// Add user as member
			$member_data = array(
				'user_id' => $current_user_id,
				'email' => $current_user->email,
				'display_name' => $current_user->display_name,
				'role' => 'member',
			);

			$result = $community_manager->addMember($community_id, $member_data);
		} else {
			VT_Ajax::sendError('Please log in to accept this invitation.');
		}

		if ($result) {
			// Mark invitation as accepted
			$db = VT_Database::getInstance();
			$db->update(
				'community_invitations',
				array('status' => 'accepted', 'responded_at' => VT_Time::currentTime('mysql')),
				array('id' => $invitation->id)
			);

			VT_Ajax::sendSuccess(array(
				'message' => sprintf('Welcome to %s!', $community->name),
			));
		} else {
			VT_Ajax::sendError('Failed to join community. Please try again.');
		}
	}

	public function ajaxLoadCommunityInvitationForm() {
		vt_service('security.service')->verifyNonce('vt_community_invitation', 'nonce');

		$token = vt_service('validation.validator')->textField($_POST['token'] ?? '');
		if (!$token) {
			VT_Ajax::sendError('No invitation token provided.');
		}

		$community_manager = $this->getCommunityManager();
		$invitation = $community_manager->getinvitation_by_token($token);

		if (!$invitation) {
			VT_Ajax::sendError('Invalid invitation token.');
		}

		// Generate form HTML (simplified version)
		ob_start();
		?>
		<form id="community-invitation-form" data-token="<?php echo vt_service('validation.validator')->escAttr($token); ?>">
			<div class="vt-form-group">
				<label for="member_name">Name:</label>
				<input type="text" id="member_name" name="member_name" required>
			</div>
			<div class="vt-form-group">
				<label for="member_email">Email:</label>
				<input type="email" id="member_email" name="member_email" value="<?php echo vt_service('validation.validator')->escAttr($invitation->invited_email); ?>" readonly>
			</div>
			<div class="vt-form-group">
				<label for="member_bio">Bio (optional):</label>
				<textarea id="member_bio" name="member_bio" rows="3"></textarea>
			</div>
			<div class="vt-form-actions">
				<button type="submit" class="vt-btn vt-btn-primary">Join Community</button>
				<button type="button" class="vt-btn vt-btn-secondary" onclick="closeModal()">Cancel</button>
			</div>
		</form>
		<?php
		$html = ob_get_clean();

		VT_Ajax::sendSuccess(array(
			'html' => $html,
			'invitation' => $invitation
		));
	}

	public function ajaxAcceptCommunityInvitation() {
		vt_service('security.service')->verifyNonce('vt_nonce', 'nonce');

		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in to join communities. Please login or create an account first.');
		}

		$token = vt_service('validation.validator')->textField($_POST['invitation_token'] ?? '');
		$community_id = intval($_POST['community_id'] ?? 0);
		$member_name = vt_service('validation.validator')->textField($_POST['member_name'] ?? '');
		$member_email = vt_service('validation.validator')->email($_POST['member_email'] ?? '');
		$member_bio = vt_service('validation.validator')->textarea($_POST['member_bio'] ?? '');

		// Validate required fields
		if (empty(trim($member_name)) || empty(trim($member_email))) {
			VT_Ajax::sendError('All required fields must be filled.');
		}

		if (!$community_id) {
			VT_Ajax::sendError('Community ID is required.');
		}

		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		$community_manager = $this->getCommunityManager();

		if ($token) {
			// Token-based invitation
			$member_data = array(
				'bio' => $member_bio,
				'display_name' => $member_name,
			);

			$result = $community_manager->accept_community_invitation($token, $current_user_id, $member_data);

			if (is_vt_error($result)) {
				VT_Ajax::sendError($result->getErrorMessage());
			}

			$invitation = $community_manager->getinvitation_by_token($token);
			$community = $community_manager->getCommunity($invitation->community_id);
		} else {
			// Generic community join (no token)
			$community = $community_manager->getCommunity($community_id);
			if (!$community) {
				VT_Ajax::sendError('Community not found.');
			}

			// Check if already a member
			if ($community_manager->isMember($community_id, $current_user_id)) {
				VT_Ajax::sendError('You are already a member of this community.');
			}

			// Add user as member
			$member_data = array(
				'user_id' => $current_user_id,
				'email' => $member_email,
				'display_name' => $member_name,
				'bio' => $member_bio,
				'role' => 'member',
			);

			$result = $community_manager->addMember($community_id, $member_data);

			if (!$result) {
				VT_Ajax::sendError('Failed to join community. Please try again.');
			}
		}

		VT_Ajax::sendSuccess(array(
			'message' => sprintf('Welcome to %s!', $community->name),
			'redirect_url' => VT_Config::get('site_url') . '/communities/' . $community->slug
		));
	}

	public function ajaxLoadCommunityJoinForm() {
		vt_service('security.service')->verifyNonce('vt_community_invitation', 'nonce');

		$community_id = intval($_POST['community_id'] ?? 0);
		if (!$community_id) {
			VT_Ajax::sendError('No community ID provided.');
		}

		$community_manager = $this->getCommunityManager();
		$community = $community_manager->getCommunity($community_id);

		if (!$community) {
			VT_Ajax::sendError('Community not found.');
		}

		// Generate generic join form HTML
		ob_start();
		?>
		<form id="community-join-form" data-community-id="<?php echo vt_service('validation.validator')->escAttr($community_id); ?>">
			<div class="vt-form-group">
				<label for="member_name">Name:</label>
				<input type="text" id="member_name" name="member_name" required>
			</div>
			<div class="vt-form-group">
				<label for="member_email">Email:</label>
				<input type="email" id="member_email" name="member_email" required>
			</div>
			<div class="vt-form-group">
				<label for="member_bio">Bio (optional):</label>
				<textarea id="member_bio" name="member_bio" rows="3"></textarea>
			</div>
			<div class="vt-form-actions">
				<button type="submit" class="vt-btn vt-btn-primary">Join Community</button>
				<button type="button" class="vt-btn vt-btn-secondary" onclick="closeModal()">Cancel</button>
			</div>
		</form>
		<?php
		$html = ob_get_clean();

		VT_Ajax::sendSuccess(array(
			'html' => $html,
			'community' => $community
		));
	}

	private function handleCoverImageUpload($file, $community_id) {
		// Similar to event image upload handling
		if (class_exists('VT_Upload')) {
			$validation_result = VT_Upload::validateFile($file);
			if (is_vt_error($validation_result)) {
				return $validation_result;
			}

			$uploaded_file = VT_Upload::handleUpload($file);

			if ($uploaded_file && !isset($uploaded_file['error'])) {
				$community_manager = $this->getCommunityManager();
				$community_manager->updateCommunity($community_id, array('featured_image' => $uploaded_file['url']));
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
				$community_manager = $this->getCommunityManager();
				$community_manager->updateCommunity($community_id, array('featured_image' => $file_url));
				return array('url' => $file_url);
			} else {
				return new VT_Error('upload_failed', 'File upload failed.');
			}
		}
	}

	/**
	 * AJAX handler for getting communities with circle filtering
	 */
	public static function ajaxGetCommunities() {
		// Verify nonce for security
		if (!vt_service('security.service')->verifyNonce($_POST['nonce'] ?? '', 'vt_nonce')) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'Security check failed']);
			exit;
		}

		$circle = vt_service('validation.validator')->textField($_POST['circle'] ?? 'inner');
		$page = max(1, intval($_POST['page'] ?? 1));
		$per_page = 20;

		// Validate circle
		$allowed_circles = array('inner', 'trusted', 'extended');
		if (!in_array($circle, $allowed_circles)) {
			$circle = 'inner';
		}

		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		$communities = array();

		// Community circle filtering logic
		if ($current_user_id) {
			$db = VT_Database::getInstance();
			$communities_table = $db->prefix . 'communities';
			$members_table = $db->prefix . 'community_members';

			if ($circle === 'inner') {
				// Inner: Communities user is a member of
				$communities = $db->getResults($db->prepare(
					"SELECT c.* FROM $communities_table c
					 JOIN $members_table m ON c.id = m.community_id
					 WHERE m.user_id = %d AND m.status = 'active' AND c.is_active = 1
					 ORDER BY c.created_at DESC LIMIT %d",
					$current_user_id, $per_page
				));
			} elseif ($circle === 'trusted') {
				// Trusted: Inner + communities created by members of user's communities
				$communities = $db->getResults($db->prepare(
					"SELECT DISTINCT c.* FROM $communities_table c
					 WHERE c.is_active = 1 AND (
						 c.id IN (
							 SELECT community_id FROM $members_table
							 WHERE user_id = %d AND status = 'active'
						 )
						 OR c.creator_id IN (
							 SELECT DISTINCT m.user_id FROM $members_table m
							 JOIN $members_table m2 ON m.community_id = m2.community_id
							 WHERE m2.user_id = %d AND m.status = 'active' AND m2.status = 'active'
						 )
					 )
					 ORDER BY c.created_at DESC LIMIT %d",
					$current_user_id, $current_user_id, $per_page
				));
			} else { // extended
				// Extended: Trusted + communities created by members of trusted communities
				$communities = $db->getResults($db->prepare(
					"SELECT DISTINCT c.* FROM $communities_table c
					 WHERE c.is_active = 1 AND (
						 c.id IN (
							 SELECT community_id FROM $members_table
							 WHERE user_id = %d AND status = 'active'
						 )
						 OR c.creator_id IN (
							 SELECT DISTINCT m.user_id FROM $members_table m
							 JOIN $members_table m2 ON m.community_id = m2.community_id
							 WHERE m2.user_id = %d AND m.status = 'active' AND m2.status = 'active'
						 )
						 OR c.creator_id IN (
							 SELECT DISTINCT m3.user_id FROM $members_table m3
							 WHERE m3.community_id IN (
								 SELECT DISTINCT c2.id FROM $communities_table c2
								 WHERE c2.is_active = 1 AND (
									 c2.id IN (
										 SELECT community_id FROM $members_table
										 WHERE user_id = %d AND status = 'active'
									 )
									 OR c2.creator_id IN (
										 SELECT DISTINCT m4.user_id FROM $members_table m4
										 JOIN $members_table m5 ON m4.community_id = m5.community_id
										 WHERE m5.user_id = %d AND m4.status = 'active' AND m5.status = 'active'
									 )
								 )
							 ) AND m3.status = 'active'
						 )
					 )
					 ORDER BY c.created_at DESC LIMIT %d",
					$current_user_id, $current_user_id, $current_user_id, $current_user_id, $per_page
				));
			}
		}

		// Render HTML for communities
		$html = '';
		if (!empty($communities)) {
			$community_manager = new VT_Community_Manager();
			$current_user = vt_service('auth.service')->getCurrentUser();

			foreach ($communities as $community) {
				$html .= '<div class="vt-section vt-border vt-p-4">';

				// Featured image
				if (!empty($community->featured_image)) {
					$html .= '<div class="vt-mb-4">';
					$html .= '<img src="' . vt_service('validation.validator')->escUrl($community->featured_image) . '" alt="' . vt_service('validation.validator')->escHtml($community->name) . '" style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px;">';
					$html .= '</div>';
				}

				$html .= '<div class="vt-flex vt-flex-between vt-mb-4">';
				$html .= '<div class="vt-flex-1">';
				$html .= '<h3 class="vt-heading vt-heading-sm vt-mb-2">';
				$html .= '<a href="/communities/' . vt_service('validation.validator')->escHtml($community->slug) . '" class="vt-text-primary">';
				$html .= vt_service('validation.validator')->escHtml($community->name);
				$html .= '</a>';
				$html .= '</h3>';

				// Badges
				$html .= '<div class="vt-flex vt-gap vt-flex-wrap vt-mb-2">';
				if ($community->visibility === 'private') {
					$html .= '<span class="vt-badge vt-badge-secondary">Private</span>';
				}
				$html .= '</div>';

				$html .= '<div class="vt-text-muted">';
				$html .= sprintf('%s members', intval($community->member_count ?? 0));
				$html .= '</div>';
				$html .= '</div>';

				$html .= '<div class="vt-stat vt-text-center">';
				$html .= '<div class="vt-stat-number vt-text-primary">' . intval($community->event_count ?? 0) . '</div>';
				$html .= '<div class="vt-stat-label">Events</div>';
				$html .= '</div>';
				$html .= '</div>';

				// Description
				if ($community->description) {
					$html .= '<div class="vt-mb-4">';
					$html .= '<p class="vt-text-muted">' . vt_service('validation.validator')->escHtml(VT_Text::truncateWords($community->description, 15)) . '</p>';
					$html .= '</div>';
				}

				// Action buttons
				$html .= '<div class="vt-flex vt-flex-between vt-flex-wrap vt-gap">';
				$html .= '<div class="vt-flex vt-gap vt-flex-wrap">';
				$html .= '<div class="vt-stat vt-text-center">';
				$html .= '<div class="vt-stat-number vt-text-primary">' . intval($community->member_count ?? 0) . '</div>';
				$html .= '<div class="vt-stat-label">Members</div>';
				$html .= '</div>';
				$html .= '</div>';

				$html .= '<div class="vt-flex vt-gap">';
				$html .= '<a href="/communities/' . vt_service('validation.validator')->escHtml($community->slug) . '" class="vt-btn">';
				$html .= 'View';
				$html .= '</a>';

				// Check if user is admin
				if ($current_user_id && $community_manager->isMember($community->id, $current_user_id)) {
					$member_role = $community_manager->getMemberRole($community->id, $current_user_id);
					if ($member_role === 'admin') {
						$html .= '<a href="/communities/' . $community->slug . '/manage" class="vt-btn">';
						$html .= 'Manage';
						$html .= '</a>';
					}
				}

				$html .= '</div>';
				$html .= '</div>';
				$html .= '</div>';
			}
		}

		header('Content-Type: application/json');
		echo json_encode(array(
			'success' => true,
			'html' => $html,
			'meta' => array(
				'count' => count($communities),
				'circle' => $circle,
				'page' => $page
			)
		));
		exit;
	}
}