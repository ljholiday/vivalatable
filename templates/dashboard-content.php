<?php
/**
 * VivalaTable Dashboard Content Template
 * Your VivalaTable home with conversations and navigation
 * Ported from PartyMinder WordPress plugin
 */

// Get current user info
$current_user = VT_Auth::getCurrentUser();
$user_logged_in = VT_Auth::isLoggedIn();

// Get user profile data if logged in
$profile_data = null;
if ($user_logged_in) {
	$profile_data = VT_Profile_Manager::get_user_profile($current_user->id);
}

// Get user's recent activity
$recent_events = array();
if ($user_logged_in) {
	$db = VT_Database::getInstance();
	$events_table = $db->prefix . 'events';

	// Get user's 3 most recent events (created or RSVP'd)
	$recent_events = $db->get_results(
		$db->prepare(
			"SELECT DISTINCT e.*, 'created' as relationship_type FROM $events_table e
         WHERE e.author_id = %d AND e.event_status = 'active'
         UNION
         SELECT DISTINCT e.*, 'rsvpd' as relationship_type FROM $events_table e
         INNER JOIN {$db->prefix}guests g ON e.id = g.event_id
         WHERE g.email = %s AND e.event_status = 'active'
         ORDER BY event_date DESC
         LIMIT 3",
			$current_user->id,
			$current_user->email
		)
	);
}

// Get recent conversations from user's close circle for dashboard
$recent_conversations = array();
if ($user_logged_in) {
	$conversation_manager = new VT_Conversation_Manager();
	$scope = VT_Circle_Scope::resolve_conversation_scope($current_user->id, 'close');
	$recent_conversations = $conversation_manager->get_conversations_by_scope($scope, '', 1, 3);
}

// Get recent event conversations for dashboard, grouped by event
$conversation_manager = new VT_Conversation_Manager();
$recent_event_conversations = $conversation_manager->get_event_conversations(null, 10);

// Get recent community conversations for dashboard, grouped by community
$recent_community_conversations = $conversation_manager->get_community_conversations(null, 10);

// Group conversations by event
$conversations_by_event = array();
$conversations_by_community = array();
if (!empty($recent_event_conversations)) {
	foreach ($recent_event_conversations as $conversation) {
		$event_key = $conversation->event_id;
		if (!isset($conversations_by_event[$event_key])) {
			$conversations_by_event[$event_key] = array(
				'event_title' => $conversation->event_title,
				'event_slug' => $conversation->event_slug,
				'event_date' => $conversation->event_date,
				'conversations' => array(),
			);
		}
		$conversations_by_event[$event_key]['conversations'][] = $conversation;
	}

	// Sort events by most recent conversation activity
	uasort(
		$conversations_by_event,
		function ($a, $b) {
			$a_latest = max(
				array_map(
					function ($conv) {
						return strtotime($conv->last_reply_date);
					},
					$a['conversations']
				)
			);
			$b_latest = max(
				array_map(
					function ($conv) {
						return strtotime($conv->last_reply_date);
					},
					$b['conversations']
				)
			);
			return $b_latest - $a_latest;
		}
	);

	// Limit to 3 most active events
	$conversations_by_event = array_slice($conversations_by_event, 0, 3, true);
}

// Group conversations by community
if (!empty($recent_community_conversations)) {
	foreach ($recent_community_conversations as $conversation) {
		$community_key = $conversation->community_id;
		if (!isset($conversations_by_community[$community_key])) {
			$conversations_by_community[$community_key] = array(
				'community_name' => $conversation->community_name,
				'community_slug' => $conversation->community_slug,
				'conversations' => array(),
			);
		}
		$conversations_by_community[$community_key]['conversations'][] = $conversation;
	}

	// Sort communities by most recent conversation activity
	uasort(
		$conversations_by_community,
		function ($a, $b) {
			$a_latest = max(
				array_map(
					function ($conv) {
						return strtotime($conv->last_reply_date);
					},
					$a['conversations']
				)
			);
			$b_latest = max(
				array_map(
					function ($conv) {
						return strtotime($conv->last_reply_date);
					},
					$b['conversations']
				)
			);
			return $b_latest - $a_latest;
		}
	);

	// Limit to 3 most active communities
	$conversations_by_community = array_slice($conversations_by_community, 0, 3, true);
}

// Set up template variables
$page_title = $user_logged_in
	? sprintf('Welcome back, %s!', htmlspecialchars($current_user->display_name))
	: 'Welcome to VivalaTable';
$page_description = 'Your social event hub for connecting, planning, and celebrating together.';
$breadcrumbs = array();
?>

