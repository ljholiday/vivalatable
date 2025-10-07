<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class Database {
    private PDO $pdo;
    public function __construct(array $cfg) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['host'] ?? '127.0.0.1',
            $cfg['port'] ?? '3306',
            $cfg['name'] ?? 'vivalatable'
        );
        $this->pdo = new PDO($dsn, $cfg['user'] ?? 'root', $cfg['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    public function pdo(): PDO { return $this->pdo; }
}

