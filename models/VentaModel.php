<?php
// models/VentaModel.php
include_once '../includes/db.php';

class VentaModel
{
    private $conn;

    public function __construct()
    {
        global $pdo;
        $this->conn = $pdo;
        try { $this->conn->exec("SET time_zone = '-07:00'"); } catch (\Throwable $th) {}
    }

    /* ========================= Zona horaria ========================= */
    private function tzHerm(): \DateTimeZone { return new \DateTimeZone('America/Hermosillo'); }
    private function ahoraHermStr(): string { return (new \DateTime('now', $this->tzHerm()))->format('Y-m-d H:i:s'); }
    private function fechaHoraDesdeInput(?string $fechaYmd): string
    {
        $now = new \DateTime('now', $this->tzHerm());
        return ($fechaYmd ?: $now->format('Y-m-d')) . ' ' . $now->format('H:i:s');
    }

    /* ========================= Helpers Crédito ========================= */
    private function formaPagoEsCredito(?int $idFormaPago): bool
    {
        if (!$idFormaPago) return false;
        try {
            $st = $this->conn->prepare(
                "SELECT
                   CASE
                     WHEN es_credito IS NOT NULL THEN es_credito
                     ELSE CASE
                       WHEN LOWER(descripcion) LIKE '%credito%' OR LOWER(descripcion) LIKE '%crédito%' OR COALESCE(clave_sat,'')='99'
                       THEN 1 ELSE 0 END
                   END AS es_cred
                 FROM formas_pago
                 WHERE id_forma_pago=:id"
            );
            $st->execute([':id'=>$idFormaPago]);
            return (int)$st->fetchColumn() === 1;
        } catch (\Throwable $th) {
            return false;
        }
    }

