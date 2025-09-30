<?php
/**
 * Security Service
 * Modern replacement for VT_Security static methods
 */

class VT_Security_SecurityService {

    private int $nonceLifetime = 86400; // 24 hours
    private ?string $secretKey = null;
    private array $salts = [];

    public function __construct() {
        $this->initializeSecrets();
    }

    /**
     * Initialize security system (for compatibility)
     */
    public function init(): void {
        // Security initialization is handled in constructor
        // This method exists for compatibility with legacy VT_Security::init() calls
    }

    /**
     * Initialize security secrets
     */
    private function initializeSecrets(): void {
        // In production, these should come from environment variables or secure config
        $this->secretKey = $this->getOrGenerateSecret('security_key');
        $this->salts = $this->getOrGenerateSalts();
    }

    /**
     * Generate secure hash
     */
    public function hash(string $data, string $scheme = 'auth'): string {
        $salt = $this->getSalt($scheme);
        return hash_hmac('sha256', $data, $salt);
    }

    /**
     * Get salt for specific scheme
     */
    public function getSalt(string $scheme = 'auth'): string {
        if (!isset($this->salts[$scheme])) {
            $this->salts[$scheme] = bin2hex(random_bytes(32));
            $this->storeSalts();
        }

        return $this->salts[$scheme];
    }

    /**
     * Create CSRF nonce
     */
    public function createNonce(string $action = '', int $userId = 0): string {
        $timeBucket = floor(time() / ($this->nonceLifetime / 2)) * ($this->nonceLifetime / 2);
        $token = $userId . '|' . $action . '|' . $timeBucket;
        $hash = $this->hash($token, 'nonce');

        return substr($hash, 0, 12);
    }

    /**
     * Verify CSRF nonce
     */
    public function verifyNonce(string $nonce, string $action = '', int $userId = 0): bool {
        if (empty($nonce)) {
            return false;
        }

        $currentTime = time();

        // Check current and previous time buckets (12-hour windows within 24-hour lifetime)
        for ($i = 0; $i <= 2; $i++) {
            $timeBucket = floor(($currentTime - ($i * ($this->nonceLifetime / 2))) / ($this->nonceLifetime / 2)) * ($this->nonceLifetime / 2);
            $token = $userId . '|' . $action . '|' . $timeBucket;
            $expectedHash = $this->hash($token, 'nonce');
            $expectedNonce = substr($expectedHash, 0, 12);

            if (hash_equals($expectedNonce, $nonce)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate nonce field HTML
     */
    public function nonceField(string $action = '', string $name = '_vt_nonce', bool $includeReferer = true): string {
        $nonce = $this->createNonce($action);
        $field = '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES) . '" value="' . htmlspecialchars($nonce, ENT_QUOTES) . '" />';

        if ($includeReferer) {
            $referer = $_SERVER['REQUEST_URI'] ?? '';
            $field .= '<input type="hidden" name="_vt_http_referer" value="' . htmlspecialchars($referer, ENT_QUOTES) . '" />';
        }

        return $field;
    }

    /**
     * Generate secure random token
     */
    public function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate secure password
     */
    public function generatePassword(int $length = 16): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    /**
     * Constant time string comparison
     */
    public function compareStrings(string $a, string $b): bool {
        return hash_equals($a, $b);
    }

    /**
     * Generate CSRF token for forms
     */
    public function generateCSRFToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $this->generateToken(32);
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token from request
     */
    public function verifyCSRFToken(?string $token): bool {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return $this->compareStrings($_SESSION['csrf_token'], $token);
    }

    /**
     * Rate limiting check
     */
    public function checkRateLimit(string $key, int $maxAttempts = 5, int $timeWindow = 3600): bool {
        $attempts = $_SESSION['rate_limit'][$key] ?? [];
        $currentTime = time();

        // Remove old attempts outside time window
        $attempts = array_filter($attempts, function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });

        // Check if limit exceeded
        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        // Record this attempt
        $attempts[] = $currentTime;
        $_SESSION['rate_limit'][$key] = $attempts;

        return true;
    }

    /**
     * Sanitize POST data against CSRF
     */
    public function sanitizePost(array $data): array {
        if (!$this->verifyCSRFToken($data['_vt_csrf_token'] ?? null)) {
            throw new SecurityException('CSRF token verification failed');
        }

        // Remove security fields from data
        unset($data['_vt_csrf_token'], $data['_vt_http_referer'], $data['_vt_nonce']);

        return $data;
    }

    /**
     * Check if request is from same origin
     */
    public function verifySameOrigin(): bool {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (empty($referer) || empty($host)) {
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        return $refererHost === $host;
    }

    /**
     * Generate security headers
     */
    public function getSecurityHeaders(): array {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
        ];
    }

    /**
     * Hash password securely
     */
    public function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify password against hash
     */
    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Encrypt sensitive data
     */
    public function encrypt(string $data): string {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->secretKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt(string $encryptedData): ?string {
        $data = base64_decode($encryptedData);
        if ($data === false || strlen($data) < 16) {
            return null;
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->secretKey, 0, $iv);
        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Get or generate secret key
     */
    private function getOrGenerateSecret(string $keyName): string {
        // In production, load from environment or secure config
        $key = $_ENV[$keyName] ?? null;

        if (!$key) {
            $key = bin2hex(random_bytes(32));
            // In production, this should be stored securely
        }

        return $key;
    }

    /**
     * Get or generate salts
     */
    private function getOrGenerateSalts(): array {
        // Use constant salts from config or generate persistent ones
        // In a real app, these should be in config and never change
        $config_path = dirname(__DIR__, 2) . '/config/security_salts.php';

        if (file_exists($config_path)) {
            return require $config_path;
        }

        // Generate and save salts if they don't exist
        $salts = [
            'auth' => bin2hex(random_bytes(32)),
            'nonce' => bin2hex(random_bytes(32)),
            'session' => bin2hex(random_bytes(32))
        ];

        $content = "<?php\n// Security salts - DO NOT commit to git\nreturn " . var_export($salts, true) . ";\n";
        file_put_contents($config_path, $content);

        return $salts;
    }

    /**
     * Store salts securely
     */
    private function storeSalts(): void {
        // In production, store in secure config or database
        // For now, keep in memory only
    }
}

/**
 * Security Exception
 */
class SecurityException extends Exception {}