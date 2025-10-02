# VivalaTable Image System

Comprehensive image handling with responsive thumbnails, WebP conversion, and generated avatars.

## Features

- **Multi-Size Thumbnails**: Automatic generation of 7 preset sizes
- **WebP Conversion**: Modern format with fallback for better compression
- **Responsive Images**: `<picture>` and `srcset` for different screen sizes
- **Generated Avatars**: Colorful initial-based avatars when users don't upload
- **Lazy Loading**: Deferred image loading for performance
- **EXIF Stripping**: Privacy protection and file size reduction
- **Progressive JPEG**: Better perceived load times
- **LQIP Support**: Blur placeholders while images load

## Architecture

### Core Classes

#### `VT_Image_Processor` (`includes/Image/ImageProcessor.php`)
Low-level image manipulation using GD library.

**Operations**:
- Load/save images (JPEG, PNG, GIF, WebP)
- Resize maintaining aspect ratio
- Crop to exact dimensions
- Format conversion
- EXIF stripping
- Progressive JPEG generation

**Usage**:
```php
// Load image
$image = VT_Image_Processor::load('/path/to/image.jpg');

// Resize to fit within 800x600
$resized = VT_Image_Processor::resize($image['resource'], 800, 600);

// Save as WebP
VT_Image_Processor::save($resized['resource'], '/path/to/output.webp', 'webp');

// Create thumbnail
VT_Image_Processor::createThumbnail(
    '/path/to/source.jpg',
    '/path/to/thumb.jpg',
    300, 300,
    'fill' // or 'fit' or 'stretch'
);
```

#### `VT_Image_Service` (`includes/Image/ImageService.php`)
Main orchestrator for uploads and thumbnail generation.

**Thumbnail Sizes**:
```php
'avatar_sm' => 32x32 (fill)    // Small avatars in lists
'avatar_md' => 64x64 (fill)    // Medium avatars
'avatar_lg' => 120x120 (fill)  // Large profile avatars
'thumbnail' => 300x300 (fit)    // Card thumbnails
'medium' => 600x600 (fit)       // Medium images
'large' => 1200x1200 (fit)      // Large images
'cover' => 1200x400 (fill)      // Cover photos
```

**Upload Flow**:
```php
$result = VT_Image_Service::upload($_FILES['image'], [
    'context' => 'user',
    'entity_id' => $user_id,
    'sizes' => ['avatar_sm', 'avatar_md', 'avatar_lg'],
    'webp' => true,
]);

if ($result['success']) {
    echo "Uploaded: " . $result['url'];
    // Access thumbnails
    foreach ($result['thumbnails'] as $size => $data) {
        echo "$size: " . $data['url'];
    }
}
```

**Validation**:
- File size: 5MB max
- Dimensions: 50x50 min, 5000x5000 max
- MIME types: JPEG, PNG, GIF, WebP only
- File signature verification

#### `VT_Image_Renderer` (`includes/Image/ImageRenderer.php`)
Generates responsive HTML markup.

**Responsive Image**:
```php
echo VT_Image_Renderer::render('/uploads/image.jpg', [
    'alt' => 'Product photo',
    'class' => 'product-image',
    'sizes' => '(max-width: 768px) 100vw, 50vw',
    'lazy' => true,
    'webp' => true,
    'srcset' => [
        '/uploads/image-large.jpg' => 1200,
        '/uploads/image-medium.jpg' => 600,
        '/uploads/image-thumbnail.jpg' => 300,
    ],
]);
```

**Output**:
```html
<picture>
    <source srcset="/uploads/image.webp" type="image/webp">
    <source srcset="/uploads/image-large.jpg 1200w, /uploads/image-medium.jpg 600w, /uploads/image-thumbnail.jpg 300w"
            sizes="(max-width: 768px) 100vw, 50vw">
    <img src="/uploads/image.jpg" alt="Product photo" class="product-image" loading="lazy" decoding="async">
</picture>
```

**Avatar Rendering**:
```php
echo VT_Image_Renderer::renderAvatar($user_id, [
    'size' => 64,
    'class' => 'user-avatar',
    'use_generated' => true, // Fallback to generated avatar
]);
```

**Blur Placeholder (LQIP)**:
```php
echo VT_Image_Renderer::renderWithPlaceholder('/uploads/image.jpg', [
    'alt' => 'Large image',
    'class' => 'fade-in-image',
]);
```

#### `VT_Avatar_Generator` (`includes/Image/AvatarGenerator.php`)
Creates custom avatars from user initials.

