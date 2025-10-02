# VivalaTable Embed System

Comprehensive URL embed support for conversation replies using oEmbed and Open Graph protocols.

## Features

- **oEmbed Support**: Rich embeds for YouTube, Vimeo, Twitter, Spotify, SoundCloud, and more
- **Open Graph Fallback**: Link preview cards for any site with OG meta tags
- **Security First**: SSRF prevention, iframe sandboxing, XSS protection
- **Performance**: 24-hour caching via transients, lazy loading
- **Responsive**: Mobile-friendly cards and video embeds

## Architecture

### Core Classes

#### `VT_Http_Client` (`includes/Http/Client.php`)
Secure HTTP client for fetching external URLs.

**Features**:
- SSRF attack prevention (blocks private IPs)
- Response size limits (5MB max)
- Timeout protection (10 seconds)
- cURL with file_get_contents fallback
- SSL verification

**Usage**:
```php
$response = VT_Http_Client::get('https://example.com');
$jsonData = VT_Http_Client::getJson('https://api.example.com/data');
```

#### `VT_Embed_OEmbedProvider` (`includes/Embed/OEmbedProvider.php`)
Handles oEmbed discovery and fetching.

**Supported Providers**:
- **Video**: YouTube, Vimeo, Dailymotion
- **Audio**: SoundCloud, Spotify
- **Social**: Twitter/X
- **Code**: CodePen
- **Design**: Figma

**How it works**:
1. Check known provider endpoints (fast path)
2. If unknown, fetch HTML and parse oEmbed link tags
3. Call oEmbed endpoint and normalize response

#### `VT_Embed_OpenGraphProvider` (`includes/Embed/OpenGraphProvider.php`)
Parses Open Graph meta tags as fallback.

**Extracted Data**:
- `og:title`, `og:description`
- `og:image`, `og:url`
- `og:type`, `og:site_name`
- Twitter Card tags (fallback)
- Article/video metadata

**How it works**:
1. Fetch URL HTML
2. Parse with DOMDocument/DOMXPath
3. Extract all `og:*` and `twitter:*` meta tags
4. Normalize to common format

#### `VT_Embed_Service` (`includes/Embed/EmbedService.php`)
Main orchestration service with caching.

**Flow**:
1. Check cache (24hr TTL)
2. Try oEmbed (richer data)
3. Fallback to Open Graph
4. Cache result (or failure)

**Usage**:
```php
$embed = VT_Embed_Service::buildEmbedFromUrl($url);
VT_Embed_Service::clearCache($url); // Force refresh
```

#### `VT_Embed_Renderer` (`includes/Embed/Renderer.php`)
Renders secure HTML output.

**Embed Types**:
- **Video/Rich**: Responsive iframe with sandbox attributes
- **Photo**: Image with link
- **Link**: Card with image, title, description

**Security**:
- Iframe sandboxing: `allow-scripts allow-same-origin allow-presentation`
- XSS prevention via escaping
- Only whitelisted iframe attributes

## Usage in Conversation Manager

```php
// Automatically processes embeds in conversation content
public function processContentEmbeds($content) {
    $content = VT_Text::autop($content); // Paragraph formatting

    $url = VT_Text::firstUrlInText($content);
    if ($url) {
        $embed = VT_Embed_Service::buildEmbedFromUrl($url);
        if ($embed && VT_Embed_Renderer::shouldRender($embed)) {
            $content .= VT_Embed_Renderer::render($embed);
        }
    }

    return $content;
}
```

Called in templates:
```php
<?php echo $conversation_manager->processContentEmbeds($reply->content); ?>
```

## CSS Classes

### Base Embed Container
```css
.vt-embed              /* Outer container */
.vt-embed-card         /* Link preview card */
.vt-embed-rich         /* Video/iframe embed */
.vt-embed-photo        /* Photo embed */
```

### Card Elements
```css
.vt-embed-link         /* Clickable wrapper */
.vt-embed-image-wrapper
.vt-embed-image
.vt-embed-content
.vt-embed-title
.vt-embed-description
.vt-embed-provider
```

### Rich Media
```css
.vt-embed-iframe       /* 16:9 responsive container */
.vt-embed-meta         /* Provider info below video */
```

## Testing

### CLI Test
```bash
php test-embed.php
```

Tests multiple URLs and shows:
- Embed type (oEmbed vs OG)
- Title, provider
- HTML rendering success

### Visual Test
```
http://localhost/test-embed-visual.php
```

Shows actual rendered embeds in browser with styling.

### Test URLs
- YouTube: `https://www.youtube.com/watch?v=dQw4w9WgXcQ`
- Vimeo: `https://vimeo.com/76979871`
- GitHub: `https://github.com/anthropics/claude-code`
- Dev.to: `https://dev.to/ben/...`

## Security Considerations

### SSRF Prevention
- URL validation (http/https only)
- Blocks private IP ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
- Blocks localhost (127.0.0.1, ::1)
- DNS resolution check before fetch

### XSS Protection
- All output escaped via `vt_service('validation.validator')->escHtml/escUrl/escAttr()`
- Iframe sandbox attributes
- No inline JavaScript execution
- Sanitized iframe reconstruction

### Performance
- Response size limit (5MB)
- Request timeout (10 seconds)
- Max redirects (3)
- 24-hour cache (configurable via provider `cache_age`)

### Rate Limiting
- Cache prevents repeated external requests
- Failed embeds cached to avoid retry storms
- Lazy loading images

## Limitations

### Single URL Only
Currently processes only the first URL found in content. Multiple URLs would require UI changes to show multiple embeds.

### Provider Restrictions
- Twitter/X: May require authentication for some tweets
- Instagram: Requires Facebook API token
- Some providers may block based on user-agent or rate limits

### No Embed Editing
Once cached, embeds don't update until cache expires (24 hours). To force refresh:
```php
VT_Embed_Service::clearCache($url);
```

## Future Enhancements

### Potential Improvements
1. **Multiple embeds per message** - Process all URLs, limit to N embeds
2. **Manual embed control** - Let users preview/approve embeds before posting
3. **Provider API integration** - Twitter, Instagram with API keys
4. **Video thumbnails** - Generate/cache video preview images
5. **Embed analytics** - Track which embeds get clicked
6. **Custom providers** - Admin UI to add oEmbed endpoints

### Database Schema Extension
Future: dedicated embeds table for analytics
```sql
CREATE TABLE vt_embeds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    url VARCHAR(2000),
    type VARCHAR(50),
    data JSON,
    fetched_at TIMESTAMP,
    click_count INT DEFAULT 0
);
```

## Troubleshooting

### Embeds Not Showing
1. Check bootstrap.php includes Embed classes
2. Verify template uses `processContentEmbeds()`
3. Check browser console for errors
4. Test URL in CLI: `php test-embed.php`

### SSL Errors
If getting SSL verification errors:
- Check PHP cURL SSL bundle is up to date
- Temporarily disable: `curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false)` (dev only!)

### Rate Limiting
Providers may rate limit. Check:
- Cache is working (look for transient entries)
- Not making duplicate requests
- Consider adding longer cache for popular URLs

## Resources

- [oEmbed Specification](https://oembed.com/)
- [Open Graph Protocol](https://ogp.me/)
- [oEmbed Providers Registry](https://oembed.com/providers.json)
