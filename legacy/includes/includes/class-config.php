<?php
/**
 * VivalaTable Configuration System
 * Replacement for WordPress get_option/update_option
 */

class VT_Config {
    private static $cache = [];
    private static $database_config = null;

    public static function get($option_name, $default = false) {
        // Check cache first
        if (isset(self::$cache[$option_name])) {
            return self::$cache[$option_name];
        }

        $db = VT_Database::getInstance();
        $value = $db->getVar("SELECT option_value FROM vt_config WHERE option_name = '$option_name'");

        if ($value !== null) {
            // Try to unserialize if it's serialized data
            $unserialized = @unserialize($value);
            $final_value = ($unserialized !== false || $value === 'b:0;') ? $unserialized : $value;
        } else {
            $final_value = $default;
        }

        // Cache the result
        self::$cache[$option_name] = $final_value;

        return $final_value;
    }

    public static function update($option_name, $option_value, $autoload = null) {
        $db = VT_Database::getInstance();

        // Serialize complex data
        if (is_array($option_value) || is_object($option_value)) {
            $option_value = serialize($option_value);
        }

        $autoload = $autoload ?: 'yes';

        // Check if option exists
        $exists = $db->getVar("SELECT COUNT(*) FROM vt_config WHERE option_name = '$option_name'");

        if ($exists) {
            $result = $db->update('config',
                ['option_value' => $option_value, 'autoload' => $autoload],
                ['option_name' => $option_name]
            );
        } else {
            $result = $db->insert('config', [
                'option_name' => $option_name,
                'option_value' => $option_value,
                'autoload' => $autoload
            ]);
        }

        if ($result) {
            // Update cache
            $final_value = @unserialize($option_value);
            self::$cache[$option_name] = ($final_value !== false || $option_value === 'b:0;') ? $final_value : $option_value;
        }

        return $result !== false;
    }

    public static function add($option_name, $option_value, $autoload = 'yes') {
        // Check if option already exists
        $exists = self::get($option_name, null);
        if ($exists !== null) {
            return false;
        }

        return self::update($option_name, $option_value, $autoload);
    }

    public static function delete($option_name) {
        $db = VT_Database::getInstance();
        $result = $db->delete('config', ['option_name' => $option_name]);

        if ($result) {
            unset(self::$cache[$option_name]);
        }

        return $result;
    }

    public static function getDatabaseConfig() {
        if (self::$database_config === null) {
            self::$database_config = [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'database' => $_ENV['DB_NAME'] ?? 'vivalatable',
                'username' => $_ENV['DB_USER'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? 'root'
            ];
        }

        return self::$database_config;
    }

    public static function setDatabaseConfig($config) {
        self::$database_config = $config;
    }

    // Load all autoload options into cache
    public static function loadAutoloadOptions() {
        $db = VT_Database::getInstance();
        $options = $db->getResults("SELECT option_name, option_value FROM vt_config WHERE autoload = 'yes'");

        if ($options) {
            foreach ($options as $option) {
                $value = @unserialize($option->option_value);
                self::$cache[$option->option_name] = ($value !== false || $option->option_value === 'b:0;') ? $value : $option->option_value;
            }
        }
    }

    // Get all configuration as array
    public static function getAll() {
        $db = VT_Database::getInstance();
        $options = $db->getResults("SELECT option_name, option_value FROM vt_config");

        $config = [];
        foreach ($options as $option) {
            $value = @unserialize($option->option_value);
            $config[$option->option_name] = ($value !== false || $option->option_value === 'b:0;') ? $value : $option->option_value;
        }

        return $config;
    }

    // Initialize default configuration
    public static function initializeDefaults() {
        $defaults = [
            'site_title' => 'VivalaTable',
            'site_description' => 'Social event management platform',
            'admin_email' => 'admin@vivalatable.com',
            'timezone' => 'America/New_York',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'allow_registrations' => true,
            'require_email_verification' => false,
            'site_admins' => [],
            'upload_path' => __DIR__ . '/../uploads',
            'upload_max_size' => 10 * 1024 * 1024, // 10MB
            'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_secure' => 'tls'
        ];

        foreach ($defaults as $key => $value) {
            if (self::get($key, null) === null) {
                self::add($key, $value);
            }
        }
    }
}