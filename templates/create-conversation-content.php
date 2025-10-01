<?php
/**
 * VivalaTable Create Conversation Content Template
 * Display-only template for conversation creation form
 * Form processing handled in VT_Pages::createConversation()
 */

// Accept variables from controller
$errors = $errors ?? array();
$messages = $messages ?? array();
$current_user = $current_user ?? null;
$community_id = $community_id ?? 0;
$event_id = $event_id ?? 0;
$community = $community ?? null;
$event = $event ?? null;
$user_communities = $user_communities ?? array();
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

<!-- Context Information -->
<?php if ($community) : ?>
	<div class="vt-alert vt-alert-info vt-mb-4">
		<p>Creating conversation in <strong><?php echo htmlspecialchars($community->name); ?></strong></p>
	</div>
<?php elseif ($event) : ?>
	<div class="vt-alert vt-alert-info vt-mb-4">
		<p>Creating conversation for event: <strong><?php echo htmlspecialchars($event->title); ?></strong></p>
	</div>
<?php endif; ?>

<!-- Create Conversation Form -->
<div class="vt-section">
	<form method="post" class="vt-form">
		<?php echo vt_service('security.service')->nonceField('vt_create_conversation', 'create_conversation_nonce'); ?>

		<div class="vt-form-group">
			<label for="title" class="vt-form-label">Conversation Title</label>
			<input type="text" id="title" name="title" class="vt-form-input"
				   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
				   placeholder="What would you like to discuss?" required>
		</div>

		<div class="vt-form-group">
			<label for="content" class="vt-form-label">Your Thoughts</label>
			<textarea id="content" name="content" class="vt-form-input vt-form-textarea"
					  rows="8" placeholder="Share your thoughts, ask a question, or start a discussion..."
					  required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
		</div>

		<div class="vt-form-group">
			<label for="community_id" class="vt-form-label">Community</label>
			<select id="community_id" name="community_id" class="vt-form-input" required>
				<option value="">Select a community...</option>
				<?php foreach ($user_communities as $user_community) : ?>
					<option value="<?php echo $user_community->id; ?>"
							<?php echo ($user_community->id == $community_id) ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($user_community->name); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<small class="vt-form-help">
				All conversations must be in a community. Choose where this conversation should live.
			</small>
		</div>

		<?php if ($event_id && $event) : ?>
			<input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
		<?php endif; ?>

		<div class="vt-form-actions">
			<button type="submit" class="vt-btn vt-btn-primary vt-btn-lg">
				Start Conversation
			</button>
			<a href="/conversations" class="vt-btn vt-btn-secondary vt-btn-lg">
				Cancel
			</a>
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

	// Privacy options logic
	const communitySelect = document.getElementById('community_id');
	const privacySelect = document.getElementById('privacy');

	if (communitySelect && privacySelect) {
		// Update privacy options based on community selection
		communitySelect.addEventListener('change', function() {
			if (this.value === '0') {
				// General discussion - show all privacy options
				privacySelect.innerHTML = `
					<option value="public">Public - Anyone can participate</option>
					<option value="members">Members Only - Limited to community members</option>
				`;
			} else {
				// Community discussion - default to members only
				privacySelect.innerHTML = `
					<option value="members">Members Only - Community members can participate</option>
					<option value="public">Public - Anyone can participate</option>
				`;
				privacySelect.value = 'members';
			}
		});

		// Trigger change event on page load
		communitySelect.dispatchEvent(new Event('change'));
	}
});
</script>
