<?php
/**
 * Embed Renderer
 * Renders embed data as secure HTML (cards, iframes, etc.)
 */

class VT_Embed_Renderer {

	/**
	 * Render embed data as HTML
	 */
	public static function render(array $embed): string {
		if (empty($embed)) {
			return '';
		}

		$type = $embed['type'] ?? 'link';

		// Route to appropriate renderer
		switch ($type) {
			case 'oembed':
				return self::renderOEmbed($embed);

			case 'opengraph':
				return self::renderCard($embed);

			default:
				return '';
		}
	}

	/**
	 * Render oEmbed content
	 */
	public static function renderOEmbed(array $embed): string {
		$oembedType = $embed['oembed_type'] ?? 'link';

		switch ($oembedType) {
			case 'video':
			case 'rich':
				return self::renderRichEmbed($embed);

			case 'photo':
				return self::renderPhotoEmbed($embed);

			case 'link':
			default:
				return self::renderCard($embed);
		}
	}

	/**
	 * Render rich/video embed (iframe)
	 */
	private static function renderRichEmbed(array $embed): string {
		$html = $embed['html'] ?? '';

		if (empty($html)) {
			return self::renderCard($embed);
		}

		// Sanitize iframe HTML
		$html = self::sanitizeIframeHtml($html);

		// Wrap in responsive container
		ob_start();
		?>
		<div class="vt-embed vt-embed-rich" data-vt-source="<?php echo vt_service('validation.validator')->escAttr($embed['source_url'] ?? ''); ?>">
			<div class="vt-embed-iframe">
				<?php echo $html; ?>
			</div>
			<?php if (!empty($embed['title']) || !empty($embed['provider_name'])): ?>
				<div class="vt-embed-meta">
					<?php if (!empty($embed['title'])): ?>
						<div class="vt-embed-title">
							<?php echo vt_service('validation.validator')->escHtml($embed['title']); ?>
						</div>
					<?php endif; ?>
					<?php if (!empty($embed['provider_name'])): ?>
						<div class="vt-embed-provider">
							<?php if (!empty($embed['provider_url'])): ?>
								<a href="<?php echo vt_service('validation.validator')->escUrl($embed['provider_url']); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo vt_service('validation.validator')->escHtml($embed['provider_name']); ?>
								</a>
							<?php else: ?>
								<?php echo vt_service('validation.validator')->escHtml($embed['provider_name']); ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render photo embed
	 */
	private static function renderPhotoEmbed(array $embed): string {
		$imageUrl = $embed['image_url'] ?? '';

		if (empty($imageUrl)) {
			return self::renderCard($embed);
		}

		ob_start();
		?>
		<div class="vt-embed vt-embed-photo" data-vt-source="<?php echo vt_service('validation.validator')->escAttr($embed['source_url'] ?? ''); ?>">
			<a href="<?php echo vt_service('validation.validator')->escUrl($embed['url'] ?? $embed['source_url']); ?>" target="_blank" rel="noopener noreferrer">
				<img src="<?php echo vt_service('validation.validator')->escUrl($imageUrl); ?>"
					 alt="<?php echo vt_service('validation.validator')->escAttr($embed['title'] ?? ''); ?>"
					 class="vt-embed-image"
					 loading="lazy"
					 decoding="async">
			</a>
			<?php if (!empty($embed['title']) || !empty($embed['provider_name'])): ?>
				<div class="vt-embed-meta">
					<?php if (!empty($embed['title'])): ?>
						<div class="vt-embed-title">
							<?php echo vt_service('validation.validator')->escHtml($embed['title']); ?>
						</div>
					<?php endif; ?>
					<?php if (!empty($embed['provider_name'])): ?>
						<div class="vt-embed-provider">
							<?php echo vt_service('validation.validator')->escHtml($embed['provider_name']); ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render link preview card (Open Graph style)
	 */
	public static function renderCard(array $embed): string {
		$title = $embed['title'] ?? '';
		$description = $embed['description'] ?? '';
		$imageUrl = $embed['image_url'] ?? $embed['thumbnail_url'] ?? '';
		$url = $embed['url'] ?? $embed['source_url'] ?? '';
		$siteName = $embed['site_name'] ?? $embed['provider_name'] ?? '';

		// Need at least a title or URL
		if (empty($title) && empty($url)) {
			return '';
		}

		ob_start();
		?>
		<div class="vt-embed vt-embed-card" data-vt-source="<?php echo vt_service('validation.validator')->escAttr($embed['source_url'] ?? ''); ?>">
			<a href="<?php echo vt_service('validation.validator')->escUrl($url); ?>" target="_blank" rel="noopener noreferrer" class="vt-embed-link">
				<?php if (!empty($imageUrl)): ?>
					<div class="vt-embed-image-wrapper">
						<img src="<?php echo vt_service('validation.validator')->escUrl($imageUrl); ?>"
							 alt="<?php echo vt_service('validation.validator')->escAttr($title); ?>"
							 class="vt-embed-image"
							 loading="lazy"
							 decoding="async">
					</div>
				<?php endif; ?>
				<div class="vt-embed-content">
					<?php if (!empty($title)): ?>
						<div class="vt-embed-title">
							<?php echo vt_service('validation.validator')->escHtml($title); ?>
						</div>
					<?php endif; ?>
					<?php if (!empty($description)): ?>
						<div class="vt-embed-description">
							<?php echo vt_service('validation.validator')->escHtml(VT_Text::truncate($description, 200)); ?>
						</div>
					<?php endif; ?>
					<?php if (!empty($siteName)): ?>
						<div class="vt-embed-provider">
							<?php echo vt_service('validation.validator')->escHtml($siteName); ?>
						</div>
					<?php endif; ?>
				</div>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Sanitize iframe HTML from oEmbed providers
	 */
	private static function sanitizeIframeHtml(string $html): string {
		// Parse and rebuild iframe with security attributes
		if (preg_match('/<iframe([^>]+)><\/iframe>/i', $html, $matches)) {
			$attributes = $matches[1];

			// Extract src
			if (preg_match('/src=["\']([^"\']+)["\']/i', $attributes, $srcMatch)) {
				$src = $srcMatch[1];

				// Extract width/height if present
				$width = '100%';
				$height = '100%';

				if (preg_match('/width=["\']?(\d+)["\'%]?/i', $attributes, $widthMatch)) {
					$width = $widthMatch[1];
				}

				if (preg_match('/height=["\']?(\d+)["\'%]?/i', $attributes, $heightMatch)) {
					$height = $heightMatch[1];
				}

				// Rebuild iframe with security attributes
				return sprintf(
					'<iframe src="%s" width="%s" height="%s" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" sandbox="allow-scripts allow-same-origin allow-presentation" loading="lazy"></iframe>',
					vt_service('validation.validator')->escUrl($src),
					vt_service('validation.validator')->escAttr($width),
					vt_service('validation.validator')->escAttr($height)
				);
			}
		}

		// If parsing fails, return empty (security precaution)
		return '';
	}

	/**
	 * Check if embed should be rendered
	 */
	public static function shouldRender(array $embed): bool {
		if (empty($embed)) {
			return false;
		}

		// Must have at least a title or URL
		return !empty($embed['title']) || !empty($embed['url']) || !empty($embed['source_url']);
	}
}
