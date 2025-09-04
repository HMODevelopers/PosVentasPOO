<?php
// Incluir conexión PDO
include_once '../includes/db.php';

class UsuarioModel
{
    /** @var PDO */
    private $conn;
    private const DEFAULT_PASSWORD_PLAIN = '123456789';

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // ================== LISTADO + CONTAR ==================
    public function listar(int $pagina = 1, int $limite = 10, array $filtros = [])
    {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

       $sql = "SELECT u.id_usuario, u.nombre, u.usuario, u.correo, u.telefono,
               u.id_rol, r.nombre AS nombre_rol, 
               u.activo, u.fecha_creacion
        FROM usuarios u
        LEFT JOIN roles r ON u.id_rol = r.id_rol
        WHERE 1=1";
        $params = [];

        $q        = trim($filtros['q']        ?? '');
        $nombre   = trim($filtros['nombre']   ?? '');
        $usuario  = trim($filtros['usuario']  ?? '');
        $correo   = trim($filtros['correo']   ?? '');
        $telefono = trim($filtros['telefono'] ?? '');
        $idRol    = isset($filtros['id_rol']) && $filtros['id_rol'] !== '' ? (int)$filtros['id_rol'] : null;
        $activo   = array_key_exists('activo', $filtros)
                    ? ($filtros['activo'] === '' ? null : (int)$filtros['activo'])
                    : 1;

        if ($activo !== null) { $sql .= " AND u.activo = :act"; $params[':act'] = $activo; }

        if ($q !== '') {
            $sql .= " AND (u.nombre LIKE :q1 OR u.usuario LIKE :q2 OR u.correo LIKE :q3 OR u.telefono LIKE :q4)";
            $like = "%{$q}%"; $params[':q1']=$like; $params[':q2']=$like; $params[':q3']=$like; $params[':q4']=$like;
        }
        if ($nombre   !== '') { $sql .= " AND u.nombre   LIKE :nom"; $params[':nom']="%{$nombre}%"; }
        if ($usuario  !== '') { $sql .= " AND u.usuario  LIKE :usr"; $params[':usr']="%{$usuario}%"; }
        if ($correo   !== '') { $sql .= " AND u.correo   LIKE :cor"; $params[':cor']="%{$correo}%"; }
        if ($telefono !== '') { $sql .= " AND u.telefono LIKE :tel"; $params[':tel']="%{$telefono}%"; }
        if ($idRol !== null)  { $sql .= " AND u.id_rol = :idrol";   $params[':idrol']=$idRol; }

        $sql .= " ORDER BY u.nombre ASC, u.id_usuario ASC LIMIT {$limite} OFFSET {$offset}";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) AS total FROM usuarios u WHERE 1=1";
        $params = [];

        $q        = trim($filtros['q']        ?? '');
        $nombre   = trim($filtros['nombre']   ?? '');
        $usuario  = trim($filtros['usuario']  ?? '');
        $correo   = trim($filtros['correo']   ?? '');
        $telefono = trim($filtros['telefono'] ?? '');
        $idRol    = isset($filtros['id_rol']) && $filtros['id_rol'] !== '' ? (int)$filtros['id_rol'] : null;
        $activo   = array_key_exists('activo', $filtros)
                    ? ($filtros['activo'] === '' ? null : (int)$filtros['activo'])
                    : 1;

        if ($activo !== null) { $sql .= " AND u.activo = :act"; $params[':act'] = $activo; }

