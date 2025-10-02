<?php
/**
 * HTTP Client for External Requests
 * Secure HTTP client for fetching external URLs (oEmbed endpoints, Open Graph data)
 */

class VT_Http_Client {

	private const USER_AGENT = 'VivalaTable/1.0 (https://vivalatable.com)';
	private const MAX_REDIRECTS = 3;
	private const TIMEOUT_SECONDS = 10;
	private const MAX_RESPONSE_SIZE = 5242880; // 5MB

	/**
	 * Perform GET request to external URL
	 */
	public static function get(string $url, array $options = []): array {
		// Validate URL
		if (!self::isValidUrl($url)) {
			return [
				'success' => false,
				'error' => 'Invalid URL',
				'status' => 0,
				'body' => null,
				'headers' => []
			];
		}

		// Security check: prevent SSRF attacks
		if (self::isPrivateIp($url)) {
			return [
				'success' => false,
				'error' => 'Access to private IPs is not allowed',
				'status' => 0,
				'body' => null,
				'headers' => []
			];
		}

		// Check if curl is available
		if (!function_exists('curl_init')) {
			return self::getViaFileGetContents($url, $options);
		}

		$ch = curl_init($url);

		// Set options
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => $options['max_redirects'] ?? self::MAX_REDIRECTS,
			CURLOPT_TIMEOUT => $options['timeout'] ?? self::TIMEOUT_SECONDS,
			CURLOPT_USERAGENT => $options['user_agent'] ?? self::USER_AGENT,
			CURLOPT_ENCODING => '', // Accept all encodings
			CURLOPT_HEADER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		]);

		// Additional headers
		if (!empty($options['headers'])) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
		}

		// Execute request
		$response = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		curl_close($ch);

		// Check for errors
		if ($response === false) {
			return [
				'success' => false,
				'error' => $error ?: 'Request failed',
				'status' => $httpCode,
				'body' => null,
				'headers' => []
			];
		}

		// Parse headers and body
		$headerString = substr($response, 0, $headerSize);
		$body = substr($response, $headerSize);
		$headers = self::parseHeaders($headerString);

		// Check response size
		if (strlen($body) > self::MAX_RESPONSE_SIZE) {
			return [
				'success' => false,
				'error' => 'Response too large',
				'status' => $httpCode,
				'body' => null,
				'headers' => $headers
			];
		}

		return [
			'success' => $httpCode >= 200 && $httpCode < 300,
			'error' => null,
			'status' => $httpCode,
			'body' => $body,
			'headers' => $headers
		];
	}

	/**
	 * Fallback to file_get_contents if curl not available
	 */
	private static function getViaFileGetContents(string $url, array $options = []): array {
		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'user_agent' => $options['user_agent'] ?? self::USER_AGENT,
				'timeout' => $options['timeout'] ?? self::TIMEOUT_SECONDS,
				'follow_location' => 1,
				'max_redirects' => $options['max_redirects'] ?? self::MAX_REDIRECTS,
				'ignore_errors' => true,
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
			]
		]);

		$body = @file_get_contents($url, false, $context);

		if ($body === false) {
			return [
				'success' => false,
				'error' => 'Request failed',
				'status' => 0,
				'body' => null,
				'headers' => []
			];
		}

		// Parse response headers
		$headers = [];
		if (isset($http_response_header)) {
			$headers = self::parseHeaders(implode("\r\n", $http_response_header));
		}

		// Extract status code
		$status = 200;
		if (isset($http_response_header[0])) {
			preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
			$status = isset($matches[1]) ? (int)$matches[1] : 200;
		}

		return [
			'success' => $status >= 200 && $status < 300,
			'error' => null,
			'status' => $status,
			'body' => $body,
			'headers' => $headers
		];
	}

	/**
	 * Validate URL format
	 */
	private static function isValidUrl(string $url): bool {
		$parsed = parse_url($url);

		if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
			return false;
		}

		// Only allow HTTP/HTTPS
		if (!in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
			return false;
		}

		return filter_var($url, FILTER_VALIDATE_URL) !== false;
	}

	/**
	 * Check if URL points to private/internal IP (SSRF prevention)
	 */
	private static function isPrivateIp(string $url): bool {
		$parsed = parse_url($url);
		$host = $parsed['host'] ?? '';

		// Resolve hostname to IP
		$ip = gethostbyname($host);

		// If resolution failed, block it
		if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
			return true;
		}

		// Check if IP is private/reserved
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
			return true;
		}

		// Block localhost variations
		$blocked = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
		if (in_array(strtolower($host), $blocked)) {
			return true;
		}

		return false;
	}

	/**
	 * Parse HTTP headers from string
	 */
	private static function parseHeaders(string $headerString): array {
		$headers = [];
		$lines = explode("\r\n", $headerString);

		foreach ($lines as $line) {
			if (strpos($line, ':') === false) {
				continue;
			}

			[$name, $value] = explode(':', $line, 2);
			$headers[strtolower(trim($name))] = trim($value);
		}

		return $headers;
	}

	/**
	 * Parse JSON response
	 */
	public static function getJson(string $url, array $options = []): array {
		$response = self::get($url, $options);

		if (!$response['success'] || empty($response['body'])) {
			return [
				'success' => false,
				'error' => $response['error'] ?? 'No response body',
				'data' => null
			];
		}

		$data = json_decode($response['body'], true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return [
				'success' => false,
				'error' => 'Invalid JSON: ' . json_last_error_msg(),
				'data' => null
			];
		}

		return [
			'success' => true,
			'error' => null,
			'data' => $data
		];
	}
}
