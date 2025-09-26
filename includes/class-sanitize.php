<?php
/**
 * VivalaTable Sanitization System
 * Replacement for WordPress sanitization functions
 */

class VT_Sanitize {

    public static function html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    public static function attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    public static function textField($str) {
        $filtered = strip_tags(trim($str));
        $filtered = preg_replace('/\s+/', ' ', $filtered);
        return $filtered;
    }

    public static function email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    public static function url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }

    public static function int($int) {
        return (int) $int;
    }

    public static function float($float) {
        return (float) $float;
    }

    public static function textarea($data) {
        // Plain text only - strip all HTML tags and normalize whitespace
        $data = strip_tags($data);
        $data = preg_replace('/\s+/', ' ', $data); // Normalize whitespace
        return trim($data);
    }

    public static function post($data) {
        // Allow safe HTML tags
        $allowed_tags = [
            'a' => ['href' => [], 'title' => [], 'target' => []],
            'b' => [],
            'strong' => [],
            'i' => [],
            'em' => [],
            'u' => [],
            'br' => [],
            'p' => [],
            'span' => ['class' => []],
            'div' => ['class' => []],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'blockquote' => [],
            'code' => [],
            'pre' => [],
            'img' => ['src' => [], 'alt' => [], 'width' => [], 'height' => [], 'class' => []],
        ];

        return self::kses($data, $allowed_tags);
    }

    public static function stripTags($string, $removeBreaks = false) {
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);
        $string = strip_tags($string);

        if ($removeBreaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }

        return trim($string);
    }

    public static function kses($string, $allowed_html) {
        // Simple implementation - in production you'd want a more robust HTML sanitizer
        if (empty($allowed_html)) {
            return self::stripTags($string);
        }

        // For now, just strip tags not in allowed list
        $allowed_tags = '<' . implode('><', array_keys($allowed_html)) . '>';
        return strip_tags($string, $allowed_tags);
    }

    public static function fileName($filename) {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        return trim($filename, '.');
    }

    public static function slug($title) {
        $title = strip_tags($title);
        $title = preg_replace('~[^\pL\d]+~u', '-', $title);
        $title = iconv('utf-8', 'us-ascii//TRANSLIT', $title);
        $title = preg_replace('~[^-\w]+~', '', $title);
        $title = trim($title, '-');
        $title = preg_replace('~-+~', '-', $title);
        $title = strtolower($title);

        return empty($title) ? 'n-a' : $title;
    }

    public static function sql($value) {
        $db = VT_Database::getInstance();
        return $db->escape($value);
    }

    // Escaping functions for output
    public static function escHtml($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    public static function escAttr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    public static function escUrl($url) {
        // First validate the URL
        $url = filter_var($url, FILTER_VALIDATE_URL);
        if ($url === false) {
            return '';
        }

        // Then escape for HTML output
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    public static function escJs($text) {
        // Properly escape for JavaScript strings
        $text = str_replace(
            array('\\', '/', '"', "'", "\n", "\r", "\t"),
            array('\\\\', '\\/', '\\"', "\\'", '\\n', '\\r', '\\t'),
            $text
        );
        return $text;
    }

    // Helper functions for textField
    private static function wp_check_invalid_utf8($string, $strip = false) {
        $string = (string) $string;

        if (0 === strlen($string)) {
            return '';
        }

        if (function_exists('mb_check_encoding')) {
            if (!mb_check_encoding($string, 'UTF-8')) {
                return $strip ? '' : $string;
            }
        } elseif (preg_match('/^./us', $string) !== 1) {
            return $strip ? '' : $string;
        }

        return $string;
    }

    private static function wp_pre_kses_less_than($text) {
        return preg_replace_callback('%<[^>]*?((?=<)|>|$)%', ['self', 'wp_pre_kses_less_than_callback'], $text);
    }

    private static function wp_pre_kses_less_than_callback($matches) {
        if (false === strpos($matches[0], '>')) {
            return htmlspecialchars($matches[0]);
        }
        return $matches[0];
    }

    private static function wp_normalize_whitespace($str) {
        return trim(preg_replace('/[\r\n\t ]+/', ' ', $str));
    }
}