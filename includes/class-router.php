<?php
/**
 * VivalaTable Router
 * Handles URL routing and page dispatch for the VivalaTable application
 * Replaces WordPress rewrite rules with PHP routing
 */

class VT_Router {

	private static $routes = array();
	private static $current_route = null;

	/**
	 * Initialize routing system
	 */
	public static function init() {
		self::register_default_routes();
		self::dispatch();
	}

	/**
	 * Register default application routes
	 */
	private static function register_default_routes() {
		// Auth routes
		self::add_route('GET', '/', array('VT_Pages', 'dashboard'));
		self::add_route('GET', '/login', array('VT_Pages', 'login'));
		self::add_route('POST', '/login', array('VT_Pages', 'login'));
		self::add_route('GET', '/logout', array('VT_Auth', 'logout_and_redirect'));
		self::add_route('GET', '/register', array('VT_Pages', 'register'));
		self::add_route('POST', '/register', array('VT_Pages', 'register'));

		// Event routes
		self::add_route('GET', '/events', array('VT_Pages', 'events_list'));
		self::add_route('GET', '/events/create', array('VT_Pages', 'create_event'));
		self::add_route('POST', '/events/create', array('VT_Pages', 'create_event'));
		self::add_route('GET', '/events/{id}', array('VT_Pages', 'single_event'));
		self::add_route('GET', '/events/{id}/edit', array('VT_Pages', 'edit_event'));
		self::add_route('POST', '/events/{id}/edit', array('VT_Pages', 'edit_event'));
		self::add_route('GET', '/events/{id}/manage', array('VT_Pages', 'manage_event'));

		// Community routes
		self::add_route('GET', '/communities', array('VT_Pages', 'communities_list'));
		self::add_route('GET', '/communities/create', array('VT_Pages', 'create_community'));
		self::add_route('POST', '/communities/create', array('VT_Pages', 'create_community'));
		self::add_route('GET', '/communities/{id}', array('VT_Pages', 'single_community'));
		self::add_route('GET', '/communities/{id}/conversations', array('VT_Pages', 'community_conversations'));
		self::add_route('GET', '/communities/{id}/events', array('VT_Pages', 'community_events'));
		self::add_route('GET', '/communities/{id}/manage', array('VT_Pages', 'manage_community'));

		// Conversation routes
		self::add_route('GET', '/conversations', array('VT_Pages', 'conversations_list'));
		self::add_route('GET', '/conversations/create', array('VT_Pages', 'create_conversation'));
		self::add_route('POST', '/conversations/create', array('VT_Pages', 'create_conversation'));
		self::add_route('GET', '/conversations/{id}', array('VT_Pages', 'single_conversation'));
		self::add_route('GET', '/conversations/{id}/edit', array('VT_Pages', 'edit_conversation'));
		self::add_route('POST', '/conversations/{id}/edit', array('VT_Pages', 'edit_conversation'));

		// Profile routes
		self::add_route('GET', '/profile', array('VT_Pages', 'profile'));
		self::add_route('POST', '/profile', array('VT_Pages', 'profile'));
		self::add_route('GET', '/profile/{id}', array('VT_Pages', 'user_profile'));

		// Guest RSVP routes
		self::add_route('GET', '/rsvp/{token}', array('VT_Pages', 'guest_rsvp'));
		self::add_route('POST', '/rsvp/{token}', array('VT_Pages', 'guest_rsvp'));

		// API routes
		self::add_route('GET', '/api/search', array('VT_Search_API', 'search_content'));
		self::add_route('POST', '/api/events/{id}/rsvp', array('VT_Event_Ajax_Handler', 'handle_rsvp'));
		self::add_route('POST', '/api/communities/{id}/join', array('VT_Community_Ajax_Handler', 'handle_join'));
		self::add_route('POST', '/api/conversations', array('VT_Conversation_Ajax_Handler', 'handle_create'));
	}

	/**
	 * Add a route
	 */
	public static function add_route($method, $path, $handler) {
		$pattern = self::convert_path_to_regex($path);
		self::$routes[] = array(
			'method' => strtoupper($method),
			'path' => $path,
			'pattern' => $pattern,
			'handler' => $handler,
		);
	}

