<?php
/**
 * VivalaTable Member Display Utilities
 * Handles displaying member information with avatars, display names, and profile links
 * Ported from PartyMinder WordPress plugin
 */

class VT_Member_Display {

	/**
	 * Display member with avatar and display name as profile link
	 *
	 * @param int $user_id User ID
	 * @param array $args Optional display arguments
	 * @return string HTML output
	 */
	public static function getMemberDisplay($user_id, $args = array()) {
		$defaults = array(
			'avatar_size' => 32,
			'show_avatar' => true,
			'show_profile_link' => true,
			'class' => 'vt-member-display',
		);

		$args = array_merge($defaults, $args);

		$user = vt_service('auth.user_repository')->getUserById($user_id);
		if (!$user) {
			return '<span class="' . vt_service('validation.validator')->escAttr($args['class']) . '">Unknown User</span>';
		}

		$profile = VT_Profile_Manager::getUserProfile($user_id);
		$display_name = $profile['display_name'] ?: $user->display_name ?: $user->username;

		$output = '<div class="' . vt_service('validation.validator')->escAttr($args['class']) . ' vt-flex vt-gap-2 vt-items-center">';

		if ($args['show_avatar']) {
			$avatar_html = self::getAvatarHtml($profile, $args['avatar_size']);
			$output .= $avatar_html;
		}

		if ($args['show_profile_link']) {
			$profile_url = VT_Profile_Manager::getProfileUrl($user_id);
			$output .= '<a href="' . vt_service('validation.validator')->escUrl($profile_url) . '" class="vt-text-primary vt-font-medium">';
			$output .= vt_service('validation.validator')->escHtml($display_name);
			$output .= '</a>';
		} else {
			$output .= '<span class="vt-font-medium">' . vt_service('validation.validator')->escHtml($display_name) . '</span>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Echo member display
	 */
	public static function memberDisplay($user_id, $args = array()) {
		echo self::getMemberDisplay($user_id, $args);
	}

	/**
	 * Display host information for events
	 */
	public static function getEventHostDisplay($event, $args = array()) {
		$defaults = array(
			'prefix' => 'Hosted by ',
			'avatar_size' => 24,
		);

		$args = array_merge($defaults, $args);

		$output = vt_service('validation.validator')->escHtml($args['prefix']);

		// Try to get user by author_id first, then fall back to host_email
		if (!empty($event->author_id)) {
			$output .= self::getMemberDisplay($event->author_id, $args);
		} elseif (!empty($event->host_email)) {
			$user = vt_service('auth.user_repository')->getUserByEmail($event->host_email);
			if ($user) {
				$output .= self::getMemberDisplay($user->id, $args);
			} else {
				$output .= '<span class="vt-member-display">' . vt_service('validation.validator')->escHtml($event->host_email) . '</span>';
			}
		} else {
			$output .= '<span class="vt-member-display">Unknown Host</span>';
		}

		return $output;
	}

	/**
	 * Echo event host display
	 */
	public static function eventHostDisplay($event, $args = array()) {
		echo self::getEventHostDisplay($event, $args);
	}

	/**
	 * Get avatar HTML for user
	 */
	private static function getAvatarHtml($profile, $size = 32) {
		$avatar_url = '';

		// Check for custom profile image
		if (!empty($profile['profile_image'])) {
			$avatar_url = VT_Image_Manager::getImageUrl($profile['profile_image']);
		} elseif ($profile['avatar_source'] === 'gravatar' && !empty($profile['email'])) {
			// Use Gravatar
			$hash = md5(strtolower(trim($profile['email'])));
			$avatar_url = "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=identicon";
		}

		if (!$avatar_url) {
			// Default avatar
			$avatar_url = VT_Http::getBaseUrl() . '/assets/images/default-avatar.svg';
		}

		return sprintf(
			'<img src="%s" alt="Avatar" class="vt-avatar" style="width: %dpx; height: %dpx; border-radius: 50%;">',
			vt_service('validation.validator')->escUrl($avatar_url),
			(int) $size,
			(int) $size
		);
	}

	/**
	 * Get user's display name with fallback
	 */
	public static function getDisplayName($user_id) {
		return VT_Profile_Manager::getDisplayName($user_id);
	}

	/**
	 * Generate member list HTML
	 */
	public static function getMemberListHtml($members, $args = array()) {
		$defaults = array(
			'avatar_size' => 24,
			'max_display' => 5,
			'class' => 'vt-member-list',
		);

		$args = array_merge($defaults, $args);

		if (empty($members)) {
			return '<div class="' . vt_service('validation.validator')->escAttr($args['class']) . '">No members</div>';
		}

		$output = '<div class="' . vt_service('validation.validator')->escAttr($args['class']) . ' vt-flex vt-gap-2 vt-flex-wrap">';

		$count = 0;
		foreach ($members as $member) {
			if ($count >= $args['max_display']) {
				$remaining = count($members) - $args['max_display'];
				$output .= '<span class="vt-text-muted vt-text-sm">+' . $remaining . ' more</span>';
				break;
			}

			$member_id = is_object($member) ? $member->user_id : $member;
			$output .= self::getMemberDisplay($member_id, array(
				'avatar_size' => $args['avatar_size'],
				'class' => 'vt-member-list-item',
			));

			$count++;
		}

		$output .= '</div>';

		return $output;
	}
}