<?php
/**
 * VivalaTable Conversations Content Template
 * Main conversations listing page - simplified without topics
 * Ported from PartyMinder WordPress plugin
 */

// Get current user info
$current_user = VT_Auth::getCurrentUser();
$user_logged_in = VT_Auth::isLoggedIn();

// Check if we're viewing a single conversation
$conversation_slug = $_GET['conversation_slug'] ?? '';

if ($conversation_slug) {
	// Single conversation view would be handled by a separate routing system
	// For now, redirect to conversation detail page
	header('Location: /conversations/' . urlencode($conversation_slug));
	exit;
}

// Create conversation manager instance
$conversation_manager = new VT_Conversation_Manager();

// Let JavaScript handle conversation loading via circle filtering
$recent_conversations = array();

// Get user's conversations for sidebar
$user_conversations = array();
if ($user_logged_in) {
	$user_conversations = $conversation_manager->get_user_conversations($current_user->id, 6);
}

// Check for filter parameter from URL
$active_filter = $_GET['filter'] ?? '';
$valid_filters = array('events', 'communities');
if (!in_array($active_filter, $valid_filters)) {
	$active_filter = '';
}

// Set up template variables
$page_title = 'Conversations';
$page_description = 'Connect, share tips, and plan amazing gatherings with fellow hosts and guests';
$breadcrumbs = array();
?>

<!-- Conversation Filters -->
<?php if ($user_logged_in) : ?>
<div class="vt-section vt-mb-4">
	<div class="vt-conversations-nav vt-flex vt-gap-4 vt-flex-wrap">
		<!-- Circle Filters -->
		<button class="vt-btn is-active" data-circle="inner" role="tab" aria-selected="true" aria-controls="vt-convo-list">
			Inner
		</button>
		<button class="vt-btn" data-circle="trusted" role="tab" aria-selected="false" aria-controls="vt-convo-list">
			Trusted
		</button>
		<button class="vt-btn" data-circle="extended" role="tab" aria-selected="false" aria-controls="vt-convo-list">
			Extended
		</button>

		<!-- Type Filters -->
		<button class="vt-btn" data-filter="events" role="tab" aria-selected="false" aria-controls="vt-convo-list">
			Event
		</button>
		<button class="vt-btn" data-filter="communities" role="tab" aria-selected="false" aria-controls="vt-convo-list">
			Community
		</button>
	</div>
</div>
<?php endif; ?>

<div class="vt-section">
	<div id="vt-convo-list" class="vt-grid vt-grid-2 vt-gap">
		<?php if ($user_logged_in) : ?>
			<div class="vt-text-center vt-p-4">
				<p class="vt-text-muted">Loading conversations...</p>
			</div>
		<?php else : ?>
			<?php
			// For non-logged in users, show recent public conversations
			$public_conversations = $conversation_manager->get_recent_conversations(20);
			if (!empty($public_conversations)) :
				foreach ($public_conversations as $conversation) : ?>
					<div class="vt-section">
						<div class="vt-flex vt-flex-between vt-mb-4">
							<h3 class="vt-heading vt-heading-sm">
								<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-text-primary"><?php echo htmlspecialchars($conversation_manager->get_display_title($conversation)); ?></a>
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
							</div>
						</div>

						<?php if ($conversation->content) : ?>
						<div class="vt-mb-4">
							<p class="vt-text-muted"><?php echo htmlspecialchars(VT_Text::truncate_words($conversation->excerpt ?: $conversation->content, 15)); ?></p>
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
		<?php endif; ?>
	</div>
</div>