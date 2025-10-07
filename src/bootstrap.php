<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (!defined('VT_VERSION')) {
    define('VT_VERSION', '2.0-dev');
}

/**
 * Legacy compatibility shim: VT_Text
 * Keeps old partials (like templates/partials/entity-card.php) working.
 */
if (!class_exists('VT_Text')) {
    final class VT_Text {
        public static function esc(string $text): string {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
        public static function plain(string $text): string {
            return trim(strip_tags($text));
        }
        public static function truncate(string $text, int $limit = 120, string $ellipsis = '…'): string {
            return self::truncate_chars($text, $limit, $ellipsis);
        }
        public static function truncate_chars(string $text, int $limit = 120, string $ellipsis = '…'): string {
            $t = self::plain($text);
            if (mb_strlen($t) <= $limit) return $t;
            return rtrim(mb_substr($t, 0, $limit)) . $ellipsis;
        }
        public static function truncate_words(string $text, int $limit = 25, string $ellipsis = '…'): string {
            $t = preg_replace('/\s+/u', ' ', self::plain($text));
            $words = $t === '' ? [] : explode(' ', $t);
            if (count($words) <= $limit) return $t;
            return implode(' ', array_slice($words, 0, $limit)) . $ellipsis;
        }
        public static function excerpt(string $text, int $words = 30, string $ellipsis = '…'): string {
            return self::truncate_words($text, $words, $ellipsis);
        }
    }
}

