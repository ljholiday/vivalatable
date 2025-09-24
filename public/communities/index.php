<?php
/**
 * Communities Listing Page
 */

$page_title = 'Communities - VivalaTable';
$page_description = 'Discover and join communities that match your interests';

$community_manager = new CommunityManager();
$communities = $community_manager->get_communities(['privacy_level' => 'public'], 20);

ob_start();
?>

<div class="pm-container">
    <div class="pm-communities-page">
        <div class="pm-page-header">
            <h1 class="pm-heading pm-heading-lg">Communities</h1>
            <?php if (is_user_logged_in()): ?>
                <a href="<?php echo vt_base_url('/communities/create'); ?>" class="pm-btn pm-btn-primary">Create Community</a>
            <?php endif; ?>
        </div>

        <?php if (empty($communities)): ?>
            <div class="pm-empty-state">
                <h2 class="pm-heading pm-heading-md">No communities yet</h2>
                <p class="pm-text-muted">Be the first to create a community!</p>
                <?php if (is_user_logged_in()): ?>
                    <a href="<?php echo vt_base_url('/communities/create'); ?>" class="pm-btn pm-btn-primary">Create Community</a>
                <?php else: ?>
                    <a href="<?php echo vt_base_url('/register'); ?>" class="pm-btn pm-btn-primary">Sign Up to Create Communities</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="pm-communities-grid">
                <?php foreach ($communities as $community): ?>
                    <div class="pm-community-card">
                        <div class="pm-community-header">
                            <h3 class="pm-community-name">
                                <a href="<?php echo vt_base_url('/communities/' . $community->slug); ?>">
                                    <?php echo vt_escape_html($community->name); ?>
                                </a>
                            </h3>
                            <div class="pm-community-type">
                                <?php echo vt_escape_html(ucfirst($community->community_type ?? 'general')); ?>
                            </div>
                        </div>

                        <div class="pm-community-body">
                            <?php if ($community->description): ?>
                                <p class="pm-community-description">
                                    <?php echo vt_escape_html(substr($community->description, 0, 150)); ?>
                                    <?php if (strlen($community->description) > 150): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($community->location): ?>
                                <div class="pm-community-location">
                                    <strong>Location:</strong> <?php echo vt_escape_html($community->location); ?>
                                </div>
                            <?php endif; ?>

                            <div class="pm-community-meta">
                                <span class="pm-community-members">
                                    <?php echo (int)$community->member_count; ?> members
                                </span>
                                <span class="pm-community-creator">
                                    Created by <?php echo vt_escape_html($community->creator_name ?? 'Unknown'); ?>
                                </span>
                            </div>
                        </div>

                        <div class="pm-community-actions">
                            <a href="<?php echo vt_base_url('/communities/' . $community->slug); ?>" class="pm-btn pm-btn-sm">
                                View Community
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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