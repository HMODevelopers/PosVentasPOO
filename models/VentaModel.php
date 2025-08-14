<?php
// Incluir conexión PDO (usa la configuración de includes/db.php)
include_once '../includes/db.php';

class VentaModel
{
    private $conn;

    public function __construct()
    {
        // Utilizamos la conexión global $pdo
        global $pdo;
        $this->conn = $pdo;
    }

    // ✅ Obtener ventas paginadas
    public function obtenerVentas($pagina = 1, $limite = 10, $folio = '', $fecha = '')
    {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT v.*, 
                    c.nombre AS cliente, 
                    u.nombre AS usuario, 
                    cj.nombre AS caja,
                    fp.descripcion AS forma_pago,
                    tp.nombre AS tipo_precio
                FROM ventas v
                LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
                INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                INNER JOIN cajas cj ON v.id_caja = cj.id_caja
                INNER JOIN formas_pago fp ON v.id_forma_pago = fp.id_forma_pago
                INNER JOIN tipo_precio tp ON v.id_tipo_precio = tp.id_tipo_precio
                WHERE v.activo = 1";

        $params = [];

        // Filtro por folio (LIKE parcial)
        if (!empty($folio)) {
            $sql .= " AND v.folio LIKE :folio";
            $params[':folio'] = "%$folio%";
        }

        // Filtro por fecha exacta
        if (!empty($fecha)) {
            $sql .= " AND DATE(v.fecha) = :fecha";
            $params[':fecha'] = $fecha;
        }

        $sql .= " ORDER BY v.id_venta DESC LIMIT :limite OFFSET :offset";

        $stmt = $this->conn->prepare($sql);

        // Bind dinámico de filtros
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ Contar total de ventas activas
    public function contarVentas($folio = '', $fecha = '')
    {
        $sql = "SELECT COUNT(*) as total FROM ventas v WHERE v.activo = 1";
        $params = [];

        if (!empty($folio)) {
            $sql .= " AND v.folio LIKE :folio";
            $params[':folio'] = "%$folio%";
        }

        if (!empty($fecha)) {
            $sql .= " AND DATE(v.fecha) = :fecha";
            $params[':fecha'] = $fecha;
        }

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // ✅ Obtener una venta específica por ID
    public function obtenerVentaPorId($idVenta)
    {
        $sql = "SELECT v.*,
                    c.nombre AS cliente,
                    u.nombre AS usuario,
                    cj.nombre AS caja,
                    fp.descripcion AS forma_pago,
                    tp.nombre AS tipo_precio
                FROM ventas v
                LEFT JOIN clientes c      ON v.id_cliente = c.id_cliente
                INNER JOIN usuarios u     ON v.id_usuario = u.id_usuario
                INNER JOIN cajas cj       ON v.id_caja = cj.id_caja
                INNER JOIN formas_pago fp ON v.id_forma_pago = fp.id_forma_pago
                INNER JOIN tipo_precio tp ON v.id_tipo_precio = tp.id_tipo_precio
                WHERE v.id_venta = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ✅ Obtener el detalle de productos vendidos en una venta
    public function obtenerDetalleVenta($idVenta)
    {
        $sql = "SELECT vd.*, 
                       p.descripcion AS producto,
                       p.codigo AS codigo
                FROM ventas_detalle vd
                INNER JOIN productos p ON vd.id_producto = p.id_producto
                WHERE vd.id_venta = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $idVenta);
        $stmt->execute();
        return $stmt->fetchAll(); // Devuelve los productos de la venta
    }

    // ✅ Crear una nueva venta con sus productos (detalle)
    public function crearVenta($datosVenta, $detalles)
    {
        try {
            // Iniciar transacción para asegurar integridad de datos
            $this->conn->beginTransaction();

            // Insertar encabezado de venta
            $sqlVenta = "INSERT INTO ventas (folio, fecha, id_cliente, id_usuario, id_caja, total, estatus, activo)
                         VALUES (:folio, :fecha, :id_cliente, :id_usuario, :id_caja, :total, 'Activa', 1)";

            $stmt = $this->conn->prepare($sqlVenta);
            $stmt->execute([
                ':folio' => $datosVenta['folio'],
                ':fecha' => $datosVenta['fecha'],
                ':id_cliente' => $datosVenta['id_cliente'],
                ':id_usuario' => $datosVenta['id_usuario'],
                ':id_caja' => $datosVenta['id_caja'],
                ':total' => $datosVenta['total']
            ]);

            // Obtener ID de la venta recién insertada
            $idVenta = $this->conn->lastInsertId();

            // Insertar los detalles/productos de la venta
            $sqlDetalle = "INSERT INTO ventas_detalle (id_venta, id_producto, cantidad, precio_unitario, subtotal, activo)
                           VALUES (:id_venta, :id_producto, :cantidad, :precio_unitario, :subtotal, 1)";
            $stmtDetalle = $this->conn->prepare($sqlDetalle);

            // Insertar cada producto en el detalle
            foreach ($detalles as $detalle) {
                $stmtDetalle->execute([
                    ':id_venta' => $idVenta,
                    ':id_producto' => $detalle['id_producto'],
                    ':cantidad' => $detalle['cantidad'],
                    ':precio_unitario' => $detalle['precio_unitario'],
                    ':subtotal' => $detalle['subtotal']
                ]);
            }

            // Confirmar la transacción
            $this->conn->commit();
            return 'ok';

        } catch (Exception $e) {
            // Revertir si hay error
            $this->conn->rollBack();
            return 'Error: ' . $e->getMessage();
        }
    }

    // ✅ Cambiar estatus de una venta (ej. Cancelada, Devuelta)
    public function cambiarEstatus($idVenta, $nuevoEstatus)
    {
        $sql = "UPDATE ventas SET estatus = :estatus WHERE id_venta = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':estatus' => $nuevoEstatus,
            ':id' => $idVenta
        ]);
    }

