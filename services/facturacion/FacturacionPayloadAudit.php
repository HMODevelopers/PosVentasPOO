<?php

class FacturacionPayloadAudit
{
    public function validate(array $payload): array
    {
        $report = [
            'missing' => [],
            'null' => [],
            'empty' => [],
            'empty_arrays' => [],
            'casing' => [],
            'unexpected' => [],
            'errors' => [],
        ];

        $this->validateAllowedKeys($payload, '', [
            'credenciales',
            'cfdi',
        ], $report);
        $this->validateRequiredObject(
            $payload,
            '',
            'credenciales',
            ['Usuario', 'Cuenta', 'Password'],
            ['Usuario', 'Cuenta', 'Password'],
            $report
        );
        $this->validateRequiredObject(
            $payload,
            '',
            'cfdi',
            ['ClaveCFDI', 'Exportacion', 'LugarExpedicion', 'Moneda', 'Referencia', 'SubTotal', 'Total'],
            [
                'ClaveCFDI',
                'CondicionesDePago',
                'Exportacion',
                'Fecha',
                'Folio',
                'FormaPago',
                'LugarExpedicion',
                'MetodoPago',
                'Moneda',
                'Referencia',
                'SubTotal',
                'TipoCambio',
                'Total',
                'Confirmacion',
                'Descuento',
                'Emisor',
                'Receptor',
                'Conceptos',
                'InformacionGlobal',
                'CfdiRelacionados',
            ],
            $report
        );

        if (!array_key_exists('cfdi', $payload) || !is_array($payload['cfdi'])) {
            return $this->buildFinalReport($report);
        }

        $cfdi = $payload['cfdi'];
        $this->validateRequiredObject(
            $cfdi,
            'cfdi',
            'Emisor',
            ['Nombre', 'RegimenFiscal'],
            ['Nombre', 'RegimenFiscal'],
            $report
        );
        $this->validateRequiredObject(
            $cfdi,
            'cfdi',
            'Receptor',
            ['DomicilioFiscalReceptor', 'Nombre', 'RegimenFiscalReceptor', 'Rfc', 'UsoCFDI'],
            ['DomicilioFiscalReceptor', 'Nombre', 'NumRegIDTrib', 'RegimenFiscalReceptor', 'ResidenciaFiscal', 'Rfc', 'UsoCFDI'],
            $report
        );

        if (!array_key_exists('Conceptos', $cfdi)) {
            $report['missing'][] = 'cfdi.Conceptos';
        } elseif (!is_array($cfdi['Conceptos'])) {
            $report['errors'][] = 'Conceptos debe ser un objeto/arreglo asociativo.';
        } else {
            $this->validateAllowedKeys($cfdi['Conceptos'], 'cfdi.Conceptos', ['Concepto40R'], $report);
            if (!array_key_exists('Concepto40R', $cfdi['Conceptos'])) {
                $report['missing'][] = 'cfdi.Conceptos.Concepto40R';
                $altConcepto = $this->findCaseInsensitiveKey($cfdi['Conceptos'], 'Concepto40R');
                if ($altConcepto !== null) {
                    $report['casing'][] = "cfdi.Conceptos.Concepto40R se encontró como '{$altConcepto}'.";
                }
                return $this->buildFinalReport($report);
            }
            if (!is_array($cfdi['Conceptos']['Concepto40R']) || count($cfdi['Conceptos']['Concepto40R']) === 0) {
                $report['empty_arrays'][] = 'cfdi.Conceptos.Concepto40R';
                return $this->buildFinalReport($report);
            }

            foreach ($cfdi['Conceptos']['Concepto40R'] as $i => $concepto) {
                $path = "cfdi.Conceptos[{$i}]";
                if (!is_array($concepto)) {
                    $report['errors'][] = "{$path} debe ser un objeto/arreglo asociativo.";
                    continue;
                }

                $this->validateAllowedKeys($concepto, $path, [
                    'Cantidad',
                    'ClaveProdServ',
                    'ClaveUnidad',
                    'Descripción',
                    'Descripcion',
                    'Descuento',
                    'Importe',
                    'NoIdentificacion',
                    'ObjetoImp',
                    'Unidad',
                    'ValorUnitario',
                    'Impuestos',
                ], $report);
                $this->validateFields($concepto, $path, [
                    'Cantidad',
                    'ClaveProdServ',
                    'ClaveUnidad',
                    'Importe',
                    'ObjetoImp',
                    'Unidad',
                    'ValorUnitario',
                ], $report);
                $this->validateAlternativeField($concepto, $path, ['Descripcion', 'Descripción'], 'Descripción/Descripcion', $report);

                $objetoImp = trim((string)($concepto['ObjetoImp'] ?? ''));
                if ($objetoImp === '02') {
                    $hayImpuestos = !empty($concepto['Impuestos']);
                    if (!$hayImpuestos) {
                        $report['errors'][] = "{$path}.ObjetoImp=02 requiere al menos un nodo de impuestos de concepto.";
                    }
                }

                $this->validateConceptoImpuestos($concepto, $path, $report);
            }
        }

        if (!empty($cfdi['InformacionGlobal'])) {
            if (!is_array($cfdi['InformacionGlobal'])) {
                $report['errors'][] = 'InformacionGlobal debe ser un objeto/arreglo asociativo.';
            } else {
                $this->validateAllowedKeys($cfdi['InformacionGlobal'], 'cfdi.InformacionGlobal', ['Anio', 'Año', 'Meses', 'Periodicidad'], $report);
                $this->validateAlternativeField($cfdi['InformacionGlobal'], 'cfdi.InformacionGlobal', ['Anio', 'Año'], 'Anio/Año', $report);
                $this->validateFields($cfdi['InformacionGlobal'], 'cfdi.InformacionGlobal', ['Meses', 'Periodicidad'], $report);
            }
        }

        if (!empty($cfdi['CfdiRelacionados'])) {
            if (!is_array($cfdi['CfdiRelacionados'])) {
                $report['errors'][] = 'CfdiRelacionados debe ser un objeto/arreglo asociativo.';
            } else {
                $this->validateAllowedKeys($cfdi['CfdiRelacionados'], 'cfdi.CfdiRelacionados', ['TipoRelacion', 'CfdiRelacionado40R'], $report);
                $this->validateFields($cfdi['CfdiRelacionados'], 'cfdi.CfdiRelacionados', ['TipoRelacion'], $report);
                if (!empty($cfdi['CfdiRelacionados']['CfdiRelacionado40R'])) {
                    foreach ((array)$cfdi['CfdiRelacionados']['CfdiRelacionado40R'] as $i => $rel) {
                        $itemPath = "cfdi.CfdiRelacionados.CfdiRelacionado40R[{$i}]";
                        if (!is_array($rel)) {
                            $report['errors'][] = "{$itemPath} debe ser un objeto/arreglo asociativo.";
                            continue;
                        }
                        $this->validateAllowedKeys($rel, $itemPath, ['UUID'], $report);
                        $this->validateFields($rel, $itemPath, ['UUID'], $report);
                    }
                }
            }
        }

        return $this->buildFinalReport($report);
    }

