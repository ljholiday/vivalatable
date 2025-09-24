<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo vt_escape_html($page_title ?? 'VivalaTable - Real-world social networking'); ?></title>
    <meta name="description" content="<?php echo vt_escape_html($page_description ?? 'Plan real events. Connect with real people. Share real life.'); ?>">

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo vt_base_url('/assets/css/partyminder.css'); ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo vt_base_url('/assets/images/favicon.svg'); ?>">

    <?php if (isset($additional_head)): ?>
        <?php echo $additional_head; ?>
    <?php endif; ?>
</head>
<body class="vt-body">
    <!-- Navigation -->
    <nav class="pm-navbar">
        <div class="pm-nav-container">
            <div class="pm-nav-brand">
                <a href="<?php echo vt_base_url('/'); ?>" class="pm-nav-brand-link">
                    <strong>VivalaTable</strong>
                </a>
            </div>

            <div class="pm-nav-links">
                <a href="<?php echo vt_base_url('/events'); ?>" class="pm-nav-link">Events</a>
                <a href="<?php echo vt_base_url('/communities'); ?>" class="pm-nav-link">Communities</a>
                <a href="<?php echo vt_base_url('/conversations'); ?>" class="pm-nav-link">Conversations</a>

                <?php if (is_user_logged_in()): ?>
                    <div class="pm-nav-user-menu">
                        <button class="pm-nav-user-toggle pm-btn" id="userMenuToggle">
                            <?php $user = vt_get_current_user(); ?>
                            <?php if (!empty($user->avatar_url)): ?>
                                <img src="<?php echo vt_escape_url($user->avatar_url); ?>" alt="Avatar" class="pm-avatar pm-avatar-sm">
                            <?php endif; ?>
                            <?php echo vt_escape_html($user->display_name ?? 'User'); ?>
                        </button>
                        <div class="pm-nav-dropdown" id="userMenuDropdown">
                            <a href="<?php echo vt_base_url('/profile'); ?>" class="pm-nav-dropdown-item">Profile</a>
                            <a href="<?php echo vt_base_url('/events/create'); ?>" class="pm-nav-dropdown-item">Create Event</a>
                            <a href="<?php echo vt_base_url('/communities/create'); ?>" class="pm-nav-dropdown-item">Create Community</a>
                            <div class="pm-nav-dropdown-divider"></div>
                            <a href="<?php echo vt_base_url('/logout'); ?>" class="pm-nav-dropdown-item">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo vt_base_url('/login'); ?>" class="pm-btn">Login</a>
                    <a href="<?php echo vt_base_url('/register'); ?>" class="pm-btn pm-btn-primary">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pm-main-content">
        <?php if (isset($content)): ?>
            <?php echo $content; ?>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="pm-footer">
        <div class="pm-footer-container">
            <div class="pm-footer-content">
                <div class="pm-footer-section">
                    <h4>VivalaTable</h4>
                    <p class="pm-text-muted">Real-world social networking for event planning and community building.</p>
                </div>
                <div class="pm-footer-section">
                    <h5>Features</h5>
                    <ul class="pm-footer-links">
                        <li><a href="<?php echo vt_base_url('/events'); ?>">Events</a></li>
                        <li><a href="<?php echo vt_base_url('/communities'); ?>">Communities</a></li>
                        <li><a href="<?php echo vt_base_url('/conversations'); ?>">Conversations</a></li>
                    </ul>
                </div>
                <div class="pm-footer-section">
                    <h5>Connect</h5>
                    <ul class="pm-footer-links">
                        <li><a href="#">Bluesky</a></li>
                        <li><a href="#">GitHub</a></li>
                    </ul>
                </div>
            </div>
            <div class="pm-footer-bottom">
                <p class="pm-text-muted">
                    &copy; <?php echo date('Y'); ?> VivalaTable.
                    <a href="#">Privacy</a> · <a href="#">Terms</a>
                </p>
                <p class="pm-text-muted">
                    <small>Built with ❤️ for real-world social connection</small>
                </p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Simple dropdown toggle
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('userMenuToggle');
            const dropdown = document.getElementById('userMenuDropdown');

            if (toggle && dropdown) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    dropdown.classList.toggle('show');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
                        dropdown.classList.remove('show');
                    }
                });
            }
        });
    </script>

    <?php if (isset($additional_js)): ?>
        <?php echo $additional_js; ?>
    <?php endif; ?>
</body>
</html>