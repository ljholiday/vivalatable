<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>VivalaTable</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php if (!empty($breadcrumbs)) : ?>
<!-- Breadcrumbs -->
<div class="vt-text-muted mb-4">
    <?php
    $breadcrumb_parts = array();
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

<?php if (!empty($nav_items)) : ?>
<!-- Navigation - Spans Full Width -->
<div class="vt-nav">
    <?php foreach ($nav_items as $nav_item) : ?>
        <a href="<?php echo htmlspecialchars($nav_item['url']); ?>"
            class="vt-nav-item <?php echo !empty($nav_item['active']) ? 'active' : ''; ?>">
            <?php if (!empty($nav_item['icon'])) : ?>
                <span><?php echo $nav_item['icon']; ?></span>
            <?php endif; ?>
            <?php echo htmlspecialchars($nav_item['title']); ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Two Column Layout -->
<div class="vt-page-two-column">
    <div class="vt-main">
        <!-- Fixed Main Navigation -->
        <div class="vt-main-nav">
            <a href="/events" class="vt-main-nav-item <?php echo (strpos(VT_Router::getCurrentUri(), '/events') !== false) ? 'active' : ''; ?>">
                Events
            </a>
            <a href="/conversations" class="vt-main-nav-item <?php echo (strpos(VT_Router::getCurrentUri(), '/conversations') !== false) ? 'active' : ''; ?>">
                Conversations
            </a>
            <a href="/communities" class="vt-main-nav-item <?php echo (strpos(VT_Router::getCurrentUri(), '/communities') !== false) ? 'active' : ''; ?>">
                Communities
            </a>
        </div>

        <!-- Scrollable Main Content -->
        <div class="vt-main-content">
            <?php echo $main_content; ?>
        </div>
    </div>

    <div class="vt-sidebar">
        <!-- Sidebar Content -->
        <?php if (isset($sidebar_content) && $sidebar_content) : ?>
            <?php echo $sidebar_content; ?>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/vivalatable.js"></script>
</body>
</html>