<?php
// models/ComprasClientesModel.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';

class ComprasClientesModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    public function listar(
        int $pagina = 1,
        int $limite = 20,
        string $codigo = '',
        string $descripcion = '',
        ?string $fechaVenta = null,
        array $scope = [],
        bool $agrupar = true
    ) {
        $offset = ($pagina - 1) * $limite;

        // ===== Filtros base
        $where  = ['v.id_cliente IS NOT NULL', 'v.id_cliente <> 0'];
        $params = [];

        if ($codigo !== '') {
            $where[] = 'p.codigo LIKE :codigo';
            $params[':codigo'] = "%{$codigo}%";
        }
        if ($descripcion !== '') {
            $where[] = 'p.descripcion LIKE :descripcion';
            $params[':descripcion'] = "%{$descripcion}%";
        }
        if (!empty($fechaVenta)) {
            $where[] = 'DATE(v.fecha) = :fecha';
            $params[':fecha'] = $fechaVenta;
        }

        $rolNombre  = strtoupper($scope['rol_nombre'] ?? '');
        $idCliente  = $scope['id_cliente'] ?? null;
        $idUsuario  = $scope['id_usuario'] ?? null;
        $esAdmin    = in_array($rolNombre, ['ADMIN','ADMINISTRADOR','SUPERADMIN','SUPER ADMIN'], true);

        if (!$esAdmin) {
            if (!empty($idCliente)) {
                $where[] = 'v.id_cliente = :id_cliente';
                $params[':id_cliente'] = (int)$idCliente;
            } elseif (!empty($idUsuario)) {
                $where[] = 'v.id_usuario = :id_usuario';
                $params[':id_usuario'] = (int)$idUsuario;
            } else {
                $where[] = '1=0';
            }
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        if ($agrupar) {
            // ===============================
            // AGRUPADO con DE-DUP (sin CTE)
            // ===============================
            // 1) Subselect vd_uni: colapsa duplicados exactos del detalle (sólo activos)
            $vdUni = "
                SELECT
                    vd.id_venta,
                    vd.id_producto,
                    vd.precio_unitario,
                    vd.cantidad,
                    COALESCE(vd.subtotal, vd.cantidad * vd.precio_unitario) AS subtotal_calc,
                    MIN(vd.id_venta_detalle) AS id_keep
                FROM ventas_detalle vd
                WHERE COALESCE(vd.activo, 1) = 1
                GROUP BY
                    vd.id_venta,
                    vd.id_producto,
                    vd.precio_unitario,
                    vd.cantidad,
                    COALESCE(vd.subtotal, vd.cantidad * vd.precio_unitario)
            ";

            // 2) Base agrupada por Ticket + Cliente + id_producto
            $sqlBaseAgrupado = "
                SELECT
                    v.folio                  AS no_tiket,
                    c.nombre                 AS cliente,
                    p.id_producto            AS id_producto,
                    p.codigo                 AS codigo,
                    TRIM(MAX(p.descripcion)) AS descripcion,
                    SUM(x.cantidad)          AS cantidad,
                    SUM(x.subtotal_calc)     AS total,
                    DATE(MIN(v.fecha))       AS fecha_dia
                FROM ventas v
                INNER JOIN clientes  c ON c.id_cliente  = v.id_cliente
                INNER JOIN ($vdUni) x ON x.id_venta     = v.id_venta
                INNER JOIN productos p ON p.id_producto = x.id_producto
                $whereSql
                GROUP BY v.folio, c.nombre, p.id_producto, p.codigo, DATE(v.fecha)
            ";

            // Conteo de grupos
            $sqlCount = "SELECT COUNT(*) FROM ( $sqlBaseAgrupado ) t";
            $stmtC = $this->conn->prepare($sqlCount);
            foreach ($params as $k => $v) { $stmtC->bindValue($k, $v); }
            $stmtC->execute();
            $total = (int)($stmtC->fetchColumn() ?: 0);

            // Página
            $sql = "
                SELECT
                    no_tiket,
                    cliente,
                    codigo,
                    descripcion,
                    cantidad,
                    (CASE WHEN cantidad <> 0 THEN total / cantidad ELSE 0 END) AS precio,
                    total,
                    fecha_dia AS fecha_venta
                FROM ( $sqlBaseAgrupado ) x
                ORDER BY fecha_venta DESC, no_tiket DESC, codigo ASC
                LIMIT :limite OFFSET :offset
            ";
            $stmt = $this->conn->prepare($sql);
            foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Suma total del período usando el set deduplicado
            $sqlSum = "
                SELECT COALESCE(SUM(x.subtotal_calc),0) AS suma_total
                FROM ventas v
                INNER JOIN clientes  c ON c.id_cliente  = v.id_cliente
                INNER JOIN ($vdUni) x ON x.id_venta     = v.id_venta
                INNER JOIN productos p ON p.id_producto = x.id_producto
                $whereSql
            ";
            $stmtS = $this->conn->prepare($sqlSum);
            foreach ($params as $k => $v) { $stmtS->bindValue($k, $v); }
            $stmtS->execute();
            $suma_total = (float)($stmtS->fetchColumn() ?: 0.0);

        } else {
            // ===============================
            // MODO DETALLE (sin agrupar)
            // ===============================
            $sql = "
                SELECT
                    v.folio                                AS no_tiket,
                    c.nombre                               AS cliente,
                    p.codigo                               AS codigo,
                    p.descripcion                          AS descripcion,
                    vd.cantidad                            AS cantidad,
                    vd.precio_unitario                     AS precio,
                    COALESCE(vd.subtotal, vd.cantidad * vd.precio_unitario) AS total,
                    v.fecha                                AS fecha_venta
                FROM ventas v
                INNER JOIN clientes c        ON c.id_cliente = v.id_cliente
                INNER JOIN ventas_detalle vd ON vd.id_venta   = v.id_venta
                INNER JOIN productos p       ON p.id_producto = vd.id_producto
                WHERE COALESCE(vd.activo,1)=1
                  AND v.id_cliente IS NOT NULL AND v.id_cliente <> 0
                  " . ($codigo      !== '' ? " AND p.codigo LIKE :codigo" : "") . "
                  " . ($descripcion !== '' ? " AND p.descripcion LIKE :descripcion" : "") . "
                  " . (!empty($fechaVenta)   ? " AND DATE(v.fecha) = :fecha" : "") . "
                  " . (!$esAdmin && !empty($idCliente) ? " AND v.id_cliente = :id_cliente" : "") . "
                  " . (!$esAdmin && empty($idCliente) && !empty($idUsuario) ? " AND v.id_usuario = :id_usuario" : "") . "
                ORDER BY v.fecha DESC, v.folio DESC, p.codigo ASC
                LIMIT :limite OFFSET :offset
            ";
            $stmt = $this->conn->prepare($sql);
            foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $sqlCount = "
                SELECT COUNT(*)
                FROM ventas v
                INNER JOIN clientes c        ON c.id_cliente = v.id_cliente
                INNER JOIN ventas_detalle vd ON vd.id_venta   = v.id_venta
                INNER JOIN productos p       ON p.id_producto = vd.id_producto
                WHERE COALESCE(vd.activo,1)=1
                  AND v.id_cliente IS NOT NULL AND v.id_cliente <> 0
                  " . ($codigo      !== '' ? " AND p.codigo LIKE :codigo" : "") . "
                  " . ($descripcion !== '' ? " AND p.descripcion LIKE :descripcion" : "") . "
                  " . (!empty($fechaVenta)   ? " AND DATE(v.fecha) = :fecha" : "") . "
                  " . (!$esAdmin && !empty($idCliente) ? " AND v.id_cliente = :id_cliente" : "") . "
                  " . (!$esAdmin && empty($idCliente) && !empty($idUsuario) ? " AND v.id_usuario = :id_usuario" : "") . "
            ";
            $stmtC = $this->conn->prepare($sqlCount);
            foreach ($params as $k => $v) { $stmtC->bindValue($k, $v); }
            $stmtC->execute();
            $total = (int)($stmtC->fetchColumn() ?: 0);

            $sqlSum = "
                SELECT COALESCE(SUM(COALESCE(vd.subtotal, vd.cantidad * vd.precio_unitario)), 0) AS suma_total
                FROM ventas v
                INNER JOIN clientes c        ON c.id_cliente = v.id_cliente
                INNER JOIN ventas_detalle vd ON vd.id_venta   = v.id_venta
                INNER JOIN productos p       ON p.id_producto = vd.id_producto
                WHERE COALESCE(vd.activo,1)=1
                  AND v.id_cliente IS NOT NULL AND v.id_cliente <> 0
                  " . ($codigo      !== '' ? " AND p.codigo LIKE :codigo" : "") . "
                  " . ($descripcion !== '' ? " AND p.descripcion LIKE :descripcion" : "") . "
                  " . (!empty($fechaVenta)   ? " AND DATE(v.fecha) = :fecha" : "") . "
                  " . (!$esAdmin && !empty($idCliente) ? " AND v.id_cliente = :id_cliente" : "") . "
                  " . (!$esAdmin && empty($idCliente) && !empty($idUsuario) ? " AND v.id_usuario = :id_usuario" : "") . "
            ";
            $stmtS = $this->conn->prepare($sqlSum);
            foreach ($params as $k => $v) { $stmtS->bindValue($k, $v); }
            $stmtS->execute();
            $suma_total = (float)($stmtS->fetchColumn() ?: 0.0);
        }

        return [
            'rows'       => $rows,
            'total'      => $total,
            'suma_total' => $suma_total,
        ];
    }

    public function obtenerRolNombrePorUsuario(int $idUsuario): ?string
    {
        $sql = "
            SELECT UPPER(r.nombre) AS rol_nombre
            FROM usuarios u
            INNER JOIN roles r ON r.id_rol = u.id_rol
            WHERE u.id_usuario = :id
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $idUsuario, \PDO::PARAM_INT);
        $stmt->execute();
        $rol = $stmt->fetchColumn();
        return $rol ? (string)$rol : null;
    }
}
