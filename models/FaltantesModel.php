<?php
require_once __DIR__ . '/../includes/db.php';

class FaltantesModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    /** ================= Paginado ================= */
    public function faltantesPaginado(array $f, int $pagina=1, int $limite=10): array
    {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

        $modo  = trim((string)($f['modo'] ?? 'rango'));
        $desde = trim((string)($f['desde'] ?? ''));
        $hasta = trim((string)($f['hasta'] ?? ''));

        $params = [];
        $filtroFechas = '';
        if ($modo === 'rango' && $desde !== '' && $hasta !== '') {
            $filtroFechas = " AND v.fecha >= :desde AND v.fecha < DATE_ADD(:hasta, INTERVAL 1 DAY) ";
            $params[':desde'] = $desde;
            $params[':hasta'] = $hasta;
        }

        $filtroDia = '';
        if ($modo === 'mar-sab') {
            $filtroDia = " AND DAYOFWEEK(v.fecha) BETWEEN 3 AND 7 ";
        } elseif ($modo === 'lunes') {
            $filtroDia = " AND DAYOFWEEK(v.fecha) = 2 ";
        }

        $filtroEstatus = " AND v.estatus IN ('Activa','Credito') ";

        // ✅ COUNT (detalle): cuenta filas del detalle, NO productos
        $sqlCount = "
            SELECT COUNT(*)
            FROM ventas_detalle vd
            JOIN ventas v ON v.id_venta = vd.id_venta
            WHERE v.activo=1
              AND (vd.activo=1 OR vd.activo IS NULL)
              {$filtroEstatus} {$filtroFechas} {$filtroDia}
        ";
        $stC = $this->conn->prepare($sqlCount);
        foreach ($params as $k=>$v) $stC->bindValue($k,$v);
        $stC->execute();
        $total = (int)$stC->fetchColumn();
        if ($total === 0) return ['data'=>[], 'total'=>0];

        // ✅ DATA (detalle): 1 fila por ventas_detalle, cliente amarrado a ESA venta
        $sql = "
          WITH ventas_filtradas AS (
            SELECT 
              vd.id_producto,
              v.id_venta,
              v.folio,
              v.fecha,
              v.estatus,
              v.id_cliente,
              vd.cantidad
            FROM ventas_detalle vd
            JOIN ventas v ON v.id_venta = vd.id_venta
            WHERE v.activo = 1
              AND (vd.activo=1 OR vd.activo IS NULL)
              {$filtroEstatus} {$filtroFechas} {$filtroDia}
          ),
          totales_ventas AS (
            SELECT
              id_producto,
              SUM(cantidad) AS total_vendido
            FROM ventas_filtradas
            GROUP BY id_producto
          )
          SELECT 
            p.id_producto,
            p.codigo,
            us.descripcion AS unidad,
            p.descripcion,
            vf.cantidad AS cantidad,
            vf.fecha    AS fecha_venta,
            vf.folio    AS folio,
            pr.nombre   AS proveedor,
            p.stock_actual AS inventario,
            GREATEST(tv.total_vendido - p.stock_actual, 0) AS faltante_sobre_ventas,
            GREATEST(p.stock_minimo - p.stock_actual, 0)   AS faltante_vs_minimo,
            CASE 
              WHEN vf.estatus = 'Credito' THEN COALESCE(c.nombre, '')
              ELSE ''
            END AS compro_credito
          FROM ventas_filtradas vf
          JOIN productos p ON p.id_producto = vf.id_producto
          JOIN totales_ventas tv ON tv.id_producto = vf.id_producto
          LEFT JOIN unidades_sat us ON us.id_unidad_sat = p.id_unidad_sat
          LEFT JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor
          LEFT JOIN clientes c ON c.id_cliente = vf.id_cliente
          WHERE tv.total_vendido > 0
          ORDER BY 
            CAST(vf.folio AS UNSIGNED) DESC,
            vf.fecha DESC,
            vf.id_venta DESC
          LIMIT :limite OFFSET :offset
        ";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':limite', (int)$limite, \PDO::PARAM_INT);
        $st->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        return ['data'=>$rows, 'total'=>$total];
    }

    public function negativosPaginado(array $f=[], int $pagina=1, int $limite=10): array
    {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

        $sqlCount = "SELECT COUNT(*) FROM productos p WHERE p.activo=1 AND p.stock_actual < 0";
        $total = (int)$this->conn->query($sqlCount)->fetchColumn();
        if ($total === 0) return ['data'=>[], 'total'=>0];

        $sql = "
          SELECT
            p.id_producto,
            p.codigo,
            us.descripcion AS unidad,
            p.descripcion,
            0               AS cantidad,
            NULL            AS fecha_venta,
            NULL            AS folio,
            pr.nombre       AS proveedor,
            p.stock_actual  AS inventario,
            0               AS faltante_sobre_ventas,
            GREATEST(p.stock_minimo - p.stock_actual, 0) AS faltante_vs_minimo,
            NULL            AS compro_credito
          FROM productos p
          LEFT JOIN unidades_sat us ON us.id_unidad_sat = p.id_unidad_sat
          LEFT JOIN proveedores pr   ON pr.id_proveedor  = p.id_proveedor
          WHERE p.activo=1 AND p.stock_actual < 0
          ORDER BY p.stock_actual ASC
          LIMIT :limite OFFSET :offset
        ";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':limite',(int)$limite,\PDO::PARAM_INT);
        $st->bindValue(':offset',(int)$offset,\PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        return ['data'=>$rows, 'total'=>$total];
    }

    /** ================= Sin paginar (exportar) ================= */
    public function faltantesAll(array $f): array
    {
        $modo  = trim((string)($f['modo'] ?? 'rango'));
        $desde = trim((string)($f['desde'] ?? ''));
        $hasta = trim((string)($f['hasta'] ?? ''));

        $params = [];
        $filtroFechas = '';
        if ($modo === 'rango' && $desde !== '' && $hasta !== '') {
            $filtroFechas = " AND v.fecha >= :desde AND v.fecha < DATE_ADD(:hasta, INTERVAL 1 DAY) ";
            $params[':desde'] = $desde;
            $params[':hasta'] = $hasta;
        }

        $filtroDia = '';
        if ($modo === 'mar-sab') {
            $filtroDia = " AND DAYOFWEEK(v.fecha) BETWEEN 3 AND 7 ";
        } elseif ($modo === 'lunes') {
            $filtroDia = " AND DAYOFWEEK(v.fecha) = 2 ";
        }

        $filtroEstatus = " AND v.estatus IN ('Activa','Credito') ";

        $sql = "
          WITH ventas_filtradas AS (
            SELECT 
              vd.id_producto,
              v.id_venta,
              v.folio,
              v.fecha,
              v.estatus,
              v.id_cliente,
              vd.cantidad
            FROM ventas_detalle vd
            JOIN ventas v ON v.id_venta = vd.id_venta
            WHERE v.activo = 1
              AND (vd.activo=1 OR vd.activo IS NULL)
              {$filtroEstatus} {$filtroFechas} {$filtroDia}
          ),
          totales_ventas AS (
            SELECT
              id_producto,
              SUM(cantidad) AS total_vendido
            FROM ventas_filtradas
            GROUP BY id_producto
          )
          SELECT 
            p.id_producto,
            p.codigo,
            us.descripcion AS unidad,
            p.descripcion,
            vf.cantidad AS cantidad,
            DATE(vf.fecha) AS fecha_venta,  -- ✅ SOLO FECHA (sin hora)
            vf.id_venta AS id_venta,
            vf.folio    AS folio,
            pr.nombre   AS proveedor,
            p.stock_actual AS inventario,
            GREATEST(tv.total_vendido - p.stock_actual, 0) AS faltante_sobre_ventas,
            GREATEST(p.stock_minimo - p.stock_actual, 0)   AS faltante_vs_minimo,
            CASE 
              WHEN vf.estatus = 'Credito' THEN COALESCE(c.nombre, '')
              ELSE ''
            END AS compro_credito
          FROM ventas_filtradas vf
          JOIN productos p ON p.id_producto = vf.id_producto
          JOIN totales_ventas tv ON tv.id_producto = vf.id_producto
          LEFT JOIN unidades_sat us ON us.id_unidad_sat = p.id_unidad_sat
          LEFT JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor
          LEFT JOIN clientes c ON c.id_cliente = vf.id_cliente
          WHERE tv.total_vendido > 0
          ORDER BY 
            CAST(vf.folio AS UNSIGNED) DESC,
            vf.fecha DESC,
            vf.id_venta DESC
        ";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->execute();

        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function negativosAll(): array
    {
        $sql = "
          SELECT
            p.id_producto,
            p.codigo,
            us.descripcion  AS unidad,
            p.descripcion,
            0               AS cantidad,
            NULL            AS fecha_venta,
            NULL            AS folio,
            pr.nombre       AS proveedor,
            p.stock_actual  AS inventario,
            0               AS faltante_sobre_ventas,
            GREATEST(p.stock_minimo - p.stock_actual, 0) AS faltante_vs_minimo,
            NULL            AS compro_credito
          FROM productos p
          LEFT JOIN unidades_sat us ON us.id_unidad_sat = p.id_unidad_sat
          LEFT JOIN proveedores pr   ON pr.id_proveedor  = p.id_proveedor
          WHERE p.activo=1 AND p.stock_actual < 0
          ORDER BY p.stock_actual ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }
}
