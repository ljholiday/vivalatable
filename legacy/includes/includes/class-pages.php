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
		// Check if already logged in
		if (vt_service('auth.service')->isLoggedIn()) {
			VT_Router::redirect('/dashboard');
		}

		$errors = array();
		$messages = array();

		// Handle POST before any output
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Verify CSRF nonce
			if (!vt_service('security.service')->verifyNonce($_POST['vt_login_nonce'] ?? '', 'vt_login')) {
				$errors[] = 'Security check failed. Please try again.';
				self::renderPage('login', 'Login', 'Sign in to your account', 'form', compact('errors', 'messages'));
				return;
			}

			// Sanitize and validate
			$username = trim($_POST['username'] ?? '');
			$password = $_POST['password'] ?? '';

			if (empty($username) || empty($password)) {
				$errors[] = 'Username and password are required.';
			} else {
				// Attempt login
				$user = vt_service('auth.service')->login($username, $password);

				if ($user) {
					// Login successful - redirect before any output
					$redirect_to = $_POST['redirect_to'] ?? $_GET['redirect_to'] ?? '/dashboard';
					VT_Router::redirect($redirect_to);
					return;
				} else {
					$errors[] = 'Invalid username or password.';
				}
			}

			// If we reach here, login failed - render with errors
			self::renderPage('login', 'Login', 'Sign in to your account', 'form', compact('errors', 'messages'));
			return;
		}

		// GET request - just show form
		self::renderPage('login', 'Login', 'Sign in to your account', 'form', compact('errors', 'messages'));
	}

	/**
	 * Register page
	 */
	public static function register() {
		// Check if already logged in
		if (vt_service('auth.service')->isLoggedIn()) {
			VT_Router::redirect('/dashboard');
		}

		$errors = array();
		$messages = array();

		// Handle guest token conversion
		$guest_token = $_GET['guest_token'] ?? '';
		$guest_data = null;
		if ($guest_token) {
			$guest_manager = new VT_Guest_Manager();
			$guest_data = $guest_manager->getGuestByToken($guest_token);
		}

		// Handle POST before any output
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Verify CSRF nonce
			if (!vt_service('security.service')->verifyNonce($_POST['vt_register_nonce'] ?? '', 'vt_register')) {
				$errors[] = 'Security check failed. Please try again.';
				self::renderPage('register', 'Create Account', 'Join VivalaTable', 'form', compact('errors', 'messages', 'guest_token', 'guest_data'));
				return;
			}

			// Sanitize and validate
			$username = trim($_POST['username'] ?? '');
			$email = trim($_POST['email'] ?? '');
			$password = $_POST['password'] ?? '';
			$confirm_password = $_POST['confirm_password'] ?? '';
			$display_name = trim($_POST['display_name'] ?? '');
			$guest_token = $_POST['guest_token'] ?? $guest_token;

			// Basic validation
			if (empty($username) || empty($email) || empty($password) || empty($display_name)) {
				$errors[] = 'All fields are required.';
			}

			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$errors[] = 'Please enter a valid email address.';
			}

			if (strlen($password) < 8) {
				$errors[] = 'Password must be at least 8 characters long.';
			}

			if ($password !== $confirm_password) {
				$errors[] = 'Passwords do not match.';
			}

			// If no validation errors, attempt registration
			if (empty($errors)) {
				$user_id = vt_service('auth.service')->register($username, $email, $password, $display_name);

				if ($user_id) {
					// Handle guest token conversion if applicable
					if ($guest_token && $guest_data) {
						$guest_manager = new VT_Guest_Manager();
						$guest_manager->convertGuestToUser($guest_data->id, array(
							'user_id' => $user_id,
							'username' => $username,
							'password' => $password
						));
					}

					// Redirect to login page after successful registration
					VT_Router::redirect('/login?registered=1');
					return;
				} else {
					$errors[] = 'Registration failed. Username or email may already exist.';
				}
			}

			// If we reach here, registration failed - render with errors
			self::renderPage('register', 'Create Account', 'Join VivalaTable', 'form', compact('errors', 'messages', 'guest_token', 'guest_data'));
			return;
		}

		// GET request - just show form
		self::renderPage('register', 'Create Account', 'Join VivalaTable', 'form', compact('errors', 'messages', 'guest_token', 'guest_data'));
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

		$errors = array();
		$messages = array();

		// Handle POST before any output
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Verify CSRF nonce
			if (!vt_service('security.service')->verifyNonce($_POST['vt_create_event_nonce'] ?? '', 'vt_create_event')) {
				$errors[] = 'Security check failed. Please try again.';
				self::renderPage('create-event', 'Create Event', 'Plan your perfect event', 'form', compact('errors', 'messages'));
				return;
			}

			// Prepare event data
			$event_data = array(
				'title' => trim($_POST['title'] ?? ''),
				'description' => trim($_POST['description'] ?? ''),
				'event_date' => $_POST['event_date'] ?? '',
				'event_time' => $_POST['event_time'] ?? '',
				'venue' => trim($_POST['venue_info'] ?? ''),
				'guest_limit' => intval($_POST['guest_limit'] ?? 0),
				'privacy' => $_POST['privacy'] ?? 'public'
			);

			// Basic validation
			if (empty($event_data['title'])) {
				$errors[] = 'Event title is required.';
			}
			if (empty($event_data['description'])) {
				$errors[] = 'Event description is required.';
			}
			if (empty($event_data['event_date'])) {
				$errors[] = 'Event date is required.';
			}

			// If no validation errors, create event
			if (empty($errors)) {
				$event_manager = new VT_Event_Manager();
				$result = $event_manager->createEventForm($event_data);

				if (isset($result['success']) && $result['success']) {
					// Redirect to the new event page using slug
					$event_slug = $result['slug'] ?? null;
					if ($event_slug) {
						VT_Router::redirect('/events/' . $event_slug);
						return;
					}
					// Fallback to events list if slug not available
					VT_Router::redirect('/events');
					return;
				} elseif (isset($result['error'])) {
					$errors[] = $result['error'];
				} else {
					$errors[] = 'Failed to create event. Please try again.';
				}
			}

			// If we reach here, event creation failed - render with errors
			self::renderPage('create-event', 'Create Event', 'Plan your perfect event', 'form', compact('errors', 'messages'));
			return;
		}

		// GET request - show empty form
		self::renderPage('create-event', 'Create Event', 'Plan your perfect event', 'form', compact('errors', 'messages'));
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

		$errors = array();
		$messages = array();
		$current_user = vt_service('auth.service')->getCurrentUser();

		// Get event by slug
		$event_manager = new VT_Event_Manager();
		$event_slug = $params['slug'] ?? null;

		if (!$event_slug) {
			self::notFound();
			return;
		}

		$event = $event_manager->getEventBySlug($event_slug);
		if (!$event) {
			self::notFound();
			return;
		}

		// Check permissions
		if ($event->author_id != $current_user->id) {
			http_response_code(403);
			self::renderPage('403', 'Access Denied', 'You don\'t have permission to edit this event', 'page');
			return;
		}

		// Get user's communities for dropdown
		$community_manager = new VT_Community_Manager();
		$user_communities = $community_manager->getUserCommunities($current_user->id);

		// Handle POST before any output
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Verify CSRF nonce
			if (!vt_service('security.service')->verifyNonce($_POST['edit_event_nonce'] ?? '', 'vt_edit_event')) {
				$errors[] = 'Security check failed. Please try again.';
				self::renderPage('edit-event', 'Edit Event', null, 'form', compact('errors', 'messages', 'event', 'user_communities'));
				return;
			}

			// Prepare event data
			$event_data = array(
				'title' => vt_service('validation.sanitizer')->textField($_POST['title'] ?? ''),
				'description' => vt_service('validation.sanitizer')->richText($_POST['description'] ?? ''),
				'event_date' => vt_service('validation.sanitizer')->textField($_POST['event_date'] ?? ''),
				'venue_info' => vt_service('validation.sanitizer')->textField($_POST['venue_info'] ?? ''),
				'guest_limit' => vt_service('validation.sanitizer')->integer($_POST['guest_limit'] ?? 0),
				'privacy' => vt_service('validation.sanitizer')->textField($_POST['privacy'] ?? 'public'),
				'community_id' => vt_service('validation.sanitizer')->integer($_POST['community_id'] ?? 0)
			);

			// Basic validation
			if (empty($event_data['title'])) {
				$errors[] = 'Event title is required.';
			}
			if (empty($event_data['event_date'])) {
				$errors[] = 'Event date is required.';
			}

			// If no validation errors, update the event
			if (empty($errors)) {
				$result = $event_manager->updateEvent($event->id, $event_data);
				if ($result) {
					// Redirect to event page on success
					VT_Router::redirect('/events/' . $event_slug);
					return;
				} else {
					$errors[] = 'Failed to update event. Please try again.';
				}
			}

			// If we reach here, update failed - render with errors
			self::renderPage('edit-event', 'Edit Event', null, 'form', compact('errors', 'messages', 'event', 'user_communities'));
			return;
		}

		// GET request - show form
		self::renderPage('edit-event', 'Edit Event', null, 'form', compact('errors', 'messages', 'event', 'user_communities'));
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

		$errors = array();
		$messages = array();

		// Handle POST before any output
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Verify CSRF nonce
			if (!vt_service('security.service')->verifyNonce($_POST['vt_create_community_nonce'] ?? '', 'vt_create_community')) {
				$errors[] = 'Security check failed. Please try again.';
				self::renderPage('create-community', 'Create Community', 'Build your community', 'form', compact('errors', 'messages'));
				return;
			}

			// Prepare community data
			$community_data = array(
				'name' => trim($_POST['name'] ?? ''),
				'description' => trim($_POST['description'] ?? ''),
				'privacy' => $_POST['privacy'] ?? 'public',
				'creator_email' => vt_service('auth.service')->getCurrentUser()->email ?? ''
			);

			// Basic validation
			if (empty($community_data['name'])) {
				$errors[] = 'Community name is required.';
			}
			if (empty($community_data['description'])) {
				$errors[] = 'Community description is required.';
			}

			// If no validation errors, create community
			if (empty($errors)) {
				$community_manager = new VT_Community_Manager();
				$result = $community_manager->createCommunity($community_data);

				if (is_vt_error($result)) {
					// Handle VT_Error object
					$errors[] = $result->get_error_message();
				} elseif (is_array($result) && isset($result['error'])) {
					$errors[] = $result['error'];
				} elseif (is_numeric($result)) {
					// Get the community to retrieve its slug
					$community_id = $result;
					$community = $community_manager->getCommunity($community_id);
					if ($community && isset($community->slug)) {
						VT_Router::redirect('/communities/' . $community->slug);
					} else {
						VT_Router::redirect('/communities');
					}
					return;
				} else {
					$errors[] = 'Failed to create community. Please try again.';
				}
			}

			// If we reach here, community creation failed - render with errors
			self::renderPage('create-community', 'Create Community', 'Build your community', 'form', compact('errors', 'messages'));
			return;
		}

		// GET request - show empty form
		self::renderPage('create-community', 'Create Community', 'Build your community', 'form', compact('errors', 'messages'));
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

		$errors = array();
		$messages = array();
		$current_user = vt_service('auth.service')->getCurrentUser();

		// Get community by slug
		$community_manager = new VT_Community_Manager();
		$community_slug = $params['slug'] ?? null;

		if (!$community_slug) {
			self::notFound();
			return;
		}

		$community = $community_manager->getCommunityBySlug($community_slug);
		if (!$community) {
			self::notFound();
			return;
		}

		// Check permissions
		if (!$community_manager->canManageCommunity($community->id, $current_user->id)) {
			http_response_code(403);
			self::renderPage('403', 'Access Denied', 'You don\'t have permission to edit this community', 'page');
			return;
		}

		// Handle POST before any output
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Verify CSRF nonce
			if (!vt_service('security.service')->verifyNonce($_POST['edit_community_nonce'] ?? '', 'vt_edit_community')) {
				$errors[] = 'Security check failed. Please try again.';
				self::renderPage('edit-community', 'Edit Community', null, 'form', compact('errors', 'messages', 'community'));
				return;
			}

			// Prepare community data
			$community_data = array(
				'name' => vt_service('validation.sanitizer')->textField($_POST['community_name'] ?? ''),
				'description' => vt_service('validation.sanitizer')->richText($_POST['description'] ?? ''),
				'privacy' => vt_service('validation.sanitizer')->textField($_POST['privacy'] ?? 'public'),
			);

			// Handle cover image removal
			if (isset($_POST['remove_cover_image']) && $_POST['remove_cover_image'] == '1') {
				$community_data['featured_image'] = '';
			}

			// Handle cover image upload
			if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
				$upload_result = VT_Image_Manager::handleImageUpload($_FILES['cover_image'], 'cover', $community->id, 'community');
				if ($upload_result['success']) {
					$community_data['featured_image'] = $upload_result['url'];
				} else {
					$errors[] = $upload_result['error'];
				}
			}

			// Basic validation
			if (empty($community_data['name'])) {
				$errors[] = 'Community name is required.';
			}

			// If no validation errors, update the community
			if (empty($errors)) {
				$result = $community_manager->updateCommunity($community->id, $community_data);
				if ($result) {
					// Redirect to community page on success
					VT_Router::redirect('/communities/' . $community_slug);
					return;
				} else {
					$errors[] = 'Failed to update community. Please try again.';
				}
			}

			// If we reach here, update failed - render with errors
			self::renderPage('edit-community', 'Edit Community', null, 'form', compact('errors', 'messages', 'community'));
			return;
		}

		// GET request - show form
		self::renderPage('edit-community', 'Edit Community', null, 'form', compact('errors', 'messages', 'community'));
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
	 * Invitation acceptance page
	 */
	public static function acceptInvitation() {
		self::renderPage('invitation-accept', 'Accept Invitation', null, 'two-column');
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

		$errors = array();
		$messages = array();
		$current_user = vt_service('auth.service')->getCurrentUser();

		// Get optional community or event context
		$community_id = intval($_GET['community_id'] ?? 0);
		$event_id = intval($_GET['event_id'] ?? 0);

		// Load managers
		$conversation_manager = new VT_Conversation_Manager();
		$community_manager = new VT_Community_Manager();
		$event_manager = new VT_Event_Manager();

		// Get context data
		$community = null;
		$event = null;
		$user_communities = $community_manager->getUserCommunities($current_user->id);

		if ($community_id) {
			$community = $community_manager->getCommunity($community_id);
		}
		if ($event_id) {
			$event = $event_manager->getEvent($event_id);
		}

		// Handle POST before any output
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Verify CSRF nonce
			if (!vt_service('security.service')->verifyNonce($_POST['create_conversation_nonce'] ?? '', 'vt_create_conversation')) {
				$errors[] = 'Security check failed. Please try again.';
				self::renderPage('create-conversation', 'Start Conversation', 'Share your thoughts', 'form', compact('errors', 'messages', 'current_user', 'community_id', 'event_id', 'community', 'event', 'user_communities'));
				return;
			}

			// Prepare conversation data with mutual exclusivity
			$posted_event_id = intval($_POST['event_id'] ?? 0);
			$posted_community_id = intval($_POST['community_id'] ?? 0);

			// Use if/elseif to ensure only one context is set
			if (!empty($posted_event_id)) {
				// Event conversation - event_id only
				$final_event_id = $posted_event_id;
				$final_community_id = null;
			} elseif (!empty($posted_community_id)) {
				// Community conversation - community_id only
				$final_event_id = null;
				$final_community_id = $posted_community_id;
			} else {
				// No context - will error below
				$final_event_id = null;
				$final_community_id = null;
			}

			$conversation_data = array(
				'title' => vt_service('validation.sanitizer')->textField($_POST['title'] ?? ''),
				'content' => vt_service('validation.sanitizer')->richText($_POST['content'] ?? ''),
				'community_id' => $final_community_id,
				'event_id' => $final_event_id,
				'privacy' => vt_service('validation.sanitizer')->textField($_POST['privacy'] ?? 'public'),
				'author_id' => $current_user->id,
				'author_name' => $current_user->display_name ?: $current_user->username,
				'author_email' => $current_user->email
			);

			// Basic validation
			if (empty($conversation_data['title'])) {
				$errors[] = 'Conversation title is required.';
			}
			if (empty($conversation_data['content'])) {
				$errors[] = 'Conversation content is required.';
			}
			if (empty($conversation_data['community_id']) && empty($conversation_data['event_id'])) {
				$errors[] = 'Conversation must be created in an event or community.';
			}

			// If no validation errors, create the conversation
			if (empty($errors)) {
				$conversation_id = $conversation_manager->createConversation($conversation_data);
				if ($conversation_id) {
					$conversation = $conversation_manager->getConversation($conversation_id);
					// Redirect to the new conversation
					VT_Router::redirect('/conversations/' . $conversation->slug);
					return;
				} else {
					$errors[] = 'Failed to create conversation. Please try again.';
				}
			}

			// If we reach here, conversation creation failed - render with errors
			self::renderPage('create-conversation', 'Start Conversation', 'Share your thoughts', 'form', compact('errors', 'messages', 'current_user', 'community_id', 'event_id', 'community', 'event', 'user_communities'));
			return;
		}

		// GET request - redirect if no context
		if (empty($event_id) && empty($community_id)) {
			VT_Router::redirect('/conversations');
			return;
		}

		// GET request - show form with context
		self::renderPage('create-conversation', 'Start Conversation', 'Share your thoughts', 'form', compact('errors', 'messages', 'current_user', 'community_id', 'event_id', 'community', 'event', 'user_communities'));
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
	 * Edit conversation page (route parameter version)
	 */
	public static function editConversationBySlug($params) {
		self::requireAuth();

		$errors = array();
		$messages = array();
		$current_user = vt_service('auth.service')->getCurrentUser();

		// Get conversation by slug
		$conversation_manager = new VT_Conversation_Manager();
		$conversation_slug = $params['slug'] ?? null;

		if (!$conversation_slug) {
			self::notFound();
			return;
		}

		$conversation = $conversation_manager->getConversationBySlug($conversation_slug);
		if (!$conversation) {
			self::notFound();
			return;
		}

		// Check permissions
		if (!$conversation_manager->canEditConversation($conversation->id, $current_user->id)) {
			http_response_code(403);
			self::renderPage('403', 'Access Denied', 'You don\'t have permission to edit this conversation', 'page');
			return;
		}

		// Get user's communities for dropdown
		$community_manager = new VT_Community_Manager();
		$user_communities = $community_manager->getUserCommunities($current_user->id);

		// Handle POST before any output
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Verify CSRF nonce
			if (!vt_service('security.service')->verifyNonce($_POST['edit_conversation_nonce'] ?? '', 'vt_edit_conversation')) {
				$errors[] = 'Security check failed. Please try again.';
				self::renderPage('edit-conversation', 'Edit Conversation', null, 'form', compact('errors', 'messages', 'conversation', 'user_communities'));
				return;
			}

			// Prepare conversation data
			$conversation_data = array(
				'title' => vt_service('validation.sanitizer')->textField($_POST['title'] ?? ''),
				'content' => vt_service('validation.sanitizer')->richText($_POST['content'] ?? ''),
				'community_id' => vt_service('validation.sanitizer')->integer($_POST['community_id'] ?? 0)
			);

			// Basic validation
			if (empty($conversation_data['title'])) {
				$errors[] = 'Conversation title is required.';
			}
			if (empty($conversation_data['content'])) {
				$errors[] = 'Content is required.';
			}
			if (empty($conversation_data['community_id'])) {
				$errors[] = 'Community is required.';
			}

			// If no validation errors, update the conversation
			if (empty($errors)) {
				$result = $conversation_manager->updateConversation($conversation->id, $conversation_data);
				if ($result) {
					// Redirect to conversation page on success
					VT_Router::redirect('/conversations/' . $conversation_slug);
					return;
				} else {
					$errors[] = 'Failed to update conversation. Please try again.';
				}
			}

			// If we reach here, update failed - render with errors
			self::renderPage('edit-conversation', 'Edit Conversation', null, 'form', compact('errors', 'messages', 'conversation', 'user_communities'));
			return;
		}

		// GET request - show form
		self::renderPage('edit-conversation', 'Edit Conversation', null, 'form', compact('errors', 'messages', 'conversation', 'user_communities'));
	}

	/**
	 * User profile page
	 */
	public static function profile($params = array()) {
		$current_user = vt_service('auth.service')->getCurrentUser();
		$current_user_id = $current_user ? $current_user->id : null;

		// Get user ID from params or query parameter or default to current user
		$user_id = $params['id'] ?? $_GET['user'] ?? null;

		if (!$user_id && $current_user_id) {
			$user_id = $current_user_id;
		}

		if (!$user_id) {
			self::requireAuth();
			$user_id = vt_service('auth.service')->getCurrentUserId();
		}

		$is_own_profile = ($user_id == $current_user_id);
		$is_editing = $is_own_profile && isset($_GET['edit']);

		// Get user data
		$user_data = vt_service('auth.user_repository')->getUserById($user_id);
		if (!$user_data) {
			self::notFound();
			return;
		}

		// Get profile data
		$profile_data = VT_Profile_Manager::getUserProfile($user_id);

		$profile_updated = false;
		$form_errors = array();

		// Handle profile update POST
		if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST') {
			// Verify CSRF nonce
			if (!vt_service('security.service')->verifyNonce($_POST['vt_profile_nonce'] ?? '', 'vt_profile_update')) {
				$form_errors[] = 'Security verification failed. Please try again.';
			} else {
				$result = VT_Profile_Manager::updateProfile($user_id, $_POST);
				if ($result['success']) {
					// Redirect to profile view on success
					VT_Router::redirect('/profile?updated=1');
					return;
				} else {
					$form_errors = $result['errors'];
				}
			}

			// If we reach here, update failed - render with errors
			self::renderPage('profile', 'Profile', null, 'two-column', compact('user_id', 'user_data', 'profile_data', 'is_own_profile', 'is_editing', 'profile_updated', 'form_errors'));
			return;
		}

		// GET request - show profile
		self::renderPage('profile', 'Profile', null, 'two-column', compact('user_id', 'user_data', 'profile_data', 'is_own_profile', 'is_editing', 'profile_updated', 'form_errors'));
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
		$templates_path = VT_Config::get('templates_path', VT_ROOT_DIR . '/templates');
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
			$sidebar_template = VT_Config::get('templates_path', VT_ROOT_DIR . '/templates') . '/partials/sidebar-secondary-nav.php';
			if (file_exists($sidebar_template)) {
				ob_start();
				include $sidebar_template;
				$sidebar_content = ob_get_clean();
			}
		}

		// Load base template
		$templates_path = VT_Config::get('templates_path', VT_ROOT_DIR . '/templates');
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