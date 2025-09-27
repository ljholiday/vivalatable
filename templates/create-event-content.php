<?php
/**
 * VivalaTable Create Event Content Template
 * Form for creating new events
 */

// Handle form submissions
$errors = array();
$messages = array();
$event_created = false;

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$event_data = [
		'title' => trim($_POST['title'] ?? ''),
		'description' => trim($_POST['description'] ?? ''),
		'event_date' => $_POST['event_date'] ?? '',
		'event_time' => $_POST['event_time'] ?? '',
		'venue' => trim($_POST['venue_info'] ?? ''),
		'guest_limit' => intval($_POST['guest_limit'] ?? 0),
		'privacy' => $_POST['privacy'] ?? 'public'
	];

	// Basic validation
	if (empty($event_data['title'])) {
		$errors[] = 'Event title is required.';
	}
	if (empty($event_data['description'])) {
		$errors[] = 'Event description is required.';
	}
	if (empty($event_data['event_date'])) {
		$errors[] = 'Event date is required.';
	}

	// If no validation errors, create event
	if (empty($errors)) {
		$event_manager = new VT_Event_Manager();
		$result = $event_manager->createEventForm($event_data);

		if (isset($result['success']) && $result['success']) {
			$messages[] = 'Event created successfully!';
			$event_created = true;
			$new_event_id = $result['event_id'];
		} elseif (isset($result['error'])) {
			$errors[] = $result['error'];
		} else {
			$errors[] = 'Failed to create event. Please try again.';
		}
	}
}
?>

<!-- Error Messages -->
<?php if (!empty($errors)) : ?>
	<div class="vt-alert vt-alert-error">
		<?php foreach ($errors as $error) : ?>
			<p><?php echo htmlspecialchars($error); ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<!-- Success Messages -->
<?php if (!empty($messages)) : ?>
	<div class="vt-alert vt-alert-success">
		<?php foreach ($messages as $message) : ?>
			<p><?php echo htmlspecialchars($message); ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<!-- Create Event Form -->
<div class="vt-section">
	<h2 class="vt-heading vt-heading-md vt-mb-4">Create New Event</h2>

	<form method="post" class="vt-form">
		<div class="vt-form-group">
			<label for="title" class="vt-form-label">Event Title</label>
			<input type="text" id="title" name="title" class="vt-form-input"
				   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
				   placeholder="What's the occasion?" required>
		</div>

		<div class="vt-form-group">
			<label for="description" class="vt-form-label">Description</label>
			<textarea id="description" name="description" class="vt-form-input" rows="4"
					  placeholder="Tell people what to expect..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
		</div>

		<div class="vt-form-row">
			<div class="vt-form-group">
				<label for="event_date" class="vt-form-label">Date</label>
				<input type="date" id="event_date" name="event_date" class="vt-form-input"
					   value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>" required>
			</div>

			<div class="vt-form-group">
				<label for="event_time" class="vt-form-label">Time</label>
				<input type="time" id="event_time" name="event_time" class="vt-form-input"
					   value="<?php echo htmlspecialchars($_POST['event_time'] ?? ''); ?>">
			</div>
		</div>

		<div class="vt-form-group">
			<label for="venue_info" class="vt-form-label">Location</label>
			<textarea id="venue_info" name="venue_info" class="vt-form-input" rows="2"
					  placeholder="Where's it happening?"><?php echo htmlspecialchars($_POST['venue_info'] ?? ''); ?></textarea>
		</div>

		<div class="vt-form-row">
			<div class="vt-form-group">
				<label for="guest_limit" class="vt-form-label">Guest Limit</label>
				<input type="number" id="guest_limit" name="guest_limit" class="vt-form-input"
					   value="<?php echo intval($_POST['guest_limit'] ?? 0); ?>" min="0"
					   placeholder="0 = unlimited">
			</div>

			<div class="vt-form-group">
				<label for="privacy" class="vt-form-label">Privacy</label>
				<select id="privacy" name="privacy" class="vt-form-input">
					<option value="public" <?php echo ($_POST['privacy'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public</option>
					<option value="private" <?php echo ($_POST['privacy'] ?? '') === 'private' ? 'selected' : ''; ?>>Private</option>
				</select>
			</div>
		</div>

		<div class="vt-form-group">
			<button type="submit" class="vt-btn vt-btn-lg" style="width: 100%;">Create Event</button>
		</div>
	</form>

	<?php if ($event_created): ?>
		<div class="vt-text-center vt-mt-4">
			<a href="/events" class="vt-btn vt-btn-secondary">View All Events</a>
		</div>
	<?php endif; ?>
</div>