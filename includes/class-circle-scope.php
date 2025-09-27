<?php
/**
 * VivalaTable Circle Scope Resolver
 *
 * Implements the three circles of trust:
 * - Close Circle: User's own communities (direct members)
 * - Trusted Circle: Close + members of those communities' other communities
 * - Extended Circle: Trusted + members of those communities' other communities
 *
 * Ported from PartyMinder WordPress plugin
 */
class VT_Circle_Scope {

	/**
	 * Resolve conversation scope for a user and circle level
	 *
	 * @param int $user_id The user to resolve scope for
	 * @param string $circle The circle level: 'close', 'trusted', 'extended'
	 * @return array Array with 'users' and 'communities' that are in scope
	 */
	public static function resolveConversationScope($user_id, $circle) {
		$db = VT_Database::getInstance();

		if (!$user_id || !VT_Auth::isLoggedIn()) {
			// Non-logged users only see public content
			return self::getPublicScope();
		}

		$scope = array(
			'users' => array($user_id), // Always include self
			'communities' => array(),
		);

		switch ($circle) {
			case 'close':
				$scope = self::getCloseCircleScope($user_id);
				break;
			case 'trusted':
				$scope = self::getTrustedCircleScope($user_id);
				break;
			case 'extended':
				$scope = self::getExtendedCircleScope($user_id);
				break;
			default:
				$scope = self::getCloseCircleScope($user_id);
		}

		return $scope;
	}

	/**
	 * Get Close Circle scope
	 * User's own communities and their direct members
	 */
	private static function getCloseCircleScope($user_id) {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';
		$members_table = $db->prefix . 'community_members';

		// Get user's communities
		$user_communities = $db->getCol(
			$db->prepare(
				"SELECT community_id FROM $members_table
				 WHERE user_id = %d AND status = 'active'",
				$user_id
			)
		);

		$scope_users = array($user_id);
		$scope_communities = $user_communities;

		if (!empty($user_communities)) {
			$community_ids_in = implode(',', array_map('intval', $user_communities));

			// Get all members of user's communities
			$close_circle_users = $db->getCol(
				"SELECT DISTINCT user_id FROM $members_table
				 WHERE community_id IN ($community_ids_in) AND status = 'active'"
			);

			$scope_users = array_unique(array_merge($scope_users, $close_circle_users));
		}

		return array(
			'users' => $scope_users,
			'communities' => $scope_communities,
		);
	}

	/**
	 * Get Trusted Circle scope
	 * Close circle + members of those communities' other communities
	 */
	private static function getTrustedCircleScope($user_id) {
		$db = VT_Database::getInstance();
		$members_table = $db->prefix . 'community_members';

		// Start with close circle
		$close_scope = self::getCloseCircleScope($user_id);
		$scope_users = $close_scope['users'];
		$scope_communities = $close_scope['communities'];

		if (!empty($close_scope['users'])) {
			$user_ids_in = implode(',', array_map('intval', $close_scope['users']));

			// Get all communities that close circle members belong to
			$trusted_communities = $db->getCol(
				"SELECT DISTINCT community_id FROM $members_table
				 WHERE user_id IN ($user_ids_in) AND status = 'active'"
			);

			$scope_communities = array_unique(array_merge($scope_communities, $trusted_communities));

			if (!empty($trusted_communities)) {
				$community_ids_in = implode(',', array_map('intval', $trusted_communities));

				// Get all members of trusted communities
				$trusted_users = $db->getCol(
					"SELECT DISTINCT user_id FROM $members_table
					 WHERE community_id IN ($community_ids_in) AND status = 'active'"
				);

				$scope_users = array_unique(array_merge($scope_users, $trusted_users));
			}
		}

		return array(
			'users' => $scope_users,
			'communities' => $scope_communities,
		);
	}

	/**
	 * Get Extended Circle scope
	 * Trusted circle + members of those communities' other communities
	 */
	private static function getExtendedCircleScope($user_id) {
		$db = VT_Database::getInstance();
		$members_table = $db->prefix . 'community_members';

		// Start with trusted circle
		$trusted_scope = self::getTrustedCircleScope($user_id);
		$scope_users = $trusted_scope['users'];
		$scope_communities = $trusted_scope['communities'];

		if (!empty($trusted_scope['users'])) {
			$user_ids_in = implode(',', array_map('intval', $trusted_scope['users']));

			// Get all communities that trusted circle members belong to
			$extended_communities = $db->getCol(
				"SELECT DISTINCT community_id FROM $members_table
				 WHERE user_id IN ($user_ids_in) AND status = 'active'"
			);

			$scope_communities = array_unique(array_merge($scope_communities, $extended_communities));

			if (!empty($extended_communities)) {
				$community_ids_in = implode(',', array_map('intval', $extended_communities));

				// Get all members of extended communities
				$extended_users = $db->getCol(
					"SELECT DISTINCT user_id FROM $members_table
					 WHERE community_id IN ($community_ids_in) AND status = 'active'"
				);

				$scope_users = array_unique(array_merge($scope_users, $extended_users));
			}
		}

		return array(
			'users' => $scope_users,
			'communities' => $scope_communities,
		);
	}

	/**
	 * Get public scope for non-logged users
	 */
	private static function getPublicScope() {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';

		// Get all public communities
		$public_communities = $db->getCol(
			"SELECT id FROM $communities_table
			 WHERE visibility = 'public' AND is_active = 1"
		);

		return array(
			'users' => array(), // No specific users for public scope
			'communities' => $public_communities,
		);
	}

	/**
	 * Check if a conversation is in scope for a user and circle
	 */
	public static function isConversationInScope($conversation, $user_id, $circle) {
		$scope = self::resolveConversationScope($user_id, $circle);

		// Check if conversation author is in scope
		if (in_array($conversation->author_id, $scope['users'])) {
			return true;
		}

		// Check if conversation community is in scope
		if ($conversation->community_id && in_array($conversation->community_id, $scope['communities'])) {
			return true;
		}

		// For public conversations, check if they're in public scope
		if (empty($conversation->community_id) && empty($conversation->event_id)) {
			return true; // General conversations are visible to all circles
		}

		return false;
	}
}