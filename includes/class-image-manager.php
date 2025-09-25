<?php
/**
 * VivalaTable Image Manager
 * Centralized image upload and management functionality
 * Ported from PartyMinder WordPress plugin
 */
class VT_Image_Manager {

	const ALLOWED_TYPES = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');

	// Image dimensions for different types
	const PROFILE_IMAGE_MAX_WIDTH = 400;
	const PROFILE_IMAGE_MAX_HEIGHT = 400;
	const COVER_IMAGE_MAX_WIDTH = 1200;
	const COVER_IMAGE_MAX_HEIGHT = 400;
	const POST_IMAGE_MAX_WIDTH = 800;
	const POST_IMAGE_MAX_HEIGHT = 600;

	/**
	 * Handle image upload
	 */
	public static function handle_image_upload($file, $image_type, $entity_id, $entity_type = 'user', $event_id = null) {
		// Validate file
		$validation = self::validate_image_file($file, $image_type);
		if (!$validation['success']) {
			return $validation;
		}

		// Set up upload directory
		$upload_info = self::get_upload_directory($entity_type, $event_id);
		if (!$upload_info['success']) {
			return $upload_info;
		}

		// Generate unique filename
		$filename = self::generate_filename($file, $image_type, $entity_id, $entity_type);
		$file_path = $upload_info['dir'] . $filename;
		$file_url = $upload_info['url'] . $filename;

		// Process and save image
		$result = self::process_and_save_image($file, $file_path, $image_type);
		if (!$result['success']) {
			return $result;
		}

		return array(
			'success' => true,
			'url' => $file_url,
			'path' => $file_path,
			'filename' => $filename,
		);
	}

	/**
	 * Validate uploaded image file
	 */
	private static function validate_image_file($file, $image_type) {
		// Check if file was uploaded
		if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			return array(
				'success' => false,
				'error' => 'No file was uploaded.',
			);
		}

		// Check file size (5MB limit)
		$max_size = 5 * 1024 * 1024; // 5MB
		if ($file['size'] > $max_size) {
			return array(
				'success' => false,
				'error' => 'File size must be less than 5MB.',
			);
		}

		// Check file type
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_file($finfo, $file['tmp_name']);
		finfo_close($finfo);

		if (!in_array($mime_type, self::ALLOWED_TYPES)) {
			return array(
				'success' => false,
				'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.',
			);
		}

