<?php
include_once __DIR__ . '/../includes/db.php';

class HistorialCreditoClientesModel
{
    /** @var PDO */
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    private function rangoFechas(string $desde = '', string $hasta = ''): array
    {
        if ($desde === '' && $hasta === '') {
            $desde = date('Y-m-01');
            $hasta = date('Y-m-t');
        }
        if ($desde === '' && $hasta !== '') $desde = $hasta;
        if ($hasta === '' && $desde !== '') $hasta = $desde;

        $ini = $desde . ' 00:00:00';
        $fin = date('Y-m-d', strtotime($hasta . ' +1 day')) . ' 00:00:00';
        return [$ini, $fin];
    }

    private function filtrosVentasCredito(array $filtros, array &$params): string
    {
        [$ini, $fin] = $this->rangoFechas(trim($filtros['fecha_inicial'] ?? ''), trim($filtros['fecha_final'] ?? ''));
        $params[':ini'] = $ini;
        $params[':fin'] = $fin;

        $where = "\n                v.activo = 1\n                AND v.estatus = 'Credito'\n                AND v.fecha >= :ini\n                AND v.fecha < :fin";

        $idCliente = (int)($filtros['id_cliente'] ?? 0);
        if ($idCliente > 0) {
            $where .= "\n                AND v.id_cliente = :id_cliente";
            $params[':id_cliente'] = $idCliente;
        }

        $estatusCredito = trim($filtros['estatus_credito'] ?? '');
        if ($estatusCredito === 'pendiente') {
            $where .= "\n                AND GREATEST(v.total - COALESCE(abt.total_abonado_total, 0), 0) > 0";
        } elseif ($estatusCredito === 'liquidado') {
            $where .= "\n                AND GREATEST(v.total - COALESCE(abt.total_abonado_total, 0), 0) <= 0";
        }

        return $where;
    }

