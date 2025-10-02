<?php
/**
 * Embed Service
 * Fetches Open Graph metadata for simple link preview cards
 */

class VT_Embed_Service {

	private const CACHE_PREFIX = 'embed_';
	private const CACHE_DURATION = 604800; // 7 days

	/**
	 * Build embed data from URL
	 */
	public static function buildEmbedFromUrl(string $url): ?array {
		if (empty($url)) {
			return null;
		}

		// Check cache first
		$cacheKey = self::getCacheKey($url);
		$cached = VT_Transient::get($cacheKey);

		if ($cached !== false) {
			// If we cached a failure, return null
			if (isset($cached['type']) && $cached['type'] === 'none') {
				return null;
			}
			return $cached;
		}

		// Try oEmbed ONLY for known video providers
		$embed = null;
		if (VT_Embed_OEmbedProvider::isSupported($url)) {
			$oembedData = VT_Embed_OEmbedProvider::fetch($url);
			// Only use oEmbed if it's a video type
			if ($oembedData && ($oembedData['oembed_type'] ?? '') === 'video') {
				$embed = $oembedData;
			}
		}

		// If no video embed, use Open Graph for nice cards
		if (!$embed) {
			$embed = self::fetchOpenGraph($url);
		}

		if ($embed) {
			// Cache success
			VT_Transient::set($cacheKey, $embed, self::CACHE_DURATION);
			return $embed;
		}

		// Cache failure to avoid repeated attempts
		VT_Transient::set($cacheKey, ['type' => 'none'], self::CACHE_DURATION);

		return null;
	}

	/**
	 * Fetch Open Graph metadata from URL
	 */
	private static function fetchOpenGraph(string $url): ?array {
		// Fetch HTML
		$response = VT_Http_Client::get($url, [
			'timeout' => 4,
			'max_redirects' => 3,
		]);

		if (!$response['success'] || empty($response['body'])) {
			return null;
		}

		$html = $response['body'];

		// Parse OG tags with regex (fast, simple)
		$title = null;
		$description = null;
		$image = null;

		// OG tags
		if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
			$title = $m[1];
		}
		if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
			$description = $m[1];
		}
		if (preg_match('/<meta\s+property=["\']og:image(?::secure_url)?["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
			$image = $m[1];
		}

		// Fallbacks
		if (!$title && preg_match('/<title>\s*(.*?)\s*<\/title>/si', $html, $m)) {
			$title = strip_tags($m[1]);
		}
		if (!$description && preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
			$description = $m[1];
		}

		// Require image (partyminder approach)
		if (!$image) {
			return null;
		}

		// Make image URL absolute if needed
		$image = self::makeAbsoluteUrl($image, $url);

		return [
			'type' => 'opengraph',
			'title' => $title ? strip_tags($title) : '',
			'description' => $description ? strip_tags($description) : '',
			'image' => $image,
			'url' => $url,
			'fetched_at' => time(),
		];
	}

	/**
	 * Convert relative URL to absolute
	 */
	private static function makeAbsoluteUrl(string $maybeRelative, string $base): string {
		// Already absolute
		if (preg_match('~^https?://~i', $maybeRelative)) {
			return $maybeRelative;
		}

		$parsed = parse_url($base);
		if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
			return $maybeRelative;
		}

		$scheme = $parsed['scheme'];
		$host = $parsed['host'];
		$port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

		// Absolute path
		if (strpos($maybeRelative, '/') === 0) {
			return "{$scheme}://{$host}{$port}{$maybeRelative}";
		}

		// Relative path
		$path = isset($parsed['path']) ? rtrim(dirname($parsed['path']), '/') : '';
		return "{$scheme}://{$host}{$port}{$path}/{$maybeRelative}";
	}

	/**
	 * Generate cache key for URL
	 */
	private static function getCacheKey(string $url): string {
		return self::CACHE_PREFIX . md5($url);
	}

	/**
	 * Clear cached embed for URL
	 */
	public static function clearCache(string $url): void {
		$cacheKey = self::getCacheKey($url);
		VT_Transient::delete($cacheKey);
	}

	/**
	 * Clear all embed caches
	 */
	public static function clearAllCaches(): void {
		// Clear from session storage
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		if (!isset($_SESSION['vt_transients'])) {
			return;
		}

		// Remove all keys starting with embed_
		foreach (array_keys($_SESSION['vt_transients']) as $key) {
			if (strpos($key, self::CACHE_PREFIX) === 0) {
				unset($_SESSION['vt_transients'][$key]);
			}
		}
	}
}
