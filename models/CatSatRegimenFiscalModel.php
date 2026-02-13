<?php
include_once __DIR__ . '/../includes/db.php';

class CatSatRegimenFiscalModel {
    private $conn;

    public function __construct() {
        global $pdo;
        $this->conn = $pdo;
    }

    public function listar(int $pagina = 1, int $limite = 10, array $f = []): array {
        $offset = (max(1, $pagina) - 1) * max(1, $limite);
        $sql = "SELECT ClaveRegimenFiscal, Descripcion, Fisica, Moral, FechaInicio, FechaFin, Activo, FechaCreacion
                FROM cat_sat_regimen_fiscal
                WHERE 1=1";
        $p = [];
        if (($clave = trim($f['ClaveRegimenFiscal'] ?? '')) !== '') {
            $sql .= " AND ClaveRegimenFiscal LIKE :clave";
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
        $sql .= " ORDER BY ClaveRegimenFiscal ASC LIMIT :limite OFFSET :offset";
        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $st->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contar(array $f = []): int {
        $sql = "SELECT COUNT(*) AS total FROM cat_sat_regimen_fiscal WHERE 1=1";
        $p = [];
        if (($clave = trim($f['ClaveRegimenFiscal'] ?? '')) !== '') {
            $sql .= " AND ClaveRegimenFiscal LIKE :clave";
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
        $st = $this->conn->prepare("SELECT ClaveRegimenFiscal, ClaveRegimenFiscal AS OriginalClaveRegimenFiscal, Descripcion, Fisica, Moral, FechaInicio, FechaFin, Activo, FechaCreacion FROM cat_sat_regimen_fiscal WHERE ClaveRegimenFiscal = :clave LIMIT 1");
        $st->bindValue(':clave', strtoupper(trim($clave)));
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $d): bool {
        $st = $this->conn->prepare("INSERT INTO cat_sat_regimen_fiscal (ClaveRegimenFiscal, Descripcion, Fisica, Moral, FechaInicio, FechaFin, Activo) VALUES (:clave, :descripcion, :fisica, :moral, :fechaInicio, :fechaFin, :activo)");
        return $st->execute([
            ':clave' => strtoupper(trim($d['ClaveRegimenFiscal'] ?? '')),
            ':descripcion' => trim($d['Descripcion'] ?? ''),
            ':fisica' => ($d['Fisica'] ?? '') !== '' ? trim($d['Fisica']) : null,
            ':moral' => ($d['Moral'] ?? '') !== '' ? trim($d['Moral']) : null,
            ':fechaInicio' => ($d['FechaInicio'] ?? '') !== '' ? $d['FechaInicio'] : null,
            ':fechaFin' => ($d['FechaFin'] ?? '') !== '' ? $d['FechaFin'] : null,
            ':activo' => isset($d['Activo']) ? (int)$d['Activo'] : 1,
        ]);
    }

    public function actualizar(string $originalClave, array $d): bool {
        $st = $this->conn->prepare("UPDATE cat_sat_regimen_fiscal SET ClaveRegimenFiscal = :clave, Descripcion = :descripcion, Fisica = :fisica, Moral = :moral, FechaInicio = :fechaInicio, FechaFin = :fechaFin, Activo = :activo WHERE ClaveRegimenFiscal = :original");
        return $st->execute([
            ':clave' => strtoupper(trim($d['ClaveRegimenFiscal'] ?? '')),
            ':descripcion' => trim($d['Descripcion'] ?? ''),
            ':fisica' => ($d['Fisica'] ?? '') !== '' ? trim($d['Fisica']) : null,
            ':moral' => ($d['Moral'] ?? '') !== '' ? trim($d['Moral']) : null,
            ':fechaInicio' => ($d['FechaInicio'] ?? '') !== '' ? $d['FechaInicio'] : null,
            ':fechaFin' => ($d['FechaFin'] ?? '') !== '' ? $d['FechaFin'] : null,
            ':activo' => isset($d['Activo']) ? (int)$d['Activo'] : 1,
            ':original' => strtoupper(trim($originalClave)),
        ]);
    }

    public function toggleActivo(string $clave, int $activo): bool {
        $st = $this->conn->prepare("UPDATE cat_sat_regimen_fiscal SET Activo = :activo WHERE ClaveRegimenFiscal = :clave");
        return $st->execute([':activo' => $activo ? 1 : 0, ':clave' => strtoupper(trim($clave))]);
    }

    public function listarMin(string $q = '', int $lim = 50): array {
        $sql = "SELECT ClaveRegimenFiscal, Descripcion FROM cat_sat_regimen_fiscal WHERE Activo = 1";
        $p = [];
        if ($q !== '') {
            $sql .= " AND (ClaveRegimenFiscal LIKE :q1 OR Descripcion LIKE :q2)";
            $p[':q1'] = "%{$q}%";
            $p[':q2'] = "%{$q}%";
        }
        $sql .= " ORDER BY ClaveRegimenFiscal ASC LIMIT :lim";
        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $lim, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
