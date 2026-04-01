<?php
// models/DashboardModel.php
include_once '../includes/db.php';

class DashboardModel
{
    /** @var PDO */
    private $conn;

    // Claves SAT (ajusta si en tu catálogo difieren)
    private const SAT_EFECTIVO    = '01';
    private const SAT_TARJETA_SET = ['03','04','28','29']; // Transferencia, Tarjeta Crédito, Débito, Servicios

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
        try { $this->conn->exec("SET time_zone='-07:00'"); } catch (\Throwable $th) {}
    }

    /* ========================= Helpers ========================= */

    private function sumOrZero(string $sql, array $params = []): float
    {
        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->execute();
        $v = $st->fetchColumn();
        return $v !== null ? (float)$v : 0.0;
    }

    /** Placeholders nombrados para IN (:p0,:p1,...) -> [cadena, params] */
    private function inNamed(string $prefix, array $vals): array
    {
        $marks = []; $p = [];
        foreach ($vals as $i => $v) { $key = ":{$prefix}{$i}"; $marks[] = $key; $p[$key] = $v; }
        return [implode(',', $marks), $p];
    }

    /** Rango sargable de un día: [f 00:00:00, f + 1 día) */
    private function rangoDia(string $fecha): array
    {
        $ini = $fecha . ' 00:00:00';
        $fin = date('Y-m-d', strtotime($fecha . ' +1 day')) . ' 00:00:00';
        return [$ini, $fin];
    }

    /** Condición por sucursal en consultas de VENTAS (pasa por CAJAS) */
    private function whereSucursalVentas(?int $idSucursal): array
    {
        if ($idSucursal && $idSucursal > 0) {
            return [' AND c.id_sucursal = :suc ', [':suc' => (int)$idSucursal]];
        }
        return ['', []];
    }

    /* ========================= KPIs del día ========================= */
    public function resumenDia(string $fecha, ?int $idSucursal = null): array
    {
        [$ini, $fin] = $this->rangoDia($fecha);
        [$wSucVentas, $pSucVentas] = $this->whereSucursalVentas($idSucursal);

        /* ===== Ventas contado (EFECTIVO / TARJETA-TRANSFER) =====
           - 1) Primero, sumamos pagos_venta (incluye MIXTO).
           - 2) Luego, sumamos ventas SIN pagos_venta activos,
                usando ventas.id_forma_pago (comportamiento viejo).
        */

        // === EFECTIVO ===

        // 1) Desde pagos_venta (incluye mixto)
        $ventaEfectivoPagos = $this->sumOrZero(
            "SELECT IFNULL(SUM(vp.monto),0)
               FROM ventas v
               JOIN cajas        c  ON c.id_caja      = v.id_caja
               JOIN pagos_venta  vp ON vp.id_venta    = v.id_venta AND vp.activo = 1
               JOIN formas_pago  fp ON fp.id_forma_pago = vp.id_forma_pago
              WHERE v.fecha >= :ini AND v.fecha < :fin
                AND v.activo = 1
                AND v.estatus = 'Activa'
                AND fp.clave_sat = :sat_ef
                $wSucVentas",
            [':ini'=>$ini, ':fin'=>$fin, ':sat_ef'=>self::SAT_EFECTIVO] + $pSucVentas
        );

        // 2) Ventas SIN pagos_venta, pero con forma de pago EFECTIVO (como antes)
        $ventaEfectivoSinPagos = $this->sumOrZero(
            "SELECT IFNULL(SUM(v.total),0)
               FROM ventas v
               JOIN cajas       c  ON c.id_caja         = v.id_caja
               JOIN formas_pago fp ON fp.id_forma_pago  = v.id_forma_pago
              WHERE v.fecha >= :ini AND v.fecha < :fin
                AND v.activo = 1
                AND v.estatus = 'Activa'
                AND fp.clave_sat = :sat_ef
                AND NOT EXISTS (
                      SELECT 1
                        FROM pagos_venta vp2
                       WHERE vp2.id_venta = v.id_venta
                         AND vp2.activo   = 1
                )
                $wSucVentas",
            [':ini'=>$ini, ':fin'=>$fin, ':sat_ef'=>self::SAT_EFECTIVO] + $pSucVentas
        );

        $ventaEfectivo = $ventaEfectivoPagos + $ventaEfectivoSinPagos;

        // === TARJETA / TRANSFER / OTROS NO EFECTIVO ===
        [$inTar, $pTar] = $this->inNamed('sat', self::SAT_TARJETA_SET);

        // 1) Desde pagos_venta (incluye mixto)
        $ventaTarjetaPagos = $this->sumOrZero(
            "SELECT IFNULL(SUM(vp.monto),0)
               FROM ventas v
               JOIN cajas        c  ON c.id_caja      = v.id_caja
               JOIN pagos_venta  vp ON vp.id_venta    = v.id_venta AND vp.activo = 1
               JOIN formas_pago  fp ON fp.id_forma_pago = vp.id_forma_pago
              WHERE v.fecha >= :ini AND v.fecha < :fin
                AND v.activo = 1
                AND v.estatus = 'Activa'
                AND fp.clave_sat IN ($inTar)
                $wSucVentas",
            [':ini'=>$ini, ':fin'=>$fin] + $pSucVentas + $pTar
        );

        // 2) Ventas SIN pagos_venta, pero con forma de pago de tarjeta/transfer (como antes)
        $ventaTarjetaSinPagos = $this->sumOrZero(
            "SELECT IFNULL(SUM(v.total),0)
               FROM ventas v
               JOIN cajas       c  ON c.id_caja         = v.id_caja
               JOIN formas_pago fp ON fp.id_forma_pago  = v.id_forma_pago
              WHERE v.fecha >= :ini AND v.fecha < :fin
                AND v.activo = 1
                AND v.estatus = 'Activa'
                AND fp.clave_sat IN ($inTar)
                AND NOT EXISTS (
                      SELECT 1
                        FROM pagos_venta vp2
                       WHERE vp2.id_venta = v.id_venta
                         AND vp2.activo   = 1
                )
                $wSucVentas",
            [':ini'=>$ini, ':fin'=>$fin] + $pSucVentas + $pTar
        );

        $ventaTarjeta = $ventaTarjetaPagos + $ventaTarjetaSinPagos;


        /* ===== Ventas a CRÉDITO (igual que antes) ===== */
        $ventaCredito = $this->sumOrZero(
            "SELECT IFNULL(SUM(v.total),0)
               FROM ventas v
               JOIN cajas c ON c.id_caja = v.id_caja
              WHERE v.fecha >= :ini AND v.fecha < :fin
                AND v.activo = 1
                AND v.estatus = 'Credito'
                $wSucVentas",
            [':ini'=>$ini, ':fin'=>$fin] + $pSucVentas
        );

        /* ===== Checadas (producto CHKDA) ===== */
        $importeChkda = $this->sumOrZero(
            "SELECT IFNULL(SUM(COALESCE(vd.subtotal, vd.cantidad*vd.precio_unitario)),0)
               FROM ventas v
               JOIN cajas c           ON c.id_caja = v.id_caja
               JOIN ventas_detalle vd ON vd.id_venta = v.id_venta AND (vd.activo = 1 OR vd.activo IS NULL)
               JOIN productos p       ON p.id_producto = vd.id_producto
              WHERE v.fecha >= :ini AND v.fecha < :fin
                AND v.activo = 1
                AND v.estatus IN ('Activa','Credito')
                AND UPPER(p.codigo) = 'CHKDA'
                $wSucVentas",
            [':ini'=>$ini, ':fin'=>$fin] + $pSucVentas
        );

        /* ===== Préstamos / Disposiciones (egreso en caja) ===== */
        $prestamosTotal = $this->sumOrZero(
            "SELECT IFNULL(SUM(p.monto_total),0)
               FROM prestamos p
              WHERE p.fecha_prestamo >= :ini AND p.fecha_prestamo < :fin
                AND p.activo = 1
                AND p.estatus <> 'Cancelado'
                AND p.tipo_operacion IN ('Prestamo','Disposicion')",
            [':ini'=>$ini, ':fin'=>$fin]
        );

        /* ===== Operación Pago (ingreso por forma de pago) ===== */
        $pagosPrestamoEfectivo = $this->sumOrZero(
            "SELECT IFNULL(SUM(p.monto_total),0)
               FROM prestamos p
               JOIN formas_pago fp ON fp.id_forma_pago = p.id_forma_pago
              WHERE p.fecha_prestamo >= :ini AND p.fecha_prestamo < :fin
                AND p.activo = 1
                AND p.estatus = 'Aplicado'
                AND p.tipo_operacion = 'Pago'
                AND fp.clave_sat = :sat_ef",
            [':ini'=>$ini, ':fin'=>$fin, ':sat_ef'=>self::SAT_EFECTIVO]
        );

        [$inTarP, $pTarP] = $this->inNamed('sat', self::SAT_TARJETA_SET);
        $pagosPrestamoTarjeta = $this->sumOrZero(
            "SELECT IFNULL(SUM(p.monto_total),0)
               FROM prestamos p
               JOIN formas_pago fp ON fp.id_forma_pago = p.id_forma_pago
              WHERE p.fecha_prestamo >= :ini AND p.fecha_prestamo < :fin
                AND p.activo = 1
                AND p.estatus = 'Aplicado'
                AND p.tipo_operacion = 'Pago'
                AND fp.clave_sat IN ($inTarP)",
            [':ini'=>$ini, ':fin'=>$fin] + $pTarP
        );

        // Total contado
        $ventaDiaContado = $ventaEfectivo + $ventaTarjeta;

        /* ===== Abonos a PRÉSTAMOS (por método) ===== */
        $abonosEfectivo = $this->sumOrZero(
            "SELECT IFNULL(SUM(a.monto),0)
               FROM abonos a
               JOIN prestamos p         ON p.id_prestamo = a.id_prestamo
               JOIN formas_pago fp      ON fp.id_forma_pago = a.id_forma_pago
              WHERE a.fecha_abono >= :ini AND a.fecha_abono < :fin
                AND a.activo = 1 AND p.activo = 1
                AND p.estatus <> 'Cancelado'
                AND fp.clave_sat = :sat_ef",
            [':ini'=>$ini, ':fin'=>$fin, ':sat_ef'=>self::SAT_EFECTIVO]
        );

        [$inTarA, $pTarA] = $this->inNamed('sat', self::SAT_TARJETA_SET);
        $abonosTarjeta = $this->sumOrZero(
            "SELECT IFNULL(SUM(a.monto),0)
               FROM abonos a
               JOIN prestamos p         ON p.id_prestamo = a.id_prestamo
               JOIN formas_pago fp      ON fp.id_forma_pago = a.id_forma_pago
              WHERE a.fecha_abono >= :ini AND a.fecha_abono < :fin
                AND a.activo = 1 AND p.activo = 1
                AND p.estatus <> 'Cancelado'
                AND fp.clave_sat IN ($inTarA)",
            [':ini'=>$ini, ':fin'=>$fin] + $pTarA
        );
        // Bloque "Pagos/Abonos (Préstamos)" = pagos aplicados + abonos del módulo previo
        $pagosAbonosPrestamoEfectivo = $pagosPrestamoEfectivo + $abonosEfectivo;
        $pagosAbonosPrestamoTarjeta  = $pagosPrestamoTarjeta + $abonosTarjeta;
        $pagosAbonosPrestamoTotal    = $pagosAbonosPrestamoEfectivo + $pagosAbonosPrestamoTarjeta;

        /* ===== Abonos a VENTAS a CRÉDITO (por método) ===== */
        [$wSucVC, $pSucVC] = $this->whereSucursalVentas($idSucursal);

        // Efectivo
        $abonosCredEf = $this->sumOrZero(
            "SELECT IFNULL(SUM(va.monto),0)
               FROM ventas_abonos va
               JOIN ventas v        ON v.id_venta = va.id_venta
               JOIN cajas c         ON c.id_caja  = v.id_caja
               JOIN formas_pago fp  ON fp.id_forma_pago = va.id_forma_pago
              WHERE va.fecha_abono >= :ini AND va.fecha_abono < :fin
                AND va.activo = 1
                AND v.activo  = 1
                AND v.estatus = 'Credito'
                AND fp.clave_sat = :sat_ef
                $wSucVC",
            [':ini'=>$ini, ':fin'=>$fin, ':sat_ef'=>self::SAT_EFECTIVO] + $pSucVC
        );

        // Tarjeta/Transfer
        [$inTarVC, $pTarVC] = $this->inNamed('sat', self::SAT_TARJETA_SET);
        $abonosCredTj = $this->sumOrZero(
            "SELECT IFNULL(SUM(va.monto),0)
               FROM ventas_abonos va
               JOIN ventas v        ON v.id_venta = va.id_venta
               JOIN cajas c         ON c.id_caja  = v.id_caja
               JOIN formas_pago fp  ON fp.id_forma_pago = va.id_forma_pago
              WHERE va.fecha_abono >= :ini AND va.fecha_abono < :fin
                AND va.activo = 1
                AND v.activo  = 1
                AND v.estatus = 'Credito'
                AND fp.clave_sat IN ($inTarVC)
                $wSucVC",
            [':ini'=>$ini, ':fin'=>$fin] + $pTarVC + $pSucVC
        );
        $abonosCredTotal = $abonosCredEf + $abonosCredTj;

        /* ===== KPIs finales ===== */
        // Total de ventas (contado + crédito)
        $ventaTotalReal = $ventaDiaContado + $ventaCredito;

        // Caja = Venta EFECTIVO + Pagos/Abonos EF (préstamos) + Abonos EF (ventas crédito) − Préstamos/Disp
        // Nota: pagos por tarjeta/transfer no afectan efectivo en caja.
        $efectivoEnCaja = $ventaEfectivo + $pagosAbonosPrestamoEfectivo + $abonosCredEf - $prestamosTotal;

        return [
            'fecha'            => $fecha,

            // Contado
            'venta_dia'        => round($ventaDiaContado, 2),
            'venta_efectivo'   => round($ventaEfectivo, 2),
            'venta_tarjeta'    => round($ventaTarjeta, 2),

            // Crédito
            'venta_credito'    => round($ventaCredito, 2),

            // Checadas
            'importe_chkda'    => round($importeChkda, 2),

            // Préstamos / Disposiciones (siempre efectivo)
            'prestamos' => [
                'prestamos_disposiciones_total' => round($prestamosTotal, 2),
                'pagos_efectivo'               => round($pagosPrestamoEfectivo, 2),
                'pagos_tarjeta_transfer'       => round($pagosPrestamoTarjeta, 2),
                'pagos_total'                  => round($pagosPrestamoEfectivo + $pagosPrestamoTarjeta, 2),
            ],

            // Abonos a préstamos
            'abonos' => [
                'efectivo'         => round($pagosAbonosPrestamoEfectivo, 2),
                'tarjeta_transfer' => round($pagosAbonosPrestamoTarjeta, 2),
                'total'            => round($pagosAbonosPrestamoTotal, 2),
            ],

            // Abonos a ventas a crédito
            'abonos_credito' => [
                'efectivo'         => round($abonosCredEf, 2),
                'tarjeta_transfer' => round($abonosCredTj, 2),
                'total'            => round($abonosCredTotal, 2),
            ],

            // KPIs finales
            'venta_total_real' => round($ventaTotalReal, 2),
            'efectivo_en_caja' => round($efectivoEnCaja, 2),

            // Compatibilidad con JS previo
            'prestamos_dia'    => round($prestamosTotal, 2),
            'abonos_dia'       => round($pagosAbonosPrestamoTotal, 2),
            'venta_total'      => round(($ventaDiaContado - $importeChkda), 2),
        ];
    }

    /* ========================= Gráficas ========================= */

    public function tendencia30d(?int $idSucursal = null): array
    {
        // últimos 30 días completos (hasta hoy)
        $hoy = new DateTime('today');
        $desde = (clone $hoy)->modify('-29 days');

        $ini = $desde->format('Y-m-d') . ' 00:00:00';
        $fin = $hoy->format('Y-m-d') . ' 23:59:59';

        $wSuc = ''; $ps = [];
        if ($idSucursal && $idSucursal > 0) { $wSuc = ' AND c.id_sucursal = :suc '; $ps[':suc'] = (int)$idSucursal; }

        $sql = "
          SELECT DATE(v.fecha) AS f, SUM(v.total) AS total
            FROM ventas v
            JOIN cajas c ON c.id_caja = v.id_caja
           WHERE v.activo = 1
             AND v.estatus IN ('Activa','Credito')
             AND v.fecha BETWEEN :ini AND :fin
             $wSuc
        GROUP BY DATE(v.fecha)
        ORDER BY f";

        $st = $this->conn->prepare($sql);
        foreach (([':ini'=>$ini, ':fin'=>$fin] + $ps) as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $r) { $map[$r['f']] = (float)$r['total']; }

        $labels = []; $serie = [];
        $cursor = clone $desde;
        while ($cursor <= $hoy) {
            $d = $cursor->format('Y-m-d');
            $labels[] = $d;
            $serie[]  = $map[$d] ?? 0;
            $cursor->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'series' => [['name' => 'Ventas', 'data' => $serie]],
            'desde'  => $labels[0] ?? $desde->format('Y-m-d'),
            'hasta'  => $hoy->format('Y-m-d'),
        ];
    }

    public function topProductosMes(?int $idSucursal = null): array
    {
        $desde = new DateTime('first day of this month');
        $hasta = new DateTime('first day of next month');
        $p = [':desde'=>$desde->format('Y-m-d').' 00:00:00', ':hasta'=>$hasta->format('Y-m-d').' 00:00:00'];

        $wSuc = ''; $ps = [];
        if ($idSucursal && $idSucursal > 0) { $wSuc = ' AND c.id_sucursal = :suc '; $ps[':suc'] = (int)$idSucursal; }

        $sql = "
          SELECT CONCAT(p.codigo, ' · ', p.descripcion) AS etiqueta,
                 SUM(COALESCE(vd.subtotal, vd.cantidad*vd.precio_unitario)) AS importe,
                 SUM(vd.cantidad) AS unidades
            FROM ventas v
            JOIN cajas c           ON c.id_caja = v.id_caja
            JOIN ventas_detalle vd ON vd.id_venta = v.id_venta AND (vd.activo = 1 OR vd.activo IS NULL)
            JOIN productos p       ON p.id_producto = vd.id_producto
           WHERE v.activo = 1
             AND v.estatus IN ('Activa','Credito')
             AND v.fecha >= :desde AND v.fecha < :hasta
             $wSuc
        GROUP BY vd.id_producto, p.codigo, p.descripcion
        ORDER BY importe DESC
        LIMIT 10";

        $st = $this->conn->prepare($sql);
        foreach (($p + $ps) as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $labels = []; $importes=[]; $unidades=[];
        foreach ($rows as $r) {
            $labels[]   = (string)$r['etiqueta'];   // "COD · Descripción"
            $importes[] = (float)$r['importe'];
            $unidades[] = (float)$r['unidades'];
        }

        return [
            'labels'  => $labels,
            'series'  => [
                ['name'=>'Importe',  'data'=>$importes],
                ['name'=>'Unidades', 'data'=>$unidades],
            ],
        ];
    }

    public function topProveedoresMes(?int $idSucursal = null): array
    {
        $desde = new DateTime('first day of this month');
        $hasta = new DateTime('first day of next month');
        $p = [':desde'=>$desde->format('Y-m-d').' 00:00:00', ':hasta'=>$hasta->format('Y-m-d').' 00:00:00'];

        // Si tu tabla compras no maneja id_sucursal, elimina $wSuc y $ps
        $wSuc = ''; $ps = [];
        if ($idSucursal && $idSucursal > 0) { $wSuc = ' AND c.id_sucursal = :suc '; $ps[':suc'] = (int)$idSucursal; }

        $sql = "
          SELECT pr.nombre AS proveedor,
                 SUM(c.total) AS importe,
                 COUNT(*)     AS facturas
            FROM compras c
            JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
           WHERE c.activo = 1
             AND c.estatus IN ('Pendiente','Pagada','Parcial')
             AND c.fecha_factura >= :desde AND c.fecha_factura < :hasta
             $wSuc
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
        ];
    }
}
