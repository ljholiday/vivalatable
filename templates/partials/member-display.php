<?php
/**
 * VivalaTable Member Display Component
 * Reusable member display with avatar, display name, and profile link
 * Ported from PartyMinder WordPress plugin
 *
 * Usage:
 * include VT_ROOT_DIR . '/templates/partials/member-display.php';
 *
 * Required variables:
 * - $user_id (int): User ID or user object/email
 *
 * Optional variables:
 * - $args (array): Display arguments
 *   - 'avatar_size' => 32 (int): Avatar size in pixels
 *   - 'show_avatar' => true (bool): Whether to show avatar
 *   - 'show_name' => true (bool): Whether to show name
 *   - 'link_profile' => true (bool): Whether to make it clickable
 *   - 'class' => 'vt-member-display' (string): CSS classes
 */

// Prevent direct access
if (!defined('VT_VERSION')) {
    exit;
}

// Set defaults
$defaults = array(
    'avatar_size'    => 32,
    'show_avatar'    => true,
    'show_name'      => true,
    'link_profile'   => true,
    'fallback_email' => true,
    'class'          => 'vt-member-display',
);

$args = isset($args) ? array_merge($defaults, $args) : $defaults;

// Get user object
$user_obj = null;
if (is_numeric($user_id)) {
    $user_obj = vt_service('auth.user_repository')->getUserById($user_id);
} elseif (is_string($user_id) && filter_var($user_id, FILTER_VALIDATE_EMAIL)) {
    $user_obj = vt_service('auth.user_repository')->getUserByEmail($user_id);
}

if (!$user_obj) {
    // Fallback for email-only cases
    if ($args['fallback_email'] && filter_var($user_id, FILTER_VALIDATE_EMAIL)) {
        echo '<span class="' . vt_service('validation.validator')->escHtml($args['class']) . '">' . vt_service('validation.validator')->escHtml($user_id) . '</span>';
        return;
    }
    echo '<span class="' . vt_service('validation.validator')->escHtml($args['class']) . '">Unknown User</span>';
    return;
}

// Get display name using Profile Manager
$display_name = VT_Profile_Manager::getDisplayName($user_obj->id);

// Get profile URL
$profile_url = VT_Profile_Manager::getProfileUrl($user_obj->id);

// Generate avatar HTML (simplified since we don't have gravatar integration yet)
$avatar_html = '<div class="vt-avatar vt-avatar-sm vt-rounded-full vt-bg-gray-300 vt-flex vt-items-center vt-justify-center" style="width: ' . intval($args['avatar_size']) . 'px; height: ' . intval($args['avatar_size']) . 'px;">' .
               '<span class="vt-text-white vt-font-bold">' . strtoupper(substr($display_name, 0, 1)) . '</span>' .
               '</div>';
?>

<div class="<?php echo vt_service('validation.validator')->escHtml($args['class']); ?> vt-flex vt-items-center vt-gap">
    <?php if ($args['show_avatar']) : ?>
        <?php if ($args['link_profile'] && $profile_url) : ?>
            <a href="<?php echo vt_service('validation.validator')->escUrl($profile_url); ?>" class="vt-avatar-link"><?php echo $avatar_html; ?></a>
        <?php else : ?>
            <?php echo $avatar_html; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($args['show_name']) : ?>
        <?php if ($args['link_profile'] && $profile_url) : ?>
            <a href="<?php echo vt_service('validation.validator')->escUrl($profile_url); ?>" class="vt-member-name vt-link"><?php echo vt_service('validation.validator')->escHtml($display_name); ?></a>
        <?php else : ?>
            <span class="vt-member-name"><?php echo vt_service('validation.validator')->escHtml($display_name); ?></span>
        <?php endif; ?>
    <?php endif; ?>
</div>