<?php
/**
 * Conversations Listing Page
 */

$page_title = 'Conversations - VivalaTable';
$page_description = 'Join discussions and connect with the community';

$conversation_manager = new ConversationManager();
$conversations = $conversation_manager->get_conversations(['privacy_level' => 'public'], 20);

ob_start();
?>

<div class="pm-container">
    <div class="pm-conversations-page">
        <div class="pm-page-header">
            <h1 class="pm-heading pm-heading-lg">Conversations</h1>
            <?php if (is_user_logged_in()): ?>
                <a href="<?php echo vt_base_url('/conversations/create'); ?>" class="pm-btn pm-btn-primary">Start Discussion</a>
            <?php endif; ?>
        </div>

        <?php if (empty($conversations)): ?>
            <div class="pm-empty-state">
                <h2 class="pm-heading pm-heading-md">No conversations yet</h2>
                <p class="pm-text-muted">Be the first to start a discussion!</p>
                <?php if (is_user_logged_in()): ?>
                    <a href="<?php echo vt_base_url('/conversations/create'); ?>" class="pm-btn pm-btn-primary">Start Discussion</a>
                <?php else: ?>
                    <a href="<?php echo vt_base_url('/register'); ?>" class="pm-btn pm-btn-primary">Sign Up to Join Conversations</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="pm-conversations-list">
                <?php foreach ($conversations as $conversation): ?>
                    <div class="pm-conversation-item">
                        <div class="pm-conversation-content">
                            <h3 class="pm-conversation-title">
                                <a href="<?php echo vt_base_url('/conversations/' . $conversation->slug); ?>">
                                    <?php echo vt_escape_html($conversation->title); ?>
                                </a>
                            </h3>

                            <?php if ($conversation->content): ?>
                                <p class="pm-conversation-excerpt">
                                    <?php echo vt_escape_html(substr($conversation->content, 0, 200)); ?>
                                    <?php if (strlen($conversation->content) > 200): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <div class="pm-conversation-meta">
                                <span class="pm-conversation-author">
                                    Started by <?php echo vt_escape_html($conversation->creator_name ?? 'Unknown'); ?>
                                </span>
                                <span class="pm-conversation-date">
                                    <?php echo vt_time_ago($conversation->created_at); ?>
                                </span>
                                <?php if ($conversation->community_name): ?>
                                    <span class="pm-conversation-community">
                                        in <?php echo vt_escape_html($conversation->community_name); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pm-conversation-stats">
                            <div class="pm-stat">
                                <div class="pm-stat-number"><?php echo (int)$conversation->reply_count; ?></div>
                                <div class="pm-stat-label">replies</div>
                            </div>
                            <div class="pm-stat">
                                <div class="pm-stat-number"><?php echo (int)$conversation->participant_count; ?></div>
                                <div class="pm-stat-label">participants</div>
                            </div>
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