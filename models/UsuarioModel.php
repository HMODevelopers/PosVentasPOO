<?php
// Incluir conexión PDO
include_once '../includes/db.php';

class UsuarioModel
{
    /** @var PDO */
    private $conn;
    // Contraseña por defecto en claro (se hashea al guardar)
    private const DEFAULT_PASSWORD_PLAIN = '123456789';

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // ================== LISTADO PAGINADO ==================
    public function listar(int $pagina = 1, int $limite = 10, array $filtros = []): array
    {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT u.id_usuario, u.nombre, u.usuario, u.correo, u.telefono,
                       u.id_rol, u.activo, u.fecha_creacion
                FROM usuarios u
                WHERE 1=1";
        $params = [];

        // ---- Normaliza filtros
        $q        = trim($filtros['q']        ?? '');
        $nombre   = trim($filtros['nombre']   ?? '');
        $usuario  = trim($filtros['usuario']  ?? '');
        $correo   = trim($filtros['correo']   ?? '');
        $telefono = trim($filtros['telefono'] ?? '');
        $idRol    = isset($filtros['id_rol']) && $filtros['id_rol'] !== '' ? (int)$filtros['id_rol'] : null;
        // activo: 1 por default; si mandas '' no filtra por activo
        $activo   = array_key_exists('activo', $filtros)
                    ? ($filtros['activo'] === '' ? null : (int)$filtros['activo'])
                    : 1;

        if ($activo !== null) {
            $sql .= " AND u.activo = :act";
            $params[':act'] = $activo;
        }

        // Búsqueda global con placeholders únicos
        if ($q !== '') {
            $sql .= " AND (
                u.nombre   LIKE :q1 OR
                u.usuario  LIKE :q2 OR
                u.correo   LIKE :q3 OR
                u.telefono LIKE :q4
            )";
            $like = "%{$q}%";
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }

        // Filtros específicos
        if ($nombre   !== '') { $sql .= " AND u.nombre   LIKE :nom"; $params[':nom'] = "%{$nombre}%"; }
        if ($usuario  !== '') { $sql .= " AND u.usuario  LIKE :usr"; $params[':usr'] = "%{$usuario}%"; }
        if ($correo   !== '') { $sql .= " AND u.correo   LIKE :cor"; $params[':cor'] = "%{$correo}%"; }
        if ($telefono !== '') { $sql .= " AND u.telefono LIKE :tel"; $params[':tel'] = "%{$telefono}%"; }
        if ($idRol !== null)  { $sql .= " AND u.id_rol = :idrol";   $params[':idrol'] = $idRol; }

        $sql .= " ORDER BY u.nombre ASC, u.id_usuario ASC
                  LIMIT {$limite} OFFSET {$offset}"; // enteros ya validados

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM usuarios u
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

        if ($activo !== null) {
            $sql .= " AND u.activo = :act";
            $params[':act'] = $activo;
        }

        if ($q !== '') {
            $sql .= " AND (
                u.nombre   LIKE :q1 OR
                u.usuario  LIKE :q2 OR
                u.correo   LIKE :q3 OR
                u.telefono LIKE :q4
            )";
            $like = "%{$q}%";
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }

        if ($nombre   !== '') { $sql .= " AND u.nombre   LIKE :nom"; $params[':nom'] = "%{$nombre}%"; }
        if ($usuario  !== '') { $sql .= " AND u.usuario  LIKE :usr"; $params[':usr'] = "%{$usuario}%"; }
        if ($correo   !== '') { $sql .= " AND u.correo   LIKE :cor"; $params[':cor'] = "%{$correo}%"; }
        if ($telefono !== '') { $sql .= " AND u.telefono LIKE :tel"; $params[':tel'] = "%{$telefono}%"; }
        if ($idRol !== null)  { $sql .= " AND u.id_rol = :idrol";   $params[':idrol'] = $idRol; }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ================== DETALLE ==================
    public function obtenerPorId(int $id): ?array
    {
        $st = $this->conn->prepare("SELECT u.* FROM usuarios u WHERE u.id_usuario = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================== CREAR ==================
    public function crear(array $data, ?int $idUsuarioBitacora = null): int
    {
        try {
            $this->conn->beginTransaction();

            // 1) Determinar el hash a guardar
            $pwdIn   = trim((string)($data['contrasena'] ?? ''));
            if ($pwdIn !== '') {
                // Acepta hash bcrypt directo ($2a/$2b/$2y) o texto plano
                if (preg_match('/^\$2[aby]\$/', $pwdIn) === 1) {
                    $pwdHash = $pwdIn; // ya es bcrypt
                } else {
                    $pwdHash = password_hash($pwdIn, PASSWORD_BCRYPT, ['cost' => 10]);
                }
            } else {
                // -> usar "123456789" (se hashea siempre)
                $pwdHash = password_hash(self::DEFAULT_PASSWORD_PLAIN, PASSWORD_BCRYPT, ['cost' => 10]);
            }

            // 2) Insert
            $sql = "INSERT INTO usuarios
                    (nombre, usuario, contrasena, correo, telefono, id_rol, activo, fecha_creacion)
                    VALUES (:nom, :usr, :pwd, :cor, :tel, :rol, 1, NOW())";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':nom' => trim($data['nombre']  ?? ''),
                ':usr' => trim($data['usuario'] ?? ''),
                ':pwd' => $pwdHash,
                ':cor' => $data['correo']   ?? null,
                ':tel' => $data['telefono'] ?? null,
                ':rol' => isset($data['id_rol']) && $data['id_rol'] !== '' ? (int)$data['id_rol'] : null,
            ]);

            if (!$ok) { $this->conn->rollBack(); return 0; }

            $id = (int)$this->conn->lastInsertId();

            // 3) Bitácora (nunca guardamos contraseñas)
            try {
                $this->registrarBitacora(
                    $idUsuarioBitacora,
                    'INSERT',
                    $id,
                    'Alta de usuario',
                    null,
                    [
                        'nombre'  => trim($data['nombre']  ?? ''),
                        'usuario' => trim($data['usuario'] ?? ''),
                        'correo'  => $data['correo']   ?? null,
                        'telefono'=> $data['telefono'] ?? null,
                        'id_rol'  => isset($data['id_rol']) && $data['id_rol'] !== '' ? (int)$data['id_rol'] : null,
                        'activo'  => 1
                    ],
                    null
                );
            } catch (\Throwable $bt) { /* no romper creación */ }

            $this->conn->commit();
            return $id;

        } catch (\PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ($e->getCode() === '23000') return -1; // duplicados
            try { $this->registrarBitacora($idUsuarioBitacora,'ERROR',0,'Error al crear usuario: '.$e->getMessage()); } catch (\Throwable $t) {}
            return 0;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuarioBitacora,'ERROR',0,'Error al crear usuario: '.$e->getMessage()); } catch (\Throwable $t) {}
            return 0;
        }
    }

    // ================== ACTUALIZAR ==================
    public function actualizar(int $id, array $data, ?int $idUsuarioBitacora = null): bool|int
    {
        try {
            $this->conn->beginTransaction();

            $prev = $this->obtenerPorId($id);
            if (!$prev) { $this->conn->rollBack(); return false; }

            $nuevo = [
                'nombre'   => trim($data['nombre']  ?? ''),
                'usuario'  => trim($data['usuario'] ?? ''),
                'correo'   => $data['correo']   ?? null,
                'telefono' => $data['telefono'] ?? null,
                'id_rol'   => isset($data['id_rol']) && $data['id_rol'] !== '' ? (int)$data['id_rol'] : null,
            ];

            $sql = "UPDATE usuarios
                    SET nombre = :nom,
                        usuario = :usr,
                        correo = :cor,
                        telefono = :tel,
                        id_rol = :rol
                    WHERE id_usuario = :id";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':nom' => $nuevo['nombre'],
                ':usr' => $nuevo['usuario'],
                ':cor' => $nuevo['correo'],
                ':tel' => $nuevo['telefono'],
                ':rol' => $nuevo['id_rol'],
                ':id'  => $id
            ]);

            if (!$ok) { $this->conn->rollBack(); return false; }

            // Bitácora: un registro por campo modificado
            $cambios = $this->compararCambios($prev, $nuevo, ['nombre','usuario','correo','telefono','id_rol']);
            try {
                if (empty($cambios)) {
                    $this->registrarBitacora($idUsuarioBitacora,'UPDATE',$id,'Actualización sin cambios');
                } else {
                    foreach ($cambios as $campo => [$antes,$despues]) {
                        $this->registrarBitacora(
                            $idUsuarioBitacora, 'UPDATE', $id,
                            'Actualización de usuario',
                            [$campo => $antes],
                            [$campo => $despues],
                            $campo
                        );
                    }
                }
            } catch (\Throwable $bt) { /* no romper actualización */ }

            $this->conn->commit();
            return true;

        } catch (\PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ($e->getCode() === '23000') return -1;
            try { $this->registrarBitacora($idUsuarioBitacora,'ERROR',$id,'Error al actualizar usuario: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuarioBitacora,'ERROR',$id,'Error al actualizar usuario: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    // ================== ELIMINAR (borrado lógico) ==================
    public function eliminar(int $id, ?int $idUsuarioBitacora = null): bool
    {
        try {
            $this->conn->beginTransaction();

            $prev = $this->obtenerPorId($id);
            if (!$prev) { $this->conn->rollBack(); return false; }
            if ((string)($prev['activo'] ?? '1') === '0') {
                // ya estaba eliminado: registramos idempotencia y salimos OK
                try {
                    $this->registrarBitacora(
                        $idUsuarioBitacora, 'DELETE', $id,
                        'Borrado lógico ya aplicado',
                        ['activo' => $prev['activo']],
                        ['activo' => 0],
                        'activo'
                    );
                } catch (\Throwable $bt) {}
                $this->conn->commit();
                return true;
            }

            $st = $this->conn->prepare("UPDATE usuarios SET activo = 0 WHERE id_usuario = :id");
            $ok = $st->execute([':id' => $id]);
            if (!$ok) { $this->conn->rollBack(); return false; }

            // Bitácora: cambio de activo
            try {
                $this->registrarBitacora(
                    $idUsuarioBitacora, 'DELETE', $id,
                    'Borrado lógico de usuario',
                    ['activo' => (int)($prev['activo'] ?? 1)],
                    ['activo' => 0],
                    'activo'
                );
            } catch (\Throwable $bt) {}

            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuarioBitacora,'ERROR',$id,'Error al eliminar usuario: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    // ================== LISTA CORTA PARA SELECTS ==================
    // Devuelve: id_usuario, nombre, usuario, nombre_mostrar
    public function listarMin(string $q = '', int $limite = 200): array
    {
        $limite = max(1, min((int)$limite, 1000));

        $sql = "SELECT u.id_usuario, u.nombre, u.usuario
                FROM usuarios u
                WHERE u.activo = 1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (u.nombre LIKE :q1 OR u.usuario LIKE :q2 OR u.correo LIKE :q3)";
            $like = '%'.trim($q).'%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }

        $sql .= " ORDER BY u.nombre ASC
                  LIMIT {$limite}";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
        $st->execute();

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $nom = trim($r['nombre'] ?? '');
            $usr = trim($r['usuario'] ?? '');
            $r['nombre_mostrar'] = $usr !== '' ? "{$nom} ({$usr})" : $nom;
        }
        return $rows;
    }

    // ================== DUPLICADOS ==================
    /**
     * Revisa si existe usuario/correo (opcionalmente excluyendo un id dado).
     * Devuelve: ['usuario'=>bool, 'correo'=>bool]
     */
    public function existeUsuarioCorreo(string $usuario, ?string $correo, ?int $excluirId = null): array
    {
        $dup = ['usuario' => false, 'correo' => false];

        // usuario
        $sqlU = "SELECT 1 FROM usuarios WHERE usuario = :u";
        if ($excluirId) { $sqlU .= " AND id_usuario <> :id"; }
        $stU = $this->conn->prepare($sqlU);
        $stU->bindValue(':u', $usuario, PDO::PARAM_STR);
        if ($excluirId) $stU->bindValue(':id', $excluirId, PDO::PARAM_INT);
        $stU->execute();
        if ($stU->fetchColumn()) $dup['usuario'] = true;

        // correo (si viene)
        if ($correo !== null && $correo !== '') {
            $sqlC = "SELECT 1 FROM usuarios WHERE correo = :c";
            if ($excluirId) { $sqlC .= " AND id_usuario <> :id"; }
            $stC = $this->conn->prepare($sqlC);
            $stC->bindValue(':c', $correo, PDO::PARAM_STR);
            if ($excluirId) $stC->bindValue(':id', $excluirId, PDO::PARAM_INT);
            $stC->execute();
            if ($stC->fetchColumn()) $dup['correo'] = true;
        }

        return $dup;
    }

    // ================== HELPERS ==================
    private function compararCambios(array $prev, array $nuevo, array $campos): array
    {
        $out = [];
        foreach ($campos as $c) {
            $a = array_key_exists($c, $prev) ? $prev[$c] : null;
            $d = array_key_exists($c, $nuevo) ? $nuevo[$c] : null;
            if ((string)$a !== (string)$d) $out[$c] = [$a, $d];
        }
        return $out;
    }

    /**
     * Registra eventos en bitacora_movimientos.
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
    ): void {
        // IP origen (considera proxy)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]); // primera IP si viene lista
        }

        $sql = "INSERT INTO bitacora_movimientos
                (id_usuario, tabla, accion, registro_id, campo_modificado, valor_anterior, valor_nuevo,
                 descripcion, ip_origen, activo, fecha)
                VALUES
                (:usr, :tbl, :acc, :rid, :campo, :val_ant, :val_nvo, :desc, :ip, 1, NOW())";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':usr'     => (int)($idUsuario ?? 0), // <-- nunca NULL
                ':tbl'     => 'usuarios',
                ':acc'     => $accion, // INSERT|UPDATE|DELETE|ERROR...
                ':rid'     => $registroId,
                ':campo'   => $campoModificado,
                ':val_ant' => $valorAnterior ? json_encode($valorAnterior, JSON_UNESCAPED_UNICODE) : null,
                ':val_nvo' => $valorNuevo   ? json_encode($valorNuevo,   JSON_UNESCAPED_UNICODE) : null,
                ':desc'    => $descripcion,
                ':ip'      => $ip
            ]);
        } catch (\Throwable $e) {
            // La bitácora JAMÁS debe romper el flujo de negocio
            // Se puede loguear en error_log si lo deseas:
            // error_log('Bitacora error: '.$e->getMessage());
        }
    }
}
