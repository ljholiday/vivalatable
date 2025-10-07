<?php
/**
 * Image Service
 * Main orchestrator for image uploads, thumbnail generation, and management
 */

class VT_Image_Service {

	private const ALLOWED_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
	];

	private const MAX_FILE_SIZE = 5242880; // 5MB

	/**
	 * Thumbnail size presets
	 */
	private static $thumbnailSizes = [
		'avatar_sm' => ['width' => 32, 'height' => 32, 'mode' => 'fill'],
		'avatar_md' => ['width' => 64, 'height' => 64, 'mode' => 'fill'],
		'avatar_lg' => ['width' => 120, 'height' => 120, 'mode' => 'fill'],
		'thumbnail' => ['width' => 300, 'height' => 300, 'mode' => 'fit'],
		'medium' => ['width' => 600, 'height' => 600, 'mode' => 'fit'],
		'large' => ['width' => 1200, 'height' => 1200, 'mode' => 'fit'],
		'cover' => ['width' => 1200, 'height' => 400, 'mode' => 'fill'],
	];

	/**
	 * Upload and process image
	 */
	public static function upload(array $file, array $options = []): ?array {
		// Validate upload
		$validation = self::validate($file);
		if (!$validation['success']) {
			return ['error' => $validation['error']];
		}

		// Get options
		$context = $options['context'] ?? 'user';
		$entityId = $options['entity_id'] ?? 0;
		$userId = $options['user_id'] ?? vt_service('auth.service')->getCurrentUserId();
		$sizes = $options['sizes'] ?? ['thumbnail', 'medium', 'large'];
		$generateWebP = $options['webp'] ?? true;

		// Create upload directory
		$uploadDir = self::getUploadDirectory($context, $entityId);
		if (!$uploadDir) {
			return ['error' => 'Failed to create upload directory'];
		}

		// Generate unique filename
		$originalName = $file['name'];
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$filename = self::generateFilename($context, $entityId, $extension);
		$filePath = $uploadDir . '/' . $filename;

		// Move uploaded file
		if (!move_uploaded_file($file['tmp_name'], $filePath)) {
			return ['error' => 'Failed to save uploaded file'];
		}

		// Strip EXIF data (privacy + filesize)
		VT_Image_Processor::stripExif($filePath);

		// Make progressive JPEG
		if ($extension === 'jpg' || $extension === 'jpeg') {
			VT_Image_Processor::makeProgressive($filePath);
		}

		// Get image dimensions
		$dimensions = VT_Image_Processor::getDimensions($filePath);

		// Generate thumbnails
		$thumbnails = self::generateThumbnails($filePath, $sizes);

		// Generate WebP versions
		$webpFiles = [];
		if ($generateWebP && function_exists('imagewebp')) {
			// Original as WebP
			$webpPath = VT_Image_Processor::generateWebP($filePath);
			if ($webpPath) {
				$webpFiles['original'] = basename($webpPath);
			}

			// Thumbnails as WebP
			foreach ($thumbnails as $sizeName => $thumbPath) {
				$webpPath = VT_Image_Processor::generateWebP($thumbPath);
				if ($webpPath) {
					$webpFiles[$sizeName] = basename($webpPath);
				}
			}
		}

		// Build result
		return [
			'success' => true,
			'filename' => $filename,
			'path' => $filePath,
			'url' => self::getImageUrl($filePath),
			'original_name' => $originalName,
			'mime_type' => $dimensions['mime'],
			'file_size' => filesize($filePath),
			'width' => $dimensions['width'],
			'height' => $dimensions['height'],
			'thumbnails' => array_map(function($path) {
				return [
					'path' => $path,
					'url' => self::getImageUrl($path),
				];
			}, $thumbnails),
			'webp' => $webpFiles,
			'context' => $context,
			'entity_id' => $entityId,
			'user_id' => $userId,
		];
	}

	/**
	 * Validate uploaded image file
	 */
	public static function validate(array $file): array {
		// Check if file was uploaded
		if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			return [
				'success' => false,
				'error' => 'No file was uploaded',
			];
		}

		// Check for upload errors
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return [
				'success' => false,
				'error' => 'Upload error: ' . $file['error'],
			];
		}

		// Check file size
		if ($file['size'] > self::MAX_FILE_SIZE) {
			return [
				'success' => false,
				'error' => 'File size must be less than 5MB',
			];
		}

		// Check MIME type
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimeType = finfo_file($finfo, $file['tmp_name']);
		finfo_close($finfo);

		if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
			return [
				'success' => false,
				'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed',
			];
		}

		// Check image dimensions (prevent tiny or huge images)
		$dimensions = @getimagesize($file['tmp_name']);
		if (!$dimensions) {
			return [
				'success' => false,
				'error' => 'Invalid image file',
			];
		}

		if ($dimensions[0] < 50 || $dimensions[1] < 50) {
			return [
				'success' => false,
				'error' => 'Image too small (minimum 50x50 pixels)',
			];
		}

		if ($dimensions[0] > 5000 || $dimensions[1] > 5000) {
			return [
				'success' => false,
				'error' => 'Image too large (maximum 5000x5000 pixels)',
			];
		}

		return ['success' => true];
	}

	/**
	 * Generate thumbnails for image
	 */
	private static function generateThumbnails(string $sourcePath, array $sizeNames): array {
		$thumbnails = [];
		$sourceDir = dirname($sourcePath);
		$sourceFile = pathinfo($sourcePath, PATHINFO_FILENAME);
		$sourceExt = pathinfo($sourcePath, PATHINFO_EXTENSION);

		foreach ($sizeNames as $sizeName) {
			if (!isset(self::$thumbnailSizes[$sizeName])) {
				continue;
			}

			$size = self::$thumbnailSizes[$sizeName];
			$thumbFilename = $sourceFile . '-' . $sizeName . '.' . $sourceExt;
			$thumbPath = $sourceDir . '/' . $thumbFilename;

			$result = VT_Image_Processor::createThumbnail(
				$sourcePath,
				$thumbPath,
				$size['width'],
				$size['height'],
				$size['mode']
			);

			if ($result) {
				$thumbnails[$sizeName] = $thumbPath;
			}
		}

		return $thumbnails;
	}

	/**
	 * Get upload directory for context
	 */
	private static function getUploadDirectory(string $context, int $entityId = 0): ?string {
		$uploadBase = VT_Config::get('upload_path', dirname(__DIR__, 2) . '/uploads');
		$directory = $uploadBase . '/vivalatable/' . $context . 's';

		if ($entityId) {
			$directory .= '/' . $entityId;
		}

		// Create directory if needed
		if (!file_exists($directory)) {
			if (!mkdir($directory, 0755, true)) {
				return null;
			}
		}

		return $directory;
	}

	/**
	 * Generate unique filename
	 */
	private static function generateFilename(string $context, int $entityId, string $extension): string {
		$timestamp = time();
		$random = substr(md5(uniqid(rand(), true)), 0, 8);

		return sprintf(
			'%s_%d_%s_%s.%s',
			$context,
			$entityId,
			$timestamp,
			$random,
			$extension
		);
	}

	/**
	 * Get public URL for image
	 */
	public static function getImageUrl(string $path): string {
		$uploadBase = VT_Config::get('upload_path', dirname(__DIR__, 2) . '/uploads');

		// If already a URL, return as-is
		if (strpos($path, 'http') === 0 || strpos($path, 'data:') === 0) {
			return $path;
		}

		// Convert filesystem path to URL
		$relativePath = str_replace($uploadBase, '', $path);
		return '/uploads' . $relativePath;
	}

	/**
	 * Delete image and all its thumbnails
	 */
	public static function delete(string $path): bool {
		if (!file_exists($path)) {
			return false;
		}

		// Delete main image
		$deleted = @unlink($path);

		// Delete thumbnails
		$directory = dirname($path);
		$filename = pathinfo($path, PATHINFO_FILENAME);
		$extension = pathinfo($path, PATHINFO_EXTENSION);

		$pattern = $directory . '/' . $filename . '-*.' . $extension;
		foreach (glob($pattern) as $thumbnailPath) {
			@unlink($thumbnailPath);
		}

		// Delete WebP versions
		$webpPath = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $path);
		if (file_exists($webpPath)) {
			@unlink($webpPath);
		}

		$pattern = $directory . '/' . $filename . '-*.webp';
		foreach (glob($pattern) as $webpPath) {
			@unlink($webpPath);
		}

		return $deleted;
	}

	/**
	 * Get thumbnail size configuration
	 */
	public static function getThumbnailSizes(): array {
		return self::$thumbnailSizes;
	}

	/**
	 * Register custom thumbnail size
	 */
	public static function addThumbnailSize(string $name, int $width, int $height, string $mode = 'fit'): void {
		self::$thumbnailSizes[$name] = [
			'width' => $width,
			'height' => $height,
			'mode' => $mode,
		];
	}
}
