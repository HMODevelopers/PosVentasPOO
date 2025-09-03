<?php
// Incluir conexión PDO
include_once '../includes/db.php';

class CajaModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    // ========== LISTADO PAGINADO ==========
    public function listar(int $pagina = 1, int $limite = 10, array $filtros = [])
    {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT c.*,
                       s.nombre AS sucursal
                FROM cajas c
                INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
                WHERE c.activo = 1";
        // Si quieres mostrar solo cajas de sucursales activas, descomenta:
        // $sql .= " AND s.activo = 1";

        $params = [];

        // Filtros normalizados
        $q           = trim($filtros['q'] ?? '');
        $nombre      = trim($filtros['nombre'] ?? '');
        $idSucursal  = (int)($filtros['id_sucursal'] ?? 0);

        if ($q !== '') {
            $sql .= " AND (c.nombre LIKE :q1 OR s.nombre LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        if ($nombre !== '') {
            $sql .= " AND c.nombre LIKE :nom";
            $params[':nom'] = "%{$nombre}%";
        }
        if ($idSucursal > 0) {
            $sql .= " AND c.id_sucursal = :ids";
            $params[':ids'] = $idSucursal;
        }

        $sql .= " ORDER BY c.id_caja ASC
                  LIMIT {$limite} OFFSET {$offset}"; // enteros validados

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k, $v);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(array $filtros = [])
    {
        $sql = "SELECT COUNT(*) AS total
                FROM cajas c
                INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
                WHERE c.activo = 1";
        // Si quieres considerar solo sucursales activas:
        // $sql .= " AND s.activo = 1";

        $params = [];

        $q           = trim($filtros['q'] ?? '');
        $nombre      = trim($filtros['nombre'] ?? '');
        $idSucursal  = (int)($filtros['id_sucursal'] ?? 0);

        if ($q !== '') {
            $sql .= " AND (c.nombre LIKE :q1 OR s.nombre LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        if ($nombre !== '') {
            $sql .= " AND c.nombre LIKE :nom";
            $params[':nom'] = "%{$nombre}%";
        }
        if ($idSucursal > 0) {
            $sql .= " AND c.id_sucursal = :ids";
            $params[':ids'] = $idSucursal;
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k, $v);
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ========== CRUD ==========
    public function obtenerPorId(int $id)
    {
        $sql = "SELECT c.*, s.nombre AS sucursal
                FROM cajas c
                INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
                WHERE c.id_caja = :id
                LIMIT 1";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':id',$id,PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function crear(array $data, ?int $idUsuario = null)
    {
        try {
            $this->conn->beginTransaction();

            $nombre     = trim($data['nombre'] ?? '');
            $idSucursal = (int)($data['id_sucursal'] ?? 0);

            if ($nombre === '' || $idSucursal <= 0) {
                $this->conn->rollBack();
                return 0;
            }

            $sql = "INSERT INTO cajas
                    (nombre, id_sucursal, activo, fecha_creacion)
                    VALUES (:nom, :ids, 1, NOW())";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':nom' => $nombre,
                ':ids' => $idSucursal
            ]);
            if (!$ok) { $this->conn->rollBack(); return 0; }

            $id = (int)$this->conn->lastInsertId();

            // Bitácora (INSERT)
            $this->registrarBitacora(
                $idUsuario,
                'INSERT',
                $id,
                'Alta de caja',
                null,
                [
                    'nombre'      => $nombre,
                    'id_sucursal' => $idSucursal
                ]
            );

            $this->conn->commit();
            return $id;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', 0, 'Error al crear caja: '.$e->getMessage()); } catch (\Throwable $t) {}
            return 0;
        }
    }

    public function actualizar(int $id, array $data, ?int $idUsuario = null)
    {
        try {
            $this->conn->beginTransaction();

            $prev = $this->obtenerPorId($id);
            if (!$prev) { $this->conn->rollBack(); return false; }

            $nombre     = trim($data['nombre'] ?? '');
            $idSucursal = (int)($data['id_sucursal'] ?? 0);
            if ($nombre === '' || $idSucursal <= 0) {
                $this->conn->rollBack();
                return false;
            }

            $sql = "UPDATE cajas
                    SET nombre = :nom, id_sucursal = :ids
                    WHERE id_caja = :id";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':nom' => $nombre,
                ':ids' => $idSucursal,
                ':id'  => $id
            ]);
            if (!$ok) { $this->conn->rollBack(); return false; }

            $nuevo = [
                'nombre'      => $nombre,
                'id_sucursal' => $idSucursal
            ];

            // Bitácora: 1 registro por CAMPO modificado
            foreach ($nuevo as $campo => $valNvo) {
                $valAnt = $prev[$campo] ?? null;
                if ((string)$valAnt !== (string)$valNvo) {
                    $this->registrarBitacora(
                        $idUsuario,
                        'UPDATE',
                        $id,
                        'Actualización de caja',
                        [$campo => $valAnt],
                        [$campo => $valNvo],
                        $campo
                    );
                }
            }

            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al actualizar caja: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    // Borrado lógico
    public function eliminar(int $id, ?int $idUsuario = null)
    {
        try {
            $this->conn->beginTransaction();

            $prev = $this->obtenerPorId($id);
            if (!$prev) { $this->conn->rollBack(); return false; }

            $st = $this->conn->prepare("UPDATE cajas SET activo = 0 WHERE id_caja = :id");
            $ok = $st->execute([':id' => $id]);
            if (!$ok) { $this->conn->rollBack(); return false; }

            // Bitácora (cambio de activo)
            $this->registrarBitacora(
                $idUsuario,
                'DELETE',
                $id,
                'Borrado lógico de caja',
                ['activo' => (string)($prev['activo'] ?? '1')],
                ['activo' => '0'],
                'activo'
            );

            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al eliminar caja: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    // ========== PARA SELECTS / AUTOCOMPLETE ==========
    // Devuelve {id_caja, nombre} y permite filtrar por sucursal
    public function listarMin(string $q = '', int $limite = 50, ?int $idSucursal = null)
    {
        $sql = "SELECT c.id_caja, c.nombre
                FROM cajas c
                WHERE c.activo = 1";
        $params = [];

        if ($idSucursal && $idSucursal > 0) {
            $sql .= " AND c.id_sucursal = :ids";
            $params[':ids'] = (int)$idSucursal;
        }
        if ($q !== '') {
            $sql .= " AND c.nombre LIKE :q1";
            $params[':q1'] = "%{$q}%";
        }
        $sql .= " ORDER BY c.nombre ASC LIMIT :lim";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':lim', (int)$limite, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========== BITÁCORA ==========
    private function registrarBitacora(
        ?int $idUsuario,
        string $accion,
        int $registroId,
        string $descripcion = '',
        ?array $valorAnterior = null,
        ?array $valorNuevo = null,
        ?string $campoModificado = null
    ) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]); // primera IP si viene lista
        }

        $sql = "INSERT INTO bitacora_movimientos
                (id_usuario, tabla, accion, registro_id, campo_modificado, valor_anterior, valor_nuevo,
                 descripcion, ip_origen, activo, fecha)
                VALUES
                (:usr, :tbl, :acc, :rid, :campo, :val_ant, :val_nvo, :desc, :ip, 1, NOW())";

        $st = $this->conn->prepare($sql);
        $st->execute([
            ':usr'     => $idUsuario,
            ':tbl'     => 'cajas',
            ':acc'     => $accion, // INSERT|UPDATE|DELETE|ERROR...
            ':rid'     => $registroId,
            ':campo'   => $campoModificado,
            ':val_ant' => $valorAnterior ? json_encode($valorAnterior, JSON_UNESCAPED_UNICODE) : null,
            ':val_nvo' => $valorNuevo   ? json_encode($valorNuevo,   JSON_UNESCAPED_UNICODE) : null,
            ':desc'    => $descripcion,
            ':ip'      => $ip
        ]);
    }
}
