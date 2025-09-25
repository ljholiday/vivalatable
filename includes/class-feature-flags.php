<?php
/**
 * VivalaTable Feature Flags
 * Safe deployment system for new features
 * Ported from PartyMinder WordPress plugin
 */
class VT_Feature_Flags {

	/**
	 * Check if communities feature is enabled
	 * Communities are core functionality for a social network - always enabled
	 */
	public static function is_communities_enabled() {
		return true;
	}

	/**
	 * Check if AT Protocol feature is enabled
	 */
	public static function is_at_protocol_enabled() {
		return VT_Config::get('at_protocol_enabled', true);
	}

	/**
	 * Check if communities require approval
	 */
	public static function communities_require_approval() {
		return (bool) VT_Config::get('communities_require_approval', true);
	}

	/**
	 * Get max communities per user
	 */
	public static function get_max_communities_per_user() {
		return (int) VT_Config::get('max_communities_per_user', 10);
	}

	/**
	 * Check if user can create communities
	 */
	public static function can_user_create_community($user_id = null) {
		if (!$user_id) {
			$user_id = VT_Auth::getCurrentUserId();
		}

		if (!$user_id) {
			return false; // Guest users cannot create communities
		}

		// Check if user has reached their limit
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';
		$user_community_count = $db->get_var(
			$db->prepare(
				"SELECT COUNT(*) FROM $communities_table WHERE creator_id = %d AND is_active = 1",
				$user_id
			)
		);

		return $user_community_count < self::get_max_communities_per_user();
	}

	/**
	 * Check if user can join communities
	 */
	public static function can_user_join_community($user_id = null) {
		if (!$user_id) {
			$user_id = VT_Auth::getCurrentUserId();
		}

		return $user_id > 0; // Must be registered user
	}

	/**
	 * Check if communities feature should show in admin
	 */
	public static function show_communities_in_admin() {
		return VT_Auth::currentUserCan('manage_options');
	}

	/**
	 * Check if AT Protocol features should show in admin
	 */
	public static function show_at_protocol_in_admin() {
		return VT_Auth::currentUserCan('manage_options') && self::is_at_protocol_enabled();
	}

	/**
	 * Get feature status for JavaScript
	 */
	public static function get_feature_status_for_js() {
		return array(
			'communities_enabled' => self::is_communities_enabled(),
			'at_protocol_enabled' => self::is_at_protocol_enabled(),
			'can_create_community' => self::can_user_create_community(),
			'can_join_community' => self::can_user_join_community(),
			'max_communities_per_user' => self::get_max_communities_per_user(),
		);
	}

