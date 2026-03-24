<?php

class FacturacionResponseMapper
{
    public function map($response, array $soapMeta = []): array
    {
        $array = $this->toArray($response);
        $resultNode = $this->extractResultNode($array);

        $operacionExitosa = $this->find($resultNode, ['OperacionExitosa', 'operacionExitosa'], false);
        $codigoRespuesta = $this->find($resultNode, ['CodigoConfirmacion', 'codigoConfirmacion', 'CodigoRespuesta', 'codigoRespuesta', 'Codigo', 'codigo']);
        $mensaje = $this->firstNonEmpty([
            $this->find($resultNode, ['ErrorGeneral', 'errorGeneral']),
            $this->find($resultNode, ['MensajeError', 'mensajeError']),
            $this->find($resultNode, ['MensajeResultado', 'mensajeResultado']),
            $this->find($resultNode, ['Mensaje', 'mensaje']),
        ]);
        $mensajeDetallado = $this->firstNonEmpty([
            $this->find($resultNode, ['ErrorDetallado', 'errorDetallado']),
            $this->find($resultNode, ['MensajeErrorDetallado', 'mensajeErrorDetallado']),
            $this->find($resultNode, ['Detalle', 'detalle']),
        ]);

        $timbre = $this->findNode($resultNode, ['Timbre', 'timbre']) ?: [];
        $uuid = $this->find($timbre, ['UUID', 'Uuid', 'uuid']) ?: $this->find($resultNode, ['UUID', 'Uuid', 'uuid']);
        $fechaTimbrado = $this->firstNonEmpty([
            $this->find($timbre, ['FechaTimbrado', 'fechaTimbrado']),
            $this->find($resultNode, ['FechaGenerada', 'fechaGenerada']),
            $this->find($resultNode, ['FechaTimbrado']),
        ]);
        $xml = $this->find($resultNode, ['XMLResultado', 'xmlResultado', 'XML', 'xml']);
        $pdf = $this->find($resultNode, ['PDFResultado', 'pdfResultado', 'PDF', 'pdf']);
        $referencia = $this->firstNonEmpty([
            $this->find($resultNode, ['FolioGenerado', 'folioGenerado']),
            $this->find($resultNode, ['Referencia', 'referencia']),
        ]);
        $cbb = $this->find($resultNode, ['CBB', 'cbb']);

        return [
            'operacion_exitosa' => filter_var($operacionExitosa, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$operacionExitosa,
            'codigo_respuesta' => $codigoRespuesta !== null ? (string)$codigoRespuesta : null,
            'mensaje_respuesta' => $mensaje !== null ? (string)$mensaje : null,
            'mensaje_detallado' => $mensajeDetallado !== null ? (string)$mensajeDetallado : null,
            'uuid' => $uuid !== null ? (string)$uuid : null,
            'fecha_timbrado' => $fechaTimbrado !== null ? (string)$fechaTimbrado : null,
            'xml_timbrado' => is_string($xml) ? $xml : null,
            'pdf_base64' => is_string($pdf) ? $pdf : null,
            'referencia' => $referencia !== null ? (string)$referencia : null,
            'cbb' => is_string($cbb) ? $cbb : null,
            'raw_response_array' => $array,
            'raw_response_json' => json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'soap_last_request' => $soapMeta['last_request'] ?? null,
            'soap_last_response' => $soapMeta['last_response'] ?? null,
            'response_node_path' => $this->detectResultNodePath($array),
        ];
    }

    private function extractResultNode($array)
    {
        if (!is_array($array)) {
            return $array;
        }

        foreach (['GenerarCFDI40Result', 'generarCFDI40Result', 'GenerarCFDI40Response', 'generarCFDI40Response'] as $wrapper) {
            if (isset($array[$wrapper])) {
                $value = $array[$wrapper];
                if (is_array($value) && isset($value['GenerarCFDI40Result'])) {
                    return $value['GenerarCFDI40Result'];
                }
                return $value;
            }
        }

        return $array;
    }

    private function detectResultNodePath($array): ?string
    {
        if (!is_array($array)) {
            return null;
        }

        if (isset($array['GenerarCFDI40Result'])) {
            return 'GenerarCFDI40Result';
        }
        if (isset($array['GenerarCFDI40Response']) && is_array($array['GenerarCFDI40Response']) && isset($array['GenerarCFDI40Response']['GenerarCFDI40Result'])) {
            return 'GenerarCFDI40Response.GenerarCFDI40Result';
        }
        if (isset($array['GenerarCFDI40Response'])) {
            return 'GenerarCFDI40Response';
        }

        return 'root';
    }

    private function toArray($value)
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = $this->toArray($v);
        }
        return $out;
    }

    private function find($data, array $keys, $default = null)
    {
        if (!is_array($data)) {
            return $default;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->find($value, $keys, '__nf__');
                if ($found !== '__nf__') {
                    return $found;
                }
            }
        }

        return $default;
    }

    private function findNode($data, array $keys)
    {
        if (!is_array($data)) {
            return null;
        }
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->findNode($value, $keys);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
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
}
