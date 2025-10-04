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

	<div class="vt-tab-nav vt-flex vt-gap-4 vt-flex-wrap">
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
		<button class="vt-btn" data-circle="all" role="tab" aria-selected="false" aria-controls="vt-convo-list">
			All
		</button>

		<!-- Type Filters -->
		<button class="vt-btn <?php echo ($active_filter === 'my-events') ? 'is-active' : ''; ?>" data-filter="my-events" role="button" aria-controls="vt-convo-list">
			My Events
		</button>
		<button class="vt-btn <?php echo ($active_filter === 'all-events') ? 'is-active' : ''; ?>" data-filter="all-events" role="button" aria-controls="vt-convo-list">
			All Events
		</button>
	</div>
</div>
<?php endif; ?>

<div class="vt-section">
	<div id="vt-convo-list" class="vt-grid vt-grid-2 vt-gap">
		<?php if (!empty($recent_conversations)) :
			foreach ($recent_conversations as $conversation) : ?>
					<?php
					// Determine conversation type
					$conversation_type = '';
					if ($conversation->event_id) {
						$conversation_type = 'Event Discussion';
					} elseif ($conversation->community_id) {
						$conversation_type = 'Community Discussion';
					} else {
						$conversation_type = 'General Discussion';
					}

					// Set up for entity card
					$entity_type = 'conversation';
					$entity = $conversation;
					$entity->title = $conversation_manager->getDisplayTitle($conversation);

					// Badges
					$badges = [
						['label' => $conversation_type, 'class' => 'vt-badge-secondary'],
						['label' => ucfirst($conversation->privacy), 'class' => $conversation->privacy === 'private' ? 'vt-badge-secondary' : 'vt-badge-success']
					];

					// Stats
					$stats = [
						['value' => intval($conversation->reply_count), 'label' => 'Replies']
					];

					// Actions
					$actions = [
						['label' => 'View', 'url' => '/conversations/' . $conversation->slug]
					];

					// Description
					$description = $conversation->content ?? '';

					// Render entity card
					include VT_INCLUDES_DIR . '/../templates/partials/entity-card.php';
					?>
			<?php endforeach;
		else : ?>
			<div class="vt-text-center vt-p-4">
				<h3 class="vt-heading vt-heading-sm vt-mb-4">No Conversations Found</h3>
				<p class="vt-text-muted">There are no conversations to display.</p>
			</div>
		<?php endif; ?>
	</div>
</div>