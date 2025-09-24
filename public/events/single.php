<?php
/**
 * Single Event Page
 */

$event_slug = $_GET['event_slug'] ?? '';

if (!$event_slug) {
    http_response_code(404);
    include __DIR__ . '/../404.php';
    exit;
}

$event_manager = new EventManager();
$event = $event_manager->get_event($event_slug);

if (!$event) {
    http_response_code(404);
    include __DIR__ . '/../404.php';
    exit;
}

$page_title = $event->title . ' - VivalaTable';
$page_description = $event->description ? substr($event->description, 0, 160) : 'Event details';

ob_start();
?>

<div class="pm-container">
    <div class="pm-event-single">
        <div class="pm-event-header">
            <h1 class="pm-heading pm-heading-lg"><?php echo vt_escape_html($event->title); ?></h1>
            <div class="pm-event-meta">
                <span class="pm-event-date">
                    <?php echo vt_format_date($event->event_date, 'l, F j, Y \a\t g:i A'); ?>
                </span>
                <span class="pm-event-host">
                    Hosted by <?php echo vt_escape_html($event->host_name ?? 'Unknown'); ?>
                </span>
            </div>
        </div>

        <div class="pm-event-content">
            <?php if ($event->description): ?>
                <div class="pm-event-description">
                    <h2 class="pm-heading pm-heading-md">About This Event</h2>
                    <p><?php echo vt_escape_html($event->description); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($event->location): ?>
                <div class="pm-event-location">
                    <h3 class="pm-heading pm-heading-sm">Location</h3>
                    <p><?php echo vt_escape_html($event->location); ?></p>
                </div>
            <?php endif; ?>

            <div class="pm-event-stats">
                <h3 class="pm-heading pm-heading-sm">Attendance</h3>
                <div class="pm-stats-grid">
                    <div class="pm-stat">
                        <div class="pm-stat-number"><?php echo (int)$event->attending_count; ?></div>
                        <div class="pm-stat-label">Attending</div>
                    </div>
                    <div class="pm-stat">
                        <div class="pm-stat-number"><?php echo (int)$event->maybe_count; ?></div>
                        <div class="pm-stat-label">Maybe</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pm-event-actions">
            <?php if (is_user_logged_in()): ?>
                <button class="pm-btn pm-btn-primary">RSVP</button>
            <?php else: ?>
                <p class="pm-text-muted">
                    <a href="<?php echo vt_base_url('/login'); ?>">Sign in</a> to RSVP to this event.
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