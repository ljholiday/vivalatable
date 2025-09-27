<?php
/**
 * VivalaTable Create Community Content Template
 * Form for creating new communities
 */

// Handle form submissions
$errors = array();
$messages = array();
$community_created = false;

// Handle community creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$community_data = [
		'name' => trim($_POST['name'] ?? ''),
		'description' => trim($_POST['description'] ?? ''),
		'visibility' => $_POST['visibility'] ?? 'public',
		'creator_email' => VT_Auth::getCurrentUser()->email ?? ''
	];

	// Basic validation
	if (empty($community_data['name'])) {
		$errors[] = 'Community name is required.';
	}
	if (empty($community_data['description'])) {
		$errors[] = 'Community description is required.';
	}

	// If no validation errors, create community
	if (empty($errors)) {
		$community_manager = new VT_Community_Manager();
		$result = $community_manager->createCommunity($community_data);

		if (isset($result['error'])) {
			$errors[] = $result['error'];
		} elseif (is_numeric($result)) {
			$messages[] = 'Community created successfully!';
			$community_created = true;
			$new_community_id = $result;
		} else {
			$errors[] = 'Failed to create community. Please try again.';
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

<!-- Create Community Form -->
<div class="vt-section">
	<h2 class="vt-heading vt-heading-md vt-mb-4">Create New Community</h2>

	<form method="post" class="vt-form">
		<div class="vt-form-group">
			<label for="name" class="vt-form-label">Community Name</label>
			<input type="text" id="name" name="name" class="vt-form-input"
				   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
				   placeholder="Give your community a name" required>
		</div>

		<div class="vt-form-group">
			<label for="description" class="vt-form-label">Description</label>
			<textarea id="description" name="description" class="vt-form-input" rows="4"
					  placeholder="What's your community about?" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
		</div>

		<div class="vt-form-group">
			<label for="visibility" class="vt-form-label">Privacy</label>
			<select id="visibility" name="visibility" class="vt-form-input">
				<option value="public" <?php echo ($_POST['visibility'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public - Anyone can find and join</option>
				<option value="private" <?php echo ($_POST['visibility'] ?? '') === 'private' ? 'selected' : ''; ?>>Private - Invite only</option>
			</select>
		</div>

		<div class="vt-form-group">
			<button type="submit" class="vt-btn vt-btn-lg" style="width: 100%;">Create Community</button>
		</div>
	</form>

	<?php if ($community_created): ?>
		<div class="vt-text-center vt-mt-4">
			<a href="/communities" class="vt-btn vt-btn-secondary">View All Communities</a>
		</div>
	<?php endif; ?>
</div>