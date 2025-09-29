<?php
/**
 * VivalaTable Single Event Content Template
 * Display individual event details
 */

// Get event data
if (!isset($event_slug)) {
    echo '<div class="vt-alert vt-alert-error">Event not found.</div>';
    return;
}

$event_manager = new VT_Event_Manager();
$event = $event_manager->getEventBySlug($event_slug);

if (!$event) {
    echo '<div class="vt-alert vt-alert-error">Event not found.</div>';
    return;
}

// Check if user can view this event
if (!$event_manager->canUserViewEvent($event)) {
    echo '<div class="vt-alert vt-alert-error">You do not have permission to view this event.</div>';
    return;
}

$current_user = VT_Auth::getCurrentUser();
$is_host = $current_user && $current_user->id == $event->author_id;
?>

<!-- Event Header -->
<div class="vt-section">
    <div class="vt-flex vt-justify-between vt-align-center vt-mb-4">
        <div>
            <h1 class="vt-heading vt-heading-lg vt-mb-2"><?php echo VT_Sanitize::html($event->title); ?></h1>
            <div class="vt-flex vt-gap vt-items-center vt-mb-2">
                <span class="vt-text-muted"><?php echo date('F j, Y', strtotime($event->event_date)); ?></span>
                <?php if ($event->event_time): ?>
                    <span class="vt-text-muted">at <?php echo VT_Sanitize::html($event->event_time); ?></span>
                <?php endif; ?>
                <span class="vt-badge vt-badge-<?php echo $event->privacy === 'private' ? 'secondary' : 'success'; ?>">
                    <?php echo VT_Sanitize::escHtml(ucfirst($event->privacy)); ?>
                </span>
            </div>
        </div>

        <?php if ($is_host): ?>
            <div>
                <a href="/events/<?php echo $event->slug; ?>/edit" class="vt-btn vt-btn-secondary vt-btn-sm">Edit Event</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($event->venue_info): ?>
        <div class="vt-mb-4">
            <strong>Location:</strong> <?php echo VT_Sanitize::html($event->venue_info); ?>
        </div>
    <?php endif; ?>

    <div class="vt-text-content">
        <?php echo VT_Sanitize::post($event->description); ?>
    </div>
</div>

<!-- Event Stats -->
<div class="vt-section vt-bg-light">
    <h3 class="vt-heading vt-heading-sm vt-mb-3">Event Details</h3>

    <div class="vt-grid vt-grid-2 vt-gap-4">
        <div>
            <strong>Privacy:</strong> <?php echo ucfirst($event->privacy); ?>
        </div>

        <?php if ($event->guest_limit > 0): ?>
            <div>
                <strong>Guest Limit:</strong> <?php echo $event->guest_limit; ?>
            </div>
        <?php endif; ?>

        <div>
            <strong>RSVPs:</strong>
            <?php
            $stats = $event->guest_stats ?? ['attending' => 0, 'declined' => 0, 'pending' => 0];
            echo $stats['attending'] . ' attending';
            if ($stats['declined'] > 0) echo ', ' . $stats['declined'] . ' declined';
            if ($stats['pending'] > 0) echo ', ' . $stats['pending'] . ' pending';
            ?>
        </div>

        <div>
            <strong>Created:</strong> <?php echo date('M j, Y', strtotime($event->created_at ?? 'now')); ?>
        </div>
    </div>
</div>

<!-- RSVP Section -->
<?php if (VT_Auth::isLoggedIn()): ?>
    <div class="vt-section">
        <h3 class="vt-heading vt-heading-sm vt-mb-3">RSVP</h3>

        <?php if ($is_host): ?>
            <div class="vt-alert vt-alert-info">
                <p>You are the host of this event.</p>
            </div>
        <?php else: ?>
            <!-- RSVP Form would go here -->
            <div class="vt-alert vt-alert-info">
                <p>RSVP functionality coming soon.</p>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="vt-section">
        <div class="vt-alert vt-alert-info">
            <p><a href="/login">Log in</a> to RSVP to this event.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Host Actions -->
<?php if ($is_host): ?>
    <div class="vt-section">
        <h3 class="vt-heading vt-heading-sm vt-mb-3">Host Actions</h3>

        <div class="vt-flex vt-gap-2">
            <a href="/events/<?php echo $event->slug; ?>/edit" class="vt-btn vt-btn-primary">Edit Event</a>
            <a href="/events/<?php echo $event->slug; ?>/invite" class="vt-btn vt-btn-secondary">Invite Guests</a>
            <a href="/events/<?php echo $event->slug; ?>/manage" class="vt-btn vt-btn-outline">Manage RSVPs</a>
        </div>
    </div>
<?php endif; ?>