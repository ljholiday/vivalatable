<?php
/**
 * VivalaTable Manage Event Content Template
 * Event management interface with Settings - Guests - Invites - View Event navigation
 * Ported from PartyMinder WordPress plugin
 */

// Get event slug from route parameter
$event_slug = VT_Router::getParam('slug');
$active_tab = $_GET['tab'] ?? 'settings';

if (!$event_slug) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Event Not Found</h3>
		<p class="vt-text-muted vt-mb-4">Event slug is required to manage an event.</p>
		<a href="/events" class="vt-btn">Back to Events</a>
	</div>
	<?php
	return;
}

// Load managers and get event
$event_manager = new VT_Event_Manager();
$guest_manager = new VT_Guest_Manager();
$event = $event_manager->getEventBySlug($event_slug);

if (!$event) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Event Not Found</h3>
		<p class="vt-text-muted vt-mb-4">The event you're trying to manage could not be found.</p>
		<a href="/events" class="vt-btn">Back to Events</a>
	</div>
	<?php
	return;
}

// Check if current user can manage this event
$current_user = vt_service('auth.service')->getCurrentUser();
if (!$current_user || $event->author_id != $current_user->id) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Access Denied</h3>
		<p class="vt-text-muted vt-mb-4">You don't have permission to manage this event.</p>
		<a href="/events" class="vt-btn">Back to Events</a>
	</div>
	<?php
	return;
}

// Handle form submissions based on active tab
$errors = array();
$messages = array();

// Get event data for display
$guests = $guest_manager->getEventGuests($event->id);
$guest_count = count($guests);
$confirmed_count = count(array_filter($guests, function($guest) { return $guest->status === 'confirmed'; }));

// Set up template variables
$page_title = 'Manage: ' . htmlspecialchars($event->title);
$page_description = 'Manage your event settings, guests, and invitations';
?>

<!-- Management Tabs -->
<div class="vt-section vt-mb-4">
	<div class="vt-tab-nav vt-flex vt-gap-4 vt-flex-wrap">
		<a href="/events/<?php echo $event->slug; ?>/manage?tab=settings"
		   class="vt-btn <?php echo ($active_tab === 'settings') ? 'is-active' : ''; ?>">
			Overview
		</a>
		<a href="/events/<?php echo $event->slug; ?>/manage?tab=guests"
		   class="vt-btn <?php echo ($active_tab === 'guests') ? 'is-active' : ''; ?>">
			Guests (<?php echo $confirmed_count; ?>)
		</a>
		<a href="/events/<?php echo $event->slug; ?>/manage?tab=invites"
		   class="vt-btn <?php echo ($active_tab === 'invites') ? 'is-active' : ''; ?>">
			Invitations
		</a>
		<a href="/events/<?php echo $event->slug; ?>/edit" class="vt-btn">
			Edit
		</a>
		<a href="/events/<?php echo htmlspecialchars($event->slug); ?>" class="vt-btn">
			View Event
		</a>
	</div>
</div>

<!-- Event Header -->
<div class="vt-section vt-mb-4">
	<h2 class="vt-heading vt-heading-lg vt-text-primary vt-mb-2">
		<?php echo htmlspecialchars($event->title); ?>
	</h2>
	<p class="vt-text-muted">
		<?php echo date('F j, Y \a\t g:i A', strtotime($event->event_date)); ?>
	</p>
</div>

<!-- Tab Content -->
<?php if ($active_tab === 'settings') : ?>
	<!-- Settings Tab -->
	<div class="vt-section">
		<h3 class="vt-heading vt-heading-md vt-mb-4">Event Settings</h3>

		<div class="vt-grid vt-gap-4">
			<div class="vt-card">
				<div class="vt-card-body">
					<h4 class="vt-heading vt-heading-sm vt-mb-2">Event Status</h4>
					<p class="vt-text-muted">Current status: <strong><?php echo ucfirst($event->event_status); ?></strong></p>
				</div>
			</div>

			<div class="vt-card">
				<div class="vt-card-body">
					<h4 class="vt-heading vt-heading-sm vt-mb-2">Privacy Settings</h4>
					<p class="vt-text-muted vt-mb-2">Privacy: <strong><?php echo ucfirst($event->privacy); ?></strong></p>
					<p class="vt-text-muted vt-mb-4">
						<?php if ($event->privacy === 'public') : ?>
							Anyone can find and join this event
						<?php else : ?>
							Only invited people can see and join
						<?php endif; ?>
					</p>
					<a href="/events/<?php echo $event->slug; ?>/edit" class="vt-btn vt-btn-secondary">
						Change Privacy
					</a>
				</div>
			</div>

			<div class="vt-card">
				<div class="vt-card-body">
					<h4 class="vt-heading vt-heading-sm vt-mb-2">Guest Management</h4>
					<p class="vt-text-muted vt-mb-2"><?php echo $confirmed_count; ?> confirmed guests</p>
					<?php if ($event->guest_limit > 0) : ?>
						<p class="vt-text-muted vt-mb-4">Limit: <?php echo $event->guest_limit; ?> guests</p>
					<?php else : ?>
						<p class="vt-text-muted vt-mb-4">No guest limit</p>
					<?php endif; ?>
					<a href="/events/<?php echo $event->slug; ?>/manage?tab=guests" class="vt-btn vt-btn-secondary">
						Manage Guests
					</a>
				</div>
			</div>
		</div>
	</div>

