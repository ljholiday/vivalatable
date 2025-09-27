<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>VivalaTable</title>
    <link rel="stylesheet" href="/assets/css/vivalatable.css">
</head>
<body>

<!-- Centered Form Layout - matches main column width -->
<div class="vt-page-form-centered">
    <!-- Persistent Main Navigation -->
    <div class="vt-main-nav">
        <a href="/events" class="vt-main-nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/events') !== false || strpos($_SERVER['REQUEST_URI'], '/create-event') !== false) ? 'active' : ''; ?>">
            Events
        </a>
        <a href="/conversations" class="vt-main-nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/conversations') !== false || strpos($_SERVER['REQUEST_URI'], '/create-conversation') !== false) ? 'active' : ''; ?>">
            Conversations
        </a>
        <a href="/communities" class="vt-main-nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/communities') !== false || strpos($_SERVER['REQUEST_URI'], '/create-community') !== false) ? 'active' : ''; ?>">
            Communities
        </a>
    </div>
    <!-- Context Action Bar -->
    <div class="vt-action-bar">
        <?php
        // Smart back button based on current page
        $back_url = '/';
        $back_text = 'Back';
        if (strpos($_SERVER['REQUEST_URI'], '/create-event') !== false) {
            $back_url = '/events';
            $back_text = 'Back to Events';
        } elseif (strpos($_SERVER['REQUEST_URI'], '/create-community') !== false) {
            $back_url = '/communities';
            $back_text = 'Back to Communities';
        } elseif (strpos($_SERVER['REQUEST_URI'], '/create-conversation') !== false) {
            $back_url = '/conversations';
            $back_text = 'Back to Conversations';
        }
        ?>
        <a href="<?php echo $back_url; ?>" class="vt-btn vt-btn-secondary">
            ← <?php echo $back_text; ?>
        </a>
    </div>

    <!-- Page Header -->
    <div class="vt-header">
        <h1 class="vt-heading vt-heading-lg vt-text-primary"><?php echo htmlspecialchars($page_title); ?></h1>
        <?php if (!empty($page_description)) : ?>
            <p class="vt-text-muted"><?php echo htmlspecialchars($page_description); ?></p>
        <?php endif; ?>
    </div>

    <!-- Form Content -->
    <div class="vt-section">
        <?php echo $content; ?>
    </div>
</div>

<script src="/assets/js/vivalatable.js"></script>
</body>
</html>