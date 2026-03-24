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
            'errors' => [],
        ];

        $this->validateRequiredObject($payload, '', 'Credenciales', ['Usuario', 'Cuenta', 'Password'], $report);
        $this->validateRequiredObject($payload, '', 'Emisor', ['Nombre', 'RegimenFiscal'], $report);
        $this->validateRequiredObject($payload, '', 'Receptor', ['DomicilioFiscalReceptor', 'Nombre', 'RegimenFiscalReceptor', 'Rfc', 'UsoCFDI'], $report);
        $this->validateRequiredObject($payload, '', 'Comprobante40R', ['ClaveCFDI', 'Exportacion', 'LugarExpedicion', 'Moneda', 'Referencia', 'SubTotal', 'Total'], $report);

        if (!array_key_exists('Conceptos', $payload)) {
            $report['missing'][] = 'Conceptos';
        } elseif (!is_array($payload['Conceptos']) || count($payload['Conceptos']) === 0) {
            $report['empty_arrays'][] = 'Conceptos';
        } else {
            foreach ($payload['Conceptos'] as $i => $concepto) {
                $path = "Conceptos[{$i}]";
                if (!is_array($concepto)) {
                    $report['errors'][] = "{$path} debe ser un objeto/arreglo asociativo.";
                    continue;
                }

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
                    $hayImpuestos = false;
                    foreach (['TrasladoConcepto40R', 'RetencionConcepto40R', 'RetencionLocal40R', 'TrasladoLocal40R'] as $impuestosNodo) {
                        if (!empty($concepto[$impuestosNodo])) {
                            $hayImpuestos = true;
                            break;
                        }
                    }
                    if (!$hayImpuestos) {
                        $report['errors'][] = "{$path}.ObjetoImp=02 requiere al menos un nodo de impuestos de concepto.";
                    }
                }

                $this->validateOptionalNodeList($concepto, $path, 'TrasladoConcepto40R', ['Base', 'Impuesto', 'TipoFactor'], $report);
                $this->validateOptionalNodeList($concepto, $path, 'RetencionConcepto40R', ['Base', 'Importe', 'Impuesto', 'TasaOCuota', 'TipoFactor'], $report);
                $this->validateOptionalNodeList($concepto, $path, 'RetencionLocal40R', ['ImpLocalRetenido', 'Importe', 'TasaRetencion'], $report);
                $this->validateOptionalNodeList($concepto, $path, 'TrasladoLocal40R', ['ImpLocalTraslado', 'Importe', 'TasaRetencion'], $report);
            }
        }

        if (!empty($payload['InformacionGlobal'])) {
            if (!is_array($payload['InformacionGlobal'])) {
                $report['errors'][] = 'InformacionGlobal debe ser un objeto/arreglo asociativo.';
            } else {
                $this->validateFields($payload['InformacionGlobal'], 'InformacionGlobal', ['Año', 'Meses', 'Periodicidad'], $report);
            }
        }

        if (!empty($payload['CfdiRelacionados40R'])) {
            if (!is_array($payload['CfdiRelacionados40R'])) {
                $report['errors'][] = 'CfdiRelacionados40R debe ser un objeto/arreglo asociativo.';
            } else {
                $this->validateFields($payload['CfdiRelacionados40R'], 'CfdiRelacionados40R', ['TipoRelacion'], $report);
                if (!empty($payload['CfdiRelacionados40R']['CfdiRelacionado40R'])) {
                    foreach ((array)$payload['CfdiRelacionados40R']['CfdiRelacionado40R'] as $i => $rel) {
                        $itemPath = "CfdiRelacionados40R.CfdiRelacionado40R[{$i}]";
                        if (!is_array($rel)) {
                            $report['errors'][] = "{$itemPath} debe ser un objeto/arreglo asociativo.";
                            continue;
                        }
                        $this->validateFields($rel, $itemPath, ['UUID'], $report);
                    }
                }
            }
        }

        return $this->buildFinalReport($report);
    }

    private function validateRequiredObject(array $root, string $path, string $node, array $requiredFields, array &$report): void
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
        $this->validateFields($root[$node], $nodePath, $requiredFields, $report);
    }

    private function validateOptionalNodeList(array $root, string $path, string $node, array $requiredFields, array &$report): void
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

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
