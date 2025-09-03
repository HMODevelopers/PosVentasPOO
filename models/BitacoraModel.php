<?php
// Incluir conexión PDO
include_once '../includes/db.php';

class BitacoraModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    // ================== LISTADO PAGINADO ==================
    public function listar(int $pagina = 1, int $limite = 10, array $filtros = [])
    {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT b.*,
                       u.nombre  AS usuario_nombre,
                       u.usuario AS usuario_login
                FROM bitacora_movimientos b
                LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario
                WHERE b.activo = 1";
        $params = [];

        // ---- Normalizar filtros
        $q        = trim($filtros['q'] ?? '');
        $tabla    = trim($filtros['tabla'] ?? '');
        $campo    = trim($filtros['campo_modificado'] ?? '');
        $ip       = trim($filtros['ip_origen'] ?? '');
        $idUsr    = (int)($filtros['id_usuario'] ?? 0);
        $regId    = (int)($filtros['registro_id'] ?? 0);
        $desde    = trim($filtros['desde'] ?? '');
        $hasta    = trim($filtros['hasta'] ?? '');
        $accion   = $filtros['accion'] ?? '';

        if ($q !== '') {
            $sql .= " AND (
                b.tabla            LIKE :q1 OR
                b.accion           LIKE :q2 OR
                b.descripcion      LIKE :q3 OR
                b.campo_modificado LIKE :q4 OR
                b.valor_anterior   LIKE :q5 OR
                b.valor_nuevo      LIKE :q6 OR
                b.ip_origen        LIKE :q7
            )";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
            $params[':q3'] = "%{$q}%";
            $params[':q4'] = "%{$q}%";
            $params[':q5'] = "%{$q}%";
            $params[':q6'] = "%{$q}%";
            $params[':q7'] = "%{$q}%";
        }
        if ($tabla !== '') {
            $sql .= " AND b.tabla LIKE :tbl";
            $params[':tbl'] = "%{$tabla}%";
        }
        if ($campo !== '') {
            $sql .= " AND b.campo_modificado LIKE :cmp";
            $params[':cmp'] = "%{$campo}%";
        }
        if ($ip !== '') {
            $sql .= " AND b.ip_origen LIKE :ip";
            $params[':ip'] = "%{$ip}%";
        }
        if ($idUsr > 0) {
            $sql .= " AND b.id_usuario = :usr";
            $params[':usr'] = $idUsr;
        }
        if ($regId > 0) {
            $sql .= " AND b.registro_id = :rid";
            $params[':rid'] = $regId;
        }
        // acciones (uno o varios)
        $acciones = [];
        if (is_array($accion)) {
            $acciones = array_values(array_filter(array_map('trim', $accion)));
        } elseif (is_string($accion) && $accion !== '') {
            $acciones = array_values(array_filter(array_map('trim', explode(',', $accion))));
        }
        if (!empty($acciones)) {
            $phs = [];
            foreach ($acciones as $i => $ac) {
                $p = ':ac'.($i+1);
                $phs[] = $p;
                $params[$p] = $ac; // valores del enum: INSERT,UPDATE,DELETE,LOGIN,LOGOUT,CANCEL,PRINT,ERROR
            }
            $sql .= " AND b.accion IN (".implode(',', $phs).")";
        }

        // Rango de fechas (fecha DATETIME)
        if ($desde !== '') {
            $sql .= " AND DATE(b.fecha) >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $sql .= " AND DATE(b.fecha) <= :hasta";
            $params[':hasta'] = $hasta;
        }

        $sql .= " ORDER BY b.fecha DESC, b.id_bitacora DESC
                  LIMIT {$limite} OFFSET {$offset}";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(array $filtros = [])
    {
        $sql = "SELECT COUNT(*) AS total
                FROM bitacora_movimientos b
                LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario
                WHERE b.activo = 1";
        $params = [];

        $q        = trim($filtros['q'] ?? '');
        $tabla    = trim($filtros['tabla'] ?? '');
        $campo    = trim($filtros['campo_modificado'] ?? '');
        $ip       = trim($filtros['ip_origen'] ?? '');
        $idUsr    = (int)($filtros['id_usuario'] ?? 0);
        $regId    = (int)($filtros['registro_id'] ?? 0);
        $desde    = trim($filtros['desde'] ?? '');
        $hasta    = trim($filtros['hasta'] ?? '');
        $accion   = $filtros['accion'] ?? '';

        if ($q !== '') {
            $sql .= " AND (
                b.tabla            LIKE :q1 OR
                b.accion           LIKE :q2 OR
                b.descripcion      LIKE :q3 OR
                b.campo_modificado LIKE :q4 OR
                b.valor_anterior   LIKE :q5 OR
                b.valor_nuevo      LIKE :q6 OR
                b.ip_origen        LIKE :q7
            )";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
            $params[':q3'] = "%{$q}%";
            $params[':q4'] = "%{$q}%";
            $params[':q5'] = "%{$q}%";
            $params[':q6'] = "%{$q}%";
            $params[':q7'] = "%{$q}%";
        }
        if ($tabla !== '') {
            $sql .= " AND b.tabla LIKE :tbl";
            $params[':tbl'] = "%{$tabla}%";
        }
        if ($campo !== '') {
            $sql .= " AND b.campo_modificado LIKE :cmp";
            $params[':cmp'] = "%{$campo}%";
        }
        if ($ip !== '') {
            $sql .= " AND b.ip_origen LIKE :ip";
            $params[':ip'] = "%{$ip}%";
        }
        if ($idUsr > 0) {
            $sql .= " AND b.id_usuario = :usr";
            $params[':usr'] = $idUsr;
        }
        if ($regId > 0) {
            $sql .= " AND b.registro_id = :rid";
            $params[':rid'] = $regId;
        }
        $acciones = [];
        if (is_array($accion)) {
            $acciones = array_values(array_filter(array_map('trim', $accion)));
        } elseif (is_string($accion) && $accion !== '') {
            $acciones = array_values(array_filter(array_map('trim', explode(',', $accion))));
        }
        if (!empty($acciones)) {
            $phs = [];
            foreach ($acciones as $i => $ac) {
                $p = ':ac'.($i+1);
                $phs[] = $p;
                $params[$p] = $ac;
            }
            $sql .= " AND b.accion IN (".implode(',', $phs).")";
        }
        if ($desde !== '') {
            $sql .= " AND DATE(b.fecha) >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $sql .= " AND DATE(b.fecha) <= :hasta";
            $params[':hasta'] = $hasta;
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ================== DETALLE ==================
    public function obtenerPorId(int $id)
    {
        $sql = "SELECT b.*,
                       u.nombre  AS usuario_nombre,
                       u.usuario AS usuario_login
                FROM bitacora_movimientos b
                LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario
                WHERE b.id_bitacora = :id
                LIMIT 1";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':id',$id,PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }
}