    public function listarResumenClientes(int $pagina = 1, int $limite = 20, array $filtros = []): array
    {
        $pagina = max(1, $pagina);
        $limite = max(1, $limite);
        $offset = ($pagina - 1) * $limite;

        $params = [];
        $whereVentas = $this->filtrosVentasCredito($filtros, $params);

        $sql = "
            SELECT
                b.id_cliente,
                COALESCE(c.nombre, CONCAT('Cliente #', b.id_cliente)) AS cliente,
                COUNT(*) AS ventas_credito_periodo,
                COALESCE(SUM(b.total_venta), 0) AS total_vendido_periodo,
                COALESCE(SUM(b.abonado_total), 0) AS total_abonado,
                COALESCE(SUM(GREATEST(b.total_venta - b.abonado_total, 0)), 0) AS saldo_pendiente_actual,
                MAX(b.ultimo_movimiento_venta) AS ultimo_movimiento,
                CASE
                    WHEN COALESCE(SUM(GREATEST(b.total_venta - b.abonado_total, 0)), 0) > 0 THEN 'Con saldo pendiente'
                    ELSE 'Liquidado'
                END AS estatus_general
            FROM (
                SELECT
                    v.id_venta,
                    v.id_cliente,
                    v.total AS total_venta,
                    COALESCE(abt.total_abonado_total, 0) AS abonado_total,
                    GREATEST(v.fecha, COALESCE(abt.ultimo_abono, v.fecha)) AS ultimo_movimiento_venta
                FROM ventas v
                LEFT JOIN (
                    SELECT
                        a.id_venta,
                        SUM(CASE WHEN a.activo = 1 THEN a.monto ELSE 0 END) AS total_abonado_total,
                        MAX(CASE WHEN a.activo = 1 THEN a.fecha_abono ELSE NULL END) AS ultimo_abono
                    FROM ventas_abonos a
                    GROUP BY a.id_venta
                ) abt ON abt.id_venta = v.id_venta
                WHERE {$whereVentas}
            ) b
            LEFT JOIN clientes c ON c.id_cliente = b.id_cliente
            GROUP BY b.id_cliente, c.nombre
            ORDER BY ultimo_movimiento DESC, cliente ASC
            LIMIT :lim OFFSET :off
        ";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', $limite, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contarResumenClientes(array $filtros = []): int
    {
        $params = [];
        $whereVentas = $this->filtrosVentasCredito($filtros, $params);

        $sql = "
            SELECT COUNT(*) AS total_clientes
            FROM (
                SELECT
                    b.id_cliente,
                    COALESCE(SUM(GREATEST(b.total_venta - b.abonado_total, 0)), 0) AS saldo_pendiente_actual
                FROM (
                    SELECT
                        v.id_venta,
                        v.id_cliente,
                        v.total AS total_venta,
                        COALESCE(abt.total_abonado_total, 0) AS abonado_total
                    FROM ventas v
                    LEFT JOIN (
                        SELECT
                            a.id_venta,
                            SUM(CASE WHEN a.activo = 1 THEN a.monto ELSE 0 END) AS total_abonado_total
                        FROM ventas_abonos a
                        GROUP BY a.id_venta
                    ) abt ON abt.id_venta = v.id_venta
                    WHERE {$whereVentas}
                ) b
                GROUP BY b.id_cliente
            ) q
        ";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total_clientes'] ?? 0);
    }

    public function obtenerDetalleCliente(int $idCliente, array $filtros = []): array
    {
        if ($idCliente <= 0) return ['resumen' => null, 'ventas' => []];

        $params = [
            ':id_cliente' => $idCliente,
        ];

        $whereVentas = $this->filtrosVentasCredito($filtros, $params);

        $sqlVentas = "
            SELECT
                v.id_venta,
                v.folio,
                v.fecha,
                v.total AS total_venta,
                COALESCE(abt.total_abonado_total, 0) AS abonado_total,
                GREATEST(v.total - COALESCE(abt.total_abonado_total, 0), 0) AS saldo_actual,
                CASE
                    WHEN GREATEST(v.total - COALESCE(abt.total_abonado_total, 0), 0) <= 0 THEN 'Liquidado'
                    WHEN COALESCE(abt.total_abonado_total, 0) <= 0 THEN 'Pendiente'
                    ELSE 'En Proceso'
                END AS estatus_credito_calculado,
                GREATEST(v.fecha, COALESCE(abt.ultimo_abono, v.fecha)) AS ultimo_movimiento,
                COALESCE(c.nombre, CONCAT('Cliente #', v.id_cliente)) AS cliente_nombre
            FROM ventas v
            LEFT JOIN clientes c ON c.id_cliente = v.id_cliente
            LEFT JOIN (
                SELECT
                    a.id_venta,
                    SUM(CASE WHEN a.activo = 1 THEN a.monto ELSE 0 END) AS total_abonado_total,
                    MAX(CASE WHEN a.activo = 1 THEN a.fecha_abono ELSE NULL END) AS ultimo_abono
                FROM ventas_abonos a
                GROUP BY a.id_venta
            ) abt ON abt.id_venta = v.id_venta
            WHERE {$whereVentas}
            ORDER BY v.fecha DESC, v.id_venta DESC
        ";

        $st = $this->conn->prepare($sqlVentas);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $ventas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($ventas)) {
            return ['resumen' => null, 'ventas' => []];
        }

        $ventaIds = array_map(static fn($v) => (int)$v['id_venta'], $ventas);
        $placeholders = implode(',', array_fill(0, count($ventaIds), '?'));

        $sqlAbonos = "
            SELECT
                a.id_abono,
                a.id_venta,
                a.fecha_abono,
                a.monto,
                a.referencia_pago,
                fp.descripcion AS forma_pago,
                u.nombre AS usuario_nombre
            FROM ventas_abonos a
            LEFT JOIN formas_pago fp ON fp.id_forma_pago = a.id_forma_pago
            LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
            WHERE a.activo = 1
              AND a.id_venta IN ({$placeholders})
            ORDER BY a.id_venta ASC, a.fecha_abono ASC, a.id_abono ASC
        ";

        $stAb = $this->conn->prepare($sqlAbonos);
        foreach ($ventaIds as $i => $idVenta) {
            $stAb->bindValue($i + 1, $idVenta, PDO::PARAM_INT);
        }
        $stAb->execute();
        $abonos = $stAb->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $abonosPorVenta = [];
        foreach ($abonos as $a) {
            $idVenta = (int)$a['id_venta'];
            $abonosPorVenta[$idVenta][] = $a;
        }

        $resumen = [
            'cliente' => $ventas[0]['cliente_nombre'],
            'ventas_credito_periodo' => count($ventas),
            'total_vendido_periodo' => 0,
            'total_abonado' => 0,
            'saldo_pendiente_actual' => 0,
            'ultimo_movimiento' => null,
        ];

        foreach ($ventas as &$venta) {
            $idVenta = (int)$venta['id_venta'];
            $abVenta = $abonosPorVenta[$idVenta] ?? [];

            $saldoTrack = (float)$venta['total_venta'];
            foreach ($abVenta as &$ab) {
                $saldoAntes = $saldoTrack;
                $saldoTrack = max(0, $saldoTrack - (float)$ab['monto']);
                $ab['saldo_antes'] = $saldoAntes;
                $ab['saldo_despues'] = $saldoTrack;
            }
            unset($ab);

            $venta['abonos'] = $abVenta;

            $resumen['total_vendido_periodo'] += (float)$venta['total_venta'];
            $resumen['total_abonado'] += (float)$venta['abonado_total'];
            $resumen['saldo_pendiente_actual'] += (float)$venta['saldo_actual'];
            if (!$resumen['ultimo_movimiento'] || $venta['ultimo_movimiento'] > $resumen['ultimo_movimiento']) {
                $resumen['ultimo_movimiento'] = $venta['ultimo_movimiento'];
            }
        }
        unset($venta);

        $resumen['estatus_general'] = ((float)$resumen['saldo_pendiente_actual'] > 0) ? 'Con saldo pendiente' : 'Liquidado';

        return ['resumen' => $resumen, 'ventas' => $ventas];
    }
}
