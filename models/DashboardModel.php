<?php
// models/DashboardModel.php
include_once '../includes/db.php';

class DashboardModel
{
    /** @var PDO */
    private $conn;

    // Claves SAT
    private const SAT_EFECTIVO = '01';
    // Tarjeta/transfer: transferencia (03), crédito (04), débito (28), servicios (29)
    private const SAT_TARJETA_SET = ['03','04','28','29'];

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
        try { $this->conn->exec("SET time_zone='-07:00'"); } catch (\Throwable $th) {}
    }

    /* ========================= Helpers ========================= */
    private function sumOrZero(string $sql, array $params = []): float
    {
        try {
            $st = $this->conn->prepare($sql);
            foreach ($params as $k => $v) $st->bindValue($k, $v);
            $st->execute();
            $v = $st->fetchColumn();
            return $v !== null ? (float)$v : 0.0;
        } catch (\Throwable $th) {
            return 0.0;
        }
    }

    /** Filtro por sucursal (solo para tablas que sí tienen id_sucursal) */
    private function wSuc(?int $idSucursal, string $alias): array
    {
        if ($idSucursal && $idSucursal > 0) {
            return [" AND {$alias}.id_sucursal = :sucursal ", [':sucursal' => (int)$idSucursal]];
        }
        return ['', []];
    }

    /** Placeholders nombrados para IN (:p0,:p1,...) -> [cadena, params] */
    private function inNamed(string $prefix, array $vals): array
    {
        $marks = []; $p = [];
        foreach ($vals as $i => $v) { $key = ":{$prefix}{$i}"; $marks[] = $key; $p[$key] = $v; }
        return [implode(',', $marks), $p];
    }

    /* ========================= KPIs del día ========================= */
    public function resumenDia(string $fecha, ?int $idSucursal = null): array
    {
        $pBase = [':f' => $fecha];

        /* ===== Ventas (sí filtran por sucursal) ===== */
        [$wV, $pV] = $this->wSuc($idSucursal, 'v');

        // Venta EFECTIVO (SAT 01)
        $ventaEfectivo = $this->sumOrZero(
            "SELECT IFNULL(SUM(v.total),0)
               FROM ventas v
               JOIN formas_pago fp ON fp.id_forma_pago = v.id_forma_pago AND fp.activo = 1
              WHERE DATE(v.fecha) = :f
                AND v.activo = 1
                AND UPPER(v.estatus) = 'ACTIVA'
                AND fp.clave_sat = :sat_ef
                $wV",
            $pBase + [':sat_ef' => self::SAT_EFECTIVO] + $pV
        );

        // Venta TARJETA / TRANSFER (SAT 03, 04, 28, 29)
        [$inTar, $pTar] = $this->inNamed('sat', self::SAT_TARJETA_SET);
        $ventaTarjeta = $this->sumOrZero(
            "SELECT IFNULL(SUM(v.total),0)
               FROM ventas v
               JOIN formas_pago fp ON fp.id_forma_pago = v.id_forma_pago AND fp.activo = 1
              WHERE DATE(v.fecha) = :f
                AND v.activo = 1
                AND UPPER(v.estatus) = 'ACTIVA'
                AND fp.clave_sat IN ($inTar)
                $wV",
            $pBase + $pV + $pTar
        );

        // Venta del día (regla 1) = efectivo + tarjeta
        $ventaDia = $ventaEfectivo + $ventaTarjeta;

        // Importe del producto CHKDA (Checadas)
        $importeChkda = $this->sumOrZero(
            "SELECT IFNULL(SUM(COALESCE(vd.subtotal, vd.cantidad*vd.precio_unitario)),0)
               FROM ventas v
               JOIN ventas_detalle vd ON vd.id_venta = v.id_venta AND (vd.activo = 1 OR vd.activo IS NULL)
               JOIN productos p ON p.id_producto = vd.id_producto
              WHERE DATE(v.fecha) = :f
                AND v.activo = 1
                AND UPPER(v.estatus) = 'ACTIVA'
                AND UPPER(p.codigo) = 'CHKDA'
                $wV",
            $pBase + $pV
        );

        /* ===== Préstamos / Disposiciones / Abonos (SIN sucursal) ===== */
        // Préstamos + Disposiciones del día (excluye Cancelado)
        $prestamosDia = $this->sumOrZero(
            "SELECT IFNULL(SUM(p.monto_total),0)
               FROM prestamos p
              WHERE DATE(p.fecha_prestamo) = :f
                AND p.activo = 1
                AND UPPER(p.estatus) <> 'CANCELADO'
                AND UPPER(TRIM(p.tipo_operacion)) IN ('PRESTAMO','DISPOSICION')",
            $pBase
        );

        // Abonos del día (tabla 'abonos'), a préstamos no cancelados
        $abonosDia = $this->sumOrZero(
            "SELECT IFNULL(SUM(a.monto),0)
               FROM abonos a
               JOIN prestamos p ON p.id_prestamo = a.id_prestamo
              WHERE DATE(a.fecha_abono) = :f
                AND a.activo = 1
                AND p.activo = 1
                AND UPPER(p.estatus) <> 'CANCELADO'",
            $pBase
        );

        /* ===== Efectivo en caja =====
           Regla: venta_efectivo + abonos_dia - prestamos_dia
        */
        $efectivoEnCaja = $ventaEfectivo + $abonosDia - $prestamosDia;

        /* ===== Venta total =====
           Regla: (venta_efectivo + venta_tarjeta) - importe_chkda
        */
        $ventaTotal = $ventaDia - $importeChkda;

        return [
            'fecha'             => $fecha,
            'venta_dia'         => round($ventaDia, 2),
            'venta_efectivo'    => round($ventaEfectivo, 2),
            'venta_tarjeta'     => round($ventaTarjeta, 2),
            'importe_chkda'     => round($importeChkda, 2),

            'prestamos_dia'     => round($prestamosDia, 2),
            'abonos_dia'        => round($abonosDia, 2),

            'efectivo_en_caja'  => round($efectivoEnCaja, 2),
            'venta_total'       => round($ventaTotal, 2),
        ];
    }

    /* ========================= Gráficas (sin cambios) ========================= */

    public function tendencia30d(?int $idSucursal = null): array
    {
        $hasta = new DateTime('today');
        $desde = (clone $hasta)->modify('-29 days');
        $p = [':desde' => $desde->format('Y-m-d'), ':hasta' => $hasta->format('Y-m-d')];

        $w = ''; $ps = [];
        if ($idSucursal && $idSucursal > 0) { $w = ' AND v.id_sucursal = :suc '; $ps[':suc'] = (int)$idSucursal; }

        $sql = "
          SELECT DATE(v.fecha) AS f, SUM(v.total) AS total
            FROM ventas v
           WHERE v.activo = 1
             AND UPPER(v.estatus) = 'ACTIVA'
             AND DATE(v.fecha) BETWEEN :desde AND :hasta
             $w
        GROUP BY DATE(v.fecha)
        ORDER BY f";

        $st = $this->conn->prepare($sql);
        foreach (($p + $ps) as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $r) { $map[$r['f']] = (float)$r['total']; }

        $labels = []; $serie = [];
        $cursor = clone $desde;
        while ($cursor <= $hasta) {
            $d = $cursor->format('Y-m-d');
            $labels[] = $d;
            $serie[]  = $map[$d] ?? 0;
            $cursor->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'series' => [['name' => 'Ventas', 'data' => $serie]],
            'desde'  => $desde->format('Y-m-d'),
            'hasta'  => $hasta->format('Y-m-d'),
        ];
    }

    public function topProductosMes(?int $idSucursal = null): array
    {
        $desde = new DateTime('first day of this month');
        $hasta = new DateTime('last day of this month');
        $p = [':desde'=>$desde->format('Y-m-d'), ':hasta'=>$hasta->format('Y-m-d')];

        [$w, $ps] = $this->wSuc($idSucursal, 'v');

        $sql = "
          SELECT p.codigo AS producto,
                 SUM(COALESCE(vd.subtotal, vd.cantidad*vd.precio_unitario)) AS importe,
                 SUM(vd.cantidad) AS unidades
            FROM ventas v
            JOIN ventas_detalle vd ON vd.id_venta = v.id_venta AND (vd.activo = 1 OR vd.activo IS NULL)
            JOIN productos p ON p.id_producto = vd.id_producto
           WHERE v.activo = 1
             AND UPPER(v.estatus) = 'ACTIVA'
             AND DATE(v.fecha) BETWEEN :desde AND :hasta
             $w
        GROUP BY vd.id_producto, p.descripcion
        ORDER BY importe DESC
        LIMIT 10";

        $st = $this->conn->prepare($sql);
        foreach (($p + $ps) as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $labels = []; $importes=[]; $unidades=[];
        foreach ($rows as $r) {
            $labels[]   = (string)$r['producto'];
            $importes[] = (float)$r['importe'];
            $unidades[] = (float)$r['unidades'];
        }

        return [
            'labels'  => $labels,
            'series'  => [
                ['name'=>'Importe',  'data'=>$importes],
                ['name'=>'Unidades', 'data'=>$unidades],
            ],
            'desde'   => $desde->format('Y-m-d'),
            'hasta'   => $hasta->format('Y-m-d'),
        ];
    }

    public function topProveedoresMes(?int $idSucursal = null): array
    {
        $desde = new DateTime('first day of this month');
        $hasta = new DateTime('last day of this month');
        $p = [':desde' => $desde->format('Y-m-d'), ':hasta' => $hasta->format('Y-m-d')];

        $w = ''; $ps = [];
        if ($idSucursal && $idSucursal > 0) {
            $w = ' AND c.id_sucursal = :suc ';
            $ps[':suc'] = (int)$idSucursal;
        }

        $sql = "
        SELECT pr.nombre AS proveedor,
               SUM(c.total) AS importe,
               COUNT(*)     AS facturas
          FROM compras c
          JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
         WHERE c.activo = 1
           AND c.estatus IN ('Pendiente','Pagada','Parcial')
           AND DATE(c.fecha_factura) BETWEEN :desde AND :hasta
           $w
      GROUP BY c.id_proveedor, pr.nombre
      ORDER BY importe DESC
      LIMIT 10";

        $st = $this->conn->prepare($sql);
        foreach (($p + $ps) as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $labels = []; $importes=[]; $facturas=[];
        foreach ($rows as $r) {
            $labels[]   = (string)$r['proveedor'];
            $importes[] = (float)$r['importe'];
            $facturas[] = (int)$r['facturas'];
        }

        return [
            'labels' => $labels,
            'series' => [
                ['name' => 'Importe',  'data' => $importes],
                ['name' => 'Facturas', 'data' => $facturas],
            ],
            'desde'  => $desde->format('Y-m-d'),
            'hasta'  => $hasta->format('Y-m-d'),
        ];
    }
}
