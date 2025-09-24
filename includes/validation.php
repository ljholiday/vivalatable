<?php
/**
 * Validation Functions
 * Input validation and sanitization utilities
 */

/**
 * Validate email address
 */
function vt_validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate username
 */
function vt_validate_username(string $username): bool {
    return preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username);
}

/**
 * Validate password strength
 */
function vt_validate_password(string $password): bool {
    return strlen($password) >= 8;
}

/**
 * Validate URL
 */
function vt_validate_url(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate date format
 */
function vt_validate_date(string $date, string $format = 'Y-m-d H:i:s'): bool {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Validate integer within range
 */
function vt_validate_int_range(int $value, int $min = null, int $max = null): bool {
    if ($min !== null && $value < $min) {
        return false;
    }
    if ($max !== null && $value > $max) {
        return false;
    }
    return true;
}

/**
 * Validate required fields
 */
function vt_validate_required(array $data, array $required_fields): array {
    $errors = [];

    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[$field] = 'This field is required';
        }
    }

    return $errors;
}

/**
 * Validate event data
 */
function vt_validate_event_data(array $data): array {
    $errors = [];

    // Required fields
    $required = ['title', 'event_date'];
    $errors = array_merge($errors, vt_validate_required($data, $required));

    // Title length
    if (!empty($data['title']) && strlen($data['title']) > 200) {
        $errors['title'] = 'Title must be less than 200 characters';
    }

    // Date validation
    if (!empty($data['event_date']) && !vt_validate_date($data['event_date'])) {
        $errors['event_date'] = 'Invalid date format';
    }

    // Max guests
    if (!empty($data['max_guests']) && !vt_validate_int_range((int)$data['max_guests'], 1, 10000)) {
        $errors['max_guests'] = 'Max guests must be between 1 and 10,000';
    }

    // Privacy level
    if (!empty($data['privacy_level']) && !in_array($data['privacy_level'], ['public', 'private', 'community'])) {
        $errors['privacy_level'] = 'Invalid privacy level';
    }

    return $errors;
}

/**
 * Validate community data
 */
function vt_validate_community_data(array $data): array {
    $errors = [];

    // Required fields
    $required = ['name'];
    $errors = array_merge($errors, vt_validate_required($data, $required));

    // Name length
    if (!empty($data['name']) && strlen($data['name']) > 100) {
        $errors['name'] = 'Name must be less than 100 characters';
    }

    // Description length
    if (!empty($data['description']) && strlen($data['description']) > 1000) {
        $errors['description'] = 'Description must be less than 1,000 characters';
    }

    // Website URL
    if (!empty($data['website']) && !vt_validate_url($data['website'])) {
        $errors['website'] = 'Invalid website URL';
    }

    // Community type
    if (!empty($data['community_type']) && !in_array($data['community_type'], ['general', 'hobby', 'professional', 'social', 'local'])) {
        $errors['community_type'] = 'Invalid community type';
    }

    return $errors;
}

/**
 * Validate user registration data
 */
function vt_validate_user_registration(array $data): array {
    $errors = [];

    // Required fields
    $required = ['username', 'email', 'password'];
    $errors = array_merge($errors, vt_validate_required($data, $required));

    // Username validation
    if (!empty($data['username']) && !vt_validate_username($data['username'])) {
        $errors['username'] = 'Username must be 3-20 characters and contain only letters, numbers, hyphens, and underscores';
    }

    // Email validation
    if (!empty($data['email']) && !vt_validate_email($data['email'])) {
        $errors['email'] = 'Invalid email address';
    }

    // Password validation
    if (!empty($data['password']) && !vt_validate_password($data['password'])) {
        $errors['password'] = 'Password must be at least 8 characters long';
    }

    // Password confirmation
    if (!empty($data['password']) && !empty($data['password_confirm']) && $data['password'] !== $data['password_confirm']) {
        $errors['password_confirm'] = 'Passwords do not match';
    }

    return $errors;
}

