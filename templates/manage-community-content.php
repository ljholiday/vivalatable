<?php
/**
 * VivalaTable Manage Community Content Template
 * Community management interface with Settings - Members - Invitations - View Community navigation
 * Ported from PartyMinder WordPress plugin
 */

// Get community ID from URL parameter
$community_id = intval($_GET['community_id'] ?? 0);
$active_tab = $_GET['tab'] ?? 'settings';

if (!$community_id) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Community Not Found</h3>
		<p class="vt-text-muted vt-mb-4">Community ID is required to manage a community.</p>
		<a href="/communities" class="vt-btn">Back to Communities</a>
	</div>
	<?php
	return;
}

// Load managers and get community
$community_manager = new VT_Community_Manager();
$community = $community_manager->getCommunity($community_id);

if (!$community) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Community Not Found</h3>
		<p class="vt-text-muted vt-mb-4">The requested community does not exist.</p>
		<a href="/communities" class="vt-btn">Back to Communities</a>
	</div>
	<?php
	return;
}

// Check user permissions
$current_user = VT_Auth::getCurrentUser();
$is_logged_in = VT_Auth::isLoggedIn();

if (!$is_logged_in) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Access Denied</h3>
		<p class="vt-text-muted vt-mb-4">You must be logged in to manage communities.</p>
		<a href="/login" class="vt-btn vt-btn-primary">Login</a>
	</div>
	<?php
	return;
}

// Check if user can manage this community
if (!$community_manager->canManageCommunity($community_id, $current_user->id)) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Access Denied</h3>
		<p class="vt-text-muted vt-mb-4">You do not have permission to manage this community.</p>
		<a href="/communities/<?php echo htmlspecialchars($community->slug); ?>" class="vt-btn">View Community</a>
	</div>
	<?php
	return;
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	if ($_POST['action'] === 'update_community_settings' && VT_Security::verifyNonce($_POST['nonce'], 'vt_community_management')) {
		$update_data = array(
			'name' => VT_Sanitize::textField($_POST['community_name']),
			'description' => VT_Sanitize::textarea($_POST['description']),
			'visibility' => VT_Sanitize::textField($_POST['visibility']),
		);

		// Handle cover image removal
		if (isset($_POST['remove_cover_image']) && $_POST['remove_cover_image'] == '1') {
			$update_data['featured_image'] = '';
		}

		// Handle cover image upload
		if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
			$upload_result = VT_Image_Manager::handleImageUpload($_FILES['cover_image'], 'cover', $community_id, 'community');
			if ($upload_result['success']) {
				$update_data['featured_image'] = $upload_result['url'];
			} else {
				$error_message = $upload_result['error'];
			}
		}

		if (!$error_message) {
			$result = $community_manager->updateCommunity($community_id, $update_data);
			if ($result) {
				$success_message = 'Community settings updated successfully.';
				// Refresh community data
				$community = $community_manager->getCommunity($community_id);
			} else {
				$error_message = 'Failed to update community settings.';
			}
		}
	}

	// Handle community deletion
	if ($_POST['action'] === 'delete_community' && VT_Security::verifyNonce($_POST['nonce'], 'vt_community_management')) {
		$confirm_name = VT_Sanitize::textField($_POST['confirm_name']);

		if ($confirm_name === $community->name) {
			$result = $community_manager->deleteCommunity($community_id);
			if ($result) {
				// Redirect to communities page after successful deletion
				VT_Router::redirect('/communities?deleted=1');
				exit;
			} else {
				$error_message = 'Failed to delete community.';
			}
		} else {
			$error_message = 'Community name confirmation does not match. Community was not deleted.';
		}
	}
}

// Set up template variables
$page_title = sprintf('Manage %s', htmlspecialchars($community->name));
$page_description = 'Manage settings, members, and invitations for your community';
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

<!-- Tab Navigation -->
<div class="vt-section vt-mb-4">
	<div class="vt-tabs">
		<a href="?community_id=<?php echo $community_id; ?>&tab=settings" class="vt-tab <?php echo ($active_tab === 'settings') ? 'active' : ''; ?>">
			Settings
		</a>
		<a href="?community_id=<?php echo $community_id; ?>&tab=members" class="vt-tab <?php echo ($active_tab === 'members') ? 'active' : ''; ?>">
			Members
		</a>
		<a href="?community_id=<?php echo $community_id; ?>&tab=invitations" class="vt-tab <?php echo ($active_tab === 'invitations') ? 'active' : ''; ?>">
			Invitations
		</a>
		<a href="/communities/<?php echo htmlspecialchars($community->slug); ?>" class="vt-tab">
			View Community
		</a>
	</div>
