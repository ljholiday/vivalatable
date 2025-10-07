<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;
use App\Services\EventService;

/**
 * Very small service container.
 * Add more services here as you implement them.
 */
function vt_service(string $name) {
    static $services = [];

    if (!isset($services['db'])) {
        // Load DB config: points to your EXISTING 'vivalatable' database
        $cfg = require __DIR__ . '/../config/database.php';
        $services['db'] = new Database($cfg);
    }

    return match ($name) {
        'event.service' => $services['event'] ??= new EventService($services['db']),
        default => throw new RuntimeException('Unknown service: ' . $name),
    };
}

