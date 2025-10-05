<?php
/**
 * VivalaTable Single Conversation Content Template
 * Individual conversation page with replies and threading
 * Ported from PartyMinder WordPress plugin
 */

// Get conversation slug from page params or fallback to query string
$conversation_slug = $conversation_slug ?? $_GET['conversation'] ?? '';
if (!$conversation_slug) {
	VT_Router::redirect('/conversations');
	exit;
}

// Load managers
$conversation_manager = new VT_Conversation_Manager();
$community_manager = new VT_Community_Manager();

// Get conversation
$conversation = $conversation_manager->getConversationBySlug($conversation_slug);
if (!$conversation) {
	http_response_code(404);
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Conversation Not Found</h3>
		<p class="vt-text-muted vt-mb-4">The conversation you're looking for could not be found.</p>
		<a href="/conversations" class="vt-btn">Browse Conversations</a>
	</div>
	<?php
	return;
}

// Check user permissions
$current_user = vt_service('auth.service')->getCurrentUser();
$is_logged_in = vt_service('auth.service')->isLoggedIn();
$can_view = true;
$can_reply = $is_logged_in;

// Check privacy permissions
if ($conversation->privacy === 'members' || $conversation->privacy === 'private') {
	if (!$is_logged_in) {
		$can_view = false;
		$can_reply = false;
	} elseif ($conversation->community_id) {
		$is_member = $community_manager->isMember($conversation->community_id, $current_user->id);
		if (!$is_member) {
			$can_view = false;
			$can_reply = false;
		}
	}
}

if (!$can_view) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Access Restricted</h3>
		<p class="vt-text-muted vt-mb-4">This conversation is private. You need permission to view it.</p>
		<?php if (!$is_logged_in) : ?>
			<a href="/login" class="vt-btn vt-btn-primary">Login</a>
		<?php endif; ?>
		<a href="/conversations" class="vt-btn vt-btn-secondary">Browse Public Conversations</a>
	</div>
	<?php
	return;
}

// Handle reply submission
$errors = array();
$messages = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_reply && vt_service('security.service')->verifyNonce($_POST['reply_nonce'], 'vt_conversation_reply')) {
	$reply_content = vt_service('validation.sanitizer')->richText($_POST['reply_content'] ?? '');
	$parent_reply_id = intval($_POST['parent_reply_id'] ?? 0);

	if (empty($reply_content)) {
		$errors[] = 'Reply content is required.';
	} else {
		$reply_data = array(
			'conversation_id' => $conversation->id,
			'parent_reply_id' => $parent_reply_id,
			'content' => $reply_content,
			'author_id' => $current_user->id,
			'author_name' => $current_user->display_name ?: $current_user->username,
			'author_email' => $current_user->email
		);

		$reply_id = $conversation_manager->addReply($conversation->id, $reply_data);
		if ($reply_id) {
			$messages[] = 'Reply posted successfully!';
			// Refresh the page to show the new reply
			VT_Router::redirect('/conversations/' . $conversation->slug . '#reply-' . $reply_id);
			exit;
		} else {
			$errors[] = 'Failed to post reply. Please try again.';
		}
	}
}

// Get conversation replies
$replies = $conversation_manager->getConversationReplies($conversation->id);

// Get community context if applicable
$community = null;
if ($conversation->community_id) {
	$community = $community_manager->getCommunity($conversation->community_id);
}

// Set up template variables
$page_title = htmlspecialchars($conversation->title);
$page_description = htmlspecialchars(VT_Text::truncate(strip_tags($conversation->content), 150));

// Get active tab (conversation view doesn't really have tabs, but we use this pattern for consistency)
$active_tab = $_GET['tab'] ?? 'conversation';
?>

<!-- Conversation Secondary Navigation -->
<div class="vt-section vt-mb-4">
	<?php
	// Build tabs array for secondary navigation
	$tabs = [
		[
			'label' => 'View Conversation',
			'url' => '/conversations/' . $conversation->slug,
			'active' => true
		]
	];

	// Add Edit tab if user can edit
	if ($is_logged_in && $conversation_manager->canEditConversation($conversation->id)) {
		$tabs[] = [
			'label' => 'Edit',
			'url' => '/conversations/' . $conversation->slug . '/edit',
			'active' => false
		];
	}

	include VT_INCLUDES_DIR . '/../templates/partials/secondary-nav.php';
	?>
</div>

