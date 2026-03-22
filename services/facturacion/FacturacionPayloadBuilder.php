<?php

class FacturacionPayloadBuilder
{
    public function build(array $ctx, array $soapConfig): array
    {
        $venta = $ctx['venta'];
        $emisor = $ctx['emisor'];
        $receptor = $ctx['receptor'];
        $conceptos = $ctx['conceptos'];
        $formaPago = $ctx['forma_pago'] ?? [];

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

        return [
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
            'Comprobante40R' => [
                'ClaveCFDI' => 'FAC',
                'CondicionesDePago' => $formaPago['condiciones_pago'] ?? ($venta['condiciones_pago'] ?? null),
                'Exportacion' => $formaPago['exportacion'] ?? '01',
                'Fecha' => $fecha,
                'Folio' => (string)$venta['folio'],
                'FormaDePago' => (string)($formaPago['forma_pago'] ?? $venta['forma_pago_sat']),
                'LugarExpedicion' => (string)$emisor['lugar_expedicion'],
                'MetodoDePago' => (string)($formaPago['metodo_pago'] ?? 'PUE'),
                'Moneda' => $moneda,
                'Referencia' => $ctx['referencia'],
                'SubTotal' => $subTotal,
                'TipoCambio' => $tipoCambio,
                'TipoDeComprobante' => (string)($formaPago['tipo_comprobante'] ?? 'I'),
                'Total' => $total,
                'Confirmacion' => null,
                'Descuento' => $descuento,
            ],
        ];
    }

    private function n($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    private function nullableAmount($value): ?string
    {
        return ((float)$value > 0) ? $this->n($value) : null;
    }
}
