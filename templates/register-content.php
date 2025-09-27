<?php
/**
 * VivalaTable Registration Content Template
 * User registration form
 * Ported from PartyMinder WordPress plugin
 */

// Check if user is already logged in
if (VT_Auth::isLoggedIn()) {
	header('Location: /');
	exit;
}

// Handle guest token conversion
$guest_token = $_GET['guest_token'] ?? '';
$guest_data = null;
if ($guest_token) {
	$guest_manager = new VT_Guest_Manager();
	$guest_data = $guest_manager->getGuestByToken($guest_token);
}

// Handle form submissions
$errors = array();
$messages = array();

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$username = $_POST['username'] ?? '';
		$email = $_POST['email'] ?? '';
		$password = $_POST['password'] ?? '';
		$confirm_password = $_POST['confirm_password'] ?? '';
		$display_name = $_POST['display_name'] ?? '';
		$guest_token = $_POST['guest_token'] ?? $guest_token;

		// Basic validation
		if (empty($username) || empty($email) || empty($password) || empty($display_name)) {
			$errors[] = 'All fields are required.';
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$errors[] = 'Please enter a valid email address.';
		}

		if (strlen($password) < 8) {
			$errors[] = 'Password must be at least 8 characters long.';
		}

		if ($password !== $confirm_password) {
			$errors[] = 'Passwords do not match.';
		}

		// If no validation errors, attempt registration
		if (empty($errors)) {
			$user_id = VT_Auth::register($username, $email, $password, $display_name);

			if ($user_id) {
				// Handle guest token conversion if applicable
				if ($guest_token && $guest_data) {
					$guest_manager = new VT_Guest_Manager();
					$conversion_result = $guest_manager->convertGuestToUser($guest_data->id, [
						'user_id' => $user_id,
						'username' => $username,
						'password' => $password
					]);
				}

				$messages[] = 'Account created successfully! You can now log in.';
				// Redirect to login page after successful registration
				header('Location: /login?registered=1');
				exit;
			} else {
				$errors[] = 'Registration failed. Username or email may already exist.';
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

<!-- Registration Form -->
<div class="vt-section">
	<h2 class="vt-heading vt-heading-md vt-mb-4">Create Account</h2>

	<form method="post" class="vt-form">
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
		</div>

		<div class="vt-form-group">
			<button type="submit" class="vt-btn vt-btn-lg" style="width: 100%;">Create Account</button>
		</div>
	</form>

	<div class="vt-text-center vt-mt-4">
		<p>Already have an account? <a href="/login" class="vt-text-primary">Sign in here</a></p>
	</div>
</div>