/**
 * Validate RSVP data
 */
function vt_validate_rsvp_data(array $data): array {
    $errors = [];

    // Required fields
    $required = ['status'];
    $errors = array_merge($errors, vt_validate_required($data, $required));

    // Status validation
    if (!empty($data['status']) && !in_array($data['status'], ['attending', 'maybe', 'declined'])) {
        $errors['status'] = 'Invalid RSVP status';
    }

    // Plus one validation
    if (!empty($data['plus_one']) && !vt_validate_int_range((int)$data['plus_one'], 0, 10)) {
        $errors['plus_one'] = 'Plus one count must be between 0 and 10';
    }

    // Guest name for non-logged-in users
    if (empty($data['user_id']) && empty($data['guest_name'])) {
        $errors['guest_name'] = 'Name is required';
    }

    // Email for non-logged-in users
    if (empty($data['user_id']) && empty($data['email'])) {
        $errors['email'] = 'Email is required';
    }

    return $errors;
}

/**
 * Validate conversation data
 */
function vt_validate_conversation_data(array $data): array {
    $errors = [];

    // Required fields
    $required = ['title', 'content'];
    $errors = array_merge($errors, vt_validate_required($data, $required));

    // Title length
    if (!empty($data['title']) && strlen($data['title']) > 200) {
        $errors['title'] = 'Title must be less than 200 characters';
    }

    // Content length
    if (!empty($data['content']) && strlen($data['content']) > 5000) {
        $errors['content'] = 'Content must be less than 5,000 characters';
    }

    // Conversation type
    if (!empty($data['conversation_type']) && !in_array($data['conversation_type'], ['discussion', 'question', 'announcement'])) {
        $errors['conversation_type'] = 'Invalid conversation type';
    }

    return $errors;
}

/**
 * Validate file upload
 */
function vt_validate_file_upload(array $file, array $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], int $max_size = 5242880): array {
    $errors = [];

    // Check if file was uploaded
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File is too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'File upload was incomplete';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file was uploaded';
                break;
            default:
                $errors[] = 'File upload failed';
        }
        return $errors;
    }

    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File is too large (max ' . round($max_size / 1024 / 1024, 1) . 'MB)';
    }

    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        $errors[] = 'File type not allowed';
    }

    return $errors;
}

/**
 * Sanitize array of data
 */
function vt_sanitize_array(array $data, array $fields): array {
    $sanitized = [];

    foreach ($fields as $field => $type) {
        if (isset($data[$field])) {
            switch ($type) {
                case 'text':
                    $sanitized[$field] = vt_sanitize_text($data[$field]);
                    break;
                case 'textarea':
                    $sanitized[$field] = vt_sanitize_textarea($data[$field]);
                    break;
                case 'email':
                    $sanitized[$field] = filter_var($data[$field], FILTER_SANITIZE_EMAIL);
                    break;
                case 'url':
                    $sanitized[$field] = filter_var($data[$field], FILTER_SANITIZE_URL);
                    break;
                case 'int':
                    $sanitized[$field] = (int) $data[$field];
                    break;
                case 'float':
                    $sanitized[$field] = (float) $data[$field];
                    break;
                case 'bool':
                    $sanitized[$field] = !empty($data[$field]);
                    break;
                default:
                    $sanitized[$field] = $data[$field];
            }
        }
    }

    return $sanitized;
}

/**
 * Generate CSRF token
 */
function vt_generate_csrf_token(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = Database::generateToken(32);
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function vt_verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate and sanitize search query
 */
function vt_validate_search_query(string $query): string {
    // Remove excessive whitespace
    $query = trim(preg_replace('/\s+/', ' ', $query));

    // Limit length
    if (strlen($query) > 100) {
        $query = substr($query, 0, 100);
    }

    // Remove special characters that could cause issues
    $query = preg_replace('/[<>"\']/', '', $query);

    return $query;
}