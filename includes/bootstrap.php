<?php
/**
 * VivalaTable Bootstrap
 * Initialize the application
 */

// Define constants
define('VT_VERSION', '1.0.0');
define('VT_PLUGIN_DIR', dirname(__DIR__));
define('VT_INCLUDES_DIR', __DIR__);

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Load environment variables if .env file exists
if (file_exists(VT_PLUGIN_DIR . '/.env')) {
    $lines = file(VT_PLUGIN_DIR . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && $line[0] !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
}

// Load core classes
require_once __DIR__ . '/class-config.php';
require_once __DIR__ . '/class-database.php';
require_once __DIR__ . '/class-auth.php';
require_once __DIR__ . '/class-security.php';
require_once __DIR__ . '/class-sanitize.php';
require_once __DIR__ . '/class-mail.php';
require_once __DIR__ . '/class-time.php';
require_once __DIR__ . '/class-http.php';

// Load WordPress replacements
require_once '/Users/lonnholiday/Repositories/vivalatable-docs/wp_replacements.php';

// Load all business logic classes
$class_files = glob(__DIR__ . '/class-*.php');
foreach ($class_files as $file) {
    $filename = basename($file);
    if (!in_array($filename, [
        'class-config.php',
        'class-database.php',
        'class-auth.php',
        'class-security.php',
        'class-sanitize.php',
        'class-mail.php',
        'class-time.php',
        'class-http.php'
    ])) {
        require_once $file;
    }
}

// Initialize configuration system
VT_Config::setDatabaseConfig(include VT_PLUGIN_DIR . '/config/database.php');

// Initialize default configuration
VT_Config::initializeDefaults();
VT_Config::loadAutoloadOptions();

// Initialize systems
VT_Security::init();
VT_Auth::init();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
VT_Security::generateCSRFToken();