</div>

<!-- Tab Content -->
<?php if ($active_tab === 'settings') : ?>
<div class="vt-section">
	<div class="vt-section-header">
		<h2 class="vt-heading vt-heading-md vt-text-primary">Community Settings</h2>
	</div>

	<form method="post" class="vt-form" enctype="multipart/form-data">
		<input type="hidden" name="action" value="update_community_settings">
		<input type="hidden" name="nonce" value="<?php echo VT_Security::createNonce('vt_community_management'); ?>">

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
			<textarea id="description" name="description" class="vt-form-textarea" rows="4"
					  placeholder="Update community description..."><?php echo htmlspecialchars($community->description); ?></textarea>
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
			<label class="vt-form-label" for="visibility">
				Privacy Setting
			</label>
			<select id="visibility" name="visibility" class="vt-form-input">
				<option value="public" <?php echo ($community->visibility === 'public') ? 'selected' : ''; ?>>
					Public - Anyone can join
				</option>
				<option value="private" <?php echo ($community->visibility === 'private') ? 'selected' : ''; ?>>
					Private - Invite only
				</option>
			</select>
		</div>

		<button type="submit" class="vt-btn vt-btn-primary">
			Save Changes
		</button>
	</form>

	<!-- Danger Zone -->
	<div class="vt-section vt-mt-6" style="border-top: 2px solid #dc2626; padding-top: 20px;">
		<h4 class="vt-heading vt-heading-sm" style="color: #dc2626;">Danger Zone</h4>
		<p class="vt-text-muted vt-mb-4">
			Once you delete a community, there is no going back. This will permanently delete the community, all its members, events, and conversations.
		</p>

		<form method="post" class="vt-form" id="delete-community-form" onsubmit="return confirmCommunityDeletion(event)">
			<input type="hidden" name="action" value="delete_community">
			<input type="hidden" name="nonce" value="<?php echo VT_Security::createNonce('vt_community_management'); ?>">

			<div class="vt-form-group">
				<label class="vt-form-label" for="confirm_name" style="color: #dc2626;">
					Type "<?php echo htmlspecialchars($community->name); ?>" to confirm deletion:
				</label>
				<input type="text" id="confirm_name" name="confirm_name" class="vt-form-input"
					   placeholder="<?php echo htmlspecialchars($community->name); ?>" required>
			</div>

			<button type="submit" class="vt-btn" style="background-color: #dc2626; border-color: #dc2626;"
					disabled id="delete-community-btn">
				Delete Community Permanently
			</button>
		</form>
	</div>
</div>

<?php elseif ($active_tab === 'members') : ?>
<div class="vt-section">
	<div class="vt-section-header">
		<h2 class="vt-heading vt-heading-md vt-text-primary">Community Members</h2>
	</div>
	<div id="members-list">
		<div class="vt-loading-placeholder">
			<p>Loading community members...</p>
		</div>
	</div>
</div>