**Features**:
- 12-color palette for variety
- Consistent colors (same name = same color)
- Automatic text contrast detection
- Both PNG and SVG generation
- Caching (30 days)

**Usage**:
```php
// Generate avatar for display name
$avatar = VT_Avatar_Generator::generate('John Doe', 120);
// Returns: data:image/png;base64,...

// With caching
$avatar = VT_Avatar_Generator::generateAndCache($user_id, 'John Doe', 120);

// SVG version (better scaling)
$avatarSvg = VT_Avatar_Generator::generateSvg('Jane Smith', 120);
```

**How It Works**:
1. Extracts initials (first & last, or first 2 letters)
2. Generates consistent color from name hash
3. Determines text color (white or dark) based on background luminance
4. Creates image with centered text
5. Returns as base64 data URL

**Color Palette**:
```php
'#667eea' // Primary purple
'#764ba2' // Deep purple
'#f093fb' // Pink
'#4facfe' // Blue
'#00f2fe' // Cyan
'#43e97b' // Green
'#38f9d7' // Teal
'#fa709a' // Coral
'#fee140' // Yellow (dark text)
'#30cfd0' // Turquoise
'#a8edea' // Light cyan (dark text)
'#fbc2eb' // Light pink (dark text)
```

## Integration

### Profile Uploads

Update existing `VT_Image_Manager` calls to use new service:

```php
// Old way
$result = VT_Image_Manager::handleImageUpload($_FILES['profile_image'], 'profile', $user_id, 'user');

// New way (with thumbnails)
$result = VT_Image_Service::upload($_FILES['profile_image'], [
    'context' => 'user',
    'entity_id' => $user_id,
    'sizes' => ['avatar_sm', 'avatar_md', 'avatar_lg'],
    'webp' => true,
]);
```

### Member Display

The `VT_Member_Display` class now automatically uses generated avatars as fallback:

```php
// Avatar priority:
// 1. Custom uploaded avatar
// 2. Gravatar (if exists)
// 3. Generated avatar from initials
// 4. Default Gravatar identicon
```

### Conversation/Event Images

For user-uploaded content images:

```php
$result = VT_Image_Service::upload($_FILES['image'], [
    'context' => 'post',
    'entity_id' => $post_id,
    'user_id' => $current_user_id,
    'sizes' => ['thumbnail', 'medium', 'large'],
]);
```

## File Structure

```
uploads/
└── vivalatable/
    ├── users/
    │   ├── 123/
    │   │   ├── user_123_1234567890_abc123.jpg       # Original
    │   │   ├── user_123_1234567890_abc123.webp      # WebP version
    │   │   ├── user_123_1234567890_abc123-avatar_sm.jpg
    │   │   ├── user_123_1234567890_abc123-avatar_md.jpg
    │   │   └── user_123_1234567890_abc123-avatar_lg.jpg
    ├── posts/
    ├── communities/
    └── events/
```

## Security

### Upload Validation
- **MIME type verification**: Uses `finfo_file()` to check actual file type
- **Extension whitelist**: .jpg, .jpeg, .png, .gif, .webp only
- **Size limits**: 5MB max, 50x50 min, 5000x5000 max dimensions
- **File signature check**: Prevents header manipulation attacks

### Privacy Protection
- **EXIF stripping**: Removes GPS, camera, and other metadata
- **Path traversal prevention**: Sanitized filenames, restricted directories
- **Resource limits**: Memory and execution time limits for processing

### XSS Prevention
- All URLs escaped via `vt_service('validation.validator')->escUrl()`
- No SVG uploads (potential XSS vector)
- Data URLs only for generated content

## Performance

### Optimization Techniques
1. **Progressive JPEG**: Better perceived loading
2. **WebP conversion**: ~30% smaller than JPEG at same quality
3. **Lazy loading**: Deferred loading of off-screen images
4. **Responsive images**: Serve appropriate sizes to devices
5. **Thumbnail pre-generation**: No on-demand resizing
6. **Avatar caching**: 30-day cache for generated avatars

### Benchmarks
- Thumbnail generation: ~100ms for 7 sizes
- WebP conversion: ~50ms per image
- Avatar generation: ~20ms (PNG), ~5ms (SVG)
- Upload + process: ~200-300ms total

## Browser Support

### Responsive Images
- `<picture>`: All modern browsers
- `srcset/sizes`: IE11+ (with polyfill), all modern browsers
- `loading="lazy"`: Chrome 77+, Firefox 75+, Safari 15.4+

