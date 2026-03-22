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
        $receptor = $this->getReceptorByVenta($venta ?: [], $emisor);
        $clienteSeleccionado = !empty($receptor['cliente_sat_id'])
            ? $this->obtenerClienteFacturacion((int)$receptor['cliente_sat_id'])
            : null;
        $conceptos = $this->buildConceptos($detalles);
        $formaPago = $this->buildFormaPago($venta ?: [], $emisor, $cfdiActual ?: []);
        $totales = $this->buildTotales($venta ?: [], $conceptos);

        return [
            'venta' => $venta,
            'detalles' => $detalles,
            'cfdi_actual' => $cfdiActual,
            'emisor' => $emisor,
            'receptor' => $receptor,
            'cliente_seleccionado' => $clienteSeleccionado,
            'informacion_global' => $this->buildInformacionGlobal($venta ?: [], $receptor),
            'catalogos' => [
                'regimenes_fiscales' => $this->listarRegimenesFiscales(),
                'usos_cfdi' => $this->listarUsosCfdi(),
                'monedas' => $this->listarMonedas(),
                'formas_pago' => $this->listarFormasPagoSat(),
                'metodos_pago' => $this->listarMetodosPago(),
                'tipos_comprobante' => $this->listarTiposComprobante(),
                'exportaciones' => $this->listarExportaciones(),
            ],
            'forma_pago' => $formaPago,
            'conceptos' => $conceptos,
            'totales' => $totales,
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
            'estatus' => 'PENDIENTE',
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

        $idCliente = (int)($venta['id_cliente'] ?? 0);
        $idClienteSeleccionado = (int)($data['id_cliente'] ?? 0);
        if ($idClienteSeleccionado > 0 && $idClienteSeleccionado !== $idCliente) {
            $this->actualizarClienteVenta($idVenta, $idClienteSeleccionado);
            $venta = $this->getVenta($idVenta);
            $idCliente = (int)($venta['id_cliente'] ?? 0);
        }

        if ($idCliente <= 0) {
            return [
                'guardado' => false,
                'msg' => 'La venta no tiene un cliente SAT vinculado. Selecciona un receptor válido desde clientes_sat antes de guardar.',
            ];
        }

        $payload = $this->normalizarDatosFiscales($data, $venta);
        $this->conn->beginTransaction();

        try {
            $this->actualizarClienteBase($idCliente, $payload);
            $this->upsertClienteSat($idCliente, $payload, $venta);
            $this->guardarComprobanteVenta($idVenta, $data, $venta);
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
                cs.id AS cliente_sat_id,
                cs.nombre_comercial AS cs_nombre_comercial,
                cs.rfc AS cs_rfc,
                cs.razon_social AS cs_razon_social,
                cs.regimen_fiscal AS cs_regimen_fiscal,
                cs.numero_registro_tributario AS cs_num_reg_id_trib,
                cs.uso_cdfi AS cs_uso_cfdi,
                cs.email AS cs_email,
                cs.email_alterno AS cs_email_alterno,
                cs.dom_fiscal_cp AS cs_dom_fiscal_cp,
                cs.estado AS cs_estado,
                cs.municipio AS cs_municipio,
                cs.localidad AS cs_localidad,
                cs.colonia AS cs_colonia,
                cs.calle AS cs_calle,
                cs.numero_exterior AS cs_numero_exterior,
                cs.numero_interior AS cs_numero_interior,
                cs.referencia AS cs_referencia,
                cs.residencia_fiscal AS cs_residencia_fiscal,
                cj.id_sucursal AS id_sucursal
            FROM ventas v
            LEFT JOIN clientes c ON c.id_cliente = v.id_cliente
            LEFT JOIN formas_pago fp ON fp.id_forma_pago = v.id_forma_pago
            INNER JOIN cajas cj ON cj.id_caja = v.id_caja
            LEFT JOIN sucursales s ON s.id_sucursal = cj.id_sucursal
            LEFT JOIN clientes_sat cs ON cs.id = v.id_cliente
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
                LEFT JOIN sucursales s ON s.id_sucursal = c.id_sucursal
                INNER JOIN config_fiscal_emisor cfe ON cfe.id_sucursal = c.id_sucursal AND cfe.activo = 1
                WHERE v.id_venta = :id
                ORDER BY cfe.id_config DESC
                LIMIT 1";
        $st = $this->conn->prepare($sql);
        $st->execute([':id' => $idVenta]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'rfc' => $this->schema->rowValue($row, ['rfc_emisor', 'rfc']),
            'nombre' => $this->schema->rowValue($row, ['razon_social_emisor', 'nombre', 'razon_social']),
            'sucursal' => $this->schema->rowValue($row, ['sucursal_nombre', 'nombre_sucursal']),
            'regimen_fiscal' => $this->schema->rowValue($row, ['regimen_fiscal_emisor', 'regimen_fiscal']),
            'lugar_expedicion' => $this->schema->rowValue($row, ['cp_expedicion', 'codigo_postal', 'lugar_expedicion']),
            'serie' => $this->schema->rowValue($row, ['serie']),
            'folio_actual' => $this->schema->rowValue($row, ['folio_actual']),
            'tipo_comprobante' => $this->schema->rowValue($row, ['tipo_comprobante'], 'I'),
            'exportacion' => $this->schema->rowValue($row, ['exportacion_default'], '01'),
            'moneda' => $this->schema->rowValue($row, ['moneda_default'], 'MXN'),
            'objeto_imp_default' => $this->schema->rowValue($row, ['objeto_imp_default'], '02'),
            'fac_atr_adquirente' => $this->schema->rowValue($row, ['fac_atr_adquirente']),
            'raw' => $row,
        ];
    }

    private function getReceptorByVenta(array $venta, array $emisor = []): array
    {
        $esPublicoGeneral = $this->esPublicoGeneral($venta);
        $rfc = $this->firstNonEmpty([
            $venta['cs_rfc'] ?? null,
        ]);
        $nombre = $this->firstNonEmpty([
            $venta['cs_razon_social'] ?? null,
        ]);
        $regimen = $this->firstNonEmpty([
            $venta['cs_regimen_fiscal'] ?? null,
        ]);
        $cp = $this->firstNonEmpty([
            $venta['cs_dom_fiscal_cp'] ?? null,
        ]);
        $usoCfdi = $this->firstNonEmpty([
            $venta['cs_uso_cfdi'] ?? null,
        ]);

        return [
            'nombre' => $nombre,
            'nombre_comercial' => $venta['cs_nombre_comercial'] ?? null,
            'rfc' => $rfc,
            'domicilio_fiscal_receptor' => $cp,
            'regimen_fiscal_receptor' => $regimen,
            'uso_cfdi' => $usoCfdi,
            'num_reg_id_trib' => $venta['cs_num_reg_id_trib'] ?? null,
            'residencia_fiscal' => $venta['cs_residencia_fiscal'] ?? null,
            'direccion' => $venta['cliente_direccion'] ?? null,
            'telefono' => $venta['cliente_telefono'] ?? null,
            'correo' => $this->firstNonEmpty([
                $venta['cs_email'] ?? null,
                $venta['cs_email_alterno'] ?? null,
            ]),
            'cliente_id' => (int)($venta['cliente_id'] ?? 0),
            'cliente_sat_id' => (int)($venta['cliente_sat_id'] ?? 0),
            'es_publico_general' => $esPublicoGeneral,
        ];
    }

    private function buildInformacionGlobal(array $venta, array $receptor): array
    {
        $aplica = (bool)($receptor['es_publico_general'] ?? false);

        return [
            'aplica' => $aplica,
            'motivo' => $aplica
                ? 'La venta se identificó como público en general; revisa los datos fiscales del receptor antes de emitir el CFDI.'
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

        if ($moneda === 'MXN') {
            $tipoCambio = '1';
        }
        if ($moneda === 'XXX') {
            $tipoCambio = null;
        }

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
            $valorUnitario = (float)($detalle['precio_unitario'] ?? 0);
            $importe = (float)($detalle['subtotal'] ?? ($cantidad * $valorUnitario));
            $descuento = (float)($detalle['descuento'] ?? 0);
            $objetoImp = (string)($detalle['objeto_imp'] ?? $detalle['producto_objeto_imp'] ?? '02');
            $tasaIva = (float)($detalle['tasa_iva'] ?? $detalle['producto_tasa_iva'] ?? 0);

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

            if ($objetoImp === '02' && $tasaIva > 0) {
                $base = isset($detalle['base_iva']) ? (float)$detalle['base_iva'] : $importe;
                $impuesto = isset($detalle['importe_iva']) ? (float)$detalle['importe_iva'] : round($base * $tasaIva, 2);
                $concepto['Traslados'] = [[
                    'Base' => $this->n($base),
                    'Impuesto' => '002',
                    'TipoFactor' => 'Tasa',
                    'TasaOCuota' => number_format($tasaIva, 6, '.', ''),
                    'Importe' => $this->n($impuesto),
                ]];
            }

            $out[] = array_filter($concepto, fn($v) => $v !== null && $v !== '');
        }

        return $out;
    }

    private function buildTotales(array $venta, array $conceptos): array
    {
        $subtotal = (float)($venta['subtotal_factura'] ?? ($venta['subtotal'] ?? $venta['total'] ?? 0));
        $descuento = (float)($venta['descuento_factura'] ?? ($venta['descuento'] ?? 0));
        $impuestos = 0.0;

        foreach ($conceptos as $concepto) {
            foreach (($concepto['Traslados'] ?? []) as $traslado) {
                $impuestos += (float)($traslado['Importe'] ?? 0);
            }
            foreach (($concepto['Retenciones'] ?? []) as $retencion) {
                $impuestos -= (float)($retencion['Importe'] ?? 0);
            }
        }

        return [
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'impuestos' => round($impuestos, 2),
            'total' => (float)($venta['total'] ?? max(0, ($subtotal - $descuento) + $impuestos)),
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
            return [
                ['ClaveMoneda' => 'MXN', 'Descripcion' => 'Peso Mexicano', 'Decimales' => 2, 'PermiteTipoCambio' => 0],
                ['ClaveMoneda' => 'USD', 'Descripcion' => 'Dólar estadounidense', 'Decimales' => 2, 'PermiteTipoCambio' => 1],
                ['ClaveMoneda' => 'XXX', 'Descripcion' => 'Sin moneda', 'Decimales' => 2, 'PermiteTipoCambio' => 0],
            ];
        }

        $st = $this->conn->query("SELECT ClaveMoneda, Descripcion, Decimales, PermiteTipoCambio FROM cat_sat_moneda WHERE Activo = 1 ORDER BY ClaveMoneda ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function listarFormasPagoSat(): array
    {
        if (!$this->schema->tableExists('formas_pago')) {
            return [];
        }

        $st = $this->conn->query("SELECT clave_sat, descripcion FROM formas_pago WHERE activo = 1 AND COALESCE(clave_sat, '') <> '' ORDER BY clave_sat ASC, descripcion ASC");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        $moneda = strtoupper(trim((string)($data['moneda'] ?? 'MXN')));
        $metodoPago = strtoupper(trim((string)($data['metodo_pago'] ?? (((string)($venta['estatus'] ?? '') === 'Credito') ? 'PPD' : 'PUE'))));
        $formaPago = strtoupper(trim((string)($data['forma_pago'] ?? ($venta['forma_pago_sat'] ?? ''))));
        $tipoComprobante = strtoupper(trim((string)($data['tipo_comprobante'] ?? 'I')));
        $exportacion = trim((string)($data['exportacion'] ?? '01'));
        $condiciones = trim((string)($data['condiciones_pago'] ?? ''));
        $tipoCambio = trim((string)($data['tipo_cambio'] ?? ''));

        if ($moneda === 'MXN') {
            $tipoCambio = '1';
        } elseif ($moneda === 'XXX') {
            $tipoCambio = '';
        }

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

    private function guardarComprobanteVenta(int $idVenta, array $data, array $venta): void
    {
        if (!$this->schema->tableExists('ventas_cfdi')) {
            return;
        }

        $payload = $this->schema->filterData('ventas_cfdi', $this->normalizarDatosComprobante($data, $venta));
        if (!$payload) {
            return;
        }

        $existing = $this->getCfdiByVenta($idVenta);
        if ($existing) {
            $this->updateCfdiRecord((int)$existing[$this->pkCfdi()], $payload);
            return;
        }

        $this->createOrGetCfdiRecord($idVenta, $payload + [
            'estatus' => 'BORRADOR',
            'mensaje_respuesta' => 'Datos del comprobante capturados desde el modal de facturación.',
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

    private function esPublicoGeneral(array $venta): bool
    {
        $rfc = strtoupper(trim((string)($venta['cs_rfc'] ?? $venta['cliente_rfc'] ?? '')));
        $nombre = strtoupper(trim((string)($venta['cs_razon_social'] ?? $venta['cliente_nombre'] ?? '')));

        if ($rfc === 'XAXX010101000') {
            return true;
        }

        if (strpos($nombre, 'PUBLICO') !== false || strpos($nombre, 'MOSTRADOR') !== false) {
            return true;
        }

        return false;
    }

    private function normalizarDatosFiscales(array $data, array $venta): array
    {
        $payload = [
            'nombre_comercial' => trim((string)($data['nombre_comercial'] ?? '')),
            'razon_social' => trim((string)($data['razon_social'] ?? $data['nombre'] ?? '')),
            'rfc' => strtoupper(trim((string)($data['rfc'] ?? ''))),
            'email' => trim((string)($data['email'] ?? $data['correo'] ?? '')),
            'dom_fiscal_cp' => trim((string)($data['dom_fiscal_cp'] ?? $data['codigo_postal'] ?? '')),
            'regimen_fiscal' => trim((string)($data['regimen_fiscal'] ?? '')),
            'uso_cdfi' => trim((string)($data['uso_cfdi'] ?? $data['uso_cdfi'] ?? '')),
            'numero_registro_tributario' => trim((string)($data['numero_registro_tributario'] ?? '')),
            'residencia_fiscal' => trim((string)($data['residencia_fiscal'] ?? '')),
        ];

        return $payload;
    }

    private function actualizarClienteBase(int $idCliente, array $payload): void
    {
        $sql = "UPDATE clientes
                SET nombre = :nombre,
                    rfc = :rfc,
                    correo = :correo,
                    uso_cfdi = :uso_cfdi
                WHERE id_cliente = :id_cliente";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':nombre' => $payload['razon_social'],
            ':rfc' => $payload['rfc'] !== '' ? $payload['rfc'] : null,
            ':correo' => $payload['email'] !== '' ? $payload['email'] : null,
            ':uso_cfdi' => $payload['uso_cdfi'] !== '' ? $payload['uso_cdfi'] : null,
            ':id_cliente' => $idCliente,
        ]);
    }

    private function actualizarClienteVenta(int $idVenta, int $idCliente): void
    {
        $st = $this->conn->prepare("UPDATE ventas SET id_cliente = :id_cliente WHERE id_venta = :id_venta");
        $st->execute([
            ':id_cliente' => $idCliente,
            ':id_venta' => $idVenta,
        ]);
    }

    private function upsertClienteSat(int $idCliente, array $payload, array $venta): void
    {
        $base = [
            ':id' => $idCliente,
            ':nombre_comercial' => $payload['nombre_comercial'] !== '' ? $payload['nombre_comercial'] : ($venta['cliente_nombre'] ?: $payload['razon_social']),
            ':rfc' => $payload['rfc'] !== '' ? $payload['rfc'] : null,
            ':razon_social' => $payload['razon_social'] !== '' ? $payload['razon_social'] : null,
            ':regimen_fiscal' => $payload['regimen_fiscal'] !== '' ? $payload['regimen_fiscal'] : null,
            ':numero_registro_tributario' => $payload['numero_registro_tributario'] !== '' ? $payload['numero_registro_tributario'] : null,
            ':uso_cdfi' => $payload['uso_cdfi'] !== '' ? $payload['uso_cdfi'] : null,
            ':telefono' => $venta['cliente_telefono'] ?: null,
            ':celular' => null,
            ':email' => $payload['email'] !== '' ? $payload['email'] : null,
            ':email_alterno' => null,
            ':pais' => 'MEX',
            ':dom_fiscal_cp' => $payload['dom_fiscal_cp'] !== '' ? $payload['dom_fiscal_cp'] : null,
            ':estado' => $venta['cs_estado'] ?: null,
            ':municipio' => $venta['cs_municipio'] ?: null,
            ':localidad' => $venta['cs_localidad'] ?: null,
            ':colonia' => $venta['cs_colonia'] ?: null,
            ':calle' => $venta['cs_calle'] ?: null,
            ':numero_exterior' => $venta['cs_numero_exterior'] ?: null,
            ':numero_interior' => $venta['cs_numero_interior'] ?: null,
            ':referencia' => $venta['cs_referencia'] ?: null,
            ':residencia_fiscal' => $payload['residencia_fiscal'] !== '' ? $payload['residencia_fiscal'] : null,
        ];

        $exists = $this->conn->prepare("SELECT id FROM clientes_sat WHERE id = :id LIMIT 1");
        $exists->execute([':id' => $idCliente]);

        if ($exists->fetch(PDO::FETCH_ASSOC)) {
            $sql = "UPDATE clientes_sat
                    SET nombre_comercial = :nombre_comercial,
                        rfc = :rfc,
                        razon_social = :razon_social,
                        regimen_fiscal = :regimen_fiscal,
                        numero_registro_tributario = :numero_registro_tributario,
                        uso_cdfi = :uso_cdfi,
                        telefono = :telefono,
                        email = :email,
                        pais = :pais,
                        dom_fiscal_cp = :dom_fiscal_cp,
                        estado = :estado,
                        municipio = :municipio,
                        localidad = :localidad,
                        colonia = :colonia,
                        calle = :calle,
                        numero_exterior = :numero_exterior,
                        numero_interior = :numero_interior,
                        referencia = :referencia,
                        residencia_fiscal = :residencia_fiscal
                    WHERE id = :id";
        } else {
            $sql = "INSERT INTO clientes_sat (
                        id, nombre_comercial, rfc, razon_social, regimen_fiscal, numero_registro_tributario, uso_cdfi,
                        telefono, celular, email, email_alterno, pais, dom_fiscal_cp, estado, municipio, localidad,
                        colonia, calle, numero_exterior, numero_interior, referencia, residencia_fiscal
                    ) VALUES (
                        :id, :nombre_comercial, :rfc, :razon_social, :regimen_fiscal, :numero_registro_tributario, :uso_cdfi,
                        :telefono, :celular, :email, :email_alterno, :pais, :dom_fiscal_cp, :estado, :municipio, :localidad,
                        :colonia, :calle, :numero_exterior, :numero_interior, :referencia, :residencia_fiscal
                    )";
        }

        $st = $this->conn->prepare($sql);
        $st->execute($base);
    }
}