    /** Busca el id de la forma de pago "Crédito" (si existe). */
    private function buscarIdFormaPagoCredito(): ?int
    {
        try {
            $st = $this->conn->query(
                "SELECT id_forma_pago
                   FROM formas_pago
                  WHERE (es_credito = 1)
                     OR (LOWER(descripcion) LIKE '%credito%' OR LOWER(descripcion) LIKE '%crédito%')
                     OR (COALESCE(clave_sat,'')='99')
                  ORDER BY es_credito DESC
                  LIMIT 1"
            );
            $id = $st->fetchColumn();
            return $id ? (int)$id : null;
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Normaliza SOLO el nombre del estatus, sin meter validaciones.
     * Regla:
     * - Si llega estatus "credito" o la forma de pago es crédito => 'Credito'
     * - Si llega "guardada/guardado" => 'Guardada'
     * - En otro caso => 'Activa'
     * Devuelve el estatus y el id_forma_pago tal cual llegó.
     */
    private function normalizarEstatusYFormaPago(string $estatusIn, ?int $idFormaPago, ?int $idCliente): array
    {
        $e = strtolower(trim($estatusIn ?: 'activa'));
        $map = [
            'activa'   => 'Activa',
            'activo'   => 'Activa',
            'guardada' => 'Guardada',
            'guardado' => 'Guardada',
            'cancelada'=> 'Cancelada',
            'devuelta' => 'Devuelta',
            'credito'  => 'Credito',
        ];
        $estatusBD = $map[$e] ?? 'Activa';

        // Forzar 'Credito' si la forma de pago es crédito o si el estatus llegó como "credito".
        if ($this->formaPagoEsCredito($idFormaPago) || $estatusBD === 'Credito') {
            return ['estatus' => 'Credito', 'id_fp' => $idFormaPago];
        }

        // Mantener 'Guardada' si así llegó
        if ($estatusBD === 'Guardada') {
            return ['estatus' => 'Guardada', 'id_fp' => $idFormaPago];
        }

        // Cualquier otro caso: 'Activa'
        return ['estatus' => 'Activa', 'id_fp' => $idFormaPago];
    }

    public function saldoVenta(int $idVenta): float
    {
        $st = $this->conn->prepare(
            "SELECT v.total - COALESCE(SUM(a.monto),0) AS saldo
             FROM ventas v
             LEFT JOIN ventas_abonos a
               ON a.id_venta = v.id_venta AND a.activo=1
             WHERE v.id_venta = :id
             GROUP BY v.id_venta, v.total"
        );
        $st->execute([':id'=>$idVenta]);
        return (float)($st->fetchColumn() ?? 0);
    }

    public function obtenerAbonosVenta(int $idVenta): array
    {
        $st = $this->conn->prepare(
            "SELECT a.*,
                    fp.descripcion AS forma_pago_desc,
                    u.nombre      AS usuario_nombre
             FROM ventas_abonos a
             LEFT JOIN formas_pago fp ON fp.id_forma_pago = a.id_forma_pago
             LEFT JOIN usuarios    u  ON u.id_usuario     = a.id_usuario
             WHERE a.id_venta = :id AND a.activo = 1
             ORDER BY a.fecha_abono ASC, a.id_abono ASC"
        );
        $st->execute([':id'=>$idVenta]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ========================= Listado / consulta ========================= */
    public function obtenerVentas($pagina = 1, $limite = 10, $folio = '', $fecha = '')
    {
        $offset = ($pagina - 1) * $limite;
        $sql = "SELECT v.*,
                       c.nombre AS cliente,
                       u.nombre AS usuario,
                       cj.nombre AS caja,
                       COALESCE(fp.descripcion,'—') AS forma_pago,
                       tp.nombre AS tipo_precio,
                       (SELECT COALESCE(SUM(a.monto),0)
                          FROM ventas_abonos a
                         WHERE a.id_venta = v.id_venta AND a.activo=1) AS abonado,
                       (v.total -
                        (SELECT COALESCE(SUM(a2.monto),0)
                           FROM ventas_abonos a2
                          WHERE a2.id_venta = v.id_venta AND a2.activo=1))      AS saldo
                FROM ventas v
                LEFT JOIN clientes     c  ON v.id_cliente     = c.id_cliente
                INNER JOIN usuarios    u  ON v.id_usuario     = u.id_usuario
                INNER JOIN cajas       cj ON v.id_caja        = cj.id_caja
                LEFT JOIN formas_pago  fp ON v.id_forma_pago  = fp.id_forma_pago
                INNER JOIN tipo_precio tp ON v.id_tipo_precio = tp.id_tipo_precio
                WHERE v.activo = 1";
        $params = [];
        if ($folio !== '') { $sql .= " AND v.folio LIKE :folio"; $params[':folio'] = "%$folio%"; }
        if ($fecha !== '') { $sql .= " AND DATE(v.fecha) = :fecha"; $params[':fecha'] = $fecha; }
        $sql .= " ORDER BY v.id_venta DESC LIMIT :limite OFFSET :offset";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':limite',(int)$limite,\PDO::PARAM_INT);
        $st->bindValue(':offset',(int)$offset,\PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function contarVentas($folio = '', $fecha = '')
    {
        $sql = "SELECT COUNT(*) FROM ventas v WHERE v.activo = 1";
        $params = [];
        if ($folio !== '') { $sql .= " AND v.folio LIKE :folio"; $params[':folio'] = "%$folio%"; }
        if ($fecha !== '') { $sql .= " AND DATE(v.fecha) = :fecha"; $params[':fecha'] = $fecha; }
        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        return (int)$st->fetchColumn();
    }

    public function obtenerVentaPorId($idVenta)
    {
        $st = $this->conn->prepare(
            "SELECT v.*,
                    c.nombre AS cliente,
                    u.nombre AS usuario,
                    cj.nombre AS caja,
                    COALESCE(fp.descripcion,'—') AS forma_pago,
                    tp.nombre AS tipo_precio,
                    (SELECT COALESCE(SUM(a.monto),0)
                       FROM ventas_abonos a
                      WHERE a.id_venta = v.id_venta AND a.activo=1) AS abonado,
                    (v.total -
                     (SELECT COALESCE(SUM(a2.monto),0)
                        FROM ventas_abonos a2
                       WHERE a2.id_venta = v.id_venta AND a2.activo=1)) AS saldo
             FROM ventas v
             LEFT JOIN clientes     c  ON v.id_cliente     = c.id_cliente
             INNER JOIN usuarios    u  ON v.id_usuario     = u.id_usuario
             INNER JOIN cajas       cj ON v.id_caja        = cj.id_caja
             LEFT JOIN formas_pago  fp ON v.id_forma_pago  = fp.id_forma_pago
             INNER JOIN tipo_precio tp ON v.id_tipo_precio = tp.id_tipo_precio
             WHERE v.id_venta = :id
             LIMIT 1"
        );
        $st->bindValue(':id',$idVenta,\PDO::PARAM_INT);
        $st->execute();
        $venta = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$venta) return null;

        $venta['abonos'] = $this->obtenerAbonosVenta((int)$idVenta);
        return $venta;
    }

    public function obtenerDetalleVenta($idVenta)
    {
        $st = $this->conn->prepare(
            "SELECT vd.*, p.descripcion AS producto, p.codigo AS codigo
             FROM ventas_detalle vd
             INNER JOIN productos p ON p.id_producto = vd.id_producto
             WHERE vd.id_venta = :id
               AND (vd.activo = 1 OR vd.activo IS NULL)"
        );
        $st->bindValue(':id',$idVenta,\PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ========================= Folios ========================= */
    public function sugerirFolioPorFecha(?string $fecha = null): array
    {
        $fecha = $fecha ?: (new \DateTime('now', $this->tzHerm()))->format('Y-m-d');
        $anio  = (int)date('Y', strtotime($fecha));
        $folio = $this->generarFolioDesdeVentas($anio);
        return ['ok'=>true,'folio'=>$folio,'anio'=>$anio];
    }
    private function generarFolioDesdeVentas(int $anio): string
    {
        $yy = $anio % 100;
        $pref = sprintf('%02d-', $yy);
        $st = $this->conn->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(folio,4) AS UNSIGNED)),0)
             FROM ventas WHERE folio LIKE :like"
        );
        $st->execute([':like'=>$pref.'%']);
        $next = ((int)$st->fetchColumn()) + 1;
        return sprintf('%s%05d', $pref, $next);
    }
    private function obtenerLockFolio(int $anio, int $timeout=5): bool
    {
        $name = 'folio_ventas_'.$anio;
        $q = $this->conn->query("SELECT GET_LOCK(".$this->conn->quote($name).", {$timeout})");
        return (int)$q->fetchColumn() === 1;
    }
    private function liberarLockFolio(int $anio): void
    {
        try {
            $name = 'folio_ventas_'.$anio;
            $this->conn->query("SELECT RELEASE_LOCK(".$this->conn->quote($name).")");
        } catch (\Throwable $th) {}
    }

    /* ========================= Crear venta ========================= */
    public function crearVenta(array $datosVenta, array $detalles)
    {
        $MAX_REINT = 6;

        try {
            $fechaHora  = $this->fechaHoraDesdeInput($datosVenta['fecha'] ?? null);
            $ahora      = $this->ahoraHermStr();

            $idClienteRaw = $datosVenta['id_cliente'] ?? null;
            $idCliente = (is_null($idClienteRaw) || $idClienteRaw==='' || (int)$idClienteRaw===0) ? null : (int)$idClienteRaw;

            $idUsuario  = (int)($datosVenta['id_usuario']  ?? 0);
            $idCaja     = (int)($datosVenta['id_caja']     ?? 0);
            $idSucursal = (int)($datosVenta['id_sucursal'] ?? 0);
            if (!$idUsuario || !$idCaja || !$idSucursal) throw new \Exception('Faltan usuario/caja/sucursal.');

            $idFormaPago = null;
            if (array_key_exists('id_forma_pago',$datosVenta) && $datosVenta['id_forma_pago']!=='') {
                $idFormaPago = (int)$datosVenta['id_forma_pago'];
            }

            $idTipoPrecio = (int)($datosVenta['id_tipo_precio'] ?? 1);

            $total = 0.0;
            foreach ($detalles as $d) { $total += (float)($d['cantidad'] ?? 0) * (float)($d['precio_unitario'] ?? 0); }

            // Normaliza SOLO el estatus (reglas arriba), no impone validaciones.
            $norm = $this->normalizarEstatusYFormaPago((string)($datosVenta['estatus'] ?? 'Activa'), $idFormaPago, $idCliente);
            $estatusBD   = $norm['estatus'];
            $idFormaPago = $norm['id_fp'];

            $anio = (int)date('Y', strtotime($fechaHora));

            for ($i=1; $i<=$MAX_REINT; $i++) {
                $lockOk = $this->obtenerLockFolio($anio, 5);
                if (!$lockOk) {
                    if ($i===$MAX_REINT) throw new \Exception('No se pudo obtener candado de folio.');
                    usleep(random_int(20000,90000));
                    continue;
                }

                try {
                    $this->conn->beginTransaction();

                    $folio = trim($datosVenta['folio'] ?? '');
                    if ($folio === '') $folio = $this->generarFolioDesdeVentas($anio);

                    $stVenta = $this->conn->prepare(
                        "INSERT INTO ventas
                         (folio, fecha, id_cliente, id_usuario, id_caja, id_forma_pago, id_tipo_precio, total, estatus, activo)
                         VALUES (:folio,:fecha,:idc,:idu,:idcj,:idfp,:idtp,:total,:estatus,1)"
                    );
                    $stVenta->bindValue(':folio',$folio);
                    $stVenta->bindValue(':fecha',$fechaHora);
                    if ($idCliente===null) $stVenta->bindValue(':idc',null,\PDO::PARAM_NULL); else $stVenta->bindValue(':idc',$idCliente,\PDO::PARAM_INT);
                    $stVenta->bindValue(':idu',$idUsuario,\PDO::PARAM_INT);
                    $stVenta->bindValue(':idcj',$idCaja,\PDO::PARAM_INT);
                    // 👇 Se guarda la forma de pago tal cual viene (sin forzar null en Guardada)
                    if ($idFormaPago===null) $stVenta->bindValue(':idfp',null,\PDO::PARAM_NULL);
                    else $stVenta->bindValue(':idfp',$idFormaPago,\PDO::PARAM_INT);
                    $stVenta->bindValue(':idtp',$idTipoPrecio,\PDO::PARAM_INT);
                    $stVenta->bindValue(':total',$total);
                    $stVenta->bindValue(':estatus',$estatusBD);
                    $stVenta->execute();

                    $idVenta = (int)$this->conn->lastInsertId();

                    /* detalle + inventario */
                    $stDet = $this->conn->prepare(
                        "INSERT INTO ventas_detalle (id_venta,id_producto,cantidad,precio_unitario,subtotal,activo)
                         VALUES (:idv,:idp,:cant,:unit,:sub,1)"
                    );
                    $stGet = $this->conn->prepare(
                        "SELECT stock_actual, stock_minimo FROM productos WHERE id_producto=:idp FOR UPDATE"
                    );
                    $stUpd = $this->conn->prepare(
                        "UPDATE productos SET stock_actual = stock_actual - :cant WHERE id_producto=:idp"
                    );
                    $stMov = $this->conn->prepare(
                        "INSERT INTO inventario_movimientos
                         (id_producto,tipo,cantidad,id_sucursal,id_usuario,referencia,motivo,fecha,activo)
                         VALUES (:idp,'Salida',:cant,:ids,:idu,:ref,:mot,:f,1)"
                    );

                    $itemsBit = [];
                    foreach ($detalles as $d) {
                        $idp  = (int)$d['id_producto'];
                        $cant = (float)$d['cantidad'];
                        $unit = (float)$d['precio_unitario'];
                        $sub  = $cant * $unit;

                        $stGet->execute([':idp'=>$idp]);
                        $p = $stGet->fetch(\PDO::FETCH_ASSOC);
                        if (!$p) throw new \Exception("Producto $idp no encontrado.");
                        $vendible = max(0.0, (float)$p['stock_actual'] - (float)$p['stock_minimo']);
                        if ($cant > $vendible) throw new \Exception("Stock insuficiente en producto $idp. Vendible: $vendible, solicitado: $cant.");

                        $stDet->execute([':idv'=>$idVenta,':idp'=>$idp,':cant'=>$cant,':unit'=>$unit,':sub'=>$sub]);
                        $stUpd->execute([':cant'=>$cant,':idp'=>$idp]);
                        $stMov->execute([
                            ':idp'=>$idp, ':cant'=>$cant, ':ids'=>$idSucursal, ':idu'=>$idUsuario,
                            ':ref'=>$folio, ':mot'=>($estatusBD==='Guardada'?'Venta guardada (reserva)':'Venta de mostrador / crédito'),
                            ':f'=>$ahora
                        ]);

                        $itemsBit[] = ['id_producto'=>$idp,'cant'=>$cant,'precio_unit'=>$unit,'subtotal'=>$sub];
                    }

                    $this->registrarBitacora(
                        $idUsuario,'ventas','INSERT',$idVenta,'Creación de venta',
                        null, json_encode([
                            'folio'=>$folio,'estatus'=>$estatusBD,'id_cliente'=>$idCliente,
                            'id_caja'=>$idCaja,'total'=>$total,'items'=>$itemsBit
                        ]), null, $ahora
                    );

                    $this->conn->commit();
                    $this->liberarLockFolio($anio);
                    return ['ok'=>true,'id_venta'=>$idVenta,'folio'=>$folio,'total'=>$total,'estatus'=>$estatusBD];

                } catch (\PDOException $e) {
                    if ($this->conn->inTransaction()) $this->conn->rollBack();
                    $this->liberarLockFolio($anio);
                    if (($e->errorInfo[1] ?? 0) === 1062) {
                        if ($i===$MAX_REINT) return ['ok'=>false,'msg'=>'No se pudo asignar folio único.'];
                        usleep(random_int(20000,90000)); continue;
                    }
                    return ['ok'=>false,'msg'=>'Error BD: '.$e->getMessage()];
                } catch (\Throwable $th) {
                    if ($this->conn->inTransaction()) $this->conn->rollBack();
                    $this->liberarLockFolio($anio);
                    return ['ok'=>false,'msg'=>$th->getMessage()];
                }
            }

            return ['ok'=>false,'msg'=>'Falló la asignación de folio por concurrencia.'];

        } catch (\Exception $e) {
            return ['ok'=>false,'msg'=>$e->getMessage()];
        }
    }

    /* ========================= Editar venta ========================= */
    public function actualizarVenta(array $datosVenta, ?array $detalles = null)
    {
        try {
            $this->conn->beginTransaction();

            $ahora = $this->ahoraHermStr();

            $idVenta = (int)($datosVenta['id_venta'] ?? 0);
            if (!$idVenta) throw new \Exception('id_venta requerido.');

            $stV = $this->conn->prepare("SELECT * FROM ventas WHERE id_venta = :id FOR UPDATE");
            $stV->execute([':id'=>$idVenta]);
            $ventaActual = $stV->fetch(\PDO::FETCH_ASSOC);
            if (!$ventaActual) throw new \Exception('Venta no encontrada.');

            $folioVenta = $ventaActual['folio'];
            $idUsuario  = (int)$ventaActual['id_usuario'];
            $idCaja     = (int)$ventaActual['id_caja'];
            $idSucursal = (int)($_SESSION['usuario']['id_sucursal'] ?? $_SESSION['id_sucursal'] ?? 1);

            $fechaBD = $this->fechaHoraDesdeInput($datosVenta['fecha'] ?? $ventaActual['fecha']);

            $idClienteRaw = $datosVenta['id_cliente'] ?? $ventaActual['id_cliente'];
            $idCliente = (is_null($idClienteRaw) || $idClienteRaw==='' || (int)$idClienteRaw===0) ? null : (int)$idClienteRaw;

            $idFormaPago = null;
            if (array_key_exists('id_forma_pago',$datosVenta) && $datosVenta['id_forma_pago']!=='') {
                $idFormaPago = (int)$datosVenta['id_forma_pago'];
            } else {
                $idFormaPago = $ventaActual['id_forma_pago'] ? (int)$ventaActual['id_forma_pago'] : null;
            }
            $idTipoPrecio = (int)($datosVenta['id_tipo_precio'] ?? $ventaActual['id_tipo_precio'] ?? 1);

            // Normaliza SOLO el estatus (sin forzar id_fp ni validar cliente)
            $norm = $this->normalizarEstatusYFormaPago((string)($datosVenta['estatus'] ?? $ventaActual['estatus'] ?? 'Activa'), $idFormaPago, $idCliente);
            $estatusBD   = $norm['estatus'];
            $idFormaPago = $norm['id_fp'];

            /* ===== SOLO CABECERA ===== */
            if ($detalles === null) {
                $stUp = $this->conn->prepare(
                    "UPDATE ventas
                     SET fecha = :fecha,
                         id_cliente = :idc,
                         id_forma_pago = :idfp,
                         id_tipo_precio = :idtp,
                         estatus = :estatus
                     WHERE id_venta = :id"
                );
                $stUp->bindValue(':fecha',$fechaBD);
                if ($idCliente===null) $stUp->bindValue(':idc',null,\PDO::PARAM_NULL); else $stUp->bindValue(':idc',$idCliente,\PDO::PARAM_INT);
                // 👇 Se guarda la forma de pago tal cual venga (sin limpiar por estatus)
                if ($idFormaPago===null) $stUp->bindValue(':idfp',null,\PDO::PARAM_NULL);
                else $stUp->bindValue(':idfp',$idFormaPago,\PDO::PARAM_INT);
                $stUp->bindValue(':idtp',$idTipoPrecio,\PDO::PARAM_INT);
                $stUp->bindValue(':estatus',$estatusBD);
                $stUp->bindValue(':id',$idVenta,\PDO::PARAM_INT);
                $stUp->execute();

                $this->registrarBitacora(
                    $idUsuario, 'ventas', 'UPDATE', $idVenta, 'Edición de cabecera (sin cambios en productos)',
                    json_encode([
                        'estatus_prev'=>$ventaActual['estatus'],
                        'id_cliente_prev'=>$ventaActual['id_cliente'],
                        'id_forma_pago_prev'=>$ventaActual['id_forma_pago'],
                        'id_tipo_precio_prev'=>$ventaActual['id_tipo_precio'],
                        'fecha_prev'=>$ventaActual['fecha']
                    ]),
                    json_encode([
                        'estatus'=>$estatusBD,
                        'id_cliente'=>$idCliente,
                        'id_forma_pago'=>$idFormaPago,
                        'id_tipo_precio'=>$idTipoPrecio,
                        'fecha'=>$fechaBD
                    ]),
                    null, $ahora
                );

                $this->conn->commit();
                return ['ok'=>true,'msg'=>'Cabecera actualizada (sin tocar inventario ni detalle).'];
            }

            /* ===== CON DETALLE ===== */
            $stDetAct = $this->conn->prepare(
                "SELECT id_producto, cantidad, precio_unitario
                 FROM ventas_detalle
                 WHERE id_venta = :id AND (activo=1 OR activo IS NULL)"
            );
            $stDetAct->execute([':id'=>$idVenta]);
            $detAct = $stDetAct->fetchAll(\PDO::FETCH_ASSOC);

            $act = [];
            foreach ($detAct as $r) {
                $pid = (int)$r['id_producto'];
                if (!isset($act[$pid])) $act[$pid] = ['cantidad'=>0.0, 'precio_unitario'=>(float)$r['precio_unitario']];
                $act[$pid]['cantidad'] += (float)$r['cantidad'];
                $act[$pid]['precio_unitario'] = (float)$r['precio_unitario'];
            }

            $nuevo = [];
            foreach ($detalles as $d) {
                $pid = (int)$d['id_producto'];
                $cant = (float)($d['cantidad'] ?? 0);
                $unit = (float)($d['precio_unitario'] ?? 0);
                if ($cant <= 0) continue;
                if (!isset($nuevo[$pid])) $nuevo[$pid] = ['cantidad'=>0.0, 'precio_unitario'=>$unit];
                $nuevo[$pid]['cantidad'] += $cant;
                $nuevo[$pid]['precio_unitario'] = $unit;
            }

            $pids = array_unique(array_merge(array_keys($act), array_keys($nuevo)));

            $stGetProd = $this->conn->prepare("SELECT stock_actual, stock_minimo FROM productos WHERE id_producto=:idp FOR UPDATE");
            $stDec = $this->conn->prepare("UPDATE productos SET stock_actual = stock_actual - :cant WHERE id_producto=:idp");
            $stInc = $this->conn->prepare("UPDATE productos SET stock_actual = stock_actual + :cant WHERE id_producto=:idp");

            $stMovSalida = $this->conn->prepare(
                "INSERT INTO inventario_movimientos
                 (id_producto,tipo,cantidad,id_sucursal,id_usuario,referencia,motivo,fecha,activo)
                 VALUES (:idp,'Salida',:cant,:ids,:idu,:ref,:mot,:f,1)"
            );
            $stMovEntrada = $this->conn->prepare(
                "INSERT INTO inventario_movimientos
                 (id_producto,tipo,cantidad,id_sucursal,id_usuario,referencia,motivo,fecha,activo)
                 VALUES (:idp,'Devolucion Venta',:cant,:ids,:idu,:ref,:mot,:f,1)"
            );

            $deltasBit = [];
            foreach ($pids as $pid) {
                $oldQ = (float)($act[$pid]['cantidad'] ?? 0.0);
                $newQ = (float)($nuevo[$pid]['cantidad'] ?? 0.0);
                $delta = round($newQ - $oldQ, 4);

                if (abs($delta) < 0.0001) continue;

                $stGetProd->execute([':idp'=>$pid]);
                $prod = $stGetProd->fetch(\PDO::FETCH_ASSOC);
                if (!$prod) throw new \Exception("Producto $pid no encontrado.");

                $stockActual = (float)$prod['stock_actual'];
                $stockMin    = (float)$prod['stock_minimo'];
                $vendible    = max(0.0, $stockActual - $stockMin);

                if ($delta > 0) {
                    if ($delta > $vendible) throw new \Exception("Stock insuficiente en producto $pid. Vendible: $vendible, requerido: $delta.");
                    $stDec->execute([':cant'=>$delta, ':idp'=>$pid]);
                    $stMovSalida->execute([
                        ':idp'=>$pid, ':cant'=>$delta, ':ids'=>$idSucursal, ':idu'=>$idUsuario,
                        ':ref'=>$folioVenta, ':mot'=>'Edición de venta (incremento)', ':f'=>$ahora
                    ]);
                } else {
                    $dev = abs($delta);
                    $stInc->execute([':cant'=>$dev, ':idp'=>$pid]);
                    $stMovEntrada->execute([
                        ':idp'=>$pid, ':cant'=>$dev, ':ids'=>$idSucursal, ':idu'=>$idUsuario,
                        ':ref'=>$folioVenta, ':mot'=>'Edición de venta (decremento)', ':f'=>$ahora
                    ]);
                }
                $deltasBit[] = ['id_producto'=>$pid,'delta'=>$delta];
            }

            $this->conn->prepare("UPDATE ventas_detalle SET activo = 0 WHERE id_venta = :id")->execute([':id'=>$idVenta]);
            $stInsDet = $this->conn->prepare(
                "INSERT INTO ventas_detalle (id_venta,id_producto,cantidad,precio_unitario,subtotal,activo)
                 VALUES (:idv,:idp,:cant,:unit,:sub,1)"
            );

            $totalNuevo = 0.0;
            foreach ($nuevo as $pid=>$row) {
                $cant = (float)$row['cantidad'];
                $unit = (float)$row['precio_unitario'];
                $sub  = $cant * $unit;
                $totalNuevo += $sub;
                $stInsDet->execute([':idv'=>$idVenta,':idp'=>$pid,':cant'=>$cant,':unit'=>$unit,':sub'=>$sub]);
            }

            $stUpV = $this->conn->prepare(
                "UPDATE ventas
                 SET fecha = :fecha,
                     id_cliente = :idc,
                     id_forma_pago = :idfp,
                     id_tipo_precio = :idtp,
                     total = :total,
                     estatus = :estatus
                 WHERE id_venta = :id"
            );
            $stUpV->bindValue(':fecha',$fechaBD);
            if ($idCliente===null) $stUpV->bindValue(':idc',null,\PDO::PARAM_NULL); else $stUpV->bindValue(':idc',$idCliente,\PDO::PARAM_INT);
            // 👇 Se guarda la forma de pago tal cual venga
            if ($idFormaPago===null) $stUpV->bindValue(':idfp',null,\PDO::PARAM_NULL);
            else $stUpV->bindValue(':idfp',$idFormaPago,\PDO::PARAM_INT);
            $stUpV->bindValue(':idtp',$idTipoPrecio,\PDO::PARAM_INT);
            $stUpV->bindValue(':total',$totalNuevo);
            $stUpV->bindValue(':estatus',$estatusBD);
            $stUpV->bindValue(':id',$idVenta,\PDO::PARAM_INT);
            $stUpV->execute();

            $this->registrarBitacora(
                $idUsuario,'ventas','UPDATE',$idVenta,'Edición de venta',
                json_encode(['antes'=>$act,'estatus'=>$ventaActual['estatus'],'total'=>$ventaActual['total']]),
                json_encode(['despues'=>$nuevo,'estatus'=>$estatusBD,'total'=>$totalNuevo,'deltas'=>$deltasBit,'fecha'=>$fechaBD,'id_cliente'=>$idCliente,'id_forma_pago'=>$idFormaPago,'id_tipo_precio'=>$idTipoPrecio]),
                null,$ahora
            );

            $this->conn->commit();
            return ['ok'=>true,'msg'=>'Venta actualizada','total'=>$totalNuevo,'estatus'=>$estatusBD];

        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try {
                $idU = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['id_usuario'] ?? 0);
                $this->registrarBitacora($idU,'ventas','ERROR',(int)($datosVenta['id_venta'] ?? 0),$e->getMessage(),null,null,null,$this->ahoraHermStr());
            } catch (\Throwable $th) {}
            return ['ok'=>false,'msg'=>$e->getMessage()];
        }
    }

    /* ========================= Abonos a venta ========================= */
    public function abonarVenta(int $idVenta, float $monto, int $idFormaPago, ?string $fechaAbono, ?string $ref, ?int $idUsuario): array
    {
        if ($monto <= 0) return ['ok'=>false,'msg'=>'Monto inválido'];

        try {
            $this->conn->beginTransaction();

            // Trae venta + valida que sea crédito y no esté cancelada
            $st = $this->conn->prepare(
                "SELECT v.*, fp.es_credito
                   FROM ventas v
              LEFT JOIN formas_pago fp ON fp.id_forma_pago = v.id_forma_pago
                  WHERE v.id_venta = :id
                  FOR UPDATE"
            );
            $st->execute([':id'=>$idVenta]);
            $v = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$v) { $this->conn->rollBack(); return ['ok'=>false,'msg'=>'Venta no encontrada']; }

            $esCreditoHeader = isset($v['es_credito'])
                ? (int)$v['es_credito'] === 1
                : $this->formaPagoEsCredito((int)($v['id_forma_pago'] ?? 0));

            if (!$esCreditoHeader && strcasecmp($v['estatus'],'Credito')!==0) {
                $this->conn->rollBack(); return ['ok'=>false,'msg'=>'La venta no es de crédito'];
            }
            if (strcasecmp($v['estatus'],'Cancelada')===0) {
                $this->conn->rollBack(); return ['ok'=>false,'msg'=>'Venta cancelada'];
            }

            $saldo = $this->saldoVenta($idVenta);
            if ($saldo <= 0) { $this->conn->rollBack(); return ['ok'=>false,'msg'=>'Venta sin saldo']; }
            if ($monto > $saldo + 0.0001) { $this->conn->rollBack(); return ['ok'=>false,'msg'=>'El abono excede el saldo']; }

            // Insert abono
            $fecha = $fechaAbono ? ($fechaAbono.' '.date('H:i:s')) : $this->ahoraHermStr();
            $ins = $this->conn->prepare(
                "INSERT INTO ventas_abonos
                 (id_venta,id_forma_pago,monto,fecha_abono,referencia_pago,id_usuario,activo,fecha_creacion)
                 VALUES (:v,:fp,:m,:f,:r,:u,1,NOW())"
            );
            $ins->execute([
                ':v'=>$idVenta, ':fp'=>$idFormaPago, ':m'=>$monto, ':f'=>$fecha,
                ':r'=>$ref, ':u'=>$idUsuario
            ]);

            $saldo2 = $this->saldoVenta($idVenta);
            if ($saldo2 <= 0.0001) {
                $this->conn->prepare("UPDATE ventas SET estatus='Cobrada' WHERE id_venta=:id")
                           ->execute([':id'=>$idVenta]);
            }

            // Bitácora
            $this->registrarBitacora(
                $idUsuario, 'ventas_abonos', 'INSERT', (int)$this->conn->lastInsertId(),
                'Abono a venta de crédito', null,
                json_encode(['id_venta'=>$idVenta,'monto'=>$monto,'saldo_antes'=>$saldo,'saldo_despues'=>$saldo2])
            );

            $this->conn->commit();
            return ['ok'=>true,'saldo'=>$saldo2];

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ['ok'=>false,'msg'=>$e->getMessage()];
        }
    }

