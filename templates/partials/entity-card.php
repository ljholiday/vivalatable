<?php
/**
 * VivalaTable Entity Card Partial
 * Reusable card component for Events, Conversations, and Communities
 *
 * @param object $entity The entity object (event, conversation, or community)
 * @param string $entity_type Type of entity ('event', 'conversation', 'community')
 * @param array $badges Array of badge objects with 'label' and 'class' properties
 * @param array $stats Array of stat objects with 'label', 'value', and optional 'icon' properties
 * @param array $actions Array of action objects with 'label', 'url', and optional 'class' properties
 * @param string $description Optional description text (will be truncated)
 * @param int $truncate_length Number of words to show in description (default 15)
 */

// Prevent direct access
if (!defined('VT_VERSION')) {
    exit;
}

// Required parameters
$entity = $entity ?? null;
$entity_type = $entity_type ?? '';

if (!$entity || !$entity_type) {
    return;
}

// Optional parameters with defaults
$badges = $badges ?? [];
$stats = $stats ?? [];
$actions = $actions ?? [];
$description = $description ?? ($entity->description ?? $entity->content ?? '');
$truncate_length = $truncate_length ?? 15;

// Entity-specific data extraction
$title = '';
$url = '';

switch ($entity_type) {
    case 'event':
        $title = $entity->title ?? '';
        $url = '/events/' . ($entity->slug ?? '');
        $date_info = isset($entity->event_date) ? date('M j, Y', strtotime($entity->event_date)) : '';
        $time_info = isset($entity->event_time) ? 'at ' . $entity->event_time : '';
        break;

    case 'conversation':
        $title = $entity->title ?? '';
        $url = '/conversations/' . ($entity->slug ?? '');
        $date_info = isset($entity->created_at) ? date('M j, Y', strtotime($entity->created_at)) : '';
        $time_info = '';
        break;

    case 'community':
        $title = $entity->name ?? '';
        $url = '/communities/' . ($entity->slug ?? '');
        $date_info = isset($entity->created_at) ? 'Created ' . VT_Text::timeAgo($entity->created_at) : '';
        $time_info = '';
        break;
}
?>

<!-- Entity Card -->
<div class="vt-card vt-entity-card" data-entity-type="<?php echo htmlspecialchars($entity_type); ?>" data-entity-id="<?php echo intval($entity->id ?? 0); ?>">
    <div class="vt-card-body">
        <!-- Header: Title and Badges -->
        <div class="vt-flex vt-flex-between vt-mb-4">
            <div class="vt-flex-1">
                <h3 class="vt-heading vt-heading-sm vt-mb-2">
                    <a href="<?php echo htmlspecialchars($url); ?>" class="vt-text-primary">
                        <?php echo htmlspecialchars($title); ?>
                    </a>
                </h3>

                <!-- Date/Time Information -->
                <?php if (!empty($date_info)) : ?>
                    <div class="vt-text-muted vt-mb-2">
                        <?php echo htmlspecialchars($date_info); ?>
                        <?php if (!empty($time_info)) : ?>
                            <?php echo htmlspecialchars($time_info); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Entity-specific meta info -->
                <?php if ($entity_type === 'event' && !empty($entity->venue_info)) : ?>
                    <div class="vt-text-muted vt-mb-2">
                        📍 <?php echo htmlspecialchars($entity->venue_info); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Badges -->
        <?php if (!empty($badges)) : ?>
            <div class="vt-flex vt-gap vt-mb-4 vt-flex-wrap">
                <?php foreach ($badges as $badge) : ?>
                    <span class="vt-badge <?php echo htmlspecialchars($badge['class'] ?? 'vt-badge-secondary'); ?>">
                        <?php echo htmlspecialchars($badge['label'] ?? ''); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Description -->
        <?php if (!empty($description)) : ?>
            <div class="vt-mb-4">
                <p class="vt-text-muted">
                    <?php echo htmlspecialchars(VT_Text::truncateWords($description, $truncate_length)); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Stats and Actions -->
        <div class="vt-flex vt-flex-between vt-items-center">
            <!-- Stats -->
            <?php if (!empty($stats)) : ?>
                <div class="vt-flex vt-gap-4">
                    <?php foreach ($stats as $stat) : ?>
                        <div class="vt-stat">
                            <div class="vt-stat-number vt-text-primary">
                                <?php if (!empty($stat['icon'])) : ?>
                                    <?php echo $stat['icon']; ?>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($stat['value'] ?? '0'); ?>
                            </div>
                            <div class="vt-stat-label vt-text-muted">
                                <?php echo htmlspecialchars($stat['label'] ?? ''); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <?php if (!empty($actions)) : ?>
                <div class="vt-flex vt-gap-2">
                    <?php foreach ($actions as $action) : ?>
                        <a href="<?php echo htmlspecialchars($action['url'] ?? '#'); ?>"
                           class="vt-btn vt-btn-sm <?php echo htmlspecialchars($action['class'] ?? ''); ?>">
                            <?php echo htmlspecialchars($action['label'] ?? ''); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