        if ($q !== '') {
            $sql .= " AND (u.nombre LIKE :q1 OR u.usuario LIKE :q2 OR u.correo LIKE :q3 OR u.telefono LIKE :q4)";
            $like = "%{$q}%"; $params[':q1']=$like; $params[':q2']=$like; $params[':q3']=$like; $params[':q4']=$like;
        }
        if ($nombre   !== '') { $sql .= " AND u.nombre   LIKE :nom"; $params[':nom']="%{$nombre}%"; }
        if ($usuario  !== '') { $sql .= " AND u.usuario  LIKE :usr"; $params[':usr']="%{$usuario}%"; }
        if ($correo   !== '') { $sql .= " AND u.correo   LIKE :cor"; $params[':cor']="%{$correo}%"; }
        if ($telefono !== '') { $sql .= " AND u.telefono LIKE :tel"; $params[':tel']="%{$telefono}%"; }
        if ($idRol !== null)  { $sql .= " AND u.id_rol = :idrol";   $params[':idrol']=$idRol; }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function obtenerPorId(int $id)
    {
        $st = $this->conn->prepare("SELECT u.* FROM usuarios u WHERE u.id_usuario = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ================== CREAR ==================
    public function crear(array $d): array
    {
        try {
            $this->conn->beginTransaction();

            // hash de contraseña (usa 123456789 si no viene)
            $pwdIn = trim((string)($d['contrasena'] ?? ''));
            if ($pwdIn !== '') {
                $pwdHash = preg_match('/^\$2[aby]\$/', $pwdIn) ? $pwdIn : password_hash($pwdIn, PASSWORD_BCRYPT, ['cost' => 10]);
            } else {
                $pwdHash = password_hash(self::DEFAULT_PASSWORD_PLAIN, PASSWORD_BCRYPT, ['cost' => 10]);
            }

            $sql = "INSERT INTO usuarios
                    (nombre, usuario, contrasena, correo, telefono, id_rol, activo, fecha_creacion)
                    VALUES (:nom, :usr, :pwd, :cor, :tel, :rol, 1, NOW())";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':nom' => trim($d['nombre']  ?? ''),
                ':usr' => trim($d['usuario'] ?? ''),
                ':pwd' => $pwdHash,
                ':cor' => $d['correo']   ?? null,
                ':tel' => $d['telefono'] ?? null,
                ':rol' => isset($d['id_rol']) && $d['id_rol'] !== '' ? (int)$d['id_rol'] : null,
            ]);

            $idUsuarioNuevo = (int)$this->conn->lastInsertId();

            // Bitácora (como Productos)
            $this->registrarBitacora(
                (int)($d['id_usuario'] ?? 0),
                'usuarios',
                'INSERT',
                $idUsuarioNuevo,
                'Alta de usuario',
                null,
                json_encode([
                    'nombre'  => trim($d['nombre']  ?? ''),
                    'usuario' => trim($d['usuario'] ?? ''),
                    'correo'  => $d['correo']   ?? null,
                    'telefono'=> $d['telefono'] ?? null,
                    'id_rol'  => isset($d['id_rol']) && $d['id_rol'] !== '' ? (int)$d['id_rol'] : null,
                    'activo'  => 1
                ], JSON_UNESCAPED_UNICODE)
            );

            $this->conn->commit();
            return ['ok' => true, 'id_usuario' => $idUsuarioNuevo];

        } catch (\Exception $e) {
            $this->conn->rollBack();
            // intenta registrar error (no debe romper)
            try { $this->registrarBitacora((int)($d['id_usuario'] ?? 0), 'usuarios', 'ERROR', 0, $e->getMessage()); } catch (\Throwable $th) {}

            // mensaje amigable si es duplicado
            $msg = $e->getMessage();
            if ($e instanceof PDOException && isset($e->errorInfo[0]) && $e->errorInfo[0] === '23000') {
                if (strpos($msg, 'usuario') !== false) $msg = 'El usuario ya existe.';
                elseif (strpos($msg, 'correo') !== false) $msg = 'El correo ya existe.';
                else $msg = 'Usuario/correo duplicado.';
            }
            return ['ok' => false, 'msg' => $msg];
        }
    }

    // ================== ACTUALIZAR ==================
    public function actualizar(int $id, array $d): array
    {
        try {
            $this->conn->beginTransaction();

            // lock previo
            $stPrev = $this->conn->prepare("SELECT * FROM usuarios WHERE id_usuario = :id FOR UPDATE");
            $stPrev->execute([':id' => $id]);
            $prev = $stPrev->fetch(PDO::FETCH_ASSOC);
            if (!$prev) { throw new Exception('Usuario no encontrado.'); }

            $nuevo = [
                'nombre'   => trim($d['nombre']  ?? $prev['nombre']),
                'usuario'  => trim($d['usuario'] ?? $prev['usuario']),
                'correo'   => array_key_exists('correo',$d)   ? $d['correo']   : $prev['correo'],
                'telefono' => array_key_exists('telefono',$d) ? $d['telefono'] : $prev['telefono'],
                'id_rol'   => array_key_exists('id_rol',$d)   ? ($d['id_rol'] === '' ? null : $d['id_rol']) : $prev['id_rol'],
            ];

            // detectar cambios
            $campos = ['nombre','usuario','correo','telefono','id_rol'];
            $changes = [];
            foreach ($campos as $c) {
                $old = $prev[$c];
                $new = $nuevo[$c];
                if ((string)$old !== (string)$new) {
                    $changes[$c] = ['old'=>$old,'new'=>$new];
                }
            }

            if (empty($changes)) {
                $this->conn->commit();
                return ['ok'=>true,'id_usuario'=>$id,'msg'=>'Sin cambios'];
            }

            // update solo de lo que cambió
            $set = []; $params = [':id'=>$id];
            foreach ($changes as $campo => $info) {
                $set[] = "$campo = :$campo";
                $params[":$campo"] = $info['new'];
            }
            $sql = "UPDATE usuarios SET ".implode(',', $set)." WHERE id_usuario = :id";
            $this->conn->prepare($sql)->execute($params);

            // bitácora por campo
            foreach ($changes as $campo => $info) {
                $this->registrarBitacora(
                    (int)($d['id_usuario'] ?? 0),
                    'usuarios',
                    'UPDATE',
                    $id,
                    'Actualización de usuario',
                    is_null($info['old']) ? null : (string)$info['old'],
                    is_null($info['new']) ? null : (string)$info['new'],
                    $campo
                );
            }

            $this->conn->commit();
            return ['ok'=>true,'id_usuario'=>$id];

        } catch (\Exception $e) {
            $this->conn->rollBack();
            try { $this->registrarBitacora((int)($d['id_usuario'] ?? 0), 'usuarios', 'ERROR', $id, $e->getMessage()); } catch (\Throwable $th) {}
            $msg = $e->getMessage();
            if ($e instanceof PDOException && isset($e->errorInfo[0]) && $e->errorInfo[0] === '23000') {
                if (strpos($msg, 'usuario') !== false) $msg = 'El usuario ya existe.';
                elseif (strpos($msg, 'correo') !== false) $msg = 'El correo ya existe.';
                else $msg = 'Usuario/correo duplicado.';
            }
            return ['ok'=>false,'msg'=>$msg];
        }
    }

