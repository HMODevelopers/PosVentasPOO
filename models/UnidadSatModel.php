<?php
// Incluir conexión PDO
include_once '../includes/db.php';

class UnidadSatModel
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

        $sql = "SELECT us.*
                FROM unidades_sat us
                WHERE us.activo = 1";
        $params = [];

        // Normaliza filtros
        $q           = trim($filtros['q']           ?? '');
        $clave       = trim($filtros['clave_unidad_sat'] ?? $filtros['clave'] ?? '');
        $descripcion = trim($filtros['descripcion'] ?? '');

        if ($q !== '') {
            $sql .= " AND (us.clave_unidad_sat LIKE :q1 OR us.descripcion LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        if ($clave !== '') {
            $sql .= " AND us.clave_unidad_sat LIKE :cla";
            $params[':cla'] = "%".strtoupper($clave)."%";
        }
        if ($descripcion !== '') {
            $sql .= " AND us.descripcion LIKE :des";
            $params[':des'] = "%{$descripcion}%";
        }

        $sql .= " ORDER BY us.clave_unidad_sat ASC
                  LIMIT {$limite} OFFSET {$offset}"; // enteros inyectados ya validados

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k, $v);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(array $filtros = [])
    {
        $sql = "SELECT COUNT(*) AS total
                FROM unidades_sat us
                WHERE us.activo = 1";
        $params = [];

        $q           = trim($filtros['q']           ?? '');
        $clave       = trim($filtros['clave_unidad_sat'] ?? $filtros['clave'] ?? '');
        $descripcion = trim($filtros['descripcion'] ?? '');

        if ($q !== '') {
            $sql .= " AND (us.clave_unidad_sat LIKE :q1 OR us.descripcion LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        if ($clave !== '') {
            $sql .= " AND us.clave_unidad_sat LIKE :cla";
            $params[':cla'] = "%".strtoupper($clave)."%";
        }
        if ($descripcion !== '') {
            $sql .= " AND us.descripcion LIKE :des";
            $params[':des'] = "%{$descripcion}%";
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k, $v);
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ========== CRUD ==========
    public function obtenerPorId(int $id)
    {
        $st = $this->conn->prepare("SELECT * FROM unidades_sat WHERE id_unidad_sat = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function crear(array $data, ?int $idUsuario = null)
    {
        $clave = strtoupper(trim($data['clave_unidad_sat'] ?? ''));
        $desc  = trim($data['descripcion'] ?? '');

        $sql = "INSERT INTO unidades_sat
                (clave_unidad_sat, descripcion, activo, fecha_creacion)
                VALUES (:cla, :des, 1, NOW())";
        try {
            $this->conn->beginTransaction();

            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':cla' => $clave,
                ':des' => $desc,
            ]);
            if (!$ok) { $this->conn->rollBack(); return 0; }

            $id = (int)$this->conn->lastInsertId();

            // Bitácora (1 registro con valores nuevos)
            $this->registrarBitacora(
                $idUsuario,
                'INSERT',
                $id,
                'Alta de unidad SAT',
                null,
                [
                    'clave_unidad_sat' => $clave,
                    'descripcion'      => $desc,
                ]
            );

            $this->conn->commit();
            return $id;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ($e->getCode() === '23000') return -1; // duplicado
            try { $this->registrarBitacora($idUsuario, 'ERROR', 0, 'Error al crear unidad SAT: '.$e->getMessage()); } catch (\Throwable $t) {}
            return 0;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', 0, 'Error al crear unidad SAT: '.$e->getMessage()); } catch (\Throwable $t) {}
            return 0;
        }
    }

    public function actualizar(int $id, array $data, ?int $idUsuario = null)
    {
        $clave = strtoupper(trim($data['clave_unidad_sat'] ?? ''));
        $desc  = trim($data['descripcion'] ?? '');

        try {
            $this->conn->beginTransaction();

            $prev = $this->obtenerPorId($id);
            if (!$prev) { $this->conn->rollBack(); return false; }

            $sql = "UPDATE unidades_sat
                    SET clave_unidad_sat = :cla,
                        descripcion      = :des
                    WHERE id_unidad_sat = :id";

            $st  = $this->conn->prepare($sql);
            $ok  = $st->execute([
                ':cla' => $clave,
                ':des' => $desc,
                ':id'  => $id
            ]);
            if (!$ok) { $this->conn->rollBack(); return false; }

            // Comparar cambios
            $nuevo = [
                'clave_unidad_sat' => $clave,
                'descripcion'      => $desc,
            ];

            $cambios = [];
            foreach ($nuevo as $campo => $valorNuevo) {
                $valorAnterior = $prev[$campo] ?? null;
                if ((string)$valorAnterior !== (string)$valorNuevo) {
                    $cambios[] = [$campo, $valorAnterior, $valorNuevo];
                }
            }

            // Bitácora: 1 registro por CAMPO modificado
            foreach ($cambios as [$campo, $valAnt, $valNvo]) {
                $this->registrarBitacora(
                    $idUsuario,
                    'UPDATE',
                    $id,
                    'Actualización de unidad SAT',
                    [$campo => $valAnt],
                    [$campo => $valNvo],
                    $campo
                );
            }

            $this->conn->commit();
            return true;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ($e->getCode() === '23000') return -1; // duplicado de clave
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al actualizar unidad SAT: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al actualizar unidad SAT: '.$e->getMessage()); } catch (\Throwable $t) {}
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

            $st = $this->conn->prepare("UPDATE unidades_sat SET activo = 0 WHERE id_unidad_sat = :id");
            $ok = $st->execute([':id' => $id]);
            if (!$ok) { $this->conn->rollBack(); return false; }

            // Bitácora (cambio de activo)
            $this->registrarBitacora(
                $idUsuario,
                'DELETE',
                $id,
                'Borrado lógico de unidad SAT',
                ['activo' => (string)($prev['activo'] ?? '1')],
                ['activo' => '0'],
                'activo'
            );

            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al eliminar unidad SAT: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    // ========== PARA SELECTS / AUTOCOMPLETE ==========
    // Devuelve {id_unidad_sat, clave, descripcion}
    public function listarMin(string $q = '', int $limite = 50)
    {
        $sql = "SELECT id_unidad_sat,
                       clave_unidad_sat AS clave,
                       descripcion
                FROM unidades_sat
                WHERE activo = 1";
        $params = [];
        if ($q !== '') {
            // placeholders únicos
            $sql .= " AND (clave_unidad_sat LIKE :q1 OR descripcion LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        $sql .= " ORDER BY clave_unidad_sat ASC LIMIT :lim";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k, $v);
        $st->bindValue(':lim', (int)$limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========== BITÁCORA ==========
    /**
     * Inserta en bitacora_movimientos
     * Campos: id_usuario, tabla, accion, registro_id, campo_modificado, valor_anterior, valor_nuevo,
     *         descripcion, ip_origen, activo, fecha
     */
    private function registrarBitacora(
        ?int $idUsuario,
        string $accion,
        int $registroId,
        string $descripcion = '',
        ?array $valorAnterior = null,
        ?array $valorNuevo = null,
        ?string $campoModificado = null
    ) {
        // IP origen (considera proxy)
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
            ':tbl'     => 'unidades_sat',
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
