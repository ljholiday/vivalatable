<?php
/**
 * Validator Service
 * Modern replacement for VT_Sanitize with validation and sanitization
 */

class VT_Validation_ValidatorService {

    private VT_Validation_InputSanitizer $sanitizer;

    public function __construct(VT_Validation_InputSanitizer $sanitizer) {
        $this->sanitizer = $sanitizer;
    }

    /**
     * Validate and sanitize email
     */
    public function email(string $email): array {
        $sanitized = $this->sanitizer->email($email);
        $isValid = filter_var($sanitized, FILTER_VALIDATE_EMAIL) !== false;

        return [
            'value' => $sanitized,
            'is_valid' => $isValid,
            'errors' => $isValid ? [] : ['Invalid email format']
        ];
    }

    /**
     * Validate and sanitize URL
     */
    public function url(string $url): array {
        $sanitized = $this->sanitizer->url($url);
        $isValid = filter_var($sanitized, FILTER_VALIDATE_URL) !== false;

        return [
            'value' => $sanitized,
            'is_valid' => $isValid,
            'errors' => $isValid ? [] : ['Invalid URL format']
        ];
    }

    /**
     * Validate text field with length constraints
     */
    public function textField(string $input, int $minLength = 0, int $maxLength = 255): array {
        $sanitized = $this->sanitizer->textField($input);
        $length = strlen($sanitized);
        $errors = [];

        if ($length < $minLength) {
            $errors[] = "Text must be at least {$minLength} characters long";
        }

        if ($length > $maxLength) {
            $errors[] = "Text cannot exceed {$maxLength} characters";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate textarea with length constraints
     */
    public function textarea(string $input, int $maxLength = 5000): array {
        $sanitized = $this->sanitizer->textarea($input);
        $length = strlen($sanitized);
        $errors = [];

        if ($length > $maxLength) {
            $errors[] = "Text cannot exceed {$maxLength} characters";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate rich text content
     */
    public function richText(string $input, int $maxLength = 10000): array {
        $sanitized = $this->sanitizer->richText($input);
        $textLength = strlen(strip_tags($sanitized));
        $errors = [];

        if ($textLength > $maxLength) {
            $errors[] = "Content cannot exceed {$maxLength} characters";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate integer with range
     */
    public function integer($input, ?int $min = null, ?int $max = null): array {
        $sanitized = $this->sanitizer->integer($input);
        $errors = [];

        if ($min !== null && $sanitized < $min) {
            $errors[] = "Value must be at least {$min}";
        }

        if ($max !== null && $sanitized > $max) {
            $errors[] = "Value cannot exceed {$max}";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate float with range
     */
    public function float($input, ?float $min = null, ?float $max = null): array {
        $sanitized = $this->sanitizer->float($input);
        $errors = [];

        if ($min !== null && $sanitized < $min) {
            $errors[] = "Value must be at least {$min}";
        }

        if ($max !== null && $sanitized > $max) {
            $errors[] = "Value cannot exceed {$max}";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate required field
     */
    public function required(string $input, string $fieldName = 'Field'): array {
        $sanitized = trim($input);
        $isEmpty = empty($sanitized);

        return [
            'value' => $sanitized,
            'is_valid' => !$isEmpty,
            'errors' => $isEmpty ? ["{$fieldName} is required"] : []
        ];
    }

    /**
     * Validate slug format
     */
    public function slug(string $input): array {
        $sanitized = $this->sanitizer->slug($input);
        $isValid = !empty($sanitized) && preg_match('/^[a-z0-9-]+$/', $sanitized);

        return [
            'value' => $sanitized,
            'is_valid' => $isValid,
            'errors' => $isValid ? [] : ['Invalid slug format (use lowercase letters, numbers, and dashes only)']
        ];
    }

    /**
     * Validate phone number
     */
    public function phoneNumber(string $input): array {
        $sanitized = $this->sanitizer->phoneNumber($input);
        $isValid = preg_match('/^[\+]?[0-9\-\(\)\s]{7,20}$/', $sanitized);

        return [
            'value' => $sanitized,
            'is_valid' => $isValid,
            'errors' => $isValid ? [] : ['Invalid phone number format']
        ];
    }

    /**
     * Validate password strength
     */
    public function password(string $password, int $minLength = 8): array {
        $errors = [];

        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        return [
            'value' => $password, // Don't sanitize passwords
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate array of inputs
     */
    public function validateArray(array $rules, array $input): array {
        $results = [];
        $allValid = true;

        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? '';
            $method = $rule['method'] ?? 'textField';
            $params = $rule['params'] ?? [];

            $result = $this->{$method}($value, ...$params);
            $results[$field] = $result;

            if (!$result['is_valid']) {
                $allValid = false;
            }
        }

        return [
            'fields' => $results,
            'is_valid' => $allValid,
            'errors' => $this->collectErrors($results)
        ];
    }

    /**
     * Quick sanitization methods (for backward compatibility)
     */
    public function escHtml(string $text): string {
        return $this->sanitizer->escapeHtml($text);
    }

    public function escAttr(string $text): string {
        return $this->sanitizer->escapeAttribute($text);
    }

    public function escUrl(string $url): string {
        return $this->sanitizer->escapeUrl($url);
    }

    /**
     * Collect all errors from validation results
     */
    private function collectErrors(array $results): array {
        $errors = [];
        foreach ($results as $field => $result) {
            if (!$result['is_valid']) {
                $errors[$field] = $result['errors'];
            }
        }
        return $errors;
    }

    /**
     * Validate file upload
     */
    public function fileUpload(array $file, array $allowedTypes = [], int $maxSize = 5242880): array {
        $errors = [];

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No valid file uploaded';
            return ['is_valid' => false, 'errors' => $errors];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . $this->getUploadErrorMessage($file['error']);
        }

        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size of ' . ($maxSize / 1024 / 1024) . 'MB';
        }

        if (!empty($allowedTypes)) {
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = 'File type not allowed';
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'filename' => $this->sanitizer->filename($file['name']),
            'size' => $file['size'],
            'type' => $file['type']
        ];
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File too large (php.ini limit)';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File too large (form limit)';
            case UPLOAD_ERR_PARTIAL:
                return 'File partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'No temporary directory';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Cannot write to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload blocked by extension';
            default:
                return 'Unknown upload error';
        }
    }
}