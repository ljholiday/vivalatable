<?php
/**
 * Registration Page
 */

// Redirect if already logged in
if (is_user_logged_in()) {
    vt_redirect(vt_base_url('/'));
}

$error_messages = [];
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'username' => $_POST['username'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'display_name' => $_POST['display_name'] ?? ''
    ];

    // Validate form data
    $error_messages = vt_validate_user_registration($form_data);

    if (empty($error_messages)) {
        $user_id = vt_register_user(
            $form_data['username'],
            $form_data['email'],
            $form_data['password'],
            $form_data['display_name']
        );

        if ($user_id) {
            // Auto-login after registration
            if (vt_login_user($form_data['email'], $form_data['password'])) {
                vt_redirect(vt_base_url('/'));
            } else {
                $success_message = 'Registration successful! Please log in.';
            }
        } else {
            $error_messages['general'] = 'Registration failed. Email or username may already be in use.';
        }
    }
}

$page_title = 'Sign Up - VivalaTable';
$page_description = 'Create your VivalaTable account';

ob_start();
?>

<div class="pm-container">
    <div class="pm-register-form">
        <div class="pm-card">
            <div class="pm-card-header">
                <h1 class="pm-heading pm-heading-md">Create Account</h1>
            </div>
            <div class="pm-card-body">
                <?php if ($success_message): ?>
                    <div class="pm-alert pm-alert-success">
                        <?php echo vt_escape_html($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_messages['general'])): ?>
                    <div class="pm-alert pm-alert-error">
                        <?php echo vt_escape_html($error_messages['general']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo vt_base_url('/register'); ?>">
                    <div class="pm-form-row">
                        <label for="username" class="pm-form-label">Username</label>
                        <input type="text" id="username" name="username" class="pm-form-input"
                               value="<?php echo vt_escape_attr($_POST['username'] ?? ''); ?>" required>
                        <?php if (!empty($error_messages['username'])): ?>
                            <div class="pm-form-error"><?php echo vt_escape_html($error_messages['username']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="pm-form-row">
                        <label for="email" class="pm-form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="pm-form-input"
                               value="<?php echo vt_escape_attr($_POST['email'] ?? ''); ?>" required>
                        <?php if (!empty($error_messages['email'])): ?>
                            <div class="pm-form-error"><?php echo vt_escape_html($error_messages['email']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="pm-form-row">
                        <label for="display_name" class="pm-form-label">Display Name (Optional)</label>
                        <input type="text" id="display_name" name="display_name" class="pm-form-input"
                               value="<?php echo vt_escape_attr($_POST['display_name'] ?? ''); ?>">
                    </div>

                    <div class="pm-form-row">
                        <label for="password" class="pm-form-label">Password</label>
                        <input type="password" id="password" name="password" class="pm-form-input" required>
                        <?php if (!empty($error_messages['password'])): ?>
                            <div class="pm-form-error"><?php echo vt_escape_html($error_messages['password']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="pm-form-row">
                        <label for="password_confirm" class="pm-form-label">Confirm Password</label>
                        <input type="password" id="password_confirm" name="password_confirm" class="pm-form-input" required>
                        <?php if (!empty($error_messages['password_confirm'])): ?>
                            <div class="pm-form-error"><?php echo vt_escape_html($error_messages['password_confirm']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="pm-form-actions">
                        <button type="submit" class="pm-btn pm-btn-primary pm-btn-block">Create Account</button>
                    </div>
                </form>

                <div class="pm-form-links">
                    <p>Already have an account? <a href="<?php echo vt_base_url('/login'); ?>">Sign in</a></p>
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