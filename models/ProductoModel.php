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
    public function listar(int $pagina = 1, int $limite = 10, string $q = '', ?int $idProveedor = null, ?int $idUnidad = null)
    {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT p.*,
                       pr.nombre    AS proveedor,
                       u.nombre     AS unidad_sat
                FROM productos p
                LEFT JOIN proveedores  pr ON p.id_proveedor = pr.id_proveedor
                LEFT JOIN unidades_sat u  ON p.id_unidad_sat = u.id_unidad_sat
                WHERE p.activo = 1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (p.descripcion LIKE :q OR p.codigo LIKE :q)";
            $params[':q'] = "%{$q}%";
        }
        if (!empty($idProveedor)) {
            $sql .= " AND p.id_proveedor = :idprov";
            $params[':idprov'] = (int)$idProveedor;
        }
        if (!empty($idUnidad)) {
            $sql .= " AND p.id_unidad_sat = :iduni";
            $params[':iduni'] = (int)$idUnidad;
        }

        $sql .= " ORDER BY p.id_producto DESC
                  LIMIT :limite OFFSET :offset";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(string $q = '', ?int $idProveedor = null, ?int $idUnidad = null)
    {
        $sql = "SELECT COUNT(*) AS total
                FROM productos p
                WHERE p.activo = 1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (p.descripcion LIKE :q OR p.codigo LIKE :q)";
            $params[':q'] = "%{$q}%";
        }
        if (!empty($idProveedor)) {
            $sql .= " AND p.id_proveedor = :idprov";
            $params[':idprov'] = (int)$idProveedor;
        }
        if (!empty($idUnidad)) {
            $sql .= " AND p.id_unidad_sat = :iduni";
            $params[':iduni'] = (int)$idUnidad;
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ================== CRUD ==================
    public function obtenerPorId(int $id)
    {
        $st = $this->conn->prepare("SELECT * FROM productos WHERE id_producto = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function crear(array $d)
    {
        $sql = "INSERT INTO productos
                (id_proveedor, id_unidad_sat, clave_prod_serv_sat, codigo, descripcion,
                 precio_compra, precio_venta, precio_taller, precio_base,
                 stock_actual, stock_minimo,
                 piso, pasillo, estante, peldaño,
                 activo, fecha_creacion)
                VALUES
                (:idprov, :iduni, :clave, :cod, :des,
                 :pc, :pv, :pt, :pb,
                 :stk, :stkmin,
                 :piso, :pas, :est, :pel,
                 1, NOW())";
        $st = $this->conn->prepare($sql);
        $ok = $st->execute([
            ':idprov' => $d['id_proveedor']   ?? null,
            ':iduni'  => $d['id_unidad_sat']  ?? null,
            ':clave'  => $d['clave_prod_serv_sat'] ?? null,
            ':cod'    => $d['codigo'] ?? null,
            ':des'    => trim($d['descripcion'] ?? ''),
            ':pc'     => $d['precio_compra'] ?? null,
            ':pv'     => $d['precio_venta']  ?? null,
            ':pt'     => $d['precio_taller'] ?? null,
            ':pb'     => $d['precio_base']   ?? null,
            ':stk'    => $d['stock_actual']  ?? 0,
            ':stkmin' => $d['stock_minimo']  ?? 0,
            ':piso'   => $d['piso']          ?? 0,
            ':pas'    => $d['pasillo']       ?? 0,
            ':est'    => $d['estante']       ?? 0,
            ':pel'    => $d['peldaño']       ?? 0,
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
                    precio_compra = :pc,
                    precio_venta = :pv,
                    precio_taller = :pt,
                    precio_base = :pb,
                    stock_actual = :stk,
                    stock_minimo = :stkmin,
                    piso = :piso,
                    pasillo = :pas,
                    estante = :est,
                    peldaño = :pel
                WHERE id_producto = :id";
        $st = $this->conn->prepare($sql);
        return $st->execute([
            ':idprov' => $d['id_proveedor']   ?? null,
            ':iduni'  => $d['id_unidad_sat']  ?? null,
            ':clave'  => $d['clave_prod_serv_sat'] ?? null,
            ':cod'    => $d['codigo'] ?? null,
            ':des'    => trim($d['descripcion'] ?? ''),
            ':pc'     => $d['precio_compra'] ?? null,
            ':pv'     => $d['precio_venta']  ?? null,
            ':pt'     => $d['precio_taller'] ?? null,
            ':pb'     => $d['precio_base']   ?? null,
            ':stk'    => $d['stock_actual']  ?? 0,
            ':stkmin' => $d['stock_minimo']  ?? 0,
            ':piso'   => $d['piso']          ?? 0,
            ':pas'    => $d['pasillo']       ?? 0,
            ':est'    => $d['estante']       ?? 0,
            ':pel'    => $d['peldaño']       ?? 0,
            ':id'     => $id
        ]);
    }

    public function eliminar(int $id)
    {
        $st = $this->conn->prepare("UPDATE productos SET activo = 0 WHERE id_producto = :id");
        return $st->execute([':id' => $id]);
    }

    // ================== PARA SELECTS/AUTOCOMPLETE ==================
    // Devuelve lista breve para compras/ventas (costo sugerido incluido)
    public function buscarMin(string $q = '', int $limite = 50)
    {
        // sanea límite
        $lim = max(1, (int)$limite);

        $sql = "SELECT id_producto, codigo, descripcion, precio_compra AS costo_sugerido
                FROM productos
                WHERE activo = 1";

        $useQ = ($q !== '');
        if ($useQ) {
            // usa placeholders distintos para evitar problemas con drivers
            $sql .= " AND (codigo LIKE :q1 OR descripcion LIKE :q2)";
        }

        // IMPORTANTE: no bindear LIMIT; inyectar entero saneado
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
