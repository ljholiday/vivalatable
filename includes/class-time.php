<?php
/**
 * VivalaTable Time/Date System
 * Replacement for WordPress time functions
 */

class VT_Time {

    public static function currentTime($type = 'timestamp', $gmt = 0) {
        return self::current($type, $gmt);
    }

    public static function current($type = 'timestamp', $gmt = 0) {
        $timezone = VT_Config::get('timezone', 'UTC');

        if ($gmt) {
            $dt = new DateTime('now', new DateTimeZone('UTC'));
        } else {
            $dt = new DateTime('now', new DateTimeZone($timezone));
        }

        switch ($type) {
            case 'mysql':
                return $dt->format('Y-m-d H:i:s');
            case 'timestamp':
                return $dt->getTimestamp();
            case 'U':
                return $dt->getTimestamp();
            default:
                return $dt->getTimestamp();
        }
    }

    public static function format($format, $timestamp = null, $timezone = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }

        if ($timezone === null) {
            $timezone = VT_Config::get('timezone', 'UTC');
        }

        $dt = new DateTime();
        $dt->setTimestamp($timestamp);
        $dt->setTimezone(new DateTimeZone($timezone));

        return $dt->format($format);
    }

    public static function humanDiff($from, $to = 0) {
        if ($to == 0) {
            $to = time();
        }

        $diff = abs($to - $from);

        if ($diff < 60) {
            return $diff == 1 ? '1 second' : $diff . ' seconds';
        }

        $diff = round($diff / 60);
        if ($diff < 60) {
            return $diff == 1 ? '1 minute' : $diff . ' minutes';
        }

        $diff = round($diff / 60);
        if ($diff < 24) {
            return $diff == 1 ? '1 hour' : $diff . ' hours';
        }

        $diff = round($diff / 24);
        if ($diff < 7) {
            return $diff == 1 ? '1 day' : $diff . ' days';
        }

        $diff = round($diff / 7);
        if ($diff < 4) {
            return $diff == 1 ? '1 week' : $diff . ' weeks';
        }

        $diff = round($diff / 4);
        if ($diff < 12) {
            return $diff == 1 ? '1 month' : $diff . ' months';
        }

        $diff = round($diff / 12);
        return $diff == 1 ? '1 year' : $diff . ' years';
    }

    public static function timeAgo($timestamp) {
        $time_diff = time() - $timestamp;

        if ($time_diff < 0) {
            return 'in the future';
        }

        if ($time_diff < 60) {
            return 'just now';
        }

        if ($time_diff < 3600) {
            $mins = floor($time_diff / 60);
            return $mins . ($mins == 1 ? ' minute ago' : ' minutes ago');
        }

        if ($time_diff < 86400) {
            $hours = floor($time_diff / 3600);
            return $hours . ($hours == 1 ? ' hour ago' : ' hours ago');
        }

        if ($time_diff < 604800) {
            $days = floor($time_diff / 86400);
            return $days . ($days == 1 ? ' day ago' : ' days ago');
        }

        if ($time_diff < 2592000) {
            $weeks = floor($time_diff / 604800);
            return $weeks . ($weeks == 1 ? ' week ago' : ' weeks ago');
        }

        if ($time_diff < 31536000) {
            $months = floor($time_diff / 2592000);
            return $months . ($months == 1 ? ' month ago' : ' months ago');
        }

        $years = floor($time_diff / 31536000);
        return $years . ($years == 1 ? ' year ago' : ' years ago');
    }

    public static function formatDate($timestamp, $format = null) {
        if ($format === null) {
            $format = VT_Config::get('date_format', 'Y-m-d');
        }

        return self::format($format, $timestamp);
    }

    public static function formatTime($timestamp, $format = null) {
        if ($format === null) {
            $format = VT_Config::get('time_format', 'H:i:s');
        }

        return self::format($format, $timestamp);
    }

    public static function formatDateTime($timestamp, $date_format = null, $time_format = null) {
        $date_format = $date_format ?: VT_Config::get('date_format', 'Y-m-d');
        $time_format = $time_format ?: VT_Config::get('time_format', 'H:i:s');

        return self::format($date_format . ' ' . $time_format, $timestamp);
    }

    public static function isToday($timestamp) {
        $today = date('Y-m-d');
        $check_date = date('Y-m-d', $timestamp);
        return $today === $check_date;
    }

    public static function isTomorrow($timestamp) {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $check_date = date('Y-m-d', $timestamp);
        return $tomorrow === $check_date;
    }

    public static function isYesterday($timestamp) {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $check_date = date('Y-m-d', $timestamp);
        return $yesterday === $check_date;
    }

    public static function getTimezoneList() {
        return [
            'UTC' => 'UTC',
            'America/New_York' => 'Eastern Time',
            'America/Chicago' => 'Central Time',
            'America/Denver' => 'Mountain Time',
            'America/Los_Angeles' => 'Pacific Time',
            'America/Anchorage' => 'Alaska Time',
            'Pacific/Honolulu' => 'Hawaii Time',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Europe/Berlin' => 'Berlin',
            'Asia/Tokyo' => 'Tokyo',
            'Asia/Shanghai' => 'Shanghai',
            'Australia/Sydney' => 'Sydney'
        ];
    }

    public static function convertTimezone($timestamp, $from_timezone, $to_timezone) {
        $dt = new DateTime();
        $dt->setTimestamp($timestamp);
        $dt->setTimezone(new DateTimeZone($from_timezone));
        $dt->setTimezone(new DateTimeZone($to_timezone));
        return $dt->getTimestamp();
    }

    public static function parseDateString($date_string, $format = 'Y-m-d H:i:s') {
        $dt = DateTime::createFromFormat($format, $date_string);
        return $dt ? $dt->getTimestamp() : false;
    }

    public static function addDays($timestamp, $days) {
        return $timestamp + ($days * 86400);
    }

    public static function addHours($timestamp, $hours) {
        return $timestamp + ($hours * 3600);
    }

    public static function addMinutes($timestamp, $minutes) {
        return $timestamp + ($minutes * 60);
    }

    public static function startOfDay($timestamp) {
        return strtotime(date('Y-m-d 00:00:00', $timestamp));
    }

    public static function endOfDay($timestamp) {
        return strtotime(date('Y-m-d 23:59:59', $timestamp));
    }

    public static function startOfWeek($timestamp) {
        return strtotime('last Sunday', $timestamp);
    }

    public static function endOfWeek($timestamp) {
        return strtotime('next Saturday', $timestamp);
    }
}

// Define current_time function for compatibility - handled in wp_replacements.php