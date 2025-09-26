<?php
/**
 * VivalaTable Cache System
 * Simple in-memory caching for database queries
 */

class VT_Cache {

    private static $cache = array();

    /**
     * Get cached value
     */
    public static function get($key) {
        return isset(self::$cache[$key]) ? self::$cache[$key] : null;
    }

    /**
     * Set cached value
     */
    public static function set($key, $value, $ttl = 3600) {
        self::$cache[$key] = array(
            'value' => $value,
            'expires' => time() + $ttl
        );
    }

    /**
     * Delete cached value
     */
    public static function delete($key) {
        unset(self::$cache[$key]);
    }

    /**
     * Clear all cache
     */
    public static function flush() {
        self::$cache = array();
    }

    /**
     * Check if cache exists and is valid
     */
    public static function exists($key) {
        if (!isset(self::$cache[$key])) {
            return false;
        }

        if (time() > self::$cache[$key]['expires']) {
            unset(self::$cache[$key]);
            return false;
        }

        return true;
    }
}