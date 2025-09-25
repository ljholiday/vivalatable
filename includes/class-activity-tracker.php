<?php
/**
 * VivalaTable Activity Tracker
 * Tracks user activity and notifications
 * Ported from PartyMinder WordPress plugin
 */

class VT_Activity_Tracker {

	/**
	 * Track when user last saw an item
	 */
	public static function track_user_activity($user_id, $activity_type, $item_id) {
		if (!$user_id || !$activity_type || !$item_id) {
			return false;
		}

		$db = VT_Database::getInstance();
		$table = $db->prefix . 'user_activity_tracking';

		return $db->replace(
			'user_activity_tracking',
			array(
				'user_id' => $user_id,
				'activity_type' => $activity_type,
				'item_id' => $item_id,
				'last_seen_at' => VT_Time::current_time('mysql')
			)
		);
	}

	/**
	 * Get user's last seen time for an item
	 */
	public static function get_last_seen($user_id, $activity_type, $item_id) {
		if (!$user_id || !$activity_type || !$item_id) {
			return null;
		}

		$db = VT_Database::getInstance();
		$table = $db->prefix . 'user_activity_tracking';

		return $db->get_var(
			$db->prepare(
				"SELECT last_seen_at FROM $table
				 WHERE user_id = %d AND activity_type = %s AND item_id = %d",
				$user_id, $activity_type, $item_id
			)
		);
	}

	/**
	 * Check if item has new activity since user last saw it
	 */
	public static function has_new_activity($user_id, $activity_type, $item_id, $item_updated_at) {
		$last_seen = self::get_last_seen($user_id, $activity_type, $item_id);

		if (!$last_seen) {
			return true; // Never seen = new
		}

		return strtotime($item_updated_at) > strtotime($last_seen);
	}

	/**
	 * Get count of new items for activity type
	 */
	public static function get_new_count($user_id, $activity_type, $items) {
		if (!$user_id || !$activity_type || empty($items)) {
			return 0;
		}

		$new_count = 0;
		foreach ($items as $item) {
			$updated_at = isset($item->updated_at) ? $item->updated_at : $item->created_at;
			if (self::has_new_activity($user_id, $activity_type, $item->id, $updated_at)) {
				$new_count++;
			}
		}

		return $new_count;
	}
}