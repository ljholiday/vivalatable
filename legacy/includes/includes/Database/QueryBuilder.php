<?php
/**
 * Query Builder for Complex Queries
 * Replaces direct SQL construction throughout codebase
 */

class VT_Database_QueryBuilder {

    private VT_Database_Connection $connection;
    private array $select = [];
    private string $from = '';
    private array $joins = [];
    private array $where = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $params = [];

    public function __construct(VT_Database_Connection $connection) {
        $this->connection = $connection;
    }

    /**
     * Select columns
     */
    public function select(array $columns = ['*']): self {
        $this->select = $columns;
        return $this;
    }

    /**
     * Set table with prefix
     */
    public function from(string $table): self {
        $this->from = $this->connection->getPrefix() . $table;
        return $this;
    }

    /**
     * Add JOIN clause
     */
    public function join(string $table, string $condition, string $type = 'INNER'): self {
        $prefixedTable = $this->connection->getPrefix() . $table;
        $this->joins[] = "$type JOIN $prefixedTable ON $condition";
        return $this;
    }

    /**
     * Add LEFT JOIN
     */
    public function leftJoin(string $table, string $condition): self {
        return $this->join($table, $condition, 'LEFT');
    }

    /**
     * Add WHERE condition
     */
    public function where(string $column, $value, string $operator = '='): self {
        $paramKey = $this->getParamKey($column);
        $this->where[] = "$column $operator :$paramKey";
        $this->params[$paramKey] = $value;
        return $this;
    }

    /**
     * Add WHERE IN condition
     */
    public function whereIn(string $column, array $values): self {
        if (empty($values)) {
            $this->where[] = '1=0'; // No matches
            return $this;
        }

        $placeholders = [];
        foreach ($values as $index => $value) {
            $paramKey = $this->getParamKey($column . '_' . $index);
            $placeholders[] = ":$paramKey";
            $this->params[$paramKey] = $value;
        }

        $this->where[] = "$column IN (" . implode(', ', $placeholders) . ')';
        return $this;
    }

    /**
     * Add WHERE NULL condition
     */
    public function whereNull(string $column): self {
        $this->where[] = "$column IS NULL";
        return $this;
    }

    /**
     * Add WHERE NOT NULL condition
     */
    public function whereNotNull(string $column): self {
        $this->where[] = "$column IS NOT NULL";
        return $this;
    }

    /**
     * Add raw WHERE condition
     */
    public function whereRaw(string $condition, array $params = []): self {
        $this->where[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * Add ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): self {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    /**
     * Set LIMIT
     */
    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set OFFSET
     */
    public function offset(int $offset): self {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Execute and fetch all results
     */
    public function get(): array {
        $query = $this->buildSelectQuery();
        return $this->connection->fetchAll($query, $this->params);
    }

    /**
     * Execute and fetch first result
     */
    public function first(): ?object {
        $originalLimit = $this->limit;
        $this->limit(1);
        $query = $this->buildSelectQuery();
        $this->limit = $originalLimit; // Restore original limit
        return $this->connection->fetchRow($query, $this->params);
    }

    /**
     * Count results
     */
    public function count(): int {
        $originalSelect = $this->select;
        $this->select = ['COUNT(*) as count'];
        $query = $this->buildSelectQuery();
        $this->select = $originalSelect; // Restore original select

        $result = $this->connection->fetchRow($query, $this->params);
        return (int) $result->count;
    }

    /**
     * Check if any results exist
     */
    public function exists(): bool {
        return $this->count() > 0;
    }

    /**
     * Build SELECT query
     */
    private function buildSelectQuery(): string {
        $query = 'SELECT ' . implode(', ', $this->select);
        $query .= ' FROM ' . $this->from;

        if (!empty($this->joins)) {
            $query .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->where)) {
            $query .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if (!empty($this->orderBy)) {
            $query .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $query .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $query .= ' OFFSET ' . $this->offset;
        }

        return $query;
    }

    /**
     * Generate unique parameter key
     */
    private function getParamKey(string $base): string {
        $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $base);
        $counter = 1;
        while (isset($this->params[$key])) {
            $key = $base . '_' . $counter;
            $counter++;
        }
        return $key;
    }

    /**
     * Reset query builder for reuse
     */
    public function reset(): self {
        $this->select = [];
        $this->from = '';
        $this->joins = [];
        $this->where = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;
        $this->params = [];
        return $this;
    }
}