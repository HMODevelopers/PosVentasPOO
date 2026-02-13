<?php
include_once __DIR__ . '/../includes/db.php';

class CatSatUsoCfdiModel {
    private $conn;

    public function __construct() {
        global $pdo;
        $this->conn = $pdo;
    }

    public function listar(int $pagina = 1, int $limite = 10, array $f = []): array {
        $offset = (max(1, $pagina) - 1) * max(1, $limite);
        $sql = "SELECT ClaveUsoCFDI, Descripcion, AplicaParaTipoPersonaFisica, AplicaParaTipoPersonaMoral, FechaInicio, FechaFin, Activo, FechaCreacion
                FROM cat_sat_uso_cfdi
                WHERE 1=1";
        $p = [];

        if (($clave = trim($f['ClaveUsoCFDI'] ?? '')) !== '') {
            $sql .= " AND ClaveUsoCFDI LIKE :clave";
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

        $sql .= " ORDER BY ClaveUsoCFDI ASC LIMIT :limite OFFSET :offset";
        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $st->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contar(array $f = []): int {
        $sql = "SELECT COUNT(*) AS total FROM cat_sat_uso_cfdi WHERE 1=1";
        $p = [];

        if (($clave = trim($f['ClaveUsoCFDI'] ?? '')) !== '') {
            $sql .= " AND ClaveUsoCFDI LIKE :clave";
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
        foreach ($p as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function obtenerPorId(string $claveUsoCFDI): ?array {
        $st = $this->conn->prepare("SELECT ClaveUsoCFDI, ClaveUsoCFDI AS OriginalClaveUsoCFDI, Descripcion, AplicaParaTipoPersonaFisica, AplicaParaTipoPersonaMoral, FechaInicio, FechaFin, Activo, FechaCreacion FROM cat_sat_uso_cfdi WHERE ClaveUsoCFDI = :clave LIMIT 1");
        $st->bindValue(':clave', strtoupper(trim($claveUsoCFDI)));
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $d): bool {
        $sql = "INSERT INTO cat_sat_uso_cfdi (ClaveUsoCFDI, Descripcion, AplicaParaTipoPersonaFisica, AplicaParaTipoPersonaMoral, FechaInicio, FechaFin, Activo)
                VALUES (:clave, :descripcion, :fisica, :moral, :fechaInicio, :fechaFin, :activo)";
        $st = $this->conn->prepare($sql);
        return $st->execute([
            ':clave' => strtoupper(trim($d['ClaveUsoCFDI'] ?? '')),
            ':descripcion' => trim($d['Descripcion'] ?? ''),
            ':fisica' => ($d['AplicaParaTipoPersonaFisica'] ?? '') !== '' ? trim($d['AplicaParaTipoPersonaFisica']) : null,
            ':moral' => ($d['AplicaParaTipoPersonaMoral'] ?? '') !== '' ? trim($d['AplicaParaTipoPersonaMoral']) : null,
            ':fechaInicio' => ($d['FechaInicio'] ?? '') !== '' ? $d['FechaInicio'] : null,
            ':fechaFin' => ($d['FechaFin'] ?? '') !== '' ? $d['FechaFin'] : null,
            ':activo' => isset($d['Activo']) ? (int)$d['Activo'] : 1,
        ]);
    }

    public function actualizar(string $originalClave, array $d): bool {
        $sql = "UPDATE cat_sat_uso_cfdi
                SET ClaveUsoCFDI = :clave, Descripcion = :descripcion, AplicaParaTipoPersonaFisica = :fisica,
                    AplicaParaTipoPersonaMoral = :moral, FechaInicio = :fechaInicio, FechaFin = :fechaFin, Activo = :activo
                WHERE ClaveUsoCFDI = :originalClave";
        $st = $this->conn->prepare($sql);
        return $st->execute([
            ':clave' => strtoupper(trim($d['ClaveUsoCFDI'] ?? '')),
            ':descripcion' => trim($d['Descripcion'] ?? ''),
            ':fisica' => ($d['AplicaParaTipoPersonaFisica'] ?? '') !== '' ? trim($d['AplicaParaTipoPersonaFisica']) : null,
            ':moral' => ($d['AplicaParaTipoPersonaMoral'] ?? '') !== '' ? trim($d['AplicaParaTipoPersonaMoral']) : null,
            ':fechaInicio' => ($d['FechaInicio'] ?? '') !== '' ? $d['FechaInicio'] : null,
            ':fechaFin' => ($d['FechaFin'] ?? '') !== '' ? $d['FechaFin'] : null,
            ':activo' => isset($d['Activo']) ? (int)$d['Activo'] : 1,
            ':originalClave' => strtoupper(trim($originalClave)),
        ]);
    }

    public function toggleActivo(string $claveUsoCFDI, int $activo): bool {
        $st = $this->conn->prepare("UPDATE cat_sat_uso_cfdi SET Activo = :activo WHERE ClaveUsoCFDI = :clave");
        return $st->execute([':activo' => $activo ? 1 : 0, ':clave' => strtoupper(trim($claveUsoCFDI))]);
    }

    public function listarMin(string $q = '', int $lim = 50): array {
        $sql = "SELECT ClaveUsoCFDI, Descripcion FROM cat_sat_uso_cfdi WHERE Activo = 1";
        $p = [];
        if ($q !== '') {
            $sql .= " AND (ClaveUsoCFDI LIKE :q1 OR Descripcion LIKE :q2)";
            $p[':q1'] = "%{$q}%";
            $p[':q2'] = "%{$q}%";
        }
        $sql .= " ORDER BY ClaveUsoCFDI ASC LIMIT :lim";
        $st = $this->conn->prepare($sql);
        foreach ($p as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', $lim, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
