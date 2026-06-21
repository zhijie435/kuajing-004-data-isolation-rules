<?php

namespace App\Core\Database;

use App\Core\Orm\TenantScope;

class QueryBuilder
{
    private string $table;
    private ?string $tableAlias = null;
    private array $columns = ['*'];
    private array $whereClauses = [];
    private array $params = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];

    private TenantScope $tenantScope;
    private bool $scopeApplied = false;

    public function __construct(string $table, ?string $alias = null)
    {
        $this->table = $table;
        $this->tableAlias = $alias;
        $this->tenantScope = new TenantScope();
    }

    public function select(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function where(string $column, $operator, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->whereClauses[] = "{$this->prefix($column)} {$operator} ?";
        $this->params[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            $this->whereClauses[] = '1 = 0';
            return $this;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->whereClauses[] = "{$this->prefix($column)} IN ({$placeholders})";
        $this->params = array_merge($this->params, $values);
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->whereClauses[] = "{$this->prefix($column)} IS NULL";
        return $this;
    }

    public function orderBy(string $column, string $dir = 'ASC'): self
    {
        $this->orderBy = "{$this->prefix($column)} {$dir}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    public function withoutTenantScope(): self
    {
        $this->tenantScope->ignoreTenant(true);
        return $this;
    }

    public function withoutDataScope(): self
    {
        $this->tenantScope->ignoreDataScope(true);
        return $this;
    }

    public function toSql(): string
    {
        $this->applyTenantScope();
        return $this->buildSql();
    }

    public function getParams(): array
    {
        $this->applyTenantScope();
        return $this->params;
    }

    public function debug(): array
    {
        return [
            'sql' => $this->toSql(),
            'params' => $this->getParams(),
            'scope' => $this->tenantScope->describe(),
        ];
    }

    private function applyTenantScope(): void
    {
        if ($this->scopeApplied) return;

        $alias = $this->tableAlias ?? $this->table;
        [$scopeWhere, $scopeParams] = $this->tenantScope->apply($alias);

        if ($scopeWhere) {
            array_unshift($this->whereClauses, $scopeWhere);
            $this->params = array_merge($scopeParams, $this->params);
        }

        $this->scopeApplied = true;
    }

    private function prefix(string $column): string
    {
        if (str_contains($column, '.') || str_contains($column, '(')) {
            return $column;
        }
        $alias = $this->tableAlias ?? $this->table;
        return "{$alias}.{$column}";
    }

    private function buildSql(): string
    {
        $cols = implode(', ', $this->columns);
        $tableRef = $this->tableAlias ? "{$this->table} {$this->tableAlias}" : $this->table;

        $sql = "SELECT {$cols} FROM {$tableRef}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->whereClauses);
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset !== null) {
                $sql .= " OFFSET {$this->offset}";
            }
        }

        return $sql;
    }
}
