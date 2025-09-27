<?php
/**
 * VivalaTable Database Singleton Class
 * Replacement for WordPress $wpdb
 */

class VT_Database {
    private static $instance = null;
    private $pdo;
    public $prefix = 'vt_';

    private function __construct() {
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

private function connect() {
    // Load database configuration from config/database.php
    $config = require __DIR__ . '/../config/database.php';

    // Use host if available, otherwise fallback to socket
    if (!empty($config['host'])) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );
    } else {
        // fallback if no host is set — optional
        $dsn = sprintf(
            'mysql:unix_socket=/tmp/mysql.sock;dbname=%s;charset=%s',
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );
    }

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public function prepare($query, ...$args) {
        try {
            // Replace WordPress-style placeholders with PDO placeholders
            $pdo_query = str_replace(['%d', '%s', '%f'], '?', $query);

            if (!empty($args)) {
                $stmt = $this->pdo->prepare($pdo_query);
                $stmt->execute($args);
                return $stmt;
            } else {
                return $this->pdo->prepare($pdo_query);
            }
        } catch (PDOException $e) {
            error_log('Database query failed: ' . $e->getMessage() . ' Query: ' . $query);
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }

    public function getResults($query, $output_type = OBJECT) {
        try {
            // Handle if $query is already a prepared statement
            if ($query instanceof PDOStatement) {
                $stmt = $query;
            } else {
                $stmt = $this->pdo->query($query);
            }

            if ($output_type === ARRAY_A) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($output_type === ARRAY_N) {
                return $stmt->fetchAll(PDO::FETCH_NUM);
            } else {
                return $stmt->fetchAll(PDO::FETCH_OBJ);
            }
        } catch (PDOException $e) {
            $query_str = $query instanceof PDOStatement ? 'prepared statement' : $query;
            error_log('Database query failed: ' . $e->getMessage() . ' Query: ' . $query_str);
            return null;
        }
    }

    public function getRow($query, $output_type = OBJECT, $y = 0) {
        try {
            // Handle if $query is already a prepared statement
            if ($query instanceof PDOStatement) {
                $stmt = $query;
            } else {
                $stmt = $this->pdo->query($query);
            }

            if ($output_type === ARRAY_A) {
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($output_type === ARRAY_N) {
                $results = $stmt->fetchAll(PDO::FETCH_NUM);
            } else {
                $results = $stmt->fetchAll(PDO::FETCH_OBJ);
            }

            return isset($results[$y]) ? $results[$y] : null;
        } catch (PDOException $e) {
            $query_str = $query instanceof PDOStatement ? 'prepared statement' : $query;
            error_log('Database query failed: ' . $e->getMessage() . ' Query: ' . $query_str);
            return null;
        }
    }

    public function getVar($query, $x = 0, $y = 0) {
        try {
            // Handle if $query is already a prepared statement
            if ($query instanceof PDOStatement) {
                $stmt = $query;
            } else {
                $stmt = $this->pdo->query($query);
            }
            $results = $stmt->fetchAll(PDO::FETCH_NUM);

            return isset($results[$y][$x]) ? $results[$y][$x] : null;
        } catch (PDOException $e) {
            $query_str = $query instanceof PDOStatement ? 'prepared statement' : $query;
            error_log('Database query failed: ' . $e->getMessage() . ' Query: ' . $query_str);
            return null;
        }
    }

    public function getCol($query, $x = 0) {
        try {
            // Handle if $query is already a prepared statement
            if ($query instanceof PDOStatement) {
                $stmt = $query;
            } else {
                $stmt = $this->pdo->query($query);
            }
            $results = $stmt->fetchAll(PDO::FETCH_NUM);

            $col = array();
            foreach ($results as $row) {
                if (isset($row[$x])) {
                    $col[] = $row[$x];
                }
            }

            return $col;
        } catch (PDOException $e) {
            $query_str = $query instanceof PDOStatement ? 'prepared statement' : $query;
            error_log('Database query failed: ' . $e->getMessage() . ' Query: ' . $query_str);
            return array();
        }
    }

    public function insert($table, $data, $format = null) {
        $table = $this->prefix . $table;
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        try {
            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute(array_values($data));

            if ($result) {
                // Return lastInsertId if available, otherwise return true for success
                $lastId = $this->pdo->lastInsertId();
                return $lastId ?: true;
            } else {
                error_log('Database insert failed - execute returned false. Query: ' . $query);
                error_log('Error info: ' . json_encode($stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log('Database insert failed: ' . $e->getMessage() . ' Query: ' . $query);
            error_log('Data: ' . json_encode($data));
            return false;
        }
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        $table = $this->prefix . $table;

        $set_clause = [];
        $values = [];

        foreach ($data as $field => $value) {
            $set_clause[] = $field . ' = ?';
            $values[] = $value;
        }

        $where_clause = [];
        foreach ($where as $field => $value) {
            $where_clause[] = $field . ' = ?';
            $values[] = $value;
        }

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $set_clause),
            implode(' AND ', $where_clause)
        );

        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log('Database update failed: ' . $e->getMessage() . ' Query: ' . $query);
            return false;
        }
    }

    public function delete($table, $where, $where_format = null) {
        $table = $this->prefix . $table;

        $where_clause = [];
        $values = [];

        foreach ($where as $field => $value) {
            $where_clause[] = $field . ' = ?';
            $values[] = $value;
        }

        $query = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $where_clause)
        );

        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log('Database delete failed: ' . $e->getMessage() . ' Query: ' . $query);
            return false;
        }
    }

    public function query($query) {
        try {
            $result = $this->pdo->exec($query);
            return $result !== false;
        } catch (PDOException $e) {
            error_log('Database query failed: ' . $e->getMessage() . ' Query: ' . $query);
            return false;
        }
    }

    public function escape($text) {
        return $this->pdo->quote($text);
    }

    public function escLike($text) {
        return str_replace(['%', '_'], ['\%', '\_'], $text);
    }

    public function __get($name) {
        if ($name === 'insert_id') {
            return $this->pdo->lastInsertId();
        }
        return null;
    }
}

// Define WordPress database constants for compatibility
if (!defined('OBJECT')) define('OBJECT', 'OBJECT');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');

// Global $wpdb replacement
$wpdb = VT_Database::getInstance();
