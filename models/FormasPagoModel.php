<?php
// models/FormasPagoModel.php
include_once '../includes/db.php';

class FormasPagoModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;

        // Hermosillo: UTC-07 sin DST
        try { $this->conn->exec("SET time_zone='-07:00'"); } catch (\Throwable $th) {}
    }

    /* ===== Helpers de zona ===== */
    private function tzHerm(): \DateTimeZone { return new \DateTimeZone('America/Hermosillo'); }
    private function ahoraHermStr(): string  { return (new \DateTime('now', $this->tzHerm()))->format('Y-m-d H:i:s'); }

    /* ===== Listado / consulta ===== */

    // Paginado con búsqueda por descripcion/clave_sat
    public function obtenerFormasPago(int $pagina=1, int $limite=10, string $q=''): array
    {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT id_forma_pago, clave_sat, descripcion, activo, fecha_creacion
                FROM formas_pago
                WHERE 1=1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (descripcion LIKE :q OR clave_sat LIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $sql .= " ORDER BY activo DESC, descripcion ASC
                  LIMIT :lim OFFSET :off";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $st->bindValue($k, $v); }
        $st->bindValue(':lim', $limite, \PDO::PARAM_INT);
        $st->bindValue(':off', $offset, \PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function contarFormasPago(string $q=''): int
    {
        $sql = "SELECT COUNT(*) FROM formas_pago WHERE 1=1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (descripcion LIKE :q OR clave_sat LIKE :q)";
            $params[':q'] = "%{$q}%";
        }
        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) { $st->bindValue($k, $v); }
        $st->execute();
        return (int)$st->fetchColumn();
    }

    // Para “select” (activos)
    public function listarActivas(): array
    {
        $st = $this->conn->prepare(
            "SELECT id_forma_pago, descripcion, clave_sat
             FROM formas_pago
             WHERE activo = 1
             ORDER BY descripcion ASC"
        );
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $st = $this->conn->prepare(
            "SELECT id_forma_pago, clave_sat, descripcion, activo, fecha_creacion
             FROM formas_pago
             WHERE id_forma_pago = :id
             LIMIT 1"
        );
        $st->execute([':id'=>$id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* ===== Crear / actualizar / estatus ===== */

    public function crear(array $data, int $idUsuario): array
    {
        $clave = trim($data['clave_sat'] ?? '');
        $desc  = trim($data['descripcion'] ?? '');
        if ($clave === '' || $desc === '') {
            return ['ok'=>false, 'msg'=>'Clave SAT y descripción son obligatorias.'];
        }

        // evita duplicados “lógicos”
        $chk = $this->conn->prepare("SELECT COUNT(*) FROM formas_pago WHERE UPPER(descripcion)=UPPER(:d)");
        $chk->execute([':d'=>$desc]);
        if ((int)$chk->fetchColumn() > 0) {
            return ['ok'=>false, 'msg'=>'Ya existe una forma de pago con esa descripción.'];
        }

        $fecha = $this->ahoraHermStr();

        try {
            $st = $this->conn->prepare(
                "INSERT INTO formas_pago (clave_sat, descripcion, activo, fecha_creacion)
                 VALUES (:c, :d, 1, :f)"
            );
            $st->execute([':c'=>$clave, ':d'=>$desc, ':f'=>$fecha]);

            $id = (int)$this->conn->lastInsertId();

            $this->registrarBitacora($idUsuario, 'formas_pago', 'INSERT', $id,
                'Alta de forma de pago', null,
                json_encode(['clave_sat'=>$clave, 'descripcion'=>$desc, 'activo'=>1]), null, $fecha);

            return ['ok'=>true, 'id_forma_pago'=>$id];
        } catch (\PDOException $e) {
            // por si hay UNIQUE a nivel BD
            if (($e->errorInfo[1] ?? 0) === 1062) {
                return ['ok'=>false, 'msg'=>'Duplicado: clave o descripción ya existentes.'];
            }
            return ['ok'=>false, 'msg'=>'Error BD: '.$e->getMessage()];
        }
    }

    public function actualizar(int $id, array $data, int $idUsuario): array
    {
        $prev = $this->obtenerPorId($id);
        if (!$prev) return ['ok'=>false, 'msg'=>'Registro no encontrado'];

        $clave = trim($data['clave_sat'] ?? $prev['clave_sat']);
        $desc  = trim($data['descripcion'] ?? $prev['descripcion']);

        // valida duplicado de descripción (excluyéndome)
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM formas_pago
             WHERE UPPER(descripcion)=UPPER(:d) AND id_forma_pago <> :id"
        );
        $chk->execute([':d'=>$desc, ':id'=>$id]);
        if ((int)$chk->fetchColumn() > 0) {
            return ['ok'=>false, 'msg'=>'Ya existe otra forma de pago con esa descripción.'];
        }

        try {
            $st = $this->conn->prepare(
                "UPDATE formas_pago
                 SET clave_sat=:c, descripcion=:d
                 WHERE id_forma_pago = :id"
            );
            $st->execute([':c'=>$clave, ':d'=>$desc, ':id'=>$id]);

            $this->registrarBitacora($idUsuario, 'formas_pago', 'UPDATE', $id,
                'Edición de forma de pago',
                json_encode($prev),
                json_encode(['clave_sat'=>$clave,'descripcion'=>$desc,'activo'=>$prev['activo']]));

            return ['ok'=>true];
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                return ['ok'=>false, 'msg'=>'Duplicado: clave o descripción ya existentes.'];
            }
            return ['ok'=>false, 'msg'=>'Error BD: '.$e->getMessage()];
        }
    }

    public function cambiarActivo(int $id, bool $activo, int $idUsuario): array
    {
        $prev = $this->obtenerPorId($id);
        if (!$prev) return ['ok'=>false, 'msg'=>'Registro no encontrado'];

        $st = $this->conn->prepare("UPDATE formas_pago SET activo=:a WHERE id_forma_pago=:id");
        $st->execute([':a'=>$activo?1:0, ':id'=>$id]);

        $this->registrarBitacora($idUsuario, 'formas_pago', $activo?'ENABLE':'DISABLE', $id,
            ($activo?'Activación':'Desactivación').' de forma de pago',
            json_encode(['activo'=>$prev['activo']]),
            json_encode(['activo'=>$activo?1:0]));

        return ['ok'=>true];
    }

    public function eliminarLogico(int $id, int $idUsuario): array
    {
        return $this->cambiarActivo($id, false, $idUsuario);
    }

    /* ===== Bitácora ===== */
    private function registrarBitacora(
        int $idUsuario,
        string $tabla,
        string $accion,
        int $registroId,
        string $descripcion = '',
        ?string $valorAnterior = null,
        ?string $valorNuevo = null,
        ?string $campoModificado = null,
        ?string $fechaRegistro = null
    ): void
    {
        $fechaRegistro = $fechaRegistro ?: $this->ahoraHermStr();

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $sql = "INSERT INTO bitacora_movimientos
                (id_usuario, tabla, accion, registro_id, campo_modificado, valor_anterior, valor_nuevo,
                 descripcion, ip_origen, activo, fecha)
                VALUES
                (:usr, :tbl, :acc, :rid, :campo, :val_ant, :val_nvo, :desc, :ip, 1, :freg)";

        $st = $this->conn->prepare($sql);
        $st->execute([
            ':usr'   => $idUsuario,
            ':tbl'   => $tabla,
            ':acc'   => $accion,
            ':rid'   => $registroId,
            ':campo' => $campoModificado,
            ':val_ant' => $valorAnterior,
            ':val_nvo' => $valorNuevo,
            ':desc'  => $descripcion,
            ':ip'    => $ip,
            ':freg'  => $fechaRegistro
        ]);
    }
}
