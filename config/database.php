<?php
/**
 * VivalaTable Database Configuration
 */

return [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'database' => $_ENV['DB_NAME'] ?? 'vivalatable',
    'username' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? 'root',
    'port' => $_ENV['DB_PORT'] ?? 3306,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];