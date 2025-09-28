<?php
// models/FaltantesModel.php
require_once __DIR__ . '/../includes/db.php';

class FaltantesModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    /**
     * Faltantes basados en ventas:
     *  - modo = 'rango'  -> usa fecha entre [desde, hasta]
     *  - modo = 'lunes'  -> filtra por DAYOFWEEK = 2
     *  - modo = 'mar-sab'-> filtra DAYOFWEEK entre 3 y 7
     *  Fechas se usan SOLO en modo 'rango'.
     */
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
            // MySQL DAYOFWEEK: 1=Dom, 2=Lun, 3=Mar, ... 7=Sab
            $filtroDia = " AND DAYOFWEEK(v.fecha) BETWEEN 3 AND 7 ";
        } elseif ($modo === 'lunes') {
            $filtroDia = " AND DAYOFWEEK(v.fecha) = 2 ";
        }

        $filtroEstatus = " AND v.estatus IN ('Activa','Credito') ";

        /* ===================== COUNT ===================== */
        $sqlCount = "
          SELECT COUNT(*) FROM (
            SELECT p.id_producto
            FROM ventas_detalle vd
            JOIN ventas v        ON v.id_venta    = vd.id_venta
            JOIN productos p     ON p.id_producto = vd.id_producto
            LEFT JOIN unidades_sat us ON us.id_unidad_sat = p.id_unidad_sat
            LEFT JOIN proveedores pr   ON pr.id_proveedor  = p.id_proveedor
            WHERE v.activo=1
              AND (vd.activo=1 OR vd.activo IS NULL)
              {$filtroEstatus}
              {$filtroFechas}
              {$filtroDia}
            GROUP BY 
              p.id_producto, p.codigo, p.descripcion, 
              p.stock_actual, p.stock_minimo,
              us.descripcion, pr.nombre
            HAVING SUM(vd.cantidad) > 0
          ) t
        ";
        $stC = $this->conn->prepare($sqlCount);
        foreach ($params as $k=>$v) $stC->bindValue($k,$v);
        $stC->execute();
        $total = (int)$stC->fetchColumn();
        if ($total === 0) return ['data'=>[], 'total'=>0];

        /* ===================== DATA ===================== */
        $sql = "
          SELECT 
            p.id_producto,
            p.codigo,
            us.descripcion         AS unidad,
            p.descripcion,
            SUM(vd.cantidad)       AS cantidad,
            MAX(v.fecha)           AS fecha_venta,
            pr.nombre              AS proveedor,
            p.stock_actual         AS inventario,
            GREATEST(SUM(vd.cantidad) - p.stock_actual, 0) AS faltante_sobre_ventas,
            GREATEST(p.stock_minimo - p.stock_actual, 0)   AS faltante_vs_minimo
          FROM ventas_detalle vd
          JOIN ventas v            ON v.id_venta    = vd.id_venta
          JOIN productos p         ON p.id_producto = vd.id_producto
          LEFT JOIN unidades_sat us ON us.id_unidad_sat = p.id_unidad_sat
          LEFT JOIN proveedores pr   ON pr.id_proveedor  = p.id_proveedor
          WHERE v.activo=1
            AND (vd.activo=1 OR vd.activo IS NULL)
            {$filtroEstatus}
            {$filtroFechas}
            {$filtroDia}
          GROUP BY 
            p.id_producto, p.codigo, p.descripcion,
            p.stock_actual, p.stock_minimo,
            us.descripcion, pr.nombre
          HAVING SUM(vd.cantidad) > 0
          ORDER BY faltante_sobre_ventas DESC, cantidad DESC
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

    /**
     * Productos con inventario negativo (sin fechas).
     * (toma unidad y proveedor por LEFT JOIN)
     */
    public function negativosPaginado(array $f=[], int $pagina=1, int $limite=10): array
    {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

        $sqlCount = "
          SELECT COUNT(*)
          FROM productos p
          WHERE p.activo=1 AND p.stock_actual < 0
        ";
        $total = (int)$this->conn->query($sqlCount)->fetchColumn();
        if ($total === 0) return ['data'=>[], 'total'=>0];

        $sql = "
          SELECT
            p.id_producto,
            p.codigo,
            us.descripcion  AS unidad,
            p.descripcion,
            0               AS cantidad,
            NULL            AS fecha_venta,
            pr.nombre       AS proveedor,
            p.stock_actual  AS inventario,
            0               AS faltante_sobre_ventas,
            GREATEST(p.stock_minimo - p.stock_actual, 0) AS faltante_vs_minimo
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
}
