<?php
/**
 * VivalaTable Create Community Content Template
 * Display-only template for community creation form
 * Form processing handled in VT_Pages::createCommunity()
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

<!-- Create Community Form -->
<div class="vt-section">
	<h2 class="vt-heading vt-heading-md vt-mb-4">Create New Community</h2>

	<form method="post" class="vt-form">
		<?php echo vt_service('security.service')->nonceField('vt_create_community', 'vt_create_community_nonce'); ?>

		<div class="vt-form-group">
			<label for="name" class="vt-form-label">Community Name</label>
			<input type="text" id="name" name="name" class="vt-form-input"
				   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
				   placeholder="Give your community a name" required>
		</div>

		<div class="vt-form-group">
			<label for="description" class="vt-form-label">Description</label>
			<textarea id="description" name="description" class="vt-form-input" rows="4"
					  placeholder="What's your community about?" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
		</div>

		<div class="vt-form-group">
			<label for="privacy" class="vt-form-label">Privacy</label>
			<select id="privacy" name="privacy" class="vt-form-input">
				<option value="public" <?php echo ($_POST['privacy'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public - Anyone can find and join</option>
				<option value="private" <?php echo ($_POST['privacy'] ?? '') === 'private' ? 'selected' : ''; ?>>Private - Invite only</option>
			</select>
		</div>

		<div class="vt-form-group">
			<button type="submit" class="vt-btn vt-btn-lg" style="width: 100%;">Create Community</button>
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
});
</script>