<?php if ($user_logged_in) : ?>
<!-- Events Section -->
<div class="vt-section vt-mb">
	<div class="vt-section-header">
		<?php
		// Get events notification count
		$events_new_count = 0;
		if ($user_logged_in && !empty($recent_events)) {
			$events_new_count = VT_Activity_Tracker::get_new_count($current_user->id, 'events', $recent_events);
		}
		?>
		<h2 class="vt-heading vt-heading-md vt-mb">
			Events
			<?php if ($events_new_count > 0) : ?>
				<?php echo ' ' . sprintf($events_new_count == 1 ? '%d update' : '%d updates', $events_new_count); ?>
			<?php endif; ?>
		</h2>
	</div>
	<?php if (!empty($recent_events)) : ?>
		<div class="vt-flex vt-gap vt-flex-column">
			<?php foreach ($recent_events as $event) : ?>
				<?php
				$is_past = strtotime($event->event_date) < time();
				$is_hosting = $event->relationship_type === 'created';
				$is_new_event = $user_logged_in && VT_Activity_Tracker::has_new_activity($current_user->id, 'events', $event->id, $event->updated_at ?? $event->created_at);
				$item_classes = 'vt-section vt-flex vt-flex-between';
				if ($is_new_event) {
					$item_classes .= ' vt-item-new';
				}
				?>
				<div class="<?php echo $item_classes; ?>">
					<div class="vt-flex-1">
						<h4 class="vt-heading vt-heading-sm">
							<a href="/events/<?php echo $event->id; ?>" class="vt-text-primary">
								<?php echo htmlspecialchars($event->title); ?>
							</a>
						</h4>
						<div class="vt-flex vt-flex-wrap vt-gap-4 vt-text-muted">
							<span><?php echo date('M j, Y', strtotime($event->event_date)); ?></span>
							<?php if ($event->venue_info) : ?>
								<span><?php echo htmlspecialchars(VT_Text::truncate($event->venue_info, 20)); ?></span>
							<?php endif; ?>
						</div>
						<span class="vt-badge vt-badge-<?php echo $is_hosting ? 'primary' : 'secondary'; ?>">
							<?php echo $is_hosting ? 'Hosting' : 'Attending'; ?>
						</span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="vt-text-center vt-p-4">
			<div class="vt-mb-4"></div>
			<h3 class="vt-heading vt-heading-sm vt-mb">No Recent Events</h3>
			<p class="vt-text-muted">Create an event or RSVP to events to see them here.</p>
		</div>
	<?php endif; ?>
	<div class="vt-text-center vt-mt-4">
		<a href="/events" class="vt-btn">
			Browse All Events
		</a>
	</div>
</div>

<?php else : ?>
<!-- Login Section for Non-Logged-In Users -->
<div class="vt-section vt-mb">
	<div class="vt-section-header">
		<h2 class="vt-heading vt-heading-md vt-mb">Sign In to Get Started</h2>
		<p class="vt-text-muted">Log in to create events, join conversations, and connect with the community</p>
	</div>
	<div class="vt-text-center vt-p-4">
		<div class="vt-text-xl vt-mb-4"></div>
		<h3 class="vt-heading vt-heading-md vt-mb">Welcome to VivalaTable!</h3>
		<p class="vt-text-muted vt-mb">Your social event hub for connecting, planning, and celebrating together.</p>
		<div class="vt-flex vt-gap-4 vt-justify-center">
			<a href="/login" class="vt-btn vt-btn-lg">
				Sign In
			</a>
			<a href="/register" class="vt-btn vt-btn-lg">
				Create Account
			</a>
		</div>
	</div>
</div>

<!-- Preview Section for Non-Logged-In Users -->
<div class="vt-section vt-mb">
	<div class="vt-section-header">
		<h2 class="vt-heading vt-heading-md vt-mb">What You Can Do</h2>
		<p class="vt-text-muted">Discover all the features waiting for you</p>
	</div>
	<div class="vt-grid vt-gap-4">
		<div class="vt-flex vt-gap-4 vt-p-4">
			<div class="vt-text-xl"></div>
			<div class="vt-flex-1">
				<h4 class="vt-heading vt-heading-sm">Create & Host Events</h4>
				<p class="vt-text-muted">Plan dinner parties, game nights, and social gatherings</p>
			</div>
		</div>
		<div class="vt-flex vt-gap-4 vt-p-4">
			<div class="vt-text-xl"></div>
			<div class="vt-flex-1">
				<h4 class="vt-heading vt-heading-sm">Join Conversations</h4>
				<p class="vt-text-muted">Share tips and connect with fellow hosts and party-goers</p>
			</div>
		</div>
		<div class="vt-flex vt-gap-4 vt-p-4">
			<div class="vt-flex-1">
				<h4 class="vt-heading vt-heading-sm">Build Communities</h4>
				<p class="vt-text-muted">Create groups around shared interests and plan together</p>
			</div>
		</div>
	</div>
	<div class="vt-text-center vt-mt-4">
		<a href="/events" class="vt-btn">
			Browse Public Events
		</a>
	</div>
</div>

<?php endif; ?>