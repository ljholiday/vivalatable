<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(array $cfg)
    {
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = (string)($cfg['port'] ?? '3306');
        $dbname = $cfg['name'] ?? $cfg['dbname'] ?? 'vivalatable';
        $charset = $cfg['charset'] ?? 'utf8mb4';

        $username = $cfg['user'] ?? $cfg['username'] ?? 'root';
        $password = $this->stringify($cfg['pass'] ?? $cfg['password'] ?? '');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbname, $charset);

        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $options = isset($cfg['options']) && is_array($cfg['options'])
            ? $cfg['options'] + $defaultOptions
            : $defaultOptions;

        $this->pdo = new PDO($dsn, $username, $password, $options);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param mixed $value
     */
    private function stringify($value): string
    {
        if ($value instanceof \Stringable) {
            return (string)$value;
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return '';
    }
}
