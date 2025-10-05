<?php
/**
 * VivalaTable Edit Conversation Content Template
 * Display-only template for conversation editing form
 * Form processing handled in VT_Pages::editConversationBySlug()
 */

// Prevent direct access
if (!defined('VT_VERSION')) {
    exit;
}

// Accept variables from controller
$errors = $errors ?? array();
$messages = $messages ?? array();
$conversation = $conversation ?? null;
$user_communities = $user_communities ?? array();

// Redirect if no conversation
if (!$conversation) {
	VT_Router::redirect('/conversations');
	return;
}
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

<!-- Edit Conversation Form -->
<div class="vt-section">
	<form method="post" class="vt-form">
		<?php echo vt_service('security.service')->nonceField('vt_edit_conversation', 'edit_conversation_nonce'); ?>

		<div class="vt-form-group">
			<label for="title" class="vt-form-label">Conversation Title</label>
			<input type="text" id="title" name="title" class="vt-form-input"
				   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : htmlspecialchars($conversation->title); ?>"
				   placeholder="What would you like to discuss?" required>
		</div>

		<div class="vt-form-group">
			<label for="content" class="vt-form-label">Content</label>
			<textarea id="content" name="content" class="vt-form-input vt-form-textarea"
					  rows="8" placeholder="Share your thoughts, ask a question, or start a discussion..."
					  required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : htmlspecialchars($conversation->content); ?></textarea>
		</div>

		<div class="vt-form-group">
			<label for="community_id" class="vt-form-label">Community</label>
			<select id="community_id" name="community_id" class="vt-form-input" required>
				<option value="">Select a community...</option>
				<?php foreach ($user_communities as $user_community) : ?>
					<option value="<?php echo $user_community->id; ?>"
							<?php
							$selected_community_id = isset($_POST['community_id']) ? $_POST['community_id'] : $conversation->community_id;
							echo ($user_community->id == $selected_community_id) ? 'selected' : '';
							?>>
						<?php echo htmlspecialchars($user_community->name); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<small class="vt-form-help">
				All conversations must be in a community.
			</small>
		</div>

		<div class="vt-form-actions">
			<button type="submit" class="vt-btn vt-btn-primary vt-btn-lg">
				Update Conversation
			</button>
			<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-btn vt-btn-secondary vt-btn-lg">
				Cancel
			</a>
		</div>
	</form>

	<!-- Danger Zone -->
	<?php
	$conversation_manager = new VT_Conversation_Manager();
	$current_user = vt_service('auth.service')->getCurrentUser();

	$entity_type = 'conversation';
	$entity_id = $conversation->id;
	$entity_name = $conversation->title;
	$can_delete = $conversation_manager->canDeleteConversation($conversation->id, $current_user->id);
	$confirmation_type = 'confirm';
	$delete_message = 'Once you delete this conversation, there is no going back. This action cannot be undone.';
	$nonce_action = 'vt_delete_conversation';

	include VT_INCLUDES_DIR . '/../templates/partials/danger-zone.php';
	?>
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
