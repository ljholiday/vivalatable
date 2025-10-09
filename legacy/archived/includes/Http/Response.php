<?php
/**
 * HTTP Response Object
 * PSR-7 inspired response handling to replace VT_Http static methods
 */

class VT_Http_Response {

    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';
    private bool $sent = false;

    private static array $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
    ];

    public function __construct(string $body = '', int $statusCode = 200, array $headers = []) {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Set status code
     */
    public function setStatusCode(int $code): self {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Get status code
     */
    public function getStatusCode(): int {
        return $this->statusCode;
    }

    /**
     * Set header
     */
    public function setHeader(string $name, string $value): self {
        $this->headers[strtolower($name)] = $value;
        return $this;
    }

    /**
     * Add header (allows duplicates)
     */
    public function addHeader(string $name, string $value): self {
        $name = strtolower($name);
        if (isset($this->headers[$name])) {
            if (is_array($this->headers[$name])) {
                $this->headers[$name][] = $value;
            } else {
                $this->headers[$name] = [$this->headers[$name], $value];
            }
        } else {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    /**
     * Get header
     */
    public function getHeader(string $name): ?string {
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }

    /**
     * Get all headers
     */
    public function getHeaders(): array {
        return $this->headers;
    }

    /**
     * Set body content
     */
    public function setBody(string $body): self {
        $this->body = $body;
        return $this;
    }

    /**
     * Get body content
     */
    public function getBody(): string {
        return $this->body;
    }

    /**
     * Create JSON response
     */
    public static function json(array $data, int $statusCode = 200): self {
        $response = new self(json_encode($data), $statusCode);
        $response->setHeader('Content-Type', 'application/json');
        return $response;
    }

    /**
     * Create success JSON response
     */
    public static function jsonSuccess(array $data = [], string $message = 'Success'): self {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Create error JSON response
     */
    public static function jsonError(string $message = 'Error', string $code = 'error', int $statusCode = 400): self {
        return self::json([
            'success' => false,
            'error' => $code,
            'message' => $message
        ], $statusCode);
    }

    /**
     * Create redirect response
     */
    public static function redirect(string $location, int $statusCode = 302): self {
        $response = new self('', $statusCode);
        $response->setHeader('Location', $location);
        return $response;
    }

    /**
     * Create safe redirect (same domain only)
     */
    public static function safeRedirect(string $location, int $statusCode = 302): self {
        // Parse URL to check host
        $parsed = parse_url($location);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';

        // If external redirect, redirect to home instead
        if (isset($parsed['host']) && $parsed['host'] !== $currentHost) {
            return self::redirect('/', $statusCode);
        }

        return self::redirect($location, $statusCode);
    }

    /**
     * Set cache headers
     */
    public function setCacheHeaders(int $seconds = 3600): self {
        $expires = gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT';

        $this->setHeader('Cache-Control', 'public, max-age=' . $seconds);
        $this->setHeader('Expires', $expires);
        $this->setHeader('Pragma', 'cache');

        return $this;
    }

    /**
     * Set no-cache headers
     */
    public function setNoCacheHeaders(): self {
        $this->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('Expires', '0');

        return $this;
    }

    /**
     * Set security headers
     */
    public function setSecurityHeaders(): self {
        $this->setHeader('X-Content-Type-Options', 'nosniff');
        $this->setHeader('X-Frame-Options', 'DENY');
        $this->setHeader('X-XSS-Protection', '1; mode=block');
        $this->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $this;
    }

    /**
     * Send response to browser
     */
    public function send(): void {
        if ($this->sent) {
            return;
        }

        // Send status code
        if (!headers_sent()) {
            $statusText = self::$statusTexts[$this->statusCode] ?? 'Unknown';
            header("HTTP/1.1 {$this->statusCode} {$statusText}");

            // Send headers
            foreach ($this->headers as $name => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        header("$name: $v", false);
                    }
                } else {
                    header("$name: $value");
                }
            }
        }

        // Send body
        echo $this->body;

        $this->sent = true;
    }

    /**
     * Terminate with error (replaces VT_Http::die)
     */
    public static function terminate(string $message = '', string $title = '', int $statusCode = 500): void {
        $response = new self('', $statusCode);

        if (self::isAjaxRequest()) {
            $response = self::jsonError($message, 'error', $statusCode);
        } else {
            $html = self::renderErrorPage($title ?: 'Error', $message, $statusCode);
            $response->setBody($html);
            $response->setHeader('Content-Type', 'text/html');
        }

        $response->send();
        exit;
    }

    /**
     * Check if AJAX request
     */
    private static function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Render error page HTML
     */
    private static function renderErrorPage(string $title, string $message, int $code): string {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8');

        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{$safeTitle} - VivalaTable</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 2.5rem 1.25rem;
            background: #f1f1f1;
            color: #333;
        }
        .vt-error-container {
            max-width: 37.5rem;
            margin: 0 auto;
            background: white;
            padding: 2.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.625rem rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            color: #d63384;
            margin-bottom: 1.25rem;
            font-size: 3rem;
            font-weight: 300;
        }
        h2 {
            margin-bottom: 1.25rem;
            color: #666;
        }
        p {
            line-height: 1.6;
            margin-bottom: 1.875rem;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 0.25rem;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #005a87;
        }
    </style>
</head>
<body>
    <div class=\"vt-error-container\">
        <h1>{$safeCode}</h1>
        <h2>{$safeTitle}</h2>
        " . ($message ? "<p>{$safeMessage}</p>" : "") . "
        <a href=\"/\" class=\"btn\">Go Home</a>
    </div>
</body>
</html>";
    }
}