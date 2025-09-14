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
    public function listar(int $pagina = 1,int $limite = 10,string $codigo = '',string $descripcion = '',?int $idProveedor = null, ?int $idGrupo = null) 
    {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT
                    p.*,
                    pr.nombre       AS proveedor,
                    u.descripcion   AS unidad_sat,
                    g.nombre_grupo  AS grupo       -- NUEVO (expuesto si lo necesitas en front)
                FROM productos p
                LEFT JOIN proveedores  pr ON p.id_proveedor   = pr.id_proveedor
                LEFT JOIN unidades_sat u  ON p.id_unidad_sat  = u.id_unidad_sat
                LEFT JOIN cat_grupos   g  ON g.id_grupo       = p.id_grupo   -- NUEVO
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
        if (!empty($idGrupo)) {                        // NUEVO
            $sql .= " AND p.id_grupo = :idg";
            $params[':idg'] = (int)$idGrupo;
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

    public function contar(string $codigo = '',string $descripcion = '',?int $idProveedor = null,?int $idGrupo = null ) 
    {
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
        if (!is_null($idGrupo)) {                      // NUEVO
            $sql .= " AND p.id_grupo = :idg";
            $params[':idg'] = (int)$idGrupo;
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, ($k === ':idprov' || $k === ':idg') ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ================== CRUD ==================
    public function obtenerPorId(int $id)
    {
        $sql = "SELECT
                    p.*,
                    pr.nombre       AS proveedor,
                    u.descripcion   AS unidad_sat,
                    g.nombre_grupo  AS grupo      -- NUEVO: nombre del grupo para detalle
                FROM productos p
                LEFT JOIN proveedores  pr ON pr.id_proveedor = p.id_proveedor
                LEFT JOIN unidades_sat u  ON u.id_unidad_sat = p.id_unidad_sat
                LEFT JOIN cat_grupos   g  ON g.id_grupo      = p.id_grupo     -- NUEVO
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
        // Compatibilidad: mapear peldano -> peldaño si viene así desde el front
        if (!array_key_exists('peldaño', $d) && array_key_exists('peldano', $d)) {
            $d['peldaño'] = $d['peldano'];
        }

        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO productos
                    (id_proveedor, id_unidad_sat, id_grupo, clave_prod_serv_sat, codigo, descripcion,
                     costo_neto, precio_publico, precio_taller, precio_proveedor,
                     stock_actual, stock_maximo, stock_minimo,
                     piso, pasillo, estante, `peldaño`,
                     activo, fecha_creacion)
                    VALUES
                    (:idprov, :iduni, :idg, :clave, :cod, :des,
                     :cn, :ppub, :pt, :ppv,
                     :stk, :stkmax, :stkmin,
                     :piso, :pas, :est, :pel,
                     1, NOW())";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':idprov' => $d['id_proveedor']            ?? null,
                ':iduni'  => $d['id_unidad_sat']           ?? null,
                ':idg'    => $d['id_grupo']                ?? null,     // NUEVO
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
        // Compat: peldano -> peldaño (input sin acento)
        if (!array_key_exists('peldaño', $d) && array_key_exists('peldano', $d)) {
            $d['peldaño'] = $d['peldano'];
        }

        try {
            $this->conn->beginTransaction();

            // 1) Estado previo con lock
            $stPrev = $this->conn->prepare("SELECT * FROM productos WHERE id_producto = :id FOR UPDATE");
            $stPrev->execute([':id' => $id]);
            $prev = $stPrev->fetch(PDO::FETCH_ASSOC);
            if (!$prev) {
                throw new Exception('Producto no encontrado.');
            }

            // 2) Definición de campos
            $numericFields = [
                'costo_neto','precio_publico','precio_taller','precio_proveedor',
                'stock_actual','stock_maximo','stock_minimo','piso','pasillo','estante','peldaño'
            ];
            $keyFields  = ['id_proveedor','id_unidad_sat','id_grupo','activo'];
            $textFields = ['clave_prod_serv_sat','codigo','descripcion'];

            // 3) Normaliza nuevos valores tomando lo que venga en $d o lo previo
            $new = [];
            foreach ($keyFields as $f) {
                $new[$f] = array_key_exists($f,$d) ? $d[$f] : $prev[$f];
            }
            foreach ($textFields as $f) {
                $new[$f] = array_key_exists($f,$d) ? (($f==='descripcion') ? trim($d[$f]) : $d[$f]) : $prev[$f];
            }
            foreach ($numericFields as $f) {
                $new[$f] = array_key_exists($f,$d) ? $d[$f] : $prev[$f];
            }

            // 4) Detectar cambios
            $changes = [];
            $isDiff = function($old, $new, $numeric = false) {
                if ($numeric) return (float)$old != (float)$new;
                return (string)$old !== (string)$new;
            };
            foreach ($keyFields as $f)     if ($isDiff($prev[$f], $new[$f], false)) $changes[$f] = ['old'=>$prev[$f], 'new'=>$new[$f], 'numeric'=>false];
            foreach ($textFields as $f)    if ($isDiff($prev[$f], $new[$f], false)) $changes[$f] = ['old'=>$prev[$f], 'new'=>$new[$f], 'numeric'=>false];
            foreach ($numericFields as $f) if ($isDiff($prev[$f], $new[$f], true))  $changes[$f] = ['old'=>$prev[$f], 'new'=>$new[$f], 'numeric'=>true];

            // 5) Sin cambios
            if (empty($changes)) {
                $this->conn->commit();
                return ['ok' => true, 'id_producto' => $id, 'msg' => 'Sin cambios'];
            }

            // Helpers para ident y placeholder seguros
            $quoteIdent = function(string $name): string {
                // protege backticks en el nombre (raro, pero seguro)
                return '`' . str_replace('`','``',$name) . '`';
            };
            $paramNameFor = function(string $field): string {
                // reemplaza todo lo que no sea [a-zA-Z0-9_] por _
                $ascii = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);
                // si inicia con número, prefija _
                if ($ascii === '' || ctype_digit($ascii[0])) $ascii = '_' . $ascii;
                return ':' . $ascii;
            };

            // 6) UPDATE solo de campos cambiados (ASCII placeholders)
            $setSql = [];
            $params = [':id' => $id];
            foreach ($changes as $field => $info) {
                $col   = $quoteIdent($field);        // `peldaño` OK
                $pname = $paramNameFor($field);      // :pelda_o  (ASCII)
                $setSql[]        = "$col = $pname";
                $params[$pname]  = $info['new'];
            }
            $sqlUpd = "UPDATE productos SET ".implode(', ', $setSql)." WHERE id_producto = :id";
            $this->conn->prepare($sqlUpd)->execute($params);

            // 7) Movimientos de inventario
            $idUsuario = (int)($d['id_usuario']   ?? 0);
            $idSucursal= !empty($d['id_sucursal']) ? (int)$d['id_sucursal'] : 1;
            $ref       = $prev['codigo'] ? ('EDIT-' . $prev['codigo']) : ('PROD-' . $id);

            $insertAjuste = function(string $motivo) use ($id, $idSucursal, $idUsuario, $ref) {
                $stm = $this->conn->prepare(
                    "INSERT INTO inventario_movimientos
                    (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                    VALUES (:idp, 'Ajuste', 0, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
                );
                $stm->execute([
                    ':idp'   => $id,
                    ':idsuc' => $idSucursal,
                    ':idusr' => $idUsuario,
                    ':ref'   => $ref,
                    ':mot'   => $motivo,
                ]);
            };

            if (isset($changes['stock_actual'])) {
                $delta = (float)$new['stock_actual'] - (float)$prev['stock_actual'];
                if ($delta != 0.0) {
                    $tipo = $delta > 0 ? 'Entrada' : 'Salida';
                    $stm = $this->conn->prepare(
                        "INSERT INTO inventario_movimientos
                        (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                        VALUES (:idp, :tipo, :cant, :idsuc, :idusr, :ref, :mot, NOW(), 1)"
                    );
                    $stm->execute([
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

            if (isset($changes['id_proveedor'])) {
                $insertAjuste('Cambio de proveedor: '.$changes['id_proveedor']['old'].' → '.$changes['id_proveedor']['new']);
            }
            if (isset($changes['precio_proveedor'])) {
                $insertAjuste('Cambio de precio del proveedor: '.
                    number_format((float)$changes['precio_proveedor']['old'],2,'.','').' → '.
                    number_format((float)$changes['precio_proveedor']['new'],2,'.',''));
            }
            if (isset($changes['costo_neto'])) {
                $insertAjuste('Cambio de costo neto: '.
                    number_format((float)$changes['costo_neto']['old'],2,'.','').' → '.
                    number_format((float)$changes['costo_neto']['new'],2,'.',''));
            }
            if (isset($changes['precio_publico'])) {
                $insertAjuste('Cambio de precio público: '.
                    number_format((float)$changes['precio_publico']['old'],2,'.','').' → '.
                    number_format((float)$changes['precio_publico']['new'],2,'.',''));
            }
            if (isset($changes['precio_taller'])) {
                $insertAjuste('Cambio de precio taller: '.
                    number_format((float)$changes['precio_taller']['old'],2,'.','').' → '.
                    number_format((float)$changes['precio_taller']['new'],2,'.',''));
            }

            // 8) Bitácora
            foreach ($changes as $campo => $info) {
                $this->registrarBitacora(
                    (int)$idUsuario,
                    'productos',
                    'UPDATE',
                    $id,
                    'Actualización de campo',
                    is_null($info['old']) ? null : (string)$info['old'],
                    is_null($info['new']) ? null : (string)$info['new'],
                    $campo
                );
            }

            $this->conn->commit();
            return ['ok' => true, 'id_producto' => $id];

        } catch (Exception $e) {
            $this->conn->rollBack();
            try { $this->registrarBitacora((int)($d['id_usuario'] ?? 0), 'productos', 'ERROR', $id, $e->getMessage()); } catch (\Throwable $th) {}
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /* ==== Helpers (déjalos si aún no los tienes en la clase) ==== */
    private function normalizeIncoming(string $field, $value) {
        if ($value === null) return null;
        switch ($field) {
            case 'id_proveedor':
            case 'id_unidad_sat':
            case 'id_grupo':
            case 'stock_actual':
            case 'stock_maximo':
            case 'stock_minimo':
            case 'piso':
            case 'pasillo':
            case 'estante':
            case 'peldaño':
            case 'activo':
                return ($value === '' ? null : (int)$value);
            case 'costo_neto':
            case 'precio_publico':
            case 'precio_taller':
            case 'precio_proveedor':
                $v = is_string($value) ? str_replace(',', '.', $value) : $value;
                return ($v === '' ? null : (float)$v);
            default:
                return trim((string)$value);
        }
    }

    private function valuesEqual($a, $b, string $type): bool {
        if ($a === null && $b === null) return true;
        if ($a === null || $b === null) return false;
        switch ($type) {
            case 'int':   return (int)$a === (int)$b;
            case 'float': return (float)$a == (float)$b;
            case 'bool':  return (bool)$a === (bool)$b;
            default:      return (string)trim((string)$a) === (string)trim((string)$b);
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
