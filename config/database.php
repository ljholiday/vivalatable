<?php
/**
 * Database Configuration
 * Copy this file to database.php and update with your credentials
 */

// Database connection settings
define('VT_DB_HOST', 'localhost');
define('VT_DB_NAME', 'vivalatable');
define('VT_DB_USER', 'root');
define('VT_DB_PASS', 'root');
define('VT_DB_CHARSET', 'utf8mb4');

// Table prefix
define('VT_TABLE_PREFIX', 'vt_');

// Database options
define('VT_DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    PDO::ATTR_EMULATE_PREPARES => false,
]);