<?php
/**
 * Single Community Page
 */

$community_slug = $_GET['community_slug'] ?? '';

if (!$community_slug) {
    http_response_code(404);
    include __DIR__ . '/../404.php';
    exit;
}

$community_manager = new CommunityManager();
$community = $community_manager->get_community($community_slug);

if (!$community) {
    http_response_code(404);
    include __DIR__ . '/../404.php';
    exit;
}

$page_title = $community->name . ' - VivalaTable';
$page_description = $community->description ? substr($community->description, 0, 160) : 'Community details';

ob_start();
?>

<div class="pm-container">
    <div class="pm-community-single">
        <div class="pm-community-header">
            <h1 class="pm-heading pm-heading-lg"><?php echo vt_escape_html($community->name); ?></h1>
            <div class="pm-community-meta">
                <span class="pm-community-type">
                    <?php echo vt_escape_html(ucfirst($community->community_type ?? 'general')); ?> Community
                </span>
                <span class="pm-community-members">
                    <?php echo (int)$community->member_count; ?> members
                </span>
            </div>
        </div>

        <div class="pm-community-content">
            <?php if ($community->description): ?>
                <div class="pm-community-description">
                    <h2 class="pm-heading pm-heading-md">About This Community</h2>
                    <p><?php echo vt_escape_html($community->description); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($community->location): ?>
                <div class="pm-community-location">
                    <h3 class="pm-heading pm-heading-sm">Location</h3>
                    <p><?php echo vt_escape_html($community->location); ?></p>
                </div>
            <?php endif; ?>

            <div class="pm-community-stats">
                <h3 class="pm-heading pm-heading-sm">Community Stats</h3>
                <div class="pm-stats-grid">
                    <div class="pm-stat">
                        <div class="pm-stat-number"><?php echo (int)$community->member_count; ?></div>
                        <div class="pm-stat-label">Members</div>
                    </div>
                    <div class="pm-stat">
                        <div class="pm-stat-number"><?php echo (int)$community->upcoming_events_count; ?></div>
                        <div class="pm-stat-label">Upcoming Events</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pm-community-actions">
            <?php if (is_user_logged_in()): ?>
                <button class="pm-btn pm-btn-primary">Join Community</button>
            <?php else: ?>
                <p class="pm-text-muted">
                    <a href="<?php echo vt_base_url('/login'); ?>">Sign in</a> to join this community.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

vt_load_template('base/page', [
    'page_title' => $page_title,
    'page_description' => $page_description,
    'content' => $content
]);
?>