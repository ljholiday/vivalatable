<?php
/**
 * VivalaTable Security System
 * Replacement for WordPress security functions
 */

class VT_Security {
    private static $nonce_life = 86400; // 24 hours
    private static $secret_key = null;

    public static function init() {
        self::$secret_key = self::getSecretKey();
    }

    private static function getSecretKey() {
        $key = VT_Config::get('security_key', null);
        if (!$key) {
            $key = bin2hex(random_bytes(32));
            VT_Config::update('security_key', $key);
        }
        return $key;
    }

    public static function hash($data, $scheme = 'auth') {
        $salt = self::getSalt($scheme);
        return hash_hmac('sha256', $data, $salt);
    }

    public static function getSalt($scheme = 'auth') {
        $salts = VT_Config::get('security_salts', []);

        if (!isset($salts[$scheme])) {
            $salts[$scheme] = bin2hex(random_bytes(32));
            VT_Config::update('security_salts', $salts);
        }

        return $salts[$scheme];
    }

    public static function createNonce($action = -1) {
        $user_id = VT_Auth::getCurrentUserId();
        $token = $user_id . '|' . $action . '|' . time();
        $hash = self::hash($token);

        return substr($hash, 0, 10);
    }

    public static function verifyNonce($nonce, $action = -1) {
        if (!$nonce) {
            return false;
        }

        $user_id = VT_Auth::getCurrentUserId();
        $current_time = time();

        // Check against multiple time windows to account for clock drift
        for ($i = 0; $i <= 2; $i++) {
            $time = $current_time - ($i * (self::$nonce_life / 2));
            $token = $user_id . '|' . $action . '|' . $time;
            $expected_hash = self::hash($token);
            $expected_nonce = substr($expected_hash, 0, 10);

            if (hash_equals($expected_nonce, $nonce)) {
                return true;
            }
        }

        return false;
    }

    public static function nonce_field($action = -1, $name = "_wpnonce", $referer = true, $echo = true) {
        return self::nonceField($action, $name, $referer, $echo);
    }

    public static function nonceField($action = -1, $name = "_wpnonce", $referer = true, $echo = true) {
        $nonce = self::createNonce($action);
        $field = '<input type="hidden" name="' . $name . '" value="' . $nonce . '" />';

        if ($referer) {
            $field .= '<input type="hidden" name="_wp_http_referer" value="' . VT_Sanitize::escAttr($_SERVER['REQUEST_URI']) . '" />';
        }

        if ($echo) {
            echo $field;
        } else {
            return $field;
        }
    }

    public static function checkAjaxReferer($action = -1, $query_arg = false, $die = true) {
        $query_arg = $query_arg ?: '_wpnonce';
        $nonce = $_REQUEST[$query_arg] ?? '';

        if (!self::verifyNonce($nonce, $action)) {
            if ($die) {
                http_response_code(403);
                echo json_encode(['error' => 'Security check failed']);
                exit;
            }
            return false;
        }

        return true;
    }

    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    public static function encryptData($data, $key = null) {
        $key = $key ?: self::$secret_key;
        $cipher = "AES-256-CBC";
        $iv = random_bytes(openssl_cipher_iv_length($cipher));
        $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decryptData($data, $key = null) {
        $key = $key ?: self::$secret_key;
        $cipher = "AES-256-CBC";
        $data = base64_decode($data);
        $iv_length = openssl_cipher_iv_length($cipher);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
    }

    public static function rateLimitCheck($identifier, $max_attempts = 5, $window = 3600) {
        $key = 'rate_limit_' . hash('sha256', $identifier);
        $attempts = VT_Config::get($key, ['count' => 0, 'first_attempt' => time()]);

        if (time() - $attempts['first_attempt'] > $window) {
            // Reset window
            $attempts = ['count' => 1, 'first_attempt' => time()];
        } else {
            $attempts['count']++;
        }

        VT_Config::update($key, $attempts);

        return $attempts['count'] <= $max_attempts;
    }

    public static function validateCSRFToken() {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        $session_token = $_SESSION['csrf_token'] ?? '';

        return hash_equals($session_token, $token);
    }

    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }
        return $_SESSION['csrf_token'];
    }

    public static function kses_post($data) {
        return VT_Sanitize::post($data);
    }

    public static function sanitize_textarea($data) {
        return VT_Sanitize::post($data);
    }
}

// Initialize security system
VT_Security::init();