    /* ========================= Cambiar estatus / cancelar ========================= */
    public function cambiarEstatus($idVenta, $nuevoEstatus)
    {
        $st = $this->conn->prepare("UPDATE ventas SET estatus = :e WHERE id_venta = :id");
        return $st->execute([':e'=>$nuevoEstatus, ':id'=>$idVenta]);
    }

    public function cancelarVenta($idVenta, $idSucursal, $idUsuario, $motivo = 'Cancelación de venta')
    {
        try {
            $this->conn->beginTransaction();

            $ahora = $this->ahoraHermStr();

            $stV = $this->conn->prepare("SELECT folio, estatus FROM ventas WHERE id_venta = :id FOR UPDATE");
            $stV->execute([':id'=>$idVenta]);
            $venta = $stV->fetch(\PDO::FETCH_ASSOC);
            if (!$venta) throw new \Exception('Venta no encontrada.');

            if (strcasecmp($venta['estatus'],'Cancelada')===0) {
                $this->registrarBitacora($idUsuario,'ventas','CANCEL',$idVenta,'Intento de cancelar venta ya cancelada',
                    json_encode(['estatus_prev'=>$venta['estatus']]),
                    json_encode(['estatus_new'=>$venta['estatus']]),
                    null,$ahora
                );
                $this->conn->commit();
                return ['ok'=>true,'msg'=>'La venta ya estaba cancelada.'];
            }

            $folio = $venta['folio'];

            $stDet = $this->conn->prepare(
                "SELECT id_producto, cantidad
                 FROM ventas_detalle
                 WHERE id_venta = :id AND (activo = 1 OR activo IS NULL)"
            );
            $stDet->execute([':id'=>$idVenta]);
            $dets = $stDet->fetchAll(\PDO::FETCH_ASSOC);

            $stInc = $this->conn->prepare("UPDATE productos SET stock_actual = stock_actual + :cant WHERE id_producto=:idp");
            $stMov = $this->conn->prepare(
                "INSERT INTO inventario_movimientos
                 (id_producto,tipo,cantidad,id_sucursal,id_usuario,referencia,motivo,fecha,activo)
                 VALUES (:idp,'Devolucion Venta',:cant,:ids,:idu,:ref,:mot,:f,1)"
            );

            $items = [];
            foreach ($dets as $d) {
                $idp  = (int)$d['id_producto'];
                $cant = (float)$d['cantidad'];
                if ($cant <= 0) continue;

                $stInc->execute([':cant'=>$cant, ':idp'=>$idp]);
                $stMov->execute([
                    ':idp'=>$idp, ':cant'=>$cant, ':ids'=>$idSucursal, ':idu'=>$idUsuario,
                    ':ref'=>$folio, ':mot'=>$motivo, ':f'=>$ahora
                ]);
                $items[] = ['id_producto'=>$idp,'cantidad'=>$cant];
            }

            $this->conn->prepare("UPDATE ventas SET estatus = 'Cancelada' WHERE id_venta = :id")
                       ->execute([':id'=>$idVenta]);

            $this->registrarBitacora(
                $idUsuario,'ventas','CANCEL',$idVenta,'Cancelación de venta y devolución a inventario',
                null, json_encode(['estatus_new'=>'Cancelada','devoluciones'=>$items]), null, $ahora
            );

            $this->conn->commit();
            return ['ok'=>true,'msg'=>'Venta cancelada, stock repuesto y bitácora registrada.'];

        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try {
                $this->registrarBitacora($idUsuario,'ventas','ERROR',(int)$idVenta,$e->getMessage(),null,null,null,$this->ahoraHermStr());
            } catch (\Throwable $th) {}
            return ['ok'=>false,'msg'=>$e->getMessage()];
        }
    }

