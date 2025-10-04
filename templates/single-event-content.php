<?php
/**
 * VivalaTable Single Event Content Template
 * Display individual event details
 */

// Get event data
if (!isset($event_slug)) {
    echo '<div class="vt-alert vt-alert-error">Event not found.</div>';
    return;
}

$event_manager = new VT_Event_Manager();
$event = $event_manager->getEventBySlug($event_slug);

if (!$event) {
    echo '<div class="vt-alert vt-alert-error">Event not found.</div>';
    return;
}

// Check if user can view this event
if (!$event_manager->canUserViewEvent($event)) {
    echo '<div class="vt-alert vt-alert-error">You do not have permission to view this event.</div>';
    return;
}

$current_user = vt_service('auth.service')->getCurrentUser();
$is_host = $current_user && $current_user->id == $event->author_id;

// Get event conversations
$conversation_manager = new VT_Conversation_Manager();
$event_conversations = $conversation_manager->getEventConversations($event->id);

// Get active tab
$active_tab = $_GET['tab'] ?? 'overview';
?>

<!-- Event Secondary Navigation -->
<div class="vt-section vt-mb-4">
	<?php
	// Build tabs array for secondary navigation
	$tabs = [
		[
			'label' => 'Overview',
			'url' => '/events/' . $event->slug,
			'active' => ($active_tab === 'overview')
		],
		[
			'label' => 'Conversations',
			'url' => '/events/' . $event->slug . '?tab=conversations',
			'active' => ($active_tab === 'conversations'),
			'badge_count' => count($event_conversations)
		]
	];

	// Add Manage tab if user is host
	if ($is_host) {
		$tabs[] = [
			'label' => 'Manage',
			'url' => '/events/' . $event->slug . '/manage',
			'active' => false
		];
	}

	include VT_INCLUDES_DIR . '/../templates/partials/secondary-nav.php';
	?>
</div>

<!-- Event Header -->
<div class="vt-section">
	<div class="vt-flex vt-align-start vt-mb-4">
		<div class="vt-flex-1">
			<h1 class="vt-heading vt-heading-lg vt-mb-2"><?php echo vt_service('validation.sanitizer')->richText($event->title); ?></h1>
			<div class="vt-flex vt-gap vt-items-center vt-mb-2">
				<span class="vt-text-muted"><?php echo date('F j, Y', strtotime($event->event_date)); ?></span>
				<?php if ($event->event_time): ?>
					<span class="vt-text-muted">at <?php echo vt_service('validation.sanitizer')->richText($event->event_time); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="vt-flex vt-flex-column vt-items-end" style="gap: 0.5rem; margin-left: 2rem;">
			<span class="vt-badge vt-badge-<?php echo $event->privacy === 'private' ? 'secondary' : 'success'; ?>">
				<?php echo vt_service('validation.validator')->escHtml(ucfirst($event->privacy)); ?>
			</span>
		</div>
	</div>
</div>

<!-- Tab Content -->
<?php if ($active_tab === 'overview') : ?>
	<!-- Overview Tab -->
	<?php if ($event->venue_info): ?>
		<div class="vt-section vt-mb-4">
			<strong>Location:</strong> <?php echo vt_service('validation.sanitizer')->richText($event->venue_info); ?>
		</div>
	<?php endif; ?>

	<div class="vt-section vt-mb-4">
		<div class="vt-text-content">
			<?php echo vt_service('validation.sanitizer')->richText($event->description); ?>
		</div>
	</div>

	<!-- Event Stats -->
	<div class="vt-section vt-bg-light">
		<h3 class="vt-heading vt-heading-sm vt-mb-3">Event Details</h3>

		<div class="vt-grid vt-grid-2 vt-gap-4">
			<div>
				<strong>Privacy:</strong> <?php echo ucfirst($event->privacy); ?>
			</div>

			<?php if ($event->guest_limit > 0): ?>
				<div>
					<strong>Guest Limit:</strong> <?php echo $event->guest_limit; ?>
				</div>
			<?php endif; ?>

			<div>
				<strong>RSVPs:</strong>
				<?php
				$stats = $event->guest_stats ?? ['attending' => 0, 'declined' => 0, 'pending' => 0];
				// Ensure all required keys exist
				$stats = array_merge(['attending' => 0, 'declined' => 0, 'pending' => 0], (array)$stats);
				echo $stats['attending'] . ' attending';
				if ($stats['declined'] > 0) echo ', ' . $stats['declined'] . ' declined';
				if ($stats['pending'] > 0) echo ', ' . $stats['pending'] . ' pending';
				?>
			</div>

			<div>
				<strong>Created:</strong> <?php echo date('M j, Y', strtotime($event->created_at ?? 'now')); ?>
			</div>
		</div>
	</div>

	<!-- RSVP Section -->
	<?php if (vt_service('auth.service')->isLoggedIn()): ?>
		<div class="vt-section">
			<h3 class="vt-heading vt-heading-sm vt-mb-3">RSVP</h3>

			<?php if ($is_host): ?>
				<div class="vt-alert vt-alert-info">
					<p>You are the host of this event.</p>
				</div>
			<?php else: ?>
				<!-- RSVP Form would go here -->
				<div class="vt-alert vt-alert-info">
					<p>RSVP functionality coming soon.</p>
				</div>
			<?php endif; ?>
		</div>
	<?php else: ?>
		<div class="vt-section">
			<div class="vt-alert vt-alert-info">
				<p><a href="/login">Log in</a> to RSVP to this event.</p>
			</div>
		</div>
	<?php endif; ?>

<?php elseif ($active_tab === 'conversations') : ?>
	<!-- Conversations Tab -->
	<div class="vt-section">
		<div class="vt-flex vt-flex-between vt-mb-4">
			<h3 class="vt-heading vt-heading-sm">Event Conversations</h3>
			<?php if (vt_service('auth.service')->isLoggedIn()): ?>
				<a href="/conversations/create?event_id=<?php echo $event->id; ?>" class="vt-btn vt-btn-sm">
					Create Conversation
				</a>
			<?php endif; ?>
		</div>

		<?php if (!empty($event_conversations)): ?>
			<div class="vt-grid vt-grid-2 vt-gap">
				<?php foreach ($event_conversations as $conversation): ?>
					<div class="vt-section">
						<div class="vt-flex vt-flex-between vt-mb-4">
							<h3 class="vt-heading vt-heading-sm">
								<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-text-primary">
									<?php echo htmlspecialchars($conversation_manager->getDisplayTitle($conversation)); ?>
								</a>
							</h3>
						</div>

						<?php if ($conversation->content): ?>
							<div class="vt-mb-4">
								<p class="vt-text-muted"><?php echo htmlspecialchars(VT_Text::truncateWords($conversation->content, 15)); ?></p>
							</div>
						<?php endif; ?>

						<div class="vt-flex vt-flex-between">
							<div class="vt-stat">
								<div class="vt-stat-number vt-text-primary"><?php echo intval($conversation->reply_count); ?></div>
								<div class="vt-stat-label">Replies</div>
							</div>
							<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-btn vt-btn-sm">
								View
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="vt-text-center vt-p-4">
				<p class="vt-text-muted">No conversations started yet for this event.</p>
				<?php if (vt_service('auth.service')->isLoggedIn()): ?>
					<p class="vt-text-muted">Be the first to start planning and discussing ideas!</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

<?php endif; ?>