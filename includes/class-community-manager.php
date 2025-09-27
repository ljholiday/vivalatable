<?php
/**
 * VivalaTable Community Manager
 * Handles community creation, membership, and invitations
 * Ported from PartyMinder WordPress plugin
 */

class VT_Community_Manager {

	private $db;

	public function __construct() {
		$this->db = VT_Database::getInstance();
	}

	/**
	 * Create a new community
	 */
	public function createCommunity($community_data) {
		// Validate required fields
		if (empty($community_data['name'])) {
			return ['error' => 'Community name is required'];
		}

		if (empty($community_data['creator_email'])) {
			return ['error' => 'Creator email is required'];
		}

		$current_user = VT_Auth::getCurrentUser();
		$current_user_id = VT_Auth::getCurrentUserId();

		if (!$current_user_id) {
			return ['error' => 'You must be logged in to create a community'];
		}

		// Sanitize input data
		$name = VT_Sanitize::textField($community_data['name']);
		$description = VT_Sanitize::post($community_data['description'] ?? '');
		$visibility = $this->validateprivacy_setting($community_data['visibility'] ?? 'public');
		$creator_email = VT_Sanitize::email($community_data['creator_email']);

		// Generate unique slug
		$slug = VT_Sanitize::slug($name);
		$original_slug = $slug;
		$counter = 1;

		while ($this->community_slug_exists($slug)) {
			$slug = $original_slug . '-' . $counter;
			$counter++;
		}

		// Prepare community data
		$insert_data = array(
			'name' => $name,
			'slug' => $slug,
			'description' => $description,
			'visibility' => $visibility,
			'creator_id' => $current_user_id,
			'creator_email' => $creator_email,
			'is_active' => 1,
			'member_count' => 1,
			'created_at' => VT_Time::currentTime('mysql'),
			'updated_at' => VT_Time::currentTime('mysql'),
			'settings' => json_encode(array(
				'allow_auto_join_on_reply' => ($visibility === 'public')
			))
		);

		$community_id = $this->db->insert('communities', $insert_data);

		if (!$community_id) {
			return new VT_Error('insert_failed', 'Failed to create community');
		}

		// Add creator as admin member
		$member_data = array(
			'user_id' => $current_user_id,
			'email' => $creator_email,
			'display_name' => $current_user->display_name ?? '',
			'role' => 'admin',
			'status' => 'active'
		);

		$member_result = $this->add_member($community_id, $member_data);

		if (is_vt_error($member_result)) {
			// Clean up - delete the community if member creation failed
			$this->db->delete('communities', array('id' => $community_id));
			return $member_result;
		}

		// Clear any cached data
		VT_Cache::delete('community_' . $community_id);

		return $community_id;
	}

	/**
	 * Get a community by ID
	 */
	public function getCommunity($community_id) {
		$community_id = intval($community_id);
		if (!$community_id) {
			return null;
		}

		// Check cache first
		$cache_key = 'community_' . $community_id;
		$community = VT_Cache::get($cache_key);

		if ($community === false) {
			$community = $this->db->getRow(
				$this->db->prepare(
					"SELECT * FROM {$this->db->prefix}communities WHERE id = %d AND is_active = 1",
					$community_id
				)
			);

			if ($community && $community->settings) {
				$community->settings = json_decode($community->settings, true);
			}

			// Cache for 5 minutes
			VT_Cache::set($cache_key, $community, 300);
		}

		return $community;
	}

