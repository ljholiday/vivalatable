<?php
/**
 * VivalaTable HTTP System
 * Replacement for WordPress HTTP functions
 */

class VT_Http {

    public static function redirect($location, $status = 302) {
        if (headers_sent()) {
            echo "<script>window.location.href='$location';</script>";
            return;
        }

        http_response_code($status);
        header("Location: $location");
        exit;
    }

    public static function safeRedirect($location, $status = 302) {
        // Validate the redirect location
        $allowed_hosts = [
            $_SERVER['HTTP_HOST'],
            'www.' . $_SERVER['HTTP_HOST']
        ];

        $parsed_url = parse_url($location);

        if (isset($parsed_url['host']) && !in_array($parsed_url['host'], $allowed_hosts)) {
            // External redirect - redirect to safe page instead
            self::redirect('/', $status);
            return;
        }

        self::redirect($location, $status);
    }

    public static function die($message = '', $title = '', $args = []) {
        $title = $title ?: 'Error';
        $response = $args['response'] ?? 500;

        http_response_code($response);

        if (self::isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => $message,
                'title' => $title
            ]);
        } else {
            echo self::renderErrorPage($title, $message, $response);
        }

        exit;
    }

    private static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    private static function renderErrorPage($title, $message, $code) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo vt_service('validation.validator')->escHtml($title); ?> - VivalaTable</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    margin: 0;
                    padding: 40px 20px;
                    background: #f1f1f1;
                    color: #333;
                }
                .vt-error-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    text-align: center;
                }
                h1 {
                    color: #d63384;
                    margin-bottom: 20px;
                    font-size: 48px;
                    font-weight: 300;
                }
                h2 {
                    margin-bottom: 20px;
                    color: #666;
                }
                p {
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 24px;
                    background: #007cba;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    transition: background 0.3s;
                }
                .btn:hover {
                    background: #005a87;
                }
            </style>
        </head>
        <body>
            <div class="vt-error-container">
                <h1><?php echo vt_service('validation.validator')->escHtml($code); ?></h1>
                <h2><?php echo vt_service('validation.validator')->escHtml($title); ?></h2>
                <?php if ($message): ?>
                    <p><?php echo vt_service('validation.validator')->escHtml($message); ?></p>
                <?php endif; ?>
                <a href="/" class="btn">Go Home</a>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    public static function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function jsonSuccess($data = [], $message = 'Success') {
        self::jsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    public static function jsonError($message = 'Error', $code = 'error', $status = 400) {
        self::jsonResponse([
            'success' => false,
            'error' => $code,
            'message' => $message
        ], $status);
    }

    public static function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public static function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];

        return $protocol . '://' . $host . $uri;
    }

    public static function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        return $protocol . '://' . $host;
    }

    public static function isPost() {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    public static function isGet() {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    public static function isPut() {
        return $_SERVER['REQUEST_METHOD'] === 'PUT';
    }

    public static function isDelete() {
        return $_SERVER['REQUEST_METHOD'] === 'DELETE';
    }

    public static function getRequestMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }

    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }

        return vt_service('validation.validator')->textField($input);
    }

    public static function validateReferer() {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'];

        if (empty($referer)) {
            return false;
        }

        $referer_host = parse_url($referer, PHP_URL_HOST);
        return $referer_host === $host;
    }

    public static function setHeaders($headers) {
        foreach ($headers as $header) {
            header($header);
        }
    }

    public static function setCacheHeaders($seconds = 3600) {
        $expires = gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT';

        header('Cache-Control: public, max-age=' . $seconds);
        header('Expires: ' . $expires);
        header('Pragma: cache');
    }

    public static function setNoCacheHeaders() {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}