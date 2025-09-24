<?php
/**
 * User Profile Page
 */

$user = vt_get_current_user();
if (!$user) {
    vt_redirect(vt_base_url('/login'));
}

$page_title = 'Profile - VivalaTable';
$page_description = 'Manage your VivalaTable profile';

ob_start();
?>

<div class="pm-container">
    <div class="pm-profile-page">
        <h1 class="pm-heading pm-heading-lg">Your Profile</h1>

        <div class="pm-card">
            <div class="pm-card-header">
                <h2 class="pm-heading pm-heading-md">Profile Information</h2>
            </div>
            <div class="pm-card-body">
                <div class="pm-profile-info">
                    <div class="pm-profile-field">
                        <label class="pm-profile-label">Username</label>
                        <div class="pm-profile-value"><?php echo vt_escape_html($user->username); ?></div>
                    </div>

                    <div class="pm-profile-field">
                        <label class="pm-profile-label">Email</label>
                        <div class="pm-profile-value"><?php echo vt_escape_html($user->email); ?></div>
                    </div>

                    <div class="pm-profile-field">
                        <label class="pm-profile-label">Display Name</label>
                        <div class="pm-profile-value"><?php echo vt_escape_html($user->display_name ?? $user->username); ?></div>
                    </div>

                    <div class="pm-profile-field">
                        <label class="pm-profile-label">Member Since</label>
                        <div class="pm-profile-value"><?php echo vt_format_date($user->registered, 'F j, Y'); ?></div>
                    </div>
                </div>

                <div class="pm-profile-actions">
                    <a href="#" class="pm-btn pm-btn-primary">Edit Profile</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

vt_load_template('base/page', [
    'page_title' => $page_title,
    'page_description' => $page_description,
    'content' => $content
]);
?>