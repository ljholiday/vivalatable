<?php
/**
 * VivalaTable Guest RSVP Template
 * Anonymous RSVP form for guests with tokens
 * Ported from PartyMinder WordPress plugin - maintains 32-character token system
 */

// Get RSVP token from route parameter or URL
$rsvp_token = $token ?? $_GET['token'] ?? '';
$quick_response = $_GET['response'] ?? '';

if (empty($rsvp_token) || strlen($rsvp_token) !== 64) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Invalid RSVP Link</h3>
		<p class="vt-text-muted vt-mb-4">This RSVP link is invalid or has expired.</p>
		<a href="/events" class="vt-btn">Browse Events</a>
	</div>
	<?php
	return;
}

// Load guest manager and get guest information
$guest_manager = new VT_Guest_Manager();
$guest = $guest_manager->getGuestByToken($rsvp_token);

if (!$guest) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">RSVP Not Found</h3>
		<p class="vt-text-muted vt-mb-4">This RSVP invitation could not be found.</p>
		<a href="/events" class="vt-btn">Browse Events</a>
	</div>
	<?php
	return;
}

// Handle quick response from email links
if ($quick_response && in_array($quick_response, array('yes', 'no', 'maybe'))) {
	// Pre-populate form if they haven't RSVPed yet
	if ($guest->status === 'pending') {
		$_GET['pre_select'] = $quick_response;
	}
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	if ($_POST['action'] === 'submit_rsvp' && vt_service('security.service')->verifyNonce($_POST['nonce'], 'vt_guest_rsvp')) {
		$rsvp_status = $_POST['rsvp_status'] ?? '';

		$guest_data = array(
			'name' => $_POST['guest_name'] ?? '',
			'phone' => $_POST['guest_phone'] ?? '',
			'dietary_restrictions' => $_POST['dietary_restrictions'] ?? '',
			'plus_one' => intval($_POST['plus_one'] ?? 0),
			'plus_one_name' => $_POST['plus_one_name'] ?? '',
			'notes' => $_POST['guest_notes'] ?? ''
		);

		$result = $guest_manager->processAnonymousRsvp($rsvp_token, $rsvp_status, $guest_data);

		if (is_vt_error($result)) {
			$error_message = $result->getErrorMessage();
		} else {
			$success_message = 'Thank you for your RSVP! A confirmation email has been sent.';
			// Refresh guest data
			$guest = $guest_manager->getGuestByToken($rsvp_token);
		}
	}

	// Handle guest-to-user conversion
	if ($_POST['action'] === 'create_account' && vt_service('security.service')->verifyNonce($_POST['nonce'], 'vt_guest_conversion')) {
		$user_data = array(
			'username' => vt_service('validation.validator')->textField($_POST['username']),
			'password' => $_POST['password'],
			'display_name' => vt_service('validation.validator')->textField($_POST['display_name'] ?? $guest->name)
		);

		$user_result = $guest_manager->convertGuestToUser($guest->id, $user_data);

		if (is_vt_error($user_result)) {
			$error_message = $user_result->getErrorMessage();
		} else {
			// Redirect to event page as logged-in user
			VT_Router::redirect('/events/' . $guest->event_slug . '?converted=1');
			exit;
		}
	}
}

// Set up template variables
$page_title = sprintf('RSVP: %s', htmlspecialchars($guest->event_title));
$page_description = 'Please confirm your attendance for this event';
$event_date_formatted = date('l, F j, Y', strtotime($guest->event_date));
$event_time_formatted = $guest->event_time ? date('g:i A', strtotime($guest->event_time)) : '';
$pre_select = $_GET['pre_select'] ?? '';
?>

<!-- Success/Error Messages -->
<?php if ($success_message) : ?>
	<div class="vt-alert vt-alert-success vt-mb-4">
		<?php echo htmlspecialchars($success_message); ?>
	</div>
<?php endif; ?>

<?php if ($error_message) : ?>
	<div class="vt-alert vt-alert-error vt-mb-4">
		<?php echo htmlspecialchars($error_message); ?>
	</div>
<?php endif; ?>

<!-- Event Information -->
<div class="vt-section vt-mb-6">
	<div class="vt-card">
		<?php if ($guest->featured_image): ?>
		<div class="vt-card-image">
			<img src="<?php echo htmlspecialchars($guest->featured_image); ?>"
				 alt="<?php echo htmlspecialchars($guest->event_title); ?>"
				 class="vt-card-image-img">
		</div>
		<?php endif; ?>

		<div class="vt-card-header">
			<h1 class="vt-heading vt-heading-lg vt-text-primary"><?php echo htmlspecialchars($guest->event_title); ?></h1>
		</div>

		<div class="vt-card-body">
			<div class="vt-event-meta vt-mb-4">
				<div class="vt-flex vt-gap-4 vt-mb-2">
					<div class="vt-flex vt-items-center vt-gap-2">
						<strong><?php echo $event_date_formatted; ?></strong>
					</div>
					<?php if ($event_time_formatted): ?>
					<div class="vt-flex vt-items-center vt-gap-2">
						<span><?php echo $event_time_formatted; ?></span>
					</div>
					<?php endif; ?>
				</div>

				<?php if ($guest->venue_info): ?>
				<div class="vt-flex vt-items-center vt-gap-2 vt-mb-2">
					<span><?php echo htmlspecialchars($guest->venue_info); ?></span>
				</div>
				<?php endif; ?>
			</div>

			<?php if ($guest->description): ?>
			<div class="vt-event-description vt-mb-4">
				<p><?php echo nl2br(htmlspecialchars($guest->description)); ?></p>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php if ($guest->status === 'pending' || empty($success_message)): ?>
