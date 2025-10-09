<?php
/**
 * VivalaTable Member Identity Manager
 * Handles AT Protocol DIDs and cross-site member identity
 * Ported from PartyMinder WordPress plugin
 */

class VT_Member_Identity_Manager {

	private $db;

	public function __construct() {
		$this->db = VT_Database::getInstance();
	}

	/**
	 * Create member identity when user registers
	 */
	public function createMemberIdentity($user_id) {
		$user = $this->db->getRow(
			$this->db->prepare("SELECT * FROM {$this->db->prefix}users WHERE id = %d", $user_id)
		);

		if (!$user) {
			return false;
		}

		return $this->ensureIdentityExists($user_id, $user->email, $user->display_name);
	}

	/**
	 * Ensure member identity exists on login
	 */
	public function ensureMemberIdentity($user_login, $user) {
		if (!$user || !$user->id) {
			return false;
		}

		return $this->ensureIdentityExists($user->id, $user->email, $user->display_name);
	}

	/**
	 * Ensure identity record exists for user
	 */
	public function ensureIdentityExists($user_id, $email, $display_name = '') {
		if (!VT_Config::get('at_protocol_enabled', false)) {
			return false;
		}

		$identities_table = $this->db->prefix . 'member_identities';

		// Validate that the user actually exists first
		$vt_user = $this->db->getRow(
			$this->db->prepare("SELECT * FROM {$this->db->prefix}users WHERE id = %d", $user_id)
		);

		if (!$vt_user) {
			error_log('[VivalaTable] Cannot create member identity for non-existent user ID: ' . $user_id);
			return false;
		}

		// Check if identity already exists
		$existing_identity = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM $identities_table WHERE user_id = %d",
				$user_id
			)
		);

		if ($existing_identity) {
			// Update display name if changed
			if ($existing_identity->display_name !== $vt_user->display_name) {
				$this->db->update(
					'member_identities',
					array(
						'display_name' => vt_service('validation.sanitizer')->textField($vt_user->display_name),
						'updated_at' => VT_Time::currentTime('mysql'),
					),
					array('user_id' => $user_id)
				);
			}
			return $existing_identity->at_protocol_did;
		}

		// Generate new DID and handle using user data
		$did = $this->generateMemberDid($user_id, $email);
		$handle = $this->generateMemberHandle($user_id, $vt_user->display_name);

		// Create identity record
		$insert_data = array(
			'user_id' => $user_id,
			'email' => vt_service('validation.sanitizer')->email($email),
			'display_name' => vt_service('validation.sanitizer')->textField($vt_user->display_name),
			'at_protocol_did' => $did,
			'at_protocol_handle' => $handle,
			'pds_url' => $this->getDefaultPds(),
			'profile_data' => json_encode($this->getDefaultAtProtocolData()),
			'is_verified' => 0,
			'created_at' => VT_Time::currentTime('mysql'),
		);

		$result = $this->db->insert('member_identities', $insert_data);

		if (!$result) {
			error_log('[VivalaTable] Failed to create member identity for user ' . $user_id);
			return false;
		}

		// Log DID creation
		error_log('[VivalaTable] Created AT Protocol DID for user ' . $user_id . ': ' . $did);

		return $did;
	}

	/**
	 * Get member identity by user ID
	 */
	public function getMemberIdentity($user_id) {
		$identities_table = $this->db->prefix . 'member_identities';

		$identity = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM $identities_table WHERE user_id = %d",
				$user_id
			)
		);

		if ($identity) {
			$identity->at_protocol_data = json_decode($identity->profile_data ?: '{}', true);
		}

		return $identity;
	}

	/**
	 * Get member identity by DID
	 */
	public function getMemberIdentityByDid($did) {
		$identities_table = $this->db->prefix . 'member_identities';

		$identity = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM $identities_table WHERE at_protocol_did = %s",
				$did
			)
		);

		if ($identity) {
			$identity->at_protocol_data = json_decode($identity->profile_data ?: '{}', true);
		}

		return $identity;
	}

	/**
	 * Get member identity by email
	 */
	public function getMemberIdentityByEmail($email) {
		$identities_table = $this->db->prefix . 'member_identities';

		$identity = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM $identities_table WHERE email = %s",
				$email
			)
		);

		if ($identity) {
			$identity->at_protocol_data = json_decode($identity->profile_data ?: '{}', true);
		}

		return $identity;
	}

	/**
	 * Update member identity AT Protocol data
	 */
	public function updateAtProtocolData($user_id, $at_protocol_data) {
		$identities_table = $this->db->prefix . 'member_identities';

		$result = $this->db->update(
			'member_identities',
			array(
				'profile_data' => json_encode($at_protocol_data),
				'last_sync_at' => VT_Time::currentTime('mysql'),
				'updated_at' => VT_Time::currentTime('mysql'),
			),
			array('user_id' => $user_id)
		);

		return $result !== false;
	}

	/**
	 * Mark identity as verified
	 */
	public function verifyIdentity($user_id) {
		$identities_table = $this->db->prefix . 'member_identities';

		$result = $this->db->update(
			'member_identities',
			array(
				'is_verified' => 1,
				'updated_at' => VT_Time::currentTime('mysql'),
			),
			array('user_id' => $user_id)
		);

		if ($result) {
			error_log('[VivalaTable] Verified AT Protocol identity for user ' . $user_id);
		}

		return $result !== false;
	}

	/**
	 * Get all identities for sync
	 */
	public function getIdentitiesForSync($limit = 50) {
		$identities_table = $this->db->prefix . 'member_identities';

		// Get identities that haven't been synced in the last 24 hours
		$identities = $this->db->getResults(
			$this->db->prepare(
				"SELECT * FROM $identities_table
				WHERE last_sync_at IS NULL OR last_sync_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
				ORDER BY created_at ASC
				LIMIT %d",
				$limit
			)
		);

		return $identities ?: array();
	}

	/**
	 * Generate member DID
	 */
	private function generateMemberDid($user_id, $email) {
		// Generate a deterministic but unique DID using VivalaTable salt
		$salt = VT_Config::get('auth_salt', 'vivalatable_default_salt');
		$hash = substr(md5('user:' . $user_id . ':' . $email . ':' . $salt), 0, 16);
		return 'did:vivalatable:user:' . $hash;
	}

	/**
	 * Generate member handle
	 */
	private function generateMemberHandle($user_id, $display_name) {
		// Create a handle based on display name and user ID
		$base_handle = vt_service('validation.sanitizer')->slug($display_name);
		$base_handle = preg_replace('/[^a-z0-9\-]/', '', $base_handle);

		if (empty($base_handle)) {
			$base_handle = 'user';
		}

		return $base_handle . '.' . $user_id . '.vivalatable.social';
	}

	/**
	 * Get default PDS (Personal Data Server)
	 */
	private function getDefaultPds() {
		// Use VivalaTable's own PDS
		return VT_Config::get('at_protocol_pds', 'pds.vivalatable.social');
	}

	/**
	 * Get default AT Protocol data structure
	 */
	private function getDefaultAtProtocolData() {
		return array(
			'profile' => array(
				'displayName' => '',
				'description' => '',
				'avatar' => '',
				'banner' => '',
			),
			'preferences' => array(
				'public_profile' => true,
				'discoverable' => true,
				'cross_site_sync' => true,
			),
			'sync_status' => array(
				'profile_synced' => false,
				'connections_synced' => false,
				'last_error' => null,
			),
		);
	}

	/**
	 * Get member stats for admin
	 */
	public function getMemberStats() {
		if (!vt_service('auth.service')->currentUserCan('manage_options')) {
			return array();
		}

		$identities_table = $this->db->prefix . 'member_identities';

		$total_identities = $this->db->getVar("SELECT COUNT(*) FROM $identities_table");
		$verified_identities = $this->db->getVar("SELECT COUNT(*) FROM $identities_table WHERE is_verified = 1");
		$synced_identities = $this->db->getVar("SELECT COUNT(*) FROM $identities_table WHERE last_sync_at IS NOT NULL");

		return array(
			'total_identities' => (int) $total_identities,
			'verified_identities' => (int) $verified_identities,
			'synced_identities' => (int) $synced_identities,
			'sync_pending' => (int) ($total_identities - $synced_identities),
		);
	}

	/**
	 * Bulk create identities for existing users (admin function)
	 */
	public function bulkCreateIdentitiesForExistingUsers() {
		if (!vt_service('auth.service')->currentUserCan('manage_options')) {
			return false;
		}

		$users = $this->db->getResults(
			"SELECT id, email, display_name FROM {$this->db->prefix}users WHERE status = 'active'"
		);

		$created_count = 0;

		foreach ($users as $user) {
			$existing_identity = $this->getMemberIdentity($user->id);
			if (!$existing_identity) {
				$did = $this->ensureIdentityExists($user->id, $user->email, $user->display_name);
				if ($did) {
					++$created_count;
				}
			}
		}

		error_log('[VivalaTable] Bulk created ' . $created_count . ' member identities');

		return $created_count;
	}

	/**
	 * Delete member identity when user is deleted
	 */
	public function deleteMemberIdentity($user_id) {
		$identities_table = $this->db->prefix . 'member_identities';

		$result = $this->db->delete(
			'member_identities',
			array('user_id' => $user_id)
		);

		if ($result) {
			error_log('[VivalaTable] Deleted member identity for user ' . $user_id);
		}

		return $result !== false;
	}

	/**
	 * Update PDS URL for an identity
	 */
	public function updatePdsUrl($user_id, $pds_url) {
		$identities_table = $this->db->prefix . 'member_identities';

		$result = $this->db->update(
			'member_identities',
			array(
				'pds_url' => vt_service('validation.sanitizer')->url($pds_url),
				'updated_at' => VT_Time::currentTime('mysql'),
			),
			array('user_id' => $user_id)
		);

		return $result !== false;
	}

	/**
	 * Get identities that need verification
	 */
	public function getUnverifiedIdentities($limit = 50) {
		$identities_table = $this->db->prefix . 'member_identities';

		return $this->db->getResults(
			$this->db->prepare(
				"SELECT * FROM $identities_table
				WHERE is_verified = 0
				ORDER BY created_at ASC
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Search identities by handle or display name
	 */
	public function searchIdentities($search_term, $limit = 20) {
		$identities_table = $this->db->prefix . 'member_identities';
		$search_term = '%' . $this->db->escLike($search_term) . '%';

		return $this->db->getResults(
			$this->db->prepare(
				"SELECT * FROM $identities_table
				WHERE (at_protocol_handle LIKE %s OR display_name LIKE %s)
				AND is_verified = 1
				ORDER BY display_name ASC
				LIMIT %d",
				$search_term, $search_term, $limit
			)
		);
	}

	/**
	 * Record sync error for identity
	 */
	public function recordSyncError($user_id, $error_message) {
		// Get current profile data
		$identity = $this->getMemberIdentity($user_id);
		if (!$identity) {
			return false;
		}

		$profile_data = $identity->at_protocol_data;
		$profile_data['sync_status']['last_error'] = vt_service('validation.sanitizer')->textField($error_message);
		$profile_data['sync_status']['last_error_at'] = VT_Time::currentTime('mysql');

		return $this->updateat_protocol_data($user_id, $profile_data);
	}

	/**
	 * Clear sync error for identity
	 */
	public function clearSyncError($user_id) {
		// Get current profile data
		$identity = $this->getMemberIdentity($user_id);
		if (!$identity) {
			return false;
		}

		$profile_data = $identity->at_protocol_data;
		$profile_data['sync_status']['last_error'] = null;
		unset($profile_data['sync_status']['last_error_at']);

		return $this->updateat_protocol_data($user_id, $profile_data);
	}

	/**
	 * Get identity sync status
	 */
	public function getSyncStatus($user_id) {
		$identity = $this->getMemberIdentity($user_id);
		if (!$identity) {
			return null;
		}

		return $identity->at_protocol_data['sync_status'] ?? array(
			'profile_synced' => false,
			'connections_synced' => false,
			'last_error' => null,
		);
	}

	/**
	 * Mark profile as synced
	 */
	public function markProfileSynced($user_id) {
		$identity = $this->getMemberIdentity($user_id);
		if (!$identity) {
			return false;
		}

		$profile_data = $identity->at_protocol_data;
		$profile_data['sync_status']['profile_synced'] = true;
		$profile_data['sync_status']['last_sync_at'] = VT_Time::currentTime('mysql');

		return $this->updateat_protocol_data($user_id, $profile_data);
	}

	/**
	 * Mark connections as synced
	 */
	public function markConnectionsSynced($user_id) {
		$identity = $this->getMemberIdentity($user_id);
		if (!$identity) {
			return false;
		}

		$profile_data = $identity->at_protocol_data;
		$profile_data['sync_status']['connections_synced'] = true;
		$profile_data['sync_status']['last_connections_sync_at'] = VT_Time::currentTime('mysql');

		return $this->updateat_protocol_data($user_id, $profile_data);
	}
}