    // ✅ Eliminar venta (borrado lógico)
    public function cancelarVenta($idVenta, $idSucursal, $idUsuario, $motivo = 'Cancelación de venta')
    {
        try {
            $this->conn->beginTransaction();

            // Bloquea venta para lectura consistente
            $st = $this->conn->prepare("SELECT folio, estatus FROM ventas WHERE id_venta = :id FOR UPDATE");
            $st->execute([':id' => $idVenta]);
            $venta = $st->fetch(PDO::FETCH_ASSOC);

            if (!$venta) {
                throw new Exception('Venta no encontrada.');
            }
            if (strcasecmp($venta['estatus'], 'Cancelada') === 0) {
                // Ya cancelada: registrar en bitácora como idempotente y salir
                $this->registrarBitacora($idUsuario, 'ventas', 'CANCEL', $idVenta, 
                    'Intento de cancelar venta ya cancelada', 
                    json_encode(['estatus_prev' => $venta['estatus']]), 
                    json_encode(['estatus_new'  => $venta['estatus']]));
                $this->conn->commit();
                return ['ok' => true, 'msg' => 'La venta ya estaba cancelada.'];
            }

            $folio = $venta['folio'];

            // Traer detalle
            $stDet = $this->conn->prepare(
                "SELECT id_producto, cantidad
                FROM ventas_detalle
                WHERE id_venta = :id AND (activo = 1 OR activo IS NULL)"
            );
            $stDet->execute([':id' => $idVenta]);
            $detalles = $stDet->fetchAll(PDO::FETCH_ASSOC);

            // Preparar statements
            $stUpdProd = $this->conn->prepare(
                "UPDATE productos SET stock_actual = stock_actual + :cant WHERE id_producto = :idp"
            );
            $stMov = $this->conn->prepare(
                "INSERT INTO inventario_movimientos
                (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                VALUES (:idp, 'Devolucion Venta', :cant, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
            );

            // Reponer inventario + movimiento por renglón
            $items = [];
            foreach ($detalles as $d) {
                $cant = (int)$d['cantidad'];
                $idp  = (int)$d['id_producto'];

                $stUpdProd->execute([':cant' => $cant, ':idp' => $idp]);
                $stMov->execute([
                    ':idp'   => $idp,
                    ':cant'  => $cant,
                    ':idsuc' => $idSucursal,
                    ':idusr' => $idUsuario,
                    ':ref'   => $folio,
                    ':mot'   => $motivo
                ]);

                $items[] = ['id_producto' => $idp, 'cantidad' => $cant];
            }

            // Marcar venta cancelada   
            $stVenta = $this->conn->prepare(
                "UPDATE ventas SET estatus = 'Cancelada' WHERE id_venta = :id"
            );
            $stVenta->execute([':id' => $idVenta]);

            // BITÁCORA
            $this->registrarBitacora(
                $idUsuario,
                'ventas',
                'CANCEL',
                $idVenta,
                'Cancelación de venta y devolución a inventario',
                json_encode(['estatus_prev' => $venta['estatus']]),
                json_encode(['estatus_new'  => 'Cancelada', 'devoluciones' => $items])
            );

            $this->conn->commit();
            return ['ok' => true, 'msg' => 'Venta cancelada, stock repuesto y bitácora registrada.'];

        } catch (Exception $e) {
            $this->conn->rollBack();
            // Intentar registrar error en bitácora
            try {
                $this->registrarBitacora($idUsuario, 'ventas', 'ERROR', (int)$idVenta, $e->getMessage());
            } catch (\Throwable $th) {}
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * Registra eventos en bitacora_movimientos.
     * Campos: id_usuario, tabla, accion, registro_id, campo_modificado, valor_anterior, valor_nuevo,
     *         descripcion, ip_origen, activo, fecha
     */
    private function registrarBitacora(
        $idUsuario,
        string $tabla,
        string $accion,
        int $registroId,
        string $descripcion = '',
        ?string $valorAnterior = null,
        ?string $valorNuevo = null,
        ?string $campoModificado = null
    ) {
        // IP origen
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]); // primera ip si viene lista
        }

        $sql = "INSERT INTO bitacora_movimientos
                (id_usuario, tabla, accion, registro_id, campo_modificado, valor_anterior, valor_nuevo,
                descripcion, ip_origen, activo, fecha)
                VALUES
                (:usr, :tbl, :acc, :rid, :campo, :val_ant, :val_nvo, :desc, :ip, 1, NOW())";

        $st = $this->conn->prepare($sql);
        $st->execute([
            ':usr'     => $idUsuario,
            ':tbl'     => $tabla,
            ':acc'     => $accion,        // INSERT|UPDATE|DELETE|LOGIN|LOGOUT|CANCEL|PRINT|ERROR
            ':rid'     => $registroId,
            ':campo'   => $campoModificado,
            ':val_ant' => $valorAnterior,
            ':val_nvo' => $valorNuevo,
            ':desc'    => $descripcion,
            ':ip'      => $ip
        ]);
    }

}
