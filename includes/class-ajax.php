<?php
/**
 * VivalaTable AJAX System
 * Handles AJAX requests and responses
 */

class VT_Ajax {

    private static $registered_actions = array();

    /**
     * Register an AJAX action
     */
    public static function register($action, $callback) {
        self::$registered_actions[$action] = $callback;
    }

    /**
     * Handle AJAX request
     */
    public static function handleRequest() {
        if (!isset($_POST['action'])) {
            self::sendError('No action specified');
        }

        $action = vt_service('validation.validator')->textField($_POST['action']);

        if (!isset(self::$registered_actions[$action])) {
            self::sendError('Unknown action');
        }

        $callback = self::$registered_actions[$action];

        if (is_callable($callback)) {
            call_user_func($callback);
        } else {
            self::sendError('Invalid callback');
        }
    }

    /**
     * Send successful AJAX response
     */
    public static function sendSuccess($data = array()) {
        self::sendResponse(true, $data);
    }

    /**
     * Send error AJAX response
     */
    public static function sendError($message, $data = array()) {
        self::sendResponse(false, $data, $message);
    }

    /**
     * Send AJAX response
     */
    private static function sendResponse($success, $data = array(), $message = '') {
        header('Content-Type: application/json');

        $response = array(
            'success' => $success,
            'data' => $data
        );

        if ($message) {
            $response['message'] = $message;
        }

        echo json_encode($response);
        exit;
    }

    /**
     * Verify AJAX request has valid nonce
     */
    public static function verifyNonce($action, $name = '_ajax_nonce') {
        if (!vt_service('security.service')->verifyNonce($_POST[$name] ?? '', $action)) {
            self::sendError('Security check failed');
        }
    }
}