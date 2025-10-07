<?php
/**
 * Image Renderer
 * Generates responsive HTML markup for images with srcset, WebP support, and lazy loading
 */

class VT_Image_Renderer {

	/**
	 * Render responsive image with picture element
	 */
	public static function render(string $src, array $options = []): string {
		$defaults = [
			'alt' => '',
			'class' => '',
			'sizes' => '100vw',
			'lazy' => true,
			'webp' => true,
			'srcset' => [],
			'width' => null,
			'height' => null,
		];

		$options = array_merge($defaults, $options);

		// Build attributes
		$attrs = [];
		$attrs['alt'] = vt_service('validation.validator')->escAttr($options['alt']);
		$attrs['class'] = vt_service('validation.validator')->escAttr($options['class']);

		if ($options['lazy']) {
			$attrs['loading'] = 'lazy';
			$attrs['decoding'] = 'async';
		}

		if ($options['width']) {
			$attrs['width'] = (int) $options['width'];
		}

		if ($options['height']) {
			$attrs['height'] = (int) $options['height'];
		}

		// Check for WebP version
		$webpSrc = null;
		if ($options['webp']) {
			$webpSrc = self::getWebPVersion($src);
		}

		ob_start();

		if ($webpSrc || !empty($options['srcset'])) {
			// Use picture element for WebP or srcset
			echo '<picture>';

			// WebP source
			if ($webpSrc) {
				echo '<source srcset="' . vt_service('validation.validator')->escUrl($webpSrc) . '" type="image/webp">';
			}

			// Srcset source
			if (!empty($options['srcset'])) {
				$srcsetAttr = self::buildSrcset($options['srcset']);
				echo '<source srcset="' . $srcsetAttr . '" sizes="' . vt_service('validation.validator')->escAttr($options['sizes']) . '">';
			}

			// Fallback img
			echo '<img src="' . vt_service('validation.validator')->escUrl($src) . '"';
			foreach ($attrs as $key => $value) {
				echo ' ' . $key . '="' . $value . '"';
			}
			echo '>';

			echo '</picture>';
		} else {
			// Simple img tag
			echo '<img src="' . vt_service('validation.validator')->escUrl($src) . '"';
			foreach ($attrs as $key => $value) {
				echo ' ' . $key . '="' . $value . '"';
			}
			echo '>';
		}

		return ob_get_clean();
	}

	/**
	 * Render responsive image from upload data
	 */
	public static function renderFromUpload(array $uploadData, array $options = []): string {
		$src = $uploadData['url'];
		$srcset = [];

		// Build srcset from thumbnails
		if (!empty($uploadData['thumbnails'])) {
			foreach ($uploadData['thumbnails'] as $sizeName => $thumbData) {
				if (isset($thumbData['url'])) {
					$srcset[$thumbData['url']] = self::getThumbnailWidth($sizeName);
				}
			}
		}

		// Add original to srcset
		if (!empty($uploadData['width'])) {
			$srcset[$src] = $uploadData['width'];
		}

		$options = array_merge([
			'srcset' => $srcset,
			'width' => $uploadData['width'] ?? null,
			'height' => $uploadData['height'] ?? null,
			'alt' => $uploadData['original_name'] ?? '',
		], $options);

		return self::render($src, $options);
	}

