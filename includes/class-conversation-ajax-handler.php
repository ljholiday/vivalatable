<?php
/**
 * VivalaTable Conversation AJAX Handler
 * Handles all AJAX requests for conversation operations
 * Ported from PartyMinder WordPress plugin
 */

class VT_Conversation_Ajax_Handler {

	private $conversation_manager;
	private $event_manager;
	private $community_manager;

	public function __construct() {
		$this->initRoutes();
	}

	/**
	 * Initialize AJAX routes for VivalaTable
	 */
	private function initRoutes() {
		// Register AJAX endpoints with VT_Ajax system
		VT_Ajax::register('create_conversation', array($this, 'ajax_create_conversation'));
		VT_Ajax::register('update_conversation', array($this, 'ajax_update_conversation'));
		VT_Ajax::register('delete_conversation', array($this, 'ajax_delete_conversation'));
		VT_Ajax::register('add_reply', array($this, 'ajax_add_reply'));
		VT_Ajax::register('update_reply', array($this, 'ajax_update_reply'));
		VT_Ajax::register('delete_reply', array($this, 'ajax_delete_reply'));
		VT_Ajax::register('get_conversations', array($this, 'ajax_get_conversations'));
	}

	private function getConversationManager() {
		if (!$this->conversation_manager) {
			$this->conversation_manager = new VT_Conversation_Manager();
		}
		return $this->conversation_manager;
	}

	private function getEventManager() {
		if (!$this->event_manager) {
			$this->event_manager = new VT_Event_Manager();
		}
		return $this->event_manager;
	}

	private function getCommunityManager() {
		if (!$this->community_manager) {
			$this->community_manager = new VT_Community_Manager();
		}
		return $this->community_manager;
	}

