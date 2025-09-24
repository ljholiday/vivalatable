<?php
/**
 * Login Page
 */

// Redirect if already logged in
if (is_user_logged_in()) {
    vt_redirect(vt_base_url('/'));
}

$error_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($email && $password) {
        if (vt_login_user($email, $password, $remember)) {
            $redirect_to = $_GET['redirect_to'] ?? vt_base_url('/');
            vt_redirect($redirect_to);
        } else {
            $error_message = 'Invalid email or password';
        }
    } else {
        $error_message = 'Please enter both email and password';
    }
}

$page_title = 'Login - VivalaTable';
$page_description = 'Sign in to your VivalaTable account';

ob_start();
?>

<div class="pm-container">
    <div class="pm-login-form">
        <div class="pm-card">
            <div class="pm-card-header">
                <h1 class="pm-heading pm-heading-md">Sign In</h1>
            </div>
            <div class="pm-card-body">
                <?php if ($error_message): ?>
                    <div class="pm-alert pm-alert-error">
                        <?php echo vt_escape_html($error_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo vt_base_url('/login'); ?>">
                    <div class="pm-form-row">
                        <label for="email" class="pm-form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="pm-form-input"
                               value="<?php echo vt_escape_attr($_POST['email'] ?? ''); ?>" required>
                    </div>

                    <div class="pm-form-row">
                        <label for="password" class="pm-form-label">Password</label>
                        <input type="password" id="password" name="password" class="pm-form-input" required>
                    </div>

                    <div class="pm-form-row">
                        <label class="pm-checkbox-label">
                            <input type="checkbox" name="remember" value="1"
                                   <?php echo !empty($_POST['remember']) ? 'checked' : ''; ?>>
                            Remember me
                        </label>
                    </div>

                    <div class="pm-form-actions">
                        <button type="submit" class="pm-btn pm-btn-primary pm-btn-block">Sign In</button>
                    </div>
                </form>

                <div class="pm-form-links">
                    <p>Don't have an account? <a href="<?php echo vt_base_url('/register'); ?>">Sign up</a></p>
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