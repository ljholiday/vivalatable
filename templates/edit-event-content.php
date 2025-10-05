<?php
/**
 * VivalaTable Edit Event Content Template
 * Display-only template for editing events
 * Form processing handled in VT_Pages::editEventBySlug()
 */

// Accept variables from controller
$errors = $errors ?? array();
$messages = $messages ?? array();
$event = $event ?? null;
$user_communities = $user_communities ?? array();

if (!$event) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Event Not Found</h3>
		<p class="vt-text-muted vt-mb-4">The event you're trying to edit could not be found.</p>
		<a href="/events" class="vt-btn">Back to Events</a>
	</div>
	<?php
	return;
}
?>

<!-- Secondary Navigation -->
<div class="vt-mb-4">
	<?php
	$tabs = [
		[
			'label' => 'View Event',
			'url' => '/events/' . $event->slug,
			'active' => false
		],
		[
			'label' => 'Edit',
			'url' => '/events/' . $event->slug . '/edit',
			'active' => true
		],
		[
			'label' => 'Manage',
			'url' => '/events/' . $event->slug . '/manage',
			'active' => false
		]
	];
	include VT_INCLUDES_DIR . '/../templates/partials/secondary-nav.php';
	?>
</div>

<!-- Error Messages -->
<?php if (!empty($errors)) : ?>
	<div class="vt-alert vt-alert-error vt-mb-4">
		<h4 class="vt-heading vt-heading-sm vt-mb-4">Please fix the following errors:</h4>
		<ul>
			<?php foreach ($errors as $error) : ?>
				<li><?php echo htmlspecialchars($error); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<!-- Success Messages -->
<?php if (!empty($messages)) : ?>
	<div class="vt-alert vt-alert-success vt-mb-4">
		<?php foreach ($messages as $message) : ?>
			<p><?php echo htmlspecialchars($message); ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<!-- Edit Event Form -->
<div class="vt-section">
	<form method="post" class="vt-form">
		<?php echo vt_service('security.service')->nonceField('vt_edit_event', 'edit_event_nonce'); ?>

		<div class="vt-form-group">
			<label for="title" class="vt-form-label">Event Title</label>
			<input type="text" id="title" name="title" class="vt-form-input"
				   value="<?php echo htmlspecialchars($event->title); ?>"
				   placeholder="Give your event a catchy title" required>
		</div>

		<div class="vt-form-group">
			<label for="description" class="vt-form-label">Description</label>
			<textarea id="description" name="description" class="vt-form-input vt-form-textarea"
					  rows="6" placeholder="Tell people what to expect at your event"><?php echo htmlspecialchars($event->description); ?></textarea>
		</div>

		<div class="vt-form-group">
			<label for="event_date" class="vt-form-label">Date & Time</label>
			<input type="datetime-local" id="event_date" name="event_date" class="vt-form-input"
				   value="<?php echo date('Y-m-d\TH:i', strtotime($event->event_date)); ?>" required>
		</div>

		<div class="vt-form-group">
			<label for="venue_info" class="vt-form-label">Location</label>
			<input type="text" id="venue_info" name="venue_info" class="vt-form-input"
				   value="<?php echo htmlspecialchars($event->venue_info); ?>"
				   placeholder="Where will this event take place?">
		</div>

		<div class="vt-form-group">
			<label for="guest_limit" class="vt-form-label">Guest Limit</label>
			<input type="number" id="guest_limit" name="guest_limit" class="vt-form-input"
				   value="<?php echo $event->guest_limit; ?>"
				   placeholder="0 for unlimited" min="0">
		</div>

		<?php if (!empty($user_communities)) : ?>
		<div class="vt-form-group">
			<label for="community_id" class="vt-form-label">Community (Optional)</label>
			<select id="community_id" name="community_id" class="vt-form-input">
				<option value="0">Personal Event</option>
				<?php foreach ($user_communities as $community) : ?>
					<option value="<?php echo $community->id; ?>"
							<?php echo ($community->id == $event->community_id) ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($community->name); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>

		<div class="vt-form-group">
			<label for="privacy" class="vt-form-label">Privacy</label>
			<select id="privacy" name="privacy" class="vt-form-input">
				<option value="public" <?php echo ($event->privacy === 'public') ? 'selected' : ''; ?>>
					Public - Anyone can see and join
				</option>
				<option value="private" <?php echo ($event->privacy === 'private') ? 'selected' : ''; ?>>
					Private - Invitation only
				</option>
			</select>
		</div>

		<div class="vt-form-actions">
			<button type="submit" class="vt-btn vt-btn-primary vt-btn-lg">
				Update Event
			</button>
			<a href="/events/<?php echo htmlspecialchars($event->slug); ?>" class="vt-btn vt-btn-secondary vt-btn-lg">
				Cancel
			</a>
		</div>
	</form>

	<!-- Danger Zone -->
	<?php
	$event_manager = new VT_Event_Manager();
	$guest_manager = new VT_Guest_Manager();
	$guests = $guest_manager->getEventGuests($event->id);
	$confirmed_count = count(array_filter($guests, function($guest) { return $guest->status === 'confirmed'; }));

	$entity_type = 'event';
	$entity_id = $event->id;
	$entity_name = $event->title;
	$can_delete = $event_manager->canDeleteEvent($event->id);
	$confirmation_type = 'confirm';
	$blocker_count = 0; // Never block deletion
	$blocker_message = $confirmed_count > 0 ? "This event has {$confirmed_count} confirmed guest(s)." : '';
	$delete_message = 'Once you delete this event, there is no going back. This action cannot be undone.';
	$nonce_action = 'vt_delete_event';

	include VT_INCLUDES_DIR . '/../templates/partials/danger-zone.php';
	?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Form validation
	const form = document.querySelector('.vt-form');

	if (form) {
		form.addEventListener('submit', function(e) {
			const requiredFields = form.querySelectorAll('[required]');
			let isValid = true;

			requiredFields.forEach(field => {
				if (!field.value.trim()) {
					field.style.borderColor = '#ef4444';
					isValid = false;
				} else {
					field.style.borderColor = '';
				}
			});

			if (!isValid) {
				e.preventDefault();
				alert('Please fill in all required fields.');
			}
		});
	}

	// Set minimum date to today for future events
	const dateInput = document.getElementById('event_date');
	if (dateInput) {
		const today = new Date().toISOString().split('T')[0];
		dateInput.setAttribute('min', today);
	}
});
</script>
