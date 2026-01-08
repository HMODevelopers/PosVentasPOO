<?php
// Incluir conexión PDO (debe exponer $pdo)
include_once '../includes/db.php';

class KardexProductoModel
{
    /** @var PDO */
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    public function obtenerComprasPorProducto(int $idProducto, string $desde = '', string $hasta = ''): array
    {
        $sql = "SELECT
                    cd.id_compra_detalle,
                    cd.id_compra,
                    c.folio_factura AS folio,
                    c.fecha_factura AS fecha,
                    cd.cantidad,
                    cd.precio_unitario AS precio_proveedor,
                    pr.nombre AS proveedor,
                    p.codigo,
                    p.descripcion AS producto
                FROM compras_detalle cd
                INNER JOIN compras c ON cd.id_compra = c.id_compra
                INNER JOIN productos p ON cd.id_producto = p.id_producto
                LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
                WHERE cd.activo = 1
                  AND c.activo = 1
                  AND cd.id_producto = :id_producto";

        $params = [':id_producto' => $idProducto];

        if (!empty($desde)) {
            $sql .= " AND DATE(c.fecha_factura) >= :desde";
            $params[':desde'] = $desde;
        }
        if (!empty($hasta)) {
            $sql .= " AND DATE(c.fecha_factura) <= :hasta";
            $params[':hasta'] = $hasta;
        }

        $sql .= " ORDER BY c.fecha_factura DESC, cd.id_compra_detalle DESC";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerVentasPorProducto(int $idProducto, string $desde = '', string $hasta = ''): array
    {
        $sql = "SELECT
                    d.id_venta_detalle,
                    d.id_venta,
                    v.folio,
                    v.fecha,
                    d.cantidad,
                    d.precio_unitario,
                    p.codigo,
                    p.descripcion AS producto
                FROM ventas_detalle d
                INNER JOIN ventas v ON d.id_venta = v.id_venta
                INNER JOIN productos p ON d.id_producto = p.id_producto
                WHERE v.activo = 1
                  AND (d.activo = 1 OR d.activo IS NULL)
                  AND d.id_producto = :id_producto";

        $params = [':id_producto' => $idProducto];

        if (!empty($desde)) {
            $sql .= " AND DATE(v.fecha) >= :desde";
            $params[':desde'] = $desde;
        }
        if (!empty($hasta)) {
            $sql .= " AND DATE(v.fecha) <= :hasta";
            $params[':hasta'] = $hasta;
        }

        $sql .= " ORDER BY v.fecha DESC, d.id_venta_detalle DESC";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
