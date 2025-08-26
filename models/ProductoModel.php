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

    public function crear(array $d)
    {
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
        $ok = $st->execute([
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
        return $ok ? (int)$this->conn->lastInsertId() : 0;
    }

    public function actualizar(int $id, array $d)
    {
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
        return $st->execute([
            ':idprov' => $d['id_proveedor']            ?? null,
            ':iduni'  => $d['id_unidad_sat']           ?? null,
            ':clave'  => $d['clave_prod_serv_sat']     ?? '01010101',
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
            ':id'     => $id
        ]);
    }

    public function eliminar(int $id)
    {
        $st = $this->conn->prepare("UPDATE productos SET activo = 0 WHERE id_producto = :id");
        return $st->execute([':id' => $id]);
    }

   
    // ================== PARA SELECTS/AUTOCOMPLETE ==================
    // Devuelve lista breve para compras (sólo sugiere PPV = precio_proveedor)
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
}