	public function ajaxCreateConversation() {
		VT_Security::verifyNonce('vt_nonce', 'nonce');

		$current_user = VT_Auth::getCurrentUser();
		$user_email = '';
		$user_name = '';
		$user_id = 0;

		if (VT_Auth::isLoggedIn()) {
			$user_email = $current_user->email;
			$user_name = $current_user->display_name;
			$user_id = VT_Auth::getCurrentUserId();
		} else {
			$user_email = VT_Sanitize::email($_POST['guest_email'] ?? '');
			$user_name = VT_Sanitize::textField($_POST['guest_name'] ?? '');
			if (empty($user_email) || empty($user_name)) {
				VT_Ajax::sendError('Please provide your name and email to start a conversation.');
			}
		}

		$event_id = intval($_POST['event_id'] ?? 0);
		$community_id = intval($_POST['community_id'] ?? 0);
		$title = VT_Sanitize::textField($_POST['title'] ?? '');
		$content = VT_Security::ksesPost($_POST['content'] ?? '');

		if (empty($title) || empty($content)) {
			VT_Ajax::sendError('Please fill in all required fields.');
		}

		// If no community selected, default to author's personal community
		if (!$community_id && $user_id && VT_Config::get('general_convo_default_to_personal', true)) {
			if (class_exists('VT_Personal_Community_Service')) {
				$personal_community = VT_Personal_Community_Service::getPersonalCommunityForUser($user_id);
				if ($personal_community) {
					$community_id = $personal_community->id;
				}
			}
		}

		$conversation_manager = $this->getConversationManager();

		$conversation_data = array(
			'event_id' => $event_id ?: null,
			'community_id' => $community_id ?: null,
			'title' => $title,
			'content' => $content,
			'author_id' => $user_id,
			'author_name' => $user_name,
			'author_email' => $user_email,
		);

		$conversation_id = $conversation_manager->createConversation($conversation_data);

		if ($conversation_id) {
			// Handle cover image upload
			if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
				$upload_result = $this->handleCoverImageUpload($_FILES['cover_image'], $conversation_id);
				if (is_vt_error($upload_result)) {
					error_log('Cover image upload failed: ' . $upload_result->getErrorMessage());
				}
			}

			// Get the created conversation for URL generation
			$conversation = $conversation_manager->getConversationById($conversation_id);

			$success_data = array(
				'conversation_id' => $conversation_id,
				'message' => 'Conversation started successfully!',
			);

			if ($event_id) {
				$event_manager = $this->getEventManager();
				$event = $event_manager->getEvent($event_id);
				if ($event) {
					$success_data['redirect_url'] = VT_Config::get('site_url') . '/events/' . $event->slug;
					$success_data['message'] = 'Event conversation created successfully!';
				}
			} elseif ($community_id && !empty($_POST['community_id'])) {
				// Only redirect to community if community was explicitly selected in the form
				$community_manager = $this->getCommunityManager();
				$community = $community_manager->getCommunity($community_id);
				if ($community) {
					$success_data['redirect_url'] = VT_Config::get('site_url') . '/communities/' . $community->slug;
					$success_data['message'] = 'Community conversation created successfully!';
				}
			} else {
				// For general conversations (including those auto-assigned to personal community), redirect to the conversation
				if ($conversation) {
					$success_data['redirect_url'] = VT_Config::get('site_url') . '/conversations/' . $conversation->slug;
				}
			}

			// Store success data in transient for non-AJAX fallback
			$session_id = VT_Auth::isLoggedIn() ? VT_Auth::getCurrentUserId() : session_id();
			$transient_key = 'vt_conversation_created_' . $session_id;

			VT_Transient::set($transient_key, array(
				'id' => $conversation_id,
				'url' => $success_data['redirect_url'] ?? '',
				'message' => $success_data['message']
			), 300); // 5 minutes

			VT_Ajax::sendSuccess($success_data);
		} else {
			VT_Ajax::sendError('Failed to create conversation. Please try again.');
		}
	}

	public function ajaxAddReply() {
		VT_Security::verifyNonce('vt_nonce', 'nonce');

		$current_user = VT_Auth::getCurrentUser();
		$user_email = '';
		$user_name = '';
		$user_id = 0;

		if (VT_Auth::isLoggedIn()) {
			$user_email = $current_user->email;
			$user_name = $current_user->display_name;
			$user_id = VT_Auth::getCurrentUserId();
		} else {
			$user_email = VT_Sanitize::email($_POST['guest_email'] ?? '');
			$user_name = VT_Sanitize::textField($_POST['guest_name'] ?? '');
			if (empty($user_email) || empty($user_name)) {
				VT_Ajax::sendError('Please provide your name and email to reply.');
			}
		}

		$conversation_id = intval($_POST['conversation_id'] ?? 0);
		$parent_reply_id = intval($_POST['parent_reply_id'] ?? 0) ?: null;
		$content = VT_Security::ksesPost($_POST['content'] ?? '');

		if (empty($conversation_id) || empty($content)) {
			VT_Ajax::sendError('Please provide a message to reply.');
		}

		// Handle file attachments if present
		if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
			for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
				if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
					// Prepare individual file array for upload
					$file = array(
						'name' => $_FILES['attachments']['name'][$i],
						'type' => $_FILES['attachments']['type'][$i],
						'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
						'error' => $_FILES['attachments']['error'][$i],
						'size' => $_FILES['attachments']['size'][$i]
					);

					$uploaded_file = $this->handleFileUpload($file);

					if ($uploaded_file && !isset($uploaded_file['error'])) {
						$attachment_url = $uploaded_file['url'];

						// Add image to content if it's an image file
						if (strpos($file['type'], 'image/') === 0) {
							$content .= "\n\n<img src=\"" . VT_Sanitize::escUrl($attachment_url) . "\" alt=\"Attached image\" style=\"max-width: 100%; height: auto; border-radius: 0.375rem;\">";
						} else {
							// For non-images, add as a download link
							$filename = VT_Sanitize::fileName($file['name']);
							$content .= "\n\n<a href=\"" . VT_Sanitize::escUrl($attachment_url) . "\" target=\"_blank\">📎 " . VT_Sanitize::escHtml($filename) . "</a>";
						}
					}
				}
			}
		}

		$conversation_manager = $this->getConversationManager();

		// Handle reply join flow before posting
		if ($user_id) {
			$join_result = $this->handlereply_join_flow($conversation_id, $user_id);
			if (is_vt_error($join_result)) {
				VT_Ajax::sendError($join_result->getErrorMessage());
			}
		}

		$reply_data = array(
			'content' => $content,
			'author_id' => $user_id,
			'author_name' => $user_name,
			'author_email' => $user_email,
			'parent_reply_id' => $parent_reply_id,
		);

		$reply_id = $conversation_manager->addReply($conversation_id, $reply_data);

		if ($reply_id) {
			VT_Ajax::sendSuccess(array(
				'reply_id' => $reply_id,
				'message' => 'Reply added successfully!',
			));
		} else {
			VT_Ajax::sendError('Failed to add reply. Please try again.');
		}
	}

	public function ajaxDeleteReply() {
		VT_Security::verifyNonce('vt_nonce', 'nonce');

		if (!VT_Auth::isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in to delete replies.');
		}

		$reply_id = intval($_POST['reply_id'] ?? 0);
		if (!$reply_id) {
			VT_Ajax::sendError('Invalid reply ID.');
		}

		$conversation_manager = $this->getConversationManager();
		$result = $conversation_manager->deleteReply($reply_id);

		if ($result) {
			VT_Ajax::sendSuccess(array(
				'message' => 'Reply deleted successfully.',
			));
		} else {
			VT_Ajax::sendError('Failed to delete reply. You may not have permission to delete this reply.');
		}
	}

	public function ajaxGetConversations() {
		VT_Security::verifyNonce('vt_nonce', 'nonce');

		$circle = VT_Sanitize::textField($_POST['circle'] ?? 'inner');
		$filter = VT_Sanitize::textField($_POST['filter'] ?? '');
		$topic_slug = VT_Sanitize::slug($_POST['topic_slug'] ?? '');
		$page = max(1, intval($_POST['page'] ?? 1));
		$per_page = 20;

		// Validate circle
		$allowed_circles = array('inner', 'trusted', 'extended');
		if (!in_array($circle, $allowed_circles)) {
			$circle = 'inner';
		}

		// Validate filter
		$allowed_filters = array('', 'events', 'communities');
		if (!in_array($filter, $allowed_filters)) {
			$filter = '';
		}

		$conversation_manager = $this->getConversationManager();
		$current_user_id = VT_Auth::getCurrentUserId();

		// Handle different filter types
		if ($filter === 'events') {
			// Get event conversations
			$conversations = $conversation_manager->getEventConversations(null, $per_page);
			$db = VT_Database::getInstance();
			$total_conversations = $db->getVar("SELECT COUNT(*) FROM {$db->prefix}conversations WHERE event_id IS NOT NULL");
		} elseif ($filter === 'communities') {
			// Get community conversations
			$conversations = $conversation_manager->getCommunityConversations(null, $per_page);
			$db = VT_Database::getInstance();
			$total_conversations = $db->getVar("SELECT COUNT(*) FROM {$db->prefix}conversations WHERE community_id IS NOT NULL");
		} else {
			// Use conversation feed with circles integration if available
			if (class_exists('VT_Conversation_Feed')) {
				$opts = array(
					'topic_slug' => $topic_slug,
					'page' => $page,
					'per_page' => $per_page
				);
				$feed_result = VT_Conversation_Feed::list($current_user_id, $circle, $opts);
				$conversations = $feed_result['conversations'];
				$total_conversations = $feed_result['meta']['total'];
			} else {
				$conversations = $conversation_manager->getRecentConversations($per_page);
				$total_conversations = count($conversations);
			}
		}

		$total_pages = ceil($total_conversations / $per_page);
		$has_more = $page < $total_pages;

		// Generate HTML for conversations list
		ob_start();
		?>
		<div class="conversations-list">
			<?php if (empty($conversations)): ?>
				<div class="vt-text-center vt-text-muted">
					<p>No conversations found.</p>
				</div>
			<?php else: ?>
				<?php foreach ($conversations as $conversation): ?>
					<div class="conversation-item vt-card vt-mb-4">
						<div class="vt-card-header vt-flex vt-flex-between">
							<h3 class="vt-heading vt-heading-sm">
								<a href="<?php echo VT_Sanitize::escUrl(VT_Config::get('site_url') . '/conversations/' . $conversation->slug); ?>">
									<?php echo VT_Sanitize::escHtml($conversation->title); ?>
								</a>
							</h3>
							<div class="vt-text-muted vt-text-sm">
								<?php echo date('M j, Y', strtotime($conversation->created_at)); ?>
							</div>
						</div>
						<div class="vt-card-body">
							<p><?php echo VT_Text::truncateWords(strip_tags($conversation->content), 30); ?></p>
							<div class="vt-flex vt-gap-2 vt-text-sm vt-text-muted">
								<span><?php echo VT_Sanitize::escHtml($conversation->author_name); ?></span>
								<span>•</span>
								<span><?php echo intval($conversation->reply_count ?? 0); ?> replies</span>
								<?php if ($conversation->event_title): ?>
									<span>•</span>
									<span>Event: <?php echo VT_Sanitize::escHtml($conversation->event_title); ?></span>
								<?php elseif ($conversation->community_name): ?>
									<span>•</span>
									<span>Community: <?php echo VT_Sanitize::escHtml($conversation->community_name); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
		$html = ob_get_clean();

		VT_Ajax::sendSuccess(array(
			'html' => $html,
			'meta' => array(
				'count' => $total_conversations,
				'page' => $page,
				'has_more' => $has_more,
				'circle' => $circle,
				'filter' => $filter
			)
		));
	}

	public function ajaxUpdateConversation() {
		VT_Security::verifyNonce('vt_nonce', 'nonce');

		if (!VT_Auth::isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in to edit conversations.');
		}

		$conversation_id = intval($_POST['conversation_id'] ?? 0);
		$title = VT_Sanitize::textField($_POST['title'] ?? '');
		$content = VT_Security::ksesPost($_POST['content'] ?? '');
		$privacy = VT_Sanitize::textField($_POST['privacy'] ?? 'public');

		if (!$conversation_id || !$title || !$content) {
			VT_Ajax::sendError('All fields are required.');
		}

		$conversation_manager = $this->getConversationManager();
		$conversation = $conversation_manager->getConversationById($conversation_id);

		if (!$conversation) {
			VT_Ajax::sendError('Conversation not found.');
		}

		// Check permissions
		$current_user_id = VT_Auth::getCurrentUserId();
		$can_edit = ($current_user_id == $conversation->author_id) || VT_Auth::currentUserCan('manage_options');

		if (!$can_edit) {
			VT_Ajax::sendError('You do not have permission to edit this conversation.');
		}

		// Handle cover image upload
		if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
			$upload_result = $this->handleCoverImageUpload($_FILES['cover_image'], $conversation_id);
			if (is_vt_error($upload_result)) {
				VT_Ajax::sendError($upload_result->getErrorMessage());
			}
		}

		// Handle cover image removal
		if (isset($_POST['remove_cover_image']) && $_POST['remove_cover_image'] === '1') {
			$conversation_manager->updateConversation($conversation_id, array('featured_image' => ''));
		}

		$update_data = array(
			'title' => $title,
			'content' => $content,
		);

		// Only update privacy for standalone conversations
		if (!$conversation->event_id && !$conversation->community_id) {
			$update_data['privacy'] = $privacy;
		}

		$result = $conversation_manager->updateConversation($conversation_id, $update_data);

		if (is_vt_error($result)) {
			VT_Ajax::sendError($result->getErrorMessage());
		}

		// Store success message
		VT_Transient::set('vt_conversation_updated_' . VT_Auth::getCurrentUserId(), array(
			'conversation_id' => $conversation_id,
			'message' => 'Conversation updated successfully!'
		), 300);

		// Get updated conversation data to return new slug
		$updated_conversation = $conversation_manager->getConversationById($conversation_id);

		VT_Ajax::sendSuccess(array(
			'message' => 'Conversation updated successfully!',
			'conversation_id' => $conversation_id,
			'slug' => $updated_conversation->slug
		));
	}

	public function ajaxDeleteConversation() {
		VT_Security::verifyNonce('vt_nonce', 'nonce');

		if (!VT_Auth::isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in to delete conversations.');
		}

		$conversation_id = intval($_POST['conversation_id'] ?? 0);

		if (!$conversation_id) {
			VT_Ajax::sendError('Conversation ID is required.');
		}

		$conversation_manager = $this->getConversationManager();
		$conversation = $conversation_manager->getConversationById($conversation_id);

		if (!$conversation) {
			VT_Ajax::sendError('Conversation not found.');
		}

		// Check permissions
		$current_user_id = VT_Auth::getCurrentUserId();
		$can_delete = ($current_user_id == $conversation->author_id) || VT_Auth::currentUserCan('manage_options');

		if (!$can_delete) {
			VT_Ajax::sendError('You do not have permission to delete this conversation.');
		}

		$result = $conversation_manager->deleteConversation($conversation_id);

		if (is_vt_error($result)) {
			VT_Ajax::sendError($result->getErrorMessage());
		}

		VT_Ajax::sendSuccess(array(
			'message' => 'Conversation deleted successfully!'
		));
	}

	public function ajaxUpdateReply() {
		VT_Security::verifyNonce('vt_nonce', 'nonce');

		if (!VT_Auth::isLoggedIn()) {
			VT_Ajax::sendError('You must be logged in to edit replies.');
		}

		$reply_id = intval($_POST['reply_id'] ?? 0);
		$content = VT_Security::sanitizeTextarea($_POST['content'] ?? '');

		if (!$reply_id || empty($content)) {
			VT_Ajax::sendError('Reply ID and content are required.');
		}

		$conversation_manager = $this->getConversationManager();

		// Get reply to check ownership
		$reply = $conversation_manager->getReply($reply_id);
		if (!$reply) {
			VT_Ajax::sendError('Reply not found.');
		}

		$current_user_id = VT_Auth::getCurrentUserId();

		// Check if user owns this reply
		if ($reply->author_id != $current_user_id) {
			VT_Ajax::sendError('You can only edit your own replies.');
		}

		// Get preserved images from original content (excluding removed ones)
		$original_content = $reply->content;
		$preserved_images = '';

		// Extract images from original content
		preg_match_all('/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $original_content, $image_matches);
		if (!empty($image_matches[0])) {
			$removed_images = array();
			if (isset($_POST['removed_images'])) {
				$removed_images = json_decode(stripslashes($_POST['removed_images']), true);
				if (!is_array($removed_images)) {
					$removed_images = array();
				}
			}

			foreach ($image_matches[0] as $i => $img_tag) {
				$img_src = $image_matches[1][$i];
				if (!in_array($img_src, $removed_images)) {
					$preserved_images .= "\n\n" . $img_tag;
				}
			}
		}

		// Start with new text content and add preserved images
		$content = $content . $preserved_images;

		// Handle file attachments if present
		if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
			for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
				if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
					// Prepare individual file array for upload
					$file = array(
						'name' => $_FILES['attachments']['name'][$i],
						'type' => $_FILES['attachments']['type'][$i],
						'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
						'error' => $_FILES['attachments']['error'][$i],
						'size' => $_FILES['attachments']['size'][$i]
					);

					$uploaded_file = $this->handleFileUpload($file);

					if ($uploaded_file && !isset($uploaded_file['error'])) {
						$attachment_url = $uploaded_file['url'];

						// Add image to content if it's an image file
						if (strpos($file['type'], 'image/') === 0) {
							$content .= "\n\n<img src=\"" . VT_Sanitize::escUrl($attachment_url) . "\" alt=\"Attached image\" style=\"max-width: 100%; height: auto; border-radius: 0.375rem;\">";
						} else {
							// For non-images, add as a download link
							$filename = VT_Sanitize::fileName($file['name']);
							$content .= "\n\n<a href=\"" . VT_Sanitize::escUrl($attachment_url) . "\" target=\"_blank\">📎 " . VT_Sanitize::escHtml($filename) . "</a>";
						}
					}
				}
			}
		}

		// Update the reply
		$result = $conversation_manager->updatereply($reply_id, array('content' => $content));

		if (is_vt_error($result)) {
			VT_Ajax::sendError($result->getErrorMessage());
		}

		VT_Ajax::sendSuccess(array(
			'message' => 'Reply updated successfully.',
			'content' => $content
		));
	}

	private function handleCoverImageUpload($file, $conversation_id) {
		// Similar to event/community image upload handling
		if (class_exists('VT_Upload')) {
			$validation_result = VT_Upload::validateFile($file);
			if (is_vt_error($validation_result)) {
				return $validation_result;
			}

			$uploaded_file = VT_Upload::handleUpload($file);

			if ($uploaded_file && !isset($uploaded_file['error'])) {
				// Update conversation with cover image
				$conversation_manager = $this->getConversationManager();
				$conversation_manager->updateConversation($conversation_id, array('featured_image' => $uploaded_file['url']));
				return $uploaded_file['url'];
			} else {
				return new VT_Error('upload_error', 'File upload failed.');
			}
		} else {
			// Basic file handling
			$upload_dir = VT_Config::get('upload_dir', '/uploads/');
			$filename = basename($file['name']);
			$target_path = $_SERVER['DOCUMENT_ROOT'] . $upload_dir . $filename;

			if (move_uploaded_file($file['tmp_name'], $target_path)) {
				$file_url = VT_Config::get('site_url') . $upload_dir . $filename;
				$conversation_manager = $this->getConversationManager();
				$conversation_manager->updateConversation($conversation_id, array('featured_image' => $file_url));
				return $file_url;
			} else {
				return new VT_Error('upload_error', 'File upload failed.');
			}
		}
	}

	private function handleFileUpload($file) {
		// Basic file upload handling
		if (class_exists('VT_Upload')) {
			return VT_Upload::handleUpload($file);
		} else {
			$upload_dir = VT_Config::get('upload_dir', '/uploads/');
			$filename = basename($file['name']);
			$target_path = $_SERVER['DOCUMENT_ROOT'] . $upload_dir . $filename;

			if (move_uploaded_file($file['tmp_name'], $target_path)) {
				return array('url' => VT_Config::get('site_url') . $upload_dir . $filename);
			} else {
				return array('error' => 'Upload failed');
			}
		}
	}

	/**
	 * Handle reply join flow logic
	 * Auto-join, pending approval, or access request based on community settings
	 */
	private function handleReplyJoinFlow($conversation_id, $user_id) {
		// Spam protection - rate limit join attempts
		$join_attempts_key = 'vt_join_attempts_' . $user_id;
		$recent_attempts = VT_Transient::get($join_attempts_key);

		if ($recent_attempts && $recent_attempts >= 5) {
			return new VT_Error('rate_limited', 'Too many join attempts. Please wait before trying again.');
		}

		// Get the conversation to find its community
		$conversation_manager = $this->getConversationManager();
		$conversation = $conversation_manager->getConversation($conversation_id);

		if (!$conversation || !$conversation->community_id) {
			// No community - allow reply (general conversation)
			return true;
		}

		// Check if user is already a member
		$community_manager = $this->getCommunityManager();
		$member_role = $community_manager->getMemberRole($conversation->community_id, $user_id);

		if ($member_role && $member_role !== 'blocked') {
			// User is already a member - allow reply
			return true;
		}

		// Get community details
		$community = $community_manager->getCommunity($conversation->community_id);
		if (!$community) {
			return new VT_Error('community_not_found', 'Community not found');
		}

		// Handle based on community visibility and settings
		switch ($community->visibility) {
			case 'public':
				// Public community - auto-join if allowed
				if ($community_manager->allowsAutoJoinOnReply($conversation->community_id)) {
					// Track join attempt for spam protection
					$this->track_join_attempt($user_id);

					$join_result = $community_manager->joinCommunity($conversation->community_id, $user_id);
					if (is_vt_error($join_result)) {
						return new VT_Error('auto_join_failed', 'Failed to join community');
					}
					return true;
				} else {
					return new VT_Error('membership_required', 'You must be a member to reply in this community');
				}
				break;

			case 'private':
				// Private community - provide contact info for access request
				$creator_user = VT_Database::getInstance()->getRow(
					"SELECT * FROM {VT_Database::getInstance()->prefix}users WHERE id = {$community->creator_id}"
				);
				$contact_info = $creator_user ? $creator_user->display_name : 'the community administrator';

				$message = sprintf(
					'This is a private community. To request access, contact %s or email %s',
					$contact_info,
					VT_Config::get('admin_email')
				);

				return new VT_Error('access_restricted', $message);
				break;
		}

		return new VT_Error('unknown_visibility', 'Unknown community visibility setting');
	}

	/**
	 * Track join attempts for spam protection
	 */
	private function trackJoinAttempt($user_id) {
		$join_attempts_key = 'vt_join_attempts_' . $user_id;
		$recent_attempts = VT_Transient::get($join_attempts_key);
		$attempts = $recent_attempts ? (int) $recent_attempts + 1 : 1;

		// Track attempts for 1 hour
		VT_Transient::set($join_attempts_key, $attempts, 3600);
	}
}