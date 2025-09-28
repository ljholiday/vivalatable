<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo VT_Security::createNonce('vt_nonce'); ?>">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>VivalaTable</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- Centered Page Layout -->
<div class="vt-page-form-centered">
    <!-- Persistent Main Navigation -->
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
    <!-- Page Header -->
    <div class="vt-header">
        <h1 class="vt-heading vt-heading-lg vt-text-primary"><?php echo htmlspecialchars($page_title); ?></h1>
        <?php if (!empty($page_description)) : ?>
            <p class="vt-text-muted"><?php echo htmlspecialchars($page_description); ?></p>
        <?php endif; ?>
    </div>

    <!-- Page Content -->
    <?php echo $content; ?>
</div>

<script src="/assets/js/vivalatable.js"></script>
</body>
</html>