<?php
/**
 * VivalaTable Single Community Content Template
 * Individual community page with events, conversations, and members
 * Ported from PartyMinder WordPress plugin
 */

// Get community slug from route parameter (passed from VT_Pages::singleCommunity)
if (!isset($community_slug) || !$community_slug) {
	VT_Router::redirect('/communities');
	exit;
}

// Load managers
$community_manager = new VT_Community_Manager();
$event_manager = new VT_Event_Manager();
$conversation_manager = new VT_Conversation_Manager();

// Get community
$community = $community_manager->getCommunityBySlug($community_slug);
if (!$community) {
	http_response_code(404);
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Community Not Found</h3>
		<p class="vt-text-muted vt-mb-4">The community you're looking for could not be found.</p>
		<a href="/communities" class="vt-btn">Browse Communities</a>
	</div>
	<?php
	return;
}

// Get current user info
$current_user = vt_service('auth.service')->getCurrentUser();
$is_logged_in = vt_service('auth.service')->isLoggedIn();
$is_member = false;
$user_role = null;

if ($is_logged_in) {
	$is_member = $community_manager->isMember($community->id, $current_user->id);
	$user_role = $community_manager->getMemberRole($community->id, $current_user->id);
}

// Check if user can view this community
if ($community->privacy === 'private' && !$is_member) {
	?>
	<div class="vt-section vt-text-center">
		<h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">Private Community</h3>
		<p class="vt-text-muted vt-mb-4">This is a private community. You need to be invited to join.</p>
		<a href="/communities" class="vt-btn">Browse Public Communities</a>
	</div>
	<?php
	return;
}

// Handle join/leave actions
$messages = array();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in) {
	if (isset($_POST['action']) && vt_service('security.service')->verifyNonce($_POST['community_nonce'], 'vt_community_action')) {
		$action = $_POST['action'];

		if ($action === 'join' && !$is_member) {
			$result = $community_manager->joinCommunity($community->id, $current_user->id);
			if ($result) {
				$messages[] = 'Successfully joined the community!';
				$is_member = true;
			} else {
				$errors[] = 'Failed to join community. Please try again.';
			}
		} elseif ($action === 'leave' && $is_member) {
			$result = $community_manager->leaveCommunity($community->id, $current_user->id);
			if ($result) {
				$messages[] = 'You have left the community.';
				$is_member = false;
			} else {
				$errors[] = 'Failed to leave community. Please try again.';
			}
		}
	}
}

// Get community data
$active_tab = $_GET['tab'] ?? 'overview';
$member_count = $community_manager->getMemberCount($community->id);
$recent_events = $event_manager->getCommunityEvents($community->id, 10);
$recent_conversations = $conversation_manager->getCommunityConversations($community->id, 10);
$community_members = $is_member ? $community_manager->getCommunityMembers($community->id) : [];

// Set up template variables
$page_title = htmlspecialchars($community->name);
$page_description = htmlspecialchars($community->description ?: 'Community events and discussions');
?>

<!-- Community Navigation Tabs -->
<div class="vt-section vt-mb-4">
	<?php
	// Build tabs array for secondary navigation
	$tabs = [
		[
			'label' => 'View Community',
			'url' => '/communities/' . $community->slug,
			'active' => ($active_tab === 'overview')
		],
		[
			'label' => 'Events',
			'url' => '/communities/' . $community->slug . '?tab=events',
			'active' => ($active_tab === 'events'),
			'badge_count' => count($recent_events)
		],
		[
			'label' => 'Conversations',
			'url' => '/communities/' . $community->slug . '?tab=conversations',
			'active' => ($active_tab === 'conversations'),
			'badge_count' => count($recent_conversations)
		],
		[
			'label' => 'Members',
			'url' => '/communities/' . $community->slug . '?tab=members',
			'active' => ($active_tab === 'members'),
			'badge_count' => $member_count
		]
	];

	// Add Edit and Manage tabs if user is admin
	if ($is_member && $user_role === 'admin') {
		$tabs[] = [
			'label' => 'Edit',
			'url' => '/communities/' . $community->slug . '/edit',
			'active' => false
		];
		$tabs[] = [
			'label' => 'Manage',
			'url' => '/communities/' . $community->slug . '/manage',
			'active' => false
		];
	}

	include VT_INCLUDES_DIR . '/../templates/partials/secondary-nav.php';
	?>
</div>

<!-- Success/Error Messages -->
<?php if (!empty($messages)) : ?>
	<div class="vt-alert vt-alert-success vt-mb-4">
		<?php foreach ($messages as $message) : ?>
			<p><?php echo htmlspecialchars($message); ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if (!empty($errors)) : ?>
	<div class="vt-alert vt-alert-error vt-mb-4">
		<?php foreach ($errors as $error) : ?>
			<p><?php echo htmlspecialchars($error); ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<!-- Community Header -->
