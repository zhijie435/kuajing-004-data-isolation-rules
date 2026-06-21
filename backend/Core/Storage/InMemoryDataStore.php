<?php

namespace App\Core\Storage;

class InMemoryDataStore
{
    private static ?self $instance = null;

    private array $data = [];
    private array $initialData = [];
    private int $autoIncrement = 1100;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function initTable(string $table, array $initialRows): void
    {
        if (!isset($this->data[$table])) {
            $this->data[$table] = [];
            foreach ($initialRows as $row) {
                $id = $row['id'] ?? null;
                if ($id !== null) {
                    $this->data[$table][$id] = $row;
                    if ($id >= $this->autoIncrement) {
                        $this->autoIncrement = $id + 1;
                    }
                }
            }
            $this->initialData[$table] = $initialRows;
        }
    }

    public function all(string $table): array
    {
        return $this->data[$table] ?? [];
    }

    public function find(string $table, int $id): ?array
    {
        return $this->data[$table][$id] ?? null;
    }

    public function insert(string $table, array $row): array
    {
        if (!isset($row['id'])) {
            $row['id'] = $this->nextId();
        }
        $id = $row['id'];
        $this->data[$table][$id] = $row;
        return $this->data[$table][$id];
    }

    public function update(string $table, int $id, array $updates): ?array
    {
        if (!isset($this->data[$table][$id])) {
            return null;
        }
        $this->data[$table][$id] = array_merge($this->data[$table][$id], $updates);
        return $this->data[$table][$id];
    }

    public function delete(string $table, int $id): bool
    {
        if (!isset($this->data[$table][$id])) {
            return false;
        }
        unset($this->data[$table][$id]);
        return true;
    }

    public function count(string $table): int
    {
        return count($this->data[$table] ?? []);
    }

    public function nextId(): int
    {
        return $this->autoIncrement++;
    }

    public function resetTable(string $table): void
    {
        if (isset($this->initialData[$table])) {
            $this->data[$table] = [];
            foreach ($this->initialData[$table] as $row) {
                $id = $row['id'] ?? null;
                if ($id !== null) {
                    $this->data[$table][$id] = $row;
                }
            }
        }
    }

    public function resetAll(): void
    {
        foreach ($this->initialData as $table => $rows) {
            $this->resetTable($table);
        }
    }
}
