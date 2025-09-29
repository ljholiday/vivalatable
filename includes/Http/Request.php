<?php
/**
 * HTTP Request Object
 * PSR-7 inspired request handling to replace VT_Http static methods
 */

class VT_Http_Request {

    private array $server;
    private array $query;
    private array $post;
    private array $files;
    private array $cookies;
    private array $headers;
    private string $method;
    private string $uri;
    private string $body;

    public function __construct(
        array $server = [],
        array $query = [],
        array $post = [],
        array $files = [],
        array $cookies = []
    ) {
        $this->server = $server;
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->headers = $this->parseHeaders();
        $this->method = $this->parseMethod();
        $this->uri = $this->parseUri();
        $this->body = $this->parseBody();
    }

    /**
     * Create request from PHP globals
     */
    public static function createFromGlobals(): self {
        return new self($_SERVER, $_GET, $_POST, $_FILES, $_COOKIE);
    }

    /**
     * Get HTTP method
     */
    public function getMethod(): string {
        return $this->method;
    }

    /**
     * Check if method matches
     */
    public function isMethod(string $method): bool {
        return strtoupper($this->method) === strtoupper($method);
    }

    /**
     * Check if POST request
     */
    public function isPost(): bool {
        return $this->isMethod('POST');
    }

    /**
     * Check if GET request
     */
    public function isGet(): bool {
        return $this->isMethod('GET');
    }

    /**
     * Check if AJAX request
     */
    public function isAjax(): bool {
        return strtolower($this->getHeader('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * Get request URI
     */
    public function getUri(): string {
        return $this->uri;
    }

    /**
     * Get request path (without query string)
     */
    public function getPath(): string {
        return parse_url($this->uri, PHP_URL_PATH) ?: '/';
    }

    /**
     * Get query parameter
     */
    public function query(string $key, $default = null) {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get all query parameters
     */
    public function getQuery(): array {
        return $this->query;
    }

    /**
     * Get POST parameter
     */
    public function post(string $key, $default = null) {
        return $this->post[$key] ?? $default;
    }

    /**
     * Get all POST parameters
     */
    public function getPost(): array {
        return $this->post;
    }

    /**
     * Get input parameter (POST first, then GET)
     */
    public function input(string $key, $default = null) {
        return $this->post($key) ?? $this->query($key, $default);
    }

    /**
     * Get all input parameters
     */
    public function getInput(): array {
        return array_merge($this->query, $this->post);
    }

    /**
     * Get uploaded file
     */
    public function file(string $key): ?array {
        return $this->files[$key] ?? null;
    }

    /**
     * Get all uploaded files
     */
    public function getFiles(): array {
        return $this->files;
    }

    /**
     * Get cookie value
     */
    public function cookie(string $key, $default = null) {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Get header value
     */
    public function getHeader(string $name, $default = null) {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * Get all headers
     */
    public function getHeaders(): array {
        return $this->headers;
    }

    /**
     * Get server parameter
     */
    public function server(string $key, $default = null) {
        return $this->server[$key] ?? $default;
    }

    /**
     * Get client IP address
     */
    public function getClientIp(): string {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (!empty($this->server[$key])) {
                foreach (explode(',', $this->server[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent
     */
    public function getUserAgent(): string {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get current URL
     */
    public function getCurrentUrl(): string {
        $protocol = $this->isSecure() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . $this->uri;
    }

    /**
     * Get base URL
     */
    public function getBaseUrl(): string {
        $protocol = $this->isSecure() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Check if HTTPS
     */
    public function isSecure(): bool {
        return isset($this->server['HTTPS']) && $this->server['HTTPS'] === 'on';
    }

    /**
     * Get request body
     */
    public function getBody(): string {
        return $this->body;
    }

    /**
     * Parse headers from server variables
     */
    private function parseHeaders(): array {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        // Special cases
        if (isset($this->server['CONTENT_TYPE'])) {
            $headers['content-type'] = $this->server['CONTENT_TYPE'];
        }

        if (isset($this->server['CONTENT_LENGTH'])) {
            $headers['content-length'] = $this->server['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * Parse HTTP method
     */
    private function parseMethod(): string {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Parse request URI
     */
    private function parseUri(): string {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * Parse request body
     */
    private function parseBody(): string {
        return file_get_contents('php://input') ?: '';
    }

    /**
     * Validate referer for CSRF protection
     */
    public function validateReferer(): bool {
        $referer = $this->getHeader('referer', '');
        $host = $this->server['HTTP_HOST'] ?? '';

        if (empty($referer) || empty($host)) {
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        return $refererHost === $host;
    }
}