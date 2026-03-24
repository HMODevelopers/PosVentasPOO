<?php
include_once __DIR__ . '/../includes/db.php';

class ConfigEmisoresModel {
    private PDO $conn;

    private array $fillable = [
        'id_sucursal','rfc_emisor','razon_social_emisor','regimen_fiscal_emisor','cp_expedicion',
        'tipo_comprobante','exportacion_default','moneda_default','objeto_imp_default',
        'serie','folio_actual','activo','es_default'
    ];

    public function __construct() {
        global $pdo;
        $this->conn = $pdo;
    }

    public function listar(int $pagina, int $limite, array $filtros): array {
        $offset = (max(1, $pagina) - 1) * max(1, $limite);
        $sql = "SELECT
                    cfe.id_config,
                    cfe.id_sucursal,
                    cfe.rfc_emisor,
                    cfe.razon_social_emisor,
                    cfe.regimen_fiscal_emisor,
                    cfe.cp_expedicion,
                    cfe.tipo_comprobante,
                    cfe.exportacion_default,
                    cfe.moneda_default,
                    cfe.objeto_imp_default,
                    cfe.serie,
                    cfe.folio_actual,
                    cfe.activo,
                    cfe.es_default,
                    cfe.created_at,
                    cfe.updated_at,
                    COALESCE(s.nombre, CAST(cfe.id_sucursal AS CHAR)) AS sucursal_nombre
                FROM config_fiscal_emisor cfe
                LEFT JOIN sucursales s ON s.id_sucursal = cfe.id_sucursal
                WHERE 1=1";
        $p = [];

        if (($idSucursal = (int)($filtros['id_sucursal'] ?? 0)) > 0) {
            $sql .= " AND cfe.id_sucursal = :id_sucursal";
            $p[':id_sucursal'] = $idSucursal;
        }
        if (($rfc = trim($filtros['rfc_emisor'] ?? '')) !== '') {
            $sql .= " AND cfe.rfc_emisor LIKE :rfc";
            $p[':rfc'] = '%' . strtoupper($rfc) . '%';
        }
        if (($razon = trim($filtros['razon_social_emisor'] ?? '')) !== '') {
            $sql .= " AND cfe.razon_social_emisor LIKE :razon";
            $p[':razon'] = '%' . $razon . '%';
        }
        if (($activo = $filtros['activo'] ?? '') !== '' && $activo !== null) {
            $sql .= " AND cfe.activo = :activo";
            $p[':activo'] = (int)$activo;
        }

        $sql .= " ORDER BY cfe.id_sucursal ASC, cfe.id_config DESC LIMIT :lim OFFSET :off";

        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', max(1, $limite), PDO::PARAM_INT);
        $st->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contar(array $filtros): int {
        $sql = "SELECT COUNT(*) total FROM config_fiscal_emisor cfe WHERE 1=1";
        $p = [];

        if (($idSucursal = (int)($filtros['id_sucursal'] ?? 0)) > 0) {
            $sql .= " AND cfe.id_sucursal = :id_sucursal";
            $p[':id_sucursal'] = $idSucursal;
        }
        if (($rfc = trim($filtros['rfc_emisor'] ?? '')) !== '') {
            $sql .= " AND cfe.rfc_emisor LIKE :rfc";
            $p[':rfc'] = '%' . strtoupper($rfc) . '%';
        }
        if (($razon = trim($filtros['razon_social_emisor'] ?? '')) !== '') {
            $sql .= " AND cfe.razon_social_emisor LIKE :razon";
            $p[':razon'] = '%' . $razon . '%';
        }
        if (($activo = $filtros['activo'] ?? '') !== '' && $activo !== null) {
            $sql .= " AND cfe.activo = :activo";
            $p[':activo'] = (int)$activo;
        }

        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function obtenerPorId(int $id): ?array {
        $st = $this->conn->prepare("SELECT * FROM config_fiscal_emisor WHERE id_config = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeRfcSucursal(int $idSucursal, string $rfc, int $exceptId = 0): bool {
        $sql = "SELECT COUNT(*) t FROM config_fiscal_emisor WHERE id_sucursal=:s AND rfc_emisor=:r";
        if ($exceptId > 0) {
            $sql .= " AND id_config<>:id";
        }
        $st = $this->conn->prepare($sql);
        $st->bindValue(':s', $idSucursal, PDO::PARAM_INT);
        $st->bindValue(':r', strtoupper($rfc));
        if ($exceptId > 0) {
            $st->bindValue(':id', $exceptId, PDO::PARAM_INT);
        }
        $st->execute();
        return ((int)($st->fetch(PDO::FETCH_ASSOC)['t'] ?? 0)) > 0;
    }

    public function crear(array $data): int {
        $d = $this->sanitizeData($data);
        $cols = implode(',', $this->fillable);
        $marks = ':' . implode(',:', $this->fillable);
        $st = $this->conn->prepare("INSERT INTO config_fiscal_emisor ({$cols}) VALUES ({$marks})");
        foreach ($this->fillable as $field) {
            $st->bindValue(':' . $field, $d[$field]);
        }
        $st->execute();
        $id = (int)$this->conn->lastInsertId();
        $this->syncDefaultEmitter((int)$d['id_sucursal'], (int)$d['es_default'], $id);
        return $id;
    }

    public function actualizar(int $id, array $data): bool {
        if (!$this->obtenerPorId($id)) {
            return false;
        }

        $d = $this->sanitizeData($data);
        $sets = [];
        foreach ($this->fillable as $f) {
            $sets[] = "{$f}=:{$f}";
        }

        $sql = "UPDATE config_fiscal_emisor SET " . implode(',', $sets) . " WHERE id_config=:id";
        $st = $this->conn->prepare($sql);
        foreach ($this->fillable as $field) {
            $st->bindValue(':' . $field, $d[$field]);
        }
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $ok = $st->execute();
        if ($ok) {
            $this->syncDefaultEmitter((int)$d['id_sucursal'], (int)$d['es_default'], $id);
        }
        return $ok;
    }

    public function toggle(int $id, int $activo): bool {
        $row = $this->obtenerPorId($id);
        if (!$row) {
            return false;
        }

        if ($activo) {
            $st = $this->conn->prepare("UPDATE config_fiscal_emisor SET activo = 1 WHERE id_config = :id");
            return $st->execute([':id' => $id]);
        }

        $this->conn->beginTransaction();
        try {
            $this->conn->prepare("UPDATE config_fiscal_emisor SET activo = 0, es_default = 0 WHERE id_config = :id")
                ->execute([':id' => $id]);

            $candidato = $this->conn->prepare("SELECT id_config FROM config_fiscal_emisor WHERE id_sucursal = :id_sucursal AND id_config <> :id_config AND activo = 1 ORDER BY es_default DESC, id_config DESC LIMIT 1");
            $candidato->execute([
                ':id_sucursal' => (int)$row['id_sucursal'],
                ':id_config' => $id,
            ]);
            $nuevoDefault = (int)($candidato->fetch(PDO::FETCH_ASSOC)['id_config'] ?? 0);
            if ($nuevoDefault > 0) {
                $this->conn->prepare("UPDATE config_fiscal_emisor SET es_default = CASE WHEN id_config = :id_default THEN 1 ELSE 0 END WHERE id_sucursal = :id_sucursal")
                    ->execute([':id_default' => $nuevoDefault, ':id_sucursal' => (int)$row['id_sucursal']]);
            }

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }


    public function setDefault(int $id): bool {
        $row = $this->obtenerPorId($id);
        if (!$row) {
            return false;
        }

        $this->conn->beginTransaction();
        try {
            $this->conn->prepare("UPDATE config_fiscal_emisor SET es_default = 0 WHERE id_sucursal = :id_sucursal")
                ->execute([':id_sucursal' => (int)$row['id_sucursal']]);
            $this->conn->prepare("UPDATE config_fiscal_emisor SET es_default = 1, activo = 1 WHERE id_config = :id_config")
                ->execute([':id_config' => $id]);
            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function getByVenta(int $idVenta): array {
        $sql = "SELECT c.id_sucursal
                FROM ventas v
                INNER JOIN cajas c ON c.id_caja = v.id_caja
                WHERE v.id_venta = :id LIMIT 1";
        $st = $this->conn->prepare($sql);
        $st->execute([':id' => $idVenta]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Venta no encontrada');
        }

        $stCfg = $this->conn->prepare("SELECT * FROM config_fiscal_emisor WHERE id_sucursal=:s AND activo=1 AND es_default=1 LIMIT 1");
        $stCfg->execute([':s' => (int)$row['id_sucursal']]);
        $cfg = $stCfg->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$cfg) {
            throw new RuntimeException('No existe emisor activo y default para la sucursal de la venta');
        }
        return $cfg;
    }

    public function listarSucursalesActivas(): array {
        $st = $this->conn->query("SELECT id_sucursal, nombre FROM sucursales WHERE activo=1 ORDER BY nombre ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function sanitizeData(array $data): array {
        $clean = [];
        foreach ($this->fillable as $f) {
            $v = $data[$f] ?? null;
            if (in_array($f, ['id_sucursal','folio_actual','activo','es_default'], true)) {
                $clean[$f] = (int)$v;
            } elseif ($f === 'rfc_emisor') {
                $clean[$f] = strtoupper(substr(trim((string)$v), 0, 13));
            } else {
                $clean[$f] = trim((string)$v);
            }
        }

        $clean['tipo_comprobante'] = strtoupper(substr($clean['tipo_comprobante'] ?: 'I', 0, 1));
        $clean['exportacion_default'] = substr($clean['exportacion_default'] ?: '01', 0, 2);
        $clean['moneda_default'] = strtoupper(substr($clean['moneda_default'] ?: 'MXN', 0, 3));
        $clean['objeto_imp_default'] = substr($clean['objeto_imp_default'] ?: '02', 0, 2);
        $clean['activo'] = $clean['activo'] ? 1 : 0;
        $clean['es_default'] = $clean['es_default'] ? 1 : 0;
        if ($clean['es_default'] === 1) {
            $clean['activo'] = 1;
        }
        return $clean;
    }

    private function syncDefaultEmitter(int $idSucursal, int $esDefault, int $idActual): void {
        if ($idSucursal <= 0 || $idActual <= 0) {
            return;
        }

        if ($esDefault === 1) {
            $st = $this->conn->prepare("UPDATE config_fiscal_emisor SET es_default = CASE WHEN id_config = :id_config THEN 1 ELSE 0 END WHERE id_sucursal = :id_sucursal");
            $st->execute([':id_config' => $idActual, ':id_sucursal' => $idSucursal]);
            return;
        }

        $st = $this->conn->prepare("SELECT COUNT(*) AS total FROM config_fiscal_emisor WHERE id_sucursal = :id_sucursal AND activo = 1 AND es_default = 1");
        $st->execute([':id_sucursal' => $idSucursal]);
        if ((int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) === 0) {
            $st = $this->conn->prepare("UPDATE config_fiscal_emisor SET es_default = 1 WHERE id_config = :id_config");
            $st->execute([':id_config' => $idActual]);
        }
    }
}
