<?php
// models/VentaModel.php
include_once '../includes/db.php';
require_once __DIR__ . '/../includes/constants.php';

class VentaModel
{
    private $conn;
    private static $idGrupoAcumulador = null;
    private $productoCache = [];

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

    /* ========================= Helpers de negocio ========================= */
    private function obtenerIdGrupoAcumulador(): ?int
    {
        if (self::$idGrupoAcumulador !== null) return self::$idGrupoAcumulador;
        if (defined('ID_GRUPO_ACUMULADOR')) {
            self::$idGrupoAcumulador = ID_GRUPO_ACUMULADOR ?: null;
            if (self::$idGrupoAcumulador !== null) return self::$idGrupoAcumulador;
        }

        try {
            $stmt = $this->conn->query(
                "SELECT id_grupo
                   FROM cat_grupos
                  WHERE LOWER(nombre_grupo) LIKE '%acumulador%'
                  ORDER BY id_grupo ASC
                  LIMIT 1"
            );
            $id = $stmt->fetchColumn();
            if ($id !== false && $id !== null) {
                self::$idGrupoAcumulador = (int)$id;
                if (!defined('ID_GRUPO_ACUMULADOR')) {
                    define('ID_GRUPO_ACUMULADOR', self::$idGrupoAcumulador);
                }
                return self::$idGrupoAcumulador;
            }
        } catch (\Throwable $th) {}

        self::$idGrupoAcumulador = null;
        return null;
    }

    private function obtenerProductoVenta(int $idProducto, bool $lock = true): array
    {
        if (isset($this->productoCache[$idProducto])) {
            return $this->productoCache[$idProducto];
        }

        $sql = "SELECT id_producto, descripcion, id_grupo, stock_actual, stock_minimo, costo_neto,
                       objeto_imp, tasa_iva
                FROM productos
                WHERE id_producto = :idp";
        if ($lock) {
            $sql .= " FOR UPDATE";
        }

        $st = $this->conn->prepare($sql);
        $st->execute([':idp' => $idProducto]);
        $prod = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
        $this->productoCache[$idProducto] = $prod;
        return $prod;
    }

    private function normalizarObjetoImpYtasaIva(array $producto): array
    {
        $objetoImp = trim((string)($producto['objeto_imp'] ?? ''));
        if ($objetoImp === '') {
            $objetoImp = '02';
        }

        $tasaIvaRaw = $producto['tasa_iva'] ?? null;
        $tasaIva = ($tasaIvaRaw === null || $tasaIvaRaw === '') ? 0.160000 : (float)$tasaIvaRaw;

        return [
            'objeto_imp' => $objetoImp,
            'tasa_iva' => $tasaIva,
        ];
    }

