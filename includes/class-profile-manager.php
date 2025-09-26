<?php
/**
 * VivalaTable Profile Manager
 * Handles user profile data and operations
 * Ported from PartyMinder WordPress plugin
 */
class VT_Profile_Manager {

	/**
	 * Get user profile data
	 */
	public static function get_user_profile($user_id) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';

		$profile = $db->get_row(
			$db->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if (!$profile) {
			// Create default profile if it doesn't exist
			return self::create_default_profile($user_id);
		}

		return $profile;
	}

	/**
	 * Create default profile for new user
	 */
	public static function create_default_profile($user_id) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';
		$user_data = VT_Auth::getUserById($user_id);

		$default_data = array(
			'user_id' => $user_id,
			'display_name' => $user_data ? $user_data->display_name : '',
			'bio' => '',
			'location' => '',
			'website' => '',
			'avatar_url' => '',
			'social_links' => json_encode(array()),
			'privacy_settings' => json_encode(
				array(
					'profile_visibility' => 'public',
				)
			),
			'events_hosted' => 0,
			'events_attended' => 0,
			'reputation_score' => 0,
			'last_active_at' => VT_Time::current_time('mysql'),
			'created_at' => VT_Time::current_time('mysql'),
			'updated_at' => VT_Time::current_time('mysql'),
		);

		$result = $db->insert('user_profiles', $default_data);

		if ($result) {
			$default_data['id'] = $db->insert_id;
			return $default_data;
		}

