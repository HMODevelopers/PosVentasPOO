<?php
// models/VentaModel.php
// Incluir conexión PDO (usa la configuración de includes/db.php)
include_once '../includes/db.php';

class VentaModel
{
    private $conn;

    public function __construct()
    {
        // Utilizamos la conexión global $pdo
        global $pdo;
        $this->conn = $pdo;

        // Opcional: fija la zona para esta sesión de MySQL (si no tienes tz tables, usa el offset fijo de Sonora)
        try {
            // Sonora (Hermosillo) es UTC-07 todo el año (sin DST)
            $this->conn->exec("SET time_zone = '-07:00'");
        } catch (\Throwable $th) { /* no crítico */ }
    }

    /* ============================
       Helpers de Zona Horaria
       ============================ */
    private function tzHerm(): \DateTimeZone
    {
        return new \DateTimeZone('America/Hermosillo');
    }

    /** Hora actual de Hermosillo (Y-m-d H:i:s) */
    private function ahoraHermStr(): string
    {
        return (new \DateTime('now', $this->tzHerm()))->format('Y-m-d H:i:s');
    }

    /** Combina fecha (Y-m-d, del input) + hora actual de Hermosillo -> Y-m-d H:i:s */
    private function fechaHoraDesdeInput(string $fechaYmd = null): string
    {
        $tz    = $this->tzHerm();
        $hoy   = new \DateTime('now', $tz);
        $fecha = $fechaYmd ?: $hoy->format('Y-m-d');
        $hora  = $hoy->format('H:i:s');
        return $fecha . ' ' . $hora;
    }

    /* ============================
       LISTADO / CONSULTA
       ============================ */

    // ✅ Obtener ventas paginadas (incluye Guardadas sin forma de pago)
    public function obtenerVentas($pagina = 1, $limite = 10, $folio = '', $fecha = '')
    {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT v.*, 
                    c.nombre AS cliente, 
                    u.nombre AS usuario, 
                    cj.nombre AS caja,
                    COALESCE(fp.descripcion, '—') AS forma_pago,   -- LEFT + COALESCE
                    tp.nombre AS tipo_precio
                FROM ventas v
                LEFT JOIN clientes c      ON v.id_cliente     = c.id_cliente
                INNER JOIN usuarios u     ON v.id_usuario     = u.id_usuario
                INNER JOIN cajas cj       ON v.id_caja        = cj.id_caja
                LEFT JOIN formas_pago fp  ON v.id_forma_pago  = fp.id_forma_pago  -- ← LEFT JOIN
                INNER JOIN tipo_precio tp ON v.id_tipo_precio = tp.id_tipo_precio
                WHERE v.activo = 1";

        $params = [];

        // Filtro por folio (LIKE parcial)
        if (!empty($folio)) {
            $sql .= " AND v.folio LIKE :folio";
            $params[':folio'] = "%$folio%";
        }

        // Filtro por fecha exacta (compara por DATE)
        if (!empty($fecha)) {
            $sql .= " AND DATE(v.fecha) = :fecha";
            $params[':fecha'] = $fecha;
        }

        $sql .= " ORDER BY v.id_venta DESC LIMIT :limite OFFSET :offset";

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limite', (int)$limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ✅ Contar total de ventas activas
    public function contarVentas($folio = '', $fecha = '')
    {
        $sql = "SELECT COUNT(*) as total FROM ventas v WHERE v.activo = 1";
        $params = [];

        if (!empty($folio)) {
            $sql .= " AND v.folio LIKE :folio";
            $params[':folio'] = "%$folio%";
        }

        if (!empty($fecha)) {
            $sql .= " AND DATE(v.fecha) = :fecha";
            $params[':fecha'] = $fecha;
        }

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
    }

    // ✅ Obtener una venta específica por ID (incluye Guardadas sin forma de pago)
    public function obtenerVentaPorId($idVenta)
    {
        $sql = "SELECT v.*,
                    c.nombre AS cliente,
                    u.nombre AS usuario,
                    cj.nombre AS caja,
                    COALESCE(fp.descripcion, '—') AS forma_pago,   -- LEFT + COALESCE
                    tp.nombre AS tipo_precio
                FROM ventas v
                LEFT JOIN clientes c      ON v.id_cliente     = c.id_cliente
                INNER JOIN usuarios u     ON v.id_usuario     = u.id_usuario
                INNER JOIN cajas cj       ON v.id_caja        = cj.id_caja
                LEFT JOIN formas_pago fp  ON v.id_forma_pago  = fp.id_forma_pago  -- ← LEFT JOIN
                INNER JOIN tipo_precio tp ON v.id_tipo_precio = tp.id_tipo_precio
                WHERE v.id_venta = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $idVenta, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // ✅ Obtener el detalle de productos vendidos en una venta
    public function obtenerDetalleVenta($idVenta)
    {
        $sql = "SELECT vd.*, 
                       p.descripcion AS producto,
                       p.codigo AS codigo
                FROM ventas_detalle vd
                INNER JOIN productos p ON vd.id_producto = p.id_producto
                WHERE vd.id_venta = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $idVenta);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ============================
       FOLIOS
       ============================ */

    // Folio candidato "YY-NNNNN" calculado desde la tabla ventas (sin bloquear)
    public function sugerirFolioPorFecha(string $fecha = null): array
    {
        $fecha = $fecha ?: (new \DateTime('now', $this->tzHerm()))->format('Y-m-d');
        $anio  = (int)date('Y', strtotime($fecha));
        $folio = $this->generarFolioDesdeVentas($anio); // solo candidato
        return ['ok' => true, 'folio' => $folio, 'anio' => $anio];
    }

    // Genera folio candidato "YY-NNNNN" leyendo el máximo en ventas
    private function generarFolioDesdeVentas(int $anio): string
    {
        $yy   = $anio % 100;
        $pref = sprintf('%02d-', $yy); // "25-"
        $like = $pref . '%';

        $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(folio, 4) AS UNSIGNED)), 0)
                FROM ventas
                WHERE folio LIKE :like";
        $st  = $this->conn->prepare($sql);
        $st->execute([':like' => $like]);

