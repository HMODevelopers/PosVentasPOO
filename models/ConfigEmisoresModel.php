<?php
include_once __DIR__ . '/../includes/db.php';

class ConfigEmisoresModel {
    private PDO $conn;
    private ?bool $hasNombreEmisor = null;
    private ?bool $hasEsDefault = null;
    private ?bool $hasSucursalesTable = null;
    private ?bool $hasSucursalNombre = null;

    private array $fillable = [
        'id_sucursal','nombre_emisor','rfc_emisor','razon_social_emisor','regimen_fiscal_emisor','cp_expedicion','serie','folio_actual',
        'tipo_comprobante','exportacion_default','moneda_default','objeto_imp_default','fd_ambiente','fd_usuario','fd_password',
        'fd_url_demo','fd_url_prod','csd_cer_path','csd_key_path','csd_key_password','pfx_path','pfx_password','logo_base64','es_default','activo'
    ];

    public function __construct() {
        global $pdo;
        $this->conn = $pdo;
    }

    public function listar(int $pagina, int $limite, array $filtros): array {
        $offset = (max(1, $pagina) - 1) * max(1, $limite);
        $hasNombreEmisor = $this->hasColumn('config_fiscal_emisor', 'nombre_emisor');
        $hasEsDefault = $this->hasColumn('config_fiscal_emisor', 'es_default');
        $hasSucursales = $this->hasTable('sucursales') && $this->hasColumn('sucursales', 'id_sucursal');
        $hasSucursalNombre = $hasSucursales && $this->hasColumn('sucursales', 'nombre');

        $nombreSelect = $hasNombreEmisor ? 'cfe.nombre_emisor' : "'' AS nombre_emisor";
        $defaultSelect = $hasEsDefault ? 'cfe.es_default' : '0 AS es_default';
        $sucursalSelect = $hasSucursalNombre ? 's.nombre AS sucursal_nombre' : 'CAST(cfe.id_sucursal AS CHAR) AS sucursal_nombre';

        $sql = "SELECT cfe.*, {$nombreSelect}, {$defaultSelect}, {$sucursalSelect}
                FROM config_fiscal_emisor cfe";
        if ($hasSucursales) {
            $sql .= " LEFT JOIN sucursales s ON s.id_sucursal = cfe.id_sucursal";
        }
        $sql .= " WHERE 1=1";
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
        if (($amb = trim($filtros['fd_ambiente'] ?? '')) !== '') {
            $sql .= " AND cfe.fd_ambiente = :amb";
            $p[':amb'] = strtoupper($amb);
        }
        if (($activo = $filtros['activo'] ?? '') !== '' && $activo !== null) {
            $sql .= " AND cfe.activo = :activo";
            $p[':activo'] = (int)$activo;
        }

        $sql .= " ORDER BY cfe.id_sucursal ASC";
        if ($hasEsDefault) {
            $sql .= ", cfe.es_default DESC";
        }
        $sql .= ", cfe.id_config_fiscal_emisor DESC LIMIT :lim OFFSET :off";
        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', max(1, $limite), PDO::PARAM_INT);
        $st->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function hasTable(string $table): bool {
        if ($table === 'sucursales' && $this->hasSucursalesTable !== null) {
            return $this->hasSucursalesTable;
        }

        $st = $this->conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        $st->execute([':table' => $table]);
        $exists = (bool)$st->fetchColumn();

        if ($table === 'sucursales') {
            $this->hasSucursalesTable = $exists;
        }

        return $exists;
    }

    private function hasColumn(string $table, string $column): bool {
        if ($table === 'config_fiscal_emisor' && $column === 'nombre_emisor' && $this->hasNombreEmisor !== null) {
            return $this->hasNombreEmisor;
        }
        if ($table === 'config_fiscal_emisor' && $column === 'es_default' && $this->hasEsDefault !== null) {
            return $this->hasEsDefault;
        }
        if ($table === 'sucursales' && $column === 'nombre' && $this->hasSucursalNombre !== null) {
            return $this->hasSucursalNombre;
        }

        $st = $this->conn->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1');
        $st->execute([':table' => $table, ':column' => $column]);
        $exists = (bool)$st->fetchColumn();

        if ($table === 'config_fiscal_emisor' && $column === 'nombre_emisor') {
            $this->hasNombreEmisor = $exists;
        } elseif ($table === 'config_fiscal_emisor' && $column === 'es_default') {
            $this->hasEsDefault = $exists;
        } elseif ($table === 'sucursales' && $column === 'nombre') {
            $this->hasSucursalNombre = $exists;
        }

        return $exists;
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
        if (($amb = trim($filtros['fd_ambiente'] ?? '')) !== '') {
            $sql .= " AND cfe.fd_ambiente = :amb";
            $p[':amb'] = strtoupper($amb);
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
        $st = $this->conn->prepare("SELECT * FROM config_fiscal_emisor WHERE id_config_fiscal_emisor = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existeRfcSucursal(int $idSucursal, string $rfc, int $exceptId = 0): bool {
        $sql = "SELECT COUNT(*) t FROM config_fiscal_emisor WHERE id_sucursal=:s AND rfc_emisor=:r";
        if ($exceptId > 0) {
            $sql .= " AND id_config_fiscal_emisor<>:id";
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
        $this->conn->beginTransaction();
        try {
            if ((int)$d['es_default'] === 1) {
                $this->clearDefaultBySucursal((int)$d['id_sucursal']);
            }

            $cols = implode(',', $this->fillable);
            $marks = ':' . implode(',:', $this->fillable);
            $st = $this->conn->prepare("INSERT INTO config_fiscal_emisor ({$cols}) VALUES ({$marks})");
            foreach ($this->fillable as $field) {
                $st->bindValue(':' . $field, $d[$field]);
            }
            $st->execute();
            $id = (int)$this->conn->lastInsertId();
            $this->conn->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): bool {
        $prev = $this->obtenerPorId($id);
        if (!$prev) return false;

        $d = $this->sanitizeData($data + ['id_sucursal' => $prev['id_sucursal']]);
        $this->conn->beginTransaction();
        try {
            if ((int)$d['es_default'] === 1) {
                $this->clearDefaultBySucursal((int)$d['id_sucursal']);
            }
            $sets = [];
            foreach ($this->fillable as $f) $sets[] = "{$f}=:{$f}";
            $sql = "UPDATE config_fiscal_emisor SET " . implode(',', $sets) . " WHERE id_config_fiscal_emisor=:id";
            $st = $this->conn->prepare($sql);
            foreach ($this->fillable as $field) {
                $st->bindValue(':' . $field, $d[$field]);
            }
            $st->bindValue(':id', $id, PDO::PARAM_INT);
            $ok = $st->execute();
            $this->conn->commit();
            return $ok;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function toggle(int $id, int $activo): bool {
        $this->conn->beginTransaction();
        try {
            $st = $this->conn->prepare("UPDATE config_fiscal_emisor SET activo=:a, es_default=IF(:a=0,0,es_default) WHERE id_config_fiscal_emisor=:id");
            $ok = $st->execute([':a' => $activo ? 1 : 0, ':id' => $id]);
            $this->conn->commit();
            return $ok;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function setDefault(int $id): bool {
        $row = $this->obtenerPorId($id);
        if (!$row || (int)$row['activo'] !== 1) {
            return false;
        }

        $this->conn->beginTransaction();
        try {
            $this->clearDefaultBySucursal((int)$row['id_sucursal']);
            $st = $this->conn->prepare("UPDATE config_fiscal_emisor SET es_default=1 WHERE id_config_fiscal_emisor=:id");
            $ok = $st->execute([':id' => $id]);
            $this->conn->commit();
            return $ok;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function getDefaultBySucursal(int $idSucursal): ?array {
        $st = $this->conn->prepare("SELECT * FROM config_fiscal_emisor WHERE id_sucursal=:s AND es_default=1 AND activo=1 LIMIT 1");
        $st->execute([':s' => $idSucursal]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
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
        $cfg = $this->getDefaultBySucursal((int)$row['id_sucursal']);
        if (!$cfg) {
            throw new RuntimeException('No existe emisor default activo para la sucursal de la venta');
        }
        return $cfg;
    }

    public function listarSucursalesActivas(): array {
        $st = $this->conn->query("SELECT id_sucursal, nombre FROM sucursales WHERE activo=1 ORDER BY nombre ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function clearDefaultBySucursal(int $idSucursal): void {
        $st = $this->conn->prepare("UPDATE config_fiscal_emisor SET es_default=0 WHERE id_sucursal=:s");
        $st->execute([':s' => $idSucursal]);
    }

    private function sanitizeData(array $data): array {
        $clean = [];
        foreach ($this->fillable as $f) {
            $v = $data[$f] ?? null;
            if (in_array($f, ['id_sucursal','folio_actual','es_default','activo'], true)) {
                $clean[$f] = (int)$v;
            } elseif ($f === 'rfc_emisor') {
                $clean[$f] = strtoupper(substr(trim((string)$v), 0, 13));
            } else {
                $clean[$f] = trim((string)$v);
            }
        }
        $clean['fd_ambiente'] = in_array(strtoupper($clean['fd_ambiente']), ['DEMO', 'PROD'], true) ? strtoupper($clean['fd_ambiente']) : 'DEMO';
        $clean['activo'] = $clean['activo'] ? 1 : 0;
        $clean['es_default'] = $clean['es_default'] ? 1 : 0;
        return $clean;
    }
}
