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
$public_communities = $db->get_results(
    $db->prepare(
        "SELECT * FROM $communities_table WHERE visibility = 'public' AND is_active = 1 ORDER BY created_at DESC LIMIT %d",
        20
    )
);
$user_communities = array();

if ($user_logged_in) {
	$user_communities = $community_manager->get_user_communities($current_user->id);
}

// Set up template variables
$page_title = 'Communities';
$page_description = 'Join communities of fellow hosts and guests to plan amazing events together';
?>

<!-- Secondary Menu Bar -->
<div class="pm-section pm-mb-4">
    <div class="pm-flex pm-gap-4 pm-flex-wrap">
        <?php if ($user_logged_in): ?>
            <a href="/communities/create" class="pm-btn">
                Create Community
            </a>
        <?php endif; ?>
        <a href="/events" class="pm-btn pm-btn-secondary">
            Browse Events
        </a>
        <a href="/conversations" class="pm-btn pm-btn-secondary">
            Conversations
        </a>
    </div>
</div>

<!-- Success Message for Community Deletion -->
<?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1') : ?>
	<div class="pm-alert pm-alert-success pm-mb-4">
		Community has been successfully deleted.
	</div>
<?php endif; ?>

<!-- Community Filters/Tabs -->
<?php if ($user_logged_in) : ?>
<div class="pm-section pm-mb-4">
	<div class="pm-conversations-nav pm-flex pm-gap-4 pm-flex-wrap">
		<!-- Community Type Filters -->
		<button class="pm-btn is-active" data-filter="my-communities" role="tab" aria-selected="true" aria-controls="pm-communities-list">
			My Communities
		</button>
		<button class="pm-btn" data-filter="all-communities" role="tab" aria-selected="false" aria-controls="pm-communities-list">
			All Communities
		</button>
	</div>
</div>
<?php endif; ?>

