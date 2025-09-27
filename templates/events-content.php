<?php
/**
 * VivalaTable Events List Content Template
 * Main events page with tab-based filtering
 * Ported from PartyMinder events-unified-content.php
 */

// Get current user info
$current_user = VT_Auth::getCurrentUser();
$user_logged_in = VT_Auth::isLoggedIn();
$user_email = $user_logged_in ? $current_user->email : '';

// Get data for all tabs
$user_events = array();
$rsvp_events = array();
$all_events = array();

// Create event manager instance
$event_manager = new VT_Event_Manager();

if ($user_logged_in) {
	// Get user's created events
	$user_events = $event_manager->getUserEvents($current_user->id, 20);

	// Get user's RSVP events
	$db = VT_Database::getInstance();
	$events_table = $db->prefix . 'events';
	$guests_table = $db->prefix . 'guests';

	$query = "SELECT DISTINCT e.*, g.status as rsvp_status FROM $events_table e
			  INNER JOIN $guests_table g ON e.id = g.event_id
			  WHERE g.email = %s
			  AND e.event_status = 'active'
			  ORDER BY e.event_date ASC";

	$rsvp_events = $db->getResults($db->prepare($query, $user_email));

	// Add guest stats to each RSVP event
	foreach ($rsvp_events as $event) {
		$event->guest_stats = $event_manager->getGuestStats($event->id);
	}

	// Get ALL events the user has permission to view
	$all_events = $event_manager->getUpcomingEvents(50);

	// Add guest stats to each event in all_events
	if ($all_events && is_array($all_events)) {
		foreach ($all_events as $event) {
			if (!isset($event->guest_stats)) {
				$event->guest_stats = $event_manager->getGuestStats($event->id);
			}
		}
	}
} else {
	// Not logged in - only show public events
	$all_events = $event_manager->getUpcomingEvents(20);
}

// Set up template variables
$page_title = 'Events';
$page_description = 'Discover amazing events and manage your gatherings';
?>

<!-- Event Filters/Tabs -->
<?php if ($user_logged_in) : ?>
<div class="vt-section vt-mb-4">
	<div class="vt-conversations-nav vt-flex vt-gap-4 vt-flex-wrap">
		<!-- Event Type Filters -->
		<button class="vt-btn is-active" data-filter="my-events" role="tab" aria-selected="true" aria-controls="vt-events-list">
			My Events
		</button>
		<button class="vt-btn" data-filter="all-events" role="tab" aria-selected="false" aria-controls="vt-events-list">
			All Events
		</button>
		<button class="vt-btn" data-filter="rsvp-events" role="tab" aria-selected="false" aria-controls="vt-events-list">
			My RSVPs
		</button>
	</div>
</div>
<?php endif; ?>

