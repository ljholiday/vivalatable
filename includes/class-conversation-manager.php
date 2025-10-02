<?php
/**
 * VivalaTable Conversation Manager
 * Handles conversation creation, replies, and privacy
 * Ported from PartyMinder WordPress plugin
 */

class VT_Conversation_Manager {

	private $db;

	public function __construct() {
		$this->db = VT_Database::getInstance();
	}

	/**
	 * Get recent conversations across all types
	 */
	public function getRecentConversations($limit = 10, $exclude_event_conversations = false, $exclude_community_conversations = false) {
		$conversations_table = $this->db->prefix . 'conversations';
		$events_table = $this->db->prefix . 'events';
		$communities_table = $this->db->prefix . 'communities';
		$members_table = $this->db->prefix . 'community_members';
		$guests_table = $this->db->prefix . 'guests';

		$event_clause = $exclude_event_conversations ? 'AND c.event_id IS NULL' : '';
		$community_clause = $exclude_community_conversations ? 'AND c.community_id IS NULL' : '';
		$current_user_id = vt_service('auth.service')->getCurrentUserId();

		// Build privacy filter for conversations
		$privacy_filter = $this->buildConversationPrivacyFilter($current_user_id);

		return $this->db->getResults(
			$this->db->prepare(
				"SELECT c.*, e.title as event_title, e.slug as event_slug, cm.name as community_name, cm.slug as community_slug
				FROM $conversations_table c
				LEFT JOIN $events_table e ON c.event_id = e.id
				LEFT JOIN $communities_table cm ON c.community_id = cm.id
				WHERE ($privacy_filter) $event_clause $community_clause
				ORDER BY c.created_at DESC
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get event-related conversations
	 */
	public function getEventConversations($event_id = null, $limit = 10) {
		$conversations_table = $this->db->prefix . 'conversations';
		$events_table = $this->db->prefix . 'events';

		$where_clause = $event_id ? 'WHERE c.event_id = %d' : 'WHERE c.event_id IS NOT NULL AND c.event_id > 0';
		$prepare_values = $event_id ? array($event_id, $limit) : array($limit);


		return $this->db->getResults(
			$this->db->prepare(
				"SELECT DISTINCT c.*, e.title as event_title, e.slug as event_slug, e.event_date
				FROM $conversations_table c
				LEFT JOIN $events_table e ON c.event_id = e.id
				$where_clause
				ORDER BY c.created_at DESC
				LIMIT %d",
				...$prepare_values
			)
		);
	}

	/**
	 * Get community-related conversations
	 */
	public function getCommunityConversations($community_id = null, $limit = 10) {
		$conversations_table = $this->db->prefix . 'conversations';
		$communities_table = $this->db->prefix . 'communities';

		$where_clause = $community_id ? 'WHERE c.community_id = %d' : 'WHERE c.community_id IS NOT NULL AND c.community_id > 0';
		$prepare_values = $community_id ? array($community_id, $limit) : array($limit);

		return $this->db->getResults(
			$this->db->prepare(
				"SELECT DISTINCT c.*, cm.name as community_name, cm.slug as community_slug
				FROM $conversations_table c
				LEFT JOIN $communities_table cm ON c.community_id = cm.id
				$where_clause
				ORDER BY c.created_at DESC
				LIMIT %d",
				...$prepare_values
			)
		);
	}

	/**
	 * Get general conversations (not tied to events or communities)
	 */
	public function getGeneralConversations($limit = 10) {
		$conversations_table = $this->db->prefix . 'conversations';
		$current_user_id = vt_service('auth.service')->getCurrentUserId();

		// Build privacy filter for conversations
		$privacy_filter = $this->buildConversationPrivacyFilter($current_user_id);

		return $this->db->getResults(
			$this->db->prepare(
				"SELECT c.*, NULL as event_title, NULL as event_slug, NULL as community_name, NULL as community_slug
				FROM $conversations_table c
				WHERE c.event_id IS NULL AND c.community_id IS NULL
				AND ($privacy_filter)
				ORDER BY c.created_at DESC
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Create a new conversation
	 */
	public function createConversation($data) {
		$conversations_table = $this->db->prefix . 'conversations';

		// Community ID is now required (or event ID for event conversations)
		$community_id = $data['community_id'] ?? null;

		// Generate slug from title
		$slug = $this->generateConversationSlug($data['title']);

		$insert_data = array(
			'event_id' => $data['event_id'] ?? null,
			'community_id' => $community_id,
			'title' => $data['title'],
			'slug' => $slug,
			'content' => $data['content'],
			'author_id' => $data['author_id'] ?? vt_service('auth.service')->getCurrentUserId(),
			'author_name' => $data['author_name'],
			'author_email' => $data['author_email'],
			'privacy' => $this->validateConversationPrivacy($data['privacy'] ?? 'public', $data),
			'is_pinned' => $data['is_pinned'] ?? 0,
			'created_at' => VT_Time::currentTime('mysql'),
			'last_reply_date' => VT_Time::currentTime('mysql'),
			'last_reply_author' => $data['author_name'],
		);

		$result = $this->db->insert('conversations', $insert_data);

		if (!$result) {
			return false;
		}

		$conversation_id = $this->db->insert_id;

		// Auto-follow the conversation creator
		$author_id = $insert_data['author_id'];
		$this->followConversation($conversation_id, $author_id, $data['author_email']);

		return $conversation_id;
	}

	/**
	 * Add a reply to a conversation
	 */
	public function addReply($conversation_id, $data) {
		$replies_table = $this->db->prefix . 'conversation_replies';
		$conversations_table = $this->db->prefix . 'conversations';

		// Calculate depth level
		$depth = 0;
		if (!empty($data['parent_reply_id'])) {
			$parent = $this->db->getRow(
				$this->db->prepare(
					"SELECT depth_level FROM $replies_table WHERE id = %d",
					intval($data['parent_reply_id'])
				)
			);
			$depth = $parent ? ($parent->depth_level + 1) : 0;
			$depth = min($depth, 5); // Max depth of 5 levels
		}

		// Insert reply
		$insert_data = array(
			'conversation_id' => $conversation_id,
			'parent_reply_id' => !empty($data['parent_reply_id']) ? intval($data['parent_reply_id']) : null,
			'content' => $data['content'],
			'author_id' => $data['author_id'] ?? vt_service('auth.service')->getCurrentUserId(),
			'author_name' => $data['author_name'],
			'author_email' => $data['author_email'],
			'depth_level' => $depth,
			'created_at' => VT_Time::currentTime('mysql'),
		);

		$result = $this->db->insert('conversation_replies', $insert_data);

		if (!$result) {
			return false;
		}

		// Get insert ID immediately after insert (before any other queries)
		$reply_id = $this->db->insert_id;

		// Update conversation reply count and last reply info
		$reply_count = $this->db->getVar(
			$this->db->prepare(
				"SELECT COUNT(*) FROM $replies_table WHERE conversation_id = %d",
				$conversation_id
			)
		);

		$this->db->update(
			'conversations',
			array(
				'reply_count' => $reply_count,
				'last_reply_date' => VT_Time::currentTime('mysql'),
				'last_reply_author' => $data['author_name'],
			),
			array('id' => $conversation_id)
		);

		// Auto-follow the conversation for reply author
		$this->followConversation($conversation_id, $data['author_id'], $data['author_email']);

		// Mark conversation as updated for activity tracking
		$this->markConversationUpdated($conversation_id);

		return $reply_id;
	}

	/**
	 * Get conversation by ID or slug
	 */
	public function getConversation($identifier, $by_slug = false) {
		$conversations_table = $this->db->prefix . 'conversations';
		$field = $by_slug ? 'slug' : 'id';

		$conversation = $this->db->getRow(
			$this->db->prepare(
				"SELECT c.* FROM $conversations_table c WHERE c.$field = %s",
				$identifier
			)
		);

		if ($conversation && $by_slug === false) {
			// Get replies if getting by ID
			$conversation->replies = $this->getConversationReplies($conversation->id);
		}

		return $conversation;
	}

	/**
	 * Get conversation by slug
	 */
	public function getConversationBySlug($slug) {
		return $this->getConversation($slug, true);
	}

	/**
	 * Get replies for a conversation
	 */
	public function getConversationReplies($conversation_id) {
		$replies_table = $this->db->prefix . 'conversation_replies';

		return $this->db->getResults(
			$this->db->prepare(
				"SELECT * FROM $replies_table
				WHERE conversation_id = %d
				ORDER BY created_at DESC",
				$conversation_id
			)
		);
	}

	/**
	 * Follow a conversation
	 */
	public function followConversation($conversation_id, $user_id, $email) {
		$follows_table = $this->db->prefix . 'conversation_follows';

		// Check if already following
		$existing = $this->db->getVar(
			$this->db->prepare(
				"SELECT id FROM $follows_table
				WHERE conversation_id = %d AND user_id = %d AND email = %s",
				$conversation_id, $user_id, $email
			)
		);

		if ($existing) {
			return $existing; // Already following
		}

		$insert_data = array(
			'conversation_id' => $conversation_id,
			'user_id' => $user_id,
			'email' => $email,
			'last_read_at' => VT_Time::currentTime('mysql'),
			'notification_frequency' => 'immediate',
			'created_at' => VT_Time::currentTime('mysql'),
		);

		$result = $this->db->insert('conversation_follows', $insert_data);

		return $result ? $this->db->insert_id : false;
	}

	/**
	 * Unfollow a conversation
	 */
	public function unfollowConversation($conversation_id, $user_id, $email) {
		$follows_table = $this->db->prefix . 'conversation_follows';

		return $this->db->delete(
			'conversation_follows',
			array(
				'conversation_id' => $conversation_id,
				'user_id' => $user_id,
				'email' => $email,
			)
		);
	}

	/**
	 * Check if user is following a conversation
	 */
	public function isFollowing($conversation_id, $user_id, $email) {
		$follows_table = $this->db->prefix . 'conversation_follows';

		return (bool) $this->db->getVar(
			$this->db->prepare(
				"SELECT id FROM $follows_table
				WHERE conversation_id = %d AND user_id = %d AND email = %s",
				$conversation_id, $user_id, $email
			)
		);
	}

	/**
	 * Generate unique slug for conversation
	 */
	private function generateConversationSlug($title, $exclude_id = null) {
		$conversations_table = $this->db->prefix . 'conversations';
		$base_slug = vt_service('validation.sanitizer')->slug($title);
		$slug = $base_slug;
		$counter = 1;

		$where_clause = "WHERE slug = %s";
		$params = array($slug);

		if ($exclude_id) {
			$where_clause .= " AND id != %d";
			$params[] = $exclude_id;
		}

		while ($this->db->getVar($this->db->prepare("SELECT id FROM $conversations_table $where_clause", $params))) {
			$slug = $base_slug . '-' . $counter;
			$params[0] = $slug;
			++$counter;
		}

		return $slug;
	}

	/**
	 * Get conversation statistics
	 */
	public function getStats() {
		$conversations_table = $this->db->prefix . 'conversations';
		$replies_table = $this->db->prefix . 'conversation_replies';
		$follows_table = $this->db->prefix . 'conversation_follows';

		$stats = new stdClass();
		$stats->total_conversations = $this->db->getVar("SELECT COUNT(*) FROM $conversations_table");
		$stats->total_replies = $this->db->getVar("SELECT COUNT(*) FROM $replies_table");
		$stats->total_follows = $this->db->getVar("SELECT COUNT(*) FROM $follows_table");
		$stats->active_conversations = $this->db->getVar(
			"SELECT COUNT(*) FROM $conversations_table
			WHERE last_reply_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
		);

		return $stats;
	}

	/**
	 * Auto-create event conversation when event is created
	 */
	public function createEventConversation($event_id, $event_data) {
		$conversation_data = array(
			'event_id' => $event_id,
			'title' => sprintf('Planning: %s', $event_data['title']),
			'content' => sprintf(
				'Let\'s plan an amazing %s together! Share ideas, coordinate details, and help make this event unforgettable.',
				$event_data['title']
			),
			'author_id' => $event_data['author_id'],
			'author_name' => $event_data['author_name'],
			'author_email' => $event_data['author_email'],
		);

		return $this->createConversation($conversation_data);
	}

	/**
	 * Auto-create community conversation when community is created
	 */
	public function createCommunityConversation($community_id, $community_data) {
		$conversation_data = array(
			'community_id' => $community_id,
			'title' => sprintf('Welcome to %s!', $community_data['name']),
			'content' => sprintf(
				'Welcome to the %s community! This is our gathering place to connect, share experiences, and plan amazing events together. Please introduce yourself and let us know what brings you here!',
				$community_data['name']
			),
			'author_id' => $community_data['creator_id'],
			'author_name' => $community_data['creator_name'],
			'author_email' => $community_data['creator_email'],
			'is_pinned' => 1, // Pin the welcome conversation
		);

		return $this->createConversation($conversation_data);
	}

	/**
	 * Build privacy filter for conversations based on parent event/community privacy
	 */
	private function buildConversationPrivacyFilter($current_user_id) {
		$members_table = $this->db->prefix . 'community_members';
		$guests_table = $this->db->prefix . 'guests';
		// $invitations_table = $this->db->prefix . 'invitations'; // TODO: Phase 3.5

		if (!$current_user_id || !vt_service('auth.service')->isLoggedIn()) {
			// Non-logged in users can only see conversations from public events/communities
			return "(
				(c.event_id IS NULL AND c.community_id IS NULL AND c.privacy = 'public') OR
				(c.event_id IS NOT NULL AND e.privacy = 'public') OR
				(c.community_id IS NOT NULL AND cm.privacy = 'public')
			)";
		}

		$current_user = vt_service('auth.service')->getCurrentUser();
		$user_email = $current_user->email;

		return "(
			(c.event_id IS NULL AND c.community_id IS NULL AND (
				c.privacy = 'public' OR
				c.author_id = $current_user_id
			)) OR
			(c.event_id IS NOT NULL AND (
				e.privacy = 'public' OR
				e.author_id = $current_user_id OR
				(e.privacy = 'private' AND EXISTS(
					SELECT 1 FROM $guests_table g
					WHERE g.event_id = e.id AND g.email = '$user_email'
				))
			)) OR
			(c.community_id IS NOT NULL AND (
				cm.privacy = 'public' OR
				cm.creator_id = $current_user_id OR
				EXISTS(
					SELECT 1 FROM $members_table m
					WHERE m.community_id = cm.id AND m.user_id = $current_user_id
					AND m.status = 'active'
				)
			))
		)";
	}

	/**
	 * Validate conversation privacy setting and implement inheritance
	 */
	private function validateConversationPrivacy($privacy, $data) {
		// If conversation is tied to an event or community, inherit their privacy
		if (!empty($data['event_id'])) {
			return $this->getEventPrivacy($data['event_id']);
		}

		if (!empty($data['community_id'])) {
			return $this->getCommunityPrivacy($data['community_id']);
		}

		// For standalone conversations, validate the provided privacy
		$allowed_privacy_settings = array('public', 'friends', 'members');

		if (!in_array($privacy, $allowed_privacy_settings)) {
			return 'public'; // Default to public if invalid
		}

		return $privacy;
	}

	/**
	 * Get effective privacy for an event
	 */
	private function getEventPrivacy($event_id) {
		$events_table = $this->db->prefix . 'events';
		$event = $this->db->getRow(
			$this->db->prepare(
				"SELECT privacy, community_id FROM $events_table WHERE id = %d",
				$event_id
			)
		);

		if (!$event) {
			return 'public';
		}

		// If event is part of a community, it inherits community privacy
		if ($event->community_id) {
			return $this->getCommunityPrivacy($event->community_id);
		}

		return $event->privacy;
	}

	/**
	 * Get effective privacy for a community
	 */
	private function getCommunityPrivacy($community_id) {
		$communities_table = $this->db->prefix . 'communities';
		$privacy = $this->db->getVar(
			$this->db->prepare(
				"SELECT visibility FROM $communities_table WHERE id = %d",
				$community_id
			)
		);

		return $privacy ?: 'public';
	}

	/**
	 * Get the effective privacy for a conversation (resolving inheritance)
	 */
	public function getConversationPrivacy($conversation) {
		if ($conversation->event_id) {
			return $this->getEventPrivacy($conversation->event_id);
		}

		if ($conversation->community_id) {
			return $this->getCommunityPrivacy($conversation->community_id);
		}

		return $conversation->privacy;
	}

	/**
	 * Check if user can edit a conversation
	 * Author, community admin, or site admin can edit
	 */
	public function canEditConversation($conversation_id, $user_id = null) {
		if (!$user_id) {
			$user_id = vt_service('auth.service')->getCurrentUserId();
		}

		if (!$user_id) {
			return false;
		}

		// Site admins can edit anything
		if (vt_service('auth.service')->isSiteAdmin()) {
			return true;
		}

		// Get conversation
		$conversation = $this->getConversation($conversation_id);
		if (!$conversation) {
			return false;
		}

		// Author can edit
		if ($conversation->author_id == $user_id) {
			return true;
		}

		// Community admin can edit
		if ($conversation->community_id) {
			$community_manager = new VT_Community_Manager();
			if ($community_manager->canManageCommunity($conversation->community_id, $user_id)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user can delete a conversation
	 * Author can delete if no replies, community admin or site admin can always delete
	 */
	public function canDeleteConversation($conversation_id, $user_id = null) {
		if (!$user_id) {
			$user_id = vt_service('auth.service')->getCurrentUserId();
		}

		if (!$user_id) {
			return false;
		}

		// Site admins can delete anything
		if (vt_service('auth.service')->isSiteAdmin()) {
			return true;
		}

		// Get conversation
		$conversation = $this->getConversation($conversation_id);
		if (!$conversation) {
			return false;
		}

		// Community admin can delete
		if ($conversation->community_id) {
			$community_manager = new VT_Community_Manager();
			if ($community_manager->canManageCommunity($conversation->community_id, $user_id)) {
				return true;
			}
		}

		// Author can delete only if no replies
		if ($conversation->author_id == $user_id) {
			$replies_table = $this->db->prefix . 'conversation_replies';
			$reply_count = $this->db->getVar(
				$this->db->prepare(
					"SELECT COUNT(*) FROM $replies_table WHERE conversation_id = %d",
					$conversation_id
				)
			);
			return $reply_count == 0;
		}

		return false;
	}

	/**
	 * Check if user can pin a conversation
	 * Only community admin or site admin
	 */
	public function canPinConversation($conversation_id, $user_id = null) {
		if (!$user_id) {
			$user_id = vt_service('auth.service')->getCurrentUserId();
		}

		if (!$user_id) {
			return false;
		}

		// Site admins can pin anything
		if (vt_service('auth.service')->isSiteAdmin()) {
			return true;
		}

		// Get conversation
		$conversation = $this->getConversation($conversation_id);
		if (!$conversation) {
			return false;
		}

		// Community admin can pin
		if ($conversation->community_id) {
			$community_manager = new VT_Community_Manager();
			return $community_manager->canManageCommunity($conversation->community_id, $user_id);
		}

		return false;
	}

	/**
	 * Get conversations created by a specific user
	 */
	public function getUserConversations($user_id, $limit = 10) {
		$conversations_table = $this->db->prefix . 'conversations';
		$events_table = $this->db->prefix . 'events';
		$communities_table = $this->db->prefix . 'communities';

		return $this->db->getResults(
			$this->db->prepare(
				"SELECT c.*, e.title as event_title, e.slug as event_slug, cm.name as community_name, cm.slug as community_slug
				FROM $conversations_table c
				LEFT JOIN $events_table e ON c.event_id = e.id
				LEFT JOIN $communities_table cm ON c.community_id = cm.id
				WHERE c.author_id = %d
				ORDER BY c.created_at DESC
				LIMIT %d",
				$user_id, $limit
			)
		);
	}

	/**
	 * Generate a contextual display title for conversations
	 */
	public function getDisplayTitle($conversation, $show_context = true) {
		$title = $conversation->title;

		if (!$show_context) {
			return $title;
		}

		if (!empty($conversation->event_title)) {
			return $conversation->event_title . ': ' . $title;
		}

		if (!empty($conversation->community_name)) {
			return $conversation->community_name . ': ' . $title;
		}

		return $title;
	}

	/**
	 * Get conversations filtered by circle scope
	 */
	public function getConversationsByScope($scope, $topic_slug = '', $page = 1, $per_page = 20) {
		$conversations_table = $this->db->prefix . 'conversations';
		$events_table = $this->db->prefix . 'events';
		$communities_table = $this->db->prefix . 'communities';

		$offset = ($page - 1) * $per_page;

		// Build WHERE clause for scope filtering
		$where_conditions = array();

		// Include conversations by users in scope
		if (!empty($scope['users'])) {
			$user_ids_in = implode(',', array_map('intval', $scope['users']));
			$where_conditions[] = "c.author_id IN ($user_ids_in)";
		}

		// Include conversations in communities in scope
		if (!empty($scope['communities'])) {
			$community_ids_in = implode(',', array_map('intval', $scope['communities']));
			$where_conditions[] = "c.community_id IN ($community_ids_in)";
		}

		// If no scope conditions, return empty
		if (empty($where_conditions)) {
			return array();
		}

		$where_clause = '(' . implode(' OR ', $where_conditions) . ')';

		$query = $this->db->prepare(
			"SELECT c.*, e.title as event_title, e.slug as event_slug, cm.name as community_name, cm.slug as community_slug
			FROM $conversations_table c
			LEFT JOIN $events_table e ON c.event_id = e.id
			LEFT JOIN $communities_table cm ON c.community_id = cm.id
			WHERE $where_clause
			ORDER BY c.last_reply_date DESC
			LIMIT %d OFFSET %d",
			$per_page, $offset
		);

		return $this->db->getResults($query);
	}

	/**
	 * Get count of conversations in scope
	 */
	public function getConversationsCountByScope($scope, $topic_slug = '') {
		$conversations_table = $this->db->prefix . 'conversations';

		// Build WHERE clause for scope filtering
		$where_conditions = array();

		// Include conversations by users in scope
		if (!empty($scope['users'])) {
			$user_ids_in = implode(',', array_map('intval', $scope['users']));
			$where_conditions[] = "author_id IN ($user_ids_in)";
		}

		// Include conversations in communities in scope
		if (!empty($scope['communities'])) {
			$community_ids_in = implode(',', array_map('intval', $scope['communities']));
			$where_conditions[] = "community_id IN ($community_ids_in)";
		}

		// If no scope conditions, return 0
		if (empty($where_conditions)) {
			return 0;
		}

		$where_clause = '(' . implode(' OR ', $where_conditions) . ')';

		return intval($this->db->getVar(
			"SELECT COUNT(*) FROM $conversations_table WHERE $where_clause"
		));
	}

	/**
	 * Process content for URL embeds
	 */
	public function processContentEmbeds($content) {
		if (empty($content)) {
			return '';
		}

		// Store original content for URL detection
		$original_content = $content;

		// Escape HTML to prevent XSS from user content
		$content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

		// Apply paragraph formatting (works with escaped content)
		$content = VT_Text::autop($content);

		// Check for URLs and add embed cards
		$url = VT_Text::firstUrlInText($original_content);

		if ($url) {
			$embed = VT_Embed_Service::buildEmbedFromUrl($url);
			if ($embed && VT_Embed_Renderer::shouldRender($embed)) {
				// Add the embed after the content (embed HTML is already safely escaped in renderer)
				$content .= VT_Embed_Renderer::render($embed);
			}
		}

		return $content;
	}

	/**
	 * Update a reply in a conversation
	 */
	public function updateReply($reply_id, $data) {
		$replies_table = $this->db->prefix . 'conversation_replies';

		// Get current reply for validation
		$reply = $this->getReply($reply_id);
		if (!$reply) {
			return new VT_Error('reply_not_found', 'Reply not found');
		}

		// Prepare update data
		$update_data = array();
		if (isset($data['content'])) {
			$update_data['content'] = vt_service('validation.sanitizer')->richText($data['content']);
		}

		if (empty($update_data)) {
			return new VT_Error('no_data', 'No data to update');
		}

		// Update the reply
		$result = $this->db->update(
			'conversation_replies',
			$update_data,
			array('id' => $reply_id)
		);

		if ($result === false) {
			return new VT_Error('update_failed', 'Failed to update reply');
		}

		return true;
	}

	/**
	 * Get a single reply by ID
	 */
	public function getReply($reply_id) {
		$replies_table = $this->db->prefix . 'conversation_replies';

		return $this->db->getRow($this->db->prepare(
			"SELECT * FROM $replies_table WHERE id = %d",
			$reply_id
		));
	}

	/**
	 * Delete a reply from a conversation
	 */
	public function deleteReply($reply_id) {
		$replies_table = $this->db->prefix . 'conversation_replies';
		$conversations_table = $this->db->prefix . 'conversations';

		// Get reply data first for permission checking and conversation updates
		$reply = $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM $replies_table WHERE id = %d",
				$reply_id
			)
		);

		if (!$reply) {
			return false; // Reply not found
		}

		// Check permissions
		$current_user = vt_service('auth.service')->getCurrentUser();
		if (!vt_service('auth.service')->isLoggedIn()) {
			return false; // Must be logged in
		}

		// User can delete if they are the author or an admin
		$can_delete = (vt_service('auth.service')->getCurrentUserId() == $reply->author_id) || vt_service('auth.service')->currentUserCan('manage_options');
		if (!$can_delete) {
			return false; // Not authorized
		}

		// Delete the reply
		$result = $this->db->delete(
			'conversation_replies',
			array('id' => $reply_id)
		);

		if ($result === false) {
			return false; // Delete failed
		}

		// Update conversation reply count and last reply info
		$conversation_id = $reply->conversation_id;

		// Get updated reply count
		$new_reply_count = $this->db->getVar(
			$this->db->prepare(
				"SELECT COUNT(*) FROM $replies_table WHERE conversation_id = %d",
				$conversation_id
			)
		);

		// Get last reply info
		$last_reply = $this->db->getRow(
			$this->db->prepare(
				"SELECT created_at, author_name FROM $replies_table
				WHERE conversation_id = %d
				ORDER BY created_at DESC
				LIMIT 1",
				$conversation_id
			)
		);

		// Update conversation
		if ($last_reply) {
			// There are still replies
			$this->db->update(
				'conversations',
				array(
					'reply_count' => $new_reply_count,
					'last_reply_date' => $last_reply->created_at,
					'last_reply_author' => $last_reply->author_name,
				),
				array('id' => $conversation_id)
			);
		} else {
			// No more replies, use conversation creation date
			$conversation = $this->db->getRow(
				$this->db->prepare(
					"SELECT created_at, author_name FROM $conversations_table WHERE id = %d",
					$conversation_id
				)
			);

			$this->db->update(
				'conversations',
				array(
					'reply_count' => 0,
					'last_reply_date' => $conversation->created_at,
					'last_reply_author' => $conversation->author_name,
				),
				array('id' => $conversation_id)
			);
		}

		return true;
	}

	/**
	 * Get conversation by ID
	 */
	public function getConversationById($conversation_id) {
		$conversations_table = $this->db->prefix . 'conversations';

		return $this->db->getRow(
			$this->db->prepare(
				"SELECT * FROM $conversations_table WHERE id = %d",
				$conversation_id
			)
		);
	}

	/**
	 * Update conversation
	 */
	public function updateConversation($conversation_id, $update_data) {
		$conversations_table = $this->db->prefix . 'conversations';

		// Validate required fields
		if (empty($update_data['title']) || empty($update_data['content'])) {
			return new VT_Error('missing_data', 'Title and content are required');
		}

		// Prepare update data
		$data = array(
			'title' => $update_data['title'],
			'content' => $update_data['content'],
			'slug' => $this->generateConversationSlug($update_data['title'], $conversation_id),
		);

		// Only update privacy if provided (for standalone conversations)
		if (isset($update_data['privacy'])) {
			$data['privacy'] = $this->validatePrivacySetting($update_data['privacy']);
		}

		$result = $this->db->update(
			'conversations',
			$data,
			array('id' => $conversation_id)
		);

		if ($result === false) {
			return new VT_Error('update_failed', 'Failed to update conversation');
		}

		return $conversation_id;
	}

	/**
	 * Delete conversation and all related data
	 */
	public function deleteConversation($conversation_id) {
		// Get conversation first
		$conversation = $this->getConversationById($conversation_id);
		if (!$conversation) {
			return new VT_Error('conversation_not_found', 'Conversation not found');
		}

		$conversations_table = $this->db->prefix . 'conversations';
		$replies_table = $this->db->prefix . 'conversation_replies';

		// Start transaction
		try {
			$this->db->query('START TRANSACTION');

			// Delete all replies first
			$this->db->delete('conversation_replies', array('conversation_id' => $conversation_id));

			// Delete conversation followers
			$this->db->delete('conversation_follows', array('conversation_id' => $conversation_id));

			// Delete the conversation itself
			$result = $this->db->delete('conversations', array('id' => $conversation_id));

			if ($result === false) {
				throw new Exception('Failed to delete conversation');
			}

			// Delete any metadata
			VT_Meta::delete('conversation', $conversation_id, 'cover_image');

			// Commit transaction
			$this->db->query('COMMIT');

			return true;

		} catch (Exception $e) {
			// Rollback transaction on error
			$this->db->query('ROLLBACK');
			return new VT_Error('deletion_failed', $e->getMessage());
		}
	}

	/**
	 * Validate privacy setting for standalone conversations
	 */
	private function validatePrivacySetting($privacy) {
		$allowed_privacy_settings = array('public', 'friends', 'members');

		if (!in_array($privacy, $allowed_privacy_settings)) {
			return 'public'; // Default to public if invalid
		}

		return $privacy;
	}

	/**
	 * Mark conversation as updated for activity tracking
	 */
	private function markConversationUpdated($conversation_id) {
		if (class_exists('VT_Activity_Tracker')) {
			$tracking_table = $this->db->prefix . 'user_activity_tracking';

			// Clear all existing tracking for this conversation so it appears "new" to everyone
			$this->db->delete(
				'user_activity_tracking',
				array(
					'activity_type' => 'conversations',
					'item_id' => $conversation_id
				)
			);
		}
	}
}
