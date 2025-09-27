<?php
/**
 * VivalaTable Edit Event Content Template
 * Edit existing event details and settings
 * Ported from PartyMinder WordPress plugin
 */

// Get event ID from URL parameter
$event_id = intval($_GET['event_id'] ?? 0);

if (!$event_id) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Event Not Found</h3>
		<p class="vt-text-muted vt-mb-4">Event ID is required to edit an event.</p>
		<a href="/events" class="vt-btn">Back to Events</a>
	</div>
	<?php
	return;
}

// Load event manager and get event
$event_manager = new VT_Event_Manager();
$event = $event_manager->getEvent($event_id);

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

// Check if current user can edit this event
$current_user = VT_Auth::getCurrentUser();
if (!$current_user || $event->author_id != $current_user->id) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Access Denied</h3>
		<p class="vt-text-muted vt-mb-4">You don't have permission to edit this event.</p>
		<a href="/events" class="vt-btn">Back to Events</a>
	</div>
	<?php
	return;
}

// Handle form submission
$errors = array();
$messages = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && VT_Security::verifyNonce($_POST['edit_event_nonce'], 'vt_edit_event')) {
	$event_data = array(
		'title' => VT_Sanitize::textField($_POST['title'] ?? ''),
		'description' => VT_Sanitize::post($_POST['description'] ?? ''),
		'event_date' => VT_Sanitize::textField($_POST['event_date'] ?? ''),
		'venue_info' => VT_Sanitize::textField($_POST['venue_info'] ?? ''),
		'guest_limit' => VT_Sanitize::int($_POST['guest_limit'] ?? 0),
		'privacy' => VT_Sanitize::textField($_POST['privacy'] ?? 'public'),
		'community_id' => VT_Sanitize::int($_POST['community_id'] ?? 0)
	);

	// Basic validation
	if (empty($event_data['title'])) {
		$errors[] = 'Event title is required.';
	}
	if (empty($event_data['event_date'])) {
		$errors[] = 'Event date is required.';
	}

	// If no validation errors, update the event
	if (empty($errors)) {
		$result = $event_manager->updateEvent($event_id, $event_data);
		if ($result) {
			$messages[] = 'Event updated successfully!';
			// Refresh event data
			$event = $event_manager->getEvent($event_id);
		} else {
			$errors[] = 'Failed to update event. Please try again.';
		}
	}
}

// Get user's communities for the dropdown
$community_manager = new VT_Community_Manager();
$user_communities = $community_manager->getUserCommunities($current_user->id);

// Set up template variables
$page_title = 'Edit Event: ' . htmlspecialchars($event->title);
$page_description = 'Update your event details and settings';
?>

<!-- Error Messages -->
<?php if (!empty($errors)) : ?>
	<div class="vt-alert vt-alert-error vt-mb-4">
		<?php foreach ($errors as $error) : ?>
			<p><?php echo htmlspecialchars($error); ?></p>
		<?php endforeach; ?>
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
		<?php echo VT_Security::nonceField('vt_edit_event', 'edit_event_nonce'); ?>

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
				View Event
			</a>
			<a href="/events" class="vt-btn vt-btn-secondary vt-btn-lg">
				Cancel
			</a>
		</div>
	</form>
</div>