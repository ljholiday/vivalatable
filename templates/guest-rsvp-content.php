<?php
/**
 * VivalaTable Guest RSVP Content Template
 * Dedicated RSVP page for invited guests (works without login)
 * Ported from PartyMinder WordPress plugin
 */

// Get invitation token from URL
$invitation_token = $_GET['invitation'] ?? '';
$event_id = intval($_GET['event'] ?? 0);
$quick_rsvp = $_GET['quick_rsvp'] ?? '';

if (!$invitation_token || !$event_id) {
	header('Location: /events');
	exit;
}

// Verify invitation token and get event data
$guest_manager = new VT_Guest_Manager();
$guest = $guest_manager->getGuestByToken($invitation_token);

if (!$guest || $guest->event_id != $event_id) {
	$page_title = 'Invitation Not Found';
	$page_description = 'This invitation link is invalid or has expired.';
	$breadcrumbs = array();
	?>
	<div class="vt-text-center vt-p-4">
		<h2 class="vt-heading vt-heading-lg vt-text-primary vt-mb">Invitation Not Found</h2>
		<p class="vt-text-muted vt-mb">This invitation link is invalid or has expired.</p>
		<a href="/events" class="vt-btn">Browse Events</a>
	</div>
	<?php
	return;
}

// Get event data
$event_manager = new VT_Event_Manager();
$event = $event_manager->getEvent($event_id);

if (!$event) {
	header('Location: /events');
	exit;
}

// Handle quick RSVP from email button click
if ($quick_rsvp && in_array($quick_rsvp, array('confirmed', 'maybe', 'declined'))) {
	$result = $guest_manager->processAnonymousRsvp($invitation_token, $quick_rsvp, [
		'name' => $guest->name ?: 'Guest'
	]);

	if ($result['success']) {
		// Send confirmation email
		VT_Mail::sendRSVPConfirmation($guest->email, $event->title, $event->event_date, $quick_rsvp);

		// Redirect to show success
		$redirect_url = '/guest-rsvp?' . http_build_query([
			'invitation' => $invitation_token,
			'event' => $event_id,
			'quick_response' => $quick_rsvp
		]);
		header('Location: ' . $redirect_url);
		exit;
	}
}

// Check for quick response confirmation
$quick_response = $_GET['quick_response'] ?? '';

// Handle RSVP form submission
$rsvp_submitted = false;
$rsvp_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rsvp_response']) && VT_Security::verifyNonce($_POST['rsvp_nonce'], 'vt_guest_rsvp')) {
	$rsvp_response = VT_Sanitizer::sanitizeTextField($_POST['rsvp_response']);
	$guest_name = VT_Sanitizer::sanitizeTextField($_POST['guest_name']);
	$dietary_restrictions = VT_Sanitizer::sanitizeTextarea($_POST['dietary_restrictions']);
	$plus_one = isset($_POST['plus_one']) ? 1 : 0;
	$plus_one_name = $plus_one ? VT_Sanitizer::sanitizeTextField($_POST['plus_one_name']) : '';
	$guest_notes = VT_Sanitizer::sanitizeTextarea($_POST['guest_notes']);

	$guest_data = [
		'name' => $guest_name,
		'dietary_restrictions' => $dietary_restrictions,
		'plus_one' => $plus_one,
		'plus_one_name' => $plus_one_name,
		'notes' => $guest_notes
	];

	$result = $guest_manager->processAnonymousRsvp($invitation_token, $rsvp_response, $guest_data);

	if ($result['success']) {
		$rsvp_submitted = true;
		$rsvp_status = $rsvp_response;

		// Send confirmation email
		VT_Mail::sendRSVPConfirmation($guest->email, $event->title, $event->event_date, $rsvp_response);
	}
}

// Set up template variables
$is_success_state = $rsvp_submitted || $quick_response;
$display_status = $rsvp_submitted ? $rsvp_status : $quick_response;

$page_title = $is_success_state
	? 'RSVP Submitted!'
	: sprintf('RSVP: %s', $event->title);

