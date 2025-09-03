<?php
// Incluir conexión PDO (usa el mismo db.php de tu app)
include_once '../includes/db.php';

class RolModel
{
    private PDO $conn;
    private const TABLE = 'roles'; // Cambia aquí si tu tabla se llama diferente

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // ============ LISTAR + CONTAR ============
    public function listar(int $pagina=1, int $limite=10, string $nombre='', $activo=1): array
    {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT id_rol, nombre, descripcion, activo, fecha_creacion
                FROM ".self::TABLE." WHERE 1=1";
        $params = [];

        if ($nombre !== '') {
            $sql .= " AND nombre LIKE :nom";
            $params[':nom'] = "%{$nombre}%";
        }
        // activo: 1 por default; si viene '' no se filtra
        if ($activo !== '') {
            $sql .= " AND activo = :act";
            $params[':act'] = (int)$activo;
        }

        $sql .= " ORDER BY nombre ASC, id_rol ASC
                  LIMIT :lim OFFSET :off";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue(':lim', $limite, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contar(string $nombre='', $activo=1): int
    {
        $sql = "SELECT COUNT(*) AS total FROM ".self::TABLE." WHERE 1=1";
        $params = [];

        if ($nombre !== '') {
            $sql .= " AND nombre LIKE :nom";
            $params[':nom'] = "%{$nombre}%";
        }
        if ($activo !== '') {
            $sql .= " AND activo = :act";
            $params[':act'] = (int)$activo;
        }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();
        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ============ DETALLE ============
    public function obtenerPorId(int $id): ?array
    {
        $st = $this->conn->prepare("SELECT * FROM ".self::TABLE." WHERE id_rol = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ============ CREAR ============
    public function crear(array $d): array
    {
        try {
            $this->conn->beginTransaction();

            // (Opcional) evitar duplicados por nombre
            if ($this->existeNombre(trim($d['nombre'] ?? ''), null)) {
                throw new Exception('Ya existe un rol con ese nombre.');
            }

            $sql = "INSERT INTO ".self::TABLE."
                    (nombre, descripcion, activo, fecha_creacion)
                    VALUES (:nom, :des, 1, NOW())";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':nom' => trim($d['nombre'] ?? ''),
                ':des' => $d['descripcion'] ?? null,
            ]);

            $idRol = (int)$this->conn->lastInsertId();

            // Bitácora (misma idea que Productos)
            $this->registrarBitacora(
                (int)($d['id_usuario'] ?? 0),
                'roles',
                'INSERT',
                $idRol,
                'Alta de rol',
                null,
                json_encode([
                    'nombre'      => trim($d['nombre'] ?? ''),
                    'descripcion' => $d['descripcion'] ?? null,
                    'activo'      => 1
                ], JSON_UNESCAPED_UNICODE)
            );

            $this->conn->commit();
            return ['ok'=>true, 'id_rol'=>$idRol];

        } catch (Exception $e) {
            $this->conn->rollBack();
            try { $this->registrarBitacora((int)($d['id_usuario'] ?? 0), 'roles', 'ERROR', 0, $e->getMessage()); } catch (\Throwable $th) {}
            return ['ok'=>false, 'msg'=>$e->getMessage()];
        }
    }

    // ============ ACTUALIZAR ============
    public function actualizar(int $id, array $d): array
    {
        try {
            $this->conn->beginTransaction();

            // Lock previo
            $prev = $this->obtenerPorIdForUpdate($id);
            if (!$prev) throw new Exception('Rol no encontrado.');

            $nuevo = [
                'nombre'      => trim($d['nombre'] ?? $prev['nombre']),
                'descripcion' => array_key_exists('descripcion',$d) ? $d['descripcion'] : $prev['descripcion'],
            ];

            // (Opcional) evitar duplicado de nombre contra otros IDs
            if ($this->existeNombre($nuevo['nombre'], $id)) {
                throw new Exception('Ya existe un rol con ese nombre.');
            }

            // Detecta cambios
            $changes = [];
            foreach (['nombre','descripcion'] as $c) {
                if ((string)($prev[$c] ?? '') !== (string)($nuevo[$c] ?? '')) {
                    $changes[$c] = ['old'=>$prev[$c], 'new'=>$nuevo[$c]];
                }
            }

            if (empty($changes)) {
                $this->conn->commit();
                return ['ok'=>true, 'id_rol'=>$id, 'msg'=>'Sin cambios'];
            }

            // Update solo campos cambiados
            $set = []; $params = [':id'=>$id];
            foreach ($changes as $campo=>$info) {
                $set[] = "$campo = :$campo";
                $params[":$campo"] = $info['new'];
            }
            $sql = "UPDATE ".self::TABLE." SET ".implode(',',$set)." WHERE id_rol = :id";
            $this->conn->prepare($sql)->execute($params);

            // Bitácora por campo
            foreach ($changes as $campo=>$info) {
                $this->registrarBitacora(
                    (int)($d['id_usuario'] ?? 0),
                    'roles',
                    'UPDATE',
                    $id,
                    'Actualización de rol',
                    is_null($info['old']) ? null : (string)$info['old'],
                    is_null($info['new']) ? null : (string)$info['new'],
                    $campo
                );
            }

            $this->conn->commit();
            return ['ok'=>true, 'id_rol'=>$id];

        } catch (Exception $e) {
            $this->conn->rollBack();
            try { $this->registrarBitacora((int)($d['id_usuario'] ?? 0), 'roles', 'ERROR', $id, $e->getMessage()); } catch (\Throwable $th) {}
            return ['ok'=>false, 'msg'=>$e->getMessage()];
        }
    }

    // ============ ELIMINAR (soft-delete) ============
    public function eliminar(int $id, int $idUsuario, string $motivo='Baja de rol'): array
    {
        try {
            $this->conn->beginTransaction();

            $st = $this->conn->prepare("SELECT activo FROM ".self::TABLE." WHERE id_rol = :id FOR UPDATE");
            $st->execute([':id'=>$id]);
            $prev = $st->fetch(PDO::FETCH_ASSOC);
            if (!$prev) throw new Exception('Rol no encontrado.');

            if ((string)$prev['activo'] === '0') {
                $this->registrarBitacora(
                    $idUsuario,'roles','DELETE',$id,'Soft delete ya aplicado',
                    json_encode(['activo'=>0], JSON_UNESCAPED_UNICODE),
                    json_encode(['activo'=>0], JSON_UNESCAPED_UNICODE),
                    'activo'
                );
                $this->conn->commit();
                return ['ok'=>true,'msg'=>'Sin cambios (ya estaba inactivo).'];
            }

            $this->conn->prepare("UPDATE ".self::TABLE." SET activo = 0 WHERE id_rol = :id")
                       ->execute([':id'=>$id]);

            $this->registrarBitacora(
                $idUsuario,'roles','DELETE',$id,$motivo,
                json_encode(['activo'=>1], JSON_UNESCAPED_UNICODE),
                json_encode(['activo'=>0], JSON_UNESCAPED_UNICODE),
                'activo'
            );

            $this->conn->commit();
            return ['ok'=>true,'msg'=>'Rol desactivado.'];

        } catch (Exception $e) {
            $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario,'roles','ERROR',$id,$e->getMessage()); } catch (\Throwable $th) {}
            return ['ok'=>false,'msg'=>$e->getMessage()];
        }
    }