    private function validateConceptoImpuestos(array $concepto, string $path, array &$report): void
    {
        if (empty($concepto['Impuestos'])) {
            return;
        }

        if (!is_array($concepto['Impuestos'])) {
            $report['errors'][] = "{$path}.Impuestos debe ser un objeto/arreglo asociativo.";
            return;
        }

        $allowed = ['Traslados', 'Retenciones', 'RetencionesLocales', 'TrasladosLocales'];
        $this->validateAllowedKeys($concepto['Impuestos'], "{$path}.Impuestos", $allowed, $report);

        $traslados = $concepto['Impuestos']['Traslados'] ?? [];
        if (is_array($traslados)) {
            $this->validateOptionalNodeList($traslados, "{$path}.Impuestos", 'TrasladoConcepto40R', ['Base', 'Impuesto', 'TipoFactor'], ['Base', 'Importe', 'Impuesto', 'TasaOCuota', 'TipoFactor'], $report);
        }

        $retenciones = $concepto['Impuestos']['Retenciones'] ?? [];
        if (is_array($retenciones)) {
            $this->validateOptionalNodeList($retenciones, "{$path}.Impuestos", 'RetencionConcepto40R', ['Base', 'Importe', 'Impuesto', 'TasaOCuota', 'TipoFactor'], ['Base', 'Importe', 'Impuesto', 'TasaOCuota', 'TipoFactor'], $report);
        }
    }

    private function validateRequiredObject(array $root, string $path, string $node, array $requiredFields, array $allowedFields, array &$report): void
    {
        $nodePath = $path === '' ? $node : $path . '.' . $node;
        if (!array_key_exists($node, $root)) {
            $report['missing'][] = $nodePath;
            return;
        }
        if (!is_array($root[$node])) {
            $report['errors'][] = "{$nodePath} debe ser un objeto/arreglo asociativo.";
            return;
        }
        $this->validateAllowedKeys($root[$node], $nodePath, $allowedFields, $report);
        $this->validateFields($root[$node], $nodePath, $requiredFields, $report);
    }

