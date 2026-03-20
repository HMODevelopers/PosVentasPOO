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
        $receptor = $this->getReceptorByVenta($venta ?: []);
        $conceptos = $this->buildConceptos($detalles);

        return [
            'venta' => $venta,
            'detalles' => $detalles,
            'cfdi_actual' => $cfdiActual,
            'emisor' => $emisor,
            'receptor' => $receptor,
            'conceptos' => $conceptos,
            'totales' => [
                'subtotal' => $venta['subtotal_factura'] ?? ($venta['subtotal'] ?? $venta['total'] ?? 0),
                'descuento' => $venta['descuento_factura'] ?? ($venta['descuento'] ?? 0),
                'total' => $venta['total'] ?? 0,
            ],
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

    private function getVenta(int $idVenta): ?array
    {
        $clienteNombre = $this->schema->pickColumn('clientes', ['nombre', 'razon_social', 'nombre_fiscal'], true);
        $clienteRfc = $this->schema->pickColumn('clientes', ['rfc']);
        $clienteUsoCfdi = $this->schema->pickColumn('clientes', ['uso_cfdi', 'uso_cdfi']);
        $clienteRegimen = $this->schema->pickColumn('clientes', ['regimen_fiscal']);
        $clienteCp = $this->schema->pickColumn('clientes', ['codigo_postal', 'cp', 'dom_fiscal_cp']);
        $clienteDireccion = $this->schema->pickColumn('clientes', ['direccion']);
        $clienteTelefono = $this->schema->pickColumn('clientes', ['telefono']);

        $satSelect = '';
        $satJoin = '';
        if ($this->schema->tableExists('clientes_sat')) {
            $satSelect = ", cs.razon_social AS cs_razon_social, cs.regimen_fiscal AS cs_regimen_fiscal, cs.dom_fiscal_cp AS cs_dom_fiscal_cp,
                cs.uso_cdfi AS cs_uso_cfdi, cs.numero_registro_tributario AS cs_num_reg_id_trib, cs.residencia_fiscal AS cs_residencia_fiscal";
            $satJoin = ' LEFT JOIN clientes_sat cs ON cs.rfc = c.rfc ';
        }

        $sql = "SELECT
                v.*,
                COALESCE(fp.clave_sat, '') AS forma_pago_sat,
                COALESCE(fp.descripcion, '') AS forma_pago,
                c.{$clienteNombre} AS cliente_nombre" .
                ($clienteRfc ? ", c.{$clienteRfc} AS cliente_rfc" : '') .
                ($clienteUsoCfdi ? ", c.{$clienteUsoCfdi} AS cliente_uso_cfdi" : '') .
                ($clienteRegimen ? ", c.{$clienteRegimen} AS cliente_regimen_fiscal" : '') .
                ($clienteCp ? ", c.{$clienteCp} AS cliente_codigo_postal" : '') .
                ($clienteDireccion ? ", c.{$clienteDireccion} AS cliente_direccion" : '') .
                ($clienteTelefono ? ", c.{$clienteTelefono} AS cliente_telefono" : '') .
                ", cj.id_sucursal AS id_sucursal{$satSelect}
            FROM ventas v
            LEFT JOIN clientes c ON c.id_cliente = v.id_cliente
            LEFT JOIN formas_pago fp ON fp.id_forma_pago = v.id_forma_pago
            INNER JOIN cajas cj ON cj.id_caja = v.id_caja
            {$satJoin}
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

    private function getEmisorByVenta(int $idVenta): array
    {
        $sql = "SELECT cfe.*
                FROM ventas v
                INNER JOIN cajas c ON c.id_caja = v.id_caja
                INNER JOIN config_fiscal_emisor cfe ON cfe.id_sucursal = c.id_sucursal AND cfe.activo = 1
                WHERE v.id_venta = :id
                ORDER BY cfe.id_config DESC
                LIMIT 1";
        $st = $this->conn->prepare($sql);
        $st->execute([':id' => $idVenta]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'nombre' => $this->schema->rowValue($row, ['razon_social_emisor', 'nombre', 'razon_social']),
            'regimen_fiscal' => $this->schema->rowValue($row, ['regimen_fiscal_emisor', 'regimen_fiscal']),
            'lugar_expedicion' => $this->schema->rowValue($row, ['cp_expedicion', 'codigo_postal', 'lugar_expedicion']),
            'fac_atr_adquirente' => $this->schema->rowValue($row, ['fac_atr_adquirente']),
            'raw' => $row,
        ];
    }

    private function getReceptorByVenta(array $venta): array
    {
        $nombre = $this->firstNonEmpty([
            $venta['cliente_nombre'] ?? null,
            $venta['cs_razon_social'] ?? null,
        ]);
        $regimen = $this->firstNonEmpty([
            $venta['cliente_regimen_fiscal'] ?? null,
            $venta['cs_regimen_fiscal'] ?? null,
        ]);
        $cp = $this->firstNonEmpty([
            $venta['cliente_codigo_postal'] ?? null,
            $venta['cs_dom_fiscal_cp'] ?? null,
        ]);
        $usoCfdi = $this->firstNonEmpty([
            $venta['cliente_uso_cfdi'] ?? null,
            $venta['cs_uso_cfdi'] ?? null,
        ]);

        return [
            'nombre' => $nombre,
            'rfc' => $venta['cliente_rfc'] ?? null,
            'domicilio_fiscal_receptor' => $cp,
            'regimen_fiscal_receptor' => $regimen,
            'uso_cfdi' => $usoCfdi,
            'num_reg_id_trib' => $venta['cs_num_reg_id_trib'] ?? null,
            'residencia_fiscal' => $venta['cs_residencia_fiscal'] ?? null,
            'direccion' => $venta['cliente_direccion'] ?? null,
            'telefono' => $venta['cliente_telefono'] ?? null,
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
}
