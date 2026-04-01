<?php
// Incluir conexión PDO (usa el mismo archivo que tu ClienteModel)
include_once '../includes/db.php';

class PrestamoModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
    }

    /* ========================= LISTADO ========================= */
    public function listar(int $pagina = 1, int $limite = 10, array $f = [])
    {
        $pagina = max(1, $pagina);
        $limite = max(1, $limite);
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT p.*
                FROM prestamos p
                WHERE p.activo = 1";
        $params = [];

        // Normaliza filtros
        $q              = trim($f['q']               ?? '');
        $tipoOperacion  = trim($f['tipo_operacion']  ?? ''); // Prestamo | Disposicion | Pago
        $estatus        = trim($f['estatus']         ?? ''); // Pendiente|Pagado|Cancelado|SinRetorno
        $tipoBenef      = trim($f['tipo']            ?? ''); // Cliente|Empleado|Otro
        $idCliente      = (int)($f['id_cliente']     ?? 0);
        $idEmpleado     = (int)($f['id_empleado']    ?? 0);
        $desde          = trim($f['desde']           ?? '');
        $hasta          = trim($f['hasta']           ?? '');

        if ($q !== '') {
            $sql .= " AND (
                p.concepto       LIKE :q1 OR
                CAST(p.id_prestamo AS CHAR) LIKE :q2
            )";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        if ($tipoOperacion !== '') { $sql .= " AND p.tipo_operacion = :tope"; $params[':tope'] = $tipoOperacion; }
        if ($estatus !== '')       { $sql .= " AND p.estatus        = :est";  $params[':est']  = $estatus; }
        if ($tipoBenef !== '')     { $sql .= " AND p.tipo           = :tben"; $params[':tben'] = $tipoBenef; }
        if ($idCliente > 0)        { $sql .= " AND p.id_cliente     = :icl";  $params[':icl']  = $idCliente; }
        if ($idEmpleado > 0)       { $sql .= " AND p.id_empleado    = :iem";  $params[':iem']  = $idEmpleado; }
        if ($desde !== '')         { $sql .= " AND DATE(p.fecha_prestamo) >= :d1"; $params[':d1'] = $desde; }
        if ($hasta !== '')         { $sql .= " AND DATE(p.fecha_prestamo) <= :d2"; $params[':d2'] = $hasta; }

        $sql .= " ORDER BY p.fecha_prestamo DESC, p.id_prestamo DESC
                  LIMIT {$limite} OFFSET {$offset}";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(array $f = []): int
    {
        $sql = "SELECT COUNT(*) total FROM prestamos p WHERE p.activo = 1";
        $params = [];

        $q              = trim($f['q']               ?? '');
        $tipoOperacion  = trim($f['tipo_operacion']  ?? '');
        $estatus        = trim($f['estatus']         ?? '');
        $tipoBenef      = trim($f['tipo']            ?? '');
        $idCliente      = (int)($f['id_cliente']     ?? 0);
        $idEmpleado     = (int)($f['id_empleado']    ?? 0);
        $desde          = trim($f['desde']           ?? '');
        $hasta          = trim($f['hasta']           ?? '');

        if ($q !== '') {
            $sql .= " AND (p.concepto LIKE :q1 OR CAST(p.id_prestamo AS CHAR) LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        if ($tipoOperacion !== '') { $sql .= " AND p.tipo_operacion = :tope"; $params[':tope'] = $tipoOperacion; }
        if ($estatus !== '')       { $sql .= " AND p.estatus        = :est";  $params[':est']  = $estatus; }
        if ($tipoBenef !== '')     { $sql .= " AND p.tipo           = :tben"; $params[':tben'] = $tipoBenef; }
        if ($idCliente > 0)        { $sql .= " AND p.id_cliente     = :icl";  $params[':icl']  = $idCliente; }
        if ($idEmpleado > 0)       { $sql .= " AND p.id_empleado    = :iem";  $params[':iem']  = $idEmpleado; }
        if ($desde !== '')         { $sql .= " AND DATE(p.fecha_prestamo) >= :d1"; $params[':d1'] = $desde; }
        if ($hasta !== '')         { $sql .= " AND DATE(p.fecha_prestamo) <= :d2"; $params[':d2'] = $hasta; }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();

        return (int)($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /* ========================= CRUD ========================= */
     public function obtenerPorId(int $id)
    {
        // Préstamo
        $st = $this->conn->prepare("SELECT * FROM prestamos WHERE id_prestamo = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $prestamo = $st->fetch(PDO::FETCH_ASSOC);

        if (!$prestamo) return null;

        // Abonos + método de pago (sin usuario del abono)
        $sqlAb = "SELECT a.*,
                        fp.descripcion AS forma_pago_desc
                FROM abonos a
                LEFT JOIN formas_pago fp ON fp.id_forma_pago = a.id_forma_pago
                WHERE a.id_prestamo = :id AND a.activo = 1
                ORDER BY a.fecha_abono DESC, a.id_abono DESC";
        $ab = $this->conn->prepare($sqlAb);
        $ab->bindValue(':id', $id, PDO::PARAM_INT);
        $ab->execute();
        $abonos = $ab->fetchAll(PDO::FETCH_ASSOC);

        return ['prestamo'=>$prestamo, 'abonos'=>$abonos];
    }

    public function crear(array $data, ?int $idUsuario = null): int
    {
        try {
            $this->conn->beginTransaction();

            // Reglas de negocio
            $tipoOp  = $data['tipo_operacion'] ?? 'Prestamo'; // Prestamo | Disposicion | Pago
            $monto   = (float)($data['monto_total'] ?? 0);
            if ($monto <= 0) throw new Exception('El monto debe ser mayor a 0');

            $idFormaPago = isset($data['id_forma_pago']) ? (int)$data['id_forma_pago'] : null;
            if ($tipoOp === 'Pago') {
                if (!$this->formaPagoValida($idFormaPago)) {
                    throw new Exception('Selecciona una forma de pago válida para Pago');
                }
            } else {
                $idFormaPago = null;
            }

            $estatus = ($tipoOp === 'Disposicion') ? 'SinRetorno' : 'Pendiente';
            $saldo   = ($tipoOp === 'Prestamo') ? $monto : 0;

            $sql = "INSERT INTO prestamos
                    (tipo_operacion, tipo, id_cliente, id_empleado, id_usuario,
                     monto_total, saldo, concepto, fecha_prestamo, estatus, id_forma_pago, activo, fecha_creacion)
                    VALUES
                    (:tope, :tipo, :idc, :ide, :usr, :monto, :saldo, :concepto, :fch, :est, :idfp, 1, NOW())";

            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':tope'    => $tipoOp,
                ':tipo'    => $data['tipo'] ?? 'Cliente', // Cliente|Empleado|Otro
                ':idc'     => !empty($data['id_cliente'])  ? (int)$data['id_cliente'] : null,
                ':ide'     => !empty($data['id_empleado']) ? (int)$data['id_empleado'] : null,
                ':usr'     => $idUsuario,
                ':monto'   => $monto,
                ':saldo'   => $saldo,
                ':concepto'=> $data['concepto'] ?? null,
                ':fch'     => $data['fecha_prestamo'] ?? date('Y-m-d H:i:s'),
                ':est'     => $estatus,
                ':idfp'    => $idFormaPago
            ]);

            if (!$ok) { $this->conn->rollBack(); return 0; }

            $id = (int)$this->conn->lastInsertId();

            // Bitácora: INSERT (valores nuevos)
            $this->registrarBitacora(
                $idUsuario,
                'INSERT',
                $id,
                'Alta de préstamo/disposición',
                null,
                [
                    'tipo_operacion' => $tipoOp,
                    'tipo'           => $data['tipo'] ?? 'Cliente',
                    'id_cliente'     => $data['id_cliente']  ?? null,
                    'id_empleado'    => $data['id_empleado'] ?? null,
                    'monto_total'    => $monto,
                    'saldo'          => $saldo,
                    'concepto'       => $data['concepto'] ?? null,
                    'fecha_prestamo' => $data['fecha_prestamo'] ?? date('Y-m-d H:i:s'),
                    'estatus'        => $estatus,
                    'id_forma_pago'   => $idFormaPago
                ]
            );

            $this->conn->commit();
            return $id;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', 0, 'Error al crear préstamo: '.$e->getMessage()); } catch (\Throwable $t) {}
            return 0;
        }
    }

    /**
     * Actualiza campos editables del préstamo. Si cambia monto_total y es un Préstamo,
     * el saldo se ajusta: saldo_nuevo = GREATEST(0, saldo_actual + (monto_total_nuevo - monto_total_anterior))
     */
    public function actualizar(int $id, array $data, ?int $idUsuario = null): bool
    {
        try {
            $this->conn->beginTransaction();

            $prev = $this->obtenerSoloPrestamo($id);
            if (!$prev) { $this->conn->rollBack(); return false; }

            // Campos editables
            $nuevo = [
                'tipo'         => $data['tipo'] ?? $prev['tipo'],
                'id_cliente'   => $data['id_cliente']  ?? $prev['id_cliente'],
                'id_empleado'  => $data['id_empleado'] ?? $prev['id_empleado'],
                'concepto'     => $data['concepto']    ?? $prev['concepto'],
                'fecha_prestamo' => $data['fecha_prestamo'] ?? $prev['fecha_prestamo'],
                'monto_total'  => isset($data['monto_total']) ? (float)$data['monto_total'] : (float)$prev['monto_total'],
            ];

            $cambios = [];
            foreach ($nuevo as $k=>$v) {
                $prevVal = $prev[$k] ?? null;
                if ((string)$prevVal !== (string)$v) {
                    $cambios[$k] = ['antes'=>$prevVal, 'despues'=>$v];
                }
            }

            // Ajuste de saldo si cambió monto_total y es Prestamo (no Disposicion)
            $saldoUpdateSql = '';
            $saldoUpdateParams = [];
            if (isset($cambios['monto_total']) && $prev['tipo_operacion'] === 'Prestamo') {
                $delta = (float)$nuevo['monto_total'] - (float)$prev['monto_total'];
                $saldoUpdateSql = ", saldo = GREATEST(0, saldo + :deltaSaldo)";
                $saldoUpdateParams[':deltaSaldo'] = $delta;
            }

            if (empty($cambios) && $saldoUpdateSql === '') {
                $this->conn->commit();
                return true;
            }

            $sql = "UPDATE prestamos
                    SET tipo = :tipo,
                        id_cliente = :idc,
                        id_empleado = :ide,
                        concepto = :concepto,
                        fecha_prestamo = :fch,
                        monto_total = :monto
                        {$saldoUpdateSql}
                    WHERE id_prestamo = :id";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute(array_merge([
                ':tipo'   => $nuevo['tipo'],
                ':idc'    => !empty($nuevo['id_cliente'])  ? (int)$nuevo['id_cliente'] : null,
                ':ide'    => !empty($nuevo['id_empleado']) ? (int)$nuevo['id_empleado'] : null,
                ':concepto'=> $nuevo['concepto'],
                ':fch'    => $nuevo['fecha_prestamo'],
                ':monto'  => $nuevo['monto_total'],
                ':id'     => $id
            ], $saldoUpdateParams));

            if (!$ok) { $this->conn->rollBack(); return false; }

            // Bitácora: una fila por campo modificado
            foreach ($cambios as $campo => $vals) {
                $this->registrarBitacora(
                    $idUsuario,
                    'UPDATE',
                    $id,
                    'Actualización de préstamo - campo: ' . $campo,
                    [$campo => $vals['antes']],
                    [$campo => $vals['despues']],
                    $campo
                );
            }

            // Si se ajustó el saldo por delta de monto_total, deja constancia
            if ($saldoUpdateSql !== '') {
                $this->registrarBitacora(
                    $idUsuario,
                    'UPDATE',
                    $id,
                    'Ajuste de saldo por cambio de monto_total',
                    ['saldo' => $prev['saldo']],
                    ['saldo' => '(saldo + delta)']
                );
            }

            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al actualizar préstamo: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    /**
     * Registra un abono y actualiza el saldo del préstamo.
     * $idFormaPago es opcional para mantener compatibilidad con llamadas existentes.
     */
    public function abonar(int $idPrestamo,float $monto,string $fechaAbono,?string $refPago,?int $idUsuario = null,?int $idFormaPago = null): bool 
    {
        if ($monto <= 0) return false;

        try {
            $this->conn->beginTransaction();

            // Valida que sea un préstamo (no disposición)
            $prev = $this->obtenerSoloPrestamo($idPrestamo);
            if (!$prev || $prev['tipo_operacion'] !== 'Prestamo') {
                $this->conn->rollBack();
                return false;
            }

            // Normaliza forma de pago (NULL si no existe)
            $idFormaPago = $this->formaPagoValida($idFormaPago) ? (int)$idFormaPago : null;

            // 1) Insertar abono (ahora con id_forma_pago)
            $sqlA = "INSERT INTO abonos (id_prestamo, monto, fecha_abono, referencia_pago, id_forma_pago, activo, fecha_creacion)
                     VALUES (:id, :monto, :fecha, :ref, :fp, 1, NOW())";
            $stA = $this->conn->prepare($sqlA);
            $stA->execute([
                ':id'    => $idPrestamo,
                ':monto' => $monto,
                ':fecha' => $fechaAbono,
                ':ref'   => $refPago,
                ':fp'    => $idFormaPago
            ]);
            $idAbono = (int)$this->conn->lastInsertId();

            // Bitácora: INSERT en abonos
            $this->registrarBitacora(
                $idUsuario,
                'INSERT',
                $idAbono,
                'Alta de abono',
                null,
                [
                    'id_prestamo'   => $idPrestamo,
                    'monto'         => $monto,
                    'fecha_abono'   => $fechaAbono,
                    'referencia_pago'=> $refPago,
                    'id_forma_pago' => $idFormaPago
                ],
                null,
                'abonos'
            );

            // 2) Actualizar saldo
            $saldoAntes = (float)$prev['saldo'];
            $sqlU = "UPDATE prestamos SET saldo = GREATEST(0, saldo - :monto) WHERE id_prestamo=:id";
            $this->conn->prepare($sqlU)->execute([':monto'=>$monto, ':id'=>$idPrestamo]);

            // 3) Si saldo llega a 0 => estatus Pagado
            $stS = $this->conn->prepare("SELECT saldo, estatus FROM prestamos WHERE id_prestamo = :id");
            $stS->execute([':id'=>$idPrestamo]);
            $row = $stS->fetch(PDO::FETCH_ASSOC);
            $saldoDespues = (float)($row['saldo'] ?? 0);
            $estatusPrev  = $row['estatus'] ?? 'Pendiente';

            // Bitácora: cambio de saldo
            $this->registrarBitacora(
                $idUsuario,
                'UPDATE',
                $idPrestamo,
                'Abono aplicado (actualiza saldo)',
                ['saldo'=>$saldoAntes],
                ['saldo'=>$saldoDespues],
                'saldo'
            );

            if ($saldoDespues <= 0.00001 && $estatusPrev !== 'Pagado') {
                $this->conn->prepare("UPDATE prestamos SET estatus = 'Pagado' WHERE id_prestamo = :id")
                           ->execute([':id'=>$idPrestamo]);

                $this->registrarBitacora(
                    $idUsuario,
                    'UPDATE',
                    $idPrestamo,
                    'Estatus cambiado a Pagado por saldo 0',
                    ['estatus'=>$estatusPrev],
                    ['estatus'=>'Pagado'],
                    'estatus'
                );
            }

            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try { $this->registrarBitacora($idUsuario, 'ERROR', $idPrestamo, 'Error al abonar: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    public function cancelar(int $id, ?int $idUsuario = null): bool
    {
        try {
            $prev = $this->obtenerSoloPrestamo($id);
            if (!$prev) return false;

            $ok = $this->conn->prepare("UPDATE prestamos SET estatus = 'Cancelado' WHERE id_prestamo = :id")
                             ->execute([':id'=>$id]);

            if ($ok) {
                $this->registrarBitacora(
                    $idUsuario,
                    'UPDATE',
                    $id,
                    'Cancelación de préstamo/disposición',
                    ['estatus'=>$prev['estatus']],
                    ['estatus'=>'Cancelado'],
                    'estatus'
                );
            }
            return (bool)$ok;

        } catch (\Throwable $e) {
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al cancelar: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    public function eliminar(int $id, ?int $idUsuario = null): bool
    {
        try {
            $prev = $this->obtenerSoloPrestamo($id);
            if (!$prev) return false;

            $ok = $this->conn->prepare("UPDATE prestamos SET activo = 0 WHERE id_prestamo = :id")
                             ->execute([':id'=>$id]);

            if ($ok) {
                $this->registrarBitacora(
                    $idUsuario,
                    'DELETE',
                    $id,
                    'Borrado lógico de préstamo/disposición',
                    ['activo'=>(string)($prev['activo'] ?? '1')],
                    ['activo'=>'0'],
                    'activo'
                );
            }
            return (bool)$ok;

        } catch (\Throwable $e) {
            try { $this->registrarBitacora($idUsuario, 'ERROR', $id, 'Error al eliminar: '.$e->getMessage()); } catch (\Throwable $t) {}
            return false;
        }
    }

    /* ===== Helpers ===== */
    private function obtenerSoloPrestamo(int $id)
    {
        $st = $this->conn->prepare("SELECT * FROM prestamos WHERE id_prestamo = :id LIMIT 1");
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    // Para selects: préstamos pendientes (con saldo > 0)
    public function listarMin(string $q = '', int $limite = 50)
    {
        $sql = "SELECT id_prestamo, concepto, saldo
                FROM prestamos
                WHERE activo = 1 AND tipo_operacion = 'Prestamo' AND estatus IN ('Pendiente') AND saldo > 0";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (concepto LIKE :q1 OR CAST(id_prestamo AS CHAR) LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        $sql .= " ORDER BY fecha_prestamo DESC LIMIT :lim";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':lim', (int)$limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ Checa si la forma de pago existe y está activa
    private function formaPagoValida(?int $id = null): bool
    {
        if (empty($id)) return false;
        $st = $this->conn->prepare("SELECT 1 FROM formas_pago WHERE id_forma_pago = :id AND activo = 1 LIMIT 1");
        $st->execute([':id'=>$id]);
        return (bool)$st->fetchColumn();
    }

    /* ===== Bitácora (misma firma/estilo que ClienteModel) ===== */
    private function registrarBitacora(
        ?int $idUsuario,
        string $accion,
        int $registroId,
        string $descripcion = '',
        ?array $valorAnterior = null,
        ?array $valorNuevo = null,
        ?string $campoModificado = null,
        ?string $tablaForzada = null
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
            ':usr'   => $idUsuario,
            ':tbl'   => $tablaForzada ?: 'prestamos',
            ':acc'   => $accion,
            ':rid'   => $registroId,
            ':campo' => $campoModificado,
            ':val_ant' => $valorAnterior ? json_encode($valorAnterior, JSON_UNESCAPED_UNICODE) : null,
            ':val_nvo' => $valorNuevo   ? json_encode($valorNuevo,   JSON_UNESCAPED_UNICODE) : null,
            ':desc'  => $descripcion,
            ':ip'    => $ip
        ]);
    }
}