        $max  = (int)$st->fetchColumn(); // 0 si no hay ventas del año
        $next = $max + 1;
        return sprintf('%s%05d', $pref, $next); // "25-00001"
    }

    // Candado por año para evitar carreras entre cajas
    private function obtenerLockFolio(int $anio, int $timeout = 5): bool
    {
        $name = 'folio_ventas_' . $anio;
        $q    = $this->conn->query("SELECT GET_LOCK(".$this->conn->quote($name).", {$timeout})");
        return (int)$q->fetchColumn() === 1;
    }
    private function liberarLockFolio(int $anio): void
    {
        $name = 'folio_ventas_' . $anio;
        try { $this->conn->query("SELECT RELEASE_LOCK(".$this->conn->quote($name).")"); } catch (\Throwable $th) {}
    }

    /* ============================
       CREAR / CANCELAR
       ============================ */

    /**
     * ✅ Crear una nueva venta:
     *  - Folio único (desde ventas, atómico con GET_LOCK + UNIQUE)
     *  - Inserta cabecera + detalles
     *  - Descuenta stock (valida vendible contra stock_minimo)
     *  - Inserta movimientos de inventario (tipo = 'Salida', cantidad positiva)
     *  - Registra bitácora
     *  - TIMESTAMPS SIEMPRE EN AMERICA/HERMOSILLO
     */
    public function crearVenta($datosVenta, $detalles)
    {
        $MAX_REINTENTOS = 6;

        try {
            // === Timestamps Hermosillo ===
            $fechaInput = $datosVenta['fecha'] ?? null;
            $fechaHora  = $this->fechaHoraDesdeInput($fechaInput); // Y-m-d H:i:s America/Hermosillo
            $ahoraHerm  = $this->ahoraHermStr();

            // === Cliente opcional ===
            $idClienteRaw  = $datosVenta['id_cliente'] ?? null;
            $isNullCliente = is_null($idClienteRaw) || $idClienteRaw === '' || (int)$idClienteRaw === 0;
            $idCliente     = $isNullCliente ? null : (int)$idClienteRaw;

            // === Obligatorios (desde sesión/controlador) ===
            $idUsuario  = (int)($datosVenta['id_usuario']  ?? 0);
            $idCaja     = (int)($datosVenta['id_caja']     ?? 0);
            $idSucursal = (int)($datosVenta['id_sucursal'] ?? 0);
            if (!$idUsuario || !$idCaja || !$idSucursal) {
                throw new \Exception('Faltan datos obligatorios (usuario/caja/sucursal).');
            }

            // === Estatus normalizado ===
            $estatusIn = strtolower(trim($datosVenta['estatus'] ?? 'Activa'));
            $estatusBD = ($estatusIn === 'guardada') ? 'Guardada' : 'Activa';

            // === Forma de pago ===
            // - Si es Guardada => siempre NULL (no contabiliza en corte)
            // - Si es Activa   => usa la que venga (si no viene, queda NULL; NO forzar 1)
            $idFormaPago = null;
            if ($estatusBD !== 'Guardada'
                && array_key_exists('id_forma_pago', $datosVenta)
                && $datosVenta['id_forma_pago'] !== null
                && $datosVenta['id_forma_pago'] !== '') {
                $idFormaPago = (int)$datosVenta['id_forma_pago'];
            }

            // === Tipo de precio (default 1) ===
            $idTipoPrecio = (int)($datosVenta['id_tipo_precio'] ?? 1);

            // === Total (recalculado) ===
            $totalCalc = 0.0;
            foreach ($detalles as $d) {
                $cant = (float)($d['cantidad']        ?? 0);
                $unit = (float)($d['precio_unitario'] ?? 0);
                $totalCalc += $cant * $unit;
            }

            $anio = (int)date('Y', strtotime($fechaHora));

            for ($intento = 1; $intento <= $MAX_REINTENTOS; $intento++) {

                $lockOk = $this->obtenerLockFolio($anio, 5);
                if (!$lockOk) {
                    if ($intento === $MAX_REINTENTOS) {
                        throw new \Exception('No se pudo obtener el candado de folio.');
                    }
                    usleep(random_int(20000, 90000));
                    continue;
                }

                try {
                    $this->conn->beginTransaction();

                    // Folio entrante o generado
                    $folio = trim($datosVenta['folio'] ?? '');
                    if ($folio === '') {
                        $folio = $this->generarFolioDesdeVentas($anio);
                    }

                    // === INSERT cabecera (estatus por parámetro; sin hardcode) ===
                    $sqlVenta = "INSERT INTO ventas
                        (folio, fecha, id_cliente, id_usuario, id_caja, id_forma_pago, id_tipo_precio, total, estatus, activo)
                        VALUES
                        (:folio, :fecha, :id_cliente, :id_usuario, :id_caja, :id_forma_pago, :id_tipo_precio, :total, :estatus, 1)";
                    $stVenta = $this->conn->prepare($sqlVenta);

                    $stVenta->bindValue(':folio', $folio);
                    $stVenta->bindValue(':fecha', $fechaHora);
                    // cliente
                    if ($idCliente === null) {
                        $stVenta->bindValue(':id_cliente', null, \PDO::PARAM_NULL);
                    } else {
                        $stVenta->bindValue(':id_cliente', $idCliente, \PDO::PARAM_INT);
                    }
                    // usuario / caja
                    $stVenta->bindValue(':id_usuario',     $idUsuario,    \PDO::PARAM_INT);
                    $stVenta->bindValue(':id_caja',        $idCaja,       \PDO::PARAM_INT);
                    // forma de pago (NULL si Guardada)
                    if ($idFormaPago === null) {
                        $stVenta->bindValue(':id_forma_pago', null, \PDO::PARAM_NULL);
                    } else {
                        $stVenta->bindValue(':id_forma_pago', $idFormaPago, \PDO::PARAM_INT);
                    }
                    $stVenta->bindValue(':id_tipo_precio', $idTipoPrecio, \PDO::PARAM_INT);
                    $stVenta->bindValue(':total',          $totalCalc);
                    $stVenta->bindValue(':estatus',        $estatusBD);
                    $stVenta->execute();

                    $idVenta = (int)$this->conn->lastInsertId();

                    // === Statements detalle / stock / movimiento ===
                    $stDet = $this->conn->prepare(
                        "INSERT INTO ventas_detalle
                         (id_venta, id_producto, cantidad, precio_unitario, subtotal, activo)
                         VALUES (:id_venta, :id_producto, :cantidad, :precio_unitario, :subtotal, 1)"
                    );

                    $stGetProd = $this->conn->prepare(
                        "SELECT stock_actual, stock_minimo
                         FROM productos
                         WHERE id_producto = :idp
                         FOR UPDATE"
                    );

                    $stUpdProd = $this->conn->prepare(
                        "UPDATE productos
                         SET stock_actual = stock_actual - :cant
                         WHERE id_producto = :idp"
                    );

                    $stMov = $this->conn->prepare(
                        "INSERT INTO inventario_movimientos
                         (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                         VALUES (:idp, 'Salida', :cant, :idsuc, :idusr, :ref, :mot, :fmov, 1)"
                    );

                    $itemsMov = [];
                    foreach ($detalles as $d) {
                        $idProd = (int)$d['id_producto'];
                        $cant   = (float)$d['cantidad'];
                        $unit   = (float)$d['precio_unitario'];
                        $sub    = $cant * $unit;

                        // valida vendible
                        $stGetProd->execute([':idp' => $idProd]);
                        $prod = $stGetProd->fetch(\PDO::FETCH_ASSOC);
                        if (!$prod) {
                            throw new \Exception("Producto $idProd no encontrado.");
                        }
                        $stockActual = (float)$prod['stock_actual'];
                        $stockMin    = (float)$prod['stock_minimo'];
                        $vendible    = max(0.0, $stockActual - $stockMin);
                        if ($cant > $vendible) {
                            throw new \Exception("Stock insuficiente en producto $idProd. Vendible: $vendible, solicitado: $cant.");
                        }

                        // detalle
                        $stDet->execute([
                            ':id_venta'        => $idVenta,
                            ':id_producto'     => $idProd,
                            ':cantidad'        => $cant,
                            ':precio_unitario' => $unit,
                            ':subtotal'        => $sub
                        ]);

                        // descuenta stock
                        $stUpdProd->execute([':cant' => $cant, ':idp' => $idProd]);

                        // movimiento inventario (siempre; Guardada también reserva stock)
                        $stMov->execute([
                            ':idp'   => $idProd,
                            ':cant'  => $cant,
                            ':idsuc' => $idSucursal,
                            ':idusr' => $idUsuario,
                            ':ref'   => $folio,
                            ':mot'   => ($estatusBD === 'Guardada' ? 'Venta guardada (reserva)' : 'Venta de mostrador'),
                            ':fmov'  => $ahoraHerm
                        ]);

                        $itemsMov[] = [
                            'id_producto' => $idProd,
                            'cant'        => $cant,
                            'precio_unit' => $unit,
                            'subtotal'    => $sub
                        ];
                    }

                    // bitácora
                    $this->registrarBitacora(
                        $idUsuario,
                        'ventas',
                        'INSERT',
                        $idVenta,
                        'Creación de venta',
                        null,
                        json_encode([
                            'folio'      => $folio,
                            'estatus'    => $estatusBD,
                            'id_cliente' => $idCliente,
                            'id_caja'    => $idCaja,
                            'total'      => $totalCalc,
                            'items'      => $itemsMov
                        ]),
                        null,
                        $ahoraHerm
                    );

                    $this->conn->commit();
                    $this->liberarLockFolio($anio);

                    return [
                        'ok'       => true,
                        'id_venta' => $idVenta,
                        'folio'    => $folio,
                        'total'    => $totalCalc,
                        'estatus'  => $estatusBD
                    ];

                } catch (\PDOException $e) {
                    if ($this->conn->inTransaction()) $this->conn->rollBack();
                    $this->liberarLockFolio($anio);

                    // 1062: folio duplicado
                    if (($e->errorInfo[1] ?? 0) === 1062) {
                        if ($intento === $MAX_REINTENTOS) {
                            return ['ok'=>false, 'msg'=>'No se pudo asignar folio único tras varios intentos.'];
                        }
                        usleep(random_int(20000, 90000));
                        continue;
                    }
                    return ['ok'=>false, 'msg'=>'Error BD: '.$e->getMessage()];
                } catch (\Throwable $th) {
                    if ($this->conn->inTransaction()) $this->conn->rollBack();
                    $this->liberarLockFolio($anio);
                    return ['ok'=>false, 'msg'=>$th->getMessage()];
                }
            }

            return ['ok'=>false, 'msg'=>'Falló la asignación de folio por concurrencia.'];

        } catch (\Exception $e) {
            return ['ok'=>false, 'msg'=>$e->getMessage()];
        }
    }

    // ✅ Cambiar estatus de una venta (ej. Cancelada, Devuelta)
    public function cambiarEstatus($idVenta, $nuevoEstatus)
    {
        $sql = "UPDATE ventas SET estatus = :estatus WHERE id_venta = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':estatus' => $nuevoEstatus,
            ':id' => $idVenta
        ]);
    }

    // ✅ Cancelar venta: reponer inventario y dejar rastro (timestamps Hermosillo)
    public function cancelarVenta($idVenta, $idSucursal, $idUsuario, $motivo = 'Cancelación de venta')
    {
        try {
            $this->conn->beginTransaction();

            $ahoraHerm = $this->ahoraHermStr();

            // Bloquea venta para lectura consistente
            $st = $this->conn->prepare("SELECT folio, estatus FROM ventas WHERE id_venta = :id FOR UPDATE");
            $st->execute([':id' => $idVenta]);
            $venta = $st->fetch(\PDO::FETCH_ASSOC);

            if (!$venta) {
                throw new \Exception('Venta no encontrada.');
            }
            if (strcasecmp($venta['estatus'], 'Cancelada') === 0) {
                // Ya cancelada: registrar en bitácora como idempotente y salir
                $this->registrarBitacora($idUsuario, 'ventas', 'CANCEL', $idVenta, 
                    'Intento de cancelar venta ya cancelada', 
                    json_encode(['estatus_prev' => $venta['estatus']]), 
                    json_encode(['estatus_new'  => $venta['estatus']]),
                    null,
                    $ahoraHerm);
                $this->conn->commit();
                return ['ok' => true, 'msg' => 'La venta ya estaba cancelada.'];
            }

            $folio = $venta['folio'];

            // Traer detalle
            $stDet = $this->conn->prepare(
                "SELECT id_producto, cantidad
                 FROM ventas_detalle
                 WHERE id_venta = :id AND (activo = 1 OR activo IS NULL)"
            );
            $stDet->execute([':id' => $idVenta]);
            $detalles = $stDet->fetchAll(\PDO::FETCH_ASSOC);

            // Preparar statements
            $stUpdProd = $this->conn->prepare(
                "UPDATE productos SET stock_actual = stock_actual + :cant WHERE id_producto = :idp"
            );
            $stMov = $this->conn->prepare(
                "INSERT INTO inventario_movimientos
                (id_producto, tipo, cantidad, id_sucursal, id_usuario, referencia, motivo, fecha, activo)
                VALUES (:idp, 'Devolucion Venta', :cant, :idsuc, :idusr, :ref, :mot, :fmov, 1)"
            );

            // Reponer inventario + movimiento por renglón
            $items = [];
            foreach ($detalles as $d) {
                $cant = (int)$d['cantidad'];
                $idp  = (int)$d['id_producto'];

                $stUpdProd->execute([':cant' => $cant, ':idp' => $idp]);
                $stMov->execute([
                    ':idp'   => $idp,
                    ':cant'  => $cant,
                    ':idsuc' => $idSucursal,
                    ':idusr' => $idUsuario,
                    ':ref'   => $folio,
                    ':mot'   => $motivo,
                    ':fmov'  => $ahoraHerm
                ]);

                $items[] = ['id_producto' => $idp, 'cantidad' => $cant];
            }

            // Marcar venta cancelada   
            $stVenta = $this->conn->prepare(
                "UPDATE ventas SET estatus = 'Cancelada' WHERE id_venta = :id"
            );
            $stVenta->execute([':id' => $idVenta]);

            // BITÁCORA (Herm)
            $this->registrarBitacora(
                $idUsuario,
                'ventas',
                'CANCEL',
                $idVenta,
                'Cancelación de venta y devolución a inventario',
                null,
                json_encode(['estatus_new'  => 'Cancelada', 'devoluciones' => $items]),
                null,
                $ahoraHerm
            );

            $this->conn->commit();
            return ['ok' => true, 'msg' => 'Venta cancelada, stock repuesto y bitácora registrada.'];

        } catch (\Exception $e) {
            $this->conn->rollBack();
            try {
                $this->registrarBitacora($idUsuario, 'ventas', 'ERROR', (int)$idVenta, $e->getMessage(), null, null, null, $this->ahoraHermStr());
            } catch (\Throwable $th) {}
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /* ============================
       BITÁCORA
       ============================ */
    private function registrarBitacora(
        $idUsuario,
        string $tabla,
        string $accion,
        int $registroId,
        string $descripcion = '',
        ?string $valorAnterior = null,
        ?string $valorNuevo = null,
        ?string $campoModificado = null,
        ?string $fechaRegistro = null          // <-- NUEVO: timestamp Hermosillo
    ) {
        $fechaRegistro = $fechaRegistro ?: $this->ahoraHermStr();

        // IP origen
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]); // primera ip si viene lista
        }

        $sql = "INSERT INTO bitacora_movimientos
                (id_usuario, tabla, accion, registro_id, campo_modificado, valor_anterior, valor_nuevo,
                 descripcion, ip_origen, activo, fecha)
                VALUES
                (:usr, :tbl, :acc, :rid, :campo, :val_ant, :val_nvo, :desc, :ip, 1, :freg)";

        $st = $this->conn->prepare($sql);
        $st->execute([
            ':usr'     => $idUsuario,
            ':tbl'     => $tabla,
            ':acc'     => $accion,        // INSERT|UPDATE|DELETE|LOGIN|LOGOUT|CANCEL|PRINT|ERROR
            ':rid'     => $registroId,
            ':campo'   => $campoModificado,
            ':val_ant' => $valorAnterior,
            ':val_nvo' => $valorNuevo,
            ':desc'    => $descripcion,
            ':ip'      => $ip,
            ':freg'    => $fechaRegistro   // <-- Hermosillo
        ]);
    }
}
