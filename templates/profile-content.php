<?php
/**
 * VivalaTable Profile Content Template
 * User profile display and editing page
 * Ported from PartyMinder WordPress plugin
 */

// Get user ID from query parameter or default to current user
$user_id = $_GET['user'] ?? null;
$current_user = vt_service('auth.service')->getCurrentUser();
$current_user_id = $current_user ? $current_user->id : null;

if (!$user_id && $current_user_id) {
	$user_id = $current_user_id;
}

$is_own_profile = ($user_id == $current_user_id);
$is_editing = $is_own_profile && isset($_GET['edit']);

// Get user data
$user_data = vt_service('auth.user_repository')->getUserById($user_id);
if (!$user_data) {
	echo '<div class="vt-section vt-text-center">';
	echo '<h3 class="vt-heading vt-heading-md">Profile Not Found</h3>';
	echo '<p class="vt-text-muted">The requested user profile could not be found.</p>';
	echo '</div>';
	return;
}

// Get VivalaTable profile data
$profile_data = VT_Profile_Manager::getUserProfile($user_id);

// Get avatar URL using existing logic from member display
$avatar_url = '';
if (!empty($profile_data['profile_image'])) {
	$avatar_url = VT_Image_Manager::getImageUrl($profile_data['profile_image']);
} elseif (!empty($user_data->email)) {
	$hash = md5(strtolower(trim($user_data->email)));
	$avatar_url = "https://www.gravatar.com/avatar/{$hash}?s=120&d=identicon";
}
if (!$avatar_url) {
	// Fallback to Gravatar identicon with a default email
	$fallback_hash = md5('default@vivalatable.com');
	$avatar_url = "https://www.gravatar.com/avatar/{$fallback_hash}?s=120&d=identicon";
}

// Handle profile form submission
$profile_updated = false;
$form_errors = array();

if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vt_profile_nonce'])) {
	if (vt_service('security.service')->verifyNonce($_POST['vt_profile_nonce'], 'vt_profile_update')) {
		$result = VT_Profile_Manager::updateProfile($user_id, $_POST);
		if ($result['success']) {
			$profile_updated = true;
			// Refresh profile data
			$profile_data = VT_Profile_Manager::getUserProfile($user_id);
		} else {
			$form_errors = $result['errors'];
		}
	} else {
		$form_errors[] = 'Security verification failed. Please try again.';
	}
}

// Set up template variables
$page_title = $is_editing
	? 'Edit Profile'
	: $user_data->display_name;
$page_description = $is_editing
	? 'Update your information, preferences, and privacy settings'
	: sprintf('%s\'s profile and activity', $user_data->display_name);

$breadcrumbs = array(
	array(
		'title' => 'Dashboard',
		'url' => '/dashboard'
	),
	array('title' => 'Profile')
);

