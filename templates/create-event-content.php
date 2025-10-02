<?php
/**
 * VivalaTable Create Event Content Template
 * Display-only template for event creation form
 * Form processing handled in VT_Pages::createEvent()
 */

// Accept variables from controller
$errors = $errors ?? array();
$messages = $messages ?? array();
?>

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

<!-- Create Event Form -->
<div class="vt-section">
	<h2 class="vt-heading vt-heading-md vt-mb-4">Create New Event</h2>

	<form method="post" class="vt-form">
		<?php echo vt_service('security.service')->nonceField('vt_create_event', 'vt_create_event_nonce'); ?>

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
					   value="<?php echo htmlspecialchars($_POST['event_time'] ?? '18:00'); ?>">
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

	// Set minimum date to today
	const dateInput = document.getElementById('event_date');
	if (dateInput) {
		const today = new Date().toISOString().split('T')[0];
		dateInput.setAttribute('min', today);
	}
});
</script>
