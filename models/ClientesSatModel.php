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
        $sql = "SELECT c.id, c.nombre_comercial, c.rfc, c.razon_social, c.regimen_fiscal, c.numero_registro_tributario, c.uso_cdfi, c.uso_cdfi AS uso_cfdi, c.telefono, c.celular, c.email, c.email_alterno, c.pais, c.dom_fiscal_cp, c.estado, c.municipio, c.localidad, c.colonia, c.calle, c.numero_exterior, c.numero_interior, c.referencia, c.residencia_fiscal,
                       rf.Descripcion AS regimen_fiscal_descripcion,
                       uc.Descripcion AS uso_cfdi_descripcion,
                       CASE WHEN c.estado REGEXP '^[0-9]{2}$' THEN e.nombre_ent ELSE NULL END AS estado_nombre,
                       CASE WHEN c.estado REGEXP '^[0-9]{2}$' AND c.municipio REGEXP '^[0-9]{3}$' THEN m.nombre_mun ELSE NULL END AS municipio_nombre,
                       CASE WHEN c.estado REGEXP '^[0-9]{2}$' AND c.municipio REGEXP '^[0-9]{3}$' AND c.localidad REGEXP '^[0-9]{4}$' THEN l.nombre_loc ELSE NULL END AS localidad_nombre,
                       COALESCE(CASE WHEN c.estado REGEXP '^[0-9]{2}$' THEN e.nombre_ent END, c.estado) AS estado_display,
                       COALESCE(CASE WHEN c.estado REGEXP '^[0-9]{2}$' AND c.municipio REGEXP '^[0-9]{3}$' THEN m.nombre_mun END, c.municipio) AS municipio_display,
                       COALESCE(CASE WHEN c.estado REGEXP '^[0-9]{2}$' AND c.municipio REGEXP '^[0-9]{3}$' AND c.localidad REGEXP '^[0-9]{4}$' THEN l.nombre_loc END, c.localidad) AS localidad_display,
                       CASE WHEN c.id IS NOT NULL THEN CONCAT('ID:', c.id) ELSE CONCAT('RFC:', c.rfc) END AS row_key
                FROM clientes_sat c
                LEFT JOIN cat_sat_regimen_fiscal rf ON rf.ClaveRegimenFiscal = c.regimen_fiscal
                LEFT JOIN cat_sat_uso_cfdi uc ON uc.ClaveUsoCFDI = c.uso_cdfi
                LEFT JOIN entidades e
                  ON c.estado REGEXP '^[0-9]{2}$'
                 AND e.cve_ent = c.estado
                LEFT JOIN municipios m
                  ON c.estado REGEXP '^[0-9]{2}$'
                 AND c.municipio REGEXP '^[0-9]{3}$'
                 AND m.cve_ent = c.estado
                 AND m.cve_mun = c.municipio
                LEFT JOIN localidades l
                  ON c.estado REGEXP '^[0-9]{2}$'
                 AND c.municipio REGEXP '^[0-9]{3}$'
                 AND c.localidad REGEXP '^[0-9]{4}$'
                 AND l.cve_ent = c.estado
                 AND l.cve_mun = c.municipio
                 AND l.cve_loc = c.localidad
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
            $st = $this->conn->prepare("SELECT *, uso_cdfi AS uso_cfdi, CASE WHEN id IS NOT NULL THEN CONCAT('ID:', id) ELSE CONCAT('RFC:', rfc) END AS row_key FROM clientes_sat WHERE id = :id LIMIT 1");
            $st->bindValue(':id', $id, PDO::PARAM_INT);
        } else {
            $rfc = strtoupper(trim(str_replace('RFC:', '', $rowKey)));
            $st = $this->conn->prepare("SELECT *, uso_cdfi AS uso_cfdi, CASE WHEN id IS NOT NULL THEN CONCAT('ID:', id) ELSE CONCAT('RFC:', rfc) END AS row_key FROM clientes_sat WHERE rfc = :rfc LIMIT 1");
            $st->bindValue(':rfc', $rfc);
        }
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) return null;

        $ub = $this->resolverUbicacionLegacy($row);
        return array_merge($row, $ub);
    }

    public function obtenerDetallePorId(int $id): ?array {
        $sql = "SELECT c.*,
                       c.uso_cdfi AS uso_cfdi,
                       rf.Descripcion AS regimen_fiscal_descripcion,
                       uc.Descripcion AS uso_cfdi_descripcion,
                       COALESCE(CASE WHEN c.estado REGEXP '^[0-9]{2}$' THEN e.nombre_ent END, c.estado) AS estado_display,
                       COALESCE(CASE WHEN c.estado REGEXP '^[0-9]{2}$' AND c.municipio REGEXP '^[0-9]{3}$' THEN m.nombre_mun END, c.municipio) AS municipio_display,
                       COALESCE(CASE WHEN c.estado REGEXP '^[0-9]{2}$' AND c.municipio REGEXP '^[0-9]{3}$' AND c.localidad REGEXP '^[0-9]{4}$' THEN l.nombre_loc END, c.localidad) AS localidad_display
                FROM clientes_sat c
                LEFT JOIN cat_sat_regimen_fiscal rf ON rf.ClaveRegimenFiscal = c.regimen_fiscal
                LEFT JOIN cat_sat_uso_cfdi uc ON uc.ClaveUsoCFDI = c.uso_cdfi
                LEFT JOIN entidades e
                  ON c.estado REGEXP '^[0-9]{2}$'
                 AND e.cve_ent = c.estado
                LEFT JOIN municipios m
                  ON c.estado REGEXP '^[0-9]{2}$'
                 AND c.municipio REGEXP '^[0-9]{3}$'
                 AND m.cve_ent = c.estado
                 AND m.cve_mun = c.municipio
                LEFT JOIN localidades l
                  ON c.estado REGEXP '^[0-9]{2}$'
                 AND c.municipio REGEXP '^[0-9]{3}$'
                 AND c.localidad REGEXP '^[0-9]{4}$'
                 AND l.cve_ent = c.estado
                 AND l.cve_mun = c.municipio
                 AND l.cve_loc = c.localidad
                WHERE c.id = :id
                LIMIT 1";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listarEntidades(): array {
        $st = $this->conn->query("SELECT cve_ent, nombre_ent FROM entidades ORDER BY cve_ent");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarMunicipios(string $cveEnt): array {
        $st = $this->conn->prepare("SELECT cve_mun, nombre_mun FROM municipios WHERE cve_ent = :cve_ent ORDER BY cve_mun");
        $st->execute([':cve_ent' => $cveEnt]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarLocalidades(string $cveEnt, string $cveMun): array {
        $st = $this->conn->prepare("SELECT cve_loc, nombre_loc FROM localidades WHERE cve_ent = :cve_ent AND cve_mun = :cve_mun ORDER BY cve_loc");
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
            ':uso_cdfi' => $this->nullable($d['uso_cdfi'] ?? ($d['uso_cfdi'] ?? null)),
            ':telefono' => $this->nullable($d['telefono'] ?? null),
            ':celular' => $this->nullable($d['celular'] ?? null),
            ':email' => $this->nullable($d['email'] ?? null),
            ':email_alterno' => $this->nullable($d['email_alterno'] ?? null),
            ':pais' => 'MEX',
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

    private function resolverUbicacionLegacy(array $row): array {
        $estado = $this->resolverEstadoLegacy($row['estado'] ?? '');
        $municipio = $this->resolverMunicipioLegacy($row['municipio'] ?? '', $estado['code']);
        $localidad = $this->resolverLocalidadLegacy($row['localidad'] ?? '', $estado['code'], $municipio['code']);

        return [
            'estado_select' => $estado['code'],
            'municipio_select' => $municipio['code'],
            'localidad_select' => $localidad['code'],
            'estado_texto_fallback' => $estado['fallback'],
            'municipio_texto_fallback' => $municipio['fallback'],
            'localidad_texto_fallback' => $localidad['fallback'],
        ];
    }

    private function resolverEstadoLegacy($valor): array {
        $valor = trim((string)$valor);
        if ($valor === '') return ['code' => '', 'fallback' => ''];

        $rawUpper = mb_strtoupper($valor, 'UTF-8');
        if ($rawUpper === 'SON' || $rawUpper === 'SONORA') {
            return ['code' => '26', 'fallback' => ''];
        }

        $codigo = $this->extractCode($valor, 2);
        if ($codigo !== '') {
            return ['code' => $codigo, 'fallback' => ''];
        }

        $st = $this->conn->prepare("SELECT cve_ent FROM entidades WHERE UPPER(nombre_ent) = UPPER(:nombre) OR UPPER(nombre_abr) = UPPER(:nombre) ORDER BY cve_ent ASC LIMIT 1");
        $st->execute([':nombre' => $valor]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['cve_ent'])) {
            return ['code' => str_pad((string)$row['cve_ent'], 2, '0', STR_PAD_LEFT), 'fallback' => ''];
        }

        return ['code' => '', 'fallback' => $valor];
    }

    private function resolverMunicipioLegacy($valor, string $cveEnt): array {
        $valor = trim((string)$valor);
        if ($valor === '') return ['code' => '', 'fallback' => ''];

        $codigo = $this->extractCode($valor, 3);
        if ($codigo !== '') {
            return ['code' => $codigo, 'fallback' => ''];
        }

        if ($cveEnt !== '') {
            $st = $this->conn->prepare("SELECT cve_mun FROM municipios WHERE cve_ent = :cve_ent AND UPPER(nombre_mun) = UPPER(:nombre) ORDER BY cve_mun ASC LIMIT 1");
            $st->execute([':cve_ent' => $cveEnt, ':nombre' => $valor]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $st = $this->conn->prepare("SELECT cve_mun FROM municipios WHERE cve_ent = :cve_ent AND UPPER(nombre_mun) LIKE UPPER(:nombre) ORDER BY cve_mun ASC LIMIT 1");
                $st->execute([':cve_ent' => $cveEnt, ':nombre' => '%' . $valor . '%']);
                $row = $st->fetch(PDO::FETCH_ASSOC);
            }
            if (!empty($row['cve_mun'])) {
                return ['code' => str_pad((string)$row['cve_mun'], 3, '0', STR_PAD_LEFT), 'fallback' => ''];
            }
        }

        return ['code' => '', 'fallback' => $valor];
    }

    private function resolverLocalidadLegacy($valor, string $cveEnt, string $cveMun): array {
        $valor = trim((string)$valor);
        if ($valor === '') return ['code' => '', 'fallback' => ''];

        $codigo = $this->extractCode($valor, 4);
        if ($codigo !== '') {
            return ['code' => $codigo, 'fallback' => ''];
        }

        if ($cveEnt !== '' && $cveMun !== '') {
            $st = $this->conn->prepare("SELECT cve_loc FROM localidades WHERE cve_ent = :cve_ent AND cve_mun = :cve_mun AND UPPER(nombre_loc) = UPPER(:nombre) ORDER BY cve_loc ASC LIMIT 1");
            $st->execute([':cve_ent' => $cveEnt, ':cve_mun' => $cveMun, ':nombre' => $valor]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $st = $this->conn->prepare("SELECT cve_loc FROM localidades WHERE cve_ent = :cve_ent AND cve_mun = :cve_mun AND UPPER(nombre_loc) LIKE UPPER(:nombre) ORDER BY cve_loc ASC LIMIT 1");
                $st->execute([':cve_ent' => $cveEnt, ':cve_mun' => $cveMun, ':nombre' => '%' . $valor . '%']);
                $row = $st->fetch(PDO::FETCH_ASSOC);
            }
            if (!empty($row['cve_loc'])) {
                return ['code' => str_pad((string)$row['cve_loc'], 4, '0', STR_PAD_LEFT), 'fallback' => ''];
            }
        }

        return ['code' => '', 'fallback' => $valor];
    }

    private function extractCode(string $valor, int $len): string {
        if (preg_match('/^(\d{1,' . $len . '})\s*(?:-|$)/', $valor, $m)) {
            return str_pad($m[1], $len, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^\d{'.$len.'}$/', $valor)) {
            return $valor;
        }
        if (preg_match('/(\d{1,'.$len.'})(?!\d)/', $valor, $m)) {
            return str_pad($m[1], $len, '0', STR_PAD_LEFT);
        }
        return '';
    }

    private function nullable($v) {
        $v = is_string($v) ? trim($v) : $v;
        return $v === '' ? null : $v;
    }
}
