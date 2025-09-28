<?php
/**
 * VivalaTable Personal Community Service
 * Manages personal communities for users (default inner circle communities)
 * Ported from PartyMinder WordPress plugin
 */

class VT_Personal_Community_Service {

	/**
	 * Create a personal community for a user
	 * personal_owner_user_id = $user_id, creator_id = $user_id,
	 * visibility = 'public' (default), slug like pc_{user_id}
	 */
	public static function createForUser($user_id) {
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

		// Check if user already has a personal community
		$existing = self::getPersonalCommunityForUser($user_id);
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

		// Create community with personal_owner_user_id set
		$community_data = array(
			'name' => $user->display_name . "'s Personal Community",
			'slug' => 'pc_' . $user_id,
			'description' => 'Personal social feed for ' . $user->display_name,
			'type' => 'personal',
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

		// Add creator as member with 'active' status
		$member_data = array(
			'community_id' => $community_id,
			'user_id' => $user_id,
			'email' => $user->email,
			'display_name' => $user->display_name,
			'role' => 'admin', // Owner of their personal community
			'status' => 'active',
			'joined_at' => date('Y-m-d H:i:s'),
			'last_seen_at' => date('Y-m-d H:i:s')
		);

		$db->insert($members_table, $member_data);

		return $community_id;
	}

	/**
	 * Get the personal community for a user
	 */
	public static function getPersonalCommunityForUser($user_id) {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';

		return $db->getRow(
			$db->prepare(
				"SELECT * FROM $communities_table
				 WHERE personal_owner_user_id = %d AND is_active = 1",
				$user_id
			)
		);
	}

	/**
	 * Check if a community is a personal community
	 */
	public static function isPersonalCommunity($community_id) {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';

		$community = $db->getRow(
			$db->prepare(
				"SELECT personal_owner_user_id FROM $communities_table
				 WHERE id = %d AND is_active = 1",
				$community_id
			)
		);

		return $community && $community->personal_owner_user_id;
	}

	/**
	 * Ensure user has a personal community (create if needed)
	 * Called during user registration or login
	 */
	public static function ensurePersonalCommunityForUser($user_id) {
		$existing = self::getPersonalCommunityForUser($user_id);
		if (!$existing) {
			return self::createForUser($user_id);
		}
		return $existing->id;
	}

	/**
	 * Backfill personal communities for existing users
	 * Rate limited to avoid database pressure
	 */
	public static function backfillExistingUsers($batch_size = 50) {
		$db = VT_Database::getInstance();
		$users_table = $db->prefix . 'users';

		// Get users without personal communities
		$users = $db->getResults(
			$db->prepare(
				"SELECT u.id FROM $users_table u
				 LEFT JOIN {$db->prefix}communities c ON c.personal_owner_user_id = u.id
				 WHERE c.id IS NULL
				 LIMIT %d",
				$batch_size
			)
		);

		$created = 0;
		foreach ($users as $user) {
			$community_id = self::createForUser($user->id);
			if ($community_id) {
				$created++;
			}
		}

		return $created;
	}
}