<div class="vt-section">
	<div id="vt-events-list" class="vt-grid vt-grid-2 vt-gap">
		<?php if ($user_logged_in) : ?>
			<!-- My Events Tab Content (Default) -->
			<div class="vt-events-tab-content" data-tab="my-events">
				<?php if (!empty($user_events)) : ?>
					<?php foreach ($user_events as $event) : ?>
						<?php
						$event_date = new DateTime($event->event_date);
						$is_past = $event_date < new DateTime();
						$date_formatted = $event_date->format('M j, Y');
						$time_formatted = $event_date->format('g:i A');
						?>
						<div class="vt-card">
							<div class="vt-card-body">
								<div class="vt-flex vt-flex-between vt-mb-4">
									<div class="vt-flex-1">
										<h3 class="vt-heading vt-heading-md vt-mb-2">
											<a href="/events/<?php echo $event->id; ?>" class="vt-text-primary">
												<?php echo htmlspecialchars($event->title); ?>
											</a>
										</h3>
										<div class="vt-text-muted vt-mb-2">
											<?php echo $date_formatted; ?> at <?php echo $time_formatted; ?>
										</div>
										<?php if ($event->venue_info) : ?>
											<div class="vt-text-muted vt-mb-2">
												📍 <?php echo htmlspecialchars($event->venue_info); ?>
											</div>
										<?php endif; ?>
									</div>
								</div>

								<?php if ($event->description) : ?>
									<p class="vt-text-muted vt-mb-4">
										<?php echo htmlspecialchars(VT_Text::truncate($event->description, 120)); ?>
									</p>
								<?php endif; ?>

								<div class="vt-flex vt-flex-between vt-items-center">
									<div class="vt-flex vt-gap-2">
										<span class="vt-badge vt-badge-primary">Hosting</span>
										<?php if ($is_past) : ?>
											<span class="vt-badge vt-badge-secondary">Past Event</span>
										<?php endif; ?>
									</div>
									<div class="vt-text-sm vt-text-muted">
										<?php
										$guest_count = isset($event->guest_stats->confirmed) ? $event->guest_stats->confirmed : 0;
										echo $guest_count . ' guest' . ($guest_count !== 1 ? 's' : '');
										?>
									</div>
								</div>

								<div class="vt-flex vt-gap-2 vt-mt-4">
									<a href="/events/<?php echo $event->id; ?>" class="vt-btn vt-btn-sm">View</a>
									<a href="/events/<?php echo $event->id; ?>/manage" class="vt-btn vt-btn-sm">Manage</a>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="vt-text-center vt-p-8">
						<h3 class="vt-heading vt-heading-md vt-mb-4">No Events Yet</h3>
						<p class="vt-text-muted vt-mb-4">You haven't created any events yet. Start planning your first gathering!</p>
						<a href="/events/create" class="vt-btn vt-btn-primary">Create Your First Event</a>
					</div>
				<?php endif; ?>
			</div>

			<!-- All Events Tab Content -->
			<div class="vt-events-tab-content" data-tab="all-events" style="display: none;">
				<?php if (!empty($all_events)) : ?>
					<?php foreach ($all_events as $event) : ?>
						<?php
						$event_date = new DateTime($event->event_date);
						$is_past = $event_date < new DateTime();
						$date_formatted = $event_date->format('M j, Y');
						$time_formatted = $event_date->format('g:i A');
						$is_hosting = $event->author_id == $current_user->id;
						?>
						<div class="vt-card">
							<div class="vt-card-body">
								<div class="vt-flex vt-flex-between vt-mb-4">
									<div class="vt-flex-1">
										<h3 class="vt-heading vt-heading-md vt-mb-2">
											<a href="/events/<?php echo $event->id; ?>" class="vt-text-primary">
												<?php echo htmlspecialchars($event->title); ?>
											</a>
										</h3>
										<div class="vt-text-muted vt-mb-2">
											<?php echo $date_formatted; ?> at <?php echo $time_formatted; ?>
										</div>
										<?php if ($event->venue_info) : ?>
											<div class="vt-text-muted vt-mb-2">
												📍 <?php echo htmlspecialchars($event->venue_info); ?>
											</div>
										<?php endif; ?>
									</div>
								</div>

								<?php if ($event->description) : ?>
									<p class="vt-text-muted vt-mb-4">
										<?php echo htmlspecialchars(VT_Text::truncate($event->description, 120)); ?>
									</p>
								<?php endif; ?>

								<div class="vt-flex vt-flex-between vt-items-center">
									<div class="vt-flex vt-gap-2">
										<?php if ($is_hosting) : ?>
											<span class="vt-badge vt-badge-primary">Hosting</span>
										<?php else : ?>
											<span class="vt-badge vt-badge-secondary">Public</span>
										<?php endif; ?>
										<?php if ($is_past) : ?>
											<span class="vt-badge vt-badge-secondary">Past Event</span>
										<?php endif; ?>
									</div>
									<div class="vt-text-sm vt-text-muted">
										<?php
										$guest_count = isset($event->guest_stats->confirmed) ? $event->guest_stats->confirmed : 0;
										echo $guest_count . ' guest' . ($guest_count !== 1 ? 's' : '');
										?>
									</div>
								</div>

								<div class="vt-flex vt-gap-2 vt-mt-4">
									<a href="/events/<?php echo $event->id; ?>" class="vt-btn vt-btn-sm">View</a>
									<?php if (!$is_past) : ?>
										<button class="vt-btn vt-btn-sm vt-btn-primary" onclick="openRSVPModal(<?php echo $event->id; ?>)">RSVP</button>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="vt-text-center vt-p-8">
						<h3 class="vt-heading vt-heading-md vt-mb-4">No Events Found</h3>
						<p class="vt-text-muted">No events are currently available.</p>
					</div>
				<?php endif; ?>
			</div>

			<!-- RSVP Events Tab Content -->
			<div class="vt-events-tab-content" data-tab="rsvp-events" style="display: none;">
				<?php if (!empty($rsvp_events)) : ?>
					<?php foreach ($rsvp_events as $event) : ?>
						<?php
						$event_date = new DateTime($event->event_date);
						$is_past = $event_date < new DateTime();
						$date_formatted = $event_date->format('M j, Y');
						$time_formatted = $event_date->format('g:i A');
						?>
						<div class="vt-card">
							<div class="vt-card-body">
								<div class="vt-flex vt-flex-between vt-mb-4">
									<div class="vt-flex-1">
										<h3 class="vt-heading vt-heading-md vt-mb-2">
											<a href="/events/<?php echo $event->id; ?>" class="vt-text-primary">
												<?php echo htmlspecialchars($event->title); ?>
											</a>
										</h3>
										<div class="vt-text-muted vt-mb-2">
											<?php echo $date_formatted; ?> at <?php echo $time_formatted; ?>
										</div>
										<?php if ($event->venue_info) : ?>
											<div class="vt-text-muted vt-mb-2">
												📍 <?php echo htmlspecialchars($event->venue_info); ?>
											</div>
										<?php endif; ?>
									</div>
								</div>

								<div class="vt-flex vt-flex-between vt-items-center">
									<div class="vt-flex vt-gap-2">
										<span class="vt-badge vt-badge-<?php echo strtolower($event->rsvp_status) === 'yes' ? 'success' : (strtolower($event->rsvp_status) === 'maybe' ? 'warning' : 'danger'); ?>">
											<?php echo ucfirst($event->rsvp_status); ?>
										</span>
										<?php if ($is_past) : ?>
											<span class="vt-badge vt-badge-secondary">Past Event</span>
										<?php endif; ?>
									</div>
									<div class="vt-text-sm vt-text-muted">
										<?php
										$guest_count = isset($event->guest_stats->confirmed) ? $event->guest_stats->confirmed : 0;
										echo $guest_count . ' guest' . ($guest_count !== 1 ? 's' : '');
										?>
									</div>
								</div>

								<div class="vt-flex vt-gap-2 vt-mt-4">
									<a href="/events/<?php echo $event->id; ?>" class="vt-btn vt-btn-sm">View</a>
									<?php if (!$is_past) : ?>
										<button class="vt-btn vt-btn-sm" onclick="openRSVPModal(<?php echo $event->id; ?>)">Update RSVP</button>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="vt-text-center vt-p-8">
						<h3 class="vt-heading vt-heading-md vt-mb-4">No RSVPs Yet</h3>
						<p class="vt-text-muted vt-mb-4">You haven't RSVP'd to any events yet.</p>
						<a href="#" onclick="showAllEventsTab()" class="vt-btn">Browse Events</a>
					</div>
				<?php endif; ?>
			</div>

		<?php else : ?>
			<!-- Non-logged-in users see public events -->
			<?php if (!empty($all_events)) : ?>
				<?php foreach ($all_events as $event) : ?>
					<?php
					$event_date = new DateTime($event->event_date);
					$is_past = $event_date < new DateTime();
					$date_formatted = $event_date->format('M j, Y');
					$time_formatted = $event_date->format('g:i A');
					?>
					<div class="vt-card">
						<div class="vt-card-body">
							<div class="vt-flex vt-flex-between vt-mb-4">
								<div class="vt-flex-1">
									<h3 class="vt-heading vt-heading-md vt-mb-2">
										<a href="/events/<?php echo $event->id; ?>" class="vt-text-primary">
											<?php echo htmlspecialchars($event->title); ?>
										</a>
									</h3>
									<div class="vt-text-muted vt-mb-2">
										<?php echo $date_formatted; ?> at <?php echo $time_formatted; ?>
									</div>
									<?php if ($event->venue_info) : ?>
										<div class="vt-text-muted vt-mb-2">
											📍 <?php echo htmlspecialchars($event->venue_info); ?>
										</div>
									<?php endif; ?>
								</div>
							</div>

							<?php if ($event->description) : ?>
								<p class="vt-text-muted vt-mb-4">
									<?php echo htmlspecialchars(VT_Text::truncate($event->description, 120)); ?>
								</p>
							<?php endif; ?>

							<div class="vt-flex vt-gap-2 vt-mt-4">
								<a href="/events/<?php echo $event->id; ?>" class="vt-btn vt-btn-sm">View Event</a>
								<?php if (!$is_past) : ?>
									<a href="/login" class="vt-btn vt-btn-sm vt-btn-primary">Sign In to RSVP</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="vt-text-center vt-p-8">
					<h3 class="vt-heading vt-heading-md vt-mb-4">No Events Found</h3>
					<p class="vt-text-muted vt-mb-4">No public events are currently available.</p>
					<a href="/login" class="vt-btn">Sign In to Create Events</a>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<script>