<div class="pm-section">
	<div id="pm-communities-list" class="pm-grid pm-gap">
		<?php if ($user_logged_in) : ?>
			<!-- My Communities Tab Content (Default) -->
			<div class="pm-communities-tab-content" data-tab="my-communities">
				<?php if (!empty($user_communities)) : ?>
					<?php foreach ($user_communities as $community) : ?>
						<div class="pm-section pm-border pm-p-4">
							<?php if (!empty($community->featured_image)) : ?>
								<div class="pm-mb-4">
									<img src="<?php echo VT_Sanitize::escUrl($community->featured_image); ?>" alt="<?php echo VT_Sanitize::escHtml($community->name); ?>" style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px;">
								</div>
							<?php endif; ?>
							<div class="pm-flex pm-flex-between pm-mb-4">
								<div class="pm-flex-1">
									<h3 class="pm-heading pm-heading-sm pm-mb-2">
										<a href="/communities/<?php echo VT_Sanitize::escHtml($community->slug); ?>" class="pm-text-primary">
											<?php echo VT_Sanitize::escHtml($community->name); ?>
										</a>
									</h3>
									<div class="pm-flex pm-gap pm-flex-wrap pm-mb-2">
										<span class="pm-badge pm-badge-<?php echo $community->role === 'admin' ? 'primary' : 'success'; ?>">
											<?php echo VT_Sanitize::escHtml(ucfirst($community->role)); ?>
										</span>
										<?php if ($community->visibility === 'private') : ?>
											<span class="pm-badge pm-badge-secondary">Private</span>
										<?php endif; ?>
									</div>
									<div class="pm-text-muted">
										<?php echo sprintf('Joined %s ago', VT_Text::timeAgo($community->joined_at)); ?>
									</div>
								</div>
								<div class="pm-stat pm-text-center">
									<div class="pm-stat-number pm-text-primary"><?php echo intval($community->event_count); ?></div>
									<div class="pm-stat-label">Events</div>
								</div>
							</div>

							<?php if ($community->description) : ?>
							<div class="pm-mb-4">
								<p class="pm-text-muted"><?php echo VT_Sanitize::escHtml(VT_Text::truncateWords($community->description, 15)); ?></p>
							</div>
							<?php endif; ?>

							<div class="pm-flex pm-flex-between pm-flex-wrap pm-gap">
								<div class="pm-flex pm-gap pm-flex-wrap">
									<div class="pm-stat pm-text-center">
										<div class="pm-stat-number pm-text-primary"><?php echo intval($community->member_count); ?></div>
										<div class="pm-stat-label">Members</div>
									</div>
								</div>

								<div class="pm-flex pm-gap">
									<a href="/communities/<?php echo VT_Sanitize::escHtml($community->slug); ?>" class="pm-btn">
										View
									</a>
									<?php if ($community->role === 'admin') : ?>
										<a href="/communities/<?php echo intval($community->id); ?>/manage" class="pm-btn">
											Manage
										</a>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="pm-text-center pm-p-4">
						<p class="pm-text-muted pm-mb-4">You haven't joined any communities yet.</p>
						<div class="pm-flex pm-gap pm-justify-center">
							<a href="/communities" class="pm-btn">
								Browse Communities
							</a>
							<?php if (VT_Feature_Flags::can_user_create_community()) : ?>
								<a href="/communities/create" class="pm-btn">
									Create Community
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- All Communities Tab Content -->
		<div class="pm-communities-tab-content" data-tab="all-communities" <?php echo $user_logged_in ? 'style="display: none;"' : ''; ?>>
			<?php if (!empty($public_communities)) : ?>
				<?php foreach ($public_communities as $community) : ?>
				<div class="pm-section pm-border pm-p-4">
					<?php if (!empty($community->featured_image)) : ?>
						<div class="pm-mb-4">
							<img src="<?php echo VT_Sanitize::escUrl($community->featured_image); ?>" alt="<?php echo VT_Sanitize::escHtml($community->name); ?>" style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px;">
						</div>
					<?php endif; ?>
					<div class="pm-section-header pm-flex pm-flex-between pm-mb-4">
						<h3 class="pm-heading pm-heading-sm">
							<a href="/communities/<?php echo VT_Sanitize::escHtml($community->slug); ?>" class="pm-text-primary">
								<?php echo VT_Sanitize::escHtml($community->name); ?>
							</a>
						</h3>
						<div class="pm-badge pm-badge-<?php echo $community->visibility === 'public' ? 'success' : 'secondary'; ?>">
							<?php echo VT_Sanitize::escHtml(ucfirst($community->visibility)); ?>
						</div>
					</div>
						<div class="pm-mb-4">
							<div class="pm-flex pm-gap">
								<span class="pm-text-muted"><?php echo intval($community->member_count); ?> members</span>
							</div>
						</div>

					<?php if ($community->description) : ?>
					<div class="pm-mb-4">
						<p class="pm-text-muted"><?php echo VT_Sanitize::escHtml(VT_Text::truncateWords($community->description, 20)); ?></p>
					</div>
					<?php endif; ?>

					<div class="pm-flex pm-flex-between pm-mt-4">
						<div class="pm-stat">
							<div class="pm-stat-number pm-text-primary"><?php echo intval($community->event_count); ?></div>
							<div class="pm-text-muted">Events</div>
						</div>

						<?php if ($user_logged_in) : ?>
							<?php
							$is_member = $community_manager->is_member($community->id, $current_user->id);
							?>
							<a href="/communities/<?php echo VT_Sanitize::escHtml($community->slug); ?>"
								class="pm-btn <?php echo $is_member ? 'pm-btn' : ''; ?>">
								<?php echo $is_member ? 'Member' : 'Join'; ?>
							</a>
						<?php else : ?>
							<a href="/login?redirect_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="pm-btn">
								Login to Join
							</a>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="pm-text-center pm-p-4">
					<p class="pm-text-muted pm-mb-4">No public communities yet.</p>
					<p class="pm-text-muted">Be the first to create a community!</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Tab functionality for communities
	const communityTabs = document.querySelectorAll('[data-filter]');
	const communityTabContents = document.querySelectorAll('.pm-communities-tab-content');

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