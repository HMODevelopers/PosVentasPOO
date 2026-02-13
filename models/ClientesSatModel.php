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
        $sql = "SELECT c.id, c.nombre_comercial, c.rfc, c.razon_social, c.regimen_fiscal, c.numero_registro_tributario, c.uso_cdfi, c.telefono, c.celular, c.email, c.email_alterno, c.pais, c.dom_fiscal_cp, c.estado, c.municipio, c.localidad, c.colonia, c.calle, c.numero_exterior, c.numero_interior, c.referencia, c.residencia_fiscal,
                       rf.Descripcion AS regimen_fiscal_descripcion,
                       uc.Descripcion AS uso_cfdi_descripcion,
                       CASE WHEN c.id IS NOT NULL THEN CONCAT('ID:', c.id) ELSE CONCAT('RFC:', c.rfc) END AS row_key
                FROM clientes_sat c
                LEFT JOIN cat_sat_regimen_fiscal rf ON rf.ClaveRegimenFiscal = c.regimen_fiscal
                LEFT JOIN cat_sat_uso_cfdi uc ON uc.ClaveUsoCFDI = c.uso_cdfi
                WHERE 1=1";
        $p = [];
        if (($rfc = trim($f['rfc'] ?? '')) !== '') {
            $sql .= " AND c.rfc LIKE :rfc";
            $p[':rfc'] = '%' . strtoupper($rfc) . '%';
        }
        if (($razon = trim($f['razon_social'] ?? '')) !== '') {
            $sql .= " AND c.razon_social LIKE :razon";
            $p[':razon'] = "%{$razon}%";
        }
        if (($cp = trim($f['dom_fiscal_cp'] ?? '')) !== '') {
            $sql .= " AND c.dom_fiscal_cp LIKE :cp";
            $p[':cp'] = "%{$cp}%";
        }
        $sql .= " ORDER BY c.id DESC, c.rfc ASC LIMIT :limite OFFSET :offset";
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
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) return null;

        $ub = $this->resolverUbicacionLegacy($row);
        return array_merge($row, $ub);
    }

    public function listarEntidades(): array {
        $st = $this->conn->query("SELECT cvegeo, cve_ent, nombre_ent FROM entidades ORDER BY cvegeo ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarMunicipios(string $cveEnt): array {
        $st = $this->conn->prepare("SELECT cvegeo, cve_ent, cve_mun, nombre_mun FROM municipios WHERE cve_ent = :cve_ent ORDER BY cvegeo ASC");
        $st->execute([':cve_ent' => $cveEnt]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarLocalidades(string $cveEnt, string $cveMun): array {
        $st = $this->conn->prepare("SELECT cvegeo, cve_ent, cve_mun, cve_loc, nombre_loc FROM localidades WHERE cve_ent = :cve_ent AND cve_mun = :cve_mun ORDER BY cvegeo ASC");
        $st->execute([':cve_ent' => $cveEnt, ':cve_mun' => $cveMun]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarRegimenes(): array {
        $st = $this->conn->query("SELECT ClaveRegimenFiscal, Descripcion FROM cat_sat_regimen_fiscal WHERE Activo = 1 ORDER BY ClaveRegimenFiscal ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarUsosCfdi(): array {
        $st = $this->conn->query("SELECT ClaveUsoCFDI, Descripcion FROM cat_sat_uso_cfdi WHERE Activo = 1 ORDER BY ClaveUsoCFDI ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
            ':estado' => $this->nullable($this->normalizeGeoCode($d['estado'] ?? null, 2)),
            ':municipio' => $this->nullable($this->normalizeGeoCode($d['municipio'] ?? null, 5)),
            ':localidad' => $this->nullable($this->normalizeGeoCode($d['localidad'] ?? null, 9)),
            ':colonia' => $this->nullable($d['colonia'] ?? null),
            ':calle' => $this->nullable($d['calle'] ?? null),
            ':numero_exterior' => $this->nullable($d['numero_exterior'] ?? null),
            ':numero_interior' => $this->nullable($d['numero_interior'] ?? null),
            ':referencia' => $this->nullable($d['referencia'] ?? null),
            ':residencia_fiscal' => $this->nullable($d['residencia_fiscal'] ?? null),
        ];
    }

    private function resolverUbicacionLegacy(array $row): array {
        $estado = $this->resolverClaveGeo($row['estado'] ?? '', 2, 'entidades', 'nombre_ent');
        $municipio = $this->resolverClaveGeo($row['municipio'] ?? '', 5, 'municipios', 'nombre_mun', [
            'cve_ent' => $estado['code'] ? substr($estado['code'], 0, 2) : null,
        ]);
        $localidad = $this->resolverClaveGeo($row['localidad'] ?? '', 9, 'localidades', 'nombre_loc', [
            'cve_ent' => $municipio['code'] ? substr($municipio['code'], 0, 2) : ($estado['code'] ? substr($estado['code'], 0, 2) : null),
            'cve_mun' => $municipio['code'] ? substr($municipio['code'], 2, 3) : null,
        ]);

        return [
            'estado_select' => $estado['code'],
            'municipio_select' => $municipio['code'],
            'localidad_select' => $localidad['code'],
            'estado_texto_fallback' => $estado['fallback'],
            'municipio_texto_fallback' => $municipio['fallback'],
            'localidad_texto_fallback' => $localidad['fallback'],
        ];
    }

    private function resolverClaveGeo($valor, int $len, string $tabla, string $colNombre, array $scope = []): array {
        $valor = trim((string)$valor);
        if ($valor === '') return ['code' => '', 'fallback' => ''];

        if (preg_match('/^\d{'.$len.'}$/', $valor)) {
            return ['code' => $valor, 'fallback' => ''];
        }

        if (preg_match('/(\d{'.$len.'})/', $valor, $m)) {
            return ['code' => $m[1], 'fallback' => ''];
        }

        $where = ["{$colNombre} LIKE :nombre"];
        $params = [':nombre' => '%' . $valor . '%'];
        foreach ($scope as $k => $v) {
            if ($v === null || $v === '') continue;
            $where[] = "{$k} = :{$k}";
            $params[":{$k}"] = $v;
        }

        $sql = "SELECT cvegeo FROM {$tabla} WHERE " . implode(' AND ', $where) . " ORDER BY cvegeo ASC LIMIT 1";
        $st = $this->conn->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['cvegeo'])) {
            return ['code' => $row['cvegeo'], 'fallback' => ''];
        }

        return ['code' => '', 'fallback' => $valor];
    }

    private function normalizeGeoCode($valor, int $len): ?string {
        $valor = trim((string)$valor);
        if ($valor === '') return null;
        if (preg_match('/^\d{'.$len.'}$/', $valor)) return $valor;
        if (preg_match('/(\d{'.$len.'})/', $valor, $m)) return $m[1];
        return $valor;
    }

    private function nullable($v) {
        $v = is_string($v) ? trim($v) : $v;
        return $v === '' ? null : $v;
    }
}
