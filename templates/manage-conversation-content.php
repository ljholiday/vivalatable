<?php
/**
 * VivalaTable Manage Conversation Content Template
 * Conversation management interface with Settings tab
 */

// Get conversation slug from route parameter
$conversation_slug = VT_Router::getParam('slug');
$active_tab = $_GET['tab'] ?? 'settings';

if (!$conversation_slug) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Conversation Not Found</h3>
		<p class="vt-text-muted vt-mb-4">Conversation slug is required to manage a conversation.</p>
		<a href="/conversations" class="vt-btn">Back to Conversations</a>
	</div>
	<?php
	return;
}

// Load managers and get conversation
$conversation_manager = new VT_Conversation_Manager();
$conversation = $conversation_manager->getConversationBySlug($conversation_slug);

if (!$conversation) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Conversation Not Found</h3>
		<p class="vt-text-muted vt-mb-4">The conversation you're trying to manage could not be found.</p>
		<a href="/conversations" class="vt-btn">Back to Conversations</a>
	</div>
	<?php
	return;
}

// Check if current user can manage this conversation
$current_user = vt_service('auth.service')->getCurrentUser();
if (!$current_user || $conversation->author_id != $current_user->id) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Access Denied</h3>
		<p class="vt-text-muted vt-mb-4">You don't have permission to manage this conversation.</p>
		<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-btn">View Conversation</a>
	</div>
	<?php
	return;
}

// Handle form submissions
$errors = array();
$messages = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	if ($_POST['action'] === 'update_conversation' && vt_service('security.service')->verifyNonce($_POST['nonce'], 'vt_manage_conversation')) {
		$update_data = array(
			'title' => vt_service('validation.sanitizer')->textField($_POST['title']),
			'content' => vt_service('validation.sanitizer')->textarea($_POST['content']),
			'privacy' => vt_service('validation.sanitizer')->textField($_POST['privacy']),
		);

		// Handle community change
		if (isset($_POST['community_id'])) {
			$update_data['community_id'] = intval($_POST['community_id']);
		}

		$result = $conversation_manager->updateConversation($conversation->id, $update_data);
		if ($result) {
			$messages[] = 'Conversation updated successfully.';
			// Refresh conversation data
			$conversation = $conversation_manager->getConversationBySlug($conversation_slug);
		} else {
			$errors[] = 'Failed to update conversation.';
		}
	}

	// Handle conversation deletion
	if ($_POST['action'] === 'delete_conversation' && vt_service('security.service')->verifyNonce($_POST['delete_nonce'], 'vt_delete_conversation')) {
		$result = $conversation_manager->deleteConversation($conversation->id);
		if ($result) {
			// Redirect to conversations page after successful deletion
			VT_Router::redirect('/conversations?deleted=1');
			exit;
		} else {
			$errors[] = 'Failed to delete conversation.';
		}
	}
}

// Get user's communities for dropdown
$community_manager = new VT_Community_Manager();
$user_communities = $community_manager->getUserCommunities($current_user->id);

// Set up template variables
$page_title = 'Manage: ' . htmlspecialchars($conversation->title);
$page_description = 'Manage your conversation settings';
?>

<!-- Success/Error Messages -->
<?php if (!empty($messages)) : ?>
	<div class="vt-alert vt-alert-success vt-mb-4">
		<?php foreach ($messages as $message) : ?>
			<p><?php echo htmlspecialchars($message); ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if (!empty($errors)) : ?>
	<div class="vt-alert vt-alert-error vt-mb-4">
		<?php foreach ($errors as $error) : ?>
			<p><?php echo htmlspecialchars($error); ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<!-- Management Tabs -->
<div class="vt-section vt-mb-4">
	<div class="vt-tab-nav vt-flex vt-gap-4 vt-flex-wrap">
		<a href="/conversations/<?php echo $conversation->slug; ?>/manage?tab=settings"
		   class="vt-btn <?php echo ($active_tab === 'settings') ? 'is-active' : ''; ?>">
			Settings
		</a>
		<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-btn">
			View Conversation
		</a>
	</div>
</div>

<!-- Conversation Header -->
<div class="vt-section vt-mb-4">
	<h2 class="vt-heading vt-heading-lg vt-text-primary vt-mb-2">
		<?php echo htmlspecialchars($conversation->title); ?>
	</h2>
</div>

<!-- Tab Content -->
<?php if ($active_tab === 'settings') : ?>
	<!-- Settings Tab -->
	<div class="vt-section">
		<h3 class="vt-heading vt-heading-md vt-mb-4">Conversation Settings</h3>

		<form method="post" class="vt-form">
			<input type="hidden" name="action" value="update_conversation">
			<input type="hidden" name="nonce" value="<?php echo vt_service('security.service')->createNonce('vt_manage_conversation'); ?>">

			<div class="vt-form-group">
				<label for="title" class="vt-form-label">Conversation Title</label>
				<input type="text" id="title" name="title" class="vt-form-input"
					   value="<?php echo htmlspecialchars($conversation->title); ?>"
					   placeholder="What would you like to discuss?" required>
			</div>

			<div class="vt-form-group">
				<label for="content" class="vt-form-label">Content</label>
				<textarea id="content" name="content" class="vt-form-input vt-form-textarea"
						  rows="8" placeholder="Share your thoughts, ask a question, or start a discussion..."
						  required><?php echo htmlspecialchars($conversation->content); ?></textarea>
			</div>

			<div class="vt-form-group">
				<label for="privacy" class="vt-form-label">Privacy Setting</label>
				<select id="privacy" name="privacy" class="vt-form-input">
					<option value="public" <?php echo ($conversation->privacy === 'public') ? 'selected' : ''; ?>>
						Public
					</option>
					<option value="private" <?php echo ($conversation->privacy === 'private') ? 'selected' : ''; ?>>
						Private
					</option>
				</select>
			</div>

			<div class="vt-form-group">
				<label for="community_id" class="vt-form-label">Community</label>
				<select id="community_id" name="community_id" class="vt-form-input" required>
					<option value="">Select a community...</option>
					<?php foreach ($user_communities as $user_community) : ?>
						<option value="<?php echo $user_community->id; ?>"
								<?php echo ($user_community->id == $conversation->community_id) ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($user_community->name); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<small class="vt-form-help">
					All conversations must be in a community.
				</small>
			</div>

			<button type="submit" class="vt-btn vt-btn-primary">
				Save Changes
			</button>
		</form>

		<!-- Danger Zone -->
		<?php
		$entity_type = 'conversation';
		$entity_id = $conversation->id;
		$entity_name = $conversation->title;
		$can_delete = $conversation_manager->canDeleteConversation($conversation->id, $current_user->id);
		$confirmation_type = 'confirm';
		$delete_message = 'Once you delete this conversation, there is no going back. This action cannot be undone.';
		$nonce_action = 'vt_delete_conversation';

		include VT_INCLUDES_DIR . '/../templates/partials/danger-zone.php';
		?>
	</div>
<?php endif; ?>