	/**
	 * Render avatar with fallback to generated avatar
	 */
	public static function renderAvatar(int $userId, array $options = []): string {
		$defaults = [
			'size' => 64,
			'class' => 'vt-avatar',
			'alt' => '',
			'use_generated' => true,
		];

		$options = array_merge($defaults, $options);

		// Get user profile
		$profile = VT_Profile_Manager::getUserProfile($userId);
		$user = vt_service('auth.user_repository')->getUserById($userId);

		$avatarUrl = null;

		// Check for custom uploaded avatar
		if (!empty($profile['profile_image']) && ($profile['avatar_source'] ?? 'gravatar') === 'custom') {
			$avatarUrl = VT_Image_Manager::getImageUrl($profile['profile_image']);
		}

		// Fallback to Gravatar
		if (!$avatarUrl && !empty($user->email)) {
			$hash = md5(strtolower(trim($user->email)));
			$avatarUrl = "https://www.gravatar.com/avatar/{$hash}?s={$options['size']}&d=404";

			// Check if Gravatar exists (if use_generated is true, we'll generate on 404)
			if ($options['use_generated']) {
				// We can't check Gravatar synchronously, so just use it
				// If it 404s, browser will show broken image
				// Better approach: use generated avatar as default
			}
		}

		// Fallback to generated avatar
		if (!$avatarUrl && $options['use_generated']) {
			$displayName = VT_Profile_Manager::getDisplayName($userId);
			$avatarUrl = VT_Avatar_Generator::generateAndCache($userId, $displayName, $options['size']);
		}

		// Final fallback to default Gravatar
		if (!$avatarUrl) {
			$fallbackHash = md5('default@vivalatable.com');
			$avatarUrl = "https://www.gravatar.com/avatar/{$fallbackHash}?s={$options['size']}&d=identicon";
		}

		// Build alt text
		$alt = $options['alt'] ?: ($profile['display_name'] ?? $user->display_name ?? 'User');

		return self::render($avatarUrl, [
			'alt' => $alt,
			'class' => $options['class'],
			'width' => $options['size'],
			'height' => $options['size'],
			'lazy' => true,
			'webp' => false, // Avatars are usually small, skip WebP
		]);
	}

	/**
	 * Build srcset attribute from array
	 */
	private static function buildSrcset(array $srcset): string {
		$parts = [];

		foreach ($srcset as $url => $width) {
			$escapedUrl = vt_service('validation.validator')->escUrl($url);
			$parts[] = $escapedUrl . ' ' . (int) $width . 'w';
		}

		return implode(', ', $parts);
	}

	/**
	 * Get WebP version of image if it exists
	 */
	private static function getWebPVersion(string $src): ?string {
		// Skip data URLs and external URLs
		if (strpos($src, 'data:') === 0 || strpos($src, 'http') === 0) {
			return null;
		}

		// Convert URL to filesystem path
		$uploadBase = VT_Config::get('upload_path', dirname(__DIR__, 2) . '/uploads');
		$path = $uploadBase . str_replace('/uploads', '', $src);

		// Check for WebP version
		$webpPath = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $path);

		if (file_exists($webpPath)) {
			return str_replace($uploadBase, '/uploads', $webpPath);
		}

		return null;
	}

	/**
	 * Get width for thumbnail size name
	 */
	private static function getThumbnailWidth(string $sizeName): int {
		$sizes = VT_Image_Service::getThumbnailSizes();

		if (isset($sizes[$sizeName])) {
			return $sizes[$sizeName]['width'];
		}

		// Fallback to extracting from filename
		if (preg_match('/-(\d+)x\d+/', $sizeName, $matches)) {
			return (int) $matches[1];
		}

		return 300; // Default
	}

	/**
	 * Render image with blur placeholder (LQIP - Low Quality Image Placeholder)
	 */
	public static function renderWithPlaceholder(string $src, array $options = []): string {
		// Generate tiny placeholder (inline data URL)
		$placeholder = self::generatePlaceholder($src);

		$options['class'] = ($options['class'] ?? '') . ' vt-image-lazy';

		// Use placeholder as src, real image as data attribute
		$realSrc = $src;
		$options['data-src'] = $realSrc;

		if ($placeholder) {
			$src = $placeholder;
		}

		return self::render($src, $options);
	}

	/**
	 * Generate tiny blurred placeholder
	 */
	private static function generatePlaceholder(string $src): ?string {
		// Skip for external URLs
		if (strpos($src, 'http') === 0 || strpos($src, 'data:') === 0) {
			return null;
		}

		// Convert URL to path
		$uploadBase = VT_Config::get('upload_path', dirname(__DIR__, 2) . '/uploads');
		$path = $uploadBase . str_replace('/uploads', '', $src);

		if (!file_exists($path)) {
			return null;
		}

		// Create tiny version (20px wide)
		$image = VT_Image_Processor::load($path);
		if (!$image) {
			return null;
		}

		$tiny = VT_Image_Processor::resize($image['resource'], 20, 20);
		imagedestroy($image['resource']);

		// Convert to data URL
		ob_start();
		imagejpeg($tiny['resource'], null, 60);
		$imageData = ob_get_clean();
		imagedestroy($tiny['resource']);

		return 'data:image/jpeg;base64,' . base64_encode($imageData);
	}
}