    public function activarGuardada(int $idVenta, ?int $idFormaPago, bool $actualizarFecha = false): array
    {
        try {
            $this->conn->beginTransaction();

            $stV = $this->conn->prepare("SELECT * FROM ventas WHERE id_venta = :id FOR UPDATE");
            $stV->execute([':id'=>$idVenta]);
            $venta = $stV->fetch(\PDO::FETCH_ASSOC);
            if (!$venta) { throw new \Exception('Venta no encontrada.'); }

            $estatusPrev = $venta['estatus'];
            if (strcasecmp($estatusPrev, 'Activa') === 0) {
                $this->conn->commit();
                return ['ok'=>true, 'msg'=>'La venta ya estaba activa.'];
            }
            if (strcasecmp($estatusPrev, 'Guardada') !== 0) {
                throw new \Exception('Solo se pueden activar ventas con estatus "Guardada".');
            }

            // Normalizar a partir de la forma de pago elegida al activar
            $nuevoEstatus = 'Activa';
            if ($idFormaPago && $this->formaPagoEsCredito($idFormaPago)) {
                if (empty($venta['id_cliente'])) {
                    throw new \Exception('Para activar como crédito se requiere cliente en la venta.');
                }
                $nuevoEstatus = 'Credito';
            }

            $params = [ ':id'=>$idVenta, ':idfp'=>($idFormaPago ?: null) ];
            $sql = "UPDATE ventas SET estatus=:est, id_forma_pago = :idfp";
            if ($actualizarFecha) { $sql .= ", fecha = :f"; $params[':f'] = $this->ahoraHermStr(); }
            $sql .= " WHERE id_venta = :id";
            $params[':est'] = $nuevoEstatus;
            $this->conn->prepare($sql)->execute($params);

            $idUsuario = (int)($venta['id_usuario'] ?? 0);
            $this->registrarBitacora(
                $idUsuario,'ventas','UPDATE',$idVenta,'Activación de venta guardada (sin movimiento de inventario)',
                json_encode(['estatus_prev'=>$estatusPrev,'id_forma_pago_prev'=>$venta['id_forma_pago'],'fecha_prev'=>$venta['fecha']]),
                json_encode(['estatus_new'=>$nuevoEstatus,'id_forma_pago'=>$idFormaPago,'fecha_new'=>$actualizarFecha?($params[':f'] ?? $venta['fecha']):$venta['fecha']]),
                null,$this->ahoraHermStr()
            );

            $this->conn->commit();
            return ['ok'=>true, 'msg'=>'Venta activada correctamente.'];

        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            try {
                $idU = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['id_usuario'] ?? 0);
                $this->registrarBitacora($idU,'ventas','ERROR',(int)$idVenta,$e->getMessage(),null,null,null,$this->ahoraHermStr());
            } catch (\Throwable $th) {}
            return ['ok'=>false, 'msg'=>$e->getMessage()];
        }
    }

    /* ========================= Bitácora ========================= */
    private function registrarBitacora(
        $idUsuario,
        string $tabla,
        string $accion,
        int $registroId,
        string $descripcion = '',
        ?string $valorAnterior = null,
        ?string $valorNuevo = null,
        ?string $campoModificado = null,
        ?string $fechaRegistro = null
    ) {
        $fechaRegistro = $fechaRegistro ?: $this->ahoraHermStr();
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);

        $st = $this->conn->prepare(
            "INSERT INTO bitacora_movimientos
             (id_usuario,tabla,accion,registro_id,campo_modificado,valor_anterior,valor_nuevo,descripcion,ip_origen,activo,fecha)
             VALUES (:usr,:tbl,:acc,:rid,:campo,:val_ant,:val_nvo,:desc,:ip,1,:freg)"
        );
        $st->execute([
            ':usr'=>$idUsuario, ':tbl'=>$tabla, ':acc'=>$accion, ':rid'=>$registroId,
            ':campo'=>$campoModificado, ':val_ant'=>$valorAnterior, ':val_nvo'=>$valorNuevo,
            ':desc'=>$descripcion, ':ip'=>$ip, ':freg'=>$fechaRegistro
        ]);
    }
}
