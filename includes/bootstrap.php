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

// Load modern architecture classes
require_once __DIR__ . '/Container.php';
require_once __DIR__ . '/Database/Connection.php';
require_once __DIR__ . '/Database/QueryBuilder.php';
require_once __DIR__ . '/Http/Request.php';
require_once __DIR__ . '/Http/Response.php';
require_once __DIR__ . '/Auth/UserRepository.php';
require_once __DIR__ . '/Auth/AuthenticationService.php';
require_once __DIR__ . '/Validation/InputSanitizer.php';
require_once __DIR__ . '/Validation/ValidatorService.php';
require_once __DIR__ . '/Security/SecurityService.php';

// Load legacy VT classes (for gradual migration)
require_once __DIR__ . '/class-config.php';
require_once __DIR__ . '/class-database.php';
require_once __DIR__ . '/class-auth.php';
require_once __DIR__ . '/class-security.php';
require_once __DIR__ . '/class-sanitize.php';
require_once __DIR__ . '/class-mail.php';
require_once __DIR__ . '/class-time.php';
require_once __DIR__ . '/class-http.php';

// Initialize modern dependency injection container
$container = new Container();
$container->createCompatibilityLayer();

// WordPress functions have been replaced with native VT class methods
// Phase 1: Modern architecture foundation added alongside legacy system

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
vt_service('security.service')->init();
vt_service('auth.service')->init();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
vt_service('security.service')->generateCSRFToken();