// If editing, use form template
if ($is_editing) {
	// Success message
	if ($profile_updated || isset($_GET['updated'])) {
		echo '<div class="vt-alert vt-alert-success vt-mb-4">';
		echo '<h4 class="vt-heading vt-heading-sm">Profile Updated!</h4>';
		echo '<p>Your profile has been successfully updated.</p>';
		echo '<a href="/profile" class="vt-btn">';
		echo '👤 View Profile';
		echo '</a>';
		echo '</div>';
	}

	// Show errors if any
	if (!empty($form_errors)) {
		echo '<div class="vt-alert vt-alert-error vt-mb-4">';
		echo '<h4 class="vt-heading vt-heading-sm">Please fix the following errors:</h4>';
		echo '<ul>';
		foreach ($form_errors as $error) {
			echo '<li>' . htmlspecialchars($error) . '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	?>

	<form method="post" class="vt-form" enctype="multipart/form-data">
		<?php echo vt_service('security.service')->nonceField('vt_profile_update', 'vt_profile_nonce'); ?>

		<div class="vt-mb-4">
			<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Basic Information</h3>

			<div class="vt-form-group">
				<label class="vt-form-label" for="display_name">Display Name *</label>
				<input type="text"
						id="display_name"
						name="display_name"
						class="vt-form-input"
						value="<?php echo htmlspecialchars($profile_data['display_name'] ?? $user_data->display_name); ?>"
						required>
			</div>

			<div class="vt-form-group">
				<label class="vt-form-label" for="bio">Bio</label>
				<textarea id="bio"
							name="bio"
							class="vt-form-textarea"
							rows="4"
							placeholder="Tell people a bit about yourself..."><?php echo htmlspecialchars($profile_data['bio'] ?? ''); ?></textarea>
			</div>

			<div class="vt-form-group">
				<label class="vt-form-label" for="location">Location</label>
				<input type="text"
						id="location"
						name="location"
						class="vt-form-input"
						value="<?php echo htmlspecialchars($profile_data['location'] ?? ''); ?>"
						placeholder="City, State/Country">
			</div>
		</div>

		<!-- Profile Photo Upload Card -->
		<div class="vt-card vt-mb-4">
			<div class="vt-card-header">
				<h3 class="vt-heading vt-heading-md vt-text-primary">Profile Photo</h3>
			</div>
			<div class="vt-card-body">
				<div class="vt-text-center vt-mb">
					<div class="vt-profile-avatar" style="width: 120px; height: 120px; margin: 0 auto;">
						<?php if (($profile_data['avatar_source'] ?? 'gravatar') === 'custom' && !empty($profile_data['profile_image'])) : ?>
						<img src="<?php echo htmlspecialchars($profile_data['profile_image']); ?>"
							style="width: 100%; height: 100%; object-fit: cover;"
							alt="Profile photo">
						<?php else : ?>
						<img src="<?php echo htmlspecialchars($avatar_url); ?>"
							style="width: 100%; height: 100%; object-fit: cover;"
							alt="Profile photo">
						<?php endif; ?>
					</div>
				</div>
				<p class="vt-form-help vt-text-muted vt-mb">Your profile photo appears throughout the site</p>

				<div class="vt-form-group">
					<label class="vt-form-label">Avatar Source</label>
					<div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
						<label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
							<input type="radio" name="avatar_source" value="gravatar" <?php echo ($profile_data['avatar_source'] ?? 'gravatar') === 'gravatar' ? 'checked' : ''; ?>>
							<span class="vt-btn">Gravatar</span>
						</label>
						<label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
							<input type="radio" name="avatar_source" value="custom" <?php echo ($profile_data['avatar_source'] ?? 'gravatar') === 'custom' ? 'checked' : ''; ?>>
							<span class="vt-btn">Custom Avatar</span>
						</label>
					</div>
				</div>

				<div class="vt-avatar-upload">
					<input type="file" id="avatar_upload" name="profile_image" accept="image/*" style="display: none;">
					<button type="button" class="vt-btn" onclick="document.getElementById('avatar_upload').click()">
						Upload Profile Photo
					</button>
					<div class="vt-upload-progress" style="display: none; margin-top: 10px;">
						<div class="vt-progress-bar">
							<div class="vt-progress-fill"></div>
						</div>
						<div class="vt-progress-text">0%</div>
					</div>
					<div class="vt-upload-message" style="margin-top: 10px;"></div>
				</div>
			</div>
		</div>

		<!-- Cover Photo Upload Card -->
		<div class="vt-card vt-mb-4">
			<div class="vt-card-header">
				<h3 class="vt-heading vt-heading-md vt-text-primary">Cover Photo</h3>
			</div>
			<div class="vt-card-body">
				<div class="vt-text-center vt-mb">
					<div style="width: 200px; height: 80px; margin: 0 auto; border-radius: 0.5rem; overflow: hidden; border: 2px solid #e2e8f0;">
						<?php if (!empty($profile_data['cover_image'])) : ?>
						<img src="<?php echo htmlspecialchars($profile_data['cover_image']); ?>"
							style="width: 100%; height: 100%; object-fit: cover;"
							alt="Cover photo preview">
						<?php else : ?>
						<div style="width: 100%; height: 100%; background: linear-gradient(135deg, #3b82f6 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.75rem;">
							No cover photo
						</div>
						<?php endif; ?>
					</div>
				</div>
				<p class="vt-form-help vt-text-muted vt-mb">Your cover photo appears at the top of your profile</p>

				<div class="vt-cover-upload">
					<input type="file" id="cover_upload" name="cover_image" accept="image/*" style="display: none;">
					<button type="button" class="vt-btn" onclick="document.getElementById('cover_upload').click()">
						Upload Cover Photo
					</button>
					<div class="vt-upload-progress" style="display: none; margin-top: 10px;">
						<div class="vt-progress-bar">
							<div class="vt-progress-fill"></div>
						</div>
						<div class="vt-progress-text">0%</div>
					</div>
					<div class="vt-upload-message" style="margin-top: 10px;"></div>
				</div>
			</div>
		</div>

		<div class="vt-form-actions">
			<button type="submit" class="vt-btn">
				Save Profile Info
			</button>
			<a href="/profile" class="vt-btn">
				View Profile
			</a>
		</div>
	</form>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		// Handle avatar source radio button changes
		document.querySelectorAll('input[name="avatar_source"]').forEach(function(radio) {
			radio.addEventListener('change', function() {
				const avatarImages = document.querySelectorAll('.vt-profile-avatar img');
				const isCustom = this.value === 'custom';
				const hasCustomImage = <?php echo !empty($profile_data['profile_image']) ? 'true' : 'false'; ?>;

				avatarImages.forEach(function(img) {
					if (isCustom && hasCustomImage) {
						img.src = '<?php echo addslashes($profile_data['profile_image'] ?? ''); ?>';
					} else {
						img.src = '<?php echo addslashes($avatar_url); ?>';
					}
				});
			});
		});

		// Show file names when selected and validate
		document.getElementById('avatar_upload').addEventListener('change', function() {
			if (this.files.length > 0) {
				const file = this.files[0];
				const message = document.querySelector('.vt-avatar-upload .vt-upload-message');

				// Basic validation
				if (file.size > 5 * 1024 * 1024) { // 5MB
					message.innerHTML = 'File too large. Maximum size is 5MB.';
					message.className = 'vt-upload-message error';
					this.value = '';
					return;
				}

				if (!file.type.match(/^image\/(jpeg|jpg|png|gif|webp)$/)) {
					message.innerHTML = 'Invalid file type. Please select a JPEG, PNG, GIF, or WebP image.';
					message.className = 'vt-upload-message error';
					this.value = '';
					return;
				}

				message.innerHTML = 'Selected: ' + file.name + ' (' + Math.round(file.size / 1024) + ' KB). Click "Save Profile Info" to upload.';
				message.className = 'vt-upload-message success';
			}
		});

		document.getElementById('cover_upload').addEventListener('change', function() {
			if (this.files.length > 0) {
				const file = this.files[0];
				const message = document.querySelector('.vt-cover-upload .vt-upload-message');

				// Basic validation
				if (file.size > 5 * 1024 * 1024) { // 5MB
					message.innerHTML = 'File too large. Maximum size is 5MB.';
					message.className = 'vt-upload-message error';
					this.value = '';
					return;
				}

				if (!file.type.match(/^image\/(jpeg|jpg|png|gif|webp)$/)) {
					message.innerHTML = 'Invalid file type. Please select a JPEG, PNG, GIF, or WebP image.';
					message.className = 'vt-upload-message error';
					this.value = '';
					return;
				}

				message.innerHTML = 'Selected: ' + file.name + ' (' + Math.round(file.size / 1024) + ' KB). Click "Save Profile Info" to upload.';
				message.className = 'vt-upload-message success';
			}
		});
	});
	</script>

<?php } else {
	// Profile view mode

	// Profile Header Section
	$cover_photo = $profile_data['cover_image'] ?? '';
	$cover_photo_url = $cover_photo ? htmlspecialchars($cover_photo) : '';
	?>

	<!-- Modern Profile Header -->
	<section class="vt-profile-header-modern vt-mb">
		<!-- Banner -->
		<div class="vt-profile-cover">
			<?php if ($cover_photo_url) : ?>
				<img id="vt-banner-img"
					 src="<?php echo $cover_photo_url; ?>"
					 alt="Profile banner"
					 style="width: 100%; height: 100%; object-fit: cover;">
			<?php else : ?>
				<div class="vt-flex vt-flex-center vt-text-center" style="height: 100%; background: linear-gradient(135deg, var(--vt-primary) 0%, #764ba2 100%);"></div>
			<?php endif; ?>
		</div>

		<!-- Avatar + Right content row -->
		<div class="vt-flex vt-flex-between vt-avatar-row">
			<!-- Left: Avatar -->
			<div id="vt-avatar"
				 class="vt-profile-avatar vt-avatar-modern"
				 role="img"
				 aria-label="<?php echo htmlspecialchars($user_data->display_name); ?>">
				<?php if (($profile_data['avatar_source'] ?? 'gravatar') === 'custom' && !empty($profile_data['profile_image'])) : ?>
				<img src="<?php echo htmlspecialchars($profile_data['profile_image']); ?>" alt="<?php echo htmlspecialchars($user_data->display_name); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
				<?php else : ?>
				<img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="<?php echo htmlspecialchars($user_data->display_name); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
				<?php endif; ?>
			</div>

			<!-- Right: Buttons -->
			<div class="vt-flex vt-gap">
				<?php if ($is_own_profile) : ?>
					<a href="/profile?edit=1" class="vt-btn">
						Edit Profile
					</a>
				<?php endif; ?>
			</div>
		</div>

		<!-- Identity/text row -->
		<div class="vt-profile-identity">
			<h1 class="vt-heading vt-heading-xl vt-mb"><?php echo htmlspecialchars(VT_Profile_Manager::getDisplayName($user_id)); ?></h1>
			<div class="vt-text-muted vt-mb">@<?php echo htmlspecialchars($user_data->username ?? $user_data->email ?? 'user'); ?></div>
		</div>
	</section>

	<!-- Bio Section (separate from header) -->
	<?php if (!empty($profile_data['bio']) || !empty($profile_data['location']) || $user_data->created_at) : ?>
	<section class="vt-section vt-mb">
		<div class="vt-card">
			<div class="vt-card-body">
				<?php if (!empty($profile_data['bio'])) : ?>
					<h3 class="vt-heading vt-heading-md vt-mb-2">About</h3>
					<p class="vt-text vt-mb-4"><?php echo htmlspecialchars($profile_data['bio']); ?></p>
				<?php endif; ?>

				<div class="vt-flex vt-flex-wrap vt-gap vt-text-muted">
					<?php if (!empty($profile_data['location'])) : ?>
					<span><?php echo htmlspecialchars($profile_data['location']); ?></span>
					<?php endif; ?>
					<span><?php printf('Joined %s', date('M Y', strtotime($user_data->created_at))); ?></span>
				</div>
			</div>
		</div>
	</section>
	<?php endif; ?>

<?php } ?>