    // ================== ELIMINAR (soft) ==================
    public function eliminar(int $id, int $idUsuario, string $motivo = 'Baja de usuario'): array
    {
        try {
            $this->conn->beginTransaction();

            $st = $this->conn->prepare("SELECT activo FROM usuarios WHERE id_usuario = :id FOR UPDATE");
            $st->execute([':id'=>$id]);
            $prev = $st->fetch(PDO::FETCH_ASSOC);
            if (!$prev) { throw new Exception('Usuario no encontrado.'); }

            if ((string)$prev['activo'] === '0') {
                // ya estaba inactivo
                $this->registrarBitacora(
                    $idUsuario, 'usuarios', 'DELETE', $id,
                    'Borrado lógico ya aplicado',
                    json_encode(['activo'=>0], JSON_UNESCAPED_UNICODE),
                    json_encode(['activo'=>0], JSON_UNESCAPED_UNICODE)
                );
                $this->conn->commit();
                return ['ok'=>true,'msg'=>'Sin cambios (ya estaba inactivo).'];
            }

            $this->conn->prepare("UPDATE usuarios SET activo = 0 WHERE id_usuario = :id")->execute([':id'=>$id]);

            $this->registrarBitacora(
                $idUsuario, 'usuarios', 'DELETE', $id,
                $motivo,
                json_encode(['activo'=>1], JSON_UNESCAPED_UNICODE),
                json_encode(['activo'=>0], JSON_UNESCAPED_UNICODE),
                'activo'
            );

            $this->conn->commit();
            return ['ok'=>true,'msg'=>'Usuario desactivado.'];

        } catch (\Exception $e) {
            $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'usuarios', 'ERROR', $id, $e->getMessage()); } catch (\Throwable $th) {}
            return ['ok'=>false,'msg'=>$e->getMessage()];
        }
    }

    // ================== PARA SELECTS ==================
    public function listarMin(string $q = '', int $limite = 200): array
    {
        $limite = max(1, min($limite, 1000));

        $sql = "SELECT u.id_usuario, u.nombre, u.usuario
                FROM usuarios u
                WHERE u.activo = 1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (u.nombre LIKE :q1 OR u.usuario LIKE :q2 OR u.correo LIKE :q3)";
            $like = '%'.trim($q).'%';
            $params[':q1']=$like; $params[':q2']=$like; $params[':q3']=$like;
        }

        $sql .= " ORDER BY u.nombre ASC LIMIT {$limite}";
        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v,PDO::PARAM_STR);
        $st->execute();

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $nom = trim($r['nombre'] ?? '');
            $usr = trim($r['usuario'] ?? '');
            $r['nombre_mostrar'] = $usr !== '' ? "{$nom} ({$usr})" : $nom;
        }
        return $rows;
    }

    // ================== BITÁCORA ==================
    private function registrarBitacora(
        int $idUsuario,
        string $tabla,
        string $accion,
        int $registroId,
        string $descripcion = '',
        ?string $valorAnterior = null,
        ?string $valorNuevo = null,
        ?string $campoModificado = null
    ) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);

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