function showAllEventsTab() {
	// Hide all tabs
	document.querySelectorAll('.vt-events-tab-content').forEach(tab => {
		tab.style.display = 'none';
	});

	// Show all events tab
	document.querySelector('[data-tab="all-events"]').style.display = 'block';

	// Update button states
	document.querySelectorAll('.vt-conversations-nav .vt-btn').forEach(btn => {
		btn.classList.remove('is-active');
		btn.setAttribute('aria-selected', 'false');
	});
	document.querySelector('[data-filter="all-events"]').classList.add('is-active');
	document.querySelector('[data-filter="all-events"]').setAttribute('aria-selected', 'true');
}

// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
	const tabButtons = document.querySelectorAll('.vt-conversations-nav .vt-btn');
	const tabContents = document.querySelectorAll('.vt-events-tab-content');

	tabButtons.forEach(button => {
		button.addEventListener('click', function() {
			const filter = this.getAttribute('data-filter');

			// Hide all tabs
			tabContents.forEach(tab => {
				tab.style.display = 'none';
			});

			// Show selected tab
			const activeTab = document.querySelector(`[data-tab="${filter}"]`);
			if (activeTab) {
				activeTab.style.display = 'block';
			}

			// Update button states
			tabButtons.forEach(btn => {
				btn.classList.remove('is-active');
				btn.setAttribute('aria-selected', 'false');
			});
			this.classList.add('is-active');
			this.setAttribute('aria-selected', 'true');
		});
	});
});
</script>