<?php elseif ($active_tab === 'guests') : ?>
	<!-- Guests Tab -->
	<div class="vt-section">
		<div class="vt-flex vt-flex-between vt-mb-4">
			<h3 class="vt-heading vt-heading-md">Event Guests</h3>
			<a href="/events/<?php echo $event->slug; ?>/manage?tab=invites" class="vt-btn">
				Send Invitations
			</a>
		</div>

		<?php if (!empty($guests)) : ?>
			<div class="vt-table-responsive">
				<table class="vt-table">
					<thead>
						<tr>
							<th>Name</th>
							<th>Email</th>
							<th>Status</th>
							<th>RSVP Date</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($guests as $guest) : ?>
							<tr>
								<td>
									<strong><?php echo htmlspecialchars($guest->name); ?></strong>
									<?php if ($guest->plus_one) : ?>
										<br><small class="vt-text-muted">+1: <?php echo htmlspecialchars($guest->plus_one_name ?: 'Guest'); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo htmlspecialchars($guest->email); ?></td>
								<td>
									<span class="vt-badge vt-badge-<?php
										echo $guest->status === 'confirmed' ? 'success' :
											($guest->status === 'declined' ? 'danger' : 'warning');
									?>">
										<?php echo ucfirst($guest->status); ?>
									</span>
								</td>
								<td><?php echo date('M j, Y', strtotime($guest->rsvp_date)); ?></td>
								<td>
									<button class="vt-btn vt-btn-sm vt-btn-secondary" onclick="viewGuestDetails(<?php echo $guest->id; ?>)">
										View
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div class="vt-text-center vt-p-4">
				<p class="vt-text-muted vt-mb-4">No guests have RSVP'd yet.</p>
				<a href="/events/<?php echo $event->slug; ?>/manage?tab=invites" class="vt-btn">
					Send Your First Invitations
				</a>
			</div>
		<?php endif; ?>
	</div>

<?php elseif ($active_tab === 'invites') : ?>
	<!-- Invitations Tab -->
	<div class="vt-section">
		<div class="vt-section-header">
			<h2 class="vt-heading vt-heading-md vt-text-primary">Send Invitations</h2>
		</div>

		<!-- Copyable Invitation Links -->
		<div class="vt-card vt-mb-4">
			<div class="vt-card-header">
				<h3 class="vt-heading vt-heading-sm">Share Event Link</h3>
			</div>
			<div class="vt-card-body">
				<p class="vt-text-muted vt-mb-4">
					Copy and share this link via text, social media, Discord, Slack, or any other platform.
				</p>

				<div class="vt-form-group vt-mb-4">
					<label class="vt-form-label">Event Invitation Link</label>
					<div class="vt-flex vt-gap-2">
						<input type="text" class="vt-form-input vt-flex-1" id="invitation-link"
							   value="<?php echo VT_Http::getBaseUrl() . '/events/' . htmlspecialchars($event->slug) . '?join=1'; ?>"
							   readonly>
						<button type="button" class="vt-btn vt-copy-invitation-link">
							Copy
						</button>
					</div>
				</div>

				<div class="vt-form-group">
					<label class="vt-form-label">Custom Message (Optional)</label>
					<textarea class="vt-form-textarea" id="custom-message" rows="3"
							  placeholder="Add a personal message to include when sharing..."></textarea>
					<div class="vt-mt-2">
						<button type="button" class="vt-btn vt-copy-invitation-with-message">
							Copy Link with Message
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Email Invitation Form -->
		<form method="post" class="vt-form">
			<?php echo vt_service('security.service')->nonceField('vt_send_invitations', 'invite_nonce'); ?>
			<input type="hidden" name="event_id" value="<?php echo $event->id; ?>">

			<div class="vt-form-group">
				<label for="invite_emails" class="vt-form-label">Email Addresses</label>
				<textarea id="invite_emails" name="invite_emails" class="vt-form-input vt-form-textarea"
						  rows="4" placeholder="Enter email addresses separated by commas or new lines"
						  required></textarea>
				<small class="vt-form-help">
					You can enter multiple email addresses separated by commas or on separate lines.
				</small>
			</div>

			<div class="vt-form-group">
				<label for="invite_message" class="vt-form-label">Personal Message (Optional)</label>
				<textarea id="invite_message" name="invite_message" class="vt-form-input vt-form-textarea"
						  rows="3" placeholder="Add a personal message to your invitation"></textarea>
			</div>

			<div class="vt-form-actions">
				<button type="submit" class="vt-btn vt-btn-primary">
					Send Invitations
				</button>
			</div>
		</form>
	</div>
<?php endif; ?>