	/**
	 * Enable communities feature (admin only)
	 */
	public static function enable_communities() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}

		VT_Config::set('enable_communities', true);

		// Log the feature activation
		error_log('[VivalaTable] Communities feature enabled by user ID: ' . VT_Auth::getCurrentUserId());

		return true;
	}

	/**
	 * Disable communities feature (admin only)
	 */
	public static function disable_communities() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}

		VT_Config::set('enable_communities', false);

		// Log the feature deactivation
		error_log('[VivalaTable] Communities feature disabled by user ID: ' . VT_Auth::getCurrentUserId());

		return true;
	}

	/**
	 * Enable AT Protocol feature (admin only)
	 */
	public static function enable_at_protocol() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}

		VT_Config::set('at_protocol_enabled', true);

		// Log the feature activation
		error_log('[VivalaTable] AT Protocol feature enabled by user ID: ' . VT_Auth::getCurrentUserId());

		return true;
	}

	/**
	 * Disable AT Protocol feature (admin only)
	 */
	public static function disable_at_protocol() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}

		VT_Config::set('at_protocol_enabled', false);

		// Log the feature deactivation
		error_log('[VivalaTable] AT Protocol feature disabled by user ID: ' . VT_Auth::getCurrentUserId());

		return true;
	}

	// ========================================
	// CIRCLES IMPLEMENTATION FEATURE FLAGS
	// ========================================
	// Per Step 0 of the circles implementation plan
	//
	// ROLLBACK PLAN FOR EACH FLAG:
	// 1. circles_schema: Disabling won't affect existing data, but new schema operations will be skipped
	// 2. personal_community_new_users: Disabling stops creating personal communities for new users
	// 3. personal_community_backfill: Disabling stops the backfill process for existing users
	// 4. general_convo_default_to_personal: Disabling reverts to original conversation creation logic
	// 5. reply_join_flow: Disabling reverts to standard reply handling without auto-join
	// 6. circles_resolver: Disabling turns off inner/trusted/extended circle logic
	// 7. convo_feed_by_circle: Disabling shows all conversations instead of filtered by circle
	// 8. circles_nav_ui: Disabling hides the circles navigation and shows standard UI

	/**
	 * Step 1: Database schema changes
	 */
	public static function is_circles_schema_enabled() {
		return (bool) VT_Config::get('circles_schema', false);
	}

	/**
	 * Step 2: Personal communities for new users
	 */
	public static function is_personal_community_new_users_enabled() {
		return (bool) VT_Config::get('personal_community_new_users', false);
	}

	/**
	 * Step 3: Personal communities backfill for existing users
	 */
	public static function is_personal_community_backfill_enabled() {
		return (bool) VT_Config::get('personal_community_backfill', false);
	}

	/**
	 * Step 4: General conversations default to personal communities
	 */
	public static function is_general_convo_default_to_personal_enabled() {
		return true;
	}

	/**
	 * Step 5: Reply join flow for personal communities
	 */
	public static function is_reply_join_flow_enabled() {
		return (bool) VT_Config::get('reply_join_flow', false);
	}

	/**
	 * Step 6: Circles resolver (inner/trusted/extended logic)
	 */
	public static function is_circles_resolver_enabled() {
		return true;
	}

	/**
	 * Step 7: Conversation feeds filtered by circle
	 */
	public static function is_convo_feed_by_circle_enabled() {
		return (bool) VT_Config::get('convo_feed_by_circle', false);
	}

	/**
	 * Step 8: Circles navigation UI (3-button secondary nav)
	 */
	public static function is_circles_nav_ui_enabled() {
		return (bool) VT_Config::get('circles_nav_ui', false);
	}

	/**
	 * Admin methods to enable/disable circles flags
	 */
	public static function enable_circles_schema() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}
		VT_Config::set('circles_schema', true);
		error_log('[VivalaTable] Circles schema enabled by user ID: ' . VT_Auth::getCurrentUserId());
		return true;
	}

	public static function enable_personal_community_new_users() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}
		VT_Config::set('personal_community_new_users', true);
		error_log('[VivalaTable] Personal communities for new users enabled by user ID: ' . VT_Auth::getCurrentUserId());
		return true;
	}

	public static function enable_personal_community_backfill() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}
		VT_Config::set('personal_community_backfill', true);
		error_log('[VivalaTable] Personal communities backfill enabled by user ID: ' . VT_Auth::getCurrentUserId());
		return true;
	}

	public static function enable_general_convo_default_to_personal() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}
		VT_Config::set('general_convo_default_to_personal', true);
		error_log('[VivalaTable] General conversations default to personal enabled by user ID: ' . VT_Auth::getCurrentUserId());
		return true;
	}

	public static function enable_reply_join_flow() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}
		VT_Config::set('reply_join_flow', true);
		error_log('[VivalaTable] Reply join flow enabled by user ID: ' . VT_Auth::getCurrentUserId());
		return true;
	}

	public static function enable_circles_resolver() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}
		VT_Config::set('circles_resolver', true);
		error_log('[VivalaTable] Circles resolver enabled by user ID: ' . VT_Auth::getCurrentUserId());
		return true;
	}

	public static function enable_convo_feed_by_circle() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}
		VT_Config::set('convo_feed_by_circle', true);
		error_log('[VivalaTable] Conversation feed by circle enabled by user ID: ' . VT_Auth::getCurrentUserId());
		return true;
	}

	public static function enable_circles_nav_ui() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return false;
		}
		VT_Config::set('circles_nav_ui', true);
		error_log('[VivalaTable] Circles navigation UI enabled by user ID: ' . VT_Auth::getCurrentUserId());
		return true;
	}

	/**
	 * Get all feature flags for debugging
	 */
	public static function get_all_flags() {
		if (!VT_Auth::currentUserCan('manage_options')) {
			return array();
		}

		return array(
			'communities_enabled' => self::is_communities_enabled(),
			'at_protocol_enabled' => self::is_at_protocol_enabled(),
			'communities_require_approval' => self::communities_require_approval(),
			'max_communities_per_user' => self::get_max_communities_per_user(),
			'circles_schema' => self::is_circles_schema_enabled(),
			'personal_community_new_users' => self::is_personal_community_new_users_enabled(),
			'personal_community_backfill' => self::is_personal_community_backfill_enabled(),
			'general_convo_default_to_personal' => self::is_general_convo_default_to_personal_enabled(),
			'reply_join_flow' => self::is_reply_join_flow_enabled(),
			'circles_resolver' => self::is_circles_resolver_enabled(),
			'convo_feed_by_circle' => self::is_convo_feed_by_circle_enabled(),
			'circles_nav_ui' => self::is_circles_nav_ui_enabled(),
		);
	}
}