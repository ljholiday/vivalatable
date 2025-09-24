<?php
/**
 * Database Connection Manager
 * Singleton pattern for PDO database connections
 */

class Database {
    private static $instance = null;
    private $pdo = null;

    private function __construct() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                VT_DB_HOST,
                VT_DB_NAME,
                VT_DB_CHARSET
            );

            $this->pdo = new PDO($dsn, VT_DB_USER, VT_DB_PASS, VT_DB_OPTIONS);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    /**
     * Get table name with prefix
     */
    public static function table(string $table): string {
        return VT_TABLE_PREFIX . $table;
    }

    /**
     * Prepare and execute a query with parameters
     */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Insert a record and return the insert ID
     */
    public function insert(string $table, array $data): int {
        $table = self::table($table);
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);

        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update records
     */
    public function update(string $table, array $data, array $where): int {
        $table = self::table($table);

        $set_clause = [];
        foreach (array_keys($data) as $field) {
            $set_clause[] = "{$field} = :{$field}";
        }

        $where_clause = [];
        $where_params = [];
        foreach ($where as $field => $value) {
            $where_clause[] = "{$field} = :where_{$field}";
            $where_params["where_{$field}"] = $value;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $set_clause) .
               " WHERE " . implode(' AND ', $where_clause);

        $params = array_merge($data, $where_params);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(string $table, array $where): int {
        $table = self::table($table);

        $where_clause = [];
        foreach (array_keys($where) as $field) {
            $where_clause[] = "{$field} = :{$field}";
        }

        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $where_clause);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($where);

        return $stmt->rowCount();
    }

    /**
     * Select a single record
     */
    public function selectOne(string $table, array $where = [], array $fields = ['*']): ?object {
        $table = self::table($table);
        $field_list = implode(', ', $fields);

        $sql = "SELECT {$field_list} FROM {$table}";
        $params = [];

        if (!empty($where)) {
            $where_clause = [];
            foreach (array_keys($where) as $field) {
                $where_clause[] = "{$field} = :{$field}";
            }
            $sql .= " WHERE " . implode(' AND ', $where_clause);
            $params = $where;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: null;
    }

    /**
     * Select multiple records
     */
    public function select(string $table, array $where = [], array $fields = ['*'], string $order_by = '', int $limit = 0): array {
        $table = self::table($table);
        $field_list = implode(', ', $fields);

        $sql = "SELECT {$field_list} FROM {$table}";
        $params = [];

        if (!empty($where)) {
            $where_clause = [];
            foreach (array_keys($where) as $field) {
                $where_clause[] = "{$field} = :{$field}";
            }
            $sql .= " WHERE " . implode(' AND ', $where_clause);
            $params = $where;
        }

        if ($order_by) {
            $sql .= " ORDER BY {$order_by}";
        }

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Generate a secure random token
     */
    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
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
}