<?php
/**
 * VivalaTable 500 Error Template
 * Server error page
 */
?>

<div class="vt-section vt-text-center">
	<h1 class="vt-heading vt-heading-lg vt-text-danger vt-mb-4">Server Error</h1>

	<div class="vt-alert vt-alert-error vt-mb-4">
		<p><?php echo htmlspecialchars($vt_page_description ?: 'An unexpected error occurred.'); ?></p>
	</div>

	<div class="vt-text-center">
		<a href="/" class="vt-btn vt-btn-primary">Return Home</a>
	</div>
</div>