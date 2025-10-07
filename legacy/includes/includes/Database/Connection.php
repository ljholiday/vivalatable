<?php
/**
 * Modern Database Connection
 * Replaces VT_Database singleton with proper dependency injection
 */

class VT_Database_Connection {

    private \PDO $pdo;
    private array $config;
    private string $prefix = 'vt_';

    public function __construct() {
        $this->loadConfig();
        $this->connect();
    }

    /**
     * Get PDO instance
     */
    public function getPdo(): \PDO {
        return $this->pdo;
    }

    /**
     * Get table prefix
     */
    public function getPrefix(): string {
        return $this->prefix;
    }

    /**
     * Execute a prepared statement
     */
    public function execute(string $query, array $params = []): \PDOStatement {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch single row
     */
    public function fetchRow(string $query, array $params = []): ?object {
        $stmt = $this->execute($query, $params);
        $result = $stmt->fetch(\PDO::FETCH_OBJ);
        return $result ?: null;
    }

    /**
     * Fetch multiple rows
     */
    public function fetchAll(string $query, array $params = []): array {
        $stmt = $this->execute($query, $params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Fetch a single column value
     */
    public function fetchColumn(string $query, array $params = []): mixed {
        $stmt = $this->execute($query, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Insert record and return ID
     */
    public function insert(string $table, array $data): int {
        $table = $this->prefix . $table;
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        $query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->execute($query, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update records
     */
    public function update(string $table, array $data, array $where): int {
        $table = $this->prefix . $table;

        $setPairs = array_map(fn($col) => "$col = :$col", array_keys($data));
        $wherePairs = array_map(fn($col) => "$col = :where_$col", array_keys($where));

        $query = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setPairs),
            implode(' AND ', $wherePairs)
        );

        // Prefix where parameters to avoid conflicts
        $whereParams = [];
        foreach ($where as $key => $value) {
            $whereParams["where_$key"] = $value;
        }

        return $this->execute($query, array_merge($data, $whereParams))->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(string $table, array $where): int {
        $table = $this->prefix . $table;
        $wherePairs = array_map(fn($col) => "$col = :$col", array_keys($where));

        $query = sprintf(
            "DELETE FROM %s WHERE %s",
            $table,
            implode(' AND ', $wherePairs)
        );

        return $this->execute($query, $where)->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool {
        return $this->pdo->rollBack();
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool {
        return $this->pdo->inTransaction();
    }

    /**
     * Load database configuration
     */
    private function loadConfig(): void {
        $configPath = dirname(__DIR__, 2) . '/config/database.php';

        if (!file_exists($configPath)) {
            throw new \RuntimeException("Database configuration file not found: $configPath");
        }

        $this->config = require $configPath;

        if (!is_array($this->config)) {
            throw new \RuntimeException("Invalid database configuration format");
        }
    }

    /**
     * Establish database connection
     */
    private function connect(): void {
        try {
            // Use host if available, otherwise fallback to socket
            if (!empty($this->config['host'])) {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    $this->config['host'],
                    $this->config['database'],
                    $this->config['charset'] ?? 'utf8mb4'
                );
            } else {
                $dsn = sprintf(
                    'mysql:unix_socket=%s;dbname=%s;charset=%s',
                    $this->config['socket'] ?? '/tmp/mysql.sock',
                    $this->config['database'],
                    $this->config['charset'] ?? 'utf8mb4'
                );
            }

            $options = $this->config['options'] ?? [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->pdo = new \PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );

        } catch (\PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }
}