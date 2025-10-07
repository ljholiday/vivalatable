<?php
/**
 * Image Processor
 * Handles image manipulation operations (resize, crop, format conversion)
 */

class VT_Image_Processor {

	private const JPEG_QUALITY = 90;
	private const PNG_COMPRESSION = 8;
	private const WEBP_QUALITY = 85;

	/**
	 * Load image from file path
	 */
	public static function load(string $filePath): ?array {
		if (!file_exists($filePath)) {
			return null;
		}

		$imageInfo = @getimagesize($filePath);
		if (!$imageInfo) {
			return null;
		}

		$resource = null;
		switch ($imageInfo['mime']) {
			case 'image/jpeg':
				$resource = @imagecreatefromjpeg($filePath);
				break;
			case 'image/png':
				$resource = @imagecreatefrompng($filePath);
				break;
			case 'image/gif':
				$resource = @imagecreatefromgif($filePath);
				break;
			case 'image/webp':
				if (function_exists('imagecreatefromwebp')) {
					$resource = @imagecreatefromwebp($filePath);
				}
				break;
		}

		if (!$resource) {
			return null;
		}

		return [
			'resource' => $resource,
			'width' => $imageInfo[0],
			'height' => $imageInfo[1],
			'mime' => $imageInfo['mime'],
		];
	}

	/**
	 * Save image to file
	 */
	public static function save($resource, string $filePath, string $format = 'jpeg'): bool {
		$format = strtolower($format);

		switch ($format) {
			case 'jpg':
			case 'jpeg':
				return imagejpeg($resource, $filePath, self::JPEG_QUALITY);

			case 'png':
				return imagepng($resource, $filePath, self::PNG_COMPRESSION);

			case 'gif':
				return imagegif($resource, $filePath);

			case 'webp':
				if (function_exists('imagewebp')) {
					return imagewebp($resource, $filePath, self::WEBP_QUALITY);
				}
				return false;

			default:
				return false;
		}
	}

	/**
	 * Resize image maintaining aspect ratio
	 */
	public static function resize($source, int $maxWidth, int $maxHeight, bool $upscale = false): array {
		$origWidth = imagesx($source);
		$origHeight = imagesy($source);

		// Calculate new dimensions
		$ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);

		// Don't upscale unless explicitly requested
		if (!$upscale && $ratio > 1) {
			return [
				'resource' => $source,
				'width' => $origWidth,
				'height' => $origHeight,
				'resized' => false,
			];
		}

		$newWidth = (int) ($origWidth * $ratio);
		$newHeight = (int) ($origHeight * $ratio);

		// Create new image
		$resized = imagecreatetruecolor($newWidth, $newHeight);

		// Preserve transparency
		self::preserveTransparency($resized, $source);

		// Resize with resampling for better quality
		imagecopyresampled(
			$resized, $source,
			0, 0, 0, 0,
			$newWidth, $newHeight,
			$origWidth, $origHeight
		);

