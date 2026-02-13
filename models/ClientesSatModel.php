<?php
include_once __DIR__ . '/../includes/db.php';

class ClientesSatModel {
    private $conn;

    public function __construct() {
        global $pdo;
        $this->conn = $pdo;
    }

    public function listar(int $pagina = 1, int $limite = 10, array $f = []): array {
        $offset = (max(1, $pagina) - 1) * max(1, $limite);
        $sql = "SELECT id, nombre_comercial, rfc, razon_social, regimen_fiscal, numero_registro_tributario, uso_cdfi, telefono, celular, email, email_alterno, pais, dom_fiscal_cp, estado, municipio, localidad, colonia, calle, numero_exterior, numero_interior, referencia, residencia_fiscal,
                       CASE WHEN id IS NOT NULL THEN CONCAT('ID:', id) ELSE CONCAT('RFC:', rfc) END AS row_key
                FROM clientes_sat
                WHERE 1=1";
        $p = [];
        if (($rfc = trim($f['rfc'] ?? '')) !== '') {
            $sql .= " AND rfc LIKE :rfc";
            $p[':rfc'] = '%' . strtoupper($rfc) . '%';
        }
        if (($razon = trim($f['razon_social'] ?? '')) !== '') {
            $sql .= " AND razon_social LIKE :razon";
            $p[':razon'] = "%{$razon}%";
        }
        if (($cp = trim($f['dom_fiscal_cp'] ?? '')) !== '') {
            $sql .= " AND dom_fiscal_cp LIKE :cp";
            $p[':cp'] = "%{$cp}%";
        }
        $sql .= " ORDER BY id DESC, rfc ASC LIMIT :limite OFFSET :offset";
        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $st->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contar(array $f = []): int {
        $sql = "SELECT COUNT(*) AS total FROM clientes_sat WHERE 1=1";
        $p = [];
        if (($rfc = trim($f['rfc'] ?? '')) !== '') {
            $sql .= " AND rfc LIKE :rfc";
            $p[':rfc'] = '%' . strtoupper($rfc) . '%';
        }
        if (($razon = trim($f['razon_social'] ?? '')) !== '') {
            $sql .= " AND razon_social LIKE :razon";
            $p[':razon'] = "%{$razon}%";
        }
        if (($cp = trim($f['dom_fiscal_cp'] ?? '')) !== '') {
            $sql .= " AND dom_fiscal_cp LIKE :cp";
            $p[':cp'] = "%{$cp}%";
        }
        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function obtenerPorRowKey(string $rowKey): ?array {
        if (str_starts_with($rowKey, 'ID:')) {
            $id = (int)substr($rowKey, 3);
            $st = $this->conn->prepare("SELECT *, CASE WHEN id IS NOT NULL THEN CONCAT('ID:', id) ELSE CONCAT('RFC:', rfc) END AS row_key FROM clientes_sat WHERE id = :id LIMIT 1");
            $st->bindValue(':id', $id, PDO::PARAM_INT);
        } else {
            $rfc = strtoupper(trim(str_replace('RFC:', '', $rowKey)));
            $st = $this->conn->prepare("SELECT *, CASE WHEN id IS NOT NULL THEN CONCAT('ID:', id) ELSE CONCAT('RFC:', rfc) END AS row_key FROM clientes_sat WHERE rfc = :rfc LIMIT 1");
            $st->bindValue(':rfc', $rfc);
        }
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $d): bool {
        $sql = "INSERT INTO clientes_sat (id, nombre_comercial, rfc, razon_social, regimen_fiscal, numero_registro_tributario, uso_cdfi, telefono, celular, email, email_alterno, pais, dom_fiscal_cp, estado, municipio, localidad, colonia, calle, numero_exterior, numero_interior, referencia, residencia_fiscal)
                VALUES (:id, :nombre_comercial, :rfc, :razon_social, :regimen_fiscal, :numero_registro_tributario, :uso_cdfi, :telefono, :celular, :email, :email_alterno, :pais, :dom_fiscal_cp, :estado, :municipio, :localidad, :colonia, :calle, :numero_exterior, :numero_interior, :referencia, :residencia_fiscal)";
        $st = $this->conn->prepare($sql);
        return $st->execute($this->payload($d));
    }

    public function actualizar(string $rowKey, array $d): bool {
        $payload = $this->payload($d);
        $set = "id = :id, nombre_comercial = :nombre_comercial, rfc = :rfc, razon_social = :razon_social, regimen_fiscal = :regimen_fiscal,
                numero_registro_tributario = :numero_registro_tributario, uso_cdfi = :uso_cdfi, telefono = :telefono, celular = :celular, email = :email,
                email_alterno = :email_alterno, pais = :pais, dom_fiscal_cp = :dom_fiscal_cp, estado = :estado, municipio = :municipio, localidad = :localidad,
                colonia = :colonia, calle = :calle, numero_exterior = :numero_exterior, numero_interior = :numero_interior, referencia = :referencia,
                residencia_fiscal = :residencia_fiscal";

        if (str_starts_with($rowKey, 'ID:')) {
            $id = (int)substr($rowKey, 3);
            $sql = "UPDATE clientes_sat SET {$set} WHERE id = :where_id";
            $payload[':where_id'] = $id;
        } else {
            $rfc = strtoupper(trim(str_replace('RFC:', '', $rowKey)));
            $sql = "UPDATE clientes_sat SET {$set} WHERE rfc = :where_rfc";
            $payload[':where_rfc'] = $rfc;
        }

        $st = $this->conn->prepare($sql);
        return $st->execute($payload);
    }

    private function payload(array $d): array {
        return [
            ':id' => ($d['id'] ?? '') !== '' ? (int)$d['id'] : null,
            ':nombre_comercial' => $this->nullable($d['nombre_comercial'] ?? null),
            ':rfc' => $this->nullable(strtoupper(trim((string)($d['rfc'] ?? '')))),
            ':razon_social' => $this->nullable($d['razon_social'] ?? null),
            ':regimen_fiscal' => $this->nullable($d['regimen_fiscal'] ?? null),
            ':numero_registro_tributario' => $this->nullable($d['numero_registro_tributario'] ?? null),
            ':uso_cdfi' => $this->nullable($d['uso_cdfi'] ?? null),
            ':telefono' => $this->nullable($d['telefono'] ?? null),
            ':celular' => $this->nullable($d['celular'] ?? null),
            ':email' => $this->nullable($d['email'] ?? null),
            ':email_alterno' => $this->nullable($d['email_alterno'] ?? null),
            ':pais' => $this->nullable($d['pais'] ?? null),
            ':dom_fiscal_cp' => $this->nullable($d['dom_fiscal_cp'] ?? null),
            ':estado' => $this->nullable($d['estado'] ?? null),
            ':municipio' => $this->nullable($d['municipio'] ?? null),
            ':localidad' => $this->nullable($d['localidad'] ?? null),
            ':colonia' => $this->nullable($d['colonia'] ?? null),
            ':calle' => $this->nullable($d['calle'] ?? null),
            ':numero_exterior' => $this->nullable($d['numero_exterior'] ?? null),
            ':numero_interior' => $this->nullable($d['numero_interior'] ?? null),
            ':referencia' => $this->nullable($d['referencia'] ?? null),
            ':residencia_fiscal' => $this->nullable($d['residencia_fiscal'] ?? null),
        ];
    }

    private function nullable($v) {
        $v = is_string($v) ? trim($v) : $v;
        return $v === '' ? null : $v;
    }
}