<?php elseif ($active_tab === 'invitations') : ?>
<div class="vt-section">
	<div class="vt-section-header">
		<h2 class="vt-heading vt-heading-md vt-text-primary">Send Invitations</h2>
	</div>

	<!-- Copyable Invitation Links -->
	<div class="vt-card vt-mb-4">
		<div class="vt-card-header">
			<h3 class="vt-heading vt-heading-sm">Share Community Link</h3>
		</div>
		<div class="vt-card-body">
			<p class="vt-text-muted vt-mb-4">
				Copy and share this link via text, social media, Discord, Slack, or any other platform.
			</p>

			<div class="vt-form-group vt-mb-4">
				<label class="vt-form-label">Community Invitation Link</label>
				<div class="vt-flex vt-gap-2">
					<input type="text" class="vt-form-input vt-flex-1" id="invitation-link"
						   value="<?php echo VT_Http::getBaseUrl() . '/communities/' . htmlspecialchars($community->slug) . '?join=1'; ?>"
						   readonly>
					<button type="button" class="vt-btn vt-copy-invitation-link">
						Copy
					</button>
				</div>
			</div>

			<div class="vt-form-group">
				<label class="vt-form-label">Custom Message (Optional)</label>
				<textarea class="vt-form-textarea" id="custom-message" rows="3"
						  placeholder="Add a personal message to include when sharing..."></textarea>
				<div class="vt-mt-2">
					<button type="button" class="vt-btn vt-copy-invitation-with-message">
						Copy Link with Message
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Email Invitation Form -->
	<form id="send-invitation-form" class="vt-form">
		<div class="vt-form-group">
			<label class="vt-form-label" for="invitation-email">
				Email Address
			</label>
			<input type="email" class="vt-form-input" id="invitation-email"
				   placeholder="Enter email address..." required>
		</div>

		<div class="vt-form-group">
			<label class="vt-form-label" for="invitation-message">
				Personal Message (Optional)
			</label>
			<textarea class="vt-form-textarea" id="invitation-message" rows="3"
					  placeholder="Add a personal message to your invitation..."></textarea>
		</div>

		<button type="submit" class="vt-btn vt-btn-primary">
			Send Invitation
		</button>
	</form>

	<div class="vt-mt-6">
		<h4 class="vt-heading vt-heading-sm">Pending Invitations</h4>
		<div id="invitations-list">
			<div class="vt-loading-placeholder">
				<p>Loading pending invitations...</p>
			</div>
		</div>
	</div>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const communityId = <?php echo $community_id; ?>;
	const currentTab = '<?php echo $active_tab; ?>';
	const communityName = '<?php echo addslashes($community->name); ?>';

	// Load appropriate tab content
	if (currentTab === 'members') {
		loadCommunityMembers(communityId);
	} else if (currentTab === 'invitations') {
		loadCommunityInvitations(communityId);
		setupInvitationHandlers();
	}

	// Settings tab functionality
	if (currentTab === 'settings') {
		setupDeletionConfirmation();
	}

	function loadCommunityMembers(communityId) {
		const membersList = document.getElementById('members-list');
		if (!membersList) return;

		// For now, show a placeholder - this would be implemented with AJAX
		membersList.innerHTML = '<div class="vt-text-center vt-p-4"><p class="vt-text-muted">Member management functionality coming soon!</p></div>';
	}

	function loadCommunityInvitations(communityId) {
		const invitationsList = document.getElementById('invitations-list');
		if (!invitationsList) return;

		// For now, show a placeholder - this would be implemented with AJAX
		invitationsList.innerHTML = '<div class="vt-text-center vt-p-4"><p class="vt-text-muted">No pending invitations.</p></div>';
	}

	function setupInvitationHandlers() {
		// Copy invitation link
		const copyLinkBtn = document.querySelector('.vt-copy-invitation-link');
		if (copyLinkBtn) {
			copyLinkBtn.addEventListener('click', function() {
				const linkInput = document.getElementById('invitation-link');
				linkInput.select();
				document.execCommand('copy');
				this.textContent = 'Copied!';
				setTimeout(() => {
					this.textContent = 'Copy';
				}, 2000);
			});
		}

		// Copy invitation with message
		const copyWithMessageBtn = document.querySelector('.vt-copy-invitation-with-message');
		if (copyWithMessageBtn) {
			copyWithMessageBtn.addEventListener('click', function() {
				const link = document.getElementById('invitation-link').value;
				const message = document.getElementById('custom-message').value;
				const fullText = message ? message + '\n\n' + link : link;

				navigator.clipboard.writeText(fullText).then(() => {
					this.textContent = 'Copied!';
					setTimeout(() => {
						this.textContent = 'Copy Link with Message';
					}, 2000);
				});
			});
		}

		// Email invitation form
		const invitationForm = document.getElementById('send-invitation-form');
		if (invitationForm) {
			invitationForm.addEventListener('submit', function(e) {
				e.preventDefault();
				// Placeholder for AJAX invitation sending
				alert('Email invitation functionality coming soon!');
			});
		}
	}

	function setupDeletionConfirmation() {
		const confirmInput = document.getElementById('confirm_name');
		const deleteBtn = document.getElementById('delete-community-btn');

		if (confirmInput && deleteBtn) {
			confirmInput.addEventListener('input', function() {
				deleteBtn.disabled = this.value !== communityName;
			});
		}
	}

	// Global function for form submission confirmation
	window.confirmCommunityDeletion = function(event) {
		return confirm('Are you absolutely sure you want to delete "' + communityName + '"?\n\nThis action cannot be undone. All community data, members, events, and conversations will be permanently deleted.');
	};
});
</script>