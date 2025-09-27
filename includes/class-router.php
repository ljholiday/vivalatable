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
		self::registerDefaultRoutes();
		self::dispatch();
	}

	/**
	 * Register default application routes
	 */
	private static function registerDefaultRoutes() {
		// Auth routes
		self::addRoute('GET', '/', array('VT_Pages', 'dashboard'));
		self::addRoute('GET', '/dashboard', array('VT_Pages', 'dashboard'));
		self::addRoute('GET', '/login', array('VT_Pages', 'login'));
		self::addRoute('POST', '/login', array('VT_Pages', 'login'));
		self::addRoute('GET', '/logout', array('VT_Auth', 'logoutAndRedirect'));
		self::addRoute('GET', '/register', array('VT_Pages', 'register'));
		self::addRoute('POST', '/register', array('VT_Pages', 'register'));

		// Event routes
		self::addRoute('GET', '/events', array('VT_Pages', 'eventsList'));
		self::addRoute('GET', '/events/create', array('VT_Pages', 'createEvent'));
		self::addRoute('POST', '/events/create', array('VT_Pages', 'createEvent'));
		self::addRoute('GET', '/events/{slug}', array('VT_Pages', 'singleEvent'));
		self::addRoute('GET', '/create-event', array('VT_Pages', 'createEvent'));
		self::addRoute('POST', '/create-event', array('VT_Pages', 'createEvent'));
		self::addRoute('GET', '/edit-event', array('VT_Pages', 'editEvent'));
		self::addRoute('POST', '/edit-event', array('VT_Pages', 'editEvent'));
		self::addRoute('GET', '/manage-event', array('VT_Pages', 'manageEvent'));
		self::addRoute('POST', '/manage-event', array('VT_Pages', 'manageEvent'));

		// Community routes
		self::addRoute('GET', '/communities', array('VT_Pages', 'communitiesList'));
		self::addRoute('GET', '/communities/create', array('VT_Pages', 'createCommunity'));
		self::addRoute('POST', '/communities/create', array('VT_Pages', 'createCommunity'));
		self::addRoute('GET', '/communities/{slug}', array('VT_Pages', 'singleCommunity'));
		self::addRoute('POST', '/communities/{slug}', array('VT_Pages', 'singleCommunity'));
		self::addRoute('GET', '/create-community', array('VT_Pages', 'createCommunity'));
		self::addRoute('POST', '/create-community', array('VT_Pages', 'createCommunity'));
		self::addRoute('GET', '/manage-community', array('VT_Pages', 'manageCommunity'));
		self::addRoute('POST', '/manage-community', array('VT_Pages', 'manageCommunity'));

		// Conversation routes
		self::addRoute('GET', '/conversations', array('VT_Pages', 'conversationsList'));
		self::addRoute('GET', '/conversations/create', array('VT_Pages', 'createConversation'));
		self::addRoute('POST', '/conversations/create', array('VT_Pages', 'createConversation'));
		self::addRoute('GET', '/conversations/{slug}', array('VT_Pages', 'singleConversation'));
		self::addRoute('POST', '/conversations/{slug}', array('VT_Pages', 'singleConversation'));
		self::addRoute('GET', '/create-conversation', array('VT_Pages', 'createConversation'));
		self::addRoute('POST', '/create-conversation', array('VT_Pages', 'createConversation'));

		// Profile routes
		self::addRoute('GET', '/profile', array('VT_Pages', 'profile'));
		self::addRoute('POST', '/profile', array('VT_Pages', 'profile'));
		self::addRoute('GET', '/profile/{id}', array('VT_Pages', 'userProfile'));

		// Guest RSVP routes
		self::addRoute('GET', '/rsvp/{token}', array('VT_Pages', 'guestRsvp'));
		self::addRoute('POST', '/rsvp/{token}', array('VT_Pages', 'guestRsvp'));

		// API routes
		self::addRoute('GET', '/api/search', array('VT_Search_API', 'searchContent'));
		self::addRoute('POST', '/api/events/{id}/rsvp', array('VT_Event_Ajax_Handler', 'handleRsvp'));
		self::addRoute('POST', '/api/communities/{id}/join', array('VT_Community_Ajax_Handler', 'handleJoin'));
		self::addRoute('POST', '/api/conversations', array('VT_Conversation_Ajax_Handler', 'handleCreate'));
	}

	/**
	 * Add a route
	 */
	public static function addRoute($method, $path, $handler) {
		$pattern = self::convertPathToRegex($path);
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
	private static function convertPathToRegex($path) {
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
		$path = self::getRequestPath();

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

				return self::callHandler($route['handler'], $params);
			}
		}

		// No route found - 404
		self::handle404();
	}

	/**
	 * Get the current request path
	 */
	private static function getRequestPath() {
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
	private static function callHandler($handler, $params = array()) {
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
		self::handle500('Handler not found');
	}

	/**
	 * Handle 404 errors
	 */
	private static function handle404() {
		http_response_code(404);
		if (class_exists('VT_Pages') && method_exists('VT_Pages', 'notFound')) {
			VT_Pages::notFound();
		} else {
			echo '<h1>404 Not Found</h1><p>The requested page was not found.</p>';
		}
		exit;
	}

	/**
	 * Handle 500 errors
	 */
	private static function handle500($message = 'Internal Server Error') {
		http_response_code(500);
		if (class_exists('VT_Pages') && method_exists('VT_Pages', 'serverError')) {
			VT_Pages::serverError($message);
		} else {
			echo '<h1>500 Internal Server Error</h1><p>' . htmlspecialchars($message) . '</p>';
		}
		exit;
	}

	/**
	 * Get current route information
	 */
	public static function getCurrentRoute() {
		return self::$current_route;
	}

	/**
	 * Get route parameter value
	 */
	public static function getParam($key, $default = null) {
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
	public static function isApiRequest() {
		return strpos(self::getRequestPath(), '/api/') === 0;
	}

	/**
	 * Send JSON response for API requests
	 */
	public static function jsonResponse($data, $status_code = 200) {
		http_response_code($status_code);
		header('Content-Type: application/json');
		echo json_encode($data);
		exit;
	}

	/**
	 * Get all registered routes (for debugging)
	 */
	public static function getRoutes() {
		return self::$routes;
	}
}