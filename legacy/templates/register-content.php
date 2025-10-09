<?php
/**
 * VivalaTable Registration Content Template
 * Display-only template for registration form
 * Form processing handled in VT_Pages::register()
 */

// Accept variables from controller
$errors = $errors ?? array();
$messages = $messages ?? array();
$guest_token = $guest_token ?? '';
$guest_data = $guest_data ?? null;
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

<!-- Registration Form -->
<div class="vt-section">
	<h2 class="vt-heading vt-heading-md vt-mb-4">Create Account</h2>

	<form method="post" class="vt-form">
		<?php echo vt_service('security.service')->nonceField('vt_register', 'vt_register_nonce'); ?>

		<?php if ($guest_token) : ?>
			<input type="hidden" name="guest_token" value="<?php echo htmlspecialchars($guest_token); ?>">
		<?php endif; ?>

		<div class="vt-form-group">
			<label for="display_name" class="vt-form-label">Display Name</label>
			<input type="text" id="display_name" name="display_name" class="vt-form-input"
				   value="<?php echo isset($_POST['display_name']) ? htmlspecialchars($_POST['display_name']) : ($guest_data ? htmlspecialchars($guest_data->name) : ''); ?>"
				   placeholder="Your display name" required>
		</div>

		<div class="vt-form-group">
			<label for="username" class="vt-form-label">Username</label>
			<input type="text" id="username" name="username" class="vt-form-input"
				   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
				   placeholder="Choose a username" required>
		</div>

		<div class="vt-form-group">
			<label for="email" class="vt-form-label">Email Address</label>
			<input type="email" id="email" name="email" class="vt-form-input"
				   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ($guest_data ? htmlspecialchars($guest_data->email) : ''); ?>"
				   placeholder="your@email.com" required <?php echo $guest_data ? 'readonly' : ''; ?>>
		</div>

		<div class="vt-form-group">
			<label for="password" class="vt-form-label">Password</label>
			<input type="password" id="password" name="password" class="vt-form-input"
				   placeholder="At least 8 characters" required>
		</div>

		<div class="vt-form-group">
			<label for="confirm_password" class="vt-form-label">Confirm Password</label>
			<input type="password" id="confirm_password" name="confirm_password" class="vt-form-input"
				   placeholder="Confirm your password" required>
			<div id="password-match-indicator" style="display: none; margin-top: 8px;"></div>
		</div>

		<div class="vt-form-group">
			<button type="submit" class="vt-btn vt-btn-lg" style="width: 100%;">Create Account</button>
		</div>
	</form>

	<div class="vt-text-center vt-mt-4">
	<p>Already have an account? <a href="/auth" class="vt-text-primary">Sign in here</a></p>
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

	// Password confirmation validation with visual feedback
	const confirmPassword = document.getElementById('confirm_password');
	const password = document.getElementById('password');
	const matchIndicator = document.getElementById('password-match-indicator');

	if (confirmPassword && password && matchIndicator) {
		function updatePasswordMatchIndicator() {
			const passwordValue = password.value;
			const confirmValue = confirmPassword.value;

			// Only show indicator if confirm password has content
			if (confirmValue.length === 0) {
				matchIndicator.style.display = 'none';
				confirmPassword.setCustomValidity('');
				return;
			}

			matchIndicator.style.display = 'block';

			if (passwordValue === confirmValue) {
				// Passwords match
				matchIndicator.className = 'vt-text-success';
				matchIndicator.innerHTML = '<span>✓</span> <span>Passwords match</span>';
				confirmPassword.setCustomValidity('');
			} else {
				// Passwords don't match
				matchIndicator.className = 'vt-text-error';
				matchIndicator.innerHTML = '<span>✗</span> <span>Passwords do not match</span>';
				confirmPassword.setCustomValidity('Passwords do not match');
			}
		}

		confirmPassword.addEventListener('input', updatePasswordMatchIndicator);
		password.addEventListener('input', updatePasswordMatchIndicator);
	}
});
</script>