    private function validateOptionalNodeList(
        array $root,
        string $path,
        string $node,
        array $requiredFields,
        array $allowedFields,
        array &$report
    ): void
    {
        if (empty($root[$node])) {
            return;
        }

        $items = $root[$node];
        if (!is_array($items)) {
            $report['errors'][] = "{$path}.{$node} debe ser lista de objetos.";
            return;
        }

        $isAssoc = $this->isAssoc($items);
        $itemsToValidate = $isAssoc ? [$items] : $items;
        if (!$itemsToValidate) {
            $report['empty_arrays'][] = "{$path}.{$node}";
            return;
        }

        foreach ($itemsToValidate as $idx => $item) {
            $itemPath = $path . '.' . $node . '[' . $idx . ']';
            if (!is_array($item)) {
                $report['errors'][] = "{$itemPath} debe ser objeto/arreglo asociativo.";
                continue;
            }
            $this->validateAllowedKeys($item, $itemPath, $allowedFields, $report);
            $this->validateFields($item, $itemPath, $requiredFields, $report);
        }
    }

    private function validateFields(array $node, string $path, array $fields, array &$report): void
    {
        foreach ($fields as $field) {
            $fullPath = $path . '.' . $field;
            if (!array_key_exists($field, $node)) {
                $report['missing'][] = $fullPath;
                $altKey = $this->findCaseInsensitiveKey($node, $field);
                if ($altKey !== null) {
                    $report['casing'][] = "{$fullPath} se encontró como '{$altKey}'.";
                }
                continue;
            }

            $value = $node[$field];
            if ($value === null) {
                $report['null'][] = $fullPath;
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                $report['empty'][] = $fullPath;
                continue;
            }

            if (is_array($value) && count($value) === 0) {
                $report['empty_arrays'][] = $fullPath;
            }
        }
    }

    private function validateAlternativeField(array $node, string $path, array $fields, string $label, array &$report): void
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $node)) {
                continue;
            }

            $value = $node[$field];
            if ($value === null) {
                $report['null'][] = "{$path}.{$label}";
                return;
            }
            if (is_string($value) && trim($value) === '') {
                $report['empty'][] = "{$path}.{$label}";
                return;
            }
            return;
        }

        $report['missing'][] = "{$path}.{$label}";
    }

    private function buildFinalReport(array $report): array
    {
        $report['missing'] = array_values(array_unique($report['missing']));
        $report['null'] = array_values(array_unique($report['null']));
        $report['empty'] = array_values(array_unique($report['empty']));
        $report['empty_arrays'] = array_values(array_unique($report['empty_arrays']));
        $report['casing'] = array_values(array_unique($report['casing']));

        if ($report['missing']) {
            $report['errors'][] = 'Campos obligatorios faltantes: ' . implode(', ', $report['missing']) . '.';
        }
        if ($report['null']) {
            $report['errors'][] = 'Campos obligatorios nulos: ' . implode(', ', $report['null']) . '.';
        }
        if ($report['empty']) {
            $report['errors'][] = 'Campos obligatorios vacíos: ' . implode(', ', $report['empty']) . '.';
        }
        if ($report['empty_arrays']) {
            $report['errors'][] = 'Arreglos obligatorios vacíos: ' . implode(', ', $report['empty_arrays']) . '.';
        }
        if ($report['casing']) {
            $report['errors'][] = 'Posibles errores de mayúsculas/minúsculas: ' . implode(' | ', $report['casing']) . '.';
        }
        if ($report['unexpected']) {
            $report['errors'][] = 'Llaves no permitidas por manual: ' . implode(', ', $report['unexpected']) . '.';
        }

        $report['errors'] = array_values(array_unique($report['errors']));
        $report['has_errors'] = !empty($report['errors']);

        return $report;
    }

    private function findCaseInsensitiveKey(array $node, string $expected): ?string
    {
        foreach (array_keys($node) as $key) {
            if (strcasecmp((string)$key, $expected) === 0 && $key !== $expected) {
                return (string)$key;
            }
        }
        return null;
    }

    private function validateAllowedKeys(array $node, string $path, array $allowed, array &$report): void
    {
        $allowedLookup = array_fill_keys($allowed, true);
        foreach (array_keys($node) as $key) {
            if (isset($allowedLookup[$key])) {
                continue;
            }

            $fullPath = $path === '' ? (string)$key : "{$path}.{$key}";
            $report['unexpected'][] = $fullPath;
            $expected = $this->findExpectedByCaseInsensitive($allowed, (string)$key);
            if ($expected !== null) {
                $report['casing'][] = "{$fullPath} debería ser '{$expected}'.";
            }
        }
    }

    private function findExpectedByCaseInsensitive(array $allowed, string $actual): ?string
    {
        foreach ($allowed as $expected) {
            if (strcasecmp($expected, $actual) === 0 && $expected !== $actual) {
                return $expected;
            }
        }
        return null;
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
