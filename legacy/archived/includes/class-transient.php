<?php
/**
 * VivalaTable Transient System
 * Temporary data storage with expiration
 */

class VT_Transient {

    /**
     * Set a transient value
     */
    public static function set($key, $value, $expiration = 3600) {
        $key = vt_service('validation.sanitizer')->textField($key);
        $data = array(
            'value' => $value,
            'expires' => time() + $expiration
        );

        // Store in session for now (in production you might use database or Redis)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['vt_transients'][$key] = $data;
        return true;
    }

    /**
     * Get a transient value
     */
    public static function get($key) {
        $key = vt_service('validation.sanitizer')->textField($key);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['vt_transients'][$key])) {
            return false;
        }

        $data = $_SESSION['vt_transients'][$key];

        // Check if expired
        if (time() > $data['expires']) {
            unset($_SESSION['vt_transients'][$key]);
            return false;
        }

        return $data['value'];
    }

    /**
     * Delete a transient
     */
    public static function delete($key) {
        $key = vt_service('validation.sanitizer')->textField($key);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['vt_transients'][$key])) {
            unset($_SESSION['vt_transients'][$key]);
            return true;
        }

        return false;
    }

    /**
     * Clean up expired transients
     */
    public static function cleanUp() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['vt_transients'])) {
            return;
        }

        $current_time = time();
        foreach ($_SESSION['vt_transients'] as $key => $data) {
            if ($current_time > $data['expires']) {
                unset($_SESSION['vt_transients'][$key]);
            }
        }
    }
}