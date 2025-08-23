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
    // CREAR COMPRA + INVENTARIO + PRECIOS PRODUCTO (ACTUALIZADO)
    // =========================
    /**
     * $datosCompra = [
     *   'id_usuario', 'id_proveedor', 'id_sucursal' (nullable),
     *   'folio_factura' (nullable), 'fecha_factura' (Y-m-d nullable),
     *   'estatus' => 'Pendiente'|'Pagada'|'Parcial'|'Cancelada' (default 'Pendiente'),
     *   'tipo_movimiento' => 'Entrada'|'Salida'|'Ajuste'|'Devolucion Venta'|'Devolucion Compra' (opcional)
     * ]
     * $detalles = [
     *   ['id_producto'=>X, 'cantidad'=>N, 'precio_unitario'=>PPV, 'subtotal'=>opc, 'tipo_movimiento'=>opc]
     *   // precio_unitario = PPV (precio de factura/proveedor)
     * ]
     */
    public function crearCompra(array $datosCompra, array $detalles)
    {
        try {
            $this->conn->beginTransaction();

            // Suma total y normalización de subtotales (PPV * cantidad)
            $total = 0.0;
            foreach ($detalles as &$d) {
                $cant = (float)($d['cantidad'] ?? 0);
                $ppv  = (float)($d['precio_unitario'] ?? 0); // PPV de factura
                if (!isset($d['subtotal'])) {
                    $d['subtotal'] = round($cant * $ppv, 2);
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

            $idCompra   = (int)$this->conn->lastInsertId();
            $ref        = $datosCompra['folio_factura'] ?? ('COMP-' . $idCompra);
            $idSucursal = !empty($datosCompra['id_sucursal']) ? (int)$datosCompra['id_sucursal'] : 1;
            $idUsuario  = (int)$datosCompra['id_usuario'];

            // Nombre del proveedor (para reglas)
            $stProvNom = $this->conn->prepare("SELECT LOWER(TRIM(nombre)) FROM proveedores WHERE id_proveedor = :id LIMIT 1");
            $stProvNom->execute([':id' => (int)$datosCompra['id_proveedor']]);
            $provNombre = (string)$stProvNom->fetchColumn();

            // Tipos permitidos (enum)
            $tiposPermitidos = ['Entrada','Salida','Ajuste','Devolucion Venta','Devolucion Compra'];
            $tipoPorCompra   = $datosCompra['tipo_movimiento'] ?? 'Entrada';
            if (!in_array($tipoPorCompra, $tiposPermitidos, true)) {
                $tipoPorCompra = 'Entrada';
            }

            // Insert detalle
            $stDet = $this->conn->prepare(
                "INSERT INTO compras_detalle
                 (id_compra, id_producto, cantidad, precio_unitario, subtotal, activo, fecha_creacion)
                 VALUES
                 (:idc, :idp, :cant, :prec, :subt, 1, NOW())"
            );

            // Update de stock
            $stUpdStock = $this->conn->prepare(
                "UPDATE productos SET stock_actual = stock_actual + :delta WHERE id_producto = :idp"
            );

            // ==== ACTUALIZAR PRECIOS DE PRODUCTO (según tu esquema actual)
            // PPV -> productos.precio_lista
            // PB  -> productos.precio_venta
            // PT  -> productos.precio_taller
            // CN  -> productos.costo_neto
            $stUpdPrecios = $this->conn->prepare(
                "UPDATE productos
                 SET precio_proveedor = :ppv,
                     costo_neto       = :cn,
                     precio_publico   = :pb,
                     precio_taller    = :pt
                 WHERE id_producto    = :idp"
            );

            // Movimiento de inventario
            $stMov = $this->conn->prepare(
                "INSERT INTO inventario_movimientos
                 (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                 VALUES (:idp, :tipo, :cant, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
            );

            foreach ($detalles as $d) {
                $idProducto = (int)$d['id_producto'];
                $cantidad   = (float)$d['cantidad'];
                $ppv        = (float)$d['precio_unitario']; // PPV de factura
                $precio     = number_format($ppv, 2, '.', '');
                $subtotal   = number_format((float)$d['subtotal'], 2, '.', '');

                // 1) Insertar detalle
                $stDet->execute([
                    ':idc'  => $idCompra,
                    ':idp'  => $idProducto,
                    ':cant' => $cantidad,
                    ':prec' => $precio,
                    ':subt' => $subtotal,
                ]);

                // 2) Tipo de movimiento (renglón o compra)
                $tipoMov = $d['tipo_movimiento'] ?? $tipoPorCompra;
                if (!in_array($tipoMov, $tiposPermitidos, true)) {
                    $tipoMov = 'Entrada';
                }

                // 3) Delta stock
                $delta = 0.0;
                switch ($tipoMov) {
                    case 'Entrada':
                    case 'Devolucion Venta':
                        $delta = +abs($cantidad);
                        break;
                    case 'Salida':
                    case 'Devolucion Compra':
                        $delta = -abs($cantidad);
                        break;
                    case 'Ajuste':
                        $delta = $cantidad; // puede ser negativo o positivo
                        break;
                }

                // 4) Actualizar stock
                if ($delta != 0.0) {
                    $stUpdStock->execute([
                        ':delta' => $delta,
                        ':idp'   => $idProducto,
                    ]);
                }

                // 5) Calcular y actualizar precios del producto
                [$cn, $pb, $pt] = $this->calcularPreciosPorProveedor($ppv, $provNombre);
                $stUpdPrecios->execute([
                    ':ppv' => number_format($ppv, 2, '.', ''),
                    ':cn'  => number_format($cn,  2, '.', ''),
                    ':pb'  => number_format($pb,  2, '.', ''),
                    ':pt'  => number_format($pt,  2, '.', ''),
                    ':idp' => $idProducto
                ]);

                // 6) Movimiento de inventario
                switch ($tipoMov) {
                    case 'Entrada':
                        $motivo = (isset($d['tipo_movimiento']) && $d['tipo_movimiento'] === 'Entrada')
                                  ? 'Entrada por adición de stock'
                                  : 'Entrada por compra';
                        break;
                    case 'Salida':            $motivo = 'Salida de stock'; break;
                    case 'Ajuste':            $motivo = 'Ajuste de inventario'; break;
                    case 'Devolucion Venta':  $motivo = 'Entrada por devolución de venta'; break;
                    case 'Devolucion Compra': $motivo = 'Salida por devolución a proveedor'; break;
                    default:                  $motivo = 'Movimiento de inventario';
                }

                $stMov->execute([
                    ':idp'   => $idProducto,
                    ':tipo'  => $tipoMov,
                    ':cant'  => abs($cantidad),
                    ':idsuc' => $idSucursal,
                    ':idusr' => $idUsuario,
                    ':ref'   => $ref,
                    ':mot'   => $motivo,
                ]);
            }

            // Bitácora
            $this->registrarBitacora(
                $idUsuario,
                'compras',
                'INSERT',
                $idCompra,
                'Alta de compra con detalle (actualiza precios y stock)',
                null,
                json_encode([
                    'total'           => $total,
                    'folio'           => $ref,
                    'proveedor'       => $provNombre,
                    'tipo_movimiento' => $tipoPorCompra
                ], JSON_UNESCAPED_UNICODE)
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
                 VALUES (:idp, 'Devolucion Compra', :cant, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
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

    // =========================
    // Reglas por proveedor (en código, como indicaste)
    // =========================
    /**
     * Calcula [costo_neto (CN), precio_venta (PB), precio_taller (PT)]
     * a partir del precio_proveedor (PPV) y el nombre del proveedor.
     * Defaults: CN = ppv*IVA, PB = (ppv*1.8)*IVA, PT = PB*0.8
     */
    private function calcularPreciosPorProveedor(float $ppv, string $provNombre): array
    {
        $ppv = max(0.0, $ppv);
        $nom = strtolower(trim($provNombre));
        $IVA = 1.16;

        // Defaults
        $CN = $ppv * $IVA;
        $PB = ($ppv * 1.8) * $IVA;
        $PT = $PB * 0.8;

        switch ($nom) {
            case 'permor':
                $CN = $ppv * 0.64 * $IVA * 0.89 * 0.95;
                $PB = $ppv * 1.024;
                $PT = $PB / 1.25;
                break;

            case 'apymsa':
                $CN = $ppv * 1.044;
                $PB = $ppv * 1.70694;
                $PT = $ppv * 1.365552; // (= PB / 1.25)
                break;

            case 'bdh':
                $CN = $ppv;
                $PB = $ppv * $IVA;
                $PT = $ppv;
                break;

            case 'switchero':
                $CN = $ppv;
                $PB = $ppv * 1.8125;
                $PT = $ppv * 1.45;
                break;

            case 'serva':
            case 'dirco':
            case 'ciosa':
            case 'diriego':
            case 'delatsa':
            case 'calderon':
            case 'visa':
                $CN = $ppv * $IVA;
                $PB = ($ppv * 1.8) * $IVA;
                $PT = $PB * 0.8;
                break;
        }

        return [round($CN, 2), round($PB, 2), round($PT, 2)];
    }
}
