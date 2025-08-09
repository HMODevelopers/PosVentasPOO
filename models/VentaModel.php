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

        $sql .= " ORDER BY v.id_venta DESC
                LIMIT :limite OFFSET :offset";

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
        $sql = "SELECT * FROM ventas WHERE id_venta = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $idVenta);
        $stmt->execute();
        return $stmt->fetch(); // Devuelve una sola venta
    }

    // ✅ Obtener el detalle de productos vendidos en una venta
    public function obtenerDetalleVenta($idVenta)
    {
        $sql = "SELECT vd.*, 
                       p.nombre AS producto
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
    public function eliminarVenta($idVenta)
    {
        $sql = "UPDATE ventas SET activo = 0 WHERE id_venta = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $idVenta]);
    }
}
