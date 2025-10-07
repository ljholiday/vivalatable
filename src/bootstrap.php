<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// No namespace here — keep this file in the global namespace.

if (!defined('VT_VERSION')) {
    define('VT_VERSION', '2.0-dev');
}

use App\Database\Database;
use App\Services\EventService;

/**
 * Very small service container.
 */
function vt_service(string $name) {
    static $services = [];

    if (!isset($services['db'])) {
        $cfg = require __DIR__ . '/../config/database.php';
        if (!is_array($cfg)) {
            throw new RuntimeException('config/database.php must return an array.');
        }
        $services['db'] = new Database($cfg); // <- now resolves to App\Database\Database
    }

    return match ($name) {
        'event.service' => $services['event'] ??= new EventService($services['db']),
        default => throw new RuntimeException('Unknown service: ' . $name),
    };
}

