<?php
include_once __DIR__ . '/../includes/db.php';

class ConfigEmisoresModel {
    private PDO $conn;

    private array $fillable = [
        'id_sucursal','rfc_emisor','razon_social_emisor','regimen_fiscal_emisor','cp_expedicion',
        'tipo_comprobante','exportacion_default','moneda_default','objeto_imp_default',
        'serie','folio_actual','fd_ambiente','fd_usuario','fd_password','fd_url_demo','fd_url_prod',
        'csd_cer_path','csd_key_path','csd_key_password','pfx_path','pfx_password','logo_base64','activo'
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
                    cfe.fd_ambiente,
                    cfe.activo,
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
        if (($amb = trim($filtros['fd_ambiente'] ?? '')) !== '') {
            $sql .= " AND cfe.fd_ambiente = :amb";
            $p[':amb'] = strtoupper($amb);
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
        return (int)$this->conn->lastInsertId();
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
        return $st->execute();
    }

    public function toggle(int $id, int $activo): bool {
        $st = $this->conn->prepare("UPDATE config_fiscal_emisor SET activo=:a WHERE id_config=:id");
        return $st->execute([':a' => $activo ? 1 : 0, ':id' => $id]);
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

        $stCfg = $this->conn->prepare("SELECT * FROM config_fiscal_emisor WHERE id_sucursal=:s AND activo=1 LIMIT 1");
        $stCfg->execute([':s' => (int)$row['id_sucursal']]);
        $cfg = $stCfg->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$cfg) {
            throw new RuntimeException('No existe emisor activo para la sucursal de la venta');
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
            if (in_array($f, ['id_sucursal','folio_actual','activo'], true)) {
                $clean[$f] = (int)$v;
            } elseif ($f === 'rfc_emisor') {
                $clean[$f] = strtoupper(substr(trim((string)$v), 0, 13));
            } else {
                $clean[$f] = trim((string)$v);
            }
        }

        $clean['fd_ambiente'] = in_array(strtoupper($clean['fd_ambiente']), ['DEMO', 'PROD'], true)
            ? strtoupper($clean['fd_ambiente'])
            : 'DEMO';
        $clean['activo'] = $clean['activo'] ? 1 : 0;
        return $clean;
    }
}