	/**
	 * Get community by slug
	 */
	public function getCommunityBySlug($slug) {
		$slug = VT_Sanitize::slug($slug);
		if (!$slug) {
			return null;
		}

		return $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}communities WHERE slug = %s AND is_active = 1",
				$slug
			)
		);
	}

	/**
	 * Update community data
	 */
	public function updateCommunity($community_id, $update_data) {
		$community_id = intval($community_id);
		if (!$community_id) {
			return new VT_Error('invalid_community', 'Invalid community ID');
		}

		// Check if community exists
		$community = $this->getCommunity($community_id);
		if (!$community) {
			return new VT_Error('community_not_found', 'Community not found');
		}

		// Check permissions
		if (!$this->can_manage_community($community_id)) {
			return new VT_Error('permission_denied', 'You do not have permission to manage this community');
		}

		// Sanitize update data
		$allowed_fields = array('name', 'description', 'visibility', 'settings');
		$sanitized_data = array();

		foreach ($update_data as $field => $value) {
			if (in_array($field, $allowed_fields)) {
				switch ($field) {
					case 'name':
						$sanitized_data[$field] = VT_Sanitize::textField($value);
						break;
					case 'description':
						$sanitized_data[$field] = VT_Sanitize::post($value);
						break;
					case 'visibility':
						$sanitized_data[$field] = $this->validateprivacy_setting($value);
						break;
					case 'settings':
						$sanitized_data[$field] = is_array($value) ? json_encode($value) : $value;
						break;
				}
			}
		}

		if (empty($sanitized_data)) {
			return new VT_Error('no_valid_data', 'No valid data to update');
		}

		$sanitized_data['updated_at'] = VT_Time::currentTime('mysql');

		$result = $this->db->update('communities', $sanitized_data, array('id' => $community_id));

		if ($result === false) {
			return new VT_Error('update_failed', 'Failed to update community');
		}

		// Clear cache
		VT_Cache::delete('community_' . $community_id);

		return true;
	}

	/**
	 * Add a member to a community
	 */
	public function addMember($community_id, $member_data, $skip_invitation = false) {
		$community_id = intval($community_id);
		if (!$community_id) {
			return new VT_Error('invalid_community', 'Invalid community ID');
		}

		// Validate required fields
		if (empty($member_data['email'])) {
			return new VT_Error('email_required', 'Member email is required');
		}

		// Check if community exists
		$community = $this->getCommunity($community_id);
		if (!$community) {
			return new VT_Error('community_not_found', 'Community not found');
		}

		// Check if user is already a member
		if ($this->isMember($community_id, null, $member_data['email'])) {
			return new VT_Error('already_member', 'User is already a member of this community');
		}

		// Permission check (skip for auto-join scenarios)
		if (!$skip_invitation && !$this->can_manage_community($community_id)) {
			return new VT_Error('permission_denied', 'You do not have permission to add members to this community');
		}

		// Ensure we have a user_id (required field)
		$user_id = isset($member_data['user_id']) ? intval($member_data['user_id']) : VT_Auth::getCurrentUserId();
		if (!$user_id) {
			return new VT_Error('user_id_required', 'User ID is required for community membership');
		}

		// Sanitize member data
		$sanitized_data = array(
			'community_id' => $community_id,
			'user_id' => $user_id,
			'email' => VT_Sanitize::email($member_data['email']),
			'display_name' => VT_Sanitize::textField($member_data['display_name'] ?? ''),
			'role' => in_array($member_data['role'] ?? 'member', array('admin', 'member')) ? $member_data['role'] : 'member',
			'status' => in_array($member_data['status'] ?? 'active', array('active', 'inactive')) ? $member_data['status'] : 'active',
			'joined_at' => VT_Time::currentTime('mysql')
		);

		$member_id = $this->db->insert('community_members', $sanitized_data);

		if (!$member_id) {
			return new VT_Error('insert_failed', 'Failed to add member to community');
		}

		// Update community member count
		$this->updatemember_count($community_id);

		// Clear cache
		VT_Cache::delete('community_' . $community_id);
		VT_Cache::delete('community_members_' . $community_id);

		return $member_id;
	}

	/**
	 * Check if user is a member of a community
	 */
	public function isMember($community_id, $user_id = null, $email = null) {
		$community_id = intval($community_id);
		if (!$community_id) {
			return false;
		}

		if (!$user_id && !$email) {
			$current_user = VT_Auth::getCurrentUser();
			if (!$current_user) {
				return false;
			}
			$user_id = VT_Auth::getCurrentUserId();
			$email = $current_user->email;
		}

		if ($user_id) {
			$member = $this->db->getRow(
				$this->db->prepare(
					"SELECT id FROM {$this->db->prefix}community_members WHERE community_id = %d AND user_id = %d AND status = %s",
					$community_id, intval($user_id), 'active'
				)
			);
		} else if ($email) {
			$member = $this->db->getRow(
				$this->db->prepare(
					"SELECT id FROM {$this->db->prefix}community_members WHERE community_id = %d AND email = %s AND status = %s",
					$community_id, VT_Sanitize::email($email), 'active'
				)
			);
		} else {
			return false;
		}

		return $member !== null;
	}

	/**
	 * Get member role in a community
	 */
	public function getMemberRole($community_id, $user_id = null, $email = null) {
		$community_id = intval($community_id);
		if (!$community_id) {
			return null;
		}

		if (!$user_id && !$email) {
			$current_user = VT_Auth::getCurrentUser();
			if (!$current_user) {
				return null;
			}
			$user_id = VT_Auth::getCurrentUserId();
			$email = $current_user->email;
		}

		if ($user_id) {
			$member = $this->db->getRow(
				$this->db->prepare(
					"SELECT role FROM {$this->db->prefix}community_members WHERE community_id = %d AND user_id = %d AND status = %s",
					$community_id, intval($user_id), 'active'
				)
			);
		} else if ($email) {
			$member = $this->db->getRow(
				$this->db->prepare(
					"SELECT role FROM {$this->db->prefix}community_members WHERE community_id = %d AND email = %s AND status = %s",
					$community_id, VT_Sanitize::email($email), 'active'
				)
			);
		} else {
			return null;
		}

		return $member ? $member->role : null;
	}

	/**
	 * Check if current user can manage a community
	 */
	public function canManageCommunity($community_id, $user_id = null) {
		if (!$user_id) {
			$user_id = VT_Auth::getCurrentUserId();
		}

		if (!$user_id) {
			return false;
		}

		// Site admins can manage any community
		if (VT_Auth::isSiteAdmin()) {
			return true;
		}

		// Community admins can manage their community
		$role = $this->getMemberRole($community_id, $user_id);
		return $role === 'admin';
	}

	/**
	 * Send community invitation
	 */
	public function sendInvitation($community_id, $invitation_data) {
		$community_id = intval($community_id);
		if (!$community_id) {
			return new VT_Error('invalid_community', 'Invalid community ID');
		}

		// Validate required fields
		if (empty($invitation_data['invited_email'])) {
			return new VT_Error('email_required', 'Invitation email is required');
		}

		// Check if community exists
		$community = $this->getCommunity($community_id);
		if (!$community) {
			return new VT_Error('community_not_found', 'Community not found');
		}

		// Permission check
		if (!$this->can_manage_community($community_id)) {
			return new VT_Error('permission_denied', 'You do not have permission to send invitations for this community');
		}

		$current_user = VT_Auth::getCurrentUser();
		$inviter_member = $this->db->getRow(
			$this->db->prepare(
				"SELECT id FROM {$this->db->prefix}community_members WHERE community_id = %d AND user_id = %d",
				$community_id, VT_Auth::getCurrentUserId()
			)
		);

		// Check if user is already invited or member
		$existing_invitation = $this->db->getRow(
			$this->db->prepare(
				"SELECT id FROM {$this->db->prefix}community_invitations
				 WHERE community_id = %d AND invited_email = %s AND status = 'pending'",
				$community_id, VT_Sanitize::email($invitation_data['invited_email'])
			)
		);

		if ($existing_invitation) {
			return new VT_Error('already_invited', 'User has already been invited to this community');
		}

		if ($this->isMember($community_id, null, $invitation_data['invited_email'])) {
			return new VT_Error('already_member', 'User is already a member of this community');
		}

		// Generate invitation token
		$invitation_token = VT_Security::generateToken();

		// Prepare invitation data
		$insert_data = array(
			'community_id' => $community_id,
			'invited_email' => VT_Sanitize::email($invitation_data['invited_email']),
			'invited_display_name' => VT_Sanitize::textField($invitation_data['invited_display_name'] ?? ''),
			'invitation_token' => $invitation_token,
			'invited_by_member_id' => $inviter_member->id,
			'personal_message' => VT_Sanitize::post($invitation_data['personal_message'] ?? ''),
			'status' => 'pending',
			'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
			'created_at' => VT_Time::currentTime('mysql')
		);

		$invitation_id = $this->db->insert('community_invitations', $insert_data);

		if (!$invitation_id) {
			return new VT_Error('invitation_failed', 'Failed to create invitation');
		}

		// Send invitation email
		$this->sendinvitation_email($invitation_id);

		return $invitation_id;
	}

	/**
	 * Send invitation email
	 */
	private function sendInvitationEmail($invitation_id) {
		$invitation = $this->db->getRow(
			$this->db->prepare(
				"SELECT i.*, c.name as community_name, c.description as community_description,
				        m.display_name as inviter_name
				 FROM {$this->db->prefix}community_invitations i
				 LEFT JOIN {$this->db->prefix}communities c ON i.community_id = c.id
				 LEFT JOIN {$this->db->prefix}community_members m ON i.invited_by_member_id = m.id
				 WHERE i.id = %d",
				$invitation_id
			)
		);

		if (!$invitation) {
			return false;
		}

		$invitation_url = VT_Config::get('site_url') . '/community/invitation/' . $invitation->invitation_token;

		$subject = sprintf('%s invited you to join %s', $invitation->inviter_name, $invitation->community_name);

		$message = $this->getinvitation_email_template($invitation, $invitation_url);

		return VT_Mail::send($invitation->invited_email, $subject, $message);
	}

	/**
	 * Get invitation email template
	 */
	private function getInvitationEmailTemplate($invitation, $invitation_url) {
		ob_start();
		?>
		<html>
		<body>
			<div>
				<h2>You've been invited to join <?php echo VT_Sanitize::escHtml($invitation->community_name); ?></h2>

				<p>Hi there!</p>

				<p><?php echo VT_Sanitize::escHtml($invitation->inviter_name); ?> has invited you to join the community "<?php echo VT_Sanitize::escHtml($invitation->community_name); ?>".</p>

				<?php if ($invitation->community_description): ?>
				<div>
					<p><strong>About this community:</strong></p>
					<p><?php echo nl2br(VT_Sanitize::escHtml($invitation->community_description)); ?></p>
				</div>
				<?php endif; ?>

				<?php if ($invitation->personal_message): ?>
				<div>
					<p><strong>Personal message from <?php echo VT_Sanitize::escHtml($invitation->inviter_name); ?>:</strong></p>
					<p><?php echo nl2br(VT_Sanitize::escHtml($invitation->personal_message)); ?></p>
				</div>
				<?php endif; ?>

				<div>
					<a href="<?php echo VT_Sanitize::escUrl($invitation_url); ?>">
						Accept Invitation
					</a>
				</div>

				<p>
					This invitation will expire on <?php echo date('F j, Y', strtotime($invitation->expires_at)); ?>.
				</p>

				<hr>

				<p>
					If you have any questions about this invitation, please contact <?php echo VT_Sanitize::escHtml($invitation->inviter_name); ?>.
					<br>
					You can also copy and paste this link into your browser: <?php echo VT_Sanitize::escUrl($invitation_url); ?>
				</p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get communities for a user
	 */
	public function getUserCommunities($user_id = null, $include_inactive = false) {
		if (!$user_id) {
			$user_id = VT_Auth::getCurrentUserId();
		}

		if (!$user_id) {
			return array();
		}

		$status_condition = $include_inactive ? '' : " AND m.status = 'active'";

		return $this->db->getResults(
			$this->db->prepare(
				"SELECT c.*, m.role, m.status as member_status, m.joined_at
				 FROM {$this->db->prefix}communities c
				 INNER JOIN {$this->db->prefix}community_members m ON c.id = m.community_id
				 WHERE m.user_id = %d AND c.is_active = 1 {$status_condition}
				 ORDER BY c.name ASC",
				$user_id
			)
		);
	}

	/**
	 * Get community members
	 */
	public function getCommunityMembers($community_id, $role = null, $status = 'active') {
		$community_id = intval($community_id);
		if (!$community_id) {
			return array();
		}

		$where_conditions = array("m.community_id = %d", "m.status = %s");
		$where_values = array($community_id, $status);

		if ($role) {
			$where_conditions[] = "m.role = %s";
			$where_values[] = $role;
		}

		return $this->db->getResults(
			$this->db->prepare(
				"SELECT m.*, u.login as username
				 FROM {$this->db->prefix}community_members m
				 LEFT JOIN {$this->db->prefix}users u ON m.user_id = u.id
				 WHERE " . implode(' AND ', $where_conditions) . "
				 ORDER BY m.display_name ASC",
				$where_values
			)
		);
	}

	/**
	 * Update member count for a community
	 */
	private function updateMemberCount($community_id) {
		$count = $this->db->getVar(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->db->prefix}community_members
				 WHERE community_id = %d AND status = 'active'",
				$community_id
			)
		);

		$this->db->update(
			'communities',
			array('member_count' => intval($count)),
			array('id' => $community_id)
		);
	}

	/**
	 * Check if community slug exists
	 */
	private function communitySlugExists($slug) {
		return $this->db->getVar(
			$this->db->prepare(
				"SELECT id FROM {$this->db->prefix}communities WHERE slug = %s",
				$slug
			)
		) !== null;
	}

	/**
	 * Validate privacy setting for communities
	 */
	private function validatePrivacySetting($privacy) {
		$allowed_privacy_settings = array('public', 'private');

		$privacy = VT_Sanitize::textField($privacy);

		if (!in_array($privacy, $allowed_privacy_settings)) {
			return 'public'; // Default to public if invalid
		}

		return $privacy;
	}

	/**
	 * Delete a community and all associated data
	 */
	public function deleteCommunity($community_id) {
		$community_id = intval($community_id);
		if (!$community_id) {
			return new VT_Error('invalid_community', 'Invalid community ID');
		}

		// Check if community exists
		$community = $this->getCommunity($community_id);
		if (!$community) {
			return new VT_Error('community_not_found', 'Community not found');
		}

		// Check if current user is admin of this community
		$current_user_id = VT_Auth::getCurrentUserId();
		if (!$current_user_id) {
			return new VT_Error('user_required', 'You must be logged in');
		}

		$user_role = $this->getMemberRole($community_id, $current_user_id);
		if ($user_role !== 'admin') {
			return new VT_Error('permission_denied', 'You must be a community admin to delete this community');
		}

		// Start transaction
		try {
			$this->db->query('START TRANSACTION');

			// Delete community members
			$this->db->delete('community_members', array('community_id' => $community_id));

			// Delete community invitations
			$this->db->delete('community_invitations', array('community_id' => $community_id));

			// Update events to remove community association
			$this->db->update(
				'events',
				array('community_id' => null),
				array('community_id' => $community_id)
			);

			// Update conversations to remove community association
			$this->db->update(
				'conversations',
				array('community_id' => null),
				array('community_id' => $community_id)
			);

			// Finally, delete the community itself
			$deleted = $this->db->delete('communities', array('id' => $community_id));

			if ($deleted === false) {
				throw new Exception('Failed to delete community record');
			}

			// Commit transaction
			$this->db->query('COMMIT');

			// Clear any cached data
			VT_Cache::delete('community_' . $community_id);

			return true;

		} catch (Exception $e) {
			// Rollback transaction on error
			$this->db->query('ROLLBACK');
			return new VT_Error('deletion_failed', $e->getMessage());
		}
	}

	/**
	 * Get community setting value
	 */
	public function getCommunitySetting($community_id, $setting_key, $default = null) {
		$community = $this->getCommunity($community_id);
		if (!$community) {
			return $default;
		}

		$settings = is_array($community->settings) ? $community->settings : array();
		return isset($settings[$setting_key]) ? $settings[$setting_key] : $default;
	}

	/**
	 * Set community setting value
	 */
	public function setCommunitySetting($community_id, $setting_key, $value) {
		$community = $this->getCommunity($community_id);
		if (!$community) {
			return false;
		}

		$settings = is_array($community->settings) ? $community->settings : array();
		$settings[$setting_key] = $value;

		return $this->updateCommunity($community_id, array('settings' => $settings));
	}

	/**
	 * Check if a community allows auto-join on reply
	 */
	public function allowsAutoJoinOnReply($community_id) {
		$community = $this->getCommunity($community_id);
		if (!$community) {
			return false;
		}

		// Default to true for all public communities
		$default = ($community->visibility === 'public');

		return $this->getcommunity_setting($community_id, 'allow_auto_join_on_reply', $default);
	}

	/**
	 * Auto-join a user to a community
	 */
	public function joinCommunity($community_id, $user_id) {
		// Check if user is already a member
		$existing_role = $this->getMemberRole($community_id, $user_id);
		if ($existing_role) {
			return true; // Already a member
		}

		// Get user info
		$user = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}users WHERE id = %d",
				$user_id
			)
		);

		if (!$user) {
			return new VT_Error('invalid_user', 'Invalid user');
		}

		// Add as active member
		$member_data = array(
			'user_id' => $user_id,
			'email' => $user->email,
			'display_name' => $user->display_name,
			'role' => 'member',
			'status' => 'active'
		);

		return $this->add_member($community_id, $member_data, true);
	}

	/**
	 * Get community invitation by token
	 */
	public function getInvitationByToken($token) {
		return $this->db->getRow(
			$this->db->prepare(
				"SELECT i.*, c.name as community_name, c.slug as community_slug,
				        c.description as community_description, c.visibility, c.member_count,
				        m.display_name as inviter_name
				 FROM {$this->db->prefix}community_invitations i
				 LEFT JOIN {$this->db->prefix}communities c ON i.community_id = c.id
				 LEFT JOIN {$this->db->prefix}community_members m ON i.invited_by_member_id = m.id
				 WHERE i.invitation_token = %s",
				$token
			)
		);
	}

	/**
	 * Accept community invitation
	 */
	public function acceptCommunityInvitation($token, $user_id, $member_data = array()) {
		// Get invitation
		$invitation = $this->getinvitation_by_token($token);
		if (!$invitation) {
			return new VT_Error('invitation_not_found', 'Invitation not found');
		}

		// Validation checks
		if ($invitation->status !== 'pending') {
			return new VT_Error('invitation_processed', 'This invitation has already been processed');
		}

		if (strtotime($invitation->expires_at) < time()) {
			return new VT_Error('invitation_expired', 'This invitation has expired');
		}

		// Check if already a member
		if ($this->isMember($invitation->community_id, $user_id)) {
			return new VT_Error('already_member', 'You are already a member of this community');
		}

		// Get user info
		$user = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}users WHERE id = %d",
				$user_id
			)
		);

		if (!$user) {
			return new VT_Error('user_not_found', 'User not found');
		}

		// Add member
		$member_result = $this->add_member(
			$invitation->community_id,
			array_merge(
				array(
					'user_id' => $user_id,
					'email' => $invitation->invited_email,
					'display_name' => $user->display_name,
					'role' => 'member',
					'status' => 'active',
				),
				$member_data
			)
		);

		if (is_vt_error($member_result)) {
			return $member_result;
		}

		// Mark invitation as accepted
		$this->db->update(
			'community_invitations',
			array(
				'status' => 'accepted',
				'responded_at' => VT_Time::currentTime('mysql'),
			),
			array('id' => $invitation->id)
		);

		return $member_result;
	}
}