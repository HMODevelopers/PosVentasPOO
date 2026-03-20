<?php

class FacturacionSchemaHelper
{
    private PDO $conn;
    private array $columnsCache = [];
    private array $tableCache = [];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $st = $this->conn->prepare('SHOW TABLES LIKE :table');
            $st->execute([':table' => $table]);
            return $this->tableCache[$table] = (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return $this->tableCache[$table] = false;
        }
    }

    public function columns(string $table): array
    {
        if (isset($this->columnsCache[$table])) {
            return $this->columnsCache[$table];
        }

        if (!$this->tableExists($table)) {
            return $this->columnsCache[$table] = [];
        }

        $cols = [];
        $st = $this->conn->query("SHOW COLUMNS FROM `{$table}`");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[] = $row['Field'];
        }
        return $this->columnsCache[$table] = $cols;
    }

    public function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    public function pickColumn(string $table, array $candidates, bool $required = false): ?string
    {
        foreach ($candidates as $candidate) {
            if ($this->hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        if ($required) {
            throw new RuntimeException(sprintf(
                'No se encontró ninguna columna válida en %s para [%s].',
                $table,
                implode(', ', $candidates)
            ));
        }

        return null;
    }

    public function filterData(string $table, array $data): array
    {
        $valid = [];
        $cols = $this->columns($table);
        foreach ($data as $key => $value) {
            if (in_array($key, $cols, true)) {
                $valid[$key] = $value;
            }
        }
        return $valid;
    }

    public function rowValue(array $row, array $candidates, $default = null)
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $row) && $row[$candidate] !== null && $row[$candidate] !== '') {
                return $row[$candidate];
            }
        }
        return $default;
    }
}