		return array('success' => true);
	}

	/**
	 * Get upload directory for entity type
	 */
	private static function get_upload_directory($entity_type, $event_id = null) {
		$upload_base = VT_Config::get('upload_path', '/uploads');
		$upload_url_base = VT_Http::getBaseUrl() . $upload_base;

		// Handle different entity types
		if ($entity_type === 'post' && $event_id) {
			$upload_dir = $upload_base . '/vivalatable/events/' . $event_id . '/posts/';
			$upload_url = $upload_url_base . '/vivalatable/events/' . $event_id . '/posts/';
		} elseif ($entity_type === 'conversation' && $event_id) {
			$upload_dir = $upload_base . '/vivalatable/conversations/' . $event_id . '/';
			$upload_url = $upload_url_base . '/vivalatable/conversations/' . $event_id . '/';
		} else {
			$upload_dir = $upload_base . '/vivalatable/' . $entity_type . 's/';
			$upload_url = $upload_url_base . '/vivalatable/' . $entity_type . 's/';
		}

		// Create directory if it doesn't exist
		if (!file_exists($upload_dir)) {
			if (!mkdir($upload_dir, 0755, true)) {
				return array(
					'success' => false,
					'error' => 'Failed to create upload directory.',
				);
			}
		}

		return array(
			'success' => true,
			'dir' => $upload_dir,
			'url' => $upload_url,
		);
	}

	/**
	 * Generate unique filename
	 */
	private static function generate_filename($file, $image_type, $entity_id, $entity_type) {
		$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
		$timestamp = time();
		$random = substr(md5(uniqid(rand(), true)), 0, 8);

		return sprintf(
			'%s_%s_%s_%s.%s',
			$entity_type,
			$entity_id,
			$image_type,
			$timestamp . '_' . $random,
			strtolower($extension)
		);
	}

	/**
	 * Process and save image with resizing
	 */
	private static function process_and_save_image($file, $file_path, $image_type) {
		// Get dimensions for image type
		$dimensions = self::get_image_dimensions($image_type);

		// Load image
		$image = self::load_image($file['tmp_name']);
		if (!$image) {
			return array(
				'success' => false,
				'error' => 'Failed to process image.',
			);
		}

		// Resize if needed
		$resized_image = self::resize_image($image, $dimensions['width'], $dimensions['height']);

		// Save image
		$saved = self::save_image($resized_image, $file_path);

		// Clean up
		imagedestroy($image);
		if ($resized_image !== $image) {
			imagedestroy($resized_image);
		}

		if (!$saved) {
			return array(
				'success' => false,
				'error' => 'Failed to save image.',
			);
		}

		return array('success' => true);
	}

	/**
	 * Get dimensions for image type
	 */
	private static function get_image_dimensions($image_type) {
		switch ($image_type) {
			case 'profile':
				return array(
					'width' => self::PROFILE_IMAGE_MAX_WIDTH,
					'height' => self::PROFILE_IMAGE_MAX_HEIGHT,
				);
			case 'cover':
				return array(
					'width' => self::COVER_IMAGE_MAX_WIDTH,
					'height' => self::COVER_IMAGE_MAX_HEIGHT,
				);
			case 'post':
				return array(
					'width' => self::POST_IMAGE_MAX_WIDTH,
					'height' => self::POST_IMAGE_MAX_HEIGHT,
				);
			default:
				return array(
					'width' => self::POST_IMAGE_MAX_WIDTH,
					'height' => self::POST_IMAGE_MAX_HEIGHT,
				);
		}
	}

	/**
	 * Load image from file
	 */
	private static function load_image($file_path) {
		$image_info = getimagesize($file_path);
		if (!$image_info) {
			return false;
		}

		switch ($image_info['mime']) {
			case 'image/jpeg':
				return imagecreatefromjpeg($file_path);
			case 'image/png':
				return imagecreatefrompng($file_path);
			case 'image/gif':
				return imagecreatefromgif($file_path);
			case 'image/webp':
				return imagecreatefromwebp($file_path);
			default:
				return false;
		}
	}

	/**
	 * Resize image maintaining aspect ratio
	 */
	private static function resize_image($source, $max_width, $max_height) {
		$orig_width = imagesx($source);
		$orig_height = imagesy($source);

		// Calculate new dimensions
		$ratio = min($max_width / $orig_width, $max_height / $orig_height);

		// Don't upscale
		if ($ratio > 1) {
			return $source;
		}

		$new_width = (int) ($orig_width * $ratio);
		$new_height = (int) ($orig_height * $ratio);

		// Create new image
		$resized = imagecreatetruecolor($new_width, $new_height);

		// Handle transparency for PNG and GIF
		imagealphablending($resized, false);
		imagesavealpha($resized, true);
		$transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
		imagefilledrectangle($resized, 0, 0, $new_width, $new_height, $transparent);

		// Resize
		imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

		return $resized;
	}

	/**
	 * Save image to file
	 */
	private static function save_image($image, $file_path) {
		$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

		switch ($extension) {
			case 'jpg':
			case 'jpeg':
				return imagejpeg($image, $file_path, 90);
			case 'png':
				return imagepng($image, $file_path, 8);
			case 'gif':
				return imagegif($image, $file_path);
			case 'webp':
				return imagewebp($image, $file_path, 90);
			default:
				return false;
		}
	}

	/**
	 * Delete image file
	 */
	public static function delete_image($image_url) {
		if (!$image_url) {
			return false;
		}

		// Convert URL to file path
		$upload_base = VT_Config::get('upload_path', '/uploads');
		$base_url = VT_Http::getBaseUrl() . $upload_base;

		if (strpos($image_url, $base_url) !== 0) {
			return false; // Not our image
		}

		$file_path = str_replace($base_url, $upload_base, $image_url);

		if (file_exists($file_path)) {
			return unlink($file_path);
		}

		return false;
	}

	/**
	 * Get image URL for display
	 */
	public static function get_image_url($image_path, $size = 'full') {
		if (!$image_path) {
			return '';
		}

		// If it's already a full URL, return it
		if (strpos($image_path, 'http') === 0) {
			return $image_path;
		}

		$upload_base = VT_Config::get('upload_path', '/uploads');
		return VT_Http::getBaseUrl() . $upload_base . '/' . ltrim($image_path, '/');
	}
}