<?php
/**
 * VivalaTable Bootstrap
 * Core initialization and configuration loading
 */

// Prevent direct access
if (!defined('VT_ROOT')) {
    define('VT_ROOT', dirname(__DIR__));
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration files
if (file_exists(VT_ROOT . '/config/database.php')) {
    require_once VT_ROOT . '/config/database.php';
} else {
    die('Database configuration not found. Please copy config/database.example.php to config/database.php and configure your database settings.');
}

// Load other config files
$config_files = ['email.php', 'ai.php'];
foreach ($config_files as $config_file) {
    $config_path = VT_ROOT . '/config/' . $config_file;
    if (file_exists($config_path)) {
        require_once $config_path;
    }
}

// Load core functions
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

// Load core classes
require_once VT_ROOT . '/classes/Database.php';
require_once VT_ROOT . '/classes/User.php';
require_once VT_ROOT . '/classes/EventManager.php';
require_once VT_ROOT . '/classes/CommunityManager.php';
require_once VT_ROOT . '/classes/ConversationManager.php';
require_once VT_ROOT . '/classes/GuestManager.php';

// Initialize database connection
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed. Please check your configuration.');
}

// Set timezone
date_default_timezone_set('America/New_York');

// Define constants
define('VT_VERSION', '1.0.0');
define('VT_UPLOADS_URL', '/assets/uploads/');
define('VT_UPLOADS_PATH', VT_ROOT . '/assets/uploads/');

// Create uploads directory if it doesn't exist
if (!is_dir(VT_UPLOADS_PATH)) {
    mkdir(VT_UPLOADS_PATH, 0755, true);
}

// Initialize current user
$current_user = null;
if (is_user_logged_in()) {
    $current_user = vt_get_current_user();
}