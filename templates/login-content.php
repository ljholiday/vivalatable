<?php
/**
 * VivalaTable Login Content Template
 * Branded login/register page with profile setup flow
 * Ported from PartyMinder WordPress plugin
 */

// Check if user is already logged in
if (VT_Auth::isLoggedIn()) {
	header('Location: /dashboard');
	exit;
}

// Handle form submissions
$action = $_GET['action'] ?? 'login';
$errors = array();
$messages = array();

// Handle registration
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vt_register_nonce'])) {
	if (VT_Security::verifyNonce($_POST['vt_register_nonce'], 'vt_register')) {
		$username = VT_Sanitizer::sanitize_username($_POST['username']);
		$email = VT_Sanitizer::sanitize_email($_POST['email']);
		$password = $_POST['password'];
		$confirm_password = $_POST['confirm_password'];
		$display_name = VT_Sanitizer::sanitize_text_field($_POST['display_name']);

		// Validation
		if (empty($username) || empty($email) || empty($password) || empty($display_name)) {
			$errors[] = 'All fields are required.';
		}

		if (!VT_Validator::is_email($email)) {
			$errors[] = 'Please enter a valid email address.';
		}

		if (VT_Auth::username_exists($username)) {
			$errors[] = 'Username already exists.';
		}

		if (VT_Auth::email_exists($email)) {
			$errors[] = 'Email address is already registered.';
		}

		if (strlen($password) < 8) {
			$errors[] = 'Password must be at least 8 characters long.';
		}

		if ($password !== $confirm_password) {
			$errors[] = 'Passwords do not match.';
		}

		// Create user if no errors
		if (empty($errors)) {
			$user_id = VT_Auth::create_user($username, $password, $email, $display_name);

			if ($user_id && !is_wp_error($user_id)) {
				// Create profile
				VT_Profile_Manager::create_default_profile($user_id);

				// Auto-login the user
				VT_Auth::login_user($user_id);

				// Redirect to profile setup
				header('Location: /profile?setup=1');
				exit;
			} else {
				$errors[] = 'Error creating account. Please try again.';
			}
		}
	}
}

// Handle login
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vt_login_nonce'])) {
	if (VT_Security::verifyNonce($_POST['vt_login_nonce'], 'vt_login')) {
		$username = VT_Sanitizer::sanitize_username($_POST['username']);
		$password = $_POST['password'];
		$remember = isset($_POST['remember']);

		if (empty($username) || empty($password)) {
			$errors[] = 'Username and password are required.';
		} else {
			$user = VT_Auth::authenticate($username, $password, $remember);

			if ($user && !is_wp_error($user)) {
				$redirect_to = $_GET['redirect_to'] ?? '/dashboard';
				header('Location: ' . $redirect_to);
				exit;
			} else {
				$errors[] = 'Invalid username or password.';
			}
		}
	}
}

// Set up template variables
$page_title = $action === 'register' ? 'Welcome to VivalaTable' : 'Welcome Back';
$page_description = $action === 'register'
	? 'Create your account to start hosting amazing events and connecting with your community.'
	: 'Sign in to manage your events, join conversations, and connect with fellow party enthusiasts.';

$breadcrumbs = array(
	array(
		'title' => 'Dashboard',
		'url' => '/dashboard'
	),
	array('title' => $action === 'register' ? 'Register' : 'Login')
);
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

<?php if ($action === 'register') : ?>
<!-- Registration Form -->
<div class="vt-section">
	<h2 class="vt-heading vt-heading-md vt-mb-4">Create Account</h2>

	<form method="post" class="vt-form">
		<?php echo VT_Security::nonce_field('vt_register', 'vt_register_nonce'); ?>

		<div class="vt-form-group">
			<label for="display_name" class="vt-form-label">Your Name</label>
			<input type="text" id="display_name" name="display_name" class="vt-form-input"
					value="<?php echo htmlspecialchars($_POST['display_name'] ?? ''); ?>"
					placeholder="How should we address you?" required>
		</div>

		<div class="vt-form-group">
			<label for="username" class="vt-form-label">Username</label>
			<input type="text" id="username" name="username" class="vt-form-input"
					value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
					placeholder="Choose a unique username" required>
		</div>

		<div class="vt-form-group">
			<label for="email" class="vt-form-label">Email Address</label>
			<input type="email" id="email" name="email" class="vt-form-input"
					value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
					placeholder="your@email.com" required>
		</div>

		<div class="vt-form-row">
			<div class="vt-form-group">
				<label for="password" class="vt-form-label">Password</label>
				<input type="password" id="password" name="password" class="vt-form-input"
						placeholder="At least 8 characters" required>
			</div>

			<div class="vt-form-group">
				<label for="confirm_password" class="vt-form-label">Confirm Password</label>
				<div>
					<input type="password" id="confirm_password" name="confirm_password" class="vt-form-input"
							placeholder="Repeat your password" required>
					<div id="password-match-indicator" style="display: none;">
						<span></span>
						<span></span>
					</div>
				</div>
			</div>
		</div>

		<div class="vt-text-center">
			<button type="submit" class="vt-btn vt-btn-lg">
				<span></span>
				Create Account & Setup Profile
			</button>
		</div>
	</form>

	<div class="vt-text-center vt-mt-4">
		<p class="vt-text-muted">Already have an account?
			<a href="<?php echo htmlspecialchars(remove_query_arg('action')); ?>" class="vt-text-primary">Sign In</a>
		</p>
	</div>
</div>

<?php else : ?>
<!-- Login Form -->
<div class="vt-section">
	<h2 class="vt-heading vt-heading-md vt-mb-4">Sign In</h2>

	<form method="post" class="vt-form">
		<?php echo VT_Security::nonce_field('vt_login', 'vt_login_nonce'); ?>

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
				<span>🚪</span>
				Sign In
			</button>
		</div>
	</form>

	<div class="vt-text-center vt-mt-4">
		<p class="vt-text-muted vt-mb-4">New to VivalaTable?
			<a href="<?php echo htmlspecialchars(add_query_arg('action', 'register')); ?>" class="vt-text-primary">Create Account</a>
		</p>
		<p><a href="/reset-password" class="vt-text-primary">Forgot your password?</a></p>
	</div>
</div>
<?php endif; ?>

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
	// Form validation and enhancements
	const forms = document.querySelectorAll('.vt-form');

	forms.forEach(form => {
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
	});

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