<!-- RSVP Form -->
<div class="vt-section">
	<div class="vt-section-header">
		<h2 class="vt-heading vt-heading-md vt-text-primary">Please Respond</h2>
		<p class="vt-text-muted">Let us know if you can make it!</p>
	</div>

	<form method="post" class="vt-form" id="rsvp-form">
		<input type="hidden" name="action" value="submit_rsvp">
		<input type="hidden" name="nonce" value="<?php echo vt_service('security.service')->createNonce('vt_guest_rsvp'); ?>">

		<!-- RSVP Status Selection -->
		<div class="vt-form-group">
			<label class="vt-form-label">Will you be attending?</label>
			<div class="vt-flex vt-gap-4 vt-flex-wrap">
				<label class="vt-flex-1">
					<input type="radio" name="rsvp_status" value="yes"
						   <?php echo ($pre_select === 'yes' || $guest->status === 'yes') ? 'checked' : ''; ?> required>
					<span class="vt-btn vt-btn-lg">Yes, I'll be there</span>
				</label>
				<label class="vt-flex-1">
					<input type="radio" name="rsvp_status" value="maybe"
						   <?php echo ($pre_select === 'maybe' || $guest->status === 'maybe') ? 'checked' : ''; ?> required>
					<span class="vt-btn vt-btn-lg vt-btn-secondary">Maybe</span>
				</label>
				<label class="vt-flex-1">
					<input type="radio" name="rsvp_status" value="no"
						   <?php echo ($pre_select === 'no' || $guest->status === 'no') ? 'checked' : ''; ?> required>
					<span class="vt-btn vt-btn-lg vt-btn-danger">Can't make it</span>
				</label>
			</div>
		</div>

		<!-- Guest Details (shown when "Yes" or "Maybe" selected) -->
		<div class="vt-guest-details vt-mt-4" style="display: none; border-top: 1px solid var(--vt-border); padding-top: 1rem;">
			<div class="vt-form-group">
				<label class="vt-form-label" for="guest_name">
					Your Name *
				</label>
				<input type="text" id="guest_name" name="guest_name" class="vt-form-input"
					   value="<?php echo htmlspecialchars($guest->name ?? ''); ?>" required>
			</div>

			<div class="vt-form-group">
				<label class="vt-form-label" for="guest_phone">
					Phone Number (Optional)
				</label>
				<input type="tel" id="guest_phone" name="guest_phone" class="vt-form-input"
					   value="<?php echo htmlspecialchars($guest->phone ?? ''); ?>">
			</div>

			<div class="vt-form-group">
				<label class="vt-form-label" for="dietary_restrictions">
					Dietary Restrictions or Allergies (Optional)
				</label>
				<input type="text" id="dietary_restrictions" name="dietary_restrictions" class="vt-form-input"
					   value="<?php echo htmlspecialchars($guest->dietary_restrictions ?? ''); ?>"
					   placeholder="e.g., Vegetarian, Gluten-free, Nut allergy">
			</div>

			<!-- Plus One Section -->
			<div class="vt-form-group">
				<label class="vt-form-label">
					<input type="checkbox" name="plus_one" value="1" id="plus_one_checkbox"
						   <?php echo ($guest->plus_one > 0) ? 'checked' : ''; ?>>
					I'm bringing a plus one
				</label>

				<div class="vt-plus-one-details vt-mt-2" style="<?php echo ($guest->plus_one > 0) ? '' : 'display: none;'; ?> margin-left: 1.5rem;">
					<input type="text" name="plus_one_name" class="vt-form-input"
						   value="<?php echo htmlspecialchars($guest->plus_one_name ?? ''); ?>"
						   placeholder="Plus one's name (optional)">
				</div>
			</div>

			<div class="vt-form-group">
				<label class="vt-form-label" for="guest_notes">
					Special Requests or Comments (Optional)
				</label>
				<textarea id="guest_notes" name="guest_notes" class="vt-form-textarea" rows="3"
						  placeholder="Any special requests or comments for the host..."><?php echo htmlspecialchars($guest->notes ?? ''); ?></textarea>
			</div>
		</div>

		<button type="submit" class="vt-btn vt-btn-primary vt-btn-lg">
			Submit RSVP
		</button>
	</form>
</div>

<?php else: ?>

