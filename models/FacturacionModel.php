<?php

require_once __DIR__ . '/../services/facturacion/FacturacionSchemaHelper.php';

class FacturacionModel
{
    private PDO $conn;
    private FacturacionSchemaHelper $schema;

    public function __construct(PDO $conn, FacturacionSchemaHelper $schema)
    {
        $this->conn = $conn;
        $this->schema = $schema;
    }

    public function loadContext(int $idVenta): array
    {
        $venta = $this->getVenta($idVenta);
        $detalles = $this->getVentaDetalle($idVenta);
        $cfdiActual = $this->getCfdiByVenta($idVenta);
        $emisor = $this->getEmisorByVenta($idVenta);
        $draft = $this->getDraftFacturacion($cfdiActual ?: []);
        $receptor = $this->getReceptorFromFacturacionData($draft, $cfdiActual ?: []);
        $clienteSeleccionado = !empty($receptor['cliente_sat_id'])
            ? $this->obtenerClienteFacturacion((int)$receptor['cliente_sat_id'])
            : null;
        $conceptos = $this->buildConceptos($detalles);
        $formaPago = $this->buildFormaPago($venta ?: [], $emisor, $cfdiActual ?: []);
        $totales = $this->buildTotales($venta ?: [], $conceptos);
        error_log('[FACTURACION][loadContext] venta=' . json_encode([
            'id_venta' => $idVenta,
            'total_venta' => (float)($venta['total'] ?? 0),
            'subtotal_factura_origen' => (float)($venta['subtotal_factura'] ?? 0),
            'descuento_factura_origen' => (float)($venta['descuento_factura'] ?? 0),
            'totales_calculados' => $totales,
            'conceptos_count' => count($conceptos),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $catalogos = [
            'regimenes_fiscales' => $this->listarRegimenesFiscales(),
            'usos_cfdi' => $this->listarUsosCfdi(),
            'monedas' => $this->listarMonedas(),
            'formas_pago' => $this->listarFormasPagoSat(),
            'metodos_pago' => $this->listarMetodosPago(),
            'tipos_comprobante' => $this->listarTiposComprobante(),
            'exportaciones' => $this->listarExportaciones(),
        ];
        $facturaDraft = $this->buildFacturaDraft($idVenta, $venta ?: [], $emisor, $receptor, $formaPago, $conceptos, $totales, $catalogos, $detalles);

        return [
            'venta' => $venta,
            'detalles' => $detalles,
            'cfdi_actual' => $cfdiActual,
            'emisor' => $emisor,
            'receptor' => $receptor,
            'cliente_seleccionado' => $clienteSeleccionado,
            'informacion_global' => $this->buildInformacionGlobal($receptor),
            'catalogos' => $catalogos,
            'forma_pago' => $formaPago,
            'conceptos' => $conceptos,
            'totales' => $totales,
            'factura_draft' => $facturaDraft,
        ];
    }

    public function getCfdiByVenta(int $idVenta): ?array
    {
        if (!$this->schema->tableExists('ventas_cfdi')) {
            return null;
        }
        $st = $this->conn->prepare('SELECT * FROM ventas_cfdi WHERE id_venta = :id ORDER BY ' . $this->orderByCfdi() . ' LIMIT 1');
        $st->execute([':id' => $idVenta]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    public function listarTicketsFacturablesMultiples(string $q = '', int $pagina = 1, int $limite = 50): array
    {
        $pagina = max(1, $pagina);
        $limite = max(1, min(200, $limite));
        $offset = ($pagina - 1) * $limite;

        $params = [];
        $fromWhere = " FROM ventas v
                LEFT JOIN clientes c ON c.id_cliente = v.id_cliente
                LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
                LEFT JOIN formas_pago fp ON fp.id_forma_pago = v.id_forma_pago
                LEFT JOIN ventas_cfdi vc ON vc.id_venta = v.id_venta AND vc.estatus = 'TIMBRADO'
                WHERE v.activo = 1
                  AND v.estatus IN ('Activa', 'Credito')
                  AND vc.id_cfdi IS NULL";

        $q = trim($q);
        if ($q !== '') {
            $fromWhere .= " AND (v.folio LIKE :q OR c.nombre LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }

        $sqlTotal = 'SELECT COUNT(*)' . $fromWhere;
        $stTotal = $this->conn->prepare($sqlTotal);
        foreach ($params as $k => $v) {
            $stTotal->bindValue($k, $v);
        }
        $stTotal->execute();
        $total = (int)$stTotal->fetchColumn();

        $sql = "SELECT v.id_venta, v.folio, v.fecha, v.total, c.nombre AS cliente,
                       COALESCE(u.nombre, u.usuario, '—') AS usuario,
                       COALESCE(fp.descripcion, '—') AS forma_pago"
            . $fromWhere
            . " ORDER BY v.id_venta DESC LIMIT :lim OFFSET :off";

        $st = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', $limite, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();

        return [
            'tickets' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'pagina' => $pagina,
            'limite' => $limite,
        ];
    }

    public function registrarTicketsEnCfdi(int $idCfdiPrincipal, array $idsVenta, ?int $idVentaPrincipal = null): void
    {
        if (!$this->schema->tableExists('ventas_cfdi_tickets')) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $idsVenta), fn($id) => $id > 0)));
        if ($idVentaPrincipal !== null && $idVentaPrincipal > 0) {
            $ids[] = $idVentaPrincipal;
            $ids = array_values(array_unique($ids));
        }
        if (!$ids) {
            return;
        }

        $deleteSql = 'DELETE FROM ventas_cfdi_tickets WHERE id_cfdi_principal = :id_cfdi_principal';
        $insertSql = 'INSERT INTO ventas_cfdi_tickets (id_cfdi_principal, id_venta, created_at) VALUES (:id_cfdi_principal, :id_venta, NOW())';
        $deleteSt = $this->conn->prepare($deleteSql);
        $insertSt = $this->conn->prepare($insertSql);

        $deleteSt->execute([':id_cfdi_principal' => $idCfdiPrincipal]);
        foreach ($ids as $idVenta) {
            $insertSt->execute([
                ':id_cfdi_principal' => $idCfdiPrincipal,
                ':id_venta' => $idVenta,
            ]);
        }
    }

    public function createOrGetCfdiRecord(int $idVenta, array $data): array
    {
        if (!$this->schema->tableExists('ventas_cfdi')) {
            throw new RuntimeException('La tabla ventas_cfdi no está disponible en la base de datos.');
        }

        $existing = $this->getCfdiByVenta($idVenta);
        if ($existing) {
            $this->updateCfdiRecord((int)$existing[$this->pkCfdi()], $data);
            return $this->getCfdiByVenta($idVenta) ?: $existing;
        }

        $payload = $this->schema->filterData('ventas_cfdi', array_merge([
            'id_venta' => $idVenta,
            'estatus' => 'BORRADOR',
            'updated_at' => date('Y-m-d H:i:s'),
        ], $data));

        $cols = array_keys($payload);
        $marks = array_map(fn($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO ventas_cfdi (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $marks) . ')';
        $st = $this->conn->prepare($sql);
        foreach ($payload as $col => $value) {
            $st->bindValue(':' . $col, $value);
        }
        $st->execute();
        return $this->getCfdiByVenta($idVenta) ?: ['id_cfdi' => (int)$this->conn->lastInsertId(), 'id_venta' => $idVenta] + $data;
    }

    public function updateCfdiRecord(int $idCfdi, array $data): void
    {
        if (!$this->schema->tableExists('ventas_cfdi')) {
            return;
        }

        $payload = $this->schema->filterData('ventas_cfdi', array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]));
        unset($payload[$this->pkCfdi()]);
        if (!$payload) {
            return;
        }

        $sets = [];
        foreach (array_keys($payload) as $col) {
            $sets[] = "{$col} = :{$col}";
        }
        $sql = 'UPDATE ventas_cfdi SET ' . implode(', ', $sets) . ' WHERE ' . $this->pkCfdi() . ' = :id_cfdi';
        $st = $this->conn->prepare($sql);
        foreach ($payload as $col => $value) {
            $st->bindValue(':' . $col, $value);
        }
        $st->bindValue(':id_cfdi', $idCfdi, PDO::PARAM_INT);
        $st->execute();
    }

    public function buildReferencia(array $venta): string
    {
        $folio = (string)($venta['folio'] ?? 'SIN-FOLIO');
        return 'FAC-' . preg_replace('/[^A-Za-z0-9\-]/', '', $folio) . '-' . date('YmdHis');
    }

    public function guardarDatosFiscalesVenta(int $idVenta, array $data): array
    {
        $venta = $this->getVenta($idVenta);
        if (!$venta) {
            throw new RuntimeException('La venta no existe.');
        }

        $receptor = $this->normalizarDatosFiscales($data);
        $this->conn->beginTransaction();

        try {
            $this->guardarComprobanteVenta($idVenta, $data, $venta, $receptor);
            $this->conn->commit();

            return [
                'guardado' => true,
                'msg' => 'Datos fiscales guardados correctamente.',
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function aplicarDatosFacturacionExistente(array $ctx, int $idClienteSat, array $data = []): array
    {
        if ($idClienteSat <= 0) {
            throw new RuntimeException('Debe seleccionar un receptor existente.');
        }

        $cliente = $this->obtenerClienteFacturacion($idClienteSat);
        if (!$cliente) {
            throw new RuntimeException('El receptor seleccionado no existe en clientes_sat.');
        }

        $inputReceptor = is_array($data['receptor'] ?? null) ? $data['receptor'] : [];
        $inputComprobante = is_array($data['comprobante'] ?? null) ? $data['comprobante'] : [];
        $inputEmisor = is_array($data['emisor'] ?? null) ? $data['emisor'] : [];

        $ctx['cliente_seleccionado'] = $cliente;
        $receptorBase = $this->buildReceptorFromClienteSat($cliente);
        $receptorEditado = $this->normalizarReceptorInput($inputReceptor);
        $receptorEditado['cliente_sat_id'] = $idClienteSat;
        $ctx['receptor'] = array_merge($receptorBase, array_filter($receptorEditado, static function ($value) {
            return $value !== null && $value !== '';
        }));
        $ctx['receptor']['cliente_sat_id'] = $idClienteSat;
        $ctx['receptor']['es_publico_general'] = $this->esPublicoGeneralReceptor($ctx['receptor']);
        $ctx['informacion_global'] = $this->buildInformacionGlobal($ctx['receptor']);

        $venta = is_array($ctx['venta'] ?? null) ? $ctx['venta'] : [];
        $ctx['forma_pago'] = array_merge(
            is_array($ctx['forma_pago'] ?? null) ? $ctx['forma_pago'] : [],
            $this->normalizarComprobanteInput($inputComprobante)
        );

        error_log('[FACTURACION][fuente-datos-fiscales] ' . json_encode([
            'emisor_fuente' => 'config_emisor_interna',
            'receptor_fuente' => 'modal_facturacion/clientes_sat',
            'emisor_input_modal' => $inputEmisor,
            'receptor_input_modal' => [
                'rfc' => $inputReceptor['rfc'] ?? null,
                'nombre' => $inputReceptor['nombre'] ?? null,
                'regimen_fiscal' => $inputReceptor['regimen_fiscal'] ?? null,
                'uso_cfdi' => $inputReceptor['uso_cfdi'] ?? null,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (is_array($ctx['factura_draft'] ?? null)) {
            $ctx['factura_draft']['receptor'] = [
                'id_cliente_fiscal' => (int)($ctx['receptor']['cliente_sat_id'] ?? 0),
                'rfc' => $ctx['receptor']['rfc'] ?? '',
                'nombre' => $ctx['receptor']['nombre'] ?? '',
                'nombre_comercial' => $ctx['receptor']['nombre_comercial'] ?? '',
                'correo' => $ctx['receptor']['correo'] ?? '',
                'codigo_postal' => $ctx['receptor']['domicilio_fiscal_receptor'] ?? '',
                'regimen_fiscal' => $ctx['receptor']['regimen_fiscal_receptor'] ?? '',
                'uso_cfdi' => $ctx['receptor']['uso_cfdi'] ?? '',
                'residencia_fiscal' => $ctx['receptor']['residencia_fiscal'] ?? '',
                'numero_registro_tributario' => $ctx['receptor']['num_reg_id_trib'] ?? '',
                'es_publico_general' => !empty($ctx['receptor']['es_publico_general']),
            ];
            $ctx['factura_draft']['comprobante'] = array_merge(
                is_array($ctx['factura_draft']['comprobante'] ?? null) ? $ctx['factura_draft']['comprobante'] : [],
                [
                    'moneda' => $ctx['forma_pago']['moneda'] ?? 'MXN',
                    'metodo_pago' => $ctx['forma_pago']['metodo_pago'] ?? '',
                    'forma_pago' => $ctx['forma_pago']['forma_pago'] ?? '',
                    'tipo_cambio' => $ctx['forma_pago']['tipo_cambio'] ?? '',
                    'exportacion' => $ctx['forma_pago']['exportacion'] ?? '01',
                    'tipo_comprobante' => $ctx['forma_pago']['tipo_comprobante'] ?? 'I',
                    'condiciones_pago' => $ctx['forma_pago']['condiciones_pago'] ?? '',
                ]
            );
        }

        return $ctx;
    }

    private function getVenta(int $idVenta): ?array
    {
        $sql = "SELECT
                v.*,
                COALESCE(fp.clave_sat, '') AS forma_pago_sat,
                COALESCE(fp.descripcion, '') AS forma_pago,
                COALESCE(s.nombre, CONCAT('Sucursal #', cj.id_sucursal)) AS sucursal_nombre,
                c.id_cliente AS cliente_id,
                c.nombre AS cliente_nombre,
                c.rfc AS cliente_rfc,
                c.correo AS cliente_correo,
                c.telefono AS cliente_telefono,
                c.direccion AS cliente_direccion,
                c.uso_cfdi AS cliente_uso_cfdi,
                cj.id_sucursal AS id_sucursal
            FROM ventas v
            LEFT JOIN clientes c ON c.id_cliente = v.id_cliente
            LEFT JOIN formas_pago fp ON fp.id_forma_pago = v.id_forma_pago
            INNER JOIN cajas cj ON cj.id_caja = v.id_caja
            LEFT JOIN sucursales s ON s.id_sucursal = cj.id_sucursal
            WHERE v.id_venta = :id
            LIMIT 1";

        $st = $this->conn->prepare($sql);
        $st->execute([':id' => $idVenta]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            return null;
        }

        $row['subtotal_factura'] = $this->resolveSubtotalVenta($row, $idVenta);
        $row['descuento_factura'] = (float)($row['descuento'] ?? 0);
        return $row;
    }

    private function getVentaDetalle(int $idVenta): array
    {
        $sql = "SELECT vd.*, p.codigo AS producto_codigo, p.descripcion AS producto_descripcion,
                       p.clave_prod_serv_sat, p.objeto_imp AS producto_objeto_imp, p.tasa_iva AS producto_tasa_iva,
                       p.id_unidad_sat, us.clave_unidad_sat, us.descripcion AS unidad_sat_descripcion
                FROM ventas_detalle vd
                INNER JOIN productos p ON p.id_producto = vd.id_producto
                LEFT JOIN unidades_sat us ON us.id_unidad_sat = p.id_unidad_sat
                WHERE vd.id_venta = :id
                  AND (vd.activo = 1 OR vd.activo IS NULL)";
        $st = $this->conn->prepare($sql);
        $st->execute([':id' => $idVenta]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function buscarClientesFacturacion(string $q = '', int $limite = 20): array
    {
        $limite = max(1, min(50, $limite));
        $sql = "SELECT
                    cs.id,
                    cs.nombre_comercial,
                    cs.rfc,
                    cs.razon_social,
                    cs.regimen_fiscal,
                    cs.uso_cdfi,
                    cs.email,
                    cs.email_alterno,
                    cs.dom_fiscal_cp,
                    cs.residencia_fiscal,
                    cs.numero_registro_tributario,
                    rf.Descripcion AS regimen_fiscal_descripcion,
                    uc.Descripcion AS uso_cfdi_descripcion
                FROM clientes_sat cs
                LEFT JOIN cat_sat_regimen_fiscal rf ON rf.ClaveRegimenFiscal = cs.regimen_fiscal
                LEFT JOIN cat_sat_uso_cfdi uc ON uc.ClaveUsoCFDI = cs.uso_cdfi
                WHERE 1 = 1";

        $params = [];
        if ($q !== '') {
            $sql .= " AND (
                        COALESCE(cs.rfc, '') LIKE :q_rfc
                        OR COALESCE(cs.razon_social, '') LIKE :q_razon
                        OR COALESCE(cs.nombre_comercial, '') LIKE :q_nombre_comercial
                    )";
            $like = '%' . $q . '%';
            $params = [
                ':q_rfc' => $like,
                ':q_razon' => $like,
                ':q_nombre_comercial' => $like,
            ];
        }

        $sql .= " ORDER BY COALESCE(NULLIF(cs.razon_social, ''), NULLIF(cs.nombre_comercial, ''), cs.rfc, cs.id) ASC
                  LIMIT :limite";

        $st = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $st->bindValue($key, $value);
        }
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->execute();

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row) => $this->mapClienteFacturacion($row), $rows);
    }

    public function obtenerClienteFacturacion(int $idCliente): ?array
    {
        if ($idCliente <= 0) {
            return null;
        }

        $sql = "SELECT
                    cs.id,
                    cs.nombre_comercial,
                    cs.rfc,
                    cs.razon_social,
                    cs.regimen_fiscal,
                    cs.uso_cdfi,
                    cs.email,
                    cs.email_alterno,
                    cs.dom_fiscal_cp,
                    cs.residencia_fiscal,
                    cs.numero_registro_tributario,
                    rf.Descripcion AS regimen_fiscal_descripcion,
                    uc.Descripcion AS uso_cfdi_descripcion
                FROM clientes_sat cs
                LEFT JOIN cat_sat_regimen_fiscal rf ON rf.ClaveRegimenFiscal = cs.regimen_fiscal
                LEFT JOIN cat_sat_uso_cfdi uc ON uc.ClaveUsoCFDI = cs.uso_cdfi
                WHERE cs.id = :id_cliente
                LIMIT 1";

        $st = $this->conn->prepare($sql);
        $st->bindValue(':id_cliente', $idCliente, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        return $row ? $this->mapClienteFacturacion($row) : null;
    }

    private function getEmisorByVenta(int $idVenta): array
    {
        $sql = "SELECT cfe.*, s.nombre AS sucursal_nombre
                FROM ventas v
                INNER JOIN cajas c ON c.id_caja = v.id_caja
                INNER JOIN config_fiscal_emisor cfe ON cfe.id_sucursal = c.id_sucursal AND cfe.activo = 1 AND cfe.es_default = 1
                LEFT JOIN sucursales s ON s.id_sucursal = cfe.id_sucursal
                WHERE v.id_venta = :id
                ORDER BY cfe.id_config DESC
                LIMIT 1";
        $st = $this->conn->prepare($sql);
        $st->execute([':id' => $idVenta]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $regimenFiscal = $this->schema->rowValue($row, ['regimen_fiscal_emisor', 'regimen_fiscal']);
        $tipoComprobante = $this->schema->rowValue($row, ['tipo_comprobante'], 'I');
        $exportacion = $this->schema->rowValue($row, ['exportacion_default'], '01');
        $moneda = $this->schema->rowValue($row, ['moneda_default'], 'MXN');

        $regimenDescripcion = $this->obtenerDescripcionCatalogoSat(
            'cat_sat_regimen_fiscal',
            $regimenFiscal,
            ['ClaveRegimenFiscal'],
            ['Descripcion']
        );
        $tipoComprobanteDescripcion = $this->obtenerDescripcionCatalogoSat(
            'cat_sat_tipo_comprobante',
            $tipoComprobante,
            ['ClaveTipoComprobante', 'ClaveTipoDeComprobante', 'Clave'],
            ['Descripcion', 'Descripción', 'Nombre']
        );
        $exportacionDescripcion = $this->obtenerDescripcionCatalogoSat(
            'cat_sat_exportacion',
            $exportacion,
            ['ClaveExportacion', 'ClaveExportación', 'Clave'],
            ['Descripcion', 'Descripción', 'Nombre']
        );
        $monedaDescripcion = $this->obtenerDescripcionCatalogoSat(
            'cat_sat_moneda',
            $moneda,
            ['ClaveMoneda', 'Clave'],
            ['Descripcion', 'Descripción', 'Nombre']
        );

        return [
            'rfc' => $this->schema->rowValue($row, ['rfc_emisor', 'rfc']),
            'nombre' => $this->schema->rowValue($row, ['razon_social_emisor', 'nombre', 'razon_social']),
            'sucursal' => $this->schema->rowValue($row, ['sucursal_nombre', 'nombre_sucursal']),
            'regimen_fiscal' => $regimenFiscal,
            'regimen_fiscal_descripcion' => $regimenDescripcion,
            'regimen_fiscal_label' => $this->buildClaveDescripcionLabel($regimenFiscal, $regimenDescripcion),
            'lugar_expedicion' => $this->schema->rowValue($row, ['cp_expedicion', 'codigo_postal', 'lugar_expedicion']),
            'serie' => $this->schema->rowValue($row, ['serie']),
            'folio_actual' => $this->schema->rowValue($row, ['folio_actual']),
            'tipo_comprobante' => $tipoComprobante,
            'tipo_comprobante_descripcion' => $tipoComprobanteDescripcion,
            'tipo_comprobante_label' => $this->buildClaveDescripcionLabel($tipoComprobante, $tipoComprobanteDescripcion),
            'exportacion' => $exportacion,
            'exportacion_descripcion' => $exportacionDescripcion,
            'exportacion_label' => $this->buildClaveDescripcionLabel($exportacion, $exportacionDescripcion),
            'moneda' => $moneda,
            'moneda_descripcion' => $monedaDescripcion,
            'moneda_label' => $this->buildClaveDescripcionLabel($moneda, $monedaDescripcion),
            'objeto_imp_default' => $this->schema->rowValue($row, ['objeto_imp_default'], '02'),
            'fac_atr_adquirente' => $this->schema->rowValue($row, ['fac_atr_adquirente']),
            'raw' => $row,
        ];
    }

    private function obtenerDescripcionCatalogoSat(
        string $tabla,
        ?string $clave,
        array $columnasClave,
        array $columnasDescripcion
    ): ?string {
        $clave = trim((string)$clave);
        if ($clave === '' || !$this->schema->tableExists($tabla)) {
            return null;
        }

        $colClave = $this->schema->pickColumn($tabla, $columnasClave);
        $colDescripcion = $this->schema->pickColumn($tabla, $columnasDescripcion);
        if (!$colClave || !$colDescripcion) {
            return null;
        }

        $sql = sprintf(
            'SELECT %s AS descripcion FROM %s WHERE %s = :clave LIMIT 1',
            $colDescripcion,
            $tabla,
            $colClave
        );

        $st = $this->conn->prepare($sql);
        $st->execute([':clave' => $clave]);
        $descripcion = $st->fetchColumn();
        if (!is_string($descripcion)) {
            return null;
        }

        $descripcion = trim($descripcion);
        return $descripcion !== '' ? $descripcion : null;
    }

    private function buildClaveDescripcionLabel(?string $clave, ?string $descripcion): string
    {
        $clave = trim((string)$clave);
        $descripcion = trim((string)$descripcion);

        if ($clave === '' && $descripcion === '') {
            return '—';
        }
        if ($clave !== '' && $descripcion !== '') {
            return $clave . ' - ' . $descripcion;
        }
        return $clave !== '' ? $clave : $descripcion;
    }

    private function buildInformacionGlobal(array $receptor): array
    {
        $aplica = $this->esPublicoGeneralReceptor($receptor);

        return [
            'aplica' => $aplica,
            'motivo' => $aplica
                ? 'El receptor capturado corresponde a público en general; revisa la información global antes de emitir el CFDI.'
                : 'No aplica información global para esta venta individual.',
        ];
    }

    private function buildFormaPago(array $venta, array $emisor, array $cfdiActual): array
    {
        $moneda = $this->firstNonEmpty([
            $cfdiActual['moneda'] ?? null,
            $venta['moneda'] ?? null,
            $emisor['moneda'] ?? null,
            'MXN',
        ]) ?: 'MXN';

        $monedaMeta = $this->obtenerMetaMoneda($moneda);
        $tipoCambio = $this->firstNonEmpty([
            $cfdiActual['tipo_cambio'] ?? null,
            $venta['tipo_cambio'] ?? null,
        ]);


        return [
            'moneda' => $moneda,
            'metodo_pago' => $this->firstNonEmpty([
                $cfdiActual['metodo_pago'] ?? null,
                $venta['metodo_pago'] ?? null,
                ((string)($venta['estatus'] ?? '') === 'Credito') ? 'PPD' : null,
                'PUE',
            ]),
            'forma_pago' => $this->firstNonEmpty([
                $cfdiActual['forma_pago'] ?? null,
                $venta['forma_pago_sat'] ?? null,
            ]),
            'forma_pago_descripcion' => $this->firstNonEmpty([
                $cfdiActual['forma_pago_descripcion'] ?? null,
                $venta['forma_pago'] ?? null,
            ]),
            'condiciones_pago' => $this->firstNonEmpty([
                $cfdiActual['condiciones_pago'] ?? null,
                $venta['condiciones_pago'] ?? null,
            ]),
            'tipo_cambio' => $tipoCambio,
            'tipo_comprobante' => $this->firstNonEmpty([
                $cfdiActual['tipo_comprobante'] ?? null,
                $emisor['tipo_comprobante'] ?? null,
                'I',
            ]),
            'exportacion' => $this->firstNonEmpty([
                $cfdiActual['exportacion'] ?? null,
                $emisor['exportacion'] ?? null,
                '01',
            ]),
            'fecha' => $this->firstNonEmpty([
                $cfdiActual['fecha_emision'] ?? null,
                $venta['fecha'] ?? null,
            ]),
            'moneda_descripcion' => $monedaMeta['Descripcion'] ?? null,
            'permite_tipo_cambio' => (int)($monedaMeta['PermiteTipoCambio'] ?? (($moneda === 'MXN' || $moneda === 'XXX') ? 0 : 1)),
            'decimales_moneda' => isset($monedaMeta['Decimales']) ? (int)$monedaMeta['Decimales'] : 2,
            'tipo_cambio_requerido' => !in_array($moneda, ['MXN', 'XXX'], true),
        ];
    }

    private function buildConceptos(array $detalles): array
    {
        $out = [];
        foreach ($detalles as $detalle) {
            $cantidad = (float)($detalle['cantidad'] ?? 0);
            $valorUnitarioBruto = (float)($detalle['precio_unitario'] ?? 0);
            $subtotalComercialOrigen = (float)($detalle['subtotal'] ?? ($cantidad * $valorUnitarioBruto));
            $descuento = (float)($detalle['descuento'] ?? 0);
            $objetoImp = $this->normalizeObjetoImp($detalle['objeto_imp'] ?? $detalle['producto_objeto_imp'] ?? '02');
            $tasaIva = (float)($detalle['tasa_iva'] ?? $detalle['producto_tasa_iva'] ?? 0);
            $usaBaseFiscalIva = ($objetoImp === '02' && $tasaIva > 0);
            $base = $subtotalComercialOrigen;
            $impuesto = 0.0;

            if ($usaBaseFiscalIva) {
                $base = $this->valorNumericoDetalle($detalle, 'base_iva')
                    ?? round($subtotalComercialOrigen / (1 + $tasaIva), 2);
                $impuesto = $this->valorNumericoDetalle($detalle, 'importe_iva')
                    ?? round($subtotalComercialOrigen - $base, 2);
            }

            $valorUnitario = $cantidad > 0 ? round($base / $cantidad, 2) : $base;
            $importe = $base;

            $concepto = [
                'Cantidad' => $this->n($cantidad),
                'ClaveProdServ' => $detalle['clave_prod_serv_sat'] ?? null,
                'ClaveUnidad' => $detalle['clave_unidad_sat'] ?? null,
                'Descripcion' => $detalle['descripcion'] ?? $detalle['producto_descripcion'] ?? null,
                'Descuento' => $descuento > 0 ? $this->n($descuento) : null,
                'Importe' => $this->n($importe),
                'NoIdentificacion' => $detalle['producto_codigo'] ?? null,
                'ObjetoImp' => $objetoImp,
                'Unidad' => $detalle['unidad_sat_descripcion'] ?? null,
                'ValorUnitario' => $this->n($valorUnitario),
            ];

            if ($usaBaseFiscalIva) {
                $concepto['Traslados'] = [[
                    'Base' => $this->n($base),
                    'Impuesto' => '002',
                    'TipoFactor' => 'Tasa',
                    'TasaOCuota' => number_format($tasaIva, 6, '.', ''),
                    'Importe' => $this->n($impuesto),
                ]];
            }

            error_log('[FACTURACION][buildConceptos] concepto=' . json_encode([
                'id_producto' => (int)($detalle['id_producto'] ?? 0),
                'campos_detalle_origen' => [
                    'subtotal' => $detalle['subtotal'] ?? null,
                    'base_iva' => $detalle['base_iva'] ?? null,
                    'importe_iva' => $detalle['importe_iva'] ?? null,
                    'objeto_imp' => $detalle['objeto_imp'] ?? null,
                    'tasa_iva' => $detalle['tasa_iva'] ?? null,
                ],
                'cantidad' => $cantidad,
                'precio_unitario_bruto' => $valorUnitarioBruto,
                'subtotal_comercial_origen' => $subtotalComercialOrigen,
                'objeto_imp' => $objetoImp,
                'tasa_iva' => $tasaIva,
                'valor_unitario_base' => $valorUnitario,
                'subtotal_fiscal_base' => $base,
                'impuestos_calculados' => $impuesto,
                'total_final' => $subtotalComercialOrigen,
                'usa_base_fiscal_iva' => $usaBaseFiscalIva,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $out[] = array_filter($concepto, fn($v) => $v !== null && $v !== '');
        }

        return $out;
    }

    private function buildFacturaDraft(int $idVenta, array $venta, array $emisor, array $receptor, array $formaPago, array $conceptos, array $totales, array $catalogos, array $detalles): array
    {
        return [
            'venta' => [
                'id_venta' => $idVenta,
                'folio' => $venta['folio'] ?? null,
                'fecha' => $venta['fecha'] ?? null,
                'referencia_interna' => $venta['folio'] ?? null,
                'conceptos' => $conceptos,
                'detalle_origen' => $detalles,
                'subtotal' => $totales['subtotal'] ?? 0,
                'descuento' => $totales['descuento'] ?? 0,
                'impuestos' => $totales['impuestos'] ?? 0,
                'total' => $totales['total'] ?? 0,
            ],
            'emisor' => [
                'rfc' => $emisor['rfc'] ?? '',
                'nombre' => $emisor['nombre'] ?? '',
                'regimen_fiscal' => $emisor['regimen_fiscal'] ?? '',
                'lugar_expedicion' => $emisor['lugar_expedicion'] ?? '',
                'serie' => $emisor['serie'] ?? '',
                'sucursal' => $emisor['sucursal'] ?? '',
            ],
            'receptor' => [
                'id_cliente_fiscal' => (int)($receptor['cliente_sat_id'] ?? 0),
                'rfc' => $receptor['rfc'] ?? '',
                'nombre' => $receptor['nombre'] ?? '',
                'nombre_comercial' => $receptor['nombre_comercial'] ?? '',
                'correo' => $receptor['correo'] ?? '',
                'codigo_postal' => $receptor['domicilio_fiscal_receptor'] ?? '',
                'regimen_fiscal' => $receptor['regimen_fiscal_receptor'] ?? '',
                'uso_cfdi' => $receptor['uso_cfdi'] ?? '',
                'residencia_fiscal' => $receptor['residencia_fiscal'] ?? '',
                'numero_registro_tributario' => $receptor['num_reg_id_trib'] ?? '',
                'es_publico_general' => !empty($receptor['es_publico_general']),
            ],
            'comprobante' => [
                'moneda' => $formaPago['moneda'] ?? 'MXN',
                'metodo_pago' => $formaPago['metodo_pago'] ?? '',
                'forma_pago' => $formaPago['forma_pago'] ?? '',
                'tipo_cambio' => $formaPago['tipo_cambio'] ?? '',
                'exportacion' => $formaPago['exportacion'] ?? '01',
                'tipo_comprobante' => $formaPago['tipo_comprobante'] ?? 'I',
                'condiciones_pago' => $formaPago['condiciones_pago'] ?? '',
            ],
            'catalogos' => $catalogos,
            'validaciones' => [
                'receptorCompleto' => false,
                'comprobanteCompleto' => false,
                'conceptosValidos' => false,
                'emisorValido' => false,
                'ventaValida' => false,
                'bloques' => [],
                'listaErrores' => [],
            ],
            'listoParaTimbrar' => false,
        ];
    }

    private function buildTotales(array $venta, array $conceptos): array
    {
        $subtotal = 0.0;
        $descuento = (float)($venta['descuento_factura'] ?? ($venta['descuento'] ?? 0));
        $impuestos = 0.0;

        foreach ($conceptos as $concepto) {
            $subtotal += (float)($concepto['Importe'] ?? 0);
            foreach (($concepto['Traslados'] ?? []) as $traslado) {
                $impuestos += (float)($traslado['Importe'] ?? 0);
            }
            foreach (($concepto['Retenciones'] ?? []) as $retencion) {
                $impuestos -= (float)($retencion['Importe'] ?? 0);
            }
        }

        $totalVenta = (float)($venta['total'] ?? 0);
        $totalCalculado = max(0, ($subtotal - $descuento) + $impuestos);
        $totalFinal = $totalVenta > 0 ? round($totalVenta, 2) : round($totalCalculado, 2);

        error_log('[FACTURACION][buildTotales] ' . json_encode([
            'subtotal_fiscal_base' => round($subtotal, 2),
            'descuento' => round($descuento, 2),
            'impuestos_calculados' => round($impuestos, 2),
            'total_final' => $totalFinal,
            'total_venta_origen' => round($totalVenta, 2),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'subtotal' => round($subtotal, 2),
            'descuento' => $descuento,
            'impuestos' => round($impuestos, 2),
            'total' => $totalFinal,
            'importe_letra' => null,
        ];
    }

    private function resolveSubtotalVenta(array $venta, int $idVenta): float
    {
        foreach (['subtotal', 'sub_total'] as $col) {
            if (isset($venta[$col]) && $venta[$col] !== null && $venta[$col] !== '') {
                return (float)$venta[$col];
            }
        }

        $st = $this->conn->prepare('SELECT COALESCE(SUM(subtotal),0) FROM ventas_detalle WHERE id_venta = :id AND (activo = 1 OR activo IS NULL)');
        $st->execute([':id' => $idVenta]);
        return (float)$st->fetchColumn();
    }

    private function pkCfdi(): string
    {
        return $this->schema->pickColumn('ventas_cfdi', ['id_cfdi', 'id_venta_cfdi'], true);
    }

    private function orderByCfdi(): string
    {
        $pk = $this->pkCfdi();
        return $pk . ' DESC';
    }

    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }
        return null;
    }

    private function n($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    private function normalizeObjetoImp($value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '02';
        }

        if (ctype_digit($raw) && strlen($raw) === 1) {
            return '0' . $raw;
        }

        return $raw;
    }

    private function valorNumericoDetalle(array $detalle, string $campo): ?float
    {
        if (!array_key_exists($campo, $detalle) || $detalle[$campo] === null || $detalle[$campo] === '') {
            return null;
        }
        if (!is_numeric((string)$detalle[$campo])) {
            return null;
        }
        return (float)$detalle[$campo];
    }


    private function listarRegimenesFiscales(): array
    {
        $st = $this->conn->query("SELECT ClaveRegimenFiscal, Descripcion FROM cat_sat_regimen_fiscal WHERE Activo = 1 ORDER BY ClaveRegimenFiscal ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function listarUsosCfdi(): array
    {
        $st = $this->conn->query("SELECT ClaveUsoCFDI, Descripcion FROM cat_sat_uso_cfdi WHERE Activo = 1 ORDER BY ClaveUsoCFDI ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    private function listarMonedas(): array
    {
        if (!$this->schema->tableExists('cat_sat_moneda')) {
            return [];
        }

        $columns = ['ClaveMoneda', 'Descripcion', 'Activo'];
        if ($this->schema->hasColumn('cat_sat_moneda', 'Decimales')) {
            $columns[] = 'Decimales';
        }
        if ($this->schema->hasColumn('cat_sat_moneda', 'PermiteTipoCambio')) {
            $columns[] = 'PermiteTipoCambio';
        }

        $sql = sprintf(
            'SELECT %s FROM cat_sat_moneda WHERE Activo = 1 ORDER BY ClaveMoneda ASC',
            implode(', ', $columns)
        );

        $st = $this->conn->query($sql);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->mapCatalogoConEtiqueta($rows, 'ClaveMoneda', 'Descripcion', [
            'Decimales' => 2,
            'PermiteTipoCambio' => static function (array $row): int {
                $clave = strtoupper(trim((string)($row['ClaveMoneda'] ?? '')));
                return in_array($clave, ['MXN', 'XXX'], true) ? 0 : 1;
            },
        ]);
    }

    private function listarFormasPagoSat(): array
    {
        if (!$this->schema->tableExists('formas_pago')) {
            return [];
        }

        $st = $this->conn->query("SELECT id_forma_pago, clave_sat, descripcion, activo FROM formas_pago WHERE activo = 1 AND COALESCE(clave_sat, '') <> '' ORDER BY clave_sat ASC");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->mapCatalogoConEtiqueta($rows, 'clave_sat', 'descripcion');
    }

    private function mapCatalogoConEtiqueta(array $rows, string $valueKey, string $descriptionKey, array $defaults = []): array
    {
        return array_map(static function (array $row) use ($valueKey, $descriptionKey, $defaults): array {
            $value = trim((string)($row[$valueKey] ?? ''));
            $description = trim((string)($row[$descriptionKey] ?? ''));
            $row['label'] = $value !== ''
                ? $value . ($description !== '' ? ' - ' . $description : '')
                : $description;

            foreach ($defaults as $field => $defaultValue) {
                if (!array_key_exists($field, $row)) {
                    $row[$field] = is_callable($defaultValue) ? $defaultValue($row) : $defaultValue;
                }
            }

            return $row;
        }, $rows);
    }

    private function listarMetodosPago(): array
    {
        return [
            ['clave' => 'PUE', 'descripcion' => 'Pago en una sola exhibición'],
            ['clave' => 'PPD', 'descripcion' => 'Pago en parcialidades o diferido'],
        ];
    }

    private function listarTiposComprobante(): array
    {
        return [
            ['clave' => 'I', 'descripcion' => 'Ingreso'],
        ];
    }

    private function listarExportaciones(): array
    {
        return [
            ['clave' => '01', 'descripcion' => 'No aplica'],
            ['clave' => '02', 'descripcion' => 'Definitiva'],
            ['clave' => '03', 'descripcion' => 'Temporal'],
        ];
    }

    private function obtenerMetaMoneda(string $claveMoneda): array
    {
        foreach ($this->listarMonedas() as $moneda) {
            if (strcasecmp((string)($moneda['ClaveMoneda'] ?? ''), $claveMoneda) === 0) {
                return $moneda;
            }
        }
        return [];
    }

    private function normalizarDatosComprobante(array $data, array $venta): array
    {
        $moneda = strtoupper(trim((string)($data['moneda'] ?? '')));
        $metodoPago = strtoupper(trim((string)($data['metodo_pago'] ?? '')));
        $formaPago = strtoupper(trim((string)($data['forma_pago'] ?? '')));
        $tipoComprobante = strtoupper(trim((string)($data['tipo_comprobante'] ?? '')));
        $exportacion = trim((string)($data['exportacion'] ?? ''));
        $condiciones = trim((string)($data['condiciones_pago'] ?? ''));
        $tipoCambio = trim((string)($data['tipo_cambio'] ?? ''));

        error_log('[FACTURACION][tipo_cambio][normalizar-comprobante] ' . json_encode([
            'moneda' => $moneda,
            'tipo_cambio_recibido' => $data['tipo_cambio'] ?? null,
            'tipo_cambio_normalizado' => $tipoCambio,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'moneda' => $moneda,
            'metodo_pago' => $metodoPago,
            'forma_pago' => $formaPago,
            'condiciones_pago' => $condiciones,
            'tipo_cambio' => $tipoCambio,
            'tipo_comprobante' => $tipoComprobante,
            'exportacion' => $exportacion,
        ];
    }

    private function normalizarComprobanteInput(array $comprobante): array
    {
        return [
            'moneda' => strtoupper(trim((string)($comprobante['moneda'] ?? ''))),
            'metodo_pago' => strtoupper(trim((string)($comprobante['metodo_pago'] ?? ''))),
            'forma_pago' => strtoupper(trim((string)($comprobante['forma_pago'] ?? ''))),
            'condiciones_pago' => trim((string)($comprobante['condiciones_pago'] ?? '')),
            'tipo_cambio' => trim((string)($comprobante['tipo_cambio'] ?? '')),
            'tipo_comprobante' => strtoupper(trim((string)($comprobante['tipo_comprobante'] ?? ''))),
            'exportacion' => trim((string)($comprobante['exportacion'] ?? '')),
        ];
    }

    private function guardarComprobanteVenta(int $idVenta, array $data, array $venta, array $receptor): void
    {
        if (!$this->schema->tableExists('ventas_cfdi')) {
            return;
        }

        $comprobante = $this->normalizarDatosComprobante($data, $venta);
        $payload = $this->schema->filterData('ventas_cfdi', $comprobante);
        $existing = $this->getCfdiByVenta($idVenta);
        $draftFromModal = is_array($data['draft'] ?? null) ? $data['draft'] : [];
        $requestPayload = $this->mergeDraftIntoRequestPayload($existing['request_payload'] ?? null, $receptor, $comprobante, $draftFromModal);
        if ($this->schema->hasColumn('ventas_cfdi', 'request_payload')) {
            $payload['request_payload'] = $requestPayload;
        }
        if ($this->schema->hasColumn('ventas_cfdi', 'mensaje_respuesta')) {
            $payload['mensaje_respuesta'] = 'Datos del comprobante y receptor capturados desde el modal de facturación.';
        }

        if ($existing) {
            if ($this->schema->hasColumn('ventas_cfdi', 'estatus') && empty($existing['uuid'])) {
                $payload['estatus'] = 'BORRADOR';
            }
            $this->updateCfdiRecord((int)$existing[$this->pkCfdi()], $payload);
            return;
        }

        $this->createOrGetCfdiRecord($idVenta, $payload + [
            'estatus' => 'BORRADOR',
        ]);
    }

    private function mapClienteFacturacion(array $row): array
    {
        $idCliente = (int)($row['id'] ?? $row['cliente_sat_id'] ?? 0);
        $razonSocial = $this->firstNonEmpty([
            $row['razon_social'] ?? null,
        ]);
        $rfc = strtoupper((string)$this->firstNonEmpty([
            $row['rfc'] ?? null,
            $row['cs_rfc'] ?? null,
        ]));
        $correo = $this->firstNonEmpty([
            $row['email'] ?? null,
            $row['cs_email'] ?? null,
            $row['email_alterno'] ?? null,
        ]);
        $usoCfdi = $this->firstNonEmpty([
            $row['uso_cdfi'] ?? null,
        ]);
        $regimen = $this->firstNonEmpty([
            $row['regimen_fiscal'] ?? null,
        ]);
        $nombreComercial = $this->firstNonEmpty([
            $row['nombre_comercial'] ?? null,
            $razonSocial,
        ]);

        $partes = array_filter([
            $razonSocial,
            $rfc !== '' ? $rfc : null,
            $correo,
        ], fn($value) => $value !== null && $value !== '');

        return [
            'id' => $idCliente,
            'id_cliente' => $idCliente,
            'id_cliente_sat' => $idCliente,
            'text' => implode(' · ', $partes) ?: ('Cliente SAT #' . $idCliente),
            'nombre' => $razonSocial,
            'nombre_comercial' => $nombreComercial,
            'rfc' => $rfc,
            'correo' => $correo,
            'domicilio_fiscal_receptor' => $row['dom_fiscal_cp'] ?? null,
            'regimen_fiscal_receptor' => $regimen,
            'regimen_fiscal_descripcion' => $row['regimen_fiscal_descripcion'] ?? null,
            'uso_cfdi' => $usoCfdi,
            'uso_cfdi_descripcion' => $row['uso_cfdi_descripcion'] ?? null,
            'residencia_fiscal' => $row['residencia_fiscal'] ?? null,
            'num_reg_id_trib' => $row['numero_registro_tributario'] ?? null,
        ];
    }

    private function esPublicoGeneralReceptor(array $receptor): bool
    {
        $rfc = strtoupper(trim((string)($receptor['rfc'] ?? '')));
        $nombre = strtoupper(trim((string)($receptor['nombre'] ?? '')));

        if ($rfc === 'XAXX010101000') {
            return true;
        }

        return strpos($nombre, 'PUBLICO') !== false || strpos($nombre, 'MOSTRADOR') !== false;
    }

    private function normalizarDatosFiscales(array $data): array
    {
        $idClienteSat = (int)($data['id_cliente_fiscal'] ?? ($data['id_cliente_sat'] ?? ($data['id_cliente'] ?? 0)));
        $payload = [
            'nombre' => trim((string)($data['razon_social'] ?? $data['nombre'] ?? '')),
            'nombre_comercial' => trim((string)($data['nombre_comercial'] ?? '')),
            'rfc' => strtoupper(trim((string)($data['rfc'] ?? ''))),
            'correo' => trim((string)($data['email'] ?? $data['correo'] ?? '')),
            'domicilio_fiscal_receptor' => trim((string)($data['dom_fiscal_cp'] ?? $data['codigo_postal'] ?? '')),
            'regimen_fiscal_receptor' => trim((string)($data['regimen_fiscal'] ?? '')),
            'uso_cfdi' => trim((string)($data['uso_cfdi'] ?? $data['uso_cdfi'] ?? '')),
            'num_reg_id_trib' => trim((string)($data['numero_registro_tributario'] ?? '')),
            'residencia_fiscal' => strtoupper(trim((string)($data['residencia_fiscal'] ?? ''))),
            'cliente_sat_id' => $idClienteSat > 0 ? $idClienteSat : null,
        ];
        $payload['es_publico_general'] = $this->esPublicoGeneralReceptor($payload);

        return $payload;
    }

    private function normalizarReceptorInput(array $receptor): array
    {
        $idClienteSat = (int)($receptor['id_cliente_sat'] ?? $receptor['id_cliente_fiscal'] ?? 0);

        $payload = [
            'nombre' => trim((string)($receptor['nombre'] ?? '')),
            'nombre_comercial' => trim((string)($receptor['nombre_comercial'] ?? '')),
            'rfc' => strtoupper(trim((string)($receptor['rfc'] ?? ''))),
            'correo' => trim((string)($receptor['correo'] ?? '')),
            'domicilio_fiscal_receptor' => trim((string)($receptor['domicilio_fiscal_receptor'] ?? '')),
            'regimen_fiscal_receptor' => trim((string)($receptor['regimen_fiscal'] ?? '')),
            'uso_cfdi' => trim((string)($receptor['uso_cfdi'] ?? '')),
            'num_reg_id_trib' => trim((string)($receptor['numero_registro_tributario'] ?? '')),
            'residencia_fiscal' => strtoupper(trim((string)($receptor['residencia_fiscal'] ?? ''))),
            'cliente_sat_id' => $idClienteSat > 0 ? $idClienteSat : null,
        ];
        $payload['es_publico_general'] = $this->esPublicoGeneralReceptor($payload);
        return $payload;
    }

    private function buildReceptorFromClienteSat(array $cliente): array
    {
        $idClienteSat = (int)($cliente['id'] ?? $cliente['id_cliente_sat'] ?? $cliente['id_cliente'] ?? 0);
        $nombre = trim((string)($cliente['nombre'] ?? $cliente['razon_social'] ?? ''));

        $receptor = [
            'nombre' => $nombre,
            'nombre_comercial' => trim((string)($cliente['nombre_comercial'] ?? '')),
            'rfc' => strtoupper(trim((string)($cliente['rfc'] ?? ''))),
            'correo' => trim((string)($cliente['correo'] ?? $cliente['email'] ?? '')),
            'domicilio_fiscal_receptor' => trim((string)($cliente['domicilio_fiscal_receptor'] ?? $cliente['dom_fiscal_cp'] ?? '')),
            'regimen_fiscal_receptor' => trim((string)($cliente['regimen_fiscal_receptor'] ?? $cliente['regimen_fiscal'] ?? '')),
            'uso_cfdi' => trim((string)($cliente['uso_cfdi'] ?? $cliente['uso_cdfi'] ?? '')),
            'num_reg_id_trib' => trim((string)($cliente['num_reg_id_trib'] ?? $cliente['numero_registro_tributario'] ?? '')),
            'residencia_fiscal' => strtoupper(trim((string)($cliente['residencia_fiscal'] ?? ''))),
            'cliente_id' => 0,
            'cliente_sat_id' => $idClienteSat,
            'direccion' => null,
            'telefono' => null,
        ];
        $receptor['es_publico_general'] = $this->esPublicoGeneralReceptor($receptor);

        return $receptor;
    }

    private function getDraftFacturacion(array $cfdiActual): array
    {
        $payload = $this->decodeJsonObject($cfdiActual['request_payload'] ?? null);
        $draft = is_array($payload['draft'] ?? null) ? $payload['draft'] : [];
        if (!is_array($draft)) {
            $draft = [];
        }

        return [
            'receptor' => is_array($draft['receptor'] ?? null) ? $draft['receptor'] : [],
            'comprobante' => is_array($draft['comprobante'] ?? null) ? $draft['comprobante'] : [],
        ];
    }

    private function getReceptorFromFacturacionData(array $draft, array $cfdiActual): array
    {
        $receptor = is_array($draft['receptor'] ?? null) ? $draft['receptor'] : [];
        if ($receptor) {
            $receptor['es_publico_general'] = $this->esPublicoGeneralReceptor($receptor);
            return $receptor;
        }

        $payload = $this->decodeJsonObject($cfdiActual['request_payload'] ?? null);
        $fromPayload = is_array($payload['Receptor'] ?? null) ? $payload['Receptor'] : [];
        if (!$fromPayload) {
            return [
                'nombre' => '',
                'nombre_comercial' => '',
                'rfc' => '',
                'domicilio_fiscal_receptor' => '',
                'regimen_fiscal_receptor' => '',
                'uso_cfdi' => '',
                'num_reg_id_trib' => '',
                'residencia_fiscal' => '',
                'direccion' => null,
                'telefono' => null,
                'correo' => '',
                'cliente_id' => 0,
                'cliente_sat_id' => 0,
                'es_publico_general' => false,
            ];
        }

        $receptor = [
            'nombre' => (string)($fromPayload['Nombre'] ?? ''),
            'nombre_comercial' => '',
            'rfc' => (string)($fromPayload['Rfc'] ?? ''),
            'domicilio_fiscal_receptor' => (string)($fromPayload['DomicilioFiscalReceptor'] ?? ''),
            'regimen_fiscal_receptor' => (string)($fromPayload['RegimenFiscalReceptor'] ?? ''),
            'uso_cfdi' => (string)($fromPayload['UsoCFDI'] ?? ''),
            'num_reg_id_trib' => (string)($fromPayload['NumRegIDTrib'] ?? ''),
            'residencia_fiscal' => (string)($fromPayload['ResidenciaFiscal'] ?? ''),
            'direccion' => null,
            'telefono' => null,
            'correo' => '',
            'cliente_id' => 0,
            'cliente_sat_id' => 0,
        ];
        $receptor['es_publico_general'] = $this->esPublicoGeneralReceptor($receptor);

        return $receptor;
    }

    private function mergeDraftIntoRequestPayload(?string $existingPayload, array $receptor, array $comprobante, array $draftFromModal = []): string
    {
        $payload = $this->decodeJsonObject($existingPayload);
        $draft = is_array($draftFromModal) ? $draftFromModal : [];
        $draft['receptor'] = array_merge(is_array($draft['receptor'] ?? null) ? $draft['receptor'] : [], $receptor);
        $draft['comprobante'] = array_merge(is_array($draft['comprobante'] ?? null) ? $draft['comprobante'] : [], $comprobante);
        $draft['updated_at'] = date('c');
        $draft['source'] = 'modal_facturacion';
        $payload['draft'] = $draft;

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function decodeJsonObject($json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