    // ============ MIN PARA SELECTS ============
    public function listarMin(string $q='', int $limite=200): array
    {
        $lim = max(1, min($limite, 1000));

        $sql = "SELECT id_rol, nombre
                FROM ".self::TABLE."
                WHERE activo = 1";
        $params = [];
        if ($q !== '') { $sql .= " AND nombre LIKE :q"; $params[':q']="%{$q}%"; }
        $sql .= " ORDER BY nombre ASC LIMIT {$lim}";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v,PDO::PARAM_STR);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) $r['nombre_mostrar'] = $r['nombre'];
        return $rows;
    }

    // ============ HELPERS ============
    private function obtenerPorIdForUpdate(int $id): ?array
    {
        $st = $this->conn->prepare("SELECT * FROM ".self::TABLE." WHERE id_rol = :id FOR UPDATE");
        $st->bindValue(':id',$id,PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function existeNombre(string $nombre, ?int $excluirId=null): bool
    {
        if ($nombre === '') return false;
        $sql = "SELECT 1 FROM ".self::TABLE." WHERE nombre = :n";
        if (!empty($excluirId)) $sql .= " AND id_rol <> :id";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':n',$nombre,PDO::PARAM_STR);
        if (!empty($excluirId)) $st->bindValue(':id',$excluirId,PDO::PARAM_INT);
        $st->execute();
        return (bool)$st->fetchColumn();
    }

    private function registrarBitacora(
        int $idUsuario,
        string $tabla,
        string $accion,
        int $registroId,
        string $descripcion='',
        ?string $valorAnterior=null,
        ?string $valorNuevo=null,
        ?string $campoModificado=null
    ): void {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);

        $sql = "INSERT INTO bitacora_movimientos
                (id_usuario, tabla, accion, registro_id, campo_modificado, valor_anterior, valor_nuevo,
                 descripcion, ip_origen, activo, fecha)
                VALUES
                (:usr,:tbl,:acc,:rid,:campo,:val_ant,:val_nvo,:desc,:ip,1,NOW())";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':usr'     => (int)$idUsuario,
            ':tbl'     => $tabla,
            ':acc'     => $accion,
            ':rid'     => $registroId,
            ':campo'   => $campoModificado,
            ':val_ant' => $valorAnterior,
            ':val_nvo' => $valorNuevo,
            ':desc'    => $descripcion,
            ':ip'      => $ip
        ]);
    }
}