		return array();
	}

	/**
	 * Update user profile
	 */
	public static function update_profile($user_id, $data) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';
		$errors = array();

		// Get current profile data for image deletion
		$current_profile = self::get_user_profile($user_id);

		// Validate input data
		$update_data = array();

		// Display name
		if (isset($data['display_name'])) {
			$display_name = VT_Sanitize::text($data['display_name']);
			if (strlen($display_name) > 255) {
				$errors[] = 'Display name must be 255 characters or less.';
			} else {
				$update_data['display_name'] = $display_name;
			}
		}

		// Bio
		if (isset($data['bio'])) {
			$bio = VT_Sanitize::textarea($data['bio']);
			if (strlen($bio) > 500) {
				$errors[] = 'Bio must be 500 characters or less.';
			} else {
				$update_data['bio'] = $bio;
			}
		}

		// Location
		if (isset($data['location'])) {
			$location = VT_Sanitize::text($data['location']);
			if (strlen($location) > 255) {
				$errors[] = 'Location must be 255 characters or less.';
			} else {
				$update_data['location'] = $location;
			}
		}

		// Avatar source
		if (isset($data['avatar_source'])) {
			$avatar_source = VT_Sanitize::text($data['avatar_source']);
			if (in_array($avatar_source, array('custom', 'gravatar'))) {
				$update_data['avatar_source'] = $avatar_source;
			}
		}

		// Website URL
		if (isset($data['website_url'])) {
			$website = VT_Sanitize::url($data['website_url']);
			if ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
				$errors[] = 'Please enter a valid website URL.';
			} else {
				$update_data['website_url'] = $website;
			}
		}

		// Dietary restrictions
		if (isset($data['dietary_restrictions'])) {
			$update_data['dietary_restrictions'] = VT_Sanitize::textarea($data['dietary_restrictions']);
		}

		// Accessibility needs
		if (isset($data['accessibility_needs'])) {
			$update_data['accessibility_needs'] = VT_Sanitize::textarea($data['accessibility_needs']);
		}

		// Notification preferences
		if (isset($data['notifications']) && is_array($data['notifications'])) {
			$notifications = array();
			$notifications['new_events'] = isset($data['notifications']['new_events']);
			$notifications['event_invitations'] = isset($data['notifications']['event_invitations']);
			$notifications['rsvp_updates'] = isset($data['notifications']['rsvp_updates']);
			$notifications['community_activity'] = isset($data['notifications']['community_activity']);
			$update_data['notification_preferences'] = json_encode($notifications);
		}

		// Privacy settings
		if (isset($data['privacy']) && is_array($data['privacy'])) {
			$privacy = array();
			if (isset($data['privacy']['profile_visibility'])) {
				$visibility = VT_Sanitize::text($data['privacy']['profile_visibility']);
				if (in_array($visibility, array('public', 'community', 'private'))) {
					$privacy['profile_visibility'] = $visibility;
				} else {
					$privacy['profile_visibility'] = 'public';
				}
			}
			$update_data['privacy_settings'] = json_encode($privacy);
		}

		// Handle profile image upload
		if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
			// Delete old profile image if exists
			if (!empty($current_profile['profile_image'])) {
				VT_Image_Manager::delete_image($current_profile['profile_image']);
			}

			$upload_result = VT_Image_Manager::handle_image_upload($_FILES['profile_image'], 'profile', $user_id, 'user');
			if ($upload_result['success']) {
				$update_data['profile_image'] = $upload_result['url'];
			} else {
				$errors[] = $upload_result['error'];
			}
		}

		// Handle cover image upload
		if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
			// Delete old cover image if exists
			if (!empty($current_profile['cover_image'])) {
				VT_Image_Manager::delete_image($current_profile['cover_image']);
			}

			$upload_result = VT_Image_Manager::handle_image_upload($_FILES['cover_image'], 'cover', $user_id, 'user');
			if ($upload_result['success']) {
				$update_data['cover_image'] = $upload_result['url'];
			} else {
				$errors[] = $upload_result['error'];
			}
		}

		// Handle direct image URL data (from AJAX uploads)
		if (isset($data['profile_image'])) {
			$update_data['profile_image'] = VT_Sanitize::url($data['profile_image']);
		}

		if (isset($data['cover_image'])) {
			$update_data['cover_image'] = VT_Sanitize::url($data['cover_image']);
		}

		// Return early if there are validation errors
		if (!empty($errors)) {
			return array(
				'success' => false,
				'errors' => $errors,
			);
		}

		// Add updated timestamp
		$update_data['updated_at'] = VT_Time::current_time('mysql');

		// Check if profile exists
		$existing = $db->get_var(
			$db->prepare(
				"SELECT id FROM $table_name WHERE user_id = %d",
				$user_id
			)
		);

		if ($existing) {
			// Update existing profile
			$result = $db->update(
				'user_profiles',
				$update_data,
				array('user_id' => $user_id)
			);
		} else {
			// Create new profile
			$update_data['user_id'] = $user_id;
			$update_data['created_at'] = VT_Time::current_time('mysql');
			$result = $db->insert('user_profiles', $update_data);
		}

		if ($result !== false) {
			// Update last active time
			self::update_last_active($user_id);

			return array('success' => true);
		} else {
			return array(
				'success' => false,
				'errors' => array('Failed to update profile. Please try again.'),
			);
		}
	}

	/**
	 * Update last active time
	 */
	public static function update_last_active($user_id) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';

		$db->update(
			'user_profiles',
			array('last_active' => VT_Time::current_time('mysql')),
			array('user_id' => $user_id)
		);
	}

	/**
	 * Increment events hosted count
	 */
	public static function increment_events_hosted($user_id) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';

		// Ensure profile exists
		self::get_user_profile($user_id);

		$db->prepare(
			"UPDATE $table_name SET events_hosted = events_hosted + 1, updated_at = %s WHERE user_id = %d",
			VT_Time::current_time('mysql'),
			$user_id
		);
	}

	/**
	 * Increment events attended count
	 */
	public static function increment_events_attended($user_id) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';

		// Ensure profile exists
		self::get_user_profile($user_id);

		$db->query(
			$db->prepare(
				"UPDATE $table_name SET events_attended = events_attended + 1, updated_at = %s WHERE user_id = %d",
				VT_Time::current_time('mysql'),
				$user_id
			)
		);
	}

	/**
	 * Update host rating
	 */
	public static function update_host_rating($user_id, $rating, $review_count = null) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';

		// Ensure profile exists
		self::get_user_profile($user_id);

		$update_data = array(
			'host_rating' => floatval($rating),
			'updated_at' => VT_Time::current_time('mysql'),
		);

		if ($review_count !== null) {
			$update_data['host_reviews_count'] = intval($review_count);
		}

		$db->update(
			'user_profiles',
			$update_data,
			array('user_id' => $user_id)
		);
	}

	/**
	 * Get profiles by visibility setting
	 */
	public static function get_public_profiles($limit = 10, $offset = 0) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';
		$users_table = $db->prefix . 'users';

		$profiles = $db->get_results(
			$db->prepare(
				"SELECT p.*, u.username, u.email
             FROM $table_name p
             LEFT JOIN $users_table u ON p.user_id = u.id
             WHERE p.is_active = 1
             AND JSON_EXTRACT(p.privacy_settings, '$.profile_visibility') = 'public'
             ORDER BY p.last_active DESC
             LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		return $profiles;
	}

	/**
	 * Search profiles
	 */
	public static function search_profiles($search_term, $limit = 10) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';
		$users_table = $db->prefix . 'users';
		$search_term = '%' . $db->esc_like($search_term) . '%';

		$profiles = $db->get_results(
			$db->prepare(
				"SELECT p.*, u.username, u.email
             FROM $table_name p
             LEFT JOIN $users_table u ON p.user_id = u.id
             WHERE p.is_active = 1
             AND JSON_EXTRACT(p.privacy_settings, '$.profile_visibility') = 'public'
             AND (p.display_name LIKE %s OR p.bio LIKE %s OR p.location LIKE %s)
             ORDER BY p.display_name ASC
             LIMIT %d",
				$search_term,
				$search_term,
				$search_term,
				$limit
			),
			ARRAY_A
		);

		return $profiles;
	}

	/**
	 * Check if user can view profile
	 */
	public static function can_view_profile($profile_user_id, $viewing_user_id = null) {
		if (!$viewing_user_id) {
			$viewing_user_id = VT_Auth::getCurrentUserId();
		}

		// Users can always view their own profile
		if ($profile_user_id == $viewing_user_id) {
			return true;
		}

		$profile = self::get_user_profile($profile_user_id);
		$privacy_settings = json_decode($profile['privacy_settings'] ?: '{}', true);
		$visibility = $privacy_settings['profile_visibility'] ?? 'public';

		switch ($visibility) {
			case 'public':
				return true;

			case 'community':
				// Check if users share any communities
				if (class_exists('VT_Community_Manager')) {
					return VT_Community_Manager::users_share_community($profile_user_id, $viewing_user_id);
				}
				return false;

			case 'private':
				return false;

			default:
				return true;
		}
	}

	/**
	 * Get profile URL for user
	 */
	public static function get_profile_url($user_id) {
		if ($user_id == VT_Auth::getCurrentUserId()) {
			return VT_Http::getBaseUrl() . '/profile';
		} else {
			return VT_Http::getBaseUrl() . '/profile/' . $user_id;
		}
	}

	/**
	 * Get user display name with fallback
	 */
	public static function get_display_name($user_id) {
		$profile = self::get_user_profile($user_id);
		if ($profile['display_name']) {
			return $profile['display_name'];
		}

		$user_data = VT_Auth::getUserById($user_id);
		return $user_data ? $user_data->display_name : 'Unknown User';
	}
}