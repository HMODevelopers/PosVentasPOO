<?php
// Incluir conexión PDO
include_once '../includes/db.php';

class ProductoModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    // ================== LISTADO + CONTAR ==================
    public function listar(int $pagina = 1,int $limite = 10,string $codigo = '', string $descripcion = '', ?int $idProveedor = null) {
        $offset = ($pagina - 1) * $limite;
        $sql = "SELECT
                    p.*,
                    pr.nombre     AS proveedor,
                    u.descripcion AS unidad_sat
                FROM productos p
                LEFT JOIN proveedores  pr ON p.id_proveedor   = pr.id_proveedor
                LEFT JOIN unidades_sat u  ON p.id_unidad_sat  = u.id_unidad_sat
                WHERE p.activo = 1";
        $params = [];

        if ($codigo !== '') {
            $sql .= " AND p.codigo LIKE :codigo";
            $params[':codigo'] = "%{$codigo}%";
        }
        if ($descripcion !== '') {
            $sql .= " AND p.descripcion LIKE :descripcion";
            $params[':descripcion'] = "%{$descripcion}%";
        }
        if (!empty($idProveedor)) {
            $sql .= " AND p.id_proveedor = :idprov";
            $params[':idprov'] = (int)$idProveedor;
        }

        $sql .= " ORDER BY p.id_producto DESC
                LIMIT :limite OFFSET :offset";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar( string $codigo = '', string $descripcion = '',  ?int $idProveedor = null) {
        $sql = "SELECT COUNT(*) AS total
                FROM productos p
                WHERE p.activo = 1";
        $params = [];

        if ($codigo !== '') {
            $sql .= " AND p.codigo LIKE :codigo";
            $params[':codigo'] = "%{$codigo}%";
        }
        if ($descripcion !== '') {
            $sql .= " AND p.descripcion LIKE :descripcion";
            $params[':descripcion'] = "%{$descripcion}%";
        }
        if (!is_null($idProveedor)) {
            $sql .= " AND p.id_proveedor = :idprov";
            $params[':idprov'] = (int)$idProveedor;
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, $k === ':idprov' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ================== CRUD ==================
    public function obtenerPorId(int $id)
    {
        $sql = "SELECT
                    p.*,
                    pr.nombre     AS proveedor,
                    u.descripcion AS unidad_sat
                FROM productos p
                LEFT JOIN proveedores  pr ON pr.id_proveedor = p.id_proveedor
                LEFT JOIN unidades_sat u  ON u.id_unidad_sat = p.id_unidad_sat
                WHERE p.id_producto = :id
                LIMIT 1";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * CREAR producto + inventario + bitácora (estilo Compras)
     * $d debe incluir: 'id_usuario' (int). 'id_sucursal' opcional (default 1).
     * Retorna: ['ok'=>true, 'id_producto'=>ID] | ['ok'=>false,'msg'=>error]
     */
    public function crear(array $d)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO productos
                    (id_proveedor, id_unidad_sat, clave_prod_serv_sat, codigo, descripcion,
                     costo_neto, precio_publico, precio_taller, precio_proveedor,
                     stock_actual, stock_maximo, stock_minimo,
                     piso, pasillo, estante, `peldaño`,
                     activo, fecha_creacion)
                    VALUES
                    (:idprov, :iduni, :clave, :cod, :des,
                     :cn, :ppub, :pt, :ppv,
                     :stk, :stkmax, :stkmin,
                     :piso, :pas, :est, :pel,
                     1, NOW())";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':idprov' => $d['id_proveedor']            ?? null,
                ':iduni'  => $d['id_unidad_sat']           ?? null,
                ':clave'  => $d['clave_prod_serv_sat']     ?? '01010101',  // NOT NULL
                ':cod'    => $d['codigo']                  ?? null,
                ':des'    => trim($d['descripcion'] ?? ''),

                ':cn'     => isset($d['costo_neto'])       ? $d['costo_neto']       : 0,
                ':ppub'   => isset($d['precio_publico'])   ? $d['precio_publico']   : 0,
                ':pt'     => isset($d['precio_taller'])    ? $d['precio_taller']    : 0,
                ':ppv'    => $d['precio_proveedor']        ?? null,

                ':stk'    => $d['stock_actual']            ?? 0,
                ':stkmax' => $d['stock_maximo']            ?? 0,
                ':stkmin' => $d['stock_minimo']            ?? 0,

                ':piso'   => $d['piso']                    ?? 0,
                ':pas'    => $d['pasillo']                 ?? 0,
                ':est'    => $d['estante']                 ?? 0,
                ':pel'    => $d['peldaño']                 ?? 0,
            ]);

            $idProducto   = (int)$this->conn->lastInsertId();
            $idUsuario    = (int)($d['id_usuario']   ?? 0);
            $idSucursal   = !empty($d['id_sucursal']) ? (int)$d['id_sucursal'] : 1;
            $ref          = $d['codigo'] ?? ('PROD-' . $idProducto);
            $stockInicial = (float)($d['stock_actual'] ?? 0);

            // Movimiento de inventario si stock inicial > 0
            if ($stockInicial > 0) {
                $stMov = $this->conn->prepare(
                    "INSERT INTO inventario_movimientos
                     (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                     VALUES (:idp, 'Entrada', :cant, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
                );
                $stMov->execute([
                    ':idp'   => $idProducto,
                    ':cant'  => $stockInicial,
                    ':idsuc' => $idSucursal,
                    ':idusr' => $idUsuario,
                    ':ref'   => $ref,
                    ':mot'   => 'Entrada por alta de producto',
                ]);
            }

            // Bitácora
            $this->registrarBitacora(
                $idUsuario,
                'productos',
                'INSERT',
                $idProducto,
                'Alta de producto (registra movimiento si stock inicial > 0)',
                null,
                json_encode([
                    'codigo'        => $d['codigo'] ?? null,
                    'stock_inicial' => $stockInicial
                ], JSON_UNESCAPED_UNICODE)
            );

            $this->conn->commit();
            return ['ok' => true, 'id_producto' => $idProducto];

        } catch (Exception $e) {
            $this->conn->rollBack();
            try {
                $this->registrarBitacora((int)($d['id_usuario'] ?? 0), 'productos', 'ERROR', 0, $e->getMessage());
            } catch (\Throwable $th) {}
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * ACTUALIZAR producto + inventario (delta) + bitácora (estilo Compras)
     * $d puede incluir: 'stock_actual' (para calcular delta), 'id_usuario', 'id_sucursal'
     */
    public function actualizar(int $id, array $d)
    {
        try {
            $this->conn->beginTransaction();

            // 1) Estado previo con lock
            $stPrev = $this->conn->prepare("SELECT * FROM productos WHERE id_producto = :id FOR UPDATE");
            $stPrev->execute([':id' => $id]);
            $prev = $stPrev->fetch(PDO::FETCH_ASSOC);
            if (!$prev) {
                throw new Exception('Producto no encontrado.');
            }

            // 2) Normaliza valores nuevos tomando lo que venga en $d o lo previo
            $numericFields = [
                'costo_neto','precio_publico','precio_taller','precio_proveedor',
                'stock_actual','stock_maximo','stock_minimo','piso','pasillo','estante','peldaño'
            ];
            $textFields = [
                'id_proveedor','id_unidad_sat','clave_prod_serv_sat','codigo','descripcion'
            ];

            $new = [];
            // texto / llaves
            $new['id_proveedor']        = array_key_exists('id_proveedor',$d)        ? $d['id_proveedor']        : $prev['id_proveedor'];
            $new['id_unidad_sat']       = array_key_exists('id_unidad_sat',$d)       ? $d['id_unidad_sat']       : $prev['id_unidad_sat'];
            $new['clave_prod_serv_sat'] = array_key_exists('clave_prod_serv_sat',$d) ? $d['clave_prod_serv_sat'] : $prev['clave_prod_serv_sat'];
            $new['codigo']              = array_key_exists('codigo',$d)              ? $d['codigo']              : $prev['codigo'];
            $new['descripcion']         = array_key_exists('descripcion',$d)         ? trim($d['descripcion'])   : $prev['descripcion'];
            // numéricos
            foreach ($numericFields as $f) {
                $new[$f] = array_key_exists($f,$d) ? $d[$f] : $prev[$f];
            }

            // 3) Detecta cambios campo a campo
            $changes = []; // ['campo'=>['old'=>..., 'new'=>..., 'numeric'=>bool]]
            $isDiff = function($old, $new, $numeric = false) {
                if ($numeric) return (float)$old != (float)$new; // comparación numérica simple
                return (string)$old !== (string)$new;
            };

            foreach ($textFields as $f) {
                if ($isDiff($prev[$f], $new[$f], false)) {
                    $changes[$f] = ['old'=>$prev[$f], 'new'=>$new[$f], 'numeric'=>false];
                }
            }
            foreach ($numericFields as $f) {
                if ($isDiff($prev[$f], $new[$f], true)) {
                    $changes[$f] = ['old'=>$prev[$f], 'new'=>$new[$f], 'numeric'=>true];
                }
            }

            // 4) Ejecuta UPDATE principal
            $sql = "UPDATE productos
                    SET id_proveedor = :idprov,
                        id_unidad_sat = :iduni,
                        clave_prod_serv_sat = :clave,
                        codigo = :cod,
                        descripcion = :des,
                        costo_neto = :cn,
                        precio_publico = :ppub,
                        precio_taller = :pt,
                        precio_proveedor = :ppv,
                        stock_actual = :stk,
                        stock_maximo = :stkmax,
                        stock_minimo = :stkmin,
                        piso = :piso,
                        pasillo = :pas,
                        estante = :est,
                        `peldaño` = :pel
                    WHERE id_producto = :id";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':idprov' => $new['id_proveedor'],
                ':iduni'  => $new['id_unidad_sat'],
                ':clave'  => $new['clave_prod_serv_sat'],
                ':cod'    => $new['codigo'],
                ':des'    => $new['descripcion'],

                ':cn'     => $new['costo_neto'],
                ':ppub'   => $new['precio_publico'],
                ':pt'     => $new['precio_taller'],
                ':ppv'    => $new['precio_proveedor'],

                ':stk'    => $new['stock_actual'],
                ':stkmax' => $new['stock_maximo'],
                ':stkmin' => $new['stock_minimo'],

                ':piso'   => $new['piso'],
                ':pas'    => $new['pasillo'],
                ':est'    => $new['estante'],
                ':pel'    => $new['peldaño'],
                ':id'     => $id
            ]);

            // 5) Movimientos de inventario según cambios
            $idUsuario = (int)($d['id_usuario']   ?? 0);
            $idSucursal= !empty($d['id_sucursal']) ? (int)$d['id_sucursal'] : 1;
            $ref       = $prev['codigo'] ? ('EDIT-' . $prev['codigo']) : ('PROD-' . $id);

            // 5.1) Si cambió el stock => movimiento por delta
            if (isset($changes['stock_actual'])) {
                $delta = (float)$new['stock_actual'] - (float)$prev['stock_actual'];
                if ($delta != 0.0) {
                    $tipo = $delta > 0 ? 'Entrada' : 'Salida';
                    $stMov = $this->conn->prepare(
                        "INSERT INTO inventario_movimientos
                        (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                        VALUES (:idp, :tipo, :cant, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
                    );
                    $stMov->execute([
                        ':idp'   => $id,
                        ':tipo'  => $tipo,
                        ':cant'  => abs($delta),
                        ':idsuc' => $idSucursal,
                        ':idusr' => $idUsuario,
                        ':ref'   => $ref,
                        ':mot'   => 'Ajuste de inventario por edición de stock',
                    ]);
                }
            }

            // 5.2) Si cambió el precio_proveedor => registrar ajuste (cantidad 0)
            if (isset($changes['precio_proveedor'])) {
                $stMov = $this->conn->prepare(
                    "INSERT INTO inventario_movimientos
                    (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                    VALUES (:idp, 'Ajuste', 0, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
                );
                $stMov->execute([
                    ':idp'   => $id,
                    ':idsuc' => $idSucursal,
                    ':idusr' => $idUsuario,
                    ':ref'   => $ref,
                    ':mot'   => 'Cambio de precio del proveedor (sin movimiento de cantidad)',
                ]);
            }

            // 5.3) Si cambió el proveedor => registrar ajuste (cantidad 0)
            if (isset($changes['id_proveedor'])) {
                $stMov = $this->conn->prepare(
                    "INSERT INTO inventario_movimientos
                    (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                    VALUES (:idp, 'Ajuste', 0, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
                );
                $stMov->execute([
                    ':idp'   => $id,
                    ':idsuc' => $idSucursal,
                    ':idusr' => $idUsuario,
                    ':ref'   => $ref,
                    ':mot'   => 'Cambio de proveedor (sin movimiento de cantidad)',
                ]);
            }

            // 6) Bitácora: un registro POR CAMPO modificado
            if (!empty($changes)) {
                foreach ($changes as $campo => $info) {
                    $this->registrarBitacora(
                        $idUsuario,
                        'productos',
                        'UPDATE',
                        $id,
                        'Actualización de campo',
                        (string)$info['old'],
                        (string)$info['new'],
                        $campo
                    );
                }
            } else {
                // Nada cambió realmente; aún así registra intento de edición sin cambios
                $this->registrarBitacora(
                    $idUsuario,
                    'productos',
                    'UPDATE',
                    $id,
                    'Edición sin cambios efectivos'
                );
            }

            $this->conn->commit();
            return ['ok' => true, 'id_producto' => $id];

        } catch (Exception $e) {
            $this->conn->rollBack();
            try {
                $this->registrarBitacora((int)($d['id_usuario'] ?? 0), 'productos', 'ERROR', $id, $e->getMessage());
            } catch (\Throwable $th) {}
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }


    /**
     * ELIMINAR (soft-delete) + salida inventario + bitácora (estilo Compras::cancelarCompra)
     * Firma al estilo Compras: $idProducto, $idSucursal, $idUsuario, $motivo (opcional)
     */
    public function eliminar(int $idProducto, int $idSucursal, int $idUsuario, string $motivo = 'Desactivación de producto')
    {
        try {
            $this->conn->beginTransaction();

            // Lock y datos previos
            $st = $this->conn->prepare("SELECT codigo, stock_actual FROM productos WHERE id_producto = :id FOR UPDATE");
            $st->execute([':id' => (int)$idProducto]);
            $prev = $st->fetch(PDO::FETCH_ASSOC);
            if (!$prev) {
                throw new Exception('Producto no encontrado.');
            }

            $stock = (float)$prev['stock_actual'];
            $ref   = $prev['codigo'] ?? ('PROD-' . $idProducto);

            // Si hay stock, salida y poner a 0
            if ($stock > 0) {
                $stMov = $this->conn->prepare(
                    "INSERT INTO inventario_movimientos
                     (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                     VALUES (:idp, 'Salida', :cant, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
                );
                $stMov->execute([
                    ':idp'   => (int)$idProducto,
                    ':cant'  => $stock,
                    ':idsuc' => (int)$idSucursal,
                    ':idusr' => (int)$idUsuario,
                    ':ref'   => 'BAJA-' . $ref,
                    ':mot'   => $motivo,
                ]);

                $stUpd = $this->conn->prepare("UPDATE productos SET stock_actual = 0 WHERE id_producto = :id");
                $stUpd->execute([':id' => (int)$idProducto]);
            }

            // Soft delete
            $stDel = $this->conn->prepare("UPDATE productos SET activo = 0 WHERE id_producto = :id");
            $stDel->execute([':id' => (int)$idProducto]);

            // Bitácora
            $this->registrarBitacora(
                (int)$idUsuario,
                'productos',
                'DELETE',
                (int)$idProducto,
                'Desactivación de producto (soft-delete)',
                json_encode(['activo'=>1, 'stock'=>$stock], JSON_UNESCAPED_UNICODE),
                json_encode(['activo'=>0, 'stock'=>0],     JSON_UNESCAPED_UNICODE)
            );

            $this->conn->commit();
            return ['ok' => true, 'msg' => 'Producto desactivado y stock ajustado.'];

        } catch (Exception $e) {
            $this->conn->rollBack();
            try {
                $this->registrarBitacora((int)$idUsuario, 'productos', 'ERROR', (int)$idProducto, $e->getMessage());
            } catch (\Throwable $th) {}
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    // ================== PARA SELECTS/AUTOCOMPLETE ==================
    public function buscarMin(string $q = '', int $limite = 50)
    {
        $lim = max(1, (int)$limite);

        $sql = "SELECT id_producto,
                    codigo,
                    descripcion,
                    precio_proveedor
                FROM productos
                WHERE activo = 1";

        $useQ = ($q !== '');
        if ($useQ) {
            $sql .= " AND (codigo LIKE :q1 OR descripcion LIKE :q2)";
        }

        $sql .= " ORDER BY descripcion ASC LIMIT {$lim}";

        $st = $this->conn->prepare($sql);

        if ($useQ) {
            $like = "%{$q}%";
            $st->bindValue(':q1', $like, PDO::PARAM_STR);
            $st->bindValue(':q2', $like, PDO::PARAM_STR);
        }

        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================== UTILIDADES (autocálculo) ==================
    public function simularPrecios(?int $idProveedor, float $precioProveedor): array
    {
        $nomProv = $this->obtenerNombreProveedor($idProveedor);
        [$cn, $pb, $pt] = $this->calcularPreciosPorProveedor((float)$precioProveedor, $nomProv);
        return ['costo_neto' => $cn, 'precio_publico' => $pb, 'precio_taller' => $pt];
    }

    private function obtenerNombreProveedor(?int $id): string
    {
        if (empty($id)) return '';
        $st = $this->conn->prepare("SELECT nombre FROM proveedores WHERE id_proveedor = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? (string)$row['nombre'] : '';
    }

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

    // ================== BITÁCORA ==================
    private function registrarBitacora($idUsuario, string $tabla, string $accion,int $registroId,string $descripcion = '',?string $valorAnterior = null, ?string $valorNuevo = null, ?string $campoModificado = null) {
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
            ':acc'     => $accion,   // INSERT|UPDATE|DELETE|ERROR
            ':rid'     => (int)$registroId,
            ':campo'   => $campoModificado,
            ':val_ant' => $valorAnterior,
            ':val_nvo' => $valorNuevo,
            ':desc'    => $descripcion,
            ':ip'      => $ip
        ]);
    }
}
