<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>VivalaTable</title>
    <link rel="stylesheet" href="/assets/css/vivalatable.css">
</head>
<body>

<div class="vt-page">
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