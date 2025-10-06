<?php
/**
 * VivalaTable Manage Community Content Template
 * Community management interface with Settings - Members - Invitations - View Community navigation
 * Ported from PartyMinder WordPress plugin
 */

// Get community ID from route parameter (passed from VT_Pages::manageCommunity methods)
if (!isset($community_id)) {
	$community_id = intval($_GET['community_id'] ?? 0); // Fallback for backward compatibility
}
$active_tab = $_GET['tab'] ?? 'members';

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
$current_user = vt_service('auth.service')->getCurrentUser();
$is_logged_in = vt_service('auth.service')->isLoggedIn();

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

// No form submissions handled on manage page - editing happens on /edit page

// Get community members for display
$community_members = $community_manager->getCommunityMembers($community_id);

// Set up template variables
$page_title = sprintf('Manage %s', htmlspecialchars($community->name));
$page_description = 'Manage members and invitations for your community';
?>

<!-- Tab Navigation -->
<div class="vt-section vt-mb-4">
	<div class="vt-tab-nav vt-flex vt-gap-4 vt-flex-wrap">

		<a href="?community_id=<?php echo $community_id; ?>&tab=members" class="vt-btn <?php echo ($active_tab === 'members') ? 'is-active' : ''; ?>">
			Members
		</a>

		<a href="?community_id=<?php echo $community_id; ?>&tab=invitations" class="vt-btn <?php echo ($active_tab === 'invitations') ? 'is-active' : ''; ?>">
			Invitations
		</a>

		<a href="/communities/<?php echo htmlspecialchars($community->slug); ?>/edit" class="vt-btn">
			Edit
		</a>

		<a href="/communities/<?php echo htmlspecialchars($community->slug); ?>" class="vt-btn">
			View Community
		</a>

	</div>
</div>

<!-- Tab Content -->
<?php if ($active_tab === 'members') : ?>
<div class="vt-section">
	<h3 class="vt-heading vt-heading-md vt-mb-4">Community Members</h3>

	<?php if (!empty($community_members)) : ?>
		<div class="vt-table-responsive">
			<table class="vt-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Email</th>
						<th>Role</th>
						<th>Joined</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($community_members as $member) : ?>
						<tr id="member-row-<?php echo $member->id; ?>">
							<td>
								<strong><?php echo htmlspecialchars($member->display_name ?: 'No name'); ?></strong>
							</td>
							<td><?php echo htmlspecialchars($member->email); ?></td>
							<td>
								<span class="vt-badge vt-badge-<?php echo $member->role === 'admin' ? 'primary' : 'secondary'; ?>">
									<?php echo htmlspecialchars(ucfirst($member->role)); ?>
								</span>
							</td>
							<td><?php echo date('M j, Y', strtotime($member->joined_at)); ?></td>
							<td>
								<div class="vt-flex vt-gap-2">
									<?php if ($member->user_id != $current_user->id) : ?>
										<select class="vt-form-input vt-form-input-sm"
												onchange="changeMemberRole(<?php echo $member->id; ?>, this.value, <?php echo $community_id; ?>)">
											<option value="member" <?php echo $member->role === 'member' ? 'selected' : ''; ?>>Member</option>
											<option value="admin" <?php echo $member->role === 'admin' ? 'selected' : ''; ?>>Admin</option>
										</select>
										<button class="vt-btn vt-btn-sm vt-btn-danger"
												onclick="removeMember(<?php echo $member->id; ?>, '<?php echo htmlspecialchars($member->display_name ?: $member->email, ENT_QUOTES); ?>', <?php echo $community_id; ?>)">
											Remove
										</button>
									<?php else : ?>
										<span class="vt-text-muted vt-text-sm">You</span>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<div class="vt-text-center vt-p-4">
			<p class="vt-text-muted">No members yet.</p>
		</div>
	<?php endif; ?>
</div>

<?php elseif ($active_tab === 'invitations') : ?>
<?php
$entity_type = 'community';
$entity_slug = $community->slug;
$entity_id = $community_id;
$show_pending = true;
include VT_INCLUDES_DIR . '/../templates/partials/invitation-section.php';
?>

<?php endif; ?>

