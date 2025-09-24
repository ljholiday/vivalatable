<?php
/**
 * Single Conversation Page
 */

$conversation_slug = $_GET['conversation_slug'] ?? '';

if (!$conversation_slug) {
    http_response_code(404);
    include __DIR__ . '/../404.php';
    exit;
}

$conversation_manager = new ConversationManager();
$conversation = $conversation_manager->get_conversation($conversation_slug);

if (!$conversation) {
    http_response_code(404);
    include __DIR__ . '/../404.php';
    exit;
}

$page_title = $conversation->title . ' - VivalaTable';
$page_description = $conversation->content ? substr($conversation->content, 0, 160) : 'Join the conversation';

ob_start();
?>

<div class="pm-container">
    <div class="pm-conversation-single">
        <div class="pm-conversation-header">
            <h1 class="pm-heading pm-heading-lg"><?php echo vt_escape_html($conversation->title); ?></h1>
            <div class="pm-conversation-meta">
                <span class="pm-conversation-author">
                    Started by <?php echo vt_escape_html($conversation->creator_name ?? 'Unknown'); ?>
                </span>
                <span class="pm-conversation-date">
                    <?php echo vt_time_ago($conversation->created_at); ?>
                </span>
                <?php if ($conversation->community_name): ?>
                    <span class="pm-conversation-community">
                        in <a href="<?php echo vt_base_url('/communities/' . $conversation->community_slug); ?>">
                            <?php echo vt_escape_html($conversation->community_name); ?>
                        </a>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="pm-conversation-content">
            <?php if ($conversation->content): ?>
                <div class="pm-conversation-body">
                    <p><?php echo vt_escape_html($conversation->content); ?></p>
                </div>
            <?php endif; ?>

            <div class="pm-conversation-stats">
                <div class="pm-stats-inline">
                    <span class="pm-stat-item">
                        <?php echo (int)$conversation->reply_count; ?> replies
                    </span>
                    <span class="pm-stat-item">
                        <?php echo (int)$conversation->participant_count; ?> participants
                    </span>
                </div>
            </div>
        </div>

        <div class="pm-conversation-replies">
            <h2 class="pm-heading pm-heading-md">Replies</h2>

            <?php if (is_user_logged_in()): ?>
                <div class="pm-reply-form">
                    <form method="POST" action="#">
                        <div class="pm-form-row">
                            <textarea name="content" class="pm-form-textarea" rows="4"
                                      placeholder="Add your reply..."></textarea>
                        </div>
                        <div class="pm-form-actions">
                            <button type="submit" class="pm-btn pm-btn-primary">Reply</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="pm-auth-prompt">
                    <p class="pm-text-muted">
                        <a href="<?php echo vt_base_url('/login'); ?>">Sign in</a> to join this conversation.
                    </p>
                </div>
            <?php endif; ?>

            <div class="pm-replies-placeholder">
                <p class="pm-text-muted">No replies yet. Be the first to respond!</p>
            </div>
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