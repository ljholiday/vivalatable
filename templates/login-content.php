<?php
/**
 * VivalaTable Login Content Template
 * Display-only template for login form
 * Form processing handled in VT_Pages::login()
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

<!-- Login Form -->
<div class="vt-section">
	<h2 class="vt-heading vt-heading-md vt-mb-4">Sign In</h2>

	<form method="post" class="vt-form">
		<?php echo vt_service('security.service')->nonceField('vt_login', 'vt_login_nonce'); ?>

		<div class="vt-form-group">
			<label for="username" class="vt-form-label">Username or Email</label>
			<input type="text" id="username" name="username" class="vt-form-input"
					value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
					placeholder="Enter your username or email" required>
		</div>

		<div class="vt-form-group">
			<label for="password" class="vt-form-label">Password</label>
			<input type="password" id="password" name="password" class="vt-form-input"
					placeholder="Enter your password" required>
		</div>

		<div class="vt-form-group">
			<label class="vt-flex">
				<input type="checkbox" name="remember" value="1"
						<?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
				<span class="vt-text-muted">Remember me for 2 weeks</span>
			</label>
		</div>

		<div class="vt-text-center">
			<button type="submit" class="vt-btn vt-btn-lg">
				Sign In
			</button>
		</div>
	</form>

	<div class="vt-text-center vt-mt-4">
		<p class="vt-text-muted vt-mb-4">New to VivalaTable?
			<a href="/register" class="vt-text-primary">Create Account</a>
		</p>
		<p><a href="/reset-password" class="vt-text-primary">Forgot your password?</a></p>
	</div>
</div>

<!-- Features Preview -->
<div class="vt-section vt-mt-4">
	<h3 class="vt-heading vt-heading-md vt-text-center vt-mb-4">Join the VivalaTable Community</h3>

	<div class="vt-grid vt-grid-3 vt-gap-4">
		<div class="vt-text-center vt-p-4">
			<div class="vt-text-xl vt-mb-4">🎪</div>
			<h4 class="vt-heading vt-heading-sm vt-mb-4">Host Events</h4>
			<p class="vt-text-muted">Create and manage amazing parties with our easy-to-use tools.</p>
		</div>

		<div class="vt-text-center vt-p-4">
			<div class="vt-text-xl vt-mb-4"></div>
			<h4 class="vt-heading vt-heading-sm vt-mb-4">RSVP & Attend</h4>
			<p class="vt-text-muted">Discover local events and connect with your community.</p>
		</div>

		<div class="vt-text-center vt-p-4">
			<div class="vt-text-xl vt-mb-4"></div>
			<h4 class="vt-heading vt-heading-sm vt-mb-4">Connect</h4>
			<p class="vt-text-muted">Share tips, recipes, and stories with fellow party enthusiasts.</p>
		</div>
	</div>
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
