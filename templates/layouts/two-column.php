<?php
/**
 * Two-Column Layout - Main content + sidebar
 * Used for: List pages, detail pages with sidebar
 *
 * Expected variables:
 * @var string $page_title - Page title
 * @var string $main_content - Main content HTML
 * @var string $sidebar_content - Sidebar content HTML
 * @var string $current_path - Current request path
 * @var array $breadcrumbs - Optional breadcrumb array
 * @var array $nav_items - Optional secondary navigation
 */

declare(strict_types=1);

$page_title = $page_title ?? 'VivalaTable';
$main_content = $main_content ?? '';
$sidebar_content = $sidebar_content ?? '';
$current_path = $current_path ?? $_SERVER['REQUEST_URI'] ?? '/';
$breadcrumbs = $breadcrumbs ?? [];
$nav_items = $nav_items ?? [];

$security = vt_service('security.service');
$csrf_token = $security->createNonce('vt_nonce');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?= htmlspecialchars($page_title); ?> - VivalaTable</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php if ($breadcrumbs): ?>
<div class="vt-text-muted mb-4">
    <?php
    $breadcrumb_parts = [];
    foreach ($breadcrumbs as $crumb) {
        if (isset($crumb['url'])) {
            $breadcrumb_parts[] = '<a href="' . htmlspecialchars($crumb['url']) . '" class="vt-text-primary">' . htmlspecialchars($crumb['title']) . '</a>';
        } else {
            $breadcrumb_parts[] = '<span>' . htmlspecialchars($crumb['title']) . '</span>';
        }
    }
    echo implode(' › ', $breadcrumb_parts);
    ?>
</div>
<?php endif; ?>

<div class="vt-page-two-column">
    <div class="vt-main">
        <div class="vt-main-nav">
            <a href="/events" class="vt-main-nav-item<?= str_contains($current_path, '/events') ? ' active' : ''; ?>">
                Events
            </a>
            <a href="/conversations" class="vt-main-nav-item<?= str_contains($current_path, '/conversations') ? ' active' : ''; ?>">
                Conversations
            </a>
            <a href="/communities" class="vt-main-nav-item<?= str_contains($current_path, '/communities') ? ' active' : ''; ?>">
                Communities
            </a>
        </div>

        <?php if ($nav_items): ?>
        <div class="vt-nav">
            <?php foreach ($nav_items as $nav_item): ?>
                <a href="<?= htmlspecialchars($nav_item['url']); ?>"
                    class="vt-nav-item<?= !empty($nav_item['active']) ? ' active' : ''; ?>">
                    <?php if (!empty($nav_item['icon'])): ?>
                        <span><?= $nav_item['icon']; ?></span>
                    <?php endif; ?>
                    <?= htmlspecialchars($nav_item['title']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="vt-main-content">
            <?= $main_content; ?>
        </div>
    </div>

    <div class="vt-sidebar">
        <?php if ($sidebar_content): ?>
            <?= $sidebar_content; ?>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/modal.js"></script>
<script src="/assets/js/vivalatable.js"></script>
<?php if (str_contains($current_path, '/conversations')): ?>
<script src="/assets/js/conversations.js"></script>
<?php endif; ?>
<?php if (str_contains($current_path, '/communities') || str_contains($current_path, '/events')): ?>
<script src="/assets/js/communities.js"></script>
<script src="/assets/js/invitation.js"></script>
<?php endif; ?>
//<script>
//  fetch('/assets/css/dev.css', { method: 'HEAD' })
//    .then(response => {
//      if (response.ok) {
//        const link = document.createElement('link');
//        link.rel = 'stylesheet';
//        link.href = '/assets/css/dev.css';
//        document.head.appendChild(link);
//      }
//    })
//    .catch(() => {
//      // dev.css doesn't exist - silently ignore
//    });
//</script>
</body>
</html>
