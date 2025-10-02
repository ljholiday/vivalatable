<?php
/**
 * oEmbed Provider
 * Discovers and fetches oEmbed data from supported providers
 */

class VT_Embed_OEmbedProvider {

	/**
	 * Known oEmbed providers (subset of most common ones)
	 */
	private static $providers = [
		// Video
		'youtube.com' => 'https://www.youtube.com/oembed',
		'youtu.be' => 'https://www.youtube.com/oembed',
		'vimeo.com' => 'https://vimeo.com/api/oembed.json',
		'dailymotion.com' => 'https://www.dailymotion.com/services/oembed',

		// Audio
		'soundcloud.com' => 'https://soundcloud.com/oembed',
		'spotify.com' => 'https://open.spotify.com/oembed',

		// Social (note: some require API keys)
		'twitter.com' => 'https://publish.twitter.com/oembed',
		'x.com' => 'https://publish.twitter.com/oembed',

		// Code
		'codepen.io' => 'https://codepen.io/api/oembed',

		// Design
		'figma.com' => 'https://www.figma.com/api/oembed',
	];

	/**
	 * Attempt to get oEmbed data for URL
	 */
	public static function fetch(string $url): ?array {
		// Try known providers first (faster, no HTML parsing needed)
		$endpoint = self::getKnownProviderEndpoint($url);

		if ($endpoint) {
			return self::fetchFromEndpoint($endpoint, $url);
		}

		// Fallback: discover endpoint from HTML
		$endpoint = self::discover($url);

		if ($endpoint) {
			return self::fetchFromEndpoint($endpoint, $url);
		}

		return null;
	}

	/**
	 * Get oEmbed endpoint for known providers
	 */
	private static function getKnownProviderEndpoint(string $url): ?string {
		$parsed = parse_url($url);
		$host = $parsed['host'] ?? '';

		// Remove www. prefix
		$host = preg_replace('/^www\./', '', $host);

		// Check if we have this provider
		if (isset(self::$providers[$host])) {
			return self::$providers[$host];
		}

		// Check for partial matches (e.g., subdomain.youtube.com)
		foreach (self::$providers as $domain => $endpoint) {
			if (strpos($host, $domain) !== false) {
				return $endpoint;
			}
		}

		return null;
	}

	/**
	 * Discover oEmbed endpoint from HTML link tags
	 */
	public static function discover(string $url): ?string {
		$response = VT_Http_Client::get($url);

		if (!$response['success'] || empty($response['body'])) {
			return null;
		}

		$html = $response['body'];

		// Look for oEmbed link tag
		// <link rel="alternate" type="application/json+oembed" href="...">
		if (preg_match('/<link[^>]+rel=(["\'])alternate\1[^>]+type=(["\'])application\/json\+oembed\2[^>]+href=(["\'])([^"\']+)\3/i', $html, $matches)) {
			return html_entity_decode($matches[4]);
		}

		// Try reversed attribute order
		if (preg_match('/<link[^>]+type=(["\'])application\/json\+oembed\1[^>]+rel=(["\'])alternate\2[^>]+href=(["\'])([^"\']+)\3/i', $html, $matches)) {
			return html_entity_decode($matches[4]);
		}

		return null;
	}

	/**
	 * Fetch oEmbed data from endpoint
	 */
	private static function fetchFromEndpoint(string $endpoint, string $url): ?array {
		// Build endpoint URL with parameters
		$separator = strpos($endpoint, '?') !== false ? '&' : '?';
		$oembedUrl = $endpoint . $separator . http_build_query([
			'url' => $url,
			'format' => 'json',
			'maxwidth' => 800,
			'maxheight' => 600,
		]);

		$response = VT_Http_Client::getJson($oembedUrl);

		if (!$response['success'] || empty($response['data'])) {
			return null;
		}

		$data = $response['data'];

		// Validate required oEmbed fields
		if (empty($data['type']) || empty($data['version'])) {
			return null;
		}

		// Normalize to our common format
		return self::normalizeOEmbedData($data);
	}

	/**
	 * Normalize oEmbed response to common format
	 */
	private static function normalizeOEmbedData(array $data): array {
		$type = $data['type'] ?? 'link';

		$normalized = [
			'type' => 'oembed',
			'oembed_type' => $type,
			'title' => $data['title'] ?? null,
			'description' => null, // oEmbed doesn't include description
			'url' => $data['url'] ?? null,
			'provider_name' => $data['provider_name'] ?? null,
			'provider_url' => $data['provider_url'] ?? null,
			'thumbnail_url' => $data['thumbnail_url'] ?? null,
			'author_name' => $data['author_name'] ?? null,
			'author_url' => $data['author_url'] ?? null,
			'cache_age' => $data['cache_age'] ?? 86400, // Default 24 hours
		];

		// Type-specific fields
		switch ($type) {
			case 'photo':
				$normalized['image_url'] = $data['url'] ?? null;
				$normalized['image_width'] = $data['width'] ?? null;
				$normalized['image_height'] = $data['height'] ?? null;
				break;

			case 'video':
			case 'rich':
				$normalized['html'] = $data['html'] ?? null;
				$normalized['width'] = $data['width'] ?? null;
				$normalized['height'] = $data['height'] ?? null;
				// Also set image_url from thumbnail for preview
				$normalized['image_url'] = $data['thumbnail_url'] ?? null;
				break;

			case 'link':
				// Just title and URL
				break;
		}

		return $normalized;
	}

	/**
	 * Check if URL is from a supported provider
	 */
	public static function isSupported(string $url): bool {
		return self::getKnownProviderEndpoint($url) !== null;
	}

	/**
	 * Get list of supported domains
	 */
	public static function getSupportedDomains(): array {
		return array_keys(self::$providers);
	}
}
