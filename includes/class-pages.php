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
		self::renderPage('dashboard', 'Dashboard', 'Your social event hub', 'two-column');
	}

	/**
	 * Login page
	 */
	public static function login() {
		if (vt_service('auth.service')->isLoggedIn()) {
			VT_Router::redirect('/');
		}
		self::renderPage('login', 'Login', 'Sign in to your account', 'form');
	}

	/**
	 * Register page
	 */
	public static function register() {
		if (vt_service('auth.service')->isLoggedIn()) {
			VT_Router::redirect('/');
		}
		self::renderPage('register', 'Create Account', 'Join VivalaTable', 'form');
	}

	/**
	 * Events list page
	 */
	public static function eventsList() {
		self::renderPage('events', 'Events', 'Discover and join events', 'two-column');
	}

	/**
	 * Create event page
	 */
	public static function createEvent() {
		self::requireAuth();
		self::renderPage('create-event', 'Create Event', 'Plan your perfect event', 'form');
	}

	/**
	 * Single event page
	 */
	public static function singleEvent($params) {
		$event_slug = $params['slug'];
		self::renderPage('single-event', 'Event Details', null, 'two-column', compact('event_slug'));
	}

	/**
	 * Edit event page
	 */
	public static function editEvent($params) {
		self::requireAuth();
		// Get event_id from query parameter
		$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
		if (!$event_id) {
			self::notFound();
			return;
		}
		self::renderPage('edit-event', 'Edit Event', null, 'form', compact('event_id'));
	}

	/**
	 * Manage event page (query parameter version)
	 */
	public static function manageEvent($params) {
		self::requireAuth();
		// Get event_id from query parameter
		$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
		if (!$event_id) {
			self::notFound();
			return;
		}
		self::renderPage('manage-event', 'Manage Event', null, 'two-column', compact('event_id'));
	}

	/**
	 * Edit event page (route parameter version)
	 */
	public static function editEventBySlug($params) {
		self::requireAuth();
		// Get event by slug, then pass event_id to template
		$event_manager = new VT_Event_Manager();
		$event = $event_manager->getEventBySlug($params['slug']);
		if (!$event) {
			self::notFound();
			return;
		}
		$event_id = $event->id;
		self::renderPage('edit-event', 'Edit Event', null, 'form', compact('event_id'));
	}

	/**
	 * Manage event page (route parameter version)
	 */
	public static function manageEventBySlug($params) {
		self::requireAuth();
		// Get event by slug, then pass event_id to template
		$event_manager = new VT_Event_Manager();
		$event = $event_manager->getEventBySlug($params['slug']);
		if (!$event) {
			self::notFound();
			return;
		}
		$event_id = $event->id;

		self::renderPage('manage-event', 'Manage Event', null, 'two-column', compact('event_id'));
	}

	/**
	 * Communities list page
	 */
	public static function communitiesList() {
		self::renderPage('communities', 'Communities', 'Connect with like-minded people', 'two-column');
	}

	/**
	 * Create community page
	 */
	public static function createCommunity() {
		self::requireAuth();
		self::renderPage('create-community', 'Create Community', 'Build your community', 'form');
	}

	/**
	 * Single community page
	 */
	public static function singleCommunity($params) {
		$community_slug = $params['slug'];
		self::renderPage('single-community', 'Community', null, 'two-column', compact('community_slug'));
	}

	/**
	 * Community conversations page
	 */
	public static function communityConversations($params) {
		$community_id = $params['id'];
		self::renderPage('community-conversations', 'Community Conversations', null, 'two-column', compact('community_id'));
	}

	/**
	 * Community events page
	 */
	public static function communityEvents($params) {
		$community_id = $params['id'];
		self::renderPage('community-events', 'Community Events', null, 'two-column', compact('community_id'));
	}

	/**
	 * Manage community page (query parameter version)
	 */
	public static function manageCommunity($params) {
		self::requireAuth();
		// Get community_id from query parameter
		$community_id = isset($_GET['community_id']) ? intval($_GET['community_id']) : 0;
		if (!$community_id) {
			self::notFound();
			return;
		}
		self::renderPage('manage-community', 'Manage Community', null, 'two-column', compact('community_id'));
	}

	/**
	 * Edit community page (route parameter version)
	 */
	public static function editCommunityBySlug($params) {
		self::requireAuth();
		// Get community by slug, then pass community_id to template
		$community_manager = new VT_Community_Manager();
		$community = $community_manager->getCommunityBySlug($params['slug']);
		if (!$community) {
			self::notFound();
			return;
		}
		$community_id = $community->id;
		self::renderPage('edit-community', 'Edit Community', null, 'form', compact('community_id'));
	}

	/**
	 * Manage community page (route parameter version)
	 */
	public static function manageCommunityBySlug($params) {
		self::requireAuth();
		// Get community by slug, then pass community_id to template
		$community_manager = new VT_Community_Manager();
		$community = $community_manager->getCommunityBySlug($params['slug']);
		if (!$community) {
			self::notFound();
			return;
		}
		$community_id = $community->id;
		self::renderPage('manage-community', 'Manage Community', null, 'two-column', compact('community_id'));
	}

	/**
	 * Manage community page (legacy route parameter version)
	 */
	public static function manageCommunityById($params) {
		self::requireAuth();
		// Get community_id from route parameter
		$community_id = isset($params['id']) ? intval($params['id']) : 0;
		if (!$community_id) {
			self::notFound();
			return;
		}
		self::renderPage('manage-community', 'Manage Community', null, 'two-column', compact('community_id'));
	}

	/**
	 * Conversations list page
	 */
	public static function conversationsList() {
		self::renderPage('conversations', 'Conversations', 'Join the discussion', 'two-column');
	}

	/**
	 * Create conversation page
	 */
	public static function createConversation() {
		self::requireAuth();
		self::renderPage('create-conversation', 'Start Conversation', 'Share your thoughts', 'form');
	}

	/**
	 * Single conversation page
	 */
	public static function singleConversation($params) {
		$conversation_slug = $params['slug'] ?? null;
		self::renderPage('single-conversation', 'Conversation', null, 'two-column', compact('conversation_slug'));
	}

	/**
	 * Edit conversation page
	 */
	public static function editConversation($params) {
		self::requireAuth();
		$conversation_id = $params['id'];
		self::renderPage('edit-conversation', 'Edit Conversation', null, 'form', compact('conversation_id'));
	}

	/**
	 * User profile page
	 */
	public static function profile($params = array()) {
		$user_id = isset($params['id']) ? $params['id'] : vt_service('auth.service')->getCurrentUserId();

		if (!$user_id && !isset($params['id'])) {
			self::requireAuth();
			$user_id = vt_service('auth.service')->getCurrentUserId();
		}

		self::renderPage('profile', 'Profile', null, 'two-column', compact('user_id'));
	}

	/**
	 * User profile page (when accessing someone else's profile)
	 */
	public static function userProfile($params) {
		$user_id = $params['id'];
		self::renderPage('user-profile', 'User Profile', null, 'two-column', compact('user_id'));
	}

	/**
	 * Guest RSVP page
	 */
	public static function guestRsvp($params) {
		$token = $params['token'];
		self::renderPage('guest-rsvp', 'RSVP to Event', 'Respond to your invitation', 'form', compact('token'));
	}

	/**
	 * 404 Not Found page
	 */
	public static function notFound() {
		http_response_code(404);
		self::renderPage('404', 'Page Not Found', 'The page you requested was not found', 'page');
	}

	/**
	 * 500 Server Error page
	 */
	public static function serverError($message = 'An unexpected error occurred') {
		http_response_code(500);
		self::renderPage('500', 'Server Error', $message, 'page');
	}

	/**
	 * Render a page using the VivalaTable template system
	 */
	private static function renderPage($template, $page_title, $page_description = null, $base_template = 'page', $data = array()) {
		// Set up template variables
		global $vt_page_title, $vt_page_description, $vt_breadcrumbs, $vt_nav_items;

		$vt_page_title = $page_title;
		$vt_page_description = $page_description;
		$vt_breadcrumbs = self::getBreadcrumbs();
		$vt_nav_items = self::getNavItems();

		// Make data available to templates
		if (!empty($data)) {
			extract($data);
		}

		// Content template path
		$templates_path = VT_Config::get('templates_path', VT_PLUGIN_DIR . '/templates');
		$content_template = $templates_path . '/' . $template . '-content.php';

		if (!file_exists($content_template)) {
			error_log('Template not found: ' . $content_template);
			self::renderFallbackPage($page_title, 'Template not found: ' . $template);
			return;
		}

		// Capture content
		ob_start();
		include $content_template;
		$content = ob_get_clean();

		// For two-column layout, capture sidebar using standardized sidebar
		$sidebar_content = '';
		if ($base_template === 'two-column') {
			$sidebar_template = VT_Config::get('templates_path', VT_PLUGIN_DIR . '/templates') . '/partials/sidebar-secondary-nav.php';
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
			self::renderFallbackPage($page_title, 'Base template not found: ' . $base_template);
			return;
		}

		// Make variables available to base template
		$main_content = $content;

		include $base_template_path;
	}

	/**
	 * Render fallback page when templates are missing
	 */
	private static function renderFallbackPage($title, $message) {
		echo '<!DOCTYPE html>';
		echo '<html><head><title>' . htmlspecialchars($title) . ' - VivalaTable</title></head>';
		echo '<body><h1>' . htmlspecialchars($title) . '</h1>';
		echo '<p>' . htmlspecialchars($message) . '</p>';
		echo '</body></html>';
	}

	/**
	 * Get breadcrumbs for current page
	 */
	private static function getBreadcrumbs() {
		$route = VT_Router::getCurrentRoute();
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
	private static function getNavItems() {
		$current_path = VT_Router::getCurrentRoute()['route']['path'] ?? '';

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
	private static function requireAuth() {
		if (!vt_service('auth.service')->isLoggedIn()) {
			VT_Router::redirect('/login');
		}
	}
}