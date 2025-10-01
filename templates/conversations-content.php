<?php
/**
 * VivalaTable Conversations Content Template
 * Main conversations listing page - simplified without topics
 * Ported from PartyMinder WordPress plugin
 */

// Get current user info
$current_user = vt_service('auth.service')->getCurrentUser();
$user_logged_in = vt_service('auth.service')->isLoggedIn();

// Check if we're viewing a single conversation
$conversation_slug = $_GET['conversation_slug'] ?? '';

if ($conversation_slug) {
	// Single conversation view would be handled by a separate routing system
	// For now, redirect to conversation detail page
	header('Location: /conversations/' . urlencode($conversation_slug));
	exit;
}

// Check for filter parameter from URL
$active_filter = $_GET['filter'] ?? '';
$valid_filters = array('events', 'communities');
if (!in_array($active_filter, $valid_filters)) {
	$active_filter = '';
}

// Create conversation manager instance
$conversation_manager = new VT_Conversation_Manager();

// Load conversations based on filter using VT_Conversation_Feed
$recent_conversations = array();
if ($user_logged_in && class_exists('VT_Conversation_Feed')) {
	// Use the new circles-aware conversation feed
	$feed_options = array(
		'page' => 1,
		'per_page' => 20,
		'filter' => $active_filter
	);
	$feed_result = VT_Conversation_Feed::list($current_user->id, 'inner', $feed_options);
	$recent_conversations = $feed_result['conversations'];
} else {
	// Fallback for non-logged users or when VT_Conversation_Feed is not available
	if ($active_filter === 'events') {
		$recent_conversations = $conversation_manager->getEventConversations(null, 20);
	} elseif ($active_filter === 'communities') {
		$recent_conversations = $conversation_manager->getCommunityConversations(null, 20);
	} else {
		$recent_conversations = $conversation_manager->getRecentConversations(20);
	}
}

// Get user's conversations for sidebar
$user_conversations = array();
if ($user_logged_in) {
	$user_conversations = $conversation_manager->getUserConversations($current_user->id, 6);
}

// Set up template variables
$page_title = 'Conversations';
$page_description = 'Connect, share tips, and plan amazing gatherings with fellow hosts and guests';
$breadcrumbs = array();
?>

<!-- Conversation Filters -->
<?php if ($user_logged_in) : ?>
<div class="vt-section vt-mb-4">
	<!-- Circle Status Indicator -->
	<div class="vt-mb-4" id="vt-circle-status">
		<strong class="vt-text-primary">Inner Circle</strong>
		<span class="vt-text-muted">(<?php echo count($recent_conversations); ?> conversation<?php echo count($recent_conversations) !== 1 ? 's' : ''; ?>)</span>
	</div>

	<!-- Educational Message -->
	<div class="vt-mb-4 vt-p-4" style="background: #f0f9ff; border-left: 4px solid var(--vt-primary); border-radius: 4px;">
		<p class="vt-text-sm" style="margin: 0;">
			<strong>Circles of Trust:</strong> Control what you see based on relationships.
			<strong>Inner</strong> = your communities,
			<strong>Trusted</strong> = friend-of-friend,
			<strong>Extended</strong> = broader network.
		</p>
	</div>

	<div class="vt-conversations-nav vt-flex vt-gap-4 vt-flex-wrap">
		<!-- Circle Filters (Circles of Trust) -->
		<button class="vt-btn is-active" data-circle="inner" role="tab" aria-selected="true" aria-controls="vt-convo-list" style="font-weight: 600;">
			Inner
		</button>
		<button class="vt-btn" data-circle="trusted" role="tab" aria-selected="false" aria-controls="vt-convo-list">
			Trusted
		</button>
		<button class="vt-btn" data-circle="extended" role="tab" aria-selected="false" aria-controls="vt-convo-list">
			Extended
		</button>

		<!-- Type Filters -->
		<a href="/conversations" class="vt-btn <?php echo ($active_filter === '') ? 'is-active' : ''; ?>">
			All
		</a>
		<a href="/conversations?filter=events" class="vt-btn <?php echo ($active_filter === 'events') ? 'is-active' : ''; ?>">
			Events
		</a>
		<a href="/conversations?filter=communities" class="vt-btn <?php echo ($active_filter === 'communities') ? 'is-active' : ''; ?>">
			Communities
		</a>
	</div>
</div>
<?php endif; ?>

<div class="vt-section">
	<div id="vt-convo-list" class="vt-grid vt-grid-2 vt-gap">
		<?php if (!empty($recent_conversations)) :
			foreach ($recent_conversations as $conversation) : ?>
					<div class="vt-section">
						<div class="vt-flex vt-flex-between vt-mb-4">
							<h3 class="vt-heading vt-heading-sm">
								<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-text-primary"><?php echo htmlspecialchars($conversation_manager->getDisplayTitle($conversation)); ?></a>
							</h3>
						</div>

						<div class="vt-mb-4">
							<div class="vt-flex vt-gap vt-mb-4">
								<span class="vt-text-muted">
									<?php
									if ($conversation->event_id) {
										echo 'Event Discussion';
									} elseif ($conversation->community_id) {
										echo 'Community Discussion';
									} else {
										echo 'General Discussion';
									}
									?>
								</span>
								<span class="vt-badge vt-badge-<?php echo $conversation->privacy === 'private' ? 'secondary' : 'success'; ?>">
									<?php echo vt_service('validation.validator')->escHtml(ucfirst($conversation->privacy)); ?>
								</span>
							</div>
						</div>

						<?php if ($conversation->content) : ?>
						<div class="vt-mb-4">
							<p class="vt-text-muted"><?php echo htmlspecialchars(VT_Text::truncateWords($conversation->content, 15)); ?></p>
						</div>
						<?php endif; ?>

						<div class="vt-flex vt-flex-between">
							<div class="vt-stat">
								<div class="vt-stat-number vt-text-primary"><?php echo intval($conversation->reply_count); ?></div>
								<div class="vt-stat-label">
									Replies
								</div>
							</div>

							<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-btn">
								View Details
							</a>
						</div>
					</div>
			<?php endforeach;
		else : ?>
			<div class="vt-text-center vt-p-4">
				<h3 class="vt-heading vt-heading-sm vt-mb-4">No Conversations Found</h3>
				<p class="vt-text-muted">There are no conversations to display.</p>
			</div>
		<?php endif; ?>
	</div>
</div>