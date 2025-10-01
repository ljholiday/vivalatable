<?php
/**
 * VivalaTable Personal Community Service
 * Manages the two-community model for users:
 * 1. [Display Name] Circle - Private personal community (invite-only)
 * 2. [Display Name] - Public community (anyone can join)
 */

class VT_Personal_Community_Service {

	/**
	 * Create private Circle community for a user
	 * Type: 'circle', Privacy: 'private', Name: "{Display Name} Circle" (no apostrophe)
	 */
	public static function createCircleForUser($user_id) {
		$db = VT_Database::getInstance();

		// Get user from database directly
		$users_table = $db->prefix . 'users';
		$user = $db->getRow(
			$db->prepare(
				"SELECT * FROM $users_table WHERE id = %d",
				$user_id
			)
		);

		if (!$user) {
			return false;
		}

		// Check if user already has a Circle
		$existing = self::getCircleForUser($user_id);
		if ($existing) {
			return $existing->id;
		}

		$communities_table = 'communities';
		$members_table = 'community_members';

		// Get user's AT Protocol data from member_identities table if available
		$identities_table = $db->prefix . 'member_identities';
		$identity = $db->getRow(
			$db->prepare(
				"SELECT at_protocol_did, at_protocol_handle FROM $identities_table WHERE user_id = %d",
				$user_id
			)
		);

		// Create Circle community (private, invite-only)
		$community_data = array(
			'name' => $user->display_name . ' Circle',
			'slug' => 'circle-' . $user_id,
			'description' => 'Private circle for ' . $user->display_name,
			'type' => 'circle',
			'privacy' => 'private',
			'personal_owner_user_id' => $user_id,
			'visibility' => 'private',
			'creator_id' => $user_id,
			'creator_email' => $user->email,
			'at_protocol_did' => $identity ? $identity->at_protocol_did : null,
			'at_protocol_handle' => $identity ? $identity->at_protocol_handle : null,
			'is_active' => 1,
			'created_by' => $user_id,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		);

		$result = $db->insert($communities_table, $community_data);

		if (!$result) {
			return false;
		}

		$community_id = $db->insert_id;

		// Add creator as admin member
		$member_data = array(
			'community_id' => $community_id,
			'user_id' => $user_id,
			'email' => $user->email,
			'display_name' => $user->display_name,
			'role' => 'admin',
			'status' => 'active',
			'joined_at' => date('Y-m-d H:i:s'),
			'last_seen_at' => date('Y-m-d H:i:s')
		);

		$db->insert($members_table, $member_data);

		return $community_id;
	}

	/**
	 * Create public community for a user
	 * Type: 'public', Privacy: 'public', Name: "{Display Name}" (no apostrophe, just name)
	 */
	public static function createPublicCommunityForUser($user_id) {
		$db = VT_Database::getInstance();

		// Get user from database directly
		$users_table = $db->prefix . 'users';
		$user = $db->getRow(
			$db->prepare(
				"SELECT * FROM $users_table WHERE id = %d",
				$user_id
			)
		);

		if (!$user) {
			return false;
		}

		// Check if user already has a public community
		$existing = self::getPublicCommunityForUser($user_id);
		if ($existing) {
			return $existing->id;
		}

		$communities_table = 'communities';
		$members_table = 'community_members';

		// Get user's AT Protocol data from member_identities table if available
		$identities_table = $db->prefix . 'member_identities';
		$identity = $db->getRow(
			$db->prepare(
				"SELECT at_protocol_did, at_protocol_handle FROM $identities_table WHERE user_id = %d",
				$user_id
			)
		);

		// Create public community (public, anyone can join)
		$community_data = array(
			'name' => $user->display_name,
			'slug' => 'pub-' . $user_id,
			'description' => 'Public community for ' . $user->display_name,
			'type' => 'public',
			'privacy' => 'public',
			'personal_owner_user_id' => $user_id,
			'visibility' => 'public',
			'creator_id' => $user_id,
			'creator_email' => $user->email,
			'at_protocol_did' => $identity ? $identity->at_protocol_did : null,
			'at_protocol_handle' => $identity ? $identity->at_protocol_handle : null,
			'is_active' => 1,
			'created_by' => $user_id,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		);

		$result = $db->insert($communities_table, $community_data);

		if (!$result) {
			return false;
		}

		$community_id = $db->insert_id;

		// Add creator as admin member
		$member_data = array(
			'community_id' => $community_id,
			'user_id' => $user_id,
			'email' => $user->email,
			'display_name' => $user->display_name,
			'role' => 'admin',
			'status' => 'active',
			'joined_at' => date('Y-m-d H:i:s'),
			'last_seen_at' => date('Y-m-d H:i:s')
		);

		$db->insert($members_table, $member_data);

		return $community_id;
	}

