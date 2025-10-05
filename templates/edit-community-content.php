<?php
/**
 * VivalaTable Edit Community Content Template
 * Display-only template for editing communities
 * Form processing handled in VT_Pages::editCommunityBySlug()
 */

// Accept variables from controller
$errors = $errors ?? array();
$messages = $messages ?? array();
$community = $community ?? null;

if (!$community) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Community Not Found</h3>
		<p class="vt-text-muted vt-mb-4">The community you're trying to edit could not be found.</p>
		<a href="/communities" class="vt-btn">Back to Communities</a>
	</div>
	<?php
	return;
}
?>

<!-- Secondary Navigation -->
<div class="vt-mb-4">
	<?php
	$tabs = [
		[
			'label' => 'View Community',
			'url' => '/communities/' . $community->slug,
			'active' => false
		],
		[
			'label' => 'Edit',
			'url' => '/communities/' . $community->slug . '/edit',
			'active' => true
		],
		[
			'label' => 'Manage',
			'url' => '/communities/' . $community->slug . '/manage',
			'active' => false
		]
	];
	include VT_INCLUDES_DIR . '/../templates/partials/secondary-nav.php';
	?>
</div>

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

<!-- Edit Community Form -->
<div class="vt-section">
	<form method="post" class="vt-form" enctype="multipart/form-data">
		<?php echo vt_service('security.service')->nonceField('vt_edit_community', 'edit_community_nonce'); ?>

		<div class="vt-form-group">
			<label class="vt-form-label" for="community_name">
				Community Name
			</label>
			<input type="text" id="community_name" name="community_name" class="vt-form-input"
				   value="<?php echo htmlspecialchars($community->name); ?>" required>
		</div>

		<div class="vt-form-group">
			<label class="vt-form-label" for="description">
				Description
			</label>
			<textarea id="description" name="description" class="vt-form-textarea" rows="6"
					  placeholder="Tell people what this community is about..."><?php echo htmlspecialchars($community->description); ?></textarea>
		</div>

		<!-- Cover Image Upload -->
		<div class="vt-form-group">
			<label class="vt-form-label" for="cover_image">Cover Image</label>
			<input type="file" id="cover_image" name="cover_image" class="vt-form-input" accept="image/*">
			<p class="vt-form-help vt-text-muted">Optional: Upload a cover image for this community (Max 5MB)</p>

			<?php if (!empty($community->featured_image)) : ?>
				<div class="vt-current-cover vt-mt-2">
					<p class="vt-text-muted vt-mb-2">Current cover image:</p>
					<img src="<?php echo htmlspecialchars($community->featured_image); ?>" alt="Current cover"
						 style="max-width: 200px; height: auto; border-radius: 4px;">
					<label class="vt-mt-2">
						<input type="checkbox" name="remove_cover_image" value="1"> Remove current cover image
					</label>
				</div>
			<?php endif; ?>
		</div>

		<div class="vt-form-group">
			<label class="vt-form-label" for="privacy">
				Privacy Setting
			</label>
			<select id="privacy" name="privacy" class="vt-form-input">
				<option value="public" <?php echo ($community->privacy === 'public') ? 'selected' : ''; ?>>
					Public - Anyone can join
				</option>
				<option value="private" <?php echo ($community->privacy === 'private') ? 'selected' : ''; ?>>
					Private - Invite only
				</option>
			</select>
		</div>

		<div class="vt-form-actions">
			<button type="submit" class="vt-btn vt-btn-primary vt-btn-lg">
				Update Community
			</button>
			<a href="/communities/<?php echo htmlspecialchars($community->slug); ?>" class="vt-btn vt-btn-secondary vt-btn-lg">
				Cancel
			</a>
		</div>
	</form>

	<!-- Danger Zone -->
	<?php
	$community_manager = new VT_Community_Manager();
	$current_user = vt_service('auth.service')->getCurrentUser();
	$members = $community_manager->getCommunityMembers($community->id);
	$active_count = count($members);

	$entity_type = 'community';
	$entity_id = $community->id;
	$entity_name = $community->name;
	$can_delete = $community_manager->canManageCommunity($community->id, $current_user->id);
	$confirmation_type = 'type_name';
	$blocker_count = 0; // Never block deletion
	$blocker_message = $active_count > 0 ? "This community has {$active_count} active member(s)." : '';
	$delete_message = 'Once you delete a community, there is no going back. This will permanently delete the community, all its members, events, and conversations.';
	$nonce_action = 'vt_community_management';

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
