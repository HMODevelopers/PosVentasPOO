<?php
// Incluir conexión PDO
include_once '../includes/db.php';

class SucursalModel
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

        $sql = "SELECT s.*
                FROM sucursales s
                WHERE s.activo = 1";
        $params = [];

        // Filtros
        $q         = trim($filtros['q'] ?? '');
        $nombre    = trim($filtros['nombre'] ?? '');
        $direccion = trim($filtros['direccion'] ?? '');
        $telefono  = trim($filtros['telefono'] ?? '');

        if ($q !== '') {
            $sql .= " AND (s.nombre LIKE :q1 OR s.direccion LIKE :q2 OR s.telefono LIKE :q3)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
            $params[':q3'] = "%{$q}%";
        }
        if ($nombre !== '')    { $sql .= " AND s.nombre    LIKE :nom"; $params[':nom'] = "%{$nombre}%"; }
        if ($direccion !== '') { $sql .= " AND s.direccion LIKE :dir"; $params[':dir'] = "%{$direccion}%"; }
        if ($telefono !== '')  { $sql .= " AND s.telefono  LIKE :tel"; $params[':tel'] = "%{$telefono}%"; }

        $sql .= " ORDER BY s.id_sucursal ASC
                  LIMIT {$limite} OFFSET {$offset}";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(array $filtros = [])
    {
        $sql = "SELECT COUNT(*) AS total
                FROM sucursales s
                WHERE s.activo = 1";
        $params = [];

        $q         = trim($filtros['q'] ?? '');
        $nombre    = trim($filtros['nombre'] ?? '');
        $direccion = trim($filtros['direccion'] ?? '');
        $telefono  = trim($filtros['telefono'] ?? '');

        if ($q !== '') {
            $sql .= " AND (s.nombre LIKE :q1 OR s.direccion LIKE :q2 OR s.telefono LIKE :q3)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
            $params[':q3'] = "%{$q}%";
        }
        if ($nombre !== '')    { $sql .= " AND s.nombre    LIKE :nom"; $params[':nom'] = "%{$nombre}%"; }
        if ($direccion !== '') { $sql .= " AND s.direccion LIKE :dir"; $params[':dir'] = "%{$direccion}%"; }
        if ($telefono !== '')  { $sql .= " AND s.telefono  LIKE :tel"; $params[':tel'] = "%{$telefono}%"; }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ========== CRUD ==========
    public function obtenerPorId(int $id)
    {
        $st = $this->conn->prepare("SELECT * FROM sucursales WHERE id_sucursal = :id LIMIT 1");
        $st->bindValue(':id',$id,PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function crear(array $data, ?int $idUsuario = null)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO sucursales
                    (nombre, direccion, telefono, activo, fecha_creacion)
                    VALUES (:nom, :dir, :tel, 1, NOW())";

            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':nom' => trim($data['nombre'] ?? ''),
                ':dir' => $data['direccion'] ?? null,
                ':tel' => $data['telefono'] ?? null,
            ]);

            if (!$ok) { $this->conn->rollBack(); return 0; }

            $id = (int)$this->conn->lastInsertId();

            // Bitácora (1 registro con valores nuevos)
            $this->registrarBitacora(
                $idUsuario,
                'INSERT',
                $id,
                'Alta de sucursal',
                null,
                [
                    'nombre'    => trim($data['nombre'] ?? ''),
                    'direccion' => $data['direccion'] ?? null,
                    'telefono'  => $data['telefono'] ?? null,
                ]
            );

            $this->conn->commit();
            return $id;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', 0, 'Error al crear sucursal: '.$e->getMessage()); } catch (\Throwable $t) {}
            return 0;
        }
    }

    public function actualizar(int $id, array $data, ?int $idUsuario = null)
    {
        try {
            $this->conn->beginTransaction();

            $prev = $this->obtenerPorId($id);
            if (!$prev) { $this->conn->rollBack(); return false; }

            $sql = "UPDATE sucursales
                    SET nombre = :nom, direccion = :dir, telefono = :tel
                    WHERE id_sucursal = :id";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':nom' => trim($data['nombre'] ?? ''),
                ':dir' => $data['direccion'] ?? null,
                ':tel' => $data['telefono'] ?? null,
                ':id'  => $id
            ]);

            if (!$ok) { $this->conn->rollBack(); return false; }

            $nuevo = [
                'nombre'    => trim($data['nombre'] ?? ''),
                'direccion' => $data['direccion'] ?? null,
                'telefono'  => $data['telefono'] ?? null,
            ];

            // Bitácora: 1 registro por CAMPO modificado
            foreach ($nuevo as $campo => $valNvo) {
                $valAnt = $prev[$campo] ?? null;
                if ((string)$valAnt !== (string)$valNvo) {
                    $this->registrarBitacora(
                        $idUsuario,
                        'UPDATE',
                        $id,
                        'Actualización de sucursal',
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
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al actualizar sucursal: '.$e->getMessage()); } catch (\Throwable $t) {}
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

            $st = $this->conn->prepare("UPDATE sucursales SET activo = 0 WHERE id_sucursal = :id");
            $ok = $st->execute([':id' => $id]);

            if (!$ok) { $this->conn->rollBack(); return false; }

            // Bitácora (cambio de activo)
            $this->registrarBitacora(
                $idUsuario,
                'DELETE',
                $id,
                'Borrado lógico de sucursal',
                ['activo' => (string)($prev['activo'] ?? '1')],
                ['activo' => '0'],
                'activo'
            );

            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al eliminar sucursal: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    // ========== PARA SELECTS / AUTOCOMPLETE ==========
    // Devuelve {id_sucursal, nombre}
    public function listarMin(string $q = '', int $limite = 50)
    {
        $sql = "SELECT id_sucursal, nombre
                FROM sucursales
                WHERE activo = 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (nombre LIKE :q1 OR telefono LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        $sql .= " ORDER BY nombre ASC LIMIT :lim";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':lim',(int)$limite,PDO::PARAM_INT);
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
            $ip = trim(explode(',', $ip)[0]);
        }

        $sql = "INSERT INTO bitacora_movimientos
                (id_usuario, tabla, accion, registro_id, campo_modificado, valor_anterior, valor_nuevo,
                 descripcion, ip_origen, activo, fecha)
                VALUES
                (:usr, :tbl, :acc, :rid, :campo, :val_ant, :val_nvo, :desc, :ip, 1, NOW())";

        $st = $this->conn->prepare($sql);
        $st->execute([
            ':usr'     => $idUsuario,
            ':tbl'     => 'sucursales',
            ':acc'     => $accion,
            ':rid'     => $registroId,
            ':campo'   => $campoModificado,
            ':val_ant' => $valorAnterior ? json_encode($valorAnterior, JSON_UNESCAPED_UNICODE) : null,
            ':val_nvo' => $valorNuevo   ? json_encode($valorNuevo,   JSON_UNESCAPED_UNICODE) : null,
            ':desc'    => $descripcion,
            ':ip'      => $ip
        ]);
    }
}