<!-- Conversation Header -->
<div class="vt-section vt-mb-4">
	<div class="vt-conversation-header">
		<?php if ($community) : ?>
			<div class="vt-conversation-context vt-mb-2">
				<a href="/communities/<?php echo htmlspecialchars($community->slug); ?>" class="vt-text-primary">
					<?php echo htmlspecialchars($community->name); ?>
				</a>
			</div>
		<?php endif; ?>

		<div class="vt-flex vt-flex-between vt-flex-wrap vt-gap vt-mb-2">
			<h1 class="vt-heading vt-heading-xl vt-text-primary">
				<?php echo htmlspecialchars($conversation->title); ?>
			</h1>
			<div class="vt-flex vt-gap" style="align-items: flex-start;">
				<?php if ($conversation->privacy === 'private' || $conversation->privacy === 'members') : ?>
					<span class="vt-badge vt-badge-warning">Members Only</span>
				<?php else : ?>
					<span class="vt-badge vt-badge-success">Public</span>
				<?php endif; ?>
				<?php if ($conversation->is_pinned) : ?>
					<span class="vt-badge vt-badge-primary">Pinned</span>
				<?php endif; ?>
			</div>
		</div>

		<div class="vt-conversation-meta vt-mb-4">
			<div class="vt-flex vt-gap vt-text-muted">
				<span>by <strong><?php echo htmlspecialchars($conversation->author_name); ?></strong></span>
				<span>•</span>
				<span><?php echo date('F j, Y \a\t g:i A', strtotime($conversation->created_at)); ?></span>
				<span>•</span>
				<span><?php echo $conversation->reply_count; ?> replies</span>
			</div>
		</div>
	</div>
</div>

<!-- Original Conversation Content -->
<div class="vt-section vt-mb-6">
	<div class="vt-conversation-content">
		<div class="vt-content">
			<?php echo $conversation_manager->processContentEmbeds($conversation->content); ?>
		</div>
	</div>
</div>

<!-- Error/Success Messages -->
<?php if (!empty($errors)) : ?>
	<div class="vt-alert vt-alert-error vt-mb-4">
		<?php foreach ($errors as $error) : ?>
			<p><?php echo htmlspecialchars($error); ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if (!empty($messages)) : ?>
	<div class="vt-alert vt-alert-success vt-mb-4">
		<?php foreach ($messages as $message) : ?>
			<p><?php echo htmlspecialchars($message); ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<!-- Reply Form -->
<?php if ($can_reply) : ?>
	<div class="vt-section vt-mb-6">
		<h3 class="vt-heading vt-heading-md vt-mb-4">Join the Conversation</h3>
		<form method="post" class="vt-form">
			<?php echo vt_service('security.service')->nonceField('vt_conversation_reply', 'reply_nonce'); ?>
			<input type="hidden" name="parent_reply_id" value="0">

			<div class="vt-form-group">
				<label for="reply_content" class="vt-form-label">Your Reply</label>
				<textarea id="reply_content" name="reply_content" class="vt-form-input vt-form-textarea"
						  rows="4" placeholder="Share your thoughts..." required></textarea>
			</div>

			<div class="vt-form-actions">
				<button type="submit" class="vt-btn vt-btn-primary">
					Post Reply
				</button>
			</div>
		</form>
	</div>
<?php elseif (!$is_logged_in) : ?>
	<div class="vt-section vt-mb-6 vt-text-center">
		<p class="vt-text-muted vt-mb-4">Want to join the conversation?</p>
		<a href="/login" class="vt-btn vt-btn-primary">Login to Reply</a>
	</div>
<?php endif; ?>

<!-- Conversation Replies -->
<div class="vt-section">
	<h3 class="vt-heading vt-heading-md vt-mb-4">
		Replies (<?php echo count($replies); ?>)
	</h3>

	<?php if (!empty($replies)) : ?>
		<div class="vt-replies">
			<?php foreach ($replies as $reply) : ?>
				<div class="vt-reply" id="reply-<?php echo $reply->id; ?>">
					<div class="vt-reply-header">
						<div class="vt-reply-author">
							<?php
							$user_id = $reply->author_id;
							$args = array(
								'avatar_size' => 40,
								'show_avatar' => true,
								'show_name' => true,
								'link_profile' => true,
								'class' => 'vt-member-display'
							);
							include __DIR__ . '/partials/member-display.php';
							?>
						</div>
						<div class="vt-reply-meta vt-text-muted">
							<?php echo date('M j, Y \a\t g:i A', strtotime($reply->created_at)); ?>
						</div>
					</div>
					<div class="vt-reply-content">
						<?php echo $conversation_manager->processContentEmbeds($reply->content); ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="vt-text-center vt-p-4">
			<p class="vt-text-muted">No replies yet. Be the first to share your thoughts!</p>
		</div>
	<?php endif; ?>
</div>

