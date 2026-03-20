<?php

class FacturacionResponseMapper
{
    public function map($response, array $soapMeta = []): array
    {
        $array = $this->toArray($response);

        $operacionExitosa = $this->find($array, ['OperacionExitosa', 'operacionExitosa'], false);
        $codigoRespuesta = $this->find($array, ['CodigoRespuesta', 'codigoRespuesta', 'Codigo', 'codigo']);
        $mensaje = $this->firstNonEmpty([
            $this->find($array, ['MensajeError', 'mensajeError']),
            $this->find($array, ['MensajeResultado', 'mensajeResultado']),
            $this->find($array, ['Mensaje', 'mensaje']),
        ]);
        $mensajeDetallado = $this->firstNonEmpty([
            $this->find($array, ['MensajeErrorDetallado', 'mensajeErrorDetallado']),
            $this->find($array, ['Detalle', 'detalle']),
        ]);

        $timbre = $this->findNode($array, ['Timbre', 'timbre']) ?: [];
        $uuid = $this->find($timbre, ['UUID', 'Uuid', 'uuid']) ?: $this->find($array, ['UUID', 'Uuid', 'uuid']);
        $fechaTimbrado = $this->find($timbre, ['FechaTimbrado', 'fechaTimbrado']) ?: $this->find($array, ['FechaTimbrado']);
        $xml = $this->find($array, ['XMLResultado', 'xmlResultado', 'XML', 'xml']);
        $pdf = $this->find($array, ['PDFResultado', 'pdfResultado', 'PDF', 'pdf']);
        $referencia = $this->find($array, ['Referencia', 'referencia']);

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
            'raw_response_array' => $array,
            'raw_response_json' => json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'soap_last_request' => $soapMeta['last_request'] ?? null,
            'soap_last_response' => $soapMeta['last_response'] ?? null,
        ];
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
