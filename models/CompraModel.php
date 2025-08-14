<?php
// Incluir conexión PDO (usa la configuración de includes/db.php)
include_once '../includes/db.php';

class CompraModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    // =========================
    // LISTADO + CONTADOR
    // =========================
    public function obtenerCompras($pagina = 1, $limite = 10, $folio = '', $fecha = '', $estatus = '', $idProveedor = null)
    {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT c.*,
                       pr.nombre    AS proveedor,
                       u.nombre     AS usuario,
                       s.nombre     AS sucursal
                FROM compras c
                INNER JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
                INNER JOIN usuarios    u  ON c.id_usuario   = u.id_usuario
                LEFT  JOIN sucursales  s  ON c.id_sucursal  = s.id_sucursal
                WHERE c.activo = 1";
        $params = [];

        if (!empty($folio)) {
            $sql .= " AND c.folio_factura LIKE :folio";
            $params[':folio'] = "%{$folio}%";
        }
        if (!empty($fecha)) {
            // Filtra por fecha de factura; si prefieres fecha_creacion, cambia aquí
            $sql .= " AND DATE(c.fecha_factura) = :fecha";
            $params[':fecha'] = $fecha;
        }
        if (!empty($estatus)) {
            $sql .= " AND c.estatus = :estatus";
            $params[':estatus'] = $estatus; // Pendiente|Pagada|Parcial|Cancelada
        }
        if (!empty($idProveedor)) {
            $sql .= " AND c.id_proveedor = :idprov";
            $params[':idprov'] = (int)$idProveedor;
        }

        $sql .= " ORDER BY c.id_compra DESC LIMIT :limite OFFSET :offset";

        $st = $this->conn->prepare($sql);

        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarCompras($folio = '', $fecha = '', $estatus = '', $idProveedor = null)
    {
        $sql = "SELECT COUNT(*) AS total
                FROM compras c
                WHERE c.activo = 1";
        $params = [];

        if (!empty($folio)) {
            $sql .= " AND c.folio_factura LIKE :folio";
            $params[':folio'] = "%{$folio}%";
        }
        if (!empty($fecha)) {
            $sql .= " AND DATE(c.fecha_factura) = :fecha";
            $params[':fecha'] = $fecha;
        }
        if (!empty($estatus)) {
            $sql .= " AND c.estatus = :estatus";
            $params[':estatus'] = $estatus;
        }
        if (!empty($idProveedor)) {
            $sql .= " AND c.id_proveedor = :idprov";
            $params[':idprov'] = (int)$idProveedor;
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        return (int)$st->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // =========================
    // OBTENER UNA COMPRA
    // =========================
    public function obtenerCompraPorId($idCompra)
    {
        $sql = "SELECT c.*,
                       pr.nombre    AS proveedor,
                       u.nombre     AS usuario,
                       s.nombre     AS sucursal
                FROM compras c
                INNER JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
                INNER JOIN usuarios    u  ON c.id_usuario   = u.id_usuario
                LEFT  JOIN sucursales  s  ON c.id_sucursal  = s.id_sucursal
                WHERE c.id_compra = :id
                LIMIT 1";

        $st = $this->conn->prepare($sql);
        $st->bindValue(':id', (int)$idCompra, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerDetalleCompra($idCompra)
    {
        $sql = "SELECT cd.*,
                       p.descripcion AS producto,
                       p.codigo      AS codigo
                FROM compras_detalle cd
                INNER JOIN productos p ON cd.id_producto = p.id_producto
                WHERE cd.id_compra = :id
                ORDER BY cd.id_compra_detalle ASC";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':id', (int)$idCompra, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================
    // CREAR COMPRA + INVENTARIO
    // =========================
    /**
     * $datosCompra = [
     *   'id_usuario', 'id_proveedor', 'id_sucursal' (nullable),
     *   'folio_factura' (nullable), 'fecha_factura' (Y-m-d nullable),
     *   'estatus' => 'Pendiente'|'Pagada'|'Parcial'|'Cancelada' (default 'Pendiente')
     * ]
     * $detalles = [
     *   ['id_producto'=>X, 'cantidad'=>N, 'precio_unitario'=>P, 'subtotal'=>opc] // subtotal opcional, se calcula N*P
     * ]
     */
    public function crearCompra(array $datosCompra, array $detalles)
    {
        try {
            $this->conn->beginTransaction();

            // Suma total
            $total = 0.0;
            foreach ($detalles as &$d) {
                $cant = (float)$d['cantidad'];
                $prec = (float)$d['precio_unitario'];
                if (!isset($d['subtotal'])) {
                    $d['subtotal'] = round($cant * $prec, 2);
                }
                $total += (float)$d['subtotal'];
            }
            unset($d);

            // Insert encabezado
            $sqlCab = "INSERT INTO compras
                       (id_usuario, id_proveedor, id_sucursal, folio_factura, fecha_factura,
                        total, estatus, activo, fecha_creacion)
                       VALUES
                       (:usr, :prov, :suc, :folio, :ffac, :tot, :est, 1, NOW())";
            $stCab = $this->conn->prepare($sqlCab);
            $stCab->execute([
                ':usr'   => (int)$datosCompra['id_usuario'],
                ':prov'  => (int)$datosCompra['id_proveedor'],
                ':suc'   => !empty($datosCompra['id_sucursal']) ? (int)$datosCompra['id_sucursal'] : null,
                ':folio' => $datosCompra['folio_factura'] ?? null,
                ':ffac'  => $datosCompra['fecha_factura'] ?? null,
                ':tot'   => number_format((float)$total, 2, '.', ''),
                ':est'   => $datosCompra['estatus'] ?? 'Pendiente',
            ]);

            $idCompra = (int)$this->conn->lastInsertId();

            // Insert detalle
            $sqlDet = "INSERT INTO compras_detalle
                       (id_compra, id_producto, cantidad, precio_unitario, subtotal, activo, fecha_creacion)
                       VALUES
                       (:idc, :idp, :cant, :prec, :subt, 1, NOW())";
            $stDet = $this->conn->prepare($sqlDet);

            // Prep statements inventario
            $stUpdProd = $this->conn->prepare(
                "UPDATE productos SET stock_actual = stock_actual + :cant WHERE id_producto = :idp"
            );
            $stMov = $this->conn->prepare(
                "INSERT INTO inventario_movimientos
                 (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                 VALUES (:idp, 'Compra', :cant, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
            );

            $ref = $datosCompra['folio_factura'] ?? ('COMP-' . $idCompra);

            foreach ($detalles as $d) {
                $stDet->execute([
                    ':idc'  => $idCompra,
                    ':idp'  => (int)$d['id_producto'],
                    ':cant' => (float)$d['cantidad'],
                    ':prec' => number_format((float)$d['precio_unitario'], 2, '.', ''),
                    ':subt' => number_format((float)$d['subtotal'], 2, '.', ''),
                ]);

                // Actualizar stock
                $stUpdProd->execute([
                    ':cant' => (float)$d['cantidad'],
                    ':idp'  => (int)$d['id_producto'],
                ]);

                // Movimiento de inventario
                $stMov->execute([
                    ':idp'   => (int)$d['id_producto'],
                    ':cant'  => (float)$d['cantidad'],
                    ':idsuc' => !empty($datosCompra['id_sucursal']) ? (int)$datosCompra['id_sucursal'] : 1,
                    ':idusr' => (int)$datosCompra['id_usuario'],
                    ':ref'   => $ref,
                    ':mot'   => 'Entrada por compra',
                ]);
            }

            // Bitácora
            $this->registrarBitacora(
                (int)$datosCompra['id_usuario'],
                'compras',
                'INSERT',
                $idCompra,
                'Alta de compra con detalle',
                null,
                json_encode(['total' => $total, 'folio' => $ref], JSON_UNESCAPED_UNICODE)
            );

            $this->conn->commit();
            return ['ok' => true, 'id_compra' => $idCompra];

        } catch (Exception $e) {
            $this->conn->rollBack();
            try {
                $this->registrarBitacora((int)($datosCompra['id_usuario'] ?? 0), 'compras', 'ERROR', 0, $e->getMessage());
            } catch (\Throwable $th) {}
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    // =========================
    // CANCELAR COMPRA (reversa)
    // =========================
    public function cancelarCompra($idCompra, $idSucursal, $idUsuario, $motivo = 'Cancelación de compra')
    {
        try {
            $this->conn->beginTransaction();

            // Bloquear compra
            $st = $this->conn->prepare("SELECT estatus, folio_factura FROM compras WHERE id_compra = :id FOR UPDATE");
            $st->execute([':id' => (int)$idCompra]);
            $c = $st->fetch(PDO::FETCH_ASSOC);
            if (!$c) {
                throw new Exception('Compra no encontrada.');
            }
            if (strcasecmp($c['estatus'], 'Cancelada') === 0) {
                $this->registrarBitacora($idUsuario, 'compras', 'CANCEL', (int)$idCompra, 'Intento doble cancelación');
                $this->conn->commit();
                return ['ok' => true, 'msg' => 'La compra ya estaba cancelada.'];
            }

            // Detalle
            $stDet = $this->conn->prepare(
                "SELECT id_producto, cantidad FROM compras_detalle WHERE id_compra = :id"
            );
            $stDet->execute([':id' => (int)$idCompra]);
            $detalles = $stDet->fetchAll(PDO::FETCH_ASSOC);

            // Prep updates
            $stUpdProd = $this->conn->prepare(
                "UPDATE productos SET stock_actual = stock_actual - :cant WHERE id_producto = :idp"
            );
            $stMov = $this->conn->prepare(
                "INSERT INTO inventario_movimientos
                 (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                 VALUES (:idp, 'Dev_Compra', :cant, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
            );

            $ref = $c['folio_factura'] ?? ('COMP-' . $idCompra);
            $items = [];

            foreach ($detalles as $d) {
                $cant = (float)$d['cantidad'];
                $idp  = (int)$d['id_producto'];

                $stUpdProd->execute([':cant' => $cant, ':idp' => $idp]);
                $stMov->execute([
                    ':idp'   => $idp,
                    ':cant'  => $cant,
                    ':idsuc' => (int)$idSucursal,
                    ':idusr' => (int)$idUsuario,
                    ':ref'   => $ref,
                    ':mot'   => $motivo,
                ]);

                $items[] = ['id_producto' => $idp, 'cantidad' => $cant];
            }

            // Marcar como cancelada
            $stUp = $this->conn->prepare("UPDATE compras SET estatus = 'Cancelada' WHERE id_compra = :id");
            $stUp->execute([':id' => (int)$idCompra]);

            // Bitácora
            $this->registrarBitacora(
                (int)$idUsuario,
                'compras',
                'CANCEL',
                (int)$idCompra,
                'Cancelación de compra y reversa de inventario',
                null,
                json_encode(['items' => $items], JSON_UNESCAPED_UNICODE)
            );

            $this->conn->commit();
            return ['ok' => true, 'msg' => 'Compra cancelada y stock ajustado.'];

        } catch (Exception $e) {
            $this->conn->rollBack();
            try {
                $this->registrarBitacora((int)$idUsuario, 'compras', 'ERROR', (int)$idCompra, $e->getMessage());
            } catch (\Throwable $th) {}
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    // =========================
    // UTILITARIOS
    // =========================
    public function actualizarTotal($idCompra)
    {
        $sql = "UPDATE compras c
                JOIN (
                    SELECT id_compra, COALESCE(SUM(subtotal),0) AS tot
                    FROM compras_detalle
                    WHERE id_compra = :id
                ) x ON x.id_compra = c.id_compra
                SET c.total = x.tot
                WHERE c.id_compra = :id";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':id', (int)$idCompra, PDO::PARAM_INT);
        return $st->execute();
    }

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
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $sql = "INSERT INTO bitacora_movimientos
                (id_usuario, tabla, accion, registro_id, campo_modificado, valor_anterior, valor_nuevo,
                 descripcion, ip_origen, activo, fecha)
                VALUES
                (:usr, :tbl, :acc, :rid, :campo, :val_ant, :val_nvo, :desc, :ip, 1, NOW())";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':usr'     => (int)$idUsuario,
            ':tbl'     => $tabla,
            ':acc'     => $accion,   // INSERT|UPDATE|DELETE|CANCEL|ERROR
            ':rid'     => (int)$registroId,
            ':campo'   => $campoModificado,
            ':val_ant' => $valorAnterior,
            ':val_nvo' => $valorNuevo,
            ':desc'    => $descripcion,
            ':ip'      => $ip
        ]);
    }
}
