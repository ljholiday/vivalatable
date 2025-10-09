<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Modern Image Service
 *
 * Handles image upload, validation, resizing with alt-text enforcement.
 */
final class ImageService
{
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    private const DIMENSIONS = [
        'profile' => ['width' => 400, 'height' => 400],
        'cover' => ['width' => 1200, 'height' => 400],
        'post' => ['width' => 800, 'height' => 600],
        'featured' => ['width' => 1200, 'height' => 630],
    ];

    private string $uploadBasePath;
    private string $uploadBaseUrl;

    public function __construct(string $uploadBasePath, string $uploadBaseUrl = '/uploads')
    {
        $this->uploadBasePath = rtrim($uploadBasePath, '/');
        $this->uploadBaseUrl = rtrim($uploadBaseUrl, '/');
    }

    /**
     * Upload image with required alt-text
     *
     * @param array $file Uploaded file array from $_FILES
     * @param string $altText Required alt-text for accessibility
     * @param string $imageType Type: profile, cover, post, featured
     * @param string $entityType Entity: user, event, conversation, community
     * @param int $entityId Entity ID
     * @return array{success: bool, url?: string, path?: string, error?: string}
     */
    public function upload(array $file, string $altText, string $imageType, string $entityType, int $entityId): array
    {
        // Enforce alt-text requirement
        if (trim($altText) === '') {
            return ['success' => false, 'error' => 'Alt-text is required for accessibility.'];
        }

        // Validate file
        $validation = $this->validate($file);
        if (!$validation['is_valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        // Set up directory
        $uploadDir = $this->getUploadDirectory($entityType, $entityId);
        if (!$this->ensureDirectoryExists($uploadDir)) {
            return ['success' => false, 'error' => 'Failed to create upload directory.'];
        }

        // Generate filename
        $filename = $this->generateFilename($file, $imageType, $entityId, $entityType);
        $filePath = $uploadDir . '/' . $filename;
        $fileUrl = $this->uploadBaseUrl . '/' . $this->getRelativePath($entityType, $entityId) . '/' . $filename;

        // Process and save
        $result = $this->processAndSave($file, $filePath, $imageType);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'url' => $fileUrl,
            'path' => $filePath,
            'filename' => $filename,
        ];
    }

    /**
     * Validate uploaded file
     *
     * @param array $file Uploaded file from $_FILES
     * @return array{is_valid: bool, error?: string}
     */
    public function validate(array $file): array
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['is_valid' => false, 'error' => 'No file was uploaded.'];
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['is_valid' => false, 'error' => 'File must be less than 5MB.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_TYPES, true)) {
            return ['is_valid' => false, 'error' => 'Only JPEG, PNG, GIF, and WebP images allowed.'];
        }

        return ['is_valid' => true];
    }

    /**
     * Delete image file
     */
    public function delete(string $filePath): bool
    {
        if (file_exists($filePath) && strpos($filePath, $this->uploadBasePath) === 0) {
            return unlink($filePath);
        }
        return false;
    }

    /**
     * Process and save image with resizing
     */
    private function processAndSave(array $file, string $filePath, string $imageType): array
    {
        $dimensions = self::DIMENSIONS[$imageType] ?? self::DIMENSIONS['post'];

        $image = $this->loadImage($file['tmp_name']);
        if ($image === false) {
            return ['success' => false, 'error' => 'Failed to load image.'];
        }

        $resized = $this->resize($image, $dimensions['width'], $dimensions['height']);
        $saved = $this->saveImage($resized, $filePath);

        imagedestroy($image);
        if ($resized !== $image) {
            imagedestroy($resized);
        }

        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to save image.'];
        }

        return ['success' => true];
    }

    /**
     * Load image from file
     */
    private function loadImage(string $path)
    {
        $info = getimagesize($path);
        if ($info === false) {
            return false;
        }

        return match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/gif' => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default => false,
        };
    }

    /**
     * Resize image maintaining aspect ratio
     */
    private function resize($source, int $maxWidth, int $maxHeight)
    {
        $origWidth = imagesx($source);
        $origHeight = imagesy($source);

        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);

        // Don't upscale
        if ($ratio > 1) {
            return $source;
        }

        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        return $resized;
    }

    /**
     * Save image to file
     */
    private function saveImage($image, string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path, 8),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path, 90),
            default => false,
        };
    }

    private function getUploadDirectory(string $entityType, int $entityId): string
    {
        return $this->uploadBasePath . '/' . $this->getRelativePath($entityType, $entityId);
    }

    private function getRelativePath(string $entityType, int $entityId): string
    {
        return match ($entityType) {
            'event' => "events/{$entityId}",
            'conversation' => "conversations/{$entityId}",
            'community' => "communities/{$entityId}",
            'user' => "users/{$entityId}",
            default => "{$entityType}s/{$entityId}",
        };
    }

    private function ensureDirectoryExists(string $dir): bool
    {
        if (file_exists($dir)) {
            return true;
        }
        return mkdir($dir, 0755, true);
    }

    private function generateFilename(array $file, string $type, int $entityId, string $entityType): string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $timestamp = time();
        $random = substr(bin2hex(random_bytes(4)), 0, 8);
        return sprintf('%s_%s_%s_%s_%s.%s', $entityType, $entityId, $type, $timestamp, $random, $ext);
    }
}