### WebP Support
- Chrome: ✅ All versions
- Firefox: ✅ 65+
- Safari: ✅ 14+ (Big Sur)
- Edge: ✅ 18+

Fallback to JPEG/PNG provided automatically.

## API Reference

### VT_Image_Service

```php
// Upload image
VT_Image_Service::upload(array $file, array $options): ?array

// Validate upload
VT_Image_Service::validate(array $file): array

// Delete image + thumbnails
VT_Image_Service::delete(string $path): bool

// Get image URL from path
VT_Image_Service::getImageUrl(string $path): string

// Add custom thumbnail size
VT_Image_Service::addThumbnailSize(string $name, int $width, int $height, string $mode): void
```

### VT_Image_Processor

```php
// Load image
VT_Image_Processor::load(string $filePath): ?array

// Save image
VT_Image_Processor::save($resource, string $filePath, string $format): bool

// Resize image
VT_Image_Processor::resize($source, int $maxWidth, int $maxHeight, bool $upscale): array

// Crop image
VT_Image_Processor::crop($source, int $width, int $height): array

// Convert format
VT_Image_Processor::convert(string $sourcePath, string $destPath, string $format): bool

// Generate WebP
VT_Image_Processor::generateWebP(string $sourcePath, string $destPath): ?string

// Strip EXIF
VT_Image_Processor::stripExif(string $filePath): bool

// Create thumbnail
VT_Image_Processor::createThumbnail(string $source, string $dest, int $w, int $h, string $mode): ?array
```

### VT_Image_Renderer

```php
// Render responsive image
VT_Image_Renderer::render(string $src, array $options): string

// Render from upload data
VT_Image_Renderer::renderFromUpload(array $uploadData, array $options): string

// Render avatar
VT_Image_Renderer::renderAvatar(int $userId, array $options): string

// Render with placeholder
VT_Image_Renderer::renderWithPlaceholder(string $src, array $options): string
```

### VT_Avatar_Generator

```php
// Generate avatar
VT_Avatar_Generator::generate(string $displayName, int $size): ?string

// Generate and cache
VT_Avatar_Generator::generateAndCache(int $userId, string $displayName, int $size): ?string

// Generate SVG
VT_Avatar_Generator::generateSvg(string $displayName, int $size): ?string

// Clear cache
VT_Avatar_Generator::clearCache(int $userId): void
```

## Troubleshooting

### Images not uploading
1. Check PHP upload limits: `upload_max_filesize`, `post_max_size`
2. Verify directory permissions: `uploads/vivalatable/` should be 755
3. Check error logs for GD library issues
4. Ensure `file_uploads = On` in php.ini

### WebP not working
1. Check PHP version: 7.0+ required for WebP support
2. Verify GD library compiled with WebP: `gd_info()`
3. Fallback to JPEG/PNG is automatic

### Generated avatars showing broken
1. Check GD library installed: `extension=gd` in php.ini
2. Verify font paths in `AvatarGenerator::getSystemFont()`
3. Check base64 data URL support in browser

### Thumbnails not generating
1. Check memory limits: `memory_limit` in php.ini
2. Large images may timeout: increase `max_execution_time`
3. Verify GD functions available: `imagecreatetruecolor`, `imagecopyresampled`

## Future Enhancements

### Planned Features
1. **Cloud Storage**: S3/Cloudflare R2 integration
2. **Image Optimization**: TinyPNG API integration
3. **Face Detection**: Smart cropping for avatars
4. **AVIF Support**: Next-gen image format
5. **Batch Processing**: Queue system for large uploads
6. **Image Editor**: Client-side crop/rotate/filter UI
7. **Animated GIF→Video**: Convert to MP4/WebM for better compression

### Database Schema (Future)

```sql
CREATE TABLE vt_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    filename VARCHAR(255),
    original_filename VARCHAR(255),
    mime_type VARCHAR(50),
    file_size INT,
    width INT,
    height INT,
    context VARCHAR(50),
    entity_id INT,
    sizes JSON,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_context (context, entity_id)
);
```

## Resources

- [GD Library Manual](https://www.php.net/manual/en/book.image.php)
- [Responsive Images Guide](https://developer.mozilla.org/en-US/docs/Learn/HTML/Multimedia_and_embedding/Responsive_images)
- [WebP Format](https://developers.google.com/speed/webp)
- [Picture Element Spec](https://html.spec.whatwg.org/multipage/embedded-content.html#the-picture-element)