    private function calcularImpuestosDetalle(float $subtotal, string $objetoImp, float $tasaIva): array
    {
        if ($objetoImp !== '02') {
            return ['base_iva' => 0.00, 'importe_iva' => 0.00];
        }

        $baseIva = round($subtotal / (1 + $tasaIva), 2);
        $importeIva = round($subtotal - $baseIva, 2);

        return ['base_iva' => $baseIva, 'importe_iva' => $importeIva];
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

    /** Busca id_forma_pago por tipo lógico: efectivo / tarjeta / transferencia. */
    private function buscarIdFormaPagoPorTipo(string $tipo): ?int
    {
        $tipo = strtolower(trim($tipo));
        $sql  = "SELECT id_forma_pago FROM formas_pago WHERE activo = 1 AND ";

        if ($tipo === 'efectivo') {
            $sql .= "(LOWER(descripcion) LIKE '%efectivo%' OR clave_sat = '01')";
        } elseif ($tipo === 'tarjeta') {
            $sql .= "LOWER(descripcion) LIKE '%tarjeta%'";
        } elseif ($tipo === 'transferencia') {
            $sql .= "(LOWER(descripcion) LIKE '%transfer%' OR clave_sat = '03')";
        } else {
            return null;
        }

        $sql .= " ORDER BY id_forma_pago ASC LIMIT 1";

        try {
            $st = $this->conn->query($sql);
            $id = $st->fetchColumn();
            return $id ? (int)$id : null;
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function normalizarTexto($txt): string
    {
        $txt = strtolower(trim((string)$txt));
        $sinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT', $txt);
        return $sinAcentos !== false ? $sinAcentos : $txt;
    }

    private function formaPagoEsMixto(?int $idFormaPago): bool
    {
        if (!$idFormaPago) { return false; }
        try {
            $st = $this->conn->prepare("SELECT descripcion FROM formas_pago WHERE id_forma_pago = :id LIMIT 1");
            $st->execute([':id'=>$idFormaPago]);
            $desc = $st->fetchColumn();
            if ($desc === false) return false;
            $norm = $this->normalizarTexto($desc);
            return strpos($norm, 'mixto') !== false;
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function obtenerFormasPagoTarjetaCreditoDebito(): array
    {
        try {
            $st = $this->conn->prepare(
                "SELECT id_forma_pago, descripcion
                   FROM formas_pago
                  WHERE activo = 1
                    AND LOWER(descripcion) LIKE '%tarjeta%'
                    AND (
                        LOWER(descripcion) LIKE '%credito%' OR LOWER(descripcion) LIKE '%crédito%'
                        OR LOWER(descripcion) LIKE '%debito%' OR LOWER(descripcion) LIKE '%débito%'
                    )
                  ORDER BY descripcion ASC"
            );
            $st->execute();
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $th) {
            return [];
        }
    }

    /** Valida que la forma de pago exista y esté activa. */
    private function asegurarFormaPagoActiva(int $idFormaPago): void
    {
        if ($idFormaPago <= 0) {
            throw new \Exception('id_forma_pago inválido.');
        }

        $st = $this->conn->prepare(
            "SELECT activo FROM formas_pago WHERE id_forma_pago = :id LIMIT 1"
        );
        $st->execute([':id' => $idFormaPago]);
        $activo = $st->fetchColumn();

        if ($activo === false) {
            throw new \Exception('La forma de pago no existe.');
        }
        if ((int)$activo !== 1) {
            throw new \Exception('La forma de pago está inactiva.');
        }
    }

    /**
     * Normaliza SOLO el nombre del estatus, sin meter validaciones fuertes.
     * Regla:
     * - Si llega estatus "credito" o la forma de pago es crédito => 'Credito'
     * - Si llega "guardada/guardado" => 'Guardada'
     * - En otro caso => 'Activa'
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

    /** Recalcula saldo y estatus_credito a partir de ventas_abonos y total. */
    private function recalcularSaldoYEstatusCredito(int $idVenta): array
    {
        // Traer total y estatus de la venta
        $stV = $this->conn->prepare("SELECT total, estatus FROM ventas WHERE id_venta=:id");
        $stV->execute([':id'=>$idVenta]);
        $venta = $stV->fetch(\PDO::FETCH_ASSOC);
        if (!$venta) return ['ok'=>false, 'msg'=>'Venta no encontrada'];

        // Sumar abonos activos
        $stA = $this->conn->prepare(
            "SELECT COALESCE(SUM(CASE WHEN activo=1 THEN monto ELSE 0 END),0)
             FROM ventas_abonos
             WHERE id_venta=:id"
        );
        $stA->execute([':id'=>$idVenta]);
        $abonado = (float)$stA->fetchColumn();

        $total = (float)$venta['total'];
        $saldo = max(0.0, $total - $abonado);

        // Lógica de estatus_credito: solo si la venta es de crédito
        if (strcasecmp($venta['estatus'], 'Credito') !== 0) {
            $estatusCredito = 'N/A';
            $saldo = 0.0; // si no es crédito, saldo 0
        } else {
            if ($saldo >= $total - 0.0001) {
                $estatusCredito = 'Pendiente';     // sin abonos
            } elseif ($saldo <= 0.0001) {
                $estatusCredito = 'Liquidado';     // pagado
            } else {
                $estatusCredito = 'En Proceso';    // parcial
            }
        }

        // Actualizar cabecera
        $up = $this->conn->prepare(
            "UPDATE ventas
             SET saldo = :saldo, estatus_credito = :ec
             WHERE id_venta = :id"
        );
        $up->execute([':saldo'=>$saldo, ':ec'=>$estatusCredito, ':id'=>$idVenta]);

        return ['ok'=>true, 'saldo'=>$saldo, 'estatus_credito'=>$estatusCredito, 'abonado'=>$abonado];
    }

    /** Saldo rápido (para abonos). */
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
    public function obtenerVentas($pagina = 1, $limite = 10, $folio = '', $fecha = '', $estatus = '')
    {
        $offset = ($pagina - 1) * $limite;

        // Normalización leve
        $folio   = is_string($folio)   ? trim($folio)   : '';
        $estatus = is_string($estatus) ? trim($estatus) : '';
        $fecha   = is_string($fecha)   ? trim($fecha)   : ($fecha ?? '');

        $sql = "SELECT v.*,
                    c.nombre AS cliente,
                    u.nombre AS usuario,
                    cj.nombre AS caja,
                    COALESCE(fp.descripcion,'—') AS forma_pago,
                    tp.nombre AS tipo_precio,
                    (SELECT COALESCE(SUM(a.monto),0)
                        FROM ventas_abonos a
                        WHERE a.id_venta = v.id_venta AND a.activo=1) AS abonado,
                    COALESCE(v.saldo,
                             v.total - (SELECT COALESCE(SUM(a2.monto),0)
                                          FROM ventas_abonos a2
                                         WHERE a2.id_venta = v.id_venta AND a2.activo=1)
                    ) AS saldo,
                    v.estatus_credito,
                    cfdi.estatus AS estatus_fiscal,
                    cfdi.uuid AS cfdi_uuid,
                    cfdi.referencia AS cfdi_referencia
                FROM ventas v
                LEFT JOIN ventas_cfdi cfdi ON cfdi.id_venta = v.id_venta
                LEFT JOIN clientes     c  ON v.id_cliente     = c.id_cliente
                INNER JOIN usuarios    u  ON v.id_usuario     = u.id_usuario
                INNER JOIN cajas       cj ON v.id_caja        = cj.id_caja
                LEFT JOIN formas_pago  fp ON v.id_forma_pago  = fp.id_forma_pago
                INNER JOIN tipo_precio tp ON v.id_tipo_precio = tp.id_tipo_precio
                WHERE v.activo = 1";
        $params = [];

        if ($folio !== '')   { $sql .= " AND v.folio LIKE :folio";     $params[':folio']   = "%$folio%"; }
        if (!empty($fecha))  { $sql .= " AND DATE(v.fecha) = :fecha";  $params[':fecha']   = $fecha; }
        if ($estatus !== '') { $sql .= " AND v.estatus = :estatus";    $params[':estatus'] = $estatus; }

        $sql .= " ORDER BY v.id_venta DESC LIMIT :limite OFFSET :offset";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':limite',(int)$limite,\PDO::PARAM_INT);
        $st->bindValue(':offset',(int)$offset,\PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function contarVentas($folio = '', $fecha = '', $estatus = '')
    {
        $folio   = is_string($folio)   ? trim($folio)   : '';
        $estatus = is_string($estatus) ? trim($estatus) : '';
        $fecha   = is_string($fecha)   ? trim($fecha)   : ($fecha ?? '');

        $sql = "SELECT COUNT(*) FROM ventas v WHERE v.activo = 1";
        $params = [];

        if ($folio !== '')   { $sql .= " AND v.folio LIKE :folio";     $params[':folio']   = "%$folio%"; }
        if (!empty($fecha))  { $sql .= " AND DATE(v.fecha) = :fecha";  $params[':fecha']   = $fecha; }
        if ($estatus !== '') { $sql .= " AND v.estatus = :estatus";    $params[':estatus'] = $estatus; }

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        return (int)$st->fetchColumn();
    }

    /* ========================= Listado detalle ventas ========================= */
    public function obtenerVentasDetalle($pagina = 1, $limite = 10, $folio = '', $codigo = '', $fecha = '', $estatus = '')
    {
        $offset = ($pagina - 1) * $limite;

        $folio   = is_string($folio)   ? trim($folio)   : '';
        $codigo  = is_string($codigo)  ? trim($codigo)  : '';
        $estatus = is_string($estatus) ? trim($estatus) : '';
        $fecha   = is_string($fecha)   ? trim($fecha)   : ($fecha ?? '');

        $sql = "SELECT v.id_venta,
                       v.folio,
                       v.fecha,
                       v.estatus,
                       v.estatus_credito,
                       c.nombre AS cliente,
                       u.nombre AS usuario,
                       cj.nombre AS caja,
                       COALESCE(fp.descripcion,'—') AS forma_pago,
                       tp.nombre AS tipo_precio,
                       d.id_venta_detalle,
                       d.id_producto,
                       d.cantidad,
                       d.precio_unitario,
                       COALESCE(d.subtotal, d.cantidad * d.precio_unitario) AS total_renglon,
                       COALESCE(p.codigo, CONCAT('#', d.id_producto)) AS codigo_producto,
                       p.descripcion AS producto
                FROM ventas v
                INNER JOIN ventas_detalle d ON d.id_venta = v.id_venta AND (d.activo = 1 OR d.activo IS NULL)
                LEFT JOIN productos p        ON p.id_producto = d.id_producto
                LEFT JOIN clientes c         ON v.id_cliente  = c.id_cliente
                INNER JOIN usuarios u        ON v.id_usuario  = u.id_usuario
                INNER JOIN cajas cj          ON v.id_caja     = cj.id_caja
                LEFT JOIN formas_pago fp     ON v.id_forma_pago = fp.id_forma_pago
                INNER JOIN tipo_precio tp    ON v.id_tipo_precio = tp.id_tipo_precio
                WHERE v.activo = 1";
        $params = [];

        if ($folio !== '')   { $sql .= " AND v.folio LIKE :folio";     $params[':folio']   = "%$folio%"; }
        if ($codigo !== '')  { $sql .= " AND p.codigo LIKE :codigo";   $params[':codigo']  = "%$codigo%"; }
        if (!empty($fecha))  { $sql .= " AND DATE(v.fecha) = :fecha";  $params[':fecha']   = $fecha; }
        if ($estatus !== '') { $sql .= " AND v.estatus = :estatus";    $params[':estatus'] = $estatus; }

        $sql .= " ORDER BY v.id_venta DESC, d.id_venta_detalle ASC LIMIT :limite OFFSET :offset";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k,$v);
        $st->bindValue(':limite',(int)$limite,\PDO::PARAM_INT);
        $st->bindValue(':offset',(int)$offset,\PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function contarVentasDetalle($folio = '', $codigo = '', $fecha = '', $estatus = '')
    {
        $folio   = is_string($folio)   ? trim($folio)   : '';
        $codigo  = is_string($codigo)  ? trim($codigo)  : '';
        $estatus = is_string($estatus) ? trim($estatus) : '';
        $fecha   = is_string($fecha)   ? trim($fecha)   : ($fecha ?? '');

        $sql = "SELECT COUNT(*)
                FROM ventas v
                INNER JOIN ventas_detalle d ON d.id_venta = v.id_venta AND (d.activo = 1 OR d.activo IS NULL)
                LEFT JOIN productos p       ON p.id_producto = d.id_producto
                WHERE v.activo = 1";
        $params = [];

        if ($folio !== '')   { $sql .= " AND v.folio LIKE :folio";     $params[':folio']   = "%$folio%"; }
        if ($codigo !== '')  { $sql .= " AND p.codigo LIKE :codigo";   $params[':codigo']  = "%$codigo%"; }
        if (!empty($fecha))  { $sql .= " AND DATE(v.fecha) = :fecha";  $params[':fecha']   = $fecha; }
        if ($estatus !== '') { $sql .= " AND v.estatus = :estatus";    $params[':estatus'] = $estatus; }

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
                    c.rfc AS cliente_rfc,
                    c.direccion AS cliente_domicilio,
                    c.telefono AS cliente_telefono,
                    u.nombre AS usuario,
                    cj.nombre AS caja,
                    COALESCE(fp.descripcion,'—') AS forma_pago,
                    tp.nombre AS tipo_precio,
                    (SELECT COALESCE(SUM(a.monto),0)
                       FROM ventas_abonos a
                      WHERE a.id_venta = v.id_venta AND a.activo=1) AS abonado,
                    COALESCE(v.saldo,
                             v.total - (SELECT COALESCE(SUM(a2.monto),0)
                                          FROM ventas_abonos a2
                                         WHERE a2.id_venta = v.id_venta AND a2.activo=1)
                    ) AS saldo,
                    v.estatus_credito,
                    cfdi.estatus AS estatus_fiscal,
                    cfdi.uuid AS cfdi_uuid,
                    cfdi.referencia AS cfdi_referencia,
                    cfdi.fecha_timbrado AS cfdi_fecha_timbrado,
                    cfdi.mensaje_respuesta AS cfdi_mensaje_respuesta
             FROM ventas v
             LEFT JOIN ventas_cfdi cfdi ON cfdi.id_venta = v.id_venta
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
            "SELECT vd.*, p.descripcion AS producto, p.codigo AS codigo, p.id_grupo AS id_grupo
             FROM ventas_detalle vd
             INNER JOIN productos p ON p.id_producto = vd.id_producto
             WHERE vd.id_venta = :id
               AND (vd.activo = 1 OR vd.activo IS NULL)"
        );
        $st->bindValue(':id',$idVenta,\PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function obtenerPagosVenta($idVenta)
    {
        $st = $this->conn->prepare(
            "SELECT pv.*, fp.descripcion, fp.descripcion AS nombre_forma_pago
               FROM pagos_venta pv
               LEFT JOIN formas_pago fp ON fp.id_forma_pago = pv.id_forma_pago
              WHERE pv.id_venta = :id
                AND (pv.activo = 1 OR pv.activo IS NULL)"
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

    /* ========================= Pagos venta ========================= */

    /** Inserta un renglón en pagos_venta. referencia_pago = folio. */
    private function insertarPagoVenta(int $idVenta, int $idFormaPago, float $monto, string $referencia): void
    {
        $this->asegurarFormaPagoActiva($idFormaPago);
        error_log("[VENTA] insertarPagoVenta id_venta={$idVenta}, id_forma_pago={$idFormaPago}, monto={$monto}, ref={$referencia}");
        $st = $this->conn->prepare(
            "INSERT INTO pagos_venta
             (id_venta, id_forma_pago, monto, referencia_pago, activo, fecha_creacion)
             VALUES (:idv, :idfp, :m, :ref, 1, NOW())"
        );
        $st->execute([
            ':idv'  => $idVenta,
            ':idfp' => $idFormaPago,
            ':m'    => $monto,
            ':ref'  => $referencia
        ]);
    }

    /**
     * Registra pagos en pagos_venta:
     * - Solo si la venta queda Activa (no Guardada ni Crédito).
     * - Usa el folio como referencia_pago.
     * - Soporta efectivo, tarjeta, transferencia y mixto (2 renglones).
     */
    private function registrarPagosVenta(int $idVenta,string $folio,string $estatusBD,float $total,array $datosVenta, ?array $pagosMixtos = null): void
    {
        // Guardada o Crédito: no registrar pagos aquí
        if (strcasecmp($estatusBD, 'Activa') !== 0) {
            return;
        }

        // Solo registrar pagos_venta cuando la forma principal es Mixto
        $idFpVenta = isset($datosVenta['id_forma_pago']) ? (int)$datosVenta['id_forma_pago'] : 0;
        if (!$this->formaPagoEsMixto($idFpVenta)) {
            return;
        }

        $pagosRecibidos = is_array($pagosMixtos) ? array_values($pagosMixtos) : [];
        if (!empty($pagosRecibidos)) {
            $suma = 0.0;
            error_log('[VENTA] Pagos mixtos recibidos UI='.json_encode($pagosRecibidos));

            $idEfCatalogo = $this->buscarIdFormaPagoPorTipo('efectivo');

            foreach ($pagosRecibidos as $p) {
                $idFp  = (int)($p['id_forma_pago'] ?? 0);
                $monto = (float)($p['monto'] ?? 0);
                $ref   = isset($p['referencia_pago']) && $p['referencia_pago'] !== ''
                    ? (string)$p['referencia_pago']
                    : $folio;

                if ($idFp <= 0 || $monto <= 0) {
                    throw new \Exception('Cada pago del esquema mixto debe tener forma de pago y monto mayor a 0.');
                }

                if ($this->formaPagoEsMixto($idFp)) {
                    if (!$idEfCatalogo) {
                        throw new \Exception('No se encontró forma de pago EFECTIVO para registrar el pago mixto.');
                    }
                    $idFp = $idEfCatalogo;
                }

                $this->asegurarFormaPagoActiva($idFp);
                $suma += $monto;

                error_log("[VENTA] Insertando pago mixto id_venta={$idVenta}, id_forma_pago={$idFp}, monto={$monto}");
                $this->insertarPagoVenta($idVenta, $idFp, $monto, $ref);
            }

            if ($total > 0 && abs($suma - $total) > 0.05) {
                throw new \Exception('La suma de los pagos mixtos no coincide con el total de la venta.');
            }

            return;
        }

        $tipoOp = strtolower(trim((string)($datosVenta['tipo'] ?? '')));
        // Mixto: dos renglones, uno por cada forma (efectivo + tarjeta/transferencia)
        if ($tipoOp === 'mixto') {
            $mEf = (float)($datosVenta['recibido_efectivo'] ?? 0);
            $mTar = (float)($datosVenta['recibido_tarjeta'] ?? 0);
            $mTr  = (float)($datosVenta['recibido_transferencia'] ?? 0);

            if ($mEf > 0.0001) {
                $idEf = $this->buscarIdFormaPagoPorTipo('efectivo');
                if ($idEf) {
                    $this->insertarPagoVenta($idVenta, $idEf, $mEf, $folio);
                }
            }
            if ($mTar > 0.0001) {
                $idTar = $this->buscarIdFormaPagoPorTipo('tarjeta');
                if ($idTar) {
                    $this->insertarPagoVenta($idVenta, $idTar, $mTar, $folio);
                }
            }
            if ($mTr > 0.0001) {
                $idTr = $this->buscarIdFormaPagoPorTipo('transferencia');
                if ($idTr) {
                    $this->insertarPagoVenta($idVenta, $idTr, $mTr, $folio);
                }
            }
        }

        // Para tipo 'credito' no se registra aquí; se utiliza ventas_abonos.
    }

    private function reemplazarPagosVenta(int $idVenta, string $folio, string $estatusBD, float $total, ?int $idFormaPago, ?array $pagos = null): void
    {
        $this->conn->prepare("DELETE FROM pagos_venta WHERE id_venta = :id")->execute([':id'=>$idVenta]);

        if (strcasecmp($estatusBD, 'Activa') !== 0) {
            return;
        }

        // Solo registrar pagos_venta cuando la forma principal es Mixto
        if (!$this->formaPagoEsMixto((int)($idFormaPago ?? 0))) {
            return;
        }

        $pagosArr = is_array($pagos) ? $pagos : [];
        $idEfCatalogo = $this->buscarIdFormaPagoPorTipo('efectivo');
        if (!empty($pagosArr)) {
            $suma = 0.0;
            foreach ($pagosArr as $p) {
                $idFp  = (int)($p['id_forma_pago'] ?? 0);
                $monto = (float)($p['monto'] ?? 0);
                if ($idFp <= 0 || $monto <= 0) {
                    throw new \Exception('Cada pago del esquema mixto debe tener forma de pago y monto mayor a 0.');
                }

                if ($this->formaPagoEsMixto($idFp)) {
                    if (!$idEfCatalogo) {
                        throw new \Exception('No se encontró forma de pago EFECTIVO para registrar el pago mixto.');
                    }
                    $idFp = $idEfCatalogo;
                }

                $suma += $monto;
                $this->insertarPagoVenta($idVenta, $idFp, $monto, $folio);
            }

            if ($total > 0 && abs($suma - $total) > 0.05) {
                throw new \Exception('La suma de los pagos mixtos no coincide con el total de la venta.');
            }
            return;
        }

        // Si no vienen pagos no podemos inventar un renglón usando la forma de pago principal (MIXTO).
        // Evitamos insertar registros inválidos en pagos_venta.
        throw new \Exception('Se requieren los pagos para el esquema mixto.');
    }

    /* ========================= Crear venta ========================= */
    public function crearVenta(array $datosVenta, array $detalles, ?array $pagosMixtos = null)
    {
        $MAX_REINT = 6;

        try {
            $fechaHora  = $this->fechaHoraDesdeInput($datosVenta['fecha'] ?? null);
            $ahora      = $this->ahoraHermStr();

            // Cliente opcional -> NULL
            $idClienteRaw = $datosVenta['id_cliente'] ?? null;
            $idCliente = (is_null($idClienteRaw) || $idClienteRaw === '' || (int)$idClienteRaw === 0)
                ? null
                : (int)$idClienteRaw;

            // Sesión obligatoria
            $idUsuario  = (int)($datosVenta['id_usuario']  ?? 0);
            $idCaja     = (int)($datosVenta['id_caja']     ?? 0);
            $idSucursal = (int)($datosVenta['id_sucursal'] ?? 0);
            if (!$idUsuario || !$idCaja || !$idSucursal) {
                throw new \Exception('Faltan usuario/caja/sucursal.');
            }

            // Forma de pago (puede venir vacía/NULL)
            $idFormaPago = null;
            if (array_key_exists('id_forma_pago', $datosVenta) && $datosVenta['id_forma_pago'] !== '') {
                $idFormaPago = (int)$datosVenta['id_forma_pago'];
            }

            // Tipo de precio
            $idTipoPrecio = (int)($datosVenta['id_tipo_precio'] ?? 1);

            // Total
            $total = 0.0;
            foreach ($detalles as $d) {
                $total += (float)($d['cantidad'] ?? 0) * (float)($d['precio_unitario'] ?? 0);
            }

            // Normaliza estatus y forma de pago
            $norm = $this->normalizarEstatusYFormaPago(
                (string)($datosVenta['estatus'] ?? 'Activa'),
                $idFormaPago,
                $idCliente
            );
            $estatusBD   = $norm['estatus'];  // 'Activa','Guardada','Credito',...
            $idFormaPago = $norm['id_fp'];

            // Guardada: forzar id_forma_pago = NULL
            if (strcasecmp($estatusBD, 'Guardada') === 0) {
                $idFormaPago = null;
            } else {
                if ($idFormaPago !== null) {
                    $this->asegurarFormaPagoActiva($idFormaPago);
                }
            }

            // Tipo de pago textual que viene del POS (ej: 'mixto', 'contado', 'credito', 'guardada')
            $tipoPagoVenta = strtolower((string)($datosVenta['tipo_pago'] ?? ''));

            $anio = (int)date('Y', strtotime($fechaHora));

            // ===== Asignación de folio con lock y reintentos
            for ($i = 1; $i <= $MAX_REINT; $i++) {

                $lockOk = $this->obtenerLockFolio($anio, 5);
                if (!$lockOk) {
                    if ($i === $MAX_REINT) {
                        throw new \Exception('No se pudo obtener candado de folio.');
                    }
                    usleep(random_int(20000, 90000));
                    continue;
                }

                try {
                    $this->conn->beginTransaction();

                    $folio = trim($datosVenta['folio'] ?? '');
                    if ($folio === '') {
                        $folio = $this->generarFolioDesdeVentas($anio);
                    }

                    // ===== INSERT cabecera
                    $stVenta = $this->conn->prepare(
                        "INSERT INTO ventas
                        (folio, fecha, id_cliente, id_usuario, id_caja, id_forma_pago, id_tipo_precio, total, estatus, activo)
                        VALUES (:folio,:fecha,:idc,:idu,:idcj,:idfp,:idtp,:total,:estatus,1)"
                    );
                    $stVenta->bindValue(':folio', $folio);
                    $stVenta->bindValue(':fecha', $fechaHora);
                    if ($idCliente === null) {
                        $stVenta->bindValue(':idc', null, \PDO::PARAM_NULL);
                    } else {
                        $stVenta->bindValue(':idc', $idCliente, \PDO::PARAM_INT);
                    }
                    $stVenta->bindValue(':idu', $idUsuario, \PDO::PARAM_INT);
                    $stVenta->bindValue(':idcj', $idCaja, \PDO::PARAM_INT);
                    if ($idFormaPago === null) {
                        $stVenta->bindValue(':idfp', null, \PDO::PARAM_NULL);
                    } else {
                        $stVenta->bindValue(':idfp', $idFormaPago, \PDO::PARAM_INT);
                    }
                    $stVenta->bindValue(':idtp', $idTipoPrecio, \PDO::PARAM_INT);
                    $stVenta->bindValue(':total', $total);
                    $stVenta->bindValue(':estatus', $estatusBD);
                    $stVenta->execute();

                    $idVenta = (int)$this->conn->lastInsertId();

                    // ===== Helpers detalle / inventario
                    $stDet = $this->conn->prepare(
                        "INSERT INTO ventas_detalle
                        (id_venta, id_producto, cantidad, precio_unitario, subtotal, numero_poliza,
                        costo_unitario, costo_subtotal, utilidad_subtotal,
                        objeto_imp, tasa_iva, base_iva, importe_iva, activo)
                        VALUES
                        (:idv, :idp, :cant, :unit, :sub, :pol,
                        :c_unit, :c_sub, :u_sub,
                        :objimp, :tasa_iva, :base_iva, :importe_iva, 1)"
                    );
                    $stGet = $this->conn->prepare(
                        "SELECT stock_actual, stock_minimo, costo_neto, id_grupo, descripcion,
                                objeto_imp, tasa_iva
                        FROM productos
                        WHERE id_producto = :idp
                        FOR UPDATE"
                    );
                    $stUpd = $this->conn->prepare(
                        "UPDATE productos
                            SET stock_actual = stock_actual - :cant
                        WHERE id_producto = :idp"
                    );
                    $stMov = $this->conn->prepare(
                        "INSERT INTO inventario_movimientos
                        (id_producto,tipo,cantidad,id_sucursal,id_usuario,referencia,motivo,fecha,activo)
                        VALUES (:idp,'Salida',:cant,:ids,:idu,:ref,:mot,:f,1)"
                    );

                    $itemsBit = [];
                    $idGrupoAcum = $this->obtenerIdGrupoAcumulador();
                    foreach ($detalles as $d) {
                        $idp  = (int)$d['id_producto'];
                        $cant = (float)$d['cantidad'];
                        $unit = (float)$d['precio_unitario'];
                        $sub  = $cant * $unit;

                        $stGet->execute([':idp' => $idp]);
                        $p = $stGet->fetch(\PDO::FETCH_ASSOC);
                        if (!$p) {
                            throw new \Exception("Producto $idp no encontrado.");
                        }

                        $esAcumulador = $idGrupoAcum !== null && (int)($p['id_grupo'] ?? 0) === $idGrupoAcum;
                        $poliza = trim((string)($d['numero_poliza'] ?? ''));
                        if ($esAcumulador) {
                            if (abs($cant - 1.0) > 0.0001) {
                                throw new \Exception('Los acumuladores solo pueden venderse de uno en uno.');
                            }
                            if ($poliza === '') {
                                throw new \Exception('Captura el número de póliza para la batería/acumulador.');
                            }
                        }

                        $vendible = max(0.0, (float)$p['stock_actual'] - (float)$p['stock_minimo']);
                        if ($cant > $vendible) {
                            throw new \Exception("Stock insuficiente en producto $idp. Vendible: $vendible, solicitado: $cant.");
                        }

                        $costoUnit = (float)($p['costo_neto'] ?? 0);
                        $costoSub  = round($cant * $costoUnit, 2);
                        $utilSub   = round($sub - $costoSub, 2);
                        $impProd   = $this->normalizarObjetoImpYtasaIva($p);
                        $impDet    = $this->calcularImpuestosDetalle($sub, $impProd['objeto_imp'], $impProd['tasa_iva']);

                        $stDet->execute([
                            ':idv'    => $idVenta,
                            ':idp'    => $idp,
                            ':cant'   => $cant,
                            ':unit'   => $unit,
                            ':sub'    => $sub,
                            ':pol'    => ($poliza !== '' ? $poliza : null),
                            ':c_unit' => $costoUnit,
                            ':c_sub'  => $costoSub,
                            ':u_sub'  => $utilSub,
                            ':objimp' => $impProd['objeto_imp'],
                            ':tasa_iva' => $impProd['tasa_iva'],
                            ':base_iva' => $impDet['base_iva'],
                            ':importe_iva' => $impDet['importe_iva'],
                        ]);

                        $stUpd->execute([':cant' => $cant, ':idp' => $idp]);

                        // ===== Motivo de movimiento según estatus + tipo de pago =====
                        if (strcasecmp($estatusBD, 'Guardada') === 0 || $tipoPagoVenta === 'guardada') {
                            $motivoMov = 'Venta guardada (reserva)';
                        } elseif (strcasecmp($estatusBD, 'Credito') === 0 || $tipoPagoVenta === 'credito') {
                            $motivoMov = 'Venta a crédito';
                        } else {
                            // Estatus ACTIVA u otro distinto de Guardada/Crédito
                            if ($tipoPagoVenta === 'mixto') {
                                $motivoMov = 'Venta mixta de mostrador';
                            } else {
                                // efectivo, tarjeta, contado, etc.
                                $motivoMov = 'Venta de mostrador';
                            }
                        }

                        $stMov->execute([
                            ':idp' => $idp,
                            ':cant'=> $cant,
                            ':ids' => $idSucursal,
                            ':idu' => $idUsuario,
                            ':ref' => $folio,
                            ':mot' => $motivoMov,
                            ':f'   => $ahora
                        ]);

                        $itemsBit[] = [
                            'id_producto' => $idp,
                            'cant'        => $cant,
                            'precio_unit' => $unit,
                            'subtotal'    => $sub,
                            'costo_unit'  => $costoUnit
                        ];
                    }

                    // Bitácora
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
                            'total'      => $total,
                            'items'      => $itemsBit
                        ], JSON_UNESCAPED_UNICODE),
                        null,
                        $ahora
                    );

                    // 👉 Registrar pagos (pagos_venta) con referencia = folio
                    try {
                        $this->registrarPagosVenta($idVenta, $folio, $estatusBD, $total, $datosVenta, $pagosMixtos);
                    } catch (\Throwable $th) {
                        // si falla, no aborta la venta; solo no registra pagos
                    }

                    // Saldo / estatus_credito
                    try {
                        $this->recalcularSaldoYEstatusCredito($idVenta);
                    } catch (\Throwable $th) {}

                    $this->conn->commit();
                    $this->liberarLockFolio($anio);

                    return [
                        'ok'       => true,
                        'id_venta' => $idVenta,
                        'folio'    => $folio,
                        'total'    => $total,
                        'estatus'  => $estatusBD
                    ];

                } catch (\PDOException $e) {
                    if ($this->conn->inTransaction()) {
                        $this->conn->rollBack();
                    }
                    $this->liberarLockFolio($anio);

                    if (($e->errorInfo[1] ?? 0) === 1062) {
                        if ($i === $MAX_REINT) {
                            return ['ok'=>false,'msg'=>'No se pudo asignar folio único.'];
                        }
                        usleep(random_int(20000, 90000));
                        continue;
                    }

                    return ['ok'=>false,'msg'=>'Error BD: '.$e->getMessage()];
                } catch (\Throwable $th) {
                    if ($this->conn->inTransaction()) {
                        $this->conn->rollBack();
                    }
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
    public function actualizarVenta(array $datosVenta, ?array $detalles = null, ?array $pagosMixtos = null)
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

            $norm = $this->normalizarEstatusYFormaPago((string)($datosVenta['estatus'] ?? $ventaActual['estatus'] ?? 'Activa'), $idFormaPago, $idCliente);
            $estatusBD   = $norm['estatus'];
            $idFormaPago = $norm['id_fp'];

            if ($idFormaPago !== null) {
                $this->asegurarFormaPagoActiva($idFormaPago);
            }

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
                    ], JSON_UNESCAPED_UNICODE),
                    json_encode([
                        'estatus'=>$estatusBD,
                        'id_cliente'=>$idCliente,
                        'id_forma_pago'=>$idFormaPago,
                        'id_tipo_precio'=>$idTipoPrecio,
                        'fecha'=>$fechaBD
                    ], JSON_UNESCAPED_UNICODE),
                    null, $ahora
                );

                try { $this->recalcularSaldoYEstatusCredito($idVenta); } catch (\Throwable $th) {}

                $this->conn->commit();
                return ['ok'=>true,'msg'=>'Cabecera actualizada (sin tocar inventario ni detalle).'];
            }

            /* ===== CON DETALLE ===== */
            if ($pagosMixtos !== null && !is_array($pagosMixtos)) {
                $pagosMixtos = [];
            }
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

            $idGrupoAcum = $this->obtenerIdGrupoAcumulador();
            $nuevoAgg = [];
            $detallesNormalizados = [];
            $totalNuevo = 0.0;

            foreach ($detalles as $d) {
                $pid = (int)($d['id_producto'] ?? 0);
                $cant = (float)($d['cantidad'] ?? 0);
                $unit = (float)($d['precio_unitario'] ?? 0);
                $poliza = trim((string)($d['numero_poliza'] ?? ''));
                if ($cant <= 0 || $pid <= 0) continue;

                $prod = $this->obtenerProductoVenta($pid, true);
                if (!$prod) throw new \Exception("Producto $pid no encontrado.");

                $esAcumulador = $idGrupoAcum !== null && (int)($prod['id_grupo'] ?? 0) === $idGrupoAcum;
                if ($esAcumulador) {
                    if (abs($cant - 1.0) > 0.0001) {
                        throw new \Exception('Los acumuladores solo pueden venderse de uno en uno.');
                    }
                    if ($poliza === '') {
                        throw new \Exception('Captura el número de póliza para la batería/acumulador.');
                    }
                }

                $sub = $cant * $unit;
                $costoUnit = (float)($prod['costo_neto'] ?? 0);
                $costoSub  = round($cant * $costoUnit, 2);
                $utilSub   = round($sub - $costoSub, 2);

                $impuestosProd = $this->normalizarObjetoImpYtasaIva($prod);
                $impuestosDet  = $this->calcularImpuestosDetalle($sub, $impuestosProd['objeto_imp'], $impuestosProd['tasa_iva']);

                $detallesNormalizados[] = [
                    'id_producto'       => $pid,
                    'cantidad'          => $cant,
                    'precio_unitario'   => $unit,
                    'subtotal'          => $sub,
                    'numero_poliza'     => ($poliza !== '' ? $poliza : null),
                    'costo_unitario'    => $costoUnit,
                    'costo_subtotal'    => $costoSub,
                    'utilidad_subtotal' => $utilSub,
                    'objeto_imp'        => $impuestosProd['objeto_imp'],
                    'tasa_iva'          => $impuestosProd['tasa_iva'],
                    'base_iva'          => $impuestosDet['base_iva'],
                    'importe_iva'       => $impuestosDet['importe_iva'],
                ];

                if (!isset($nuevoAgg[$pid])) $nuevoAgg[$pid] = ['cantidad'=>0.0, 'precio_unitario'=>$unit];
                $nuevoAgg[$pid]['cantidad'] += $cant;
                $nuevoAgg[$pid]['precio_unitario'] = $unit;
                $totalNuevo += $sub;
            }

            $esMixtoSolicitado = isset($datosVenta['tipo_pago']) && strtolower((string)$datosVenta['tipo_pago']) === 'mixto';
            $esMixtoSolicitado = $esMixtoSolicitado || $this->formaPagoEsMixto($idFormaPago);
            if ($esMixtoSolicitado) {
                $pagosMixtos = is_array($pagosMixtos) ? array_values($pagosMixtos) : [];
                $catalogoTarjetas = $this->obtenerFormasPagoTarjetaCreditoDebito();
                $idsTarjetasValidas = array_map('intval', array_column($catalogoTarjetas, 'id_forma_pago'));

                if (empty($pagosMixtos)) {
                    throw new \Exception('Se requieren los pagos para el esquema mixto.');
                }

                $sumaPagos   = 0.0;
                $montoTarjeta = 0.0;
                foreach ($pagosMixtos as $pago) {
                    $idFpPago  = (int)($pago['id_forma_pago'] ?? 0);
                    $montoPago = (float)($pago['monto'] ?? 0);
                    if ($idFpPago <= 0 || $montoPago <= 0) {
                        throw new \Exception('Cada pago del esquema mixto debe tener forma de pago y monto mayor a 0.');
                    }
                    $sumaPagos += $montoPago;
                    if (in_array($idFpPago, $idsTarjetasValidas, true)) {
                        $montoTarjeta += $montoPago;
                    }
                }

                if ($montoTarjeta > 0 && empty($idsTarjetasValidas)) {
                    throw new \Exception('No hay formas de pago de tarjeta activas para registrar el pago con tarjeta.');
                }

                if ($montoTarjeta > 0) {
                    $idTarjetaSel = null;
                    foreach ($pagosMixtos as $pago) {
                        $idTmp = (int)($pago['id_forma_pago'] ?? 0);
                        if (in_array($idTmp, $idsTarjetasValidas, true)) { $idTarjetaSel = $idTmp; break; }
                    }
                    if ($idTarjetaSel === null) {
                        throw new \Exception('Selecciona el tipo de tarjeta (crédito o débito) para el pago con tarjeta.');
                    }
                }

                if ($totalNuevo > 0 && abs($sumaPagos - $totalNuevo) > 0.05) {
                    throw new \Exception('La suma de los pagos mixtos no coincide con el total de la venta.');
                }
            } else {
                $pagosMixtos = [];
            }

            $pids = array_unique(array_merge(array_keys($act), array_keys($nuevoAgg)));

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
                $newQ = (float)($nuevoAgg[$pid]['cantidad'] ?? 0.0);
                $delta = round($newQ - $oldQ, 4);

                if (abs($delta) < 0.0001) continue;

                $prod = $this->obtenerProductoVenta((int)$pid, true);
                if (!$prod) throw new \Exception("Producto $pid no encontrado.");

                $stockActual = (float)($prod['stock_actual'] ?? 0);
                $stockMin    = (float)($prod['stock_minimo'] ?? 0);
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
                "INSERT INTO ventas_detalle
                 (id_venta,id_producto,cantidad,precio_unitario,subtotal,numero_poliza,
                  costo_unitario,costo_subtotal,utilidad_subtotal,
                  objeto_imp,tasa_iva,base_iva,importe_iva,activo)
                 VALUES
                 (:idv,:idp,:cant,:unit,:sub,:pol,:c_unit,:c_sub,:u_sub,
                  :objimp,:tasa_iva,:base_iva,:importe_iva,1)"
            );

            foreach ($detallesNormalizados as $row) {
                $stInsDet->execute([
                    ':idv'    => $idVenta,
                    ':idp'    => $row['id_producto'],
                    ':cant'   => $row['cantidad'],
                    ':unit'   => $row['precio_unitario'],
                    ':sub'    => $row['subtotal'],
                    ':pol'    => $row['numero_poliza'],
                    ':c_unit' => $row['costo_unitario'],
                    ':c_sub'  => $row['costo_subtotal'],
                    ':u_sub'  => $row['utilidad_subtotal'],
                    ':objimp' => $row['objeto_imp'],
                    ':tasa_iva' => $row['tasa_iva'],
                    ':base_iva' => $row['base_iva'],
                    ':importe_iva' => $row['importe_iva'],
                ]);
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
            if ($idFormaPago===null) $stUpV->bindValue(':idfp',null,\PDO::PARAM_NULL);
            else $stUpV->bindValue(':idfp',$idFormaPago,\PDO::PARAM_INT);
            $stUpV->bindValue(':idtp',$idTipoPrecio,\PDO::PARAM_INT);
            $stUpV->bindValue(':total',$totalNuevo);
            $stUpV->bindValue(':estatus',$estatusBD);
            $stUpV->bindValue(':id',$idVenta,\PDO::PARAM_INT);
            $stUpV->execute();

            $this->reemplazarPagosVenta($idVenta, $folioVenta, $estatusBD, $totalNuevo, $idFormaPago, $pagosMixtos);

            $this->registrarBitacora(
                $idUsuario,'ventas','UPDATE',$idVenta,'Edición de venta',
                json_encode(['antes'=>$act,'estatus'=>$ventaActual['estatus'],'total'=>$ventaActual['total']], JSON_UNESCAPED_UNICODE),
                json_encode(['despues'=>$nuevoAgg,'estatus'=>$estatusBD,'total'=>$totalNuevo,'deltas'=>$deltasBit,'fecha'=>$fechaBD,'id_cliente'=>$idCliente,'id_forma_pago'=>$idFormaPago,'id_tipo_precio'=>$idTipoPrecio], JSON_UNESCAPED_UNICODE),
                null,$ahora
            );

            try { $this->recalcularSaldoYEstatusCredito($idVenta); } catch (\Throwable $th) {}

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

            $st = $this->conn->prepare(
                "SELECT id_venta, estatus, id_forma_pago, total
                 FROM ventas
                 WHERE id_venta = :id
                 FOR UPDATE"
            );
            $st->execute([':id'=>$idVenta]);
            $v = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$v) {
                $this->conn->rollBack();
                return ['ok'=>false,'msg'=>'Venta no encontrada'];
            }

            if (strcasecmp($v['estatus'], 'Cancelada') === 0) {
                $this->conn->rollBack();
                return ['ok'=>false,'msg'=>'Venta cancelada'];
            }
            if (strcasecmp($v['estatus'], 'Credito') !== 0) {
                $this->conn->rollBack();
                return ['ok'=>false,'msg'=>'La venta no es de crédito'];
            }

            $saldo = $this->saldoVenta($idVenta);
            if ($saldo <= 0) {
                $this->conn->rollBack();
                return ['ok'=>false,'msg'=>'Venta sin saldo'];
            }
            if ($monto > $saldo + 0.0001) {
                $this->conn->rollBack();
                return ['ok'=>false,'msg'=>'El abono excede el saldo'];
            }

            $fecha = $fechaAbono ? ($fechaAbono.' '.date('H:i:s')) : $this->ahoraHermStr();
            $ins = $this->conn->prepare(
                "INSERT INTO ventas_abonos
                 (id_venta,id_forma_pago,monto,fecha_abono,referencia_pago,id_usuario,activo,fecha_creacion)
                 VALUES (:v,:fp,:m,:f,:r,:u,1,NOW())"
            );
            $ins->execute([
                ':v'=>$idVenta,
                ':fp'=>$idFormaPago,
                ':m'=>$monto,
                ':f'=>$fecha,
                ':r'=>$ref,
                ':u'=>$idUsuario
            ]);

            $calc = $this->recalcularSaldoYEstatusCredito($idVenta);
            $saldo2 = (float)($calc['saldo'] ?? 0);

            $this->registrarBitacora(
                $idUsuario,
                'ventas_abonos',
                'INSERT',
                (int)$this->conn->lastInsertId(),
                'Abono a venta de crédito',
                null,
                json_encode(['id_venta'=>$idVenta,'monto'=>$monto,'saldo_antes'=>$saldo,'saldo_despues'=>$saldo2,'estatus_credito_nuevo'=>$calc['estatus_credito'] ?? null], JSON_UNESCAPED_UNICODE)
            );

            $this->conn->commit();
            return ['ok'=>true,'saldo'=>$saldo2];

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ['ok'=>false,'msg'=>$e->getMessage()];
        }
    }

     /* ========================= Abonos a venta (MIXTO) ========================= */
    /**
     * Registra varios pagos (mixto) en ventas_abonos en una sola transacción.
     * $pagos: [
     *   [ 'id_forma_pago' => int, 'monto' => float, 'referencia_pago' => ?string ],
     *   ...
     * ]
     */
    public function abonarVentaMixto(int $idVenta, array $pagos, ?string $fechaAbonoBase, ?int $idUsuario): array
    {
        if (empty($pagos)) {
            return ['ok'=>false,'msg'=>'No se recibieron pagos para el abono mixto.'];
        }

        try {
            $this->conn->beginTransaction();

            // Bloquea la venta
            $st = $this->conn->prepare(
                "SELECT id_venta, estatus, total
                 FROM ventas
                 WHERE id_venta = :id
                 FOR UPDATE"
            );
            $st->execute([':id'=>$idVenta]);
            $v = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$v) {
                $this->conn->rollBack();
                return ['ok'=>false,'msg'=>'Venta no encontrada'];
            }

            if (strcasecmp($v['estatus'], 'Cancelada') === 0) {
                $this->conn->rollBack();
                return ['ok'=>false,'msg'=>'Venta cancelada'];
            }
            if (strcasecmp($v['estatus'], 'Credito') !== 0) {
                $this->conn->rollBack();
                return ['ok'=>false,'msg'=>'La venta no es de crédito'];
            }

            // Saldo actual
            $saldoActual = $this->saldoVenta($idVenta);
            if ($saldoActual <= 0) {
                $this->conn->rollBack();
                return ['ok'=>false,'msg'=>'Venta sin saldo'];
            }

            // Validar pagos y sumar
            $suma = 0.0;
            foreach ($pagos as $p) {
                $idFp  = (int)($p['id_forma_pago'] ?? 0);
                $monto = (float)($p['monto'] ?? 0);
                if ($idFp <= 0 || $monto <= 0) {
                    $this->conn->rollBack();
                    return ['ok'=>false,'msg'=>'Cada pago del abono mixto debe tener forma de pago y monto mayor a 0.'];
                }
                $suma += $monto;
            }

            if ($suma > $saldoActual + 0.0001) {
                $this->conn->rollBack();
                return ['ok'=>false,'msg'=>'La suma de los pagos excede el saldo de la venta.'];
            }

            // Fecha para todos los renglones
            $fecha = $fechaAbonoBase
                ? ($fechaAbonoBase.' '.date('H:i:s'))
                : $this->ahoraHermStr();

            $ins = $this->conn->prepare(
                "INSERT INTO ventas_abonos
                 (id_venta,id_forma_pago,monto,fecha_abono,referencia_pago,id_usuario,activo,fecha_creacion)
                 VALUES (:v,:fp,:m,:f,:r,:u,1,NOW())"
            );

            foreach ($pagos as $p) {
                $idFp  = (int)$p['id_forma_pago'];
                $monto = (float)$p['monto'];
                $ref   = trim((string)($p['referencia_pago'] ?? ''));

                $this->asegurarFormaPagoActiva($idFp);

                $ins->execute([
                    ':v'  => $idVenta,
                    ':fp' => $idFp,
                    ':m'  => $monto,
                    ':f'  => $fecha,
                    ':r'  => $ref !== '' ? $ref : null,
                    ':u'  => $idUsuario
                ]);
            }

            // Recalcula saldo / estatus_credito
            $calc   = $this->recalcularSaldoYEstatusCredito($idVenta);
            $saldo2 = (float)($calc['saldo'] ?? 0);
            $abonadoTotal = (float)($calc['abonado'] ?? 0);

            // Bitácora
            try {
                $this->registrarBitacora(
                    $idUsuario,
                    'ventas_abonos',
                    'INSERT',
                    0,
                    'Abono mixto a venta de crédito',
                    null,
                    json_encode([
                        'id_venta'      => $idVenta,
                        'pagos'         => $pagos,
                        'saldo_antes'   => $saldoActual,
                        'saldo_despues' => $saldo2,
                        'abonado_total' => $abonadoTotal,
                        'estatus_credito_nuevo' => $calc['estatus_credito'] ?? null
                    ], JSON_UNESCAPED_UNICODE)
                );
            } catch (\Throwable $th) {}

            $this->conn->commit();
            return ['ok'=>true,'saldo'=>$saldo2,'abonado'=>$abonadoTotal];

        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ['ok'=>false,'msg'=>$e->getMessage()];
        }
    }
    /* ========================= Cambiar estatus / cancelar ========================= */
    public function cambiarEstatus($idVenta, $nuevoEstatus)
    {
        $st = $this->conn->prepare("UPDATE ventas SET estatus = :e WHERE id_venta = :id");
        $ok = $st->execute([':e'=>$nuevoEstatus, ':id'=>$idVenta]);

        try { $this->recalcularSaldoYEstatusCredito((int)$idVenta); } catch (\Throwable $th) {}
        return $ok;
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
                    json_encode(['estatus_prev'=>$venta['estatus']], JSON_UNESCAPED_UNICODE),
                    json_encode(['estatus_new'=>$venta['estatus']], JSON_UNESCAPED_UNICODE),
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

            try { $this->recalcularSaldoYEstatusCredito((int)$idVenta); } catch (\Throwable $th) {}

            $this->registrarBitacora(
                $idUsuario,'ventas','CANCEL',$idVenta,'Cancelación de venta y devolución a inventario',
                null, json_encode(['estatus_new'=>'Cancelada','devoluciones'=>$items], JSON_UNESCAPED_UNICODE), null, $ahora
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

   public function activarGuardada(int $idVenta,?int $idFormaPago, bool $actualizarFecha = false, ?int $idCliente = null, ?string $tipoPago = null, ?array $pagosMixtos = null ): array 
   {
        // Constante para identificar Crédito (PPD)
        $ID_FP_CREDITO = 21;
        $esMixto = is_string($tipoPago) && strtolower($tipoPago) === 'mixto';

        try {
            // Para NO mixto seguimos exigiendo forma de pago
            if (!$esMixto && $idFormaPago === null) {
                return ['ok'=>false, 'msg'=>'Debes seleccionar una forma de pago.'];
            }

            $this->conn->beginTransaction();

            // OJO: aquí agregamos el FOLIO
            $stV = $this->conn->prepare(
                "SELECT id_venta, folio, estatus, id_cliente, id_usuario, id_forma_pago, fecha, total
                FROM ventas
                WHERE id_venta = :id
                FOR UPDATE"
            );
            $stV->execute([':id'=>$idVenta]);
            $venta = $stV->fetch(\PDO::FETCH_ASSOC);
            if (!$venta) {
                throw new \Exception('Venta no encontrada.');
            }

            $folioVenta  = $venta['folio'] ?? null;
            $totalVenta  = (float)($venta['total'] ?? 0.0);
            $estatusPrev = $venta['estatus'];

            if (strcasecmp($estatusPrev, 'Activa') === 0 || strcasecmp($estatusPrev, 'Credito') === 0) {
                $this->conn->commit();
                try { $this->recalcularSaldoYEstatusCredito($idVenta); } catch (\Throwable $th) {}
                return ['ok'=>true, 'msg'=>'La venta ya estaba contabilizada.'];
            }
            if (strcasecmp($estatusPrev, 'Guardada') !== 0) {
                throw new \Exception('Solo se pueden activar ventas con estatus "Guardada".');
            }

            // Actualizar cliente si se envió uno distinto
            if ($idCliente && ((int)($venta['id_cliente'] ?? 0) !== (int)$idCliente)) {
                $this->conn->prepare("UPDATE ventas SET id_cliente=:c WHERE id_venta=:id")
                    ->execute([':c'=>$idCliente, ':id'=>$idVenta]);
                $venta['id_cliente'] = $idCliente;
            }

            // Determinar nuevo estatus
            // Mixto siempre "Activa"
            if ($esMixto) {
                $nuevoEstatus = 'Activa';
            } else {
                $nuevoEstatus = ($idFormaPago === $ID_FP_CREDITO) ? 'Credito' : 'Activa';
            }

            if ($nuevoEstatus === 'Credito' && empty($venta['id_cliente'])) {
                throw new \Exception('Para activar como Crédito se requiere seleccionar un cliente.');
            }

            if (!$esMixto && $idFormaPago !== null) {
                $this->asegurarFormaPagoActiva((int)$idFormaPago);
            }

            // ==== ACTUALIZAR VENTAS (estatus + id_forma_pago) ====
            $params = [':id'=>$idVenta, ':est'=>$nuevoEstatus];
            $sql = "UPDATE ventas SET estatus=:est";

            // AHORA SIEMPRE ACTUALIZAMOS id_forma_pago (también para mixto)
            if ($idFormaPago !== null) {
                $params[':idfp'] = $idFormaPago;
                $sql .= ", id_forma_pago=:idfp";
            }

            if ($actualizarFecha) {
                $params[':f'] = $this->ahoraHermStr();
                $sql .= ", fecha = :f";
            }

            $this->conn->prepare($sql." WHERE id_venta = :id")->execute($params);

            // ======== Manejo de pagos_venta ========
            // Solo se registran pagos_venta cuando la forma principal es Mixto (id 22)
            // y la venta queda Activa.

            // Limpiar posibles pagos previos
            $this->conn->prepare("DELETE FROM pagos_venta WHERE id_venta = :id")
                ->execute([':id' => $idVenta]);

            $idFpFinal = $idFormaPago ?? (int)($venta['id_forma_pago'] ?? 0);

            if ($nuevoEstatus === 'Activa' && $idFpFinal === 22) {
                $sqlIns = "INSERT INTO pagos_venta
                            (id_venta, id_forma_pago, monto, referencia_pago, activo, fecha_creacion)
                        VALUES (:id_v, :id_fp, :monto, :ref, 1, :f)";
                $stIns = $this->conn->prepare($sqlIns);
                $now   = $this->ahoraHermStr();

                // Validar arreglo de pagos mixtos
                if (empty($pagosMixtos) || !is_array($pagosMixtos)) {
                    throw new \Exception('No se recibieron los pagos para el esquema mixto.');
                }

                $suma = 0.0;
                $idEfCatalogo = $this->buscarIdFormaPagoPorTipo('efectivo');
                foreach ($pagosMixtos as $p) {
                    $idFp  = isset($p['id_forma_pago']) ? (int)$p['id_forma_pago'] : 0;
                    $monto = isset($p['monto']) ? (float)$p['monto'] : 0.0;
                    // Si no viene referencia, usamos el FOLIO de la venta
                    $ref   = isset($p['referencia_pago']) && $p['referencia_pago'] !== ''
                                ? (string)$p['referencia_pago']
                                : $folioVenta;

                    if ($idFp <= 0 || $monto <= 0) {
                        throw new \Exception('Cada renglón de pago mixto debe tener forma de pago y monto mayor a 0.');
                    }
                    if ($this->formaPagoEsMixto($idFp)) {
                        if (!$idEfCatalogo) {
                            throw new \Exception('No se encontró forma de pago EFECTIVO para registrar el pago mixto.');
                        }
                        $idFp = $idEfCatalogo;
                    }

                    $this->asegurarFormaPagoActiva($idFp);
                    $suma += $monto;

                    $stIns->execute([
                        ':id_v'  => $idVenta,
                        ':id_fp' => $idFp,
                        ':monto' => $monto,
                        ':ref'   => $ref,
                        ':f'     => $now,
                    ]);
                }

                $diff = abs($suma - $totalVenta);
                if ($totalVenta > 0 && $diff > 0.05) {
                    throw new \Exception('La suma de los pagos mixtos no coincide con el total de la venta.');
                }
            }
            // ======== Fin pagos_venta ========

            try { $this->recalcularSaldoYEstatusCredito($idVenta); } catch (\Throwable $th) {}

            $this->registrarBitacora(
                (int)($venta['id_usuario'] ?? 0),
                'ventas',
                'UPDATE',
                $idVenta,
                'Activación de venta guardada',
                json_encode([
                    'estatus_prev'        => $estatusPrev,
                    'id_forma_pago_prev'  => $venta['id_forma_pago'] ?? null,
                    'fecha_prev'          => $venta['fecha'] ?? null,
                    'id_cliente_prev'     => $venta['id_cliente'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'estatus_new'         => $nuevoEstatus,
                    'id_forma_pago'       => $idFormaPago,
                    'fecha_new'           => $actualizarFecha ? ($params[':f'] ?? $venta['fecha']) : ($venta['fecha'] ?? null),
                    'id_cliente_new'      => $venta['id_cliente'] ?? $idCliente,
                    'tipo_pago'           => $tipoPago,
                    'pagos_mixtos'        => $esMixto ? $pagosMixtos : null,
                ], JSON_UNESCAPED_UNICODE),
                null,
                $this->ahoraHermStr()
            );

            $this->conn->commit();
            return [
                'ok'=>true,
                'msg'=>($nuevoEstatus === 'Credito')
                    ? 'Venta activada como Crédito.'
                    : 'Venta activada como Activa.'
            ];

        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            try {
                $idU = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['id_usuario'] ?? 0);
                $this->registrarBitacora(
                    $idU,
                    'ventas',
                    'ERROR',
                    (int)$idVenta,
                    $e->getMessage(),
                    null,
                    null,
                    null,
                    $this->ahoraHermStr()
                );
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
