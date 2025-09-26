<?php
/**
 * VivalaTable Text Utilities
 * Text manipulation and formatting functions
 */

class VT_Text {

	/**
	 * Truncate text to a specific length
	 */
	public static function truncate($text, $length = 50, $suffix = '...') {
		if (strlen($text) <= $length) {
			return $text;
		}

		return substr($text, 0, $length) . $suffix;
	}

	/**
	 * Truncate text by words
	 */
	public static function truncateWords($text, $words = 10, $suffix = '...') {
		$word_array = explode(' ', $text);
		if (count($word_array) <= $words) {
			return $text;
		}

		return implode(' ', array_slice($word_array, 0, $words)) . $suffix;
	}

	/**
	 * Convert text to slug
	 */
	public static function toSlug($text) {
		// Convert to lowercase
		$text = strtolower($text);

		// Replace spaces and special characters with dashes
		$text = preg_replace('/[^a-z0-9\-]/', '-', $text);

		// Remove multiple consecutive dashes
		$text = preg_replace('/-+/', '-', $text);

		// Trim dashes from beginning and end
		return trim($text, '-');
	}

	/**
	 * Create excerpt from text
	 */
	public static function excerpt($text, $length = 150) {
		$text = strip_tags($text);
		return self::truncate($text, $length);
	}

	/**
	 * Format time ago
	 */
	public static function timeAgo($datetime) {
		$time = time() - strtotime($datetime);

		if ($time < 60) {
			return 'just now';
		}

		$time_units = array(
			31536000 => 'year',
			2592000 => 'month',
			604800 => 'week',
			86400 => 'day',
			3600 => 'hour',
			60 => 'minute'
		);

		foreach ($time_units as $unit => $text) {
			if ($time < $unit) {
				continue;
			}

			$number_of_units = floor($time / $unit);
			return $number_of_units . ' ' . $text . (($number_of_units > 1) ? 's' : '') . ' ago';
		}

		return 'just now';
	}

	/**
	 * Generate random string
	 */
	public static function random($length = 10) {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$result = '';

		for ($i = 0; $i < $length; $i++) {
			$result .= $chars[rand(0, strlen($chars) - 1)];
		}

		return $result;
	}

	/**
	 * Generate secure random token
	 */
	public static function randomToken($length = 32) {
		return bin2hex(random_bytes($length / 2));
	}
}