<?php
/**
 * Embed Renderer
 * Renders video embeds (iframe) and link preview cards
 */

class VT_Embed_Renderer {

	/**
	 * Render embed
	 */
	public static function render(array $embed): string {
		if (empty($embed)) {
			return '';
		}

		$type = $embed['type'] ?? 'opengraph';

		// If it's oEmbed video/rich with HTML, render iframe
		if ($type === 'oembed' && !empty($embed['html'])) {
			$oembedType = $embed['oembed_type'] ?? '';
			if (in_array($oembedType, ['video', 'rich'])) {
				return self::renderVideoEmbed($embed);
			}
		}

		// Otherwise render as card
		return self::renderCard($embed);
	}

	/**
	 * Render video embed with iframe
	 */
	private static function renderVideoEmbed(array $embed): string {
		$html = $embed['html'] ?? '';
		if (empty($html)) {
			return self::renderCard($embed);
		}

		// Sanitize iframe
		$html = self::sanitizeIframe($html);

		$title = vt_service('validation.validator')->escHtml($embed['title'] ?? '');
		$url = vt_service('validation.validator')->escUrl($embed['source_url'] ?? $embed['url'] ?? '');

		ob_start();
		?>
		<div class="vt-embed vt-embed-rich" data-vt-source="<?php echo vt_service('validation.validator')->escAttr($embed['oembed_type'] ?? 'video'); ?>">
			<div class="vt-embed-iframe">
				<?php echo $html; ?>
			</div>
			<?php if ($title && $url): ?>
				<div class="vt-embed-meta">
					<a href="<?php echo $url; ?>" target="_blank" rel="nofollow noopener" class="vt-embed__title">
						<?php echo $title; ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return trim(ob_get_clean());
	}

	/**
	 * Render simple card
	 */
	private static function renderCard(array $embed): string {
		$title = vt_service('validation.validator')->escHtml($embed['title'] ?? '');
		$description = vt_service('validation.validator')->escHtml($embed['description'] ?? '');
		$image = vt_service('validation.validator')->escUrl($embed['image'] ?? $embed['thumbnail_url'] ?? '');
		$url = vt_service('validation.validator')->escUrl($embed['url'] ?? $embed['source_url'] ?? '');

		// Require image and URL
		if (!$image || !$url) {
			return '';
		}

		// Truncate description
		if ($description && strlen($description) > 200) {
			$description = VT_Text::truncate($description, 200);
		}

		ob_start();
		?>
		<div class="vt-embed vt-embed-card" data-vt-source="<?php echo vt_service('validation.validator')->escAttr($embed['type'] ?? 'opengraph'); ?>">
			<div class="vt-embed__imagewrap">
				<img class="vt-embed__image" src="<?php echo $image; ?>" alt="" loading="lazy" decoding="async" />
			</div>
			<div class="vt-embed__body">
				<?php if ($title): ?>
					<a class="vt-embed__title" href="<?php echo $url; ?>" target="_blank" rel="nofollow noopener"><?php echo $title; ?></a>
				<?php endif; ?>
				<?php if ($description): ?>
					<div class="vt-embed__desc"><?php echo $description; ?></div>
				<?php endif; ?>
				<div class="vt-embed__meta">
					<a href="<?php echo $url; ?>" target="_blank" rel="nofollow noopener" class="vt-embed__link">
						<?php echo vt_service('validation.validator')->escHtml(parse_url($url, PHP_URL_HOST)); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
		return trim(ob_get_clean());
	}

	/**
	 * Sanitize iframe HTML
	 */
	private static function sanitizeIframe(string $html): string {
		// Extract iframe src and dimensions
		if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
			$src = $matches[1];

			// Extract dimensions if present
			$width = '100%';
			$height = '450';
			if (preg_match('/width=["\']?(\d+)["\']?/i', $html, $w)) {
				$width = $w[1];
			}
			if (preg_match('/height=["\']?(\d+)["\']?/i', $html, $h)) {
				$height = $h[1];
			}

			return sprintf(
				'<iframe src="%s" width="%s" height="%s" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" loading="lazy"></iframe>',
				vt_service('validation.validator')->escUrl($src),
				vt_service('validation.validator')->escAttr($width),
				vt_service('validation.validator')->escAttr($height)
			);
		}

		return '';
	}

	/**
	 * Check if embed should be rendered
	 */
	public static function shouldRender(array $embed): bool {
		if (empty($embed)) {
			return false;
		}

		// Video/rich with HTML can always render
		if ($embed['type'] === 'oembed' && !empty($embed['html'])) {
			return true;
		}

		// Cards need image and URL
		$image = $embed['image'] ?? $embed['thumbnail_url'] ?? '';
		$url = $embed['url'] ?? $embed['source_url'] ?? '';
		return !empty($image) && !empty($url);
	}
}
