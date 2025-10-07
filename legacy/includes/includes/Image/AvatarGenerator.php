<?php
/**
 * Avatar Generator
 * Generates custom avatars from user initials with colorful backgrounds
 */

class VT_Avatar_Generator {

	/**
	 * Color palette for avatar backgrounds
	 * Carefully selected for good contrast with white text
	 */
	private static $colorPalette = [
		'#667eea', // Primary purple
		'#764ba2', // Deep purple
		'#f093fb', // Pink
		'#4facfe', // Blue
		'#00f2fe', // Cyan
		'#43e97b', // Green
		'#38f9d7', // Teal
		'#fa709a', // Coral
		'#fee140', // Yellow (dark text)
		'#30cfd0', // Turquoise
		'#a8edea', // Light cyan (dark text)
		'#fbc2eb', // Light pink (dark text)
	];

	/**
	 * Generate avatar image from display name
	 */
	public static function generate(string $displayName, int $size = 120): ?string {
		if (empty($displayName)) {
			return null;
		}

		// Get initials (max 2 characters)
		$initials = self::getInitials($displayName);

		// Get consistent color for this name
		$bgColor = self::getColorForName($displayName);
		$textColor = self::getTextColorForBackground($bgColor);

		// Create image
		$image = imagecreatetruecolor($size, $size);
		if (!$image) {
			return null;
		}

		// Parse hex color to RGB
		$bgRgb = self::hexToRgb($bgColor);
		$textRgb = self::hexToRgb($textColor);

		// Allocate colors
		$bg = imagecolorallocate($image, $bgRgb['r'], $bgRgb['g'], $bgRgb['b']);
		$text = imagecolorallocate($image, $textRgb['r'], $textRgb['g'], $textRgb['b']);

		// Fill background
		imagefilledrectangle($image, 0, 0, $size, $size, $bg);

		// Add initials text
		$fontSize = $size * 0.4; // 40% of image size
		$font = self::getSystemFont();

		if ($font) {
			// Use TrueType font if available
			$bbox = imagettfbbox($fontSize, 0, $font, $initials);
			$textWidth = abs($bbox[4] - $bbox[0]);
			$textHeight = abs($bbox[5] - $bbox[1]);

			$x = ($size - $textWidth) / 2;
			$y = ($size + $textHeight) / 2;

			imagettftext($image, $fontSize, 0, $x, $y, $text, $font, $initials);
		} else {
			// Fallback to built-in font
			$fontIndex = 5; // Largest built-in font
			$textWidth = imagefontwidth($fontIndex) * strlen($initials);
			$textHeight = imagefontheight($fontIndex);

			$x = ($size - $textWidth) / 2;
			$y = ($size - $textHeight) / 2;

			imagestring($image, $fontIndex, $x, $y, $initials, $text);
		}

		// Convert to base64 data URL
		ob_start();
		imagepng($image, null, 8);
		$imageData = ob_get_clean();
		imagedestroy($image);

		return 'data:image/png;base64,' . base64_encode($imageData);
	}

	/**
	 * Get initials from display name
	 */
	private static function getInitials(string $name): string {
		$name = trim($name);

		// Split by spaces
		$parts = preg_split('/\s+/', $name);

		if (count($parts) >= 2) {
			// First and last initial
			$first = mb_substr($parts[0], 0, 1);
			$last = mb_substr($parts[count($parts) - 1], 0, 1);
			return mb_strtoupper($first . $last);
		} else {
			// Just first two characters
			return mb_strtoupper(mb_substr($name, 0, 2));
		}
	}

	/**
	 * Get consistent color for a name (same name always gets same color)
	 */
	private static function getColorForName(string $name): string {
		$hash = crc32($name);
		$index = abs($hash) % count(self::$colorPalette);
		return self::$colorPalette[$index];
	}

	/**
	 * Determine text color based on background brightness
	 */
	private static function getTextColorForBackground(string $hexColor): string {
		$rgb = self::hexToRgb($hexColor);

		// Calculate relative luminance
		$luminance = (0.299 * $rgb['r'] + 0.587 * $rgb['g'] + 0.114 * $rgb['b']) / 255;

		// Use dark text for light backgrounds
		return $luminance > 0.5 ? '#333333' : '#FFFFFF';
	}

	/**
	 * Convert hex color to RGB array
	 */
	private static function hexToRgb(string $hex): array {
		$hex = ltrim($hex, '#');

		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return [
			'r' => hexdec(substr($hex, 0, 2)),
			'g' => hexdec(substr($hex, 2, 2)),
			'b' => hexdec(substr($hex, 4, 2)),
		];
	}

	/**
	 * Get system font path (fallback chain)
	 */
	private static function getSystemFont(): ?string {
		$fonts = [
			// Linux
			'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
			// macOS
			'/System/Library/Fonts/Helvetica.ttc',
			'/Library/Fonts/Arial.ttf',
			// Windows
			'C:/Windows/Fonts/arial.ttf',
			'C:/Windows/Fonts/arialbd.ttf',
		];

		foreach ($fonts as $font) {
			if (file_exists($font)) {
				return $font;
			}
		}

		return null;
	}

	/**
	 * Generate and cache avatar for user
	 */
	public static function generateAndCache(int $userId, string $displayName, int $size = 120): ?string {
		$cacheKey = "avatar_generated_{$userId}_{$size}";

		// Check cache
		$cached = VT_Transient::get($cacheKey);
		if ($cached !== false) {
			return $cached;
		}

		// Generate new avatar
		$avatar = self::generate($displayName, $size);

		if ($avatar) {
			// Cache for 30 days (avatars don't change often)
			VT_Transient::set($cacheKey, $avatar, 30 * DAY_IN_SECONDS);
		}

		return $avatar;
	}

	/**
	 * Clear cached avatar for user
	 */
	public static function clearCache(int $userId): void {
		$sizes = [32, 64, 120, 200];

		foreach ($sizes as $size) {
			$cacheKey = "avatar_generated_{$userId}_{$size}";
			VT_Transient::delete($cacheKey);
		}
	}

	/**
	 * Generate SVG avatar (alternative to PNG for better scaling)
	 */
	public static function generateSvg(string $displayName, int $size = 120): ?string {
		if (empty($displayName)) {
			return null;
		}

		$initials = self::getInitials($displayName);
		$bgColor = self::getColorForName($displayName);
		$textColor = self::getTextColorForBackground($bgColor);

		$fontSize = $size * 0.4;

		$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
	<rect width="{$size}" height="{$size}" fill="{$bgColor}"/>
	<text x="50%" y="50%" text-anchor="middle" dy=".35em" font-family="Arial, sans-serif" font-size="{$fontSize}" font-weight="bold" fill="{$textColor}">{$initials}</text>
</svg>
SVG;

		return 'data:image/svg+xml;base64,' . base64_encode($svg);
	}
}
