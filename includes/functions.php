<?php
/**
 * Core Functions
 * Utility functions used throughout VivalaTable
 */

/**
 * Escape HTML output
 */
function vt_escape_html(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape HTML attributes
 */
function vt_escape_attr(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape URLs
 */
function vt_escape_url(string $url): string {
    return filter_var($url, FILTER_SANITIZE_URL);
}

/**
 * Clean text input
 */
function vt_sanitize_text(string $text): string {
    return trim(strip_tags($text));
}

/**
 * Clean textarea input (allow basic HTML)
 */
function vt_sanitize_textarea(string $text): string {
    $allowed_tags = '<p><br><strong><em><ul><ol><li><a>';
    return trim(strip_tags($text, $allowed_tags));
}

/**
 * Generate secure slug from title
 */
function vt_generate_slug(string $title, ?int $max_length = 100): string {
    // Convert to lowercase and replace non-alphanumeric with hyphens
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');

    if ($max_length && strlen($slug) > $max_length) {
        $slug = substr($slug, 0, $max_length);
        $slug = trim($slug, '-');
    }

    return $slug;
}

/**
 * Make slug unique by adding number suffix if needed
 */
function vt_unique_slug(string $slug, string $table, int $exclude_id = 0): string {
    $db = Database::getInstance();
    $original_slug = $slug;
    $counter = 1;

    while (true) {
        $where = ['slug' => $slug];
        if ($exclude_id > 0) {
            $sql = "SELECT id FROM " . Database::table($table) . " WHERE slug = :slug AND id != :exclude_id LIMIT 1";
            $stmt = $db->query($sql, ['slug' => $slug, 'exclude_id' => $exclude_id]);
        } else {
            $sql = "SELECT id FROM " . Database::table($table) . " WHERE slug = :slug LIMIT 1";
            $stmt = $db->query($sql, ['slug' => $slug]);
        }

        if ($stmt->rowCount() === 0) {
            return $slug;
        }

        $slug = $original_slug . '-' . $counter;
        $counter++;

        // Prevent infinite loops
        if ($counter > 1000) {
            return $original_slug . '-' . uniqid();
        }
    }
}

/**
 * Format date for display
 */
function vt_format_date(string $date, string $format = 'F j, Y'): string {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return '';
    }

    try {
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '';
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Format datetime for display
 */
function vt_format_datetime(string $datetime, string $format = 'F j, Y \a\t g:i A'): string {
    return vt_format_date($datetime, $format);
}

/**
 * Get time difference in human readable format
 */
function vt_time_diff(string $datetime): string {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '';
    }

    try {
        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return '';
        }

        $current_time = time();
        $diff = $current_time - $timestamp;

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return vt_format_date($datetime);
        }
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Truncate text to specified length
 */
function vt_truncate_text(string $text, int $length = 100, string $suffix = '...'): string {
    $text = strip_tags($text);
    if (strlen($text) <= $length) {
        return $text;
    }

    return rtrim(substr($text, 0, $length)) . $suffix;
}

/**
 * Get base URL
 */
function vt_base_url(string $path = ''): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Auto-detect base path from script location
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $base_path = ($script_dir === '/') ? '' : $script_dir;
    $base = $protocol . $host . $base_path;

    if ($path) {
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }

    return $base;
}

/**
 * Redirect to URL
 */
function vt_redirect(string $url, int $status_code = 302): void {
    header('Location: ' . $url, true, $status_code);
    exit;
}

/**
 * Get current URL
 */
function vt_current_url(): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    return $protocol . $host . $uri;
}

/**
 * Check if request is AJAX
 */
function vt_is_ajax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function vt_json_response(array $data, int $status_code = 200): void {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Verify CSRF token
 */
function vt_verify_nonce(string $action): bool {
    $token = $_POST['vt_nonce'] ?? $_GET['vt_nonce'] ?? '';
    $expected = vt_generate_nonce($action);

    return hash_equals($expected, $token);
}

/**
 * Generate CSRF token
 */
function vt_generate_nonce(string $action): string {
    $user_id = get_current_user_id();
    $session_id = session_id();

    return hash('sha256', $action . $user_id . $session_id . date('Y-m-d'));
}

/**
 * Output CSRF token field
 */
function vt_nonce_field(string $action): string {
    $nonce = vt_generate_nonce($action);
    return '<input type="hidden" name="vt_nonce" value="' . vt_escape_attr($nonce) . '">';
}

/**
 * Load template file
 */
function vt_load_template(string $template, array $vars = []): void {
    // Extract variables for template
    extract($vars);

    $template_file = VT_ROOT . '/templates/' . $template . '.php';

    if (file_exists($template_file)) {
        include $template_file;
    } else {
        throw new Exception("Template not found: {$template}");
    }
}

/**
 * Get template content as string
 */
function vt_get_template(string $template, array $vars = []): string {
    ob_start();
    try {
        vt_load_template($template, $vars);
        return ob_get_clean();
    } catch (Exception $e) {
        ob_end_clean();
        return "Template error: " . $e->getMessage();
    }
}

/**
 * Log error message
 */
function vt_log_error(string $message, array $context = []): void {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'user_id' => get_current_user_id(),
        'url' => vt_current_url(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];

    error_log(json_encode($log_entry));
}

/**
 * Send email (using PHP mail function)
 */
function vt_send_email(string $to, string $subject, string $message, array $headers = []): bool {
    // Default headers
    $default_headers = [
        'From: ' . (defined('VT_FROM_NAME') ? VT_FROM_NAME : 'VivalaTable') . ' <' . (defined('VT_FROM_EMAIL') ? VT_FROM_EMAIL : 'noreply@vivalatable.com') . '>',
        'Reply-To: ' . (defined('VT_FROM_EMAIL') ? VT_FROM_EMAIL : 'noreply@vivalatable.com'),
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: VivalaTable'
    ];

    $all_headers = array_merge($default_headers, $headers);

    try {
        return mail($to, $subject, $message, implode("\r\n", $all_headers));
    } catch (Exception $e) {
        vt_log_error('Email sending failed', [
            'to' => $to,
            'subject' => $subject,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Upload file handler
 */
function vt_handle_file_upload(array $file, string $upload_dir = ''): ?array {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $upload_path = VT_UPLOADS_PATH . ($upload_dir ? $upload_dir . '/' : '');

    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        return null;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . strtolower($extension);
    $full_path = $upload_path . $filename;

    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        return [
            'filename' => $filename,
            'original_name' => $file['name'],
            'path' => $full_path,
            'url' => VT_UPLOADS_URL . ($upload_dir ? $upload_dir . '/' : '') . $filename,
            'size' => $file['size'],
            'type' => $file_type
        ];
    }

    return null;
}