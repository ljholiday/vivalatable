<?php
/**
 * VivalaTable Communities Content Template
 * Main communities page with tab-based filtering
 * Ported from PartyMinder WordPress plugin
 */

// Prevent direct access
if (!defined('VT_VERSION')) {
    exit;
}

// Load required classes
require_once VT_INCLUDES_DIR . '/class-community-manager.php';

$community_manager = new VT_Community_Manager();

// Get current user info
$current_user = vt_service('auth.service')->getCurrentUser();
$user_logged_in = vt_service('auth.service')->isLoggedIn();
$user_email = $user_logged_in ? $current_user->email : '';

// Get data for both tabs
$user_communities = array();
$public_communities = array();

if ($user_logged_in) {
	$db = VT_Database::getInstance();
	$communities_table = $db->prefix . 'communities';
	$members_table = $db->prefix . 'community_members';

	// My Communities: Communities user is a member of
	$user_communities = $db->getResults($db->prepare(
		"SELECT c.*, m.role, m.status as member_status, m.joined_at FROM $communities_table c
		 JOIN $members_table m ON c.id = m.community_id
		 WHERE m.user_id = %d AND m.status = 'active' AND c.is_active = 1
		 ORDER BY c.created_at DESC LIMIT 20",
		$current_user->id
	));
}

// Public Communities: All discoverable communities
$public_communities = $community_manager->getPublicCommunities(20);

// Set up template variables
$page_title = 'Communities';
$page_description = 'Join communities of fellow hosts and guests to plan amazing events together';
?>


<!-- Success Message for Community Deletion -->
<?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1') : ?>
	<div class="vt-alert vt-alert-success vt-mb-4">
		Community has been successfully deleted.
	</div>
<?php endif; ?>

<!-- Community Tabs Navigation -->
<?php if ($user_logged_in) : ?>
<div class="vt-section vt-mb-4">
	<div class="vt-conversations-nav vt-flex vt-gap-4 vt-flex-wrap">
		<button class="vt-btn is-active" data-filter="my-communities" role="tab" aria-selected="true" aria-controls="vt-communities-list">
			My Communities
		</button>
		<button class="vt-btn" data-filter="all-communities" role="tab" aria-selected="false" aria-controls="vt-communities-list">
			All Communities
		</button>
	</div>
</div>
<?php endif; ?>

<div class="vt-section">
	<div id="vt-communities-list" class="vt-grid vt-gap">
		<?php if ($user_logged_in) : ?>
			<!-- My Communities Tab Content (Default) -->
			<div class="vt-communities-tab-content" data-tab="my-communities">
				<?php if (!empty($user_communities)) : ?>
					<?php foreach ($user_communities as $community) : ?>
						<div class="vt-section vt-border vt-p-4">
							<div class="vt-flex vt-flex-between vt-mb-4">
								<div class="vt-flex-1">
									<h3 class="vt-heading vt-heading-sm vt-mb-2">
										<a href="/communities/<?php echo vt_service('validation.validator')->escHtml($community->slug); ?>" class="vt-text-primary">
											<?php echo vt_service('validation.validator')->escHtml($community->name); ?>
										</a>
									</h3>
									<div class="vt-flex vt-gap vt-flex-wrap vt-mb-2">
										<?php if (!empty($community->role)) : ?>
										<span class="vt-badge vt-badge-<?php echo $community->role === 'admin' ? 'primary' : 'success'; ?>">
											<?php echo vt_service('validation.validator')->escHtml(ucfirst($community->role)); ?>
										</span>
										<?php endif; ?>
										<span class="vt-badge vt-badge-<?php echo $community->privacy === 'private' ? 'secondary' : 'success'; ?>">
											<?php echo vt_service('validation.validator')->escHtml(ucfirst($community->privacy)); ?>
										</span>
									</div>
									<div class="vt-text-muted">
										<?php if (!empty($community->joined_at)) : ?>
											<?php echo sprintf('Joined %s ago', VT_Text::timeAgo($community->joined_at)); ?>
										<?php else : ?>
											Member
										<?php endif; ?>
									</div>
								</div>
								<div class="vt-stat vt-text-center">
									<div class="vt-stat-number vt-text-primary"><?php echo intval($community->event_count ?? 0); ?></div>
									<div class="vt-stat-label">Events</div>
								</div>
							</div>

							<?php if ($community->description) : ?>
							<div class="vt-mb-4">
								<p class="vt-text-muted"><?php echo vt_service('validation.validator')->escHtml(VT_Text::truncateWords($community->description, 15)); ?></p>
							</div>
							<?php endif; ?>

							<div class="vt-flex vt-flex-between vt-flex-wrap vt-gap">
								<div class="vt-flex vt-gap vt-flex-wrap">
									<div class="vt-stat vt-text-center">
										<div class="vt-stat-number vt-text-primary"><?php echo intval($community->member_count ?? 0); ?></div>
										<div class="vt-stat-label">Members</div>
									</div>
								</div>

								<div class="vt-flex vt-gap">
									<a href="/communities/<?php echo vt_service('validation.validator')->escHtml($community->slug); ?>" class="vt-btn">
										View
									</a>
									<?php if ($community->role === 'admin') : ?>
										<a href="/communities/<?php echo vt_service('validation.validator')->escHtml($community->slug); ?>/manage" class="vt-btn">
											Manage
										</a>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="vt-text-center vt-p-4">
						<p class="vt-text-muted vt-mb-4">You haven't joined any communities yet.</p>
						<div class="vt-flex vt-gap vt-justify-center">
							<button class="vt-btn" onclick="document.querySelector('[data-filter=all-communities]').click()">
								Browse Communities
							</button>
							<a href="/communities/create" class="vt-btn">
								Create Community
							</a>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- All Communities Tab Content -->
		<div class="vt-communities-tab-content" data-tab="all-communities" <?php echo $user_logged_in ? 'style="display: none;"' : ''; ?>>
			<?php if (!empty($public_communities)) : ?>
				<?php foreach ($public_communities as $community) : ?>
				<div class="vt-section vt-border vt-p-4">
					<div class="vt-section-header vt-flex vt-flex-between vt-mb-4">
						<h3 class="vt-heading vt-heading-sm">
							<a href="/communities/<?php echo vt_service('validation.validator')->escHtml($community->slug); ?>" class="vt-text-primary">
								<?php echo vt_service('validation.validator')->escHtml($community->name); ?>
							</a>
						</h3>
						<div class="vt-badge vt-badge-<?php echo $community->privacy === 'public' ? 'success' : 'secondary'; ?>">
							<?php echo vt_service('validation.validator')->escHtml(ucfirst($community->privacy)); ?>
						</div>
					</div>
					<div class="vt-mb-4">
						<div class="vt-flex vt-gap">
							<span class="vt-text-muted"><?php echo intval($community->member_count); ?> members</span>
						</div>
					</div>

					<?php if ($community->description) : ?>
				<div class="vt-mb-4">
					<p class="vt-text-muted"><?php echo vt_service('validation.validator')->escHtml(VT_Text::truncateWords($community->description, 20)); ?></p>
				</div>
				<?php endif; ?>

				<div class="vt-flex vt-flex-between vt-mt-4">
					<div class="vt-stat">
						<div class="vt-stat-number vt-text-primary"><?php echo intval($community->event_count); ?></div>
						<div class="vt-text-muted">Events</div>
					</div>

					<?php if ($user_logged_in) : ?>
						<?php
						$is_member = $community_manager->isMember($community->id, $current_user->id);
						?>
						<button class="vt-btn <?php echo $is_member ? 'vt-btn-secondary' : 'vt-btn-primary'; ?> join-community-btn"
								data-community-id="<?php echo $community->id; ?>"
								data-community-name="<?php echo vt_service('validation.validator')->escHtml($community->name); ?>"
								<?php echo $is_member ? 'disabled' : ''; ?>>
							<?php echo $is_member ? 'Member' : 'Join'; ?>
						</button>
					<?php else : ?>
						<a href="/login?redirect_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="vt-btn">
							Login to Join
						</a>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="vt-text-center vt-p-4">
				<p class="vt-text-muted vt-mb-4">No public communities yet.</p>
				<p class="vt-text-muted">Be the first to create a community!</p>
			</div>
		<?php endif; ?>
		</div>
	</div>
</div>

