<?php
/**
 * VivalaTable Create Conversation Content Template
 * Form to start a new discussion topic
 * Ported from PartyMinder WordPress plugin
 */

// Require authentication
if (!vt_service('auth.service')->isLoggedIn()) {
	VT_Router::redirect('/login');
	exit;
}

$current_user = vt_service('auth.service')->getCurrentUser();

// Get optional community or event context
$community_id = intval($_GET['community_id'] ?? 0);
$event_id = intval($_GET['event_id'] ?? 0);

// Load managers
$conversation_manager = new VT_Conversation_Manager();
$community_manager = new VT_Community_Manager();
$event_manager = new VT_Event_Manager();

// Get context data
$community = null;
$event = null;
$user_communities = $community_manager->getUserCommunities($current_user->id);

if ($community_id) {
	$community = $community_manager->getCommunity($community_id);
}
if ($event_id) {
	$event = $event_manager->getEvent($event_id);
}

// Handle form submission
$errors = array();
$messages = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && vt_service('security.service')->verifyNonce($_POST['create_conversation_nonce'], 'vt_create_conversation')) {
	$conversation_data = array(
		'title' => vt_service('validation.validator')->textField($_POST['title'] ?? ''),
		'content' => vt_service('validation.sanitizer')->richText($_POST['content'] ?? ''),
		'community_id' => vt_service('validation.validator')->integer($_POST['community_id'] ?? 0),
		'event_id' => vt_service('validation.validator')->integer($_POST['event_id'] ?? 0),
		'privacy' => vt_service('validation.validator')->textField($_POST['privacy'] ?? 'public'),
		'author_id' => $current_user->id,
		'author_name' => $current_user->display_name ?: $current_user->username,
		'author_email' => $current_user->email
	);

	// Basic validation
	if (empty($conversation_data['title'])) {
		$errors[] = 'Conversation title is required.';
	}
	if (empty($conversation_data['content'])) {
		$errors[] = 'Conversation content is required.';
	}

	// If no validation errors, create the conversation
	if (empty($errors)) {
		$conversation_id = $conversation_manager->createConversation($conversation_data);
		if ($conversation_id) {
			$conversation = $conversation_manager->getConversation($conversation_id);
			VT_Router::redirect('/conversations/' . $conversation->slug);
			exit;
		} else {
			$errors[] = 'Failed to create conversation. Please try again.';
		}
	}
}

// Set up template variables
$page_title = 'Start a Conversation';
$page_description = 'Share your thoughts and start a discussion';
?>

<!-- Error Messages -->
<?php if (!empty($errors)) : ?>
	<div class="vt-alert vt-alert-error vt-mb-4">
		<?php foreach ($errors as $error) : ?>
			<p><?php echo htmlspecialchars($error); ?></p>
		<?php endforeach; ?>
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
			<label for="community_id" class="vt-form-label">Community (Optional)</label>
			<select id="community_id" name="community_id" class="vt-form-input">
				<option value="0">General Discussion</option>
				<?php foreach ($user_communities as $user_community) : ?>
					<option value="<?php echo $user_community->id; ?>"
							<?php echo ($user_community->id == $community_id) ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($user_community->name); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<small class="vt-form-help">
				Choose a community to limit discussion to community members only.
			</small>
		</div>

		<?php if ($event_id && $event) : ?>
			<input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
		<?php endif; ?>

		<div class="vt-form-group">
			<label for="privacy" class="vt-form-label">Privacy</label>
			<select id="privacy" name="privacy" class="vt-form-input">
				<option value="public">Public - Anyone can participate</option>
				<option value="members">Members Only - Limited to community members</option>
			</select>
		</div>

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
	const communitySelect = document.getElementById('community_id');
	const privacySelect = document.getElementById('privacy');

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
});
</script>