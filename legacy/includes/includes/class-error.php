<?php
/**
 * VivalaTable Error System
 * Error handling and reporting
 */

class VT_Error {

    private $errors = array();
    private $errorData = array();

    public function __construct($code = '', $message = '', $data = '') {
        if (!empty($code)) {
            $this->add($code, $message, $data);
        }
    }

    /**
     * Add an error
     */
    public function add($code, $message, $data = '') {
        $this->errors[$code][] = $message;
        if (!empty($data)) {
            $this->errorData[$code] = $data;
        }
    }

    /**
     * Get error codes
     */
    public function getErrorCodes() {
        return array_keys($this->errors);
    }

    /**
     * Get error messages for a code
     */
    public function getErrorMessages($code = '') {
        if (empty($code)) {
            $all_messages = array();
            foreach ($this->errors as $code => $messages) {
                $all_messages = array_merge($all_messages, $messages);
            }
            return $all_messages;
        }

        return $this->errors[$code] ?? array();
    }

    /**
     * Get first error message
     */
    public function getErrorMessage($code = '') {
        $messages = $this->getErrorMessages($code);
        return !empty($messages) ? $messages[0] : '';
    }

    /**
     * Get error data
     */
    public function getErrorData($code = '') {
        if (empty($code)) {
            return $this->errorData;
        }

        return $this->errorData[$code] ?? '';
    }

    /**
     * Check if there are any errors
     */
    public function hasErrors() {
        return !empty($this->errors);
    }

    /**
     * Convert to string
     */
    public function __toString() {
        return $this->getErrorMessage();
    }
}

/**
 * Check if a variable is a VT_Error
 */
function is_vt_error($thing) {
    return ($thing instanceof VT_Error);
}