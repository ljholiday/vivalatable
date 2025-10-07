<?php
/**
 * Open Graph Provider
 * Parses Open Graph meta tags from HTML as fallback for non-oEmbed sites
 */

class VT_Embed_OpenGraphProvider {

	/**
	 * Fetch and parse Open Graph data from URL
	 */
	public static function fetch(string $url): ?array {
		$response = VT_Http_Client::get($url);

		if (!$response['success'] || empty($response['body'])) {
			return null;
		}

		return self::parse($response['body'], $url);
	}

	/**
	 * Parse Open Graph meta tags from HTML
	 */
	public static function parse(string $html, string $sourceUrl = null): ?array {
		// Suppress errors from malformed HTML
		libxml_use_internal_errors(true);

		$doc = new DOMDocument();
		$loaded = $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

		libxml_clear_errors();

		if (!$loaded) {
			return null;
		}

		$xpath = new DOMXPath($doc);
		$ogTags = [];

		// Query for Open Graph meta tags (property="og:*")
		$metaTags = $xpath->query('//meta[@property]');

		foreach ($metaTags as $tag) {
			$property = $tag->getAttribute('property');
			$content = $tag->getAttribute('content');

			if (strpos($property, 'og:') === 0 || strpos($property, 'article:') === 0 || strpos($property, 'video:') === 0) {
				$ogTags[$property] = $content;
			}
		}

		// Fallback: also check name attribute for Twitter cards
		$twitterTags = $xpath->query('//meta[@name]');

		foreach ($twitterTags as $tag) {
			$name = $tag->getAttribute('name');
			$content = $tag->getAttribute('content');

			if (strpos($name, 'twitter:') === 0) {
				// Map Twitter card to OG equivalents
				$ogTags[$name] = $content;
			}
		}

		// If no OG tags found, return null
		if (empty($ogTags)) {
			return null;
		}

		return self::normalizeOpenGraphData($ogTags, $sourceUrl);
	}

	/**
	 * Normalize Open Graph data to common format
	 */
	private static function normalizeOpenGraphData(array $tags, ?string $sourceUrl): array {
		// Get title (OG or Twitter fallback)
		$title = $tags['og:title']
			?? $tags['twitter:title']
			?? self::extractTitleFromTags($tags);

		// Get description
		$description = $tags['og:description']
			?? $tags['twitter:description']
			?? null;

		// Get image
		$image = $tags['og:image']
			?? $tags['og:image:url']
			?? $tags['twitter:image']
			?? null;

		// Get type
		$ogType = $tags['og:type'] ?? 'website';

		// Get URL
		$url = $tags['og:url'] ?? $sourceUrl;

		// Get site name
		$siteName = $tags['og:site_name'] ?? null;

		// Build normalized array
		$normalized = [
			'type' => 'opengraph',
			'og_type' => $ogType,
			'title' => $title,
			'description' => $description,
			'url' => $url,
			'image_url' => $image,
			'image_width' => $tags['og:image:width'] ?? null,
			'image_height' => $tags['og:image:height'] ?? null,
			'site_name' => $siteName,
			'provider_name' => $siteName,
			'provider_url' => $url ? self::getBaseUrl($url) : null,
		];

		// Type-specific metadata
		if ($ogType === 'article') {
			$normalized['article'] = [
				'author' => $tags['article:author'] ?? null,
				'published_time' => $tags['article:published_time'] ?? null,
				'modified_time' => $tags['article:modified_time'] ?? null,
				'section' => $tags['article:section'] ?? null,
			];
			$normalized['author_name'] = $tags['article:author'] ?? null;
		}

		if ($ogType === 'video' || $ogType === 'video.movie') {
			$normalized['video'] = [
				'url' => $tags['og:video'] ?? $tags['og:video:url'] ?? null,
				'secure_url' => $tags['og:video:secure_url'] ?? null,
				'type' => $tags['og:video:type'] ?? null,
				'width' => $tags['og:video:width'] ?? null,
				'height' => $tags['og:video:height'] ?? null,
			];
		}

		return $normalized;
	}

	/**
	 * Extract title from various OG tag variations
	 */
	private static function extractTitleFromTags(array $tags): ?string {
		// Try various common title tags
		$titleKeys = [
			'og:title',
			'twitter:title',
			'og:site_name',
		];

		foreach ($titleKeys as $key) {
			if (!empty($tags[$key])) {
				return $tags[$key];
			}
		}

		return null;
	}

	/**
	 * Extract base URL from full URL
	 */
	private static function getBaseUrl(string $url): ?string {
		$parsed = parse_url($url);

		if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
			return null;
		}

		return $parsed['scheme'] . '://' . $parsed['host'];
	}

	/**
	 * Check if HTML contains Open Graph tags
	 */
	public static function hasOpenGraphTags(string $html): bool {
		return strpos($html, 'og:') !== false || strpos($html, 'twitter:') !== false;
	}
}