$page_description = $is_success_state
	? 'Thank you for your response. The host has been notified.'
	: 'Please let us know if you can attend this event';

$breadcrumbs = array();

// Format event date
$event_day = date('l', strtotime($event->event_date));
$event_date_formatted = date('F j, Y', strtotime($event->event_date));
$event_time_formatted = date('g:i A', strtotime($event->event_date));
?>

<?php if ($is_success_state) : ?>
	<!-- Success State -->
	<div class="vt-section vt-text-center">
		<div class="vt-mb-4">
			<?php if ($display_status === 'confirmed') : ?>
				<h2 class="vt-heading vt-heading-lg vt-text-primary vt-mb">Great! See you there!</h2>
				<p class="vt-text-muted">Your RSVP has been confirmed. The host has been notified that you'll be attending.</p>
			<?php elseif ($display_status === 'maybe') : ?>
				<h2 class="vt-heading vt-heading-lg vt-text-primary vt-mb">Thanks for letting us know!</h2>
				<p class="vt-text-muted">Your maybe response has been recorded. The host has been notified.</p>
			<?php else : ?>
				<h2 class="vt-heading vt-heading-lg vt-text-primary vt-mb">Sorry you can't make it!</h2>
				<p class="vt-text-muted">Your response has been recorded. The host has been notified.</p>
			<?php endif; ?>
		</div>

		<!-- Signup Prompt - Critical missing piece from docs -->
		<div class="vt-card vt-mb-4 signup-prompt">
			<div class="vt-card-body vt-text-center">
				<h3 class="vt-heading vt-heading-md vt-mb-2">Want to create more events like this?</h3>
				<p class="vt-text-muted vt-mb-4">Create a free VivalaTable account to host your own events and join communities.</p>

				<div class="vt-flex vt-gap vt-justify-center vt-flex-wrap">
					<a href="/register?guest_token=<?php echo urlencode($invitation_token); ?>&email=<?php echo urlencode($guest->email); ?>&name=<?php echo urlencode($guest->name); ?>" class="vt-btn vt-btn-primary">
						Create Free Account
					</a>
					<button type="button" class="vt-btn vt-btn-secondary" onclick="this.closest('.signup-prompt').style.display='none'">
						Maybe Later
					</button>
				</div>
			</div>
		</div>

		<div class="vt-flex vt-gap vt-justify-center vt-flex-wrap">
			<a href="/events/<?php echo htmlspecialchars($event->slug); ?>" class="vt-btn">
				View Event Details
			</a>
			<a href="/events" class="vt-btn">
				Browse Other Events
			</a>
		</div>
	</div>

