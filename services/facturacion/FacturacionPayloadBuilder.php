<?php

class FacturacionPayloadBuilder
{
    public function build(array $ctx, array $soapConfig): array
    {
        $venta = $ctx['venta'];
        $emisor = $ctx['emisor'];
        $receptor = $ctx['receptor'];
        $conceptos = $ctx['conceptos'];

        $subTotal = $this->n($ctx['totales']['subtotal'] ?? 0);
        $descuento = $this->nullableAmount($ctx['totales']['descuento'] ?? 0);
        $total = $this->n($ctx['totales']['total'] ?? 0);
        $fecha = $ctx['fecha_emision'] ?? date('Y-m-d\TH:i:s');

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
                'CondicionesDePago' => $venta['condiciones_pago'] ?? null,
                'Exportacion' => '01',
                'Fecha' => $fecha,
                'Folio' => (string)$venta['folio'],
                'FormaDePago' => (string)$venta['forma_pago_sat'],
                'LugarExpedicion' => (string)$emisor['lugar_expedicion'],
                'MetodoDePago' => 'PUE',
                'Moneda' => 'MXN',
                'Referencia' => $ctx['referencia'],
                'SubTotal' => $subTotal,
                'TipoCambio' => null,
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