		return [
			'resource' => $resized,
			'width' => $newWidth,
			'height' => $newHeight,
			'resized' => true,
		];
	}

	/**
	 * Crop image to exact dimensions (centered)
	 */
	public static function crop($source, int $width, int $height): array {
		$origWidth = imagesx($source);
		$origHeight = imagesy($source);

		// Calculate crop position (center)
		$aspectRatio = $width / $height;
		$origAspectRatio = $origWidth / $origHeight;

		if ($origAspectRatio > $aspectRatio) {
			// Original is wider - crop width
			$cropHeight = $origHeight;
			$cropWidth = (int) ($origHeight * $aspectRatio);
			$cropX = (int) (($origWidth - $cropWidth) / 2);
			$cropY = 0;
		} else {
			// Original is taller - crop height
			$cropWidth = $origWidth;
			$cropHeight = (int) ($origWidth / $aspectRatio);
			$cropX = 0;
			$cropY = (int) (($origHeight - $cropHeight) / 2);
		}

		// Create cropped image
		$cropped = imagecreatetruecolor($width, $height);
		self::preserveTransparency($cropped, $source);

		imagecopyresampled(
			$cropped, $source,
			0, 0, $cropX, $cropY,
			$width, $height, $cropWidth, $cropHeight
		);

		return [
			'resource' => $cropped,
			'width' => $width,
			'height' => $height,
		];
	}

	/**
	 * Convert image to different format
	 */
	public static function convert(string $sourcePath, string $destPath, string $format): bool {
		$image = self::load($sourcePath);
		if (!$image) {
			return false;
		}

		$result = self::save($image['resource'], $destPath, $format);
		imagedestroy($image['resource']);

		return $result;
	}

	/**
	 * Generate WebP version of image
	 */
	public static function generateWebP(string $sourcePath, string $destPath = null): ?string {
		if (!function_exists('imagewebp')) {
			return null;
		}

		$image = self::load($sourcePath);
		if (!$image) {
			return null;
		}

		// Generate WebP filename if not provided
		if ($destPath === null) {
			$destPath = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $sourcePath);
		}

		$result = imagewebp($image['resource'], $destPath, self::WEBP_QUALITY);
		imagedestroy($image['resource']);

		return $result ? $destPath : null;
	}

	/**
	 * Strip EXIF data from image (privacy + size reduction)
	 */
	public static function stripExif(string $filePath): bool {
		$image = self::load($filePath);
		if (!$image) {
			return false;
		}

		// Get extension
		$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

		// Resave image (this strips EXIF)
		$result = self::save($image['resource'], $filePath, $ext);
		imagedestroy($image['resource']);

		return $result;
	}

	/**
	 * Preserve transparency for PNG/GIF
	 */
	private static function preserveTransparency($dest, $source): void {
		// Disable blending
		imagealphablending($dest, false);
		imagesavealpha($dest, true);

		// Allocate transparent color
		$transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
		imagefilledrectangle($dest, 0, 0, imagesx($dest), imagesy($dest), $transparent);

		// Re-enable blending for actual copy
		imagealphablending($dest, true);
	}

	/**
	 * Get image dimensions without loading full image
	 */
	public static function getDimensions(string $filePath): ?array {
		$info = @getimagesize($filePath);
		if (!$info) {
			return null;
		}

		return [
			'width' => $info[0],
			'height' => $info[1],
			'mime' => $info['mime'],
		];
	}

	/**
	 * Optimize image filesize
	 */
	public static function optimize(string $filePath): bool {
		$image = self::load($filePath);
		if (!$image) {
			return false;
		}

		$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

		// Resave with optimization settings
		$result = self::save($image['resource'], $filePath, $ext);
		imagedestroy($image['resource']);

		return $result;
	}

	/**
	 * Generate progressive JPEG
	 */
	public static function makeProgressive(string $filePath): bool {
		$image = self::load($filePath);
		if (!$image || $image['mime'] !== 'image/jpeg') {
			if ($image) {
				imagedestroy($image['resource']);
			}
			return false;
		}

		// Enable progressive encoding
		imageinterlace($image['resource'], 1);

		$result = imagejpeg($image['resource'], $filePath, self::JPEG_QUALITY);
		imagedestroy($image['resource']);

		return $result;
	}

	/**
	 * Create thumbnail with multiple strategies
	 */
	public static function createThumbnail(string $sourcePath, string $destPath, int $width, int $height, string $mode = 'fit'): ?array {
		$image = self::load($sourcePath);
		if (!$image) {
			return null;
		}

		$thumbnail = null;

		switch ($mode) {
			case 'fit':
				// Resize to fit within bounds (maintains aspect ratio)
				$result = self::resize($image['resource'], $width, $height);
				$thumbnail = $result['resource'];
				break;

			case 'fill':
				// Crop to fill exact dimensions
				$result = self::crop($image['resource'], $width, $height);
				$thumbnail = $result['resource'];
				break;

			case 'stretch':
				// Stretch to exact dimensions (distorts image)
				$thumbnail = imagecreatetruecolor($width, $height);
				self::preserveTransparency($thumbnail, $image['resource']);
				imagecopyresampled(
					$thumbnail, $image['resource'],
					0, 0, 0, 0,
					$width, $height,
					$image['width'], $image['height']
				);
				break;

			default:
				imagedestroy($image['resource']);
				return null;
		}

		// Save thumbnail
		$ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
		$saved = self::save($thumbnail, $destPath, $ext);

		// Cleanup
		imagedestroy($image['resource']);
		if ($thumbnail !== $image['resource']) {
			imagedestroy($thumbnail);
		}

		if (!$saved) {
			return null;
		}

		return self::getDimensions($destPath);
	}

	/**
	 * Check if image processing is available
	 */
	public static function isAvailable(): bool {
		return function_exists('imagecreatetruecolor') &&
		       function_exists('imagecopyresampled');
	}

	/**
	 * Get supported output formats
	 */
	public static function getSupportedFormats(): array {
		$formats = ['jpeg', 'png', 'gif'];

		if (function_exists('imagewebp')) {
			$formats[] = 'webp';
		}

		return $formats;
	}
}