<!-- RSVP Confirmation -->
<div class="vt-section">
	<div class="vt-card <?php echo $guest->status === 'yes' ? 'vt-card-success' : ($guest->status === 'no' ? 'vt-card-error' : 'vt-card-info'); ?>">
		<div class="vt-card-header">
			<h2 class="vt-heading vt-heading-md">RSVP Confirmed</h2>
		</div>
		<div class="vt-card-body">
			<p class="vt-mb-4">
				<strong>Your Response:</strong>
				<span class="vt-rsvp-status vt-rsvp-status-<?php echo $guest->status; ?>">
					<?php
					$status_labels = array('yes' => 'Yes, I\'ll be there!', 'no' => 'Can\'t make it', 'maybe' => 'Maybe');
					echo $status_labels[$guest->status] ?? ucfirst($guest->status);
					?>
				</span>
			</p>

			<?php if ($guest->status === 'yes'): ?>
			<div class="vt-guest-info">
				<?php if ($guest->name): ?>
				<p><strong>Name:</strong> <?php echo htmlspecialchars($guest->name); ?></p>
				<?php endif; ?>

				<?php if ($guest->plus_one > 0): ?>
				<p><strong>Plus One:</strong> <?php echo htmlspecialchars($guest->plus_one_name ?: 'Yes'); ?></p>
				<?php endif; ?>

				<?php if ($guest->dietary_restrictions): ?>
				<p><strong>Dietary Restrictions:</strong> <?php echo htmlspecialchars($guest->dietary_restrictions); ?></p>
				<?php endif; ?>

				<?php if ($guest->notes): ?>
				<p><strong>Notes:</strong> <?php echo htmlspecialchars($guest->notes); ?></p>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<div class="vt-mt-4">
				<a href="/events/<?php echo htmlspecialchars($guest->event_slug); ?>" class="vt-btn vt-btn-primary">
					View Event Details
				</a>
			</div>
		</div>
	</div>

	<!-- Account Creation Option -->
	<?php if (!$guest->converted_user_id && $guest->status === 'yes'): ?>
	<div class="vt-section vt-mt-6">
		<div class="vt-card">
			<div class="vt-card-header">
				<h3 class="vt-heading vt-heading-sm">Create an Account</h3>
			</div>
			<div class="vt-card-body">
				<p class="vt-mb-4">
					Create a VivalaTable account to easily manage your RSVPs, host your own events, and stay connected with the community.
				</p>

				<form method="post" class="vt-form" id="account-creation-form">
					<input type="hidden" name="action" value="create_account">
					<input type="hidden" name="nonce" value="<?php echo vt_service('security.service')->createNonce('vt_guest_conversion'); ?>">

					<div class="vt-form-group">
						<label class="vt-form-label" for="username">
							Username *
						</label>
						<input type="text" id="username" name="username" class="vt-form-input" required
							   placeholder="Choose a username">
					</div>

					<div class="vt-form-group">
						<label class="vt-form-label" for="display_name">
							Display Name
						</label>
						<input type="text" id="display_name" name="display_name" class="vt-form-input"
							   value="<?php echo htmlspecialchars($guest->name); ?>"
							   placeholder="How you'd like to be shown to others">
					</div>

					<div class="vt-form-group">
						<label class="vt-form-label" for="password">
							Password *
						</label>
						<input type="password" id="password" name="password" class="vt-form-input" required
							   placeholder="Choose a secure password">
					</div>

					<button type="submit" class="vt-btn vt-btn-primary">
						Create Account
					</button>
				</form>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const rsvpForm = document.getElementById('rsvp-form');
	const rsvpOptions = document.querySelectorAll('input[name="rsvp_status"]');
	const guestDetails = document.querySelector('.vt-guest-details');
	const plusOneCheckbox = document.getElementById('plus_one_checkbox');
	const plusOneDetails = document.querySelector('.vt-plus-one-details');

	// Show/hide guest details based on RSVP selection
	function toggleGuestDetails() {
		const selectedStatus = document.querySelector('input[name="rsvp_status"]:checked');
		if (selectedStatus && (selectedStatus.value === 'yes' || selectedStatus.value === 'maybe')) {
			guestDetails.style.display = 'block';
		} else {
			guestDetails.style.display = 'none';
		}
	}

	// Show/hide plus one details
	function togglePlusOneDetails() {
		if (plusOneCheckbox && plusOneDetails) {
			plusOneDetails.style.display = plusOneCheckbox.checked ? 'block' : 'none';
		}
	}

	// Event listeners
	rsvpOptions.forEach(option => {
		option.addEventListener('change', toggleGuestDetails);
	});

	if (plusOneCheckbox) {
		plusOneCheckbox.addEventListener('change', togglePlusOneDetails);
	}

	// Initialize visibility
	toggleGuestDetails();
	togglePlusOneDetails();

	// Pre-select quick response if provided
	<?php if ($pre_select): ?>
	toggleGuestDetails();
	<?php endif; ?>
});
</script>