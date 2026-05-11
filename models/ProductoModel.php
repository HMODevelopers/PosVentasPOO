<?php
// Incluir conexión PDO (debe exponer $pdo)
include_once '../includes/db.php';

class ProductoModel
{
    /** @var PDO */
    private $conn;

    public function __construct()
    {
        // $pdo viene de ../includes/db.php
        global $pdo;
        $this->conn = $pdo;
    }

    /* ============================================================
     * LISTADO + CONTAR
     * ============================================================ */

    public function listar(
        int $pagina = 1,
        int $limite = 10,
        string $codigo = '',
        string $descripcion = '',
        ?int $idProveedor = null,
        ?int $idGrupo = null
    ) {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT
                    p.*,
                    pr.nombre      AS proveedor,
                    u.descripcion  AS unidad_sat,
                    g.nombre_grupo AS grupo
                FROM productos p
                LEFT JOIN proveedores  pr ON p.id_proveedor  = pr.id_proveedor
                LEFT JOIN unidades_sat u  ON p.id_unidad_sat = u.id_unidad_sat
                LEFT JOIN cat_grupos   g  ON p.id_grupo      = g.id_grupo
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
        if (!empty($idGrupo)) {
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

    public function contar(
        string $codigo = '',
        string $descripcion = '',
        ?int $idProveedor = null,
        ?int $idGrupo = null
    ) {
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
        if (!is_null($idGrupo)) {
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

    /* ============================================================
     * CRUD BÁSICO
     * ============================================================ */

    public function obtenerPorId(int $id)
    {
        $sql = "SELECT
                    p.*,
                    pr.nombre      AS proveedor,
                    u.descripcion  AS unidad_sat,
                    g.nombre_grupo AS grupo
                FROM productos p
                LEFT JOIN proveedores  pr ON pr.id_proveedor = p.id_proveedor
                LEFT JOIN unidades_sat u  ON u.id_unidad_sat = p.id_unidad_sat
                LEFT JOIN cat_grupos   g  ON g.id_grupo      = p.id_grupo
                WHERE p.id_producto = :id
                LIMIT 1";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * CREAR producto + (si aplica) movimiento de inventario + bitácora
     * $d: requiere id_usuario; id_sucursal opcional (default 1)
     */
    public function crear(array $d)
    {
        // compat: peldano -> peldaño
        if (!array_key_exists('peldaño', $d) && array_key_exists('peldano', $d)) {
            $d['peldaño'] = $d['peldano'];
        }

        try {
            $d = $this->normalizarYValidarDatosFiscales($d);
            $this->conn->beginTransaction();

            $sql = "INSERT INTO productos
                    (id_proveedor, id_unidad_sat, id_grupo, clave_prod_serv_sat, objeto_imp, tasa_iva, codigo, descripcion,
                     costo_neto, precio_publico, precio_taller, precio_proveedor,
                     stock_actual, stock_maximo, stock_minimo,
                     piso, pasillo, estante, `peldaño`,
                     activo, fecha_creacion)
                    VALUES
                    (:idprov, :iduni, :idg, :clave, :objimp, :tiva, :cod, :des,
                     :cn, :ppub, :pt, :ppv,
                     :stk, :stkmax, :stkmin,
                     :piso, :pas, :est, :pel,
                     1, NOW())";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':idprov' => $d['id_proveedor']            ?? null,
                ':iduni'  => $d['id_unidad_sat']           ?? null,
                ':idg'    => $d['id_grupo']                ?? null,
                ':clave'  => $d['clave_prod_serv_sat'],
                ':objimp' => $d['objeto_imp'],
                ':tiva'   => $d['tasa_iva'],
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

            // si stock inicial > 0, registrar entrada
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

            // bitácora
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
            try { $this->registrarBitacora((int)($d['id_usuario'] ?? 0), 'productos', 'ERROR', 0, $e->getMessage()); } catch (\Throwable $th) {}
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * ACTUALIZAR producto + movimientos por delta de stock + bitácora
     */
    public function actualizar(int $id, array $d)
    {
        // compat: peldano -> peldaño
        if (!array_key_exists('peldaño', $d) && array_key_exists('peldano', $d)) {
            $d['peldaño'] = $d['peldano'];
        }

        try {
            $this->conn->beginTransaction();

            // estado previo (lock)
            $stPrev = $this->conn->prepare("SELECT * FROM productos WHERE id_producto = :id FOR UPDATE");
            $stPrev->execute([':id' => $id]);
            $prev = $stPrev->fetch(PDO::FETCH_ASSOC);
            if (!$prev) throw new Exception('Producto no encontrado.');

            $d = $this->normalizarYValidarDatosFiscales($d, $prev);

            $numericFields = [
                'costo_neto','precio_publico','precio_taller','precio_proveedor',
                'stock_actual','stock_maximo','stock_minimo','piso','pasillo','estante','peldaño','tasa_iva'
            ];
            $keyFields  = ['id_proveedor','id_unidad_sat','id_grupo','activo'];
            $textFields = ['clave_prod_serv_sat','codigo','descripcion','objeto_imp'];

            $new = [];
            foreach ($keyFields as $f)  { $new[$f] = array_key_exists($f,$d) ? $d[$f] : $prev[$f]; }
            foreach ($textFields as $f) { $new[$f] = array_key_exists($f,$d) ? (($f==='descripcion')? trim($d[$f]) : $d[$f]) : $prev[$f]; }
            foreach ($numericFields as $f) { $new[$f] = array_key_exists($f,$d) ? $d[$f] : $prev[$f]; }

            $changes = [];
            $isDiff = function($old, $new, $numeric = false) {
                if ($numeric) return (float)$old != (float)$new;
                return (string)$old !== (string)$new;
            };
            foreach ($keyFields as $f)     if ($isDiff($prev[$f], $new[$f]))            $changes[$f] = ['old'=>$prev[$f], 'new'=>$new[$f], 'numeric'=>false];
            foreach ($textFields as $f)    if ($isDiff($prev[$f], $new[$f]))            $changes[$f] = ['old'=>$prev[$f], 'new'=>$new[$f], 'numeric'=>false];
            foreach ($numericFields as $f) if ($isDiff($prev[$f], $new[$f], true))      $changes[$f] = ['old'=>$prev[$f], 'new'=>$new[$f], 'numeric'=>true];

            if (empty($changes)) {
                $this->conn->commit();
                return ['ok' => true, 'id_producto' => $id, 'msg' => 'Sin cambios'];
            }

            // helpers para nombres seguros
            $quoteIdent = function(string $name): string {
                return '`' . str_replace('`','``',$name) . '`';
            };
            $paramNameFor = function(string $field): string {
                $ascii = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);
                if ($ascii === '' || ctype_digit($ascii[0])) $ascii = '_' . $ascii;
                return ':' . $ascii;
            };

            // UPDATE solo de campos cambiados
            $setSql = [];
            $params = [':id' => $id];
            foreach ($changes as $field => $info) {
                $col   = $quoteIdent($field);       // soporta `peldaño`
                $pname = $paramNameFor($field);     // :pelda_o
                $setSql[]       = "$col = $pname";
                $params[$pname] = $info['new'];
            }
            $sqlUpd = "UPDATE productos SET ".implode(', ', $setSql)." WHERE id_producto = :id";
            $this->conn->prepare($sqlUpd)->execute($params);

            // movimientos por delta de stock
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

            if (isset($changes['id_proveedor']))   $insertAjuste('Cambio de proveedor: '.$changes['id_proveedor']['old'].' → '.$changes['id_proveedor']['new']);
            if (isset($changes['precio_proveedor'])) $insertAjuste('Cambio de precio del proveedor: '.number_format((float)$changes['precio_proveedor']['old'],2,'.','').' → '.number_format((float)$changes['precio_proveedor']['new'],2,'.',''));
            if (isset($changes['costo_neto']))       $insertAjuste('Cambio de costo neto: '.number_format((float)$changes['costo_neto']['old'],2,'.','').' → '.number_format((float)$changes['costo_neto']['new'],2,'.',''));
            if (isset($changes['precio_publico']))   $insertAjuste('Cambio de precio público: '.number_format((float)$changes['precio_publico']['old'],2,'.','').' → '.number_format((float)$changes['precio_publico']['new'],2,'.',''));
            if (isset($changes['precio_taller']))    $insertAjuste('Cambio de precio taller: '.number_format((float)$changes['precio_taller']['old'],2,'.','').' → '.number_format((float)$changes['precio_taller']['new'],2,'.',''));

            // bitácora por campo
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

    /**
     * ELIMINAR (soft-delete) + salida inventario + bitácora
     */
    public function eliminar(int $idProducto, int $idSucursal, int $idUsuario, string $motivo = 'Desactivación de producto')
    {
        try {
            $this->conn->beginTransaction();

            $st = $this->conn->prepare("SELECT codigo, stock_actual FROM productos WHERE id_producto = :id FOR UPDATE");
            $st->execute([':id' => (int)$idProducto]);
            $prev = $st->fetch(PDO::FETCH_ASSOC);
            if (!$prev) throw new Exception('Producto no encontrado.');

            $stock = (float)$prev['stock_actual'];
            $ref   = $prev['codigo'] ?? ('PROD-' . $idProducto);

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

                $this->conn->prepare("UPDATE productos SET stock_actual = 0 WHERE id_producto = :id")
                           ->execute([':id' => (int)$idProducto]);
            }

            $this->conn->prepare("UPDATE productos SET activo = 0 WHERE id_producto = :id")
                       ->execute([':id' => (int)$idProducto]);

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
            try { $this->registrarBitacora((int)$idUsuario, 'productos', 'ERROR', (int)$idProducto, $e->getMessage()); } catch (\Throwable $th) {}
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /* ============================================================
     * SELECTS / AUTOCOMPLETE
     * ============================================================ */

    public function buscarMin(string $q = '', int $limite = 50)
    {
        $lim = max(1, (int)$limite);
        $q = trim($q);
        $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $sql = "SELECT
                    p.id_producto,
                    p.codigo,
                    p.descripcion,
                    p.stock_actual,
                    p.precio_proveedor,
                    p.precio_taller,
                    p.precio_publico,
                    pr.nombre AS proveedor
                FROM productos p
                LEFT JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor
                WHERE p.activo = 1";

        $useQ = ($q !== '');
        if ($useQ) {
            $tokenClauses = [];
            foreach ($tokens as $idx => $tok) {
                $tokenClauses[] = "(p.codigo LIKE :tok_codigo_{$idx} OR p.descripcion LIKE :tok_desc_{$idx})";
            }

            $sql .= " AND (
                        p.codigo = :qeq
                        OR p.codigo LIKE :q_like_codigo
                        OR p.descripcion LIKE :q_like_descripcion
                      )";

            if (!empty($tokenClauses)) {
                $sql .= " AND " . implode(' AND ', $tokenClauses);
            }

            $sql .= " ORDER BY
                        CASE
                          WHEN p.codigo = :ord_codigo_exacto THEN 1
                          WHEN p.codigo LIKE :ord_codigo_inicio THEN 2
                          WHEN p.codigo LIKE :ord_codigo_contiene THEN 3
                          WHEN p.descripcion LIKE :ord_desc_inicio THEN 4
                          WHEN p.descripcion LIKE :ord_desc_contiene THEN 5
                          ELSE 6
                        END ASC,
                        p.descripcion ASC";
        } else {
            $sql .= " ORDER BY p.descripcion ASC";
        }

        $sql .= " LIMIT {$lim}";

        $st = $this->conn->prepare($sql);
        if ($useQ) {
            $like = "%{$q}%";
            $prefix = "{$q}%";
            $st->bindValue(':qeq', $q, PDO::PARAM_STR);
            $st->bindValue(':q_like_codigo', $like, PDO::PARAM_STR);
            $st->bindValue(':q_like_descripcion', $like, PDO::PARAM_STR);
            $st->bindValue(':ord_codigo_exacto', $q, PDO::PARAM_STR);
            $st->bindValue(':ord_codigo_inicio', $prefix, PDO::PARAM_STR);
            $st->bindValue(':ord_codigo_contiene', $like, PDO::PARAM_STR);
            $st->bindValue(':ord_desc_inicio', $prefix, PDO::PARAM_STR);
            $st->bindValue(':ord_desc_contiene', $like, PDO::PARAM_STR);

            foreach ($tokens as $idx => $tok) {
                $tokLike = "%{$tok}%";
                $st->bindValue(":tok_codigo_{$idx}", $tokLike, PDO::PARAM_STR);
                $st->bindValue(":tok_desc_{$idx}", $tokLike, PDO::PARAM_STR);
            }
        }
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ============================================================
     * UTILIDADES – Cálculo de precios
     * ============================================================ */

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
        $FACTOR_MERMA_GARANTIA = 1.05;
        $FACTOR_TALLER_DESDE_PUBLICO = 0.8;

        /*
        * $ppv = precio proveedor
        * $CN  = costo neto calculado
        * $PB  = precio público
        * $PT  = precio taller
        */

        // Regla predeterminada
        $CN = $ppv * $IVA;
        $PB = ($ppv * 1.8) * $IVA;

        switch ($nom) {
            case 'permor':
                $CN = $ppv * 0.64 * $IVA * 0.89 * 0.95;
                $PB = $ppv * 1.024;
                break;

            case 'apymsa':
                $CN = $ppv * 1.044;
                $PB = $ppv * 1.70694;
                break;

            case 'bdh':
                $CN = $ppv;
                $PB = $ppv * $IVA;
                $PT = $ppv;
                break;

            case 'switchero':
                $CN = $ppv;
                $PB = $ppv * 1.8125;
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
                break;
        }

        /*
        * Ajuste por merma/garantías:
        * - No aplica a BDH.
        * - Aumenta 5% el precio público.
        * - Aumenta 5% el precio taller al recalcularlo desde el nuevo precio público.
        * - No modifica el costo neto.
        */
        if ($nom !== 'bdh') {
            $PB *= $FACTOR_MERMA_GARANTIA;
            $PT = $PB * $FACTOR_TALLER_DESDE_PUBLICO;
        }

        return [
            round($CN, 2),
            round($PB, 2),
            round($PT, 2),
        ];
    }

    /* ============================================================
     * EXPORTACIÓN – Listado sin paginar para Excel
     * ============================================================ */

    public function listarParaExportar($codigo = '', $descripcion = '', $idProveedor = null, $idGrupo = null)
    {
        // ⚠️ IMPORTANTE:
        // Cambia este nombre por el de la columna FECHA real en tu tabla `compras`
        // Ejemplos típicos: 'fecha_compra', 'fecha', 'fecha_ult_fact', etc.
        $colFechaCompras = 'fecha_factura';  // <--- AJÚSTALO A TU ESQUEMA REAL

        $sql = "SELECT 
                    p.id_producto,
                    p.codigo,
                    p.descripcion,
                    p.stock_actual,
                    p.precio_proveedor,
                    p.costo_neto,
                    p.precio_publico,
                    p.precio_taller,
                    pr.nombre       AS proveedor,
                    g.nombre_grupo  AS grupo,
                    MAX(c.{$colFechaCompras}) AS ultima_compra
                FROM productos p
                LEFT JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor
                LEFT JOIN cat_grupos g   ON g.id_grupo     = p.id_grupo
                -- AJUSTA el nombre de la tabla de detalle de compras si es diferente
                LEFT JOIN compras_detalle cd ON cd.id_producto = p.id_producto
                LEFT JOIN compras c          ON c.id_compra    = cd.id_compra
                WHERE p.activo = 1";
        $params = [];

        if ($codigo !== '') {
            $sql .= " AND p.codigo LIKE :codigo";
            $params[':codigo'] = "%$codigo%";
        }
        if ($descripcion !== '') {
            $sql .= " AND p.descripcion LIKE :descripcion";
            $params[':descripcion'] = "%$descripcion%";
        }
        if (!is_null($idProveedor)) {
            $sql .= " AND p.id_proveedor = :idProv";
            $params[':idProv'] = (int)$idProveedor;
        }
        if (!is_null($idGrupo)) {
            $sql .= " AND p.id_grupo = :idGrupo";
            $params[':idGrupo'] = (int)$idGrupo;
        }

        $sql .= " GROUP BY
                    p.id_producto,
                    p.codigo,
                    p.descripcion,
                    p.stock_actual,
                    p.precio_proveedor,
                    p.costo_neto,
                    p.precio_publico,
                    p.precio_taller,
                    pr.nombre,
                    g.nombre_grupo
                ORDER BY p.descripcion ASC";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    private function normalizarYValidarDatosFiscales(array $d, ?array $prev = null): array
    {
        $idUnidad = (int)($d['id_unidad_sat'] ?? ($prev['id_unidad_sat'] ?? 0));
        if ($idUnidad <= 0 || !$this->existeUnidadSat($idUnidad)) {
            throw new Exception('Unidad SAT inválida o inexistente.');
        }

        $idGrupo = (int)($d['id_grupo'] ?? ($prev['id_grupo'] ?? 0));
        if ($idGrupo <= 0) {
            throw new Exception('Grupo es requerido.');
        }

        $claveGrupo = $this->obtenerClaveProdServSatPorGrupo($idGrupo);
        if ($claveGrupo === null) {
            throw new Exception('El grupo seleccionado no existe o no tiene Clave SAT configurada.');
        }

        $objetoImp = trim((string)($d['objeto_imp'] ?? ($prev['objeto_imp'] ?? '')));
        if ($objetoImp === '') $objetoImp = '02';

        $tasaIvaRaw = $d['tasa_iva'] ?? ($prev['tasa_iva'] ?? '');
        $tasaIva = is_numeric($tasaIvaRaw) ? (float)$tasaIvaRaw : 0.16;
        if ($tasaIva <= 0) $tasaIva = 0.16;

        $d['id_unidad_sat'] = $idUnidad;
        $d['id_grupo'] = $idGrupo;
        $d['clave_prod_serv_sat'] = $claveGrupo;
        $d['objeto_imp'] = $objetoImp;
        $d['tasa_iva'] = number_format($tasaIva, 6, '.', '');

        return $d;
    }

    private function existeUnidadSat(int $idUnidadSat): bool
    {
        $st = $this->conn->prepare("SELECT 1 FROM unidades_sat WHERE id_unidad_sat = :id AND activo = 1 LIMIT 1");
        $st->bindValue(':id', $idUnidadSat, PDO::PARAM_INT);
        $st->execute();
        return (bool)$st->fetchColumn();
    }

    private function obtenerClaveProdServSatPorGrupo(int $idGrupo): ?string
    {
        $st = $this->conn->prepare("SELECT clave_h FROM cat_grupos WHERE id_grupo = :id AND activo = 1 LIMIT 1");
        $st->bindValue(':id', $idGrupo, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $clave = trim((string)($row['clave_h'] ?? ''));
        if ($clave === '') return null;
        return $clave;
    }


    /* ============================================================
     * BITÁCORA
     * ============================================================ */

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
