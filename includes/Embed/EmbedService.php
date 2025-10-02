<?php
/**
 * Embed Service
 * Main service for fetching and caching embed data from URLs
 * Tries oEmbed first, falls back to Open Graph, caches results
 */

class VT_Embed_Service {

	private const CACHE_PREFIX = 'embed_';
	private const CACHE_DURATION = 86400; // 24 hours

	/**
	 * Build embed data from URL
	 * Tries oEmbed first, then Open Graph, then returns null
	 */
	public static function buildEmbedFromUrl(string $url): ?array {
		if (empty($url)) {
			return null;
		}

		// Check cache first
		$cached = self::getCachedEmbed($url);
		if ($cached !== null) {
			return $cached;
		}

		// Try oEmbed first (richer data, better embeds)
		$embedData = VT_Embed_OEmbedProvider::fetch($url);

		if ($embedData) {
			$embedData['source_url'] = $url;
			self::cacheEmbed($url, $embedData);
			return $embedData;
		}

		// Fallback to Open Graph
		$embedData = VT_Embed_OpenGraphProvider::fetch($url);

		if ($embedData) {
			$embedData['source_url'] = $url;
			self::cacheEmbed($url, $embedData);
			return $embedData;
		}

		// Cache null result to avoid repeated failures
		self::cacheEmbed($url, ['type' => 'none', 'source_url' => $url]);

		return null;
	}

	/**
	 * Get cached embed data
	 */
	public static function getCachedEmbed(string $url): ?array {
		$cacheKey = self::getCacheKey($url);
		$cached = VT_Transient::get($cacheKey);

		if ($cached === false) {
			return null;
		}

		// If we cached a failure, return null
		if (isset($cached['type']) && $cached['type'] === 'none') {
			return null;
		}

		return $cached;
	}

	/**
	 * Cache embed data
	 */
	public static function cacheEmbed(string $url, array $data): void {
		$cacheKey = self::getCacheKey($url);

		// Determine cache duration
		$duration = self::CACHE_DURATION;

		// Use provider's cache_age if available
		if (isset($data['cache_age']) && is_numeric($data['cache_age'])) {
			$duration = (int)$data['cache_age'];
		}

		VT_Transient::set($cacheKey, $data, $duration);
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
		// Get all transient keys starting with embed_
		$db = VT_Database::getInstance();
		$prefix = $db->prefix;

		$db->query(
			$db->prepare(
				"DELETE FROM {$prefix}transients WHERE transient_key LIKE %s",
				self::CACHE_PREFIX . '%'
			)
		);
	}

	/**
	 * Check if URL can be embedded
	 */
	public static function canEmbed(string $url): bool {
		// Check if it's a valid URL
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return false;
		}

		// Check if oEmbed is supported
		if (VT_Embed_OEmbedProvider::isSupported($url)) {
			return true;
		}

		// For Open Graph, we need to fetch to check
		// Don't do this in canEmbed - too expensive
		// Let buildEmbedFromUrl handle it
		return true;
	}

	/**
	 * Get embed stats (for debugging)
	 */
	public static function getStats(): array {
		$db = VT_Database::getInstance();
		$prefix = $db->prefix;

		$total = $db->getVar(
			$db->prepare(
				"SELECT COUNT(*) FROM {$prefix}transients WHERE transient_key LIKE %s",
				self::CACHE_PREFIX . '%'
			)
		);

		$expired = $db->getVar(
			$db->prepare(
				"SELECT COUNT(*) FROM {$prefix}transients
				WHERE transient_key LIKE %s AND expires_at < NOW()",
				self::CACHE_PREFIX . '%'
			)
		);

		return [
			'total_cached' => (int)$total,
			'expired' => (int)$expired,
			'active' => (int)$total - (int)$expired,
		];
	}

	/**
	 * Process multiple URLs from text
	 */
	public static function processTextEmbeds(string $text, int $maxEmbeds = 1): array {
		$urls = VT_Text::extractUrls($text);

		if (empty($urls)) {
			return [];
		}

		$embeds = [];
		$count = 0;

		foreach ($urls as $url) {
			if ($count >= $maxEmbeds) {
				break;
			}

			$embed = self::buildEmbedFromUrl($url);

			if ($embed) {
				$embeds[] = $embed;
				$count++;
			}
		}

		return $embeds;
	}
}