<?php else : ?>
	<!-- RSVP Form -->
	<div class="vt-section vt-mb">
		<div class="vt-section-header">
			<h2 class="vt-heading vt-heading-lg vt-text-primary"><?php echo htmlspecialchars($event->title); ?></h2>
			<p class="vt-text-muted">You're invited to this event</p>
		</div>

		<!-- Event Details -->
		<div class="vt-card vt-mb-4">
			<div class="vt-card-body">
				<div class="vt-flex vt-flex-column vt-gap">
					<div class="vt-flex vt-gap">
						<div>
							<strong>When:</strong>
							<div><?php echo $event_day . ', ' . $event_date_formatted . ' at ' . $event_time_formatted; ?></div>
						</div>
					</div>
					<?php if ($event->venue_info) : ?>
					<div class="vt-flex vt-gap">
						<div>
							<strong>Where:</strong>
							<div><?php echo htmlspecialchars($event->venue_info); ?></div>
						</div>
					</div>
					<?php endif; ?>
					<?php if ($event->description) : ?>
					<div class="vt-flex vt-gap">
						<div>
							<strong>Details:</strong>
							<div><?php echo nl2br(htmlspecialchars($event->description)); ?></div>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php if ($guest->status && $guest->status !== 'pending') : ?>
		<div class="vt-alert vt-alert-info vt-mb-4">
			<h4 class="vt-heading vt-heading-sm">You've already responded</h4>
			<p>Your current response: <strong><?php echo ucfirst($guest->status); ?></strong>. You can update it below if needed.</p>
		</div>
		<?php endif; ?>
	</div>

	<!-- RSVP Form -->
	<div class="vt-section">
		<div class="vt-section-header">
			<h3 class="vt-heading vt-heading-md">Please Respond</h3>
		</div>

		<form method="post" class="vt-form">
			<?php echo VT_Security::nonceField('vt_guest_rsvp', 'rsvp_nonce'); ?>

			<div class="vt-form-group">
				<label class="vt-form-label">Your Name *</label>
				<input type="text" name="guest_name" class="vt-form-input"
						value="<?php echo htmlspecialchars($guest->name ?? ''); ?>" required>
			</div>

			<div class="vt-form-group">
				<label class="vt-form-label">Will you attend? *</label>
				<div class="vt-form-radio-group">
					<label class="vt-form-radio">
						<input type="radio" name="rsvp_response" value="confirmed"
								<?php echo ($guest->status === 'confirmed') ? 'checked' : ''; ?> required>
						<span>Yes, I'll be there!</span>
					</label>
					<label class="vt-form-radio">
						<input type="radio" name="rsvp_response" value="maybe"
								<?php echo ($guest->status === 'maybe') ? 'checked' : ''; ?> required>
						<span>Maybe, I'm not sure yet</span>
					</label>
					<label class="vt-form-radio">
						<input type="radio" name="rsvp_response" value="declined"
								<?php echo ($guest->status === 'declined') ? 'checked' : ''; ?> required>
						<span>Sorry, I can't make it</span>
					</label>
				</div>
			</div>

			<div class="vt-form-group">
				<label class="vt-form-label">
					<input type="checkbox" name="plus_one" value="1"
							<?php echo ($guest->plus_one_name) ? 'checked' : ''; ?>>
					I'm bringing a plus one
				</label>
				<input type="text" name="plus_one_name" class="vt-form-input vt-mt-2"
						placeholder="Plus one name (optional)"
						value="<?php echo htmlspecialchars($guest->plus_one_name ?? ''); ?>">
			</div>

			<div class="vt-form-group">
				<label class="vt-form-label">Dietary Restrictions / Allergies</label>
				<textarea name="dietary_restrictions" class="vt-form-textarea" rows="2"
							placeholder="Let the host know about any dietary needs..."><?php echo htmlspecialchars($guest->dietary_restrictions ?? ''); ?></textarea>
			</div>

			<div class="vt-form-group">
				<label class="vt-form-label">Message to Host</label>
				<textarea name="guest_notes" class="vt-form-textarea" rows="3"
							placeholder="Any questions or notes for the host..."><?php echo htmlspecialchars($guest->notes ?? ''); ?></textarea>
			</div>

			<div class="vt-form-actions">
				<button type="submit" class="vt-btn vt-btn-lg">
					Submit RSVP
				</button>
				<a href="/events/<?php echo htmlspecialchars($event->slug); ?>" class="vt-btn vt-btn-lg">
					View Event Page
				</a>
			</div>
		</form>
	</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Quick RSVP button functionality
	const quickButtons = document.querySelectorAll('.quick-rsvp-btn');
	const form = document.querySelector('.vt-form');
	const guestNameInput = form ? form.querySelector('input[name="guest_name"]') : null;

	quickButtons.forEach(button => {
		button.addEventListener('click', function() {
			const response = this.dataset.response;

			if (!guestNameInput || !guestNameInput.value.trim()) {
				alert('Please enter your name first in the form below.');
				guestNameInput.focus();
				return;
			}

			// Set the radio button
			const radioButton = form.querySelector(`input[name="rsvp_response"][value="${response}"]`);
			if (radioButton) {
				radioButton.checked = true;

				// Auto-submit form
				if (confirm('Submit your RSVP now?')) {
					form.submit();
				}
			}
		});
	});
});
</script>