	/**
	 * Get the Circle (private) community for a user
	 */
	public static function getCircleForUser($user_id) {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';

		return $db->getRow(
			$db->prepare(
				"SELECT * FROM $communities_table
				 WHERE personal_owner_user_id = %d AND type = 'circle' AND is_active = 1",
				$user_id
			)
		);
	}

	/**
	 * Get the public community for a user
	 */
	public static function getPublicCommunityForUser($user_id) {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';

		return $db->getRow(
			$db->prepare(
				"SELECT * FROM $communities_table
				 WHERE personal_owner_user_id = %d AND type = 'public' AND is_active = 1",
				$user_id
			)
		);
	}

	/**
	 * Get ANY personal community for a user (backward compatibility)
	 * Returns Circle if exists, otherwise public community
	 */
	public static function getPersonalCommunityForUser($user_id) {
		$circle = self::getCircleForUser($user_id);
		if ($circle) {
			return $circle;
		}
		return self::getPublicCommunityForUser($user_id);
	}

	/**
	 * Check if a community is a personal community (Circle or Public)
	 */
	public static function isPersonalCommunity($community_id) {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';

		$community = $db->getRow(
			$db->prepare(
				"SELECT personal_owner_user_id, type FROM $communities_table
				 WHERE id = %d AND is_active = 1",
				$community_id
			)
		);

		return $community && $community->personal_owner_user_id && in_array($community->type, ['circle', 'public']);
	}

	/**
	 * Check if a community is a Circle (private personal community)
	 */
	public static function isCircle($community_id) {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';

		$community = $db->getRow(
			$db->prepare(
				"SELECT type FROM $communities_table
				 WHERE id = %d AND is_active = 1",
				$community_id
			)
		);

		return $community && $community->type === 'circle';
	}

	/**
	 * Ensure user has BOTH communities (create if needed)
	 * Called during user registration
	 * Returns array with both community IDs
	 */
	public static function ensureBothCommunitiesForUser($user_id) {
		$circle_id = self::getCircleForUser($user_id);
		$public_id = self::getPublicCommunityForUser($user_id);

		// Create Circle if missing
		if (!$circle_id) {
			$circle_id = self::createCircleForUser($user_id);
		} else {
			$circle_id = $circle_id->id;
		}

		// Create Public community if missing
		if (!$public_id) {
			$public_id = self::createPublicCommunityForUser($user_id);
		} else {
			$public_id = $public_id->id;
		}

		return array(
			'circle_id' => $circle_id,
			'public_id' => $public_id
		);
	}

	/**
	 * Legacy method - now creates BOTH communities
	 * @deprecated Use ensureBothCommunitiesForUser() instead
	 */
	public static function ensurePersonalCommunityForUser($user_id) {
		$result = self::ensureBothCommunitiesForUser($user_id);
		return $result['circle_id']; // Return Circle ID for backward compatibility
	}

	/**
	 * Backfill: Create missing communities for existing users
	 * Rate limited to avoid database pressure
	 */
	public static function backfillExistingUsers($batch_size = 50) {
		$db = VT_Database::getInstance();
		$users_table = $db->prefix . 'users';

		// Get all active users
		$users = $db->getResults(
			$db->prepare(
				"SELECT id, display_name FROM $users_table WHERE status = 'active' LIMIT %d",
				$batch_size
			)
		);

		$created = 0;
		foreach ($users as $user) {
			$result = self::ensureBothCommunitiesForUser($user->id);
			if ($result['circle_id'] || $result['public_id']) {
				$created++;
			}
		}

		return $created;
	}

	/**
	 * Fix existing old-style personal communities
	 * Updates communities with type='personal' to proper two-community model
	 */
	public static function fixLegacyPersonalCommunities() {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';

		// Find all old-style personal communities (type='personal' or has apostrophe in name)
		$old_communities = $db->getResults(
			"SELECT * FROM $communities_table
			 WHERE (type = 'personal' OR name LIKE \"%'s %\")
			 AND personal_owner_user_id IS NOT NULL
			 AND is_active = 1"
		);

		$fixed = 0;
		foreach ($old_communities as $community) {
			$user_id = $community->personal_owner_user_id;

			// Get user
			$users_table = $db->prefix . 'users';
			$user = $db->getRow(
				$db->prepare("SELECT * FROM $users_table WHERE id = %d", $user_id)
			);

			if (!$user) {
				continue;
			}

			// Update this community to be the Circle
			$db->update(
				'communities',
				array(
					'name' => $user->display_name . ' Circle',
					'slug' => 'circle-' . $user_id,
					'description' => 'Private circle for ' . $user->display_name,
					'type' => 'circle',
					'privacy' => 'private',
					'visibility' => 'private',
					'updated_at' => date('Y-m-d H:i:s')
				),
				array('id' => $community->id)
			);

			// Create the public community if it doesn't exist
			self::createPublicCommunityForUser($user_id);

			$fixed++;
		}

		return $fixed;
	}
}
