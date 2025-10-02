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
		if (empty($datetime)) {
			return 'unknown';
		}
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

	/**
	 * Extract first URL from text
	 */
	public static function firstUrlInText($text) {
		if (empty($text)) {
			return null;
		}

		// Match URLs with http/https protocol
		preg_match('/https?:\/\/[^\s<>"\']+/i', $text, $matches);

		return $matches[0] ?? null;
	}

	/**
	 * Convert double line breaks to paragraphs (like WordPress autop)
	 */
	public static function autop($text) {
		if (empty($text)) {
			return '';
		}

		// Normalize line breaks
		$text = str_replace(["\r\n", "\r"], "\n", $text);

		// Remove leading/trailing whitespace
		$text = trim($text);

		// Split by double line breaks
		$paragraphs = preg_split('/\n\s*\n/', $text);

		// Wrap each paragraph in <p> tags
		$paragraphs = array_map(function($paragraph) {
			$paragraph = trim($paragraph);
			if (empty($paragraph)) {
				return '';
			}
			// Don't wrap if already wrapped in block-level tags
			if (preg_match('/^<(div|blockquote|ul|ol|pre|table|h[1-6])/i', $paragraph)) {
				return $paragraph;
			}
			return '<p>' . $paragraph . '</p>';
		}, $paragraphs);

		return implode("\n\n", array_filter($paragraphs));
	}

	/**
	 * Extract all URLs from text
	 */
	public static function extractUrls($text) {
		if (empty($text)) {
			return [];
		}

		preg_match_all('/https?:\/\/[^\s<>"\']+/i', $text, $matches);

		return $matches[0] ?? [];
	}
}