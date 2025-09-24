<?php
/**
 * Events Listing Page
 */

$page_title = 'Events - VivalaTable';
$page_description = 'Discover upcoming events in your area';

$event_manager = new EventManager();
$events = $event_manager->get_events(['upcoming' => true], 20);

ob_start();
?>

<div class="pm-container">
    <div class="pm-events-page">
        <div class="pm-page-header">
            <h1 class="pm-heading pm-heading-lg">Upcoming Events</h1>
            <?php if (is_user_logged_in()): ?>
                <a href="<?php echo vt_base_url('/events/create'); ?>" class="pm-btn pm-btn-primary">Create Event</a>
            <?php endif; ?>
        </div>

        <?php if (empty($events)): ?>
            <div class="pm-empty-state">
                <h2 class="pm-heading pm-heading-md">No upcoming events</h2>
                <p class="pm-text-muted">Be the first to create an event in your area!</p>
                <?php if (is_user_logged_in()): ?>
                    <a href="<?php echo vt_base_url('/events/create'); ?>" class="pm-btn pm-btn-primary">Create Event</a>
                <?php else: ?>
                    <a href="<?php echo vt_base_url('/register'); ?>" class="pm-btn pm-btn-primary">Sign Up to Create Events</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="pm-events-grid">
                <?php foreach ($events as $event): ?>
                    <div class="pm-event-card">
                        <div class="pm-event-header">
                            <h3 class="pm-event-title">
                                <a href="<?php echo vt_base_url('/events/' . $event->id); ?>">
                                    <?php echo vt_escape_html($event->title); ?>
                                </a>
                            </h3>
                            <div class="pm-event-date">
                                <?php echo vt_format_date($event->event_date, 'M j, Y \a\t g:i A'); ?>
                            </div>
                        </div>

                        <div class="pm-event-body">
                            <?php if ($event->description): ?>
                                <p class="pm-event-description">
                                    <?php echo vt_escape_html(substr($event->description, 0, 150)); ?>
                                    <?php if (strlen($event->description) > 150): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($event->location): ?>
                                <div class="pm-event-location">
                                    <strong>Location:</strong> <?php echo vt_escape_html($event->location); ?>
                                </div>
                            <?php endif; ?>

                            <div class="pm-event-meta">
                                <span class="pm-event-host">
                                    Hosted by <?php echo vt_escape_html($event->host_name ?? 'Unknown'); ?>
                                </span>
                                <?php if ($event->attending_count > 0): ?>
                                    <span class="pm-event-attendance">
                                        <?php echo (int)$event->attending_count; ?> attending
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pm-event-actions">
                            <a href="<?php echo vt_base_url('/events/' . $event->id); ?>" class="pm-btn pm-btn-sm">
                                View Details
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