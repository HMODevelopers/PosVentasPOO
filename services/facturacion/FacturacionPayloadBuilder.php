<?php

class FacturacionPayloadBuilder
{
    public function build(array $ctx, array $soapConfig): array
    {
        $venta = $ctx['venta'];
        $emisor = $ctx['emisor'];
        $receptor = $ctx['receptor'];
        $conceptos = $this->normalizeConceptos($ctx['conceptos'] ?? []);
        $formaPago = $ctx['forma_pago'] ?? [];
        $informacionGlobal = $this->normalizeInformacionGlobal($ctx['informacion_global'] ?? []);
        $cfdiRelacionados = $this->normalizeCfdiRelacionados($ctx['cfdi_relacionados'] ?? []);

        $subTotal = $this->n($ctx['totales']['subtotal'] ?? 0);
        $descuento = $this->nullableAmount($ctx['totales']['descuento'] ?? 0);
        $total = $this->n($ctx['totales']['total'] ?? 0);
        $fecha = $ctx['fecha_emision'] ?? ($formaPago['fecha'] ?? date('Y-m-d\TH:i:s'));
        $moneda = strtoupper((string)($formaPago['moneda'] ?? 'MXN'));
        $tipoCambio = $formaPago['tipo_cambio'] ?? null;
        if ($moneda === 'MXN') {
            $tipoCambio = '1';
        } elseif ($moneda === 'XXX') {
            $tipoCambio = null;
        }

        $payload = [
            'Credenciales' => [
                'Usuario' => $soapConfig['usuario'],
                'Cuenta' => $soapConfig['cuenta'],
                'Password' => $soapConfig['password'],
            ],
            'Emisor' => array_filter([
                'Nombre' => $emisor['nombre'],
                'RegimenFiscal' => $emisor['regimen_fiscal'],
                'FacAtrAdquirente' => $emisor['fac_atr_adquirente'] ?? null,
            ], fn($v) => $v !== null && $v !== ''),
            'Receptor' => array_filter([
                'DomicilioFiscalReceptor' => $receptor['domicilio_fiscal_receptor'],
                'Nombre' => $receptor['nombre'],
                'NumRegIDTrib' => $receptor['num_reg_id_trib'] ?? null,
                'RegimenFiscalReceptor' => $receptor['regimen_fiscal_receptor'],
                'ResidenciaFiscal' => $receptor['residencia_fiscal'] ?? null,
                'Rfc' => $receptor['rfc'],
                'UsoCFDI' => $receptor['uso_cfdi'],
            ], fn($v) => $v !== null && $v !== ''),
            'Conceptos' => $conceptos,
            'Comprobante40R' => array_filter([
                'ClaveCFDI' => 'FAC',
                'CondicionesDePago' => $formaPago['condiciones_pago'] ?? ($venta['condiciones_pago'] ?? null),
                'Exportacion' => $formaPago['exportacion'] ?? '01',
                'Fecha' => $fecha,
                'Folio' => $venta['folio'] ?? null,
                'FormaDePago' => (string)($formaPago['forma_pago'] ?? $venta['forma_pago_sat']),
                'LugarExpedicion' => (string)$emisor['lugar_expedicion'],
                'MetodoDePago' => (string)($formaPago['metodo_pago'] ?? 'PUE'),
                'Moneda' => $moneda,
                'Referencia' => $ctx['referencia'],
                'SubTotal' => $subTotal,
                'TipoCambio' => $tipoCambio,
                'Total' => $total,
                'Confirmacion' => null,
                'Descuento' => $descuento,
            ], fn($v) => $v !== null && $v !== ''),
        ];

        if (!empty($informacionGlobal)) {
            $payload['InformacionGlobal'] = $informacionGlobal;
        }
        if (!empty($cfdiRelacionados)) {
            $payload['CfdiRelacionados40R'] = $cfdiRelacionados;
        }

        return $payload;
    }

    private function n($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    private function nullableAmount($value): ?string
    {
        return ((float)$value > 0) ? $this->n($value) : null;
    }

    private function normalizeConceptos(array $conceptos): array
    {
        $normalized = [];
        foreach ($conceptos as $concepto) {
            if (!is_array($concepto)) {
                continue;
            }

            if (isset($concepto['Traslados']) && !isset($concepto['TrasladoConcepto40R'])) {
                $concepto['TrasladoConcepto40R'] = $concepto['Traslados'];
            }
            if (isset($concepto['Retenciones']) && !isset($concepto['RetencionConcepto40R'])) {
                $concepto['RetencionConcepto40R'] = $concepto['Retenciones'];
            }
            if (isset($concepto['Descripción']) && !isset($concepto['Descripcion'])) {
                $concepto['Descripcion'] = $concepto['Descripción'];
            }
            if (isset($concepto['NoIdentificación']) && !isset($concepto['NoIdentificacion'])) {
                $concepto['NoIdentificacion'] = $concepto['NoIdentificación'];
            }
            if (isset($concepto['TrasladoConcepto']) && !isset($concepto['TrasladoConcepto40R'])) {
                $concepto['TrasladoConcepto40R'] = $concepto['TrasladoConcepto'];
            }
            if (isset($concepto['RetencionConcepto']) && !isset($concepto['RetencionConcepto40R'])) {
                $concepto['RetencionConcepto40R'] = $concepto['RetencionConcepto'];
            }
            if (isset($concepto['RetencionLocal']) && !isset($concepto['RetencionLocal40R'])) {
                $concepto['RetencionLocal40R'] = $concepto['RetencionLocal'];
            }
            if (isset($concepto['TrasladoLocal']) && !isset($concepto['TrasladoLocal40R'])) {
                $concepto['TrasladoLocal40R'] = $concepto['TrasladoLocal'];
            }
            unset($concepto['Traslados'], $concepto['Retenciones']);

            $normalized[] = $concepto;
        }
        return $normalized;
    }

    private function normalizeInformacionGlobal(array $info): array
    {
        $aplica = !empty($info['aplica']);
        if (!$aplica) {
            return [];
        }

        return array_filter([
            'Año' => $info['Año'] ?? $info['Anio'] ?? $info['anio'] ?? null,
            'Meses' => $info['Meses'] ?? $info['meses'] ?? null,
            'Periodicidad' => $info['Periodicidad'] ?? $info['periodicidad'] ?? null,
        ], fn($v) => $v !== null && $v !== '');
    }

    private function normalizeCfdiRelacionados(array $rel): array
    {
        if (!$rel) {
            return [];
        }

        $tipoRelacion = $rel['TipoRelacion'] ?? $rel['tipo_relacion'] ?? null;
        $relacionados = $rel['CfdiRelacionado40R'] ?? $rel['cfdi_relacionados'] ?? [];
        $cfdis = [];
        foreach ((array)$relacionados as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cfdis[] = array_filter([
                'UUID' => $item['UUID'] ?? $item['uuid'] ?? null,
            ], fn($v) => $v !== null && $v !== '');
        }

        return array_filter([
            'TipoRelacion' => $tipoRelacion,
            'CfdiRelacionado40R' => $cfdis,
        ], function ($v) {
            if (is_array($v)) {
                return !empty($v);
            }
            return $v !== null && $v !== '';
        });
    }
}
