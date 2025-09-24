<?php
/**
 * 404 Not Found Page
 */

$page_title = '404 - Page Not Found';
$page_description = 'The page you are looking for could not be found.';

ob_start();
?>

<div class="pm-container">
    <div class="pm-error-page">
        <h1 class="pm-heading pm-heading-lg">404 - Page Not Found</h1>
        <p class="pm-text-muted">The page you are looking for could not be found.</p>

        <div class="pm-error-actions">
            <a href="<?php echo vt_base_url('/'); ?>" class="pm-btn pm-btn-primary">Go Home</a>
            <a href="<?php echo vt_base_url('/events'); ?>" class="pm-btn">Browse Events</a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Load page template
vt_load_template('base/page', [
    'page_title' => $page_title,
    'page_description' => $page_description,
    'content' => $content
]);
?>