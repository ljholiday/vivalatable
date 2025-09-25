<?php
/**
 * VivalaTable Settings
 * Application settings and configuration management
 * Ported from PartyMinder WordPress plugin
 */
class VT_Settings {

	/**
	 * Get maximum file size in bytes
	 *
	 * @return int File size limit in bytes
	 */
	public static function get_max_file_size() {
		$size_mb = VT_Config::get('max_file_size_mb', 5);
		return $size_mb * 1024 * 1024;
	}

	/**
	 * Get maximum file size in megabytes
	 *
	 * @return int File size limit in MB
	 */
	public static function get_max_file_size_mb() {
		return VT_Config::get('max_file_size_mb', 5);
	}

	/**
	 * Get file size limit description for user display
	 *
	 * @return string Description text
	 */
	public static function get_file_size_description() {
		$size_mb = self::get_max_file_size_mb();
		return sprintf('JPG, PNG, GIF, WebP up to %dMB each', $size_mb);
	}

	/**
	 * Get file size limit error message
	 *
	 * @return string Error message
	 */
	public static function get_file_size_error_message() {
		$size_mb = self::get_max_file_size_mb();
		return sprintf('File size must be less than %dMB.', $size_mb);
	}

	/**
	 * Validate uploaded file against VivalaTable requirements
	 *
	 * @param array $file File array from $_FILES
	 * @return array Array with 'success' and 'error' keys
	 */
	public static function validate_uploaded_file($file) {
		// Check for upload errors
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return array(
				'success' => false,
				'error' => 'File upload error occurred.'
			);
		}

		// Check file size
		if ($file['size'] > self::get_max_file_size()) {
			return array(
				'success' => false,
				'error' => self::get_file_size_error_message()
			);
		}

		// Check file type
		$allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
		if (!in_array($file['type'], $allowed_types)) {
			return array(
				'success' => false,
				'error' => 'Only JPG, PNG, GIF, and WebP images are allowed.'
			);
		}

		// Additional validation for image type
		$image_info = getimagesize($file['tmp_name']);
		if ($image_info === false) {
			return array(
				'success' => false,
				'error' => 'Invalid image file.'
			);
		}

		return array('success' => true);
	}

	/**
	 * Get application setting with fallback
	 */
	public static function get_setting($key, $default = null) {
		return VT_Config::get($key, $default);
	}

	/**
	 * Set application setting
	 */
	public static function set_setting($key, $value) {
		return VT_Config::set($key, $value);
	}

	/**
	 * Get all VivalaTable settings
	 */
	public static function get_all_settings() {
		return array(
			'max_file_size_mb' => self::get_max_file_size_mb(),
			'site_name' => VT_Config::get('site_name', 'VivalaTable'),
			'admin_email' => VT_Config::get('admin_email', 'admin@vivalatable.com'),
			'enable_guest_rsvp' => VT_Config::get('enable_guest_rsvp', true),
			'at_protocol_enabled' => VT_Config::get('at_protocol_enabled', false),
			'circles_of_trust_enabled' => VT_Config::get('circles_of_trust_enabled', true),
		);
	}
}