<div class="vt-section vt-mb-4">
	<?php if ($community->featured_image) : ?>
		<div class="vt-community-cover vt-mb-4">
			<img src="<?php echo htmlspecialchars($community->featured_image); ?>"
				 alt="<?php echo htmlspecialchars($community->name); ?>"
				 class="vt-cover-image">
		</div>
	<?php endif; ?>

	<div class="vt-flex vt-flex-between vt-flex-wrap vt-gap vt-mb-2">
		<div class="vt-flex-1">
			<h1 class="vt-heading vt-heading-xl vt-text-primary">
				<?php echo htmlspecialchars($community->name); ?>
			</h1>
		</div>
		<div class="vt-flex vt-gap" style="align-items: flex-start;">
			<?php if ($community->privacy === 'private') : ?>
				<span class="vt-badge vt-badge-warning">Private</span>
			<?php else : ?>
				<span class="vt-badge vt-badge-success">Public</span>
			<?php endif; ?>
			<?php if ($is_member && $user_role) : ?>
				<span class="vt-badge vt-badge-primary"><?php echo ucfirst($user_role); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<?php if ($community->description) : ?>
		<p class="vt-text-muted">
			<?php echo nl2br(htmlspecialchars($community->description)); ?>
		</p>
	<?php endif; ?>
</div>

<!-- Tab Content -->
<?php if ($active_tab === 'overview') : ?>
	<!-- Overview Tab -->
	<div class="vt-section">
		<h3 class="vt-heading vt-heading-md vt-mb-4">Community Stats</h3>
		<div class="vt-grid vt-grid-3 vt-gap-4 vt-mb-6">
			<div class="vt-stat-card">
				<div class="vt-stat-number vt-text-primary"><?php echo $member_count; ?></div>
				<div class="vt-stat-label">Member<?php echo $member_count !== 1 ? 's' : ''; ?></div>
			</div>
			<div class="vt-stat-card">
				<div class="vt-stat-number vt-text-primary"><?php echo count($recent_events); ?></div>
				<div class="vt-stat-label">Event<?php echo count($recent_events) !== 1 ? 's' : ''; ?></div>
			</div>
			<div class="vt-stat-card">
				<div class="vt-stat-number vt-text-primary"><?php echo count($recent_conversations); ?></div>
				<div class="vt-stat-label">Conversation<?php echo count($recent_conversations) !== 1 ? 's' : ''; ?></div>
			</div>
		</div>

		<?php if (!empty($recent_events) || !empty($recent_conversations)) : ?>
			<h3 class="vt-heading vt-heading-md vt-mb-4">Recent Activity</h3>

			<?php if (!empty($recent_events)) : ?>
				<div class="vt-mb-4">
					<h4 class="vt-heading vt-heading-sm vt-mb-2">Upcoming Events</h4>
					<?php foreach (array_slice($recent_events, 0, 3) as $event) : ?>
						<div class="vt-mb-2">
							<a href="/events/<?php echo htmlspecialchars($event->slug); ?>" class="vt-text-primary">
								<?php echo htmlspecialchars($event->title); ?>
							</a>
							<span class="vt-text-muted"> - <?php echo date('M j, Y', strtotime($event->event_date)); ?></span>
						</div>
					<?php endforeach; ?>
					<?php if (count($recent_events) > 3) : ?>
						<a href="/communities/<?php echo $community->slug; ?>?tab=events" class="vt-text-primary">View all events →</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if (!empty($recent_conversations)) : ?>
				<div class="vt-mb-4">
					<h4 class="vt-heading vt-heading-sm vt-mb-2">Recent Discussions</h4>
					<?php foreach (array_slice($recent_conversations, 0, 3) as $conversation) : ?>
						<div class="vt-mb-2">
							<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-text-primary">
								<?php echo htmlspecialchars($conversation->title); ?>
							</a>
							<span class="vt-text-muted"> - <?php echo $conversation->reply_count; ?> replies</span>
						</div>
					<?php endforeach; ?>
					<?php if (count($recent_conversations) > 3) : ?>
						<a href="/communities/<?php echo $community->slug; ?>?tab=conversations" class="vt-text-primary">View all discussions →</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="vt-text-center vt-p-4">
				<p class="vt-text-muted vt-mb-4">No activity yet in this community.</p>
				<?php if ($is_member) : ?>
					<div class="vt-flex vt-gap vt-justify-center">
						<?php if ($community_manager->canCreateEvent($community->id)) : ?>
							<a href="/events/create?community_id=<?php echo $community->id; ?>" class="vt-btn">Create Event</a>
						<?php endif; ?>
						<a href="/conversations/create?community_id=<?php echo $community->id; ?>" class="vt-btn">Start Discussion</a>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

<?php elseif ($active_tab === 'events') : ?>
	<!-- Events Tab -->
	<div class="vt-section">
		<div class="vt-flex vt-flex-between vt-mb-4">
			<h3 class="vt-heading vt-heading-md">Community Events</h3>
			<?php if ($community_manager->canCreateEvent($community->id)) : ?>
				<a href="/events/create?community_id=<?php echo $community->id; ?>" class="vt-btn">
					Create Event
				</a>
			<?php endif; ?>
		</div>

		<?php if (!empty($recent_events)) : ?>
			<div class="vt-grid vt-gap">
				<?php foreach ($recent_events as $event) : ?>
					<div class="vt-card">
						<div class="vt-card-body">
							<h4 class="vt-heading vt-heading-sm vt-mb-2">
								<a href="/events/<?php echo htmlspecialchars($event->slug); ?>" class="vt-text-primary">
									<?php echo htmlspecialchars($event->title); ?>
								</a>
							</h4>
							<p class="vt-text-muted vt-mb-2">
								<?php echo date('F j, Y \a\t g:i A', strtotime($event->event_date)); ?>
							</p>
							<?php if ($event->venue_info) : ?>
								<p class="vt-text-muted vt-mb-2">
									📍 <?php echo htmlspecialchars($event->venue_info); ?>
								</p>
							<?php endif; ?>
							<?php if ($event->description) : ?>
								<p class="vt-text-muted">
									<?php echo htmlspecialchars(VT_Text::truncate($event->description, 100)); ?>
								</p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="vt-text-center vt-p-4">
				<p class="vt-text-muted vt-mb-4">No events have been created yet.</p>
				<?php if ($community_manager->canCreateEvent($community->id)) : ?>
					<a href="/events/create?community_id=<?php echo $community->id; ?>" class="vt-btn">
						Create the First Event
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

<?php elseif ($active_tab === 'conversations') : ?>
	<!-- Conversations Tab -->
	<div class="vt-section">
		<div class="vt-flex vt-flex-between vt-mb-4">
			<h3 class="vt-heading vt-heading-md">Community Discussions</h3>
			<?php if ($is_member) : ?>
				<a href="/conversations/create?community_id=<?php echo $community->id; ?>" class="vt-btn">
					Start Discussion
				</a>
			<?php endif; ?>
		</div>

		<?php if (!empty($recent_conversations)) : ?>
			<div class="vt-conversation-list">
				<?php foreach ($recent_conversations as $conversation) : ?>
					<div class="vt-conversation-item">
						<h4 class="vt-heading vt-heading-sm vt-mb-2">
							<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-text-primary">
								<?php echo htmlspecialchars($conversation->title); ?>
							</a>
						</h4>
						<p class="vt-text-muted vt-mb-2">
							by <?php echo htmlspecialchars($conversation->author_name); ?> •
							<?php echo date('M j, Y', strtotime($conversation->created_at)); ?>
						</p>
						<p class="vt-text-muted">
							<?php echo htmlspecialchars(VT_Text::truncate($conversation->content, 150)); ?>
						</p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="vt-text-center vt-p-4">
				<p class="vt-text-muted vt-mb-4">No discussions have been started yet.</p>
				<?php if ($is_member) : ?>
					<a href="/conversations/create?community_id=<?php echo $community->id; ?>" class="vt-btn">
						Start the First Discussion
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

<?php elseif ($active_tab === 'members') : ?>
	<!-- Members Tab -->
	<div class="vt-section">
		<h3 class="vt-heading vt-heading-md vt-mb-4">Community Members</h3>

		<?php if ($is_member && !empty($community_members)) : ?>
			<div class="vt-grid vt-grid-2 vt-gap-4">
				<?php foreach ($community_members as $member) : ?>
					<div class="vt-card">
						<div class="vt-card-body">
							<div class="vt-flex vt-flex-between vt-items-center">
								<div class="vt-flex-1">
									<?php
									$user_id = $member->user_id;
									$args = array(
										'avatar_size' => 48,
										'show_avatar' => true,
										'show_name' => true,
										'link_profile' => true,
										'class' => 'vt-member-display'
									);
									include VT_INCLUDES_DIR . '/../templates/partials/member-display.php';
									?>
									<div class="vt-mt-2 vt-flex vt-gap vt-items-center">
										<span class="vt-badge vt-badge-<?php echo $member->role === 'admin' ? 'primary' : 'secondary'; ?>">
											<?php echo htmlspecialchars(ucfirst($member->role)); ?>
										</span>
										<span class="vt-text-muted vt-text-sm">
											Joined <?php echo date('M j, Y', strtotime($member->joined_at)); ?>
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php elseif (!$is_member) : ?>
			<div class="vt-text-center vt-p-4">
				<p class="vt-text-muted vt-mb-4">Join this community to see its members.</p>
			</div>
		<?php else : ?>
			<div class="vt-text-center vt-p-4">
				<p class="vt-text-muted">No members yet.</p>
			</div>
		<?php endif; ?>
	</div>

<?php endif; ?>