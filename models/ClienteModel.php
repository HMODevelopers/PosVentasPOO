<?php
// Incluir conexión PDO
include_once '../includes/db.php';

class ClienteModel
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

        $sql = "SELECT c.*
                FROM clientes c
                WHERE c.activo = 1";
        $params = [];

        // Normaliza filtros
        $q         = trim($filtros['q']         ?? '');
        $nombre    = trim($filtros['nombre']    ?? '');
        $rfc       = trim($filtros['rfc']       ?? '');
        $correo    = trim($filtros['correo']    ?? '');
        $telefono  = trim($filtros['telefono']  ?? '');
        $uso_cfdi  = trim($filtros['uso_cfdi']  ?? '');

        // Filtro global q (placeholders únicos)
        if ($q !== '') {
            $sql .= " AND (
                c.nombre    LIKE :q1 OR
                c.rfc       LIKE :q2 OR
                c.correo    LIKE :q3 OR
                c.telefono  LIKE :q4 OR
                c.uso_cfdi  LIKE :q5
            )";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
            $params[':q3'] = "%{$q}%";
            $params[':q4'] = "%{$q}%";
            $params[':q5'] = "%{$q}%";
        }
        // Filtros por campo
        if ($nombre !== '')   { $sql .= " AND c.nombre   LIKE :nom"; $params[':nom'] = "%{$nombre}%"; }
        if ($rfc !== '')      { $sql .= " AND c.rfc      LIKE :rfc"; $params[':rfc'] = "%{$rfc}%"; }
        if ($correo !== '')   { $sql .= " AND c.correo   LIKE :cor"; $params[':cor'] = "%{$correo}%"; }
        if ($telefono !== '') { $sql .= " AND c.telefono LIKE :tel"; $params[':tel'] = "%{$telefono}%"; }
        if ($uso_cfdi !== '') { $sql .= " AND c.uso_cfdi LIKE :uso"; $params[':uso'] = "%{$uso_cfdi}%"; }

        $sql .= " ORDER BY c.id_cliente ASC
                  LIMIT {$limite} OFFSET {$offset}"; // enteros ya validados

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) { $st->bindValue($k, $v); }
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(array $filtros = [])
    {
        $sql = "SELECT COUNT(*) AS total
                FROM clientes c
                WHERE c.activo = 1";
        $params = [];

        $q         = trim($filtros['q']         ?? '');
        $nombre    = trim($filtros['nombre']    ?? '');
        $rfc       = trim($filtros['rfc']       ?? '');
        $correo    = trim($filtros['correo']    ?? '');
        $telefono  = trim($filtros['telefono']  ?? '');
        $uso_cfdi  = trim($filtros['uso_cfdi']  ?? '');

        if ($q !== '') {
            $sql .= " AND (
                c.nombre    LIKE :q1 OR
                c.rfc       LIKE :q2 OR
                c.correo    LIKE :q3 OR
                c.telefono  LIKE :q4 OR
                c.uso_cfdi  LIKE :q5
            )";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
            $params[':q3'] = "%{$q}%";
            $params[':q4'] = "%{$q}%";
            $params[':q5'] = "%{$q}%";
        }
        if ($nombre !== '')   { $sql .= " AND c.nombre   LIKE :nom"; $params[':nom'] = "%{$nombre}%"; }
        if ($rfc !== '')      { $sql .= " AND c.rfc      LIKE :rfc"; $params[':rfc'] = "%{$rfc}%"; }
        if ($correo !== '')   { $sql .= " AND c.correo   LIKE :cor"; $params[':cor'] = "%{$correo}%"; }
        if ($telefono !== '') { $sql .= " AND c.telefono LIKE :tel"; $params[':tel'] = "%{$telefono}%"; }
        if ($uso_cfdi !== '') { $sql .= " AND c.uso_cfdi LIKE :uso"; $params[':uso'] = "%{$uso_cfdi}%"; }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) { $st->bindValue($k, $v); }
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    // ========== CRUD ==========
    public function obtenerPorId(int $id)
    {
        $st = $this->conn->prepare("SELECT * FROM clientes WHERE id_cliente = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function crear(array $data, ?int $idUsuario = null)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO clientes
                    (nombre, rfc, correo, telefono, direccion, uso_cfdi, activo, fecha_creacion)
                    VALUES (:nom, :rfc, :cor, :tel, :dir, :uso, 1, NOW())";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':nom' => trim($data['nombre'] ?? ''),
                ':rfc' => $data['rfc'] ?? null,
                ':cor' => $data['correo'] ?? null,
                ':tel' => $data['telefono'] ?? null,
                ':dir' => $data['direccion'] ?? null,
                ':uso' => $data['uso_cfdi'] ?? null,
            ]);

            if (!$ok) { $this->conn->rollBack(); return 0; }

            $id = (int)$this->conn->lastInsertId();

            // Bitácora: INSERT
            $this->registrarBitacora(
                $idUsuario,
                'INSERT',
                $id,
                'Alta de cliente',
                null,
                [
                    'nombre'    => trim($data['nombre'] ?? ''),
                    'rfc'       => $data['rfc'] ?? null,
                    'correo'    => $data['correo'] ?? null,
                    'telefono'  => $data['telefono'] ?? null,
                    'direccion' => $data['direccion'] ?? null,
                    'uso_cfdi'  => $data['uso_cfdi'] ?? null,
                ]
            );

            $this->conn->commit();
            return $id;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', 0, 'Error al crear cliente: '.$e->getMessage()); } catch (\Throwable $t) {}
            return 0;
        }
    }

    public function actualizar(int $id, array $data, ?int $idUsuario = null)
    {
        try {
            $this->conn->beginTransaction();

            $prev = $this->obtenerPorId($id);
            if (!$prev) { $this->conn->rollBack(); return false; }

            // Normaliza payload y detecta cambios antes de actualizar
            $nuevo = [
                'nombre'    => trim($data['nombre'] ?? ''),
                'rfc'       => $data['rfc'] ?? null,
                'correo'    => $data['correo'] ?? null,
                'telefono'  => $data['telefono'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'uso_cfdi'  => $data['uso_cfdi'] ?? null,
            ];

            $cambios = [];
            foreach ($nuevo as $k => $v) {
                $prevVal = $prev[$k] ?? null;
                if ((string)$prevVal !== (string)$v) {
                    $cambios[$k] = ['antes' => $prevVal, 'despues' => $v];
                }
            }

            if (empty($cambios)) {
                $this->conn->commit();
                return true; // Sin cambios -> idempotente
            }

            // Ejecutar UPDATE
            $sql = "UPDATE clientes
                    SET nombre = :nom, rfc = :rfc, correo = :cor, telefono = :tel, direccion = :dir, uso_cfdi = :uso
                    WHERE id_cliente = :id";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':nom' => $nuevo['nombre'],
                ':rfc' => $nuevo['rfc'],
                ':cor' => $nuevo['correo'],
                ':tel' => $nuevo['telefono'],
                ':dir' => $nuevo['direccion'],
                ':uso' => $nuevo['uso_cfdi'],
                ':id'  => $id
            ]);

            if (!$ok) { $this->conn->rollBack(); return false; }

            // Bitácora: una fila por campo modificado
            foreach ($cambios as $campo => $vals) {
                $this->registrarBitacora(
                    $idUsuario,
                    'UPDATE',
                    $id,
                    'Actualización de cliente - campo: ' . $campo,
                    [$campo => $vals['antes']],
                    [$campo => $vals['despues']],
                    $campo
                );
            }

            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al actualizar cliente: '.$e->getMessage()); } catch (\Throwable $t) {}
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

            $st = $this->conn->prepare("UPDATE clientes SET activo = 0 WHERE id_cliente = :id");
            $ok = $st->execute([':id' => $id]);

            if (!$ok) { $this->conn->rollBack(); return false; }

            $this->registrarBitacora(
                $idUsuario,
                'DELETE',
                $id,
                'Borrado lógico de cliente',
                ['activo' => (string)($prev['activo'] ?? '1')],
                ['activo' => '0'],
                'activo'
            );

            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al eliminar cliente: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    // ========== PARA SELECTS / AUTOCOMPLETE ==========
    // Devuelve {id_cliente, nombre}
    public function listarMin(string $q = '', int $limite = 50)
    {
        $sql = "SELECT id_cliente, nombre
                FROM clientes
                WHERE activo = 1";
        $params = [];
        if ($q !== '') {
            // placeholders únicos para evitar HY093
            $sql .= " AND (nombre LIKE :q1 OR rfc LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        $sql .= " ORDER BY nombre ASC LIMIT :lim";

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

        $st = $this->conn->prepare($sql);
        $st->execute([
            ':usr'     => $idUsuario,
            ':tbl'     => 'clientes',
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