	/**
	 * Convert path with parameters to regex pattern
	 */
	private static function convert_path_to_regex($path) {
		// Escape forward slashes and convert {param} to named capture groups
		$pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $path);
		$pattern = str_replace('/', '\/', $pattern);
		return '/^' . $pattern . '$/';
	}

	/**
	 * Dispatch the current request to appropriate handler
	 */
	public static function dispatch() {
		$method = $_SERVER['REQUEST_METHOD'];
		$path = self::get_request_path();

		foreach (self::$routes as $route) {
			if ($route['method'] !== $method) {
				continue;
			}

			if (preg_match($route['pattern'], $path, $matches)) {
				// Extract named parameters
				$params = array();
				foreach ($matches as $key => $value) {
					if (is_string($key)) {
						$params[$key] = $value;
					}
				}

				self::$current_route = array(
					'route' => $route,
					'params' => $params,
				);

				return self::call_handler($route['handler'], $params);
			}
		}

		// No route found - 404
		self::handle_404();
	}

	/**
	 * Get the current request path
	 */
	private static function get_request_path() {
		$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

		// Remove script name if present (for development servers)
		$script_name = dirname($_SERVER['SCRIPT_NAME']);
		if ($script_name !== '/' && strpos($path, $script_name) === 0) {
			$path = substr($path, strlen($script_name));
		}

		return $path ?: '/';
	}

	/**
	 * Call the route handler
	 */
	private static function call_handler($handler, $params = array()) {
		if (is_callable($handler)) {
			return call_user_func($handler, $params);
		}

		if (is_array($handler) && count($handler) === 2) {
			list($class, $method) = $handler;

			if (class_exists($class) && method_exists($class, $method)) {
				return call_user_func(array($class, $method), $params);
			}
		}

		// Handler not found
		self::handle_500('Handler not found');
	}

	/**
	 * Handle 404 errors
	 */
	private static function handle_404() {
		http_response_code(404);
		if (class_exists('VT_Pages') && method_exists('VT_Pages', 'not_found')) {
			VT_Pages::not_found();
		} else {
			echo '<h1>404 Not Found</h1><p>The requested page was not found.</p>';
		}
		exit;
	}

	/**
	 * Handle 500 errors
	 */
	private static function handle_500($message = 'Internal Server Error') {
		http_response_code(500);
		if (class_exists('VT_Pages') && method_exists('VT_Pages', 'server_error')) {
			VT_Pages::server_error($message);
		} else {
			echo '<h1>500 Internal Server Error</h1><p>' . htmlspecialchars($message) . '</p>';
		}
		exit;
	}

	/**
	 * Get current route information
	 */
	public static function get_current_route() {
		return self::$current_route;
	}

	/**
	 * Get route parameter value
	 */
	public static function get_param($key, $default = null) {
		if (self::$current_route && isset(self::$current_route['params'][$key])) {
			return self::$current_route['params'][$key];
		}
		return $default;
	}

	/**
	 * Generate URL for a route
	 */
	public static function url($path, $params = array()) {
		$url = $path;

		// Replace path parameters
		foreach ($params as $key => $value) {
			$url = str_replace('{' . $key . '}', $value, $url);
		}

		return VT_Http::getBaseUrl() . $url;
	}

	/**
	 * Redirect to a route
	 */
	public static function redirect($path, $params = array(), $status_code = 302) {
		$url = self::url($path, $params);
		header('Location: ' . $url, true, $status_code);
		exit;
	}

	/**
	 * Check if current request is API request
	 */
	public static function is_api_request() {
		return strpos(self::get_request_path(), '/api/') === 0;
	}

	/**
	 * Send JSON response for API requests
	 */
	public static function json_response($data, $status_code = 200) {
		http_response_code($status_code);
		header('Content-Type: application/json');
		echo json_encode($data);
		exit;
	}

	/**
	 * Get all registered routes (for debugging)
	 */
	public static function get_routes() {
		return self::$routes;
	}
}