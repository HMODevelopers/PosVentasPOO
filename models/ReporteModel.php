<?php
// models/ReporteModel.php
include_once '../includes/db.php';

class ReporteModel {
    /** @var PDO */
    private $conn;

    public function __construct() {
        global $pdo;
        $this->conn = $pdo;
        try { $this->conn->exec("SET time_zone = '-07:00'"); } catch (\Throwable $th) {}
    }

    /** Rango de fecha index-friendly */
    private function rangoFechas(string $desde = '', string $hasta = ''): array {
        if ($desde === '' && $hasta === '') { $desde = $hasta = date('Y-m-d'); }
        if ($desde === '' && $hasta !== '') $desde = $hasta;
        if ($hasta === '' && $desde !== '') $hasta = $desde;
        $ini = $desde.' 00:00:00';
        $fin = date('Y-m-d', strtotime($hasta.' +1 day')).' 00:00:00';
        return [$ini, $fin];
    }

    /**
     * LISTADO (paginado) — una fila por renglón de producto
     * Filtros: id_cliente (OBLIG), desde/hasta (o fecha), q (folio/código/descr)
     */
    public function listarCreditoClienteItems(int $pagina = 1, int $limite = 10, array $filtros = []): array {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

        $idCliente = (int)($filtros['id_cliente'] ?? 0);
        if ($idCliente <= 0) return [];

        [$ini, $fin] = $this->rangoFechas(trim($filtros['desde'] ?? ''), trim($filtros['hasta'] ?? ''));
        $q = trim($filtros['q'] ?? '');

        // En tu BD existe precio_unitario y subtotal
        $precioExpr  = "d.precio_unitario";
        $importeExpr = "COALESCE(d.subtotal, d.cantidad * d.precio_unitario)";

        $sql = "SELECT
                    v.id_venta,
                    v.folio,
                    v.fecha,
                    v.estatus_credito,
                    d.id_venta_detalle,
                    d.cantidad,
                    $precioExpr AS precio_unitario,
                    $importeExpr AS importe,
                    COALESCE(p.codigo, CONCAT('#', d.id_producto)) AS codigo,
                    COALESCE(p.descripcion, '') AS descripcion,
                    COALESCE(u.descripcion, 'Pza') AS unidad
                FROM ventas v
                JOIN ventas_detalle d ON d.id_venta = v.id_venta
                LEFT JOIN productos p  ON p.id_producto = d.id_producto
                LEFT JOIN unidades_sat u ON u.id_unidad_sat = p.id_unidad_sat
                WHERE v.activo = 1
                  AND d.activo = 1
                  AND v.id_cliente = :idCliente
                  AND (v.estatus_credito IS NOT NULL AND v.estatus_credito <> 'N/A')
                  AND v.fecha >= :ini AND v.fecha < :fin";

        $params = [ ':idCliente'=>$idCliente, ':ini'=>$ini, ':fin'=>$fin ];

        if ($q !== '') {
            $sql .= " AND (v.folio LIKE :q OR p.codigo LIKE :q OR p.descripcion LIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $sql .= " ORDER BY v.fecha DESC, v.folio ASC, d.id_venta_detalle ASC
                  LIMIT {$limite} OFFSET {$offset}";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** TOTAL de renglones para el paginador */
    public function contarCreditoClienteItems(array $filtros = []): int {
        $idCliente = (int)($filtros['id_cliente'] ?? 0);
        if ($idCliente <= 0) return 0;

        [$ini, $fin] = $this->rangoFechas(trim($filtros['desde'] ?? ''), trim($filtros['hasta'] ?? ''));
        $q = trim($filtros['q'] ?? '');

        $sql = "SELECT COUNT(*) AS total
                FROM ventas v
                JOIN ventas_detalle d ON d.id_venta = v.id_venta
                LEFT JOIN productos p  ON p.id_producto = d.id_producto
                WHERE v.activo = 1
                  AND d.activo = 1
                  AND v.id_cliente = :idCliente
                  AND (v.estatus_credito IS NOT NULL AND v.estatus_credito <> 'N/A')
                  AND v.fecha >= :ini AND v.fecha < :fin";
        $params = [ ':idCliente'=>$idCliente, ':ini'=>$ini, ':fin'=>$fin ];

        if ($q !== '') {
            $sql .= " AND (v.folio LIKE :q OR p.codigo LIKE :q OR p.descripcion LIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /** LISTAR TODO (sin paginación) para CSV */
    public function listarCreditoClienteItemsTodo(array $filtros = []): array {
        $idCliente = (int)($filtros['id_cliente'] ?? 0);
        if ($idCliente <= 0) return [];

        [$ini, $fin] = $this->rangoFechas(trim($filtros['desde'] ?? ''), trim($filtros['hasta'] ?? ''));
        $q = trim($filtros['q'] ?? '');

        $precioExpr  = "d.precio_unitario";
        $importeExpr = "COALESCE(d.subtotal, d.cantidad * d.precio_unitario)";

        $sql = "SELECT
                    v.folio,
                    v.estatus_credito,
                    d.cantidad,
                    COALESCE(p.codigo, CONCAT('#', d.id_producto)) AS codigo,
                    COALESCE(u.descripcion, 'Pza') AS unidad,
                    COALESCE(p.descripcion, '') AS descripcion,
                    $precioExpr  AS precio,
                    $importeExpr AS total,
                    v.fecha
                FROM ventas v
                JOIN ventas_detalle d ON d.id_venta = v.id_venta
                LEFT JOIN productos p  ON p.id_producto = d.id_producto
                LEFT JOIN unidades_sat u ON u.id_unidad_sat = p.id_unidad_sat
                WHERE v.activo = 1
                  AND d.activo = 1
                  AND v.id_cliente = :idCliente
                  AND (v.estatus_credito IS NOT NULL AND v.estatus_credito <> 'N/A')
                  AND v.fecha >= :ini AND v.fecha < :fin";

        $params = [ ':idCliente'=>$idCliente, ':ini'=>$ini, ':fin'=>$fin ];

        if ($q !== '') {
            $sql .= " AND (v.folio LIKE :q OR p.codigo LIKE :q OR p.descripcion LIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $sql .= " ORDER BY v.fecha DESC, v.folio ASC, d.id_venta_detalle ASC";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Totales del día (suma de cantidades e importes) */
    public function totalesCreditoClienteDia(array $filtros = []): array {
        $idCliente = (int)($filtros['id_cliente'] ?? 0);
        if ($idCliente <= 0) return ['items'=>0,'cantidad'=>0,'total'=>0];

        [$ini, $fin] = $this->rangoFechas(trim($filtros['desde'] ?? ''), trim($filtros['hasta'] ?? ''));
        $q = trim($filtros['q'] ?? '');

        $importeExpr = "COALESCE(d.subtotal, d.cantidad * d.precio_unitario)";

        $sql = "SELECT
                    COUNT(*) AS items,
                    SUM(d.cantidad) AS cantidad,
                    SUM($importeExpr) AS total
                FROM ventas v
                JOIN ventas_detalle d ON d.id_venta = v.id_venta
                LEFT JOIN productos p  ON p.id_producto = d.id_producto
                WHERE v.activo = 1
                  AND d.activo = 1
                  AND v.id_cliente = :idCliente
                  AND (v.estatus_credito IS NOT NULL AND v.estatus_credito <> 'N/A')
                  AND v.fecha >= :ini AND v.fecha < :fin";
        $params = [ ':idCliente'=>$idCliente, ':ini'=>$ini, ':fin'=>$fin ];

        if ($q !== '') {
            $sql .= " AND (v.folio LIKE :q OR p.codigo LIKE :q OR p.descripcion LIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'items'    => (int)($row['items'] ?? 0),
            'cantidad' => (float)($row['cantidad'] ?? 0),
            'total'    => (float)($row['total'] ?? 0),
        ];
    }

    /** CSV */
    public function csvCreditoClienteItems(array $rows): string {
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, ['FOLIO','ESTATUS_CREDITO','CANTIDAD','CODIGO','UNIDAD','DESCRIPCION','PRECIO','TOTAL','FECHA']);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['folio'],
                $r['estatus_credito'],
                number_format((float)$r['cantidad'], 2, '.', ''),
                $r['codigo'],
                $r['unidad'],
                $r['descripcion'],
                number_format((float)($r['precio_unitario'] ?? $r['precio'] ?? 0), 2, '.', ''),
                number_format((float)($r['importe'] ?? $r['total'] ?? 0),  2, '.', ''),
                $r['fecha']
            ]);
        }
        rewind($fh);
        return stream_get_contents($fh);
    }

    // === NUEVO: Reporte de Utilidades ===
    /**
     * Construye el WHERE de estatus de venta según filtros.
     */
    private function whereEstatusVenta(array $filtros, array &$params): string {
        $incCred   = (int)($filtros['inc_credito'] ?? 0);
        $soloCredL = (int)($filtros['solo_credito_liquidado'] ?? 1);
        $incGuard  = (int)($filtros['inc_guardadas'] ?? 0);

        // Siempre Activa
        $cond = ["v.estatus = 'Activa'"];

        // Guardada (opcional)
        if ($incGuard === 1) $cond[] = "v.estatus = 'Guardada'";

        // Crédito (opcional) + subfiltro "solo liquidado"
        if ($incCred === 1) {
            if ($soloCredL === 1) {
                $cond[] = "(v.estatus = 'Credito' AND v.estatus_credito = 'Liquidado')";
            } else {
                $cond[] = "(v.estatus = 'Credito')";
            }
        }

        // Excluimos explícitamente canceladas/devueltas
        $cond[] = "v.estatus <> 'Cancelada'";
        $cond[] = "v.estatus <> 'Devuelta'";

        return '(' . implode(' OR ', array_slice($cond, 0, max(1, count($cond)-2))) . ') AND v.estatus <> \'Cancelada\' AND v.estatus <> \'Devuelta\'';
    }

    /**
     * Define columnas de período/agrupación (día|semana|mes|ninguno).
     */
    private function periodoSQL(string $group): array {
        $g = strtolower(trim($group ?: 'dia'));
        switch ($g) {
            case 'semana':
                return [
                'select' => "CONCAT('Sem ', LPAD(WEEK(v.fecha,3),2,'0'), '/', YEAR(v.fecha)) AS periodo",
                'group'  => "YEAR(v.fecha), WEEK(v.fecha,3)",
                'order'  => "YEAR(v.fecha) DESC, WEEK(v.fecha,3) DESC"
                ];
            case 'mes':
                return [
                'select' => "DATE_FORMAT(v.fecha, '%m/%Y') AS periodo",
                'group'  => "YEAR(v.fecha), MONTH(v.fecha)",
                'order'  => "YEAR(v.fecha) DESC, MONTH(v.fecha) DESC"
                ];
            case 'ninguno':
                return [
                'select' => "NULL AS periodo",
                'group'  => "", // sin período
                'order'  => "p.codigo ASC"
                ];
            case 'dia':
            default:
                return [
                'select' => "DATE_FORMAT(v.fecha, '%d/%m/%Y') AS periodo",
                'group'  => "DATE(v.fecha)",
                'order'  => "DATE(v.fecha) DESC"
                ];
        }
    }

    /**
     * Lista utilidades agregadas por producto y período.
     * Filtros:
     *  - desde, hasta (YYYY-MM-DD)
     *  - q (código/descr)
     *  - group_by: dia|semana|mes|ninguno
     *  - inc_credito: 0/1
     *  - solo_credito_liquidado: 1 (default) / 0
     *  - inc_guardadas: 0/1
     */
    // === NUEVO: detalle por renglón (con folio, precios unitarios) ===
    public function listarUtilidadesDetalle(int $pagina=1, int $limite=20, array $filtros=[]): array {
        $pagina = max(1, $pagina);
        $limite = max(1, $limite);
        $offset = ($pagina - 1) * $limite;

        [$ini, $fin] = $this->rangoFechas(trim($filtros['desde'] ?? ''), trim($filtros['hasta'] ?? ''));
        $q = trim($filtros['q'] ?? '');

        $params = [':ini'=>$ini, ':fin'=>$fin];
        $wEstatus = $this->whereEstatusVenta($filtros, $params);

        $ingreso = "COALESCE(d.subtotal, d.cantidad * d.precio_unitario)";
        $costo   = "COALESCE(d.costo_subtotal, d.cantidad * d.costo_unitario)";
        $util    = "COALESCE(d.utilidad_subtotal, ($ingreso - $costo))";

        $sql = "
        SELECT
            v.id_venta, v.folio, v.fecha,
            d.id_venta_detalle,
            COALESCE(p.codigo, CONCAT('#', d.id_producto)) AS codigo,
            COALESCE(p.descripcion,'') AS descripcion,
            COALESCE(u.descripcion,'Pza') AS unidad,
            d.cantidad,
            d.precio_unitario,
            d.costo_unitario,
            $ingreso   AS ingreso,
            $costo     AS costo,
            $util      AS utilidad,
            CASE WHEN $ingreso>0 THEN $util/$ingreso ELSE 0 END AS margen
        FROM ventas v
        JOIN ventas_detalle d ON d.id_venta = v.id_venta
        LEFT JOIN productos p  ON p.id_producto = d.id_producto
        LEFT JOIN unidades_sat u ON u.id_unidad_sat = p.id_unidad_sat
        WHERE v.activo = 1
            AND d.activo = 1
            AND v.fecha >= :ini AND v.fecha < :fin
            AND $wEstatus
        ";
        if ($q !== '') {
            $sql .= " AND (v.folio LIKE :q OR p.codigo LIKE :q OR p.descripcion LIKE :q)";
            $params[':q'] = "%{$q}%";
        }
        $sql .= " ORDER BY v.fecha DESC, v.folio ASC, d.id_venta_detalle ASC
                LIMIT {$limite} OFFSET {$offset}";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarUtilidadesDetalle(array $filtros=[]): int {
        [$ini, $fin] = $this->rangoFechas(trim($filtros['desde'] ?? ''), trim($filtros['hasta'] ?? ''));
        $q = trim($filtros['q'] ?? '');

        $params = [':ini'=>$ini, ':fin'=>$fin];
        $wEstatus = $this->whereEstatusVenta($filtros, $params);

        $sql = "
        SELECT COUNT(*) AS total
        FROM ventas v
        JOIN ventas_detalle d ON d.id_venta = v.id_venta
        LEFT JOIN productos p  ON p.id_producto = d.id_producto
        WHERE v.activo = 1
            AND d.activo = 1
            AND v.fecha >= :ini AND v.fecha < :fin
            AND $wEstatus
        ";
        if ($q !== '') {
            $sql .= " AND (v.folio LIKE :q OR p.codigo LIKE :q OR p.descripcion LIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /** Totales del rango (ingreso, costo, utilidad) sin agrupar. */
    public function totalesUtilidades(array $filtros=[]): array {
        [$ini, $fin] = $this->rangoFechas(trim($filtros['desde'] ?? ''), trim($filtros['hasta'] ?? ''));
        $q = trim($filtros['q'] ?? '');

        $params = [':ini'=>$ini, ':fin'=>$fin];
        $wEstatus = $this->whereEstatusVenta($filtros, $params);

        $ingreso = "COALESCE(d.subtotal, d.cantidad * d.precio_unitario)";
        $costo   = "COALESCE(d.costo_subtotal, d.cantidad * d.costo_unitario)";
        $util    = "COALESCE(d.utilidad_subtotal, ($ingreso - $costo))";

        $sql = "
        SELECT
            SUM($ingreso) AS ingreso,
            SUM($costo)   AS costo,
            SUM($util)    AS utilidad
        FROM ventas v
        JOIN ventas_detalle d ON d.id_venta = v.id_venta
        LEFT JOIN productos p ON p.id_producto = d.id_producto
        WHERE v.activo = 1
            AND d.activo = 1
            AND v.fecha >= :ini AND v.fecha < :fin
            AND $wEstatus
        ";
        if ($q !== '') {
            $sql .= " AND (p.codigo LIKE :q OR p.descripcion LIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['ingreso'=>0,'costo'=>0,'utilidad'=>0];
        $ing = (float)($r['ingreso'] ?? 0);
        $uti = (float)($r['utilidad'] ?? 0);
        return [
        'ingreso'  => $ing,
        'costo'    => (float)($r['costo'] ?? 0),
        'utilidad' => $uti,
        'margen'   => $ing > 0 ? ($uti / $ing) : 0
        ];
    }

    
}
