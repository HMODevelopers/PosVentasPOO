<?php
include_once __DIR__ . '/../includes/db.php';

class CatSatMonedaModel {
    private $conn;

    public function __construct() {
        global $pdo;
        $this->conn = $pdo;
    }

    public function listar(int $pagina = 1, int $limite = 10, array $f = []): array {
        $offset = (max(1, $pagina) - 1) * max(1, $limite);
        $sql = "SELECT ClaveMoneda, Descripcion, Decimales, PermiteTipoCambio, FechaInicio, FechaFin, Simbolo, Activo, FechaCreacion
                FROM cat_sat_moneda
                WHERE 1=1";
        $p = [];
        if (($clave = trim($f['ClaveMoneda'] ?? '')) !== '') {
            $sql .= " AND ClaveMoneda LIKE :clave";
            $p[':clave'] = '%' . strtoupper($clave) . '%';
        }
        if (($descripcion = trim($f['Descripcion'] ?? '')) !== '') {
            $sql .= " AND Descripcion LIKE :descripcion";
            $p[':descripcion'] = '%' . $descripcion . '%';
        }
        if (isset($f['Activo']) && $f['Activo'] !== '') {
            $sql .= " AND Activo = :activo";
            $p[':activo'] = (int)$f['Activo'];
        }

        $sql .= " ORDER BY ClaveMoneda ASC LIMIT :limite OFFSET :offset";
        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $st->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contar(array $f = []): int {
        $sql = "SELECT COUNT(*) AS total FROM cat_sat_moneda WHERE 1=1";
        $p = [];
        if (($clave = trim($f['ClaveMoneda'] ?? '')) !== '') {
            $sql .= " AND ClaveMoneda LIKE :clave";
            $p[':clave'] = '%' . strtoupper($clave) . '%';
        }
        if (($descripcion = trim($f['Descripcion'] ?? '')) !== '') {
            $sql .= " AND Descripcion LIKE :descripcion";
            $p[':descripcion'] = '%' . $descripcion . '%';
        }
        if (isset($f['Activo']) && $f['Activo'] !== '') {
            $sql .= " AND Activo = :activo";
            $p[':activo'] = (int)$f['Activo'];
        }

        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function obtenerPorId(string $clave): ?array {
        $st = $this->conn->prepare("SELECT ClaveMoneda, ClaveMoneda AS OriginalClaveMoneda, Descripcion, Decimales, PermiteTipoCambio, FechaInicio, FechaFin, Simbolo, Activo, FechaCreacion FROM cat_sat_moneda WHERE ClaveMoneda = :clave LIMIT 1");
        $st->bindValue(':clave', strtoupper(trim($clave)));
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $d): bool {
        $st = $this->conn->prepare("INSERT INTO cat_sat_moneda (ClaveMoneda, Descripcion, Decimales, PermiteTipoCambio, FechaInicio, FechaFin, Simbolo, Activo) VALUES (:clave, :descripcion, :decimales, :permite, :fechaInicio, :fechaFin, :simbolo, :activo)");
        return $st->execute([
            ':clave' => strtoupper(trim($d['ClaveMoneda'] ?? '')),
            ':descripcion' => trim($d['Descripcion'] ?? ''),
            ':decimales' => ($d['Decimales'] ?? '') !== '' ? (int)$d['Decimales'] : null,
            ':permite' => ($d['PermiteTipoCambio'] ?? '') !== '' ? trim($d['PermiteTipoCambio']) : null,
            ':fechaInicio' => ($d['FechaInicio'] ?? '') !== '' ? $d['FechaInicio'] : null,
            ':fechaFin' => ($d['FechaFin'] ?? '') !== '' ? $d['FechaFin'] : null,
            ':simbolo' => ($d['Simbolo'] ?? '') !== '' ? trim($d['Simbolo']) : null,
            ':activo' => isset($d['Activo']) ? (int)$d['Activo'] : 1,
        ]);
    }

    public function actualizar(string $originalClave, array $d): bool {
        $st = $this->conn->prepare("UPDATE cat_sat_moneda SET ClaveMoneda = :clave, Descripcion = :descripcion, Decimales = :decimales, PermiteTipoCambio = :permite, FechaInicio = :fechaInicio, FechaFin = :fechaFin, Simbolo = :simbolo, Activo = :activo WHERE ClaveMoneda = :original");
        return $st->execute([
            ':clave' => strtoupper(trim($d['ClaveMoneda'] ?? '')),
            ':descripcion' => trim($d['Descripcion'] ?? ''),
            ':decimales' => ($d['Decimales'] ?? '') !== '' ? (int)$d['Decimales'] : null,
            ':permite' => ($d['PermiteTipoCambio'] ?? '') !== '' ? trim($d['PermiteTipoCambio']) : null,
            ':fechaInicio' => ($d['FechaInicio'] ?? '') !== '' ? $d['FechaInicio'] : null,
            ':fechaFin' => ($d['FechaFin'] ?? '') !== '' ? $d['FechaFin'] : null,
            ':simbolo' => ($d['Simbolo'] ?? '') !== '' ? trim($d['Simbolo']) : null,
            ':activo' => isset($d['Activo']) ? (int)$d['Activo'] : 1,
            ':original' => strtoupper(trim($originalClave)),
        ]);
    }

    public function toggleActivo(string $clave, int $activo): bool {
        $st = $this->conn->prepare("UPDATE cat_sat_moneda SET Activo = :activo WHERE ClaveMoneda = :clave");
        return $st->execute([':activo' => $activo ? 1 : 0, ':clave' => strtoupper(trim($clave))]);
    }

    public function listarMin(string $q = '', int $lim = 50): array {
        $sql = "SELECT ClaveMoneda, Descripcion, Decimales FROM cat_sat_moneda WHERE Activo = 1";
        $p = [];
        if ($q !== '') {
            $sql .= " AND (ClaveMoneda LIKE :q1 OR Descripcion LIKE :q2)";
            $p[':q1'] = "%{$q}%";
            $p[':q2'] = "%{$q}%";
        }
        $sql .= " ORDER BY ClaveMoneda ASC LIMIT :lim";
        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $lim, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
