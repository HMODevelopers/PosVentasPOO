<?php

class FacturacionValidator
{
    public function validate(array $ctx): array
    {
        $errors = [];
        $venta = $ctx['venta'] ?? [];
        $receptor = $ctx['receptor'] ?? [];
        $emisor = $ctx['emisor'] ?? [];
        $conceptos = $ctx['conceptos'] ?? [];

        if (!$venta) {
            $errors[] = 'La venta no existe.';
        }

        if (empty($ctx['detalles'])) {
            $errors[] = 'La venta no tiene detalle para facturar.';
        }

        if (!in_array((string)($venta['estatus'] ?? ''), ['Activa', 'Credito'], true)) {
            $errors[] = 'El estatus de la venta no es facturable.';
        }

        if (strtoupper((string)($ctx['cfdi_actual']['estatus'] ?? '')) === 'TIMBRADO') {
            $errors[] = 'La venta ya cuenta con un CFDI timbrado.';
        }

        foreach ([
            'rfc' => 'RFC del receptor',
            'nombre' => 'Nombre o razón social del receptor',
            'domicilio_fiscal_receptor' => 'Código postal del receptor',
            'regimen_fiscal_receptor' => 'Régimen fiscal del receptor',
            'uso_cfdi' => 'Uso CFDI del receptor',
        ] as $key => $label) {
            if (empty($receptor[$key])) {
                $errors[] = "Falta {$label}.";
            }
        }

        foreach ([
            'nombre' => 'Nombre o razón social del emisor',
            'regimen_fiscal' => 'Régimen fiscal del emisor',
            'lugar_expedicion' => 'Código postal de expedición del emisor',
        ] as $key => $label) {
            if (empty($emisor[$key])) {
                $errors[] = "Falta {$label}.";
            }
        }

        if (empty($venta['forma_pago_sat'])) {
            $errors[] = 'La venta no tiene una forma de pago SAT válida.';
        }

        if (!$conceptos) {
            $errors[] = 'No se pudieron construir los conceptos del CFDI.';
        }

        foreach ($conceptos as $index => $concepto) {
            $linea = $index + 1;
            foreach ([
                'Cantidad' => 'cantidad',
                'ClaveProdServ' => 'clave prod/serv SAT',
                'ClaveUnidad' => 'clave unidad SAT',
                'Descripcion' => 'descripción',
                'Importe' => 'importe',
                'ObjetoImp' => 'objeto de impuesto',
                'ValorUnitario' => 'valor unitario',
            ] as $field => $label) {
                if (!isset($concepto[$field]) || $concepto[$field] === null || $concepto[$field] === '') {
                    $errors[] = "Falta {$label} en el concepto {$linea}.";
                }
            }
        }

        return $errors;
    }
}
