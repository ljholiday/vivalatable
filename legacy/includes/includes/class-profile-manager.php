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
	public static function getUserProfile($user_id) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';

		$profile = $db->getRow(
			$db->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if (!$profile) {
			// Create default profile if it doesn't exist
			return self::createDefaultProfile($user_id);
		}

		return $profile;
	}

	/**
	 * Create default profile for new user
	 */
	public static function createDefaultProfile($user_id) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';
		$user_data = vt_service('auth.user_repository')->getUserById($user_id);

		$default_data = array(
			'user_id' => $user_id,
			'display_name' => $user_data ? $user_data->display_name : '',
			'bio' => '',
			'location' => '',
			'website_url' => '',
			'profile_image' => '',
			'cover_image' => '',
			'social_links' => json_encode(array()),
			'hosting_preferences' => json_encode(array()),
			'available_times' => json_encode(array()),
			'dietary_restrictions' => '',
			'accessibility_needs' => '',
			'notification_preferences' => json_encode(array()),
			'privacy_settings' => json_encode(
				array(
					'profile_visibility' => 'public',
				)
			),
			'events_hosted' => 0,
			'events_attended' => 0,
			'host_rating' => 0.00,
			'host_reviews_count' => 0,
			'created_at' => VT_Time::currentTime('mysql'),
			'updated_at' => VT_Time::currentTime('mysql'),
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
	public static function updateProfile($user_id, $data) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';
		$errors = array();

		// Get current profile data for image deletion
		$current_profile = self::getUserProfile($user_id);

		// Validate input data
		$update_data = array();

		// Display name
		if (isset($data['display_name'])) {
			$display_name = vt_service('validation.sanitizer')->textField($data['display_name']);
			if (strlen($display_name) > 255) {
				$errors[] = 'Display name must be 255 characters or less.';
			} else {
				$update_data['display_name'] = $display_name;
			}
		}

		// Bio
		if (isset($data['bio'])) {
			$bio = vt_service('validation.sanitizer')->textarea($data['bio']);
			if (strlen($bio) > 500) {
				$errors[] = 'Bio must be 500 characters or less.';
			} else {
				$update_data['bio'] = $bio;
			}
		}

		// Location
		if (isset($data['location'])) {
			$location = vt_service('validation.sanitizer')->textField($data['location']);
			if (strlen($location) > 255) {
				$errors[] = 'Location must be 255 characters or less.';
			} else {
				$update_data['location'] = $location;
			}
		}

		// Avatar source
		if (isset($data['avatar_source'])) {
			$avatar_source = vt_service('validation.sanitizer')->textField($data['avatar_source']);
			if (in_array($avatar_source, array('custom', 'gravatar'))) {
				$update_data['avatar_source'] = $avatar_source;
			}
		}

		// Website URL
		if (isset($data['website_url'])) {
			$website = vt_service('validation.sanitizer')->url($data['website_url']);
			if ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
				$errors[] = 'Please enter a valid website URL.';
			} else {
				$update_data['website_url'] = $website;
			}
		}

		// Dietary restrictions
		if (isset($data['dietary_restrictions'])) {
			$update_data['dietary_restrictions'] = vt_service('validation.sanitizer')->textarea($data['dietary_restrictions']);
		}

		// Accessibility needs
		if (isset($data['accessibility_needs'])) {
			$update_data['accessibility_needs'] = vt_service('validation.sanitizer')->textarea($data['accessibility_needs']);
		}

		// Hosting preferences
		if (isset($data['hosting_preferences'])) {
			if (is_array($data['hosting_preferences'])) {
				$update_data['hosting_preferences'] = json_encode($data['hosting_preferences']);
			} else {
				$update_data['hosting_preferences'] = vt_service('validation.sanitizer')->textarea($data['hosting_preferences']);
			}
		}

		// Available times for hosting
		if (isset($data['available_times'])) {
			if (is_array($data['available_times'])) {
				$update_data['available_times'] = json_encode($data['available_times']);
			} else {
				$update_data['available_times'] = vt_service('validation.sanitizer')->textarea($data['available_times']);
			}
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
				$visibility = vt_service('validation.sanitizer')->textField($data['privacy']['profile_visibility']);
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
				VT_Image_Manager::deleteImage($current_profile['profile_image']);
			}

			$upload_result = VT_Image_Manager::handleImageUpload($_FILES['profile_image'], 'profile', $user_id, 'user');
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
				VT_Image_Manager::deleteImage($current_profile['cover_image']);
			}

			$upload_result = VT_Image_Manager::handleImageUpload($_FILES['cover_image'], 'cover', $user_id, 'user');
			if ($upload_result['success']) {
				$update_data['cover_image'] = $upload_result['url'];
			} else {
				$errors[] = $upload_result['error'];
			}
		}

		// Handle direct image URL data (from AJAX uploads)
		if (isset($data['profile_image'])) {
			$update_data['profile_image'] = vt_service('validation.sanitizer')->url($data['profile_image']);
		}

		if (isset($data['cover_image'])) {
			$update_data['cover_image'] = vt_service('validation.sanitizer')->url($data['cover_image']);
		}

		// Return early if there are validation errors
		if (!empty($errors)) {
			return array(
				'success' => false,
				'errors' => $errors,
			);
		}

		// Add updated timestamp
		$update_data['updated_at'] = VT_Time::currentTime('mysql');

		// Check if profile exists
		$existing = $db->getVar(
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
			$update_data['created_at'] = VT_Time::currentTime('mysql');
			$result = $db->insert('user_profiles', $update_data);
		}

		if ($result !== false) {
			// Update last active time
			self::updateLastActive($user_id);

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
	public static function updateLastActive($user_id) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';

		$db->update(
			'user_profiles',
			array('last_active' => VT_Time::currentTime('mysql')),
			array('user_id' => $user_id)
		);
	}

	/**
	 * Increment events hosted count
	 */
	public static function incrementEventsHosted($user_id) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';

		// Ensure profile exists
		self::getUserProfile($user_id);

		$db->prepare(
			"UPDATE $table_name SET events_hosted = events_hosted + 1, updated_at = %s WHERE user_id = %d",
			VT_Time::currentTime('mysql'),
			$user_id
		);
	}

	/**
	 * Increment events attended count
	 */
	public static function incrementEventsAttended($user_id) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';

		// Ensure profile exists
		self::getUserProfile($user_id);

		$db->query(
			$db->prepare(
				"UPDATE $table_name SET events_attended = events_attended + 1, updated_at = %s WHERE user_id = %d",
				VT_Time::currentTime('mysql'),
				$user_id
			)
		);
	}

	/**
	 * Update host rating
	 */
	public static function updateHostRating($user_id, $rating, $review_count = null) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';

		// Ensure profile exists
		self::getUserProfile($user_id);

		$update_data = array(
			'host_rating' => floatval($rating),
			'updated_at' => VT_Time::currentTime('mysql'),
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
	public static function getPublicProfiles($limit = 10, $offset = 0) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';
		$users_table = $db->prefix . 'users';

		$profiles = $db->getResults(
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
	public static function searchProfiles($search_term, $limit = 10) {
		$db = VT_Database::getInstance();
		$table_name = $db->prefix . 'user_profiles';
		$users_table = $db->prefix . 'users';
		$search_term = '%' . $db->escLike($search_term) . '%';

		$profiles = $db->getResults(
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
	public static function canViewProfile($profile_user_id, $viewing_user_id = null) {
		if (!$viewing_user_id) {
			$viewing_user_id = vt_service('auth.service')->getCurrentUserId();
		}

		// Users can always view their own profile
		if ($profile_user_id == $viewing_user_id) {
			return true;
		}

		$profile = self::getUserProfile($profile_user_id);
		$privacy_settings = json_decode($profile['privacy_settings'] ?: '{}', true);
		$visibility = $privacy_settings['profile_visibility'] ?? 'public';

		switch ($visibility) {
			case 'public':
				return true;

			case 'community':
				// Check if users share any communities
				if (class_exists('VT_Community_Manager')) {
					return VT_Community_Manager::usersShareCommunity($profile_user_id, $viewing_user_id);
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
	public static function getProfileUrl($user_id) {
		if ($user_id == vt_service('auth.service')->getCurrentUserId()) {
			return VT_Http::getBaseUrl() . '/profile';
		} else {
			return VT_Http::getBaseUrl() . '/profile/' . $user_id;
		}
	}

	/**
	 * Get user display name with fallback
	 */
	public static function getDisplayName($user_id) {
		$profile = self::getUserProfile($user_id);
		if ($profile['display_name']) {
			return $profile['display_name'];
		}

		$user_data = vt_service('auth.user_repository')->getUserById($user_id);
		return $user_data ? $user_data->display_name : 'Unknown User';
	}
}