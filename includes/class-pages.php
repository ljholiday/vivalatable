<?php
/**
 * VivalaTable Pages Controller
 * Handles rendering of all frontend pages
 * Uses the three-template system ported from PartyMinder
 */

class VT_Pages {

	/**
	 * Dashboard page
	 */
	public static function dashboard() {
		self::render_page('dashboard', 'Dashboard', 'Your social event hub', 'two-column');
	}

	/**
	 * Login page
	 */
	public static function login() {
		if (VT_Auth::isLoggedIn()) {
			VT_Router::redirect('/');
		}
		self::render_page('login', 'Login', 'Sign in to your account', 'form');
	}

	/**
	 * Register page
	 */
	public static function register() {
		if (VT_Auth::isLoggedIn()) {
			VT_Router::redirect('/');
		}
		self::render_page('register', 'Create Account', 'Join VivalaTable', 'form');
	}

	/**
	 * Events list page
	 */
	public static function events_list() {
		self::render_page('events', 'Events', 'Discover and join events', 'two-column');
	}

	/**
	 * Create event page
	 */
	public static function create_event() {
		self::require_auth();
		self::render_page('create-event', 'Create Event', 'Plan your perfect event', 'form');
	}

	/**
	 * Single event page
	 */
	public static function single_event($params) {
		$event_id = $params['id'];
		self::render_page('single-event', 'Event Details', null, 'two-column', compact('event_id'));
	}

	/**
	 * Edit event page
	 */
	public static function edit_event($params) {
		self::require_auth();
		$event_id = $params['id'];
		self::render_page('edit-event', 'Edit Event', null, 'form', compact('event_id'));
	}

	/**
	 * Communities list page
	 */
	public static function communities_list() {
		self::render_page('communities', 'Communities', 'Connect with like-minded people', 'two-column');
	}

	/**
	 * Create community page
	 */
	public static function create_community() {
		self::require_auth();
		self::render_page('create-community', 'Create Community', 'Build your community', 'form');
	}

	/**
	 * Single community page
	 */
	public static function single_community($params) {
		$community_id = $params['id'];
		self::render_page('single-community', 'Community', null, 'two-column', compact('community_id'));
	}

	/**
	 * Community conversations page
	 */
	public static function community_conversations($params) {
		$community_id = $params['id'];
		self::render_page('community-conversations', 'Community Conversations', null, 'two-column', compact('community_id'));
	}

	/**
	 * Community events page
	 */
	public static function community_events($params) {
		$community_id = $params['id'];
		self::render_page('community-events', 'Community Events', null, 'two-column', compact('community_id'));
	}

	/**
	 * Conversations list page
	 */
	public static function conversations_list() {
		self::render_page('conversations', 'Conversations', 'Join the discussion', 'two-column');
	}

	/**
	 * Create conversation page
	 */
	public static function create_conversation() {
		self::require_auth();
		self::render_page('create-conversation', 'Start Conversation', 'Share your thoughts', 'form');
	}

	/**
	 * Single conversation page
	 */
	public static function single_conversation($params) {
		$conversation_id = $params['id'];
		self::render_page('single-conversation', 'Conversation', null, 'two-column', compact('conversation_id'));
	}

	/**
	 * User profile page
	 */
	public static function profile($params = array()) {
		$user_id = isset($params['id']) ? $params['id'] : VT_Auth::getCurrentUserId();

		if (!$user_id && !isset($params['id'])) {
			self::require_auth();
			$user_id = VT_Auth::getCurrentUserId();
		}

		self::render_page('profile', 'Profile', null, 'two-column', compact('user_id'));
	}

	/**
	 * User profile page (when accessing someone else's profile)
	 */
	public static function user_profile($params) {
		$user_id = $params['id'];
		self::render_page('user-profile', 'User Profile', null, 'two-column', compact('user_id'));
	}

	/**
	 * Guest RSVP page
	 */
	public static function guest_rsvp($params) {
		$token = $params['token'];
		self::render_page('guest-rsvp', 'RSVP to Event', 'Respond to your invitation', 'form', compact('token'));
	}

	/**
	 * 404 Not Found page
	 */
	public static function not_found() {
		http_response_code(404);
		self::render_page('404', 'Page Not Found', 'The page you requested was not found', 'page');
	}

	/**
	 * 500 Server Error page
	 */
	public static function server_error($message = 'An unexpected error occurred') {
		http_response_code(500);
		self::render_page('500', 'Server Error', $message, 'page');
	}

	/**
	 * Render a page using the VivalaTable template system
	 */
	private static function render_page($template, $page_title, $page_description = null, $base_template = 'page', $data = array()) {
		// Set up template variables
		global $vt_page_title, $vt_page_description, $vt_breadcrumbs, $vt_nav_items;

		$vt_page_title = $page_title;
		$vt_page_description = $page_description;
		$vt_breadcrumbs = self::get_breadcrumbs();
		$vt_nav_items = self::get_nav_items();

		// Make data available to templates
		if (!empty($data)) {
			extract($data);
		}

		// Content template path
		$templates_path = VT_Config::get('templates_path', VT_PLUGIN_DIR . '/templates');
		$content_template = $templates_path . '/' . $template . '-content.php';

		if (!file_exists($content_template)) {
			error_log('Template not found: ' . $content_template);
			self::render_fallback_page($page_title, 'Template not found: ' . $template);
			return;
		}

		// Capture content
		ob_start();
		include $content_template;
		$content = ob_get_clean();

		// For two-column layout, capture sidebar if it exists
		$sidebar_content = '';
		if ($base_template === 'two-column') {
			$sidebar_template = VT_Config::get('templates_path', '/templates') . '/' . $template . '-sidebar.php';
			if (file_exists($sidebar_template)) {
				ob_start();
				include $sidebar_template;
				$sidebar_content = ob_get_clean();
			}
		}

		// Load base template
		$templates_path = VT_Config::get('templates_path', VT_PLUGIN_DIR . '/templates');
		$base_template_path = $templates_path . '/base/template-' . $base_template . '.php';

		if (!file_exists($base_template_path)) {
			error_log('Base template not found: ' . $base_template_path);
			self::render_fallback_page($page_title, 'Base template not found: ' . $base_template);
			return;
		}

		// Make variables available to base template
		$main_content = $content;

		include $base_template_path;
	}

	/**
	 * Render fallback page when templates are missing
	 */
	private static function render_fallback_page($title, $message) {
		echo '<!DOCTYPE html>';
		echo '<html><head><title>' . htmlspecialchars($title) . ' - VivalaTable</title></head>';
		echo '<body><h1>' . htmlspecialchars($title) . '</h1>';
		echo '<p>' . htmlspecialchars($message) . '</p>';
		echo '</body></html>';
	}

	/**
	 * Get breadcrumbs for current page
	 */
	private static function get_breadcrumbs() {
		$route = VT_Router::get_current_route();
		if (!$route) {
			return array();
		}

		$path = $route['route']['path'];
		$breadcrumbs = array();

		// Generate breadcrumbs based on path
		if ($path !== '/') {
			$breadcrumbs[] = array(
				'title' => 'Dashboard',
				'url' => '/',
			);
		}

		// Add specific breadcrumbs based on current page
		if (strpos($path, '/events') === 0) {
			if ($path !== '/events') {
				$breadcrumbs[] = array(
					'title' => 'Events',
					'url' => '/events',
				);
			}
		}

		return $breadcrumbs;
	}

	/**
	 * Get navigation items
	 */
	private static function get_nav_items() {
		$current_path = VT_Router::get_current_route()['route']['path'] ?? '';

		return array(
			array(
				'title' => 'Events',
				'url' => '/events',
				'active' => strpos($current_path, '/events') === 0,
			),
			array(
				'title' => 'Conversations',
				'url' => '/conversations',
				'active' => strpos($current_path, '/conversations') === 0,
			),
			array(
				'title' => 'Communities',
				'url' => '/communities',
				'active' => strpos($current_path, '/communities') === 0,
			),
		);
	}

	/**
	 * Require user authentication
	 */
	private static function require_auth() {
		if (!VT_Auth::isLoggedIn()) {
			VT_Router::redirect('/login');
		}
	}
}