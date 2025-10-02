<?php
/**
 * VivalaTable Standardized Secondary Navigation for Sidebar
 * Universal secondary menu used across all pages
 * Ported from PartyMinder WordPress plugin
 */

// Prevent direct access
if (!defined('VT_VERSION')) {
    exit;
}

$current_user = vt_service('auth.service')->getCurrentUser();
$is_logged_in = vt_service('auth.service')->isLoggedIn();
$community_manager = new VT_Community_Manager();
?>

<div class="vt-sidebar-section vt-mb-4">

    <?php if ($is_logged_in) : ?>
    <!-- Search Section -->
    <div class="vt-search-box vt-mb-4">
        <input type="text" id="vt-search-input" class="vt-input" placeholder="Search..." autocomplete="off">
        <div id="vt-search-results" class="vt-search-results" style="display: none;"></div>
    </div>
    <?php endif; ?>

    <div class="vt-sidebar-nav">
        <?php if ($is_logged_in) : ?>
            <a href="/events/create" class="vt-btn vt-btn-secondary">
                Create Event
            </a>

            <a href="/conversations/create" class="vt-btn vt-btn-secondary">
                Create Conversation
            </a>

            <?php if ($community_manager->canCreateCommunity()) : ?>
                <a href="/communities/create" class="vt-btn vt-btn-secondary">
                    Create Community
                </a>
            <?php endif; ?>

            <a href="/profile" class="vt-btn vt-btn-secondary">
                My Profile
            </a>

            <a href="/" class="vt-btn vt-btn-secondary">
                Dashboard
            </a>

        <?php else : ?>
            <a href="/events" class="vt-btn vt-btn-secondary">
                Browse Events
            </a>

            <a href="/conversations" class="vt-btn vt-btn-secondary">
                Join Conversations
            </a>

            <a href="/communities" class="vt-btn vt-btn-secondary">
                Browse Communities
            </a>

            <a href="/login" class="vt-btn vt-btn-secondary">
                Sign In
            </a>
        <?php endif; ?>
    </div>

    <?php if ($is_logged_in) : ?>
    <!-- Profile Card -->
    <div class="vt-profile-card vt-mt-4">
        <div class="vt-flex vt-gap vt-mb">
            <?php
            // Get user profile data for location
            $profile_data = VT_Profile_Manager::getUserProfile($current_user->id);

            // Include member display with larger avatar
            $user_id = $current_user->id;
            $args = array('avatar_size' => 56);
            include VT_ROOT_DIR . '/templates/partials/member-display.php';
            ?>
            <div class="vt-flex-1">
                <?php if ($profile_data && $profile_data['location']) : ?>
                <div class="vt-text-muted"><?php echo vt_service('validation.validator')->escHtml($profile_data['location']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="vt-flex vt-gap vt-flex-column">
            <a href="/profile" class="vt-btn">
                Profile
            </a>
            <a href="/logout" class="vt-btn">
                Logout
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>
