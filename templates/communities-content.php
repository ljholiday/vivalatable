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
$current_user = VT_Auth::getCurrentUser();
$user_logged_in = VT_Auth::isLoggedIn();
$user_email = $user_logged_in ? $current_user->email : '';

// Get data for both tabs - use direct database query since method doesn't exist yet
$db = VT_Database::getInstance();
$communities_table = $db->prefix . 'communities';
$public_communities = $db->getResults(
    $db->prepare(
        "SELECT * FROM $communities_table WHERE visibility = 'public' AND is_active = 1 AND personal_owner_user_id IS NULL ORDER BY created_at DESC LIMIT %d",
        20
    )
);
$user_communities = array();

if ($user_logged_in) {
	$user_communities = $community_manager->getUserCommunities($current_user->id);
}

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

<!-- Community Filters/Tabs -->
<?php if ($user_logged_in) : ?>
<div class="vt-section vt-mb-4">
	<div class="vt-conversations-nav vt-flex vt-gap-4 vt-flex-wrap">
		<!-- Community Type Filters -->
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
							<?php if (!empty($community->featured_image)) : ?>
								<div class="vt-mb-4">
									<img src="<?php echo VT_Sanitize::escUrl($community->featured_image); ?>" alt="<?php echo VT_Sanitize::escHtml($community->name); ?>" style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px;">
								</div>
							<?php endif; ?>
							<div class="vt-flex vt-flex-between vt-mb-4">
								<div class="vt-flex-1">
									<h3 class="vt-heading vt-heading-sm vt-mb-2">
										<a href="/communities/<?php echo VT_Sanitize::escHtml($community->slug); ?>" class="vt-text-primary">
											<?php echo VT_Sanitize::escHtml($community->name); ?>
										</a>
									</h3>
									<div class="vt-flex vt-gap vt-flex-wrap vt-mb-2">
										<span class="vt-badge vt-badge-<?php echo $community->role === 'admin' ? 'primary' : 'success'; ?>">
											<?php echo VT_Sanitize::escHtml(ucfirst($community->role)); ?>
										</span>
										<?php if ($community->visibility === 'private') : ?>
											<span class="vt-badge vt-badge-secondary">Private</span>
										<?php endif; ?>
									</div>
									<div class="vt-text-muted">
										<?php echo sprintf('Joined %s ago', VT_Text::timeAgo($community->joined_at)); ?>
									</div>
								</div>
								<div class="vt-stat vt-text-center">
									<div class="vt-stat-number vt-text-primary"><?php echo intval($community->event_count); ?></div>
									<div class="vt-stat-label">Events</div>
								</div>
							</div>

							<?php if ($community->description) : ?>
							<div class="vt-mb-4">
								<p class="vt-text-muted"><?php echo VT_Sanitize::escHtml(VT_Text::truncateWords($community->description, 15)); ?></p>
							</div>
							<?php endif; ?>

							<div class="vt-flex vt-flex-between vt-flex-wrap vt-gap">
								<div class="vt-flex vt-gap vt-flex-wrap">
									<div class="vt-stat vt-text-center">
										<div class="vt-stat-number vt-text-primary"><?php echo intval($community->member_count); ?></div>
										<div class="vt-stat-label">Members</div>
									</div>
								</div>

								<div class="vt-flex vt-gap">
									<a href="/communities/<?php echo VT_Sanitize::escHtml($community->slug); ?>" class="vt-btn">
										View
									</a>
									<?php if ($community->role === 'admin') : ?>
										<a href="/communities/<?php echo $community->slug; ?>/manage" class="vt-btn">
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
							<a href="/communities" class="vt-btn">
								Browse Communities
							</a>
							<?php if (VT_Feature_Flags::canUserCreateCommunity()) : ?>
								<a href="/communities/create" class="vt-btn">
									Create Community
								</a>
							<?php endif; ?>
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
					<?php if (!empty($community->featured_image)) : ?>
						<div class="vt-mb-4">
							<img src="<?php echo VT_Sanitize::escUrl($community->featured_image); ?>" alt="<?php echo VT_Sanitize::escHtml($community->name); ?>" style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px;">
						</div>
					<?php endif; ?>
					<div class="vt-section-header vt-flex vt-flex-between vt-mb-4">
						<h3 class="vt-heading vt-heading-sm">
							<a href="/communities/<?php echo VT_Sanitize::escHtml($community->slug); ?>" class="vt-text-primary">
								<?php echo VT_Sanitize::escHtml($community->name); ?>
							</a>
						</h3>
						<div class="vt-badge vt-badge-<?php echo $community->visibility === 'public' ? 'success' : 'secondary'; ?>">
							<?php echo VT_Sanitize::escHtml(ucfirst($community->visibility)); ?>
						</div>
					</div>
						<div class="vt-mb-4">
							<div class="vt-flex vt-gap">
								<span class="vt-text-muted"><?php echo intval($community->member_count); ?> members</span>
							</div>
						</div>

					<?php if ($community->description) : ?>
					<div class="vt-mb-4">
						<p class="vt-text-muted"><?php echo VT_Sanitize::escHtml(VT_Text::truncateWords($community->description, 20)); ?></p>
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
							<a href="/communities/<?php echo VT_Sanitize::escHtml($community->slug); ?>"
								class="vt-btn <?php echo $is_member ? 'vt-btn' : ''; ?>">
								<?php echo $is_member ? 'Member' : 'Join'; ?>
							</a>
						<?php else : ?>
							<a href="/login?redirect_to=<?php echo urlencode(VT_Router::getCurrentUri()); ?>" class="vt-btn">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Tab functionality for communities
	const communityTabs = document.querySelectorAll('[data-filter]');
	const communityTabContents = document.querySelectorAll('.vt-communities-tab-content');

	// Initialize tab functionality
	function initCommunityTabs() {
		communityTabs.forEach(tab => {
			tab.addEventListener('click', function() {
				const filter = this.getAttribute('data-filter');

				// Update active tab
				communityTabs.forEach(t => {
					t.classList.remove('is-active');
					t.setAttribute('aria-selected', 'false');
				});
				this.classList.add('is-active');
				this.setAttribute('aria-selected', 'true');

				// Show/hide content
				communityTabContents.forEach(content => {
					const tab = content.getAttribute('data-tab');
					content.style.display = (tab === filter) ? '' : 'none';
				});
			});
		});
	}

	// Initialize tabs if they exist
	if (communityTabs.length > 0) {
		initCommunityTabs();
	}

	// Join community functionality
	const joinBtns = document.querySelectorAll('.join-btn');
	joinBtns.forEach(btn => {
		btn.addEventListener('click', function(e) {
			if (this.classList.contains('member')) {
				return; // Already a member, just redirect
			}

			e.preventDefault();

			// Check if user is logged in
			if (!window.vt_user || !window.vt_user.id) {
				return; // Let the login redirect happen
			}

			const communityCard = this.closest('.community-card');
			const communityName = communityCard.querySelector('h3 a').textContent;

			if (!confirm('Join community "' + communityName + '"?')) {
				return;
			}

			// Get community ID from URL
			const communityUrl = communityCard.querySelector('h3 a').href;
			const urlParts = communityUrl.split('/');
			const communitySlug = urlParts[urlParts.length - 2] || urlParts[urlParts.length - 1];

			// For now, we'll redirect to the community page
			// In Phase 3, this will be proper AJAX
			window.location.href = communityUrl;
		});
	});
});
</script>