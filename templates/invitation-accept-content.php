<?php
/**
 * VivalaTable Invitation Acceptance Page
 * Handles accepting community invitations via token
 */

$invitation_token = $_GET['token'] ?? '';

if (empty($invitation_token)) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Invalid Invitation</h3>
		<p class="vt-text-muted vt-mb-4">No invitation token was provided.</p>
		<a href="/communities" class="vt-btn">Browse Communities</a>
	</div>
	<?php
	return;
}

// Get invitation details first (before checking login)
$is_logged_in = vt_service('auth.service')->isLoggedIn();
$db = VT_Database::getInstance();
$invitation = $db->getRow(
	$db->prepare(
		"SELECT i.*, c.name as community_name, c.slug as community_slug, c.description as community_description
		 FROM {$db->prefix}community_invitations i
		 LEFT JOIN {$db->prefix}communities c ON i.community_id = c.id
		 WHERE i.invitation_token = %s AND i.status = 'pending'",
		$invitation_token
	)
);

if (!$invitation) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Invitation Not Found</h3>
		<p class="vt-text-muted vt-mb-4">This invitation is invalid, has already been accepted, or has expired.</p>
		<a href="/communities" class="vt-btn">Browse Communities</a>
	</div>
	<?php
	return;
}

// Check if expired
if (strtotime($invitation->expires_at) < time()) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Invitation Expired</h3>
		<p class="vt-text-muted vt-mb-4">This invitation expired on <?php echo date('F j, Y', strtotime($invitation->expires_at)); ?>.</p>
		<p class="vt-text-muted vt-mb-4">Please contact the community administrator for a new invitation.</p>
		<a href="/communities" class="vt-btn">Browse Communities</a>
	</div>
	<?php
	return;
}

// Check if user's email matches (only if logged in)
$current_user = null;
$email_mismatch = false;

if ($is_logged_in) {
	$current_user = vt_service('auth.service')->getCurrentUser();
	if ($current_user->email !== $invitation->invited_email) {
		$email_mismatch = true;
	}
}

if ($email_mismatch) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Email Mismatch</h3>
		<p class="vt-text-muted vt-mb-4">This invitation was sent to <strong><?php echo htmlspecialchars($invitation->invited_email); ?></strong>.</p>
		<p class="vt-text-muted vt-mb-4">You are currently logged in as <strong><?php echo htmlspecialchars($current_user->email); ?></strong>.</p>
		<p class="vt-text-muted vt-mb-4">Please log in with the correct account to accept this invitation.</p>
		<a href="/logout?redirect=<?php echo urlencode('/invitation/accept?token=' . urlencode($invitation_token)); ?>" class="vt-btn">Log Out and Try Again</a>
	</div>
	<?php
	return;
}

// Handle form submission (only if logged in)
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_invitation'])) {
	$community_manager = new VT_Community_Manager();
	$result = $community_manager->acceptInvitation($invitation_token);

	if (is_vt_error($result)) {
		$codes = $result->getErrorCodes();
		$code_str = is_array($codes) ? implode(', ', $codes) : $codes;
		$error_message = 'Error: ' . $result->getErrorMessage() . ' (Code: ' . $code_str . ')';
	} else {
		// Success - redirect to community
		VT_Router::redirect('/communities/' . $invitation->community_slug . '?joined=1');
		exit;
	}
}

?>

<div class="vt-section">
	<div class="vt-container-narrow">
		<div class="vt-text-center vt-mb-6">
			<h2 class="vt-heading vt-heading-lg vt-text-primary vt-mb-2">You're Invited!</h2>
			<p class="vt-text-muted">You've been invited to join a community</p>
		</div>

		<?php if (isset($error_message)) : ?>
		<div class="vt-alert vt-alert-danger vt-mb-4">
			<?php echo htmlspecialchars($error_message); ?>
		</div>
		<?php endif; ?>

		<div class="vt-card">
			<div class="vt-card-body">
				<h3 class="vt-heading vt-heading-md vt-mb-4"><?php echo htmlspecialchars($invitation->community_name); ?></h3>

				<?php if ($invitation->community_description) : ?>
				<div class="vt-mb-4">
					<p class="vt-text-muted"><?php echo nl2br(htmlspecialchars($invitation->community_description)); ?></p>
				</div>
				<?php endif; ?>

				<?php if ($invitation->message) : ?>
				<div class="vt-mb-4">
					<p class="vt-text-sm vt-text-muted vt-mb-2"><strong>Personal message:</strong></p>
					<p class="vt-text-muted"><?php echo nl2br(htmlspecialchars($invitation->message)); ?></p>
				</div>
				<?php endif; ?>

				<div class="vt-mb-4">
					<p class="vt-text-sm vt-text-muted">
						This invitation expires on <?php echo date('F j, Y', strtotime($invitation->expires_at)); ?>.
					</p>
				</div>

				<?php if ($is_logged_in) : ?>
					<form method="POST" class="vt-flex vt-gap-2" data-custom-handler="true">
						<button type="submit" name="accept_invitation" class="vt-btn vt-btn-primary">
							Accept Invitation
						</button>
						<a href="/communities" class="vt-btn vt-btn-secondary">
							Decline
						</a>
					</form>
				<?php else : ?>
					<div class="vt-mb-4">
						<p class="vt-text-muted vt-mb-4">To accept this invitation, you need to log in or create an account.</p>
						<div class="vt-flex vt-gap-2">
							<a href="/login?redirect_to=<?php echo urlencode('/invitation/accept?token=' . urlencode($invitation_token)); ?>" class="vt-btn vt-btn-primary">
								Log In
							</a>
							<a href="/register?redirect_to=<?php echo urlencode('/invitation/accept?token=' . urlencode($invitation_token)); ?>&email=<?php echo urlencode($invitation->invited_email); ?>" class="vt-btn vt-btn-secondary">
								Create Account
							</a>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
