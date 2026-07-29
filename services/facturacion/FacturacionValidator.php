<?php

class FacturacionValidator
{
    public function validate(array $ctx): array
    {
        $report = $this->validateDetailed($ctx);
        return $report['listaErrores'];
    }

    public function validateDetailed(array $ctx): array
    {
        $venta = $ctx['venta'] ?? [];
        $receptor = $ctx['receptor'] ?? [];
        $emisor = $ctx['emisor'] ?? [];
        $conceptos = $ctx['conceptos'] ?? [];
        $formaPago = $ctx['forma_pago'] ?? [];
        $catalogos = $ctx['catalogos'] ?? [];

        $erroresVenta = [];
        $erroresReceptor = [];
        $erroresEmisor = [];
        $erroresComprobante = [];
        $erroresConceptos = [];

        if (!$venta) {
            $erroresVenta[] = 'La venta no existe.';
        }

        if (empty($ctx['detalles'])) {
            $erroresVenta[] = 'La venta no tiene detalle para facturar.';
        }

        if (!in_array((string)($venta['estatus'] ?? ''), ['Activa', 'Credito'], true)) {
            $erroresVenta[] = 'El estatus de la venta no es facturable.';
        }

        if (strtoupper((string)($ctx['cfdi_actual']['estatus'] ?? '')) === 'TIMBRADO') {
            $erroresVenta[] = 'La venta ya cuenta con un CFDI timbrado.';
        }

        foreach ([
            'rfc' => 'RFC del receptor',
            'nombre' => 'Nombre o razón social del receptor',
            'domicilio_fiscal_receptor' => 'Código postal del receptor',
            'regimen_fiscal_receptor' => 'Régimen fiscal del receptor',
            'uso_cfdi' => 'Uso CFDI del receptor',
        ] as $key => $label) {
            if (empty($receptor[$key])) {
                $erroresReceptor[] = "Falta {$label}.";
            }
        }

        foreach ([
            'rfc' => 'RFC del emisor',
            'nombre' => 'Nombre o razón social del emisor',
            'regimen_fiscal' => 'Régimen fiscal del emisor',
            'lugar_expedicion' => 'Código postal de expedición del emisor',
        ] as $key => $label) {
            if (empty($emisor[$key])) {
                $erroresEmisor[] = "Falta {$label}.";
            }
        }

        $moneda = strtoupper(trim((string)($formaPago['moneda'] ?? '')));
        $metodoPago = strtoupper(trim((string)($formaPago['metodo_pago'] ?? '')));
        $formaPagoSat = strtoupper(trim((string)($formaPago['forma_pago'] ?? '')));
        $tipoComprobante = strtoupper(trim((string)($formaPago['tipo_comprobante'] ?? '')));
        $exportacion = trim((string)($formaPago['exportacion'] ?? ''));
        $tipoCambio = trim((string)($formaPago['tipo_cambio'] ?? ''));

        if ($moneda === '') {
            $erroresComprobante[] = 'Debes seleccionar una moneda válida.';
        }

        $monedasValidas = array_map(
            fn(array $row) => strtoupper(trim((string)($row['ClaveMoneda'] ?? ''))),
            is_array($catalogos['monedas'] ?? null) ? $catalogos['monedas'] : []
        );
        if ($moneda !== '' && $monedasValidas && !in_array($moneda, $monedasValidas, true)) {
            $erroresComprobante[] = 'La moneda seleccionada no existe en el catálogo SAT disponible.';
        }

        $metodosValidos = array_map(
            fn(array $row) => strtoupper(trim((string)($row['clave'] ?? ''))),
            is_array($catalogos['metodos_pago'] ?? null) ? $catalogos['metodos_pago'] : []
        );
        if ($metodoPago === '') {
            $erroresComprobante[] = 'Debes seleccionar un método de pago.';
        } elseif ($metodosValidos && !in_array($metodoPago, $metodosValidos, true)) {
            $erroresComprobante[] = 'El método de pago seleccionado no es válido.';
        }

        $formasValidas = array_map(
            fn(array $row) => strtoupper(trim((string)($row['clave_sat'] ?? ''))),
            is_array($catalogos['formas_pago'] ?? null) ? $catalogos['formas_pago'] : []
        );
        if ($formaPagoSat === '') {
            $erroresComprobante[] = 'Debes seleccionar una forma de pago SAT.';
        } elseif ($formasValidas && !in_array($formaPagoSat, $formasValidas, true)) {
            $erroresComprobante[] = 'La forma de pago seleccionada no es válida.';
        }

        if ($moneda === 'MXN') {
            if ($tipoCambio === '' || (float)$tipoCambio !== 1.0) {
                $erroresComprobante[] = 'Para moneda MXN el tipo de cambio debe ser 1.';
            }
        } elseif ($moneda === 'XXX') {
            if ($tipoCambio !== '') {
                $erroresComprobante[] = 'Para moneda XXX no debe enviarse tipo de cambio.';
            }
        } elseif ($moneda !== '') {
            if ($tipoCambio === '') {
                $erroresComprobante[] = 'Para moneda distinta de MXN/XXX el tipo de cambio es obligatorio.';
            } elseif (!is_numeric($tipoCambio) || (float)$tipoCambio <= 0) {
                $erroresComprobante[] = 'El tipo de cambio debe ser un número mayor a 0.';
            }
        }

        if ($tipoComprobante === '') {
            $erroresComprobante[] = 'Debes seleccionar el tipo de comprobante.';
        } elseif ($tipoComprobante !== 'I') {
            $erroresComprobante[] = 'Este flujo solo soporta tipo de comprobante I (Ingreso).';
        }

        $exportacionesValidas = array_map(
            fn(array $row) => trim((string)($row['clave'] ?? '')),
            is_array($catalogos['exportaciones'] ?? null) ? $catalogos['exportaciones'] : []
        );
        if ($exportacion === '') {
            $erroresComprobante[] = 'Debes seleccionar la clave de exportación.';
        } elseif ($exportacionesValidas && !in_array($exportacion, $exportacionesValidas, true)) {
            $erroresComprobante[] = 'La clave de exportación seleccionada no es válida.';
        }

        if (count((array)($venta['tickets_ids'] ?? [])) > 1) {
            $subtotal = (float)($ctx['totales']['subtotal'] ?? 0);
            $descuento = (float)($ctx['totales']['descuento'] ?? 0);
            $traslados = (float)($ctx['totales']['impuestos'] ?? 0);
            $retenciones = 0.0;
            foreach ($conceptos as $concepto) {
                foreach ((array)($concepto['Retenciones'] ?? []) as $retencion) {
                    $retenciones += (float)($retencion['Importe'] ?? 0);
                }
            }
            $totalEsperado = round(
                $subtotal
                - $descuento
                + $traslados
                - $retenciones,
                2
            );
            $totalEnviar = round((float)($ctx['totales']['total'] ?? 0), 2);
            if (abs($totalEsperado - $totalEnviar) > 0.001) {
                $erroresComprobante[] = 'El Total del CFDI múltiple no coincide con el total fiscal esperado. No se enviará al PAC.';
            }
        }

        if (!$conceptos) {
            $erroresConceptos[] = 'No se pudieron construir los conceptos del CFDI.';
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
                    $erroresConceptos[] = "Falta {$label} en el concepto {$linea}.";
                }
            }
        }

        $listaErrores = array_values(array_merge(
            $erroresVenta,
            $erroresReceptor,
            $erroresEmisor,
            $erroresComprobante,
            $erroresConceptos
        ));

        return [
            'ventaValida' => empty($erroresVenta),
            'receptorCompleto' => empty($erroresReceptor),
            'emisorValido' => empty($erroresEmisor),
            'comprobanteCompleto' => empty($erroresComprobante),
            'conceptosValidos' => empty($erroresConceptos),
            'bloques' => [
                'venta' => [
                    'completo' => empty($erroresVenta),
                    'errores' => array_values($erroresVenta),
                ],
                'receptor' => [
                    'completo' => empty($erroresReceptor),
                    'errores' => array_values($erroresReceptor),
                ],
                'emisor' => [
                    'completo' => empty($erroresEmisor),
                    'errores' => array_values($erroresEmisor),
                ],
                'comprobante' => [
                    'completo' => empty($erroresComprobante),
                    'errores' => array_values($erroresComprobante),
                ],
                'conceptos' => [
                    'completo' => empty($erroresConceptos),
                    'errores' => array_values($erroresConceptos),
                ],
            ],
            'listaErrores' => $listaErrores,
            'listoParaTimbrar' => empty($listaErrores),
        ];
    }
}
