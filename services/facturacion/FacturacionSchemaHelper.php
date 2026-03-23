<?php

class FacturacionSchemaHelper
{
    private PDO $conn;
    private array $columnsCache = [];
    private array $tableCache = [];
    private ?string $databaseName = null;

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
            $database = $this->databaseName();
            if ($database === '') {
                return $this->tableCache[$table] = false;
            }

            $st = $this->conn->prepare(
                'SELECT 1
                   FROM information_schema.tables
                  WHERE table_schema = :database
                    AND table_name = :table
                  LIMIT 1'
            );
            $st->execute([
                ':database' => $database,
                ':table' => $table,
            ]);
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

        try {
            $database = $this->databaseName();
            if ($database === '') {
                return $this->columnsCache[$table] = [];
            }

            $st = $this->conn->prepare(
                'SELECT COLUMN_NAME
                   FROM information_schema.columns
                  WHERE table_schema = :database
                    AND table_name = :table
                  ORDER BY ORDINAL_POSITION ASC'
            );
            $st->execute([
                ':database' => $database,
                ':table' => $table,
            ]);

            $cols = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cols[] = $row['COLUMN_NAME'];
            }
            return $this->columnsCache[$table] = $cols;
        } catch (Throwable $e) {
            return $this->columnsCache[$table] = [];
        }
    }

    private function databaseName(): string
    {
        if ($this->databaseName !== null) {
            return $this->databaseName;
        }

        try {
            $name = $this->conn->query('SELECT DATABASE()')->fetchColumn();
            $this->databaseName = is_string($name) ? $name : '';
        } catch (Throwable $e) {
            $this->databaseName = '';
        }

        return $this->databaseName;
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
