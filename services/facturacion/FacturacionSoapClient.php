<?php

class FacturacionSoapClient
{
    private const SOAP_ACTION_GENERAR_CFDI40 = 'http://tempuri.org/IConexionRemota/GenerarCFDI40';

    private array $config;
    private ?string $lastRequest = null;
    private ?string $lastResponse = null;
    private ?string $lastRequestHeaders = null;
    private ?string $lastResponseHeaders = null;

    public function __construct()
    {
        $this->config = $this->resolveConfig();
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getLastRequest(): ?string
    {
        return $this->lastRequest;
    }

    public function getLastResponse(): ?string
    {
        return $this->lastResponse;
    }

    public function timbrar(array $payload): array
    {
        $normalizedPayload = $this->normalizePayloadForGenerarCfdi40($payload);
        $operationWrapper = $this->detectInternalWrapperName();
        $xml = $this->buildSoapEnvelope($normalizedPayload, $operationWrapper);

        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . self::SOAP_ACTION_GENERAR_CFDI40 . '"',
            'Content-Length: ' . strlen($xml),
        ];

        $this->lastRequest = $xml;
        $this->lastRequestHeaders = implode("\r\n", $headers);

        error_log('[CFDI40][GenerarCFDI40] payload_normalized=' . json_encode($this->normalizeForDebug($normalizedPayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] curl_endpoint=' . $this->config['endpoint']);
        error_log('[CFDI40][GenerarCFDI40] curl_headers=' . json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] soap_manual_wrapper_name=' . ($operationWrapper ?? 'none'));
        error_log('[CFDI40][GenerarCFDI40] soap_manual_request_xml=' . $xml);

        $rawResponseHeaders = '';
        $curl = curl_init($this->config['endpoint']);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);

        $rawResponse = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);

        if ($rawResponse === false) {
            $this->lastResponseHeaders = '';
            $this->lastResponse = '';
        } else {
            $rawResponseHeaders = substr($rawResponse, 0, $headerSize);
            $this->lastResponse = (string)substr($rawResponse, $headerSize);
            $this->lastResponseHeaders = $rawResponseHeaders;
        }

        curl_close($curl);

        error_log('[CFDI40][GenerarCFDI40] curl_http_code=' . (string)$httpCode);
        error_log('[CFDI40][GenerarCFDI40] curl_errno=' . (string)$curlErrno);
        error_log('[CFDI40][GenerarCFDI40] curl_error=' . $curlError);
        error_log('[CFDI40][GenerarCFDI40] soap_manual_response_headers=' . ($this->lastResponseHeaders ?? ''));
        error_log('[CFDI40][GenerarCFDI40] soap_manual_response_body=' . ($this->lastResponse ?? ''));

        if ($curlErrno !== 0) {
            throw new RuntimeException('Error de transporte cURL al consumir GenerarCFDI40. errno=' . $curlErrno . ' error=' . $curlError);
        }

        if ($this->lastResponse === null || trim($this->lastResponse) === '') {
            throw new RuntimeException('Respuesta vacía del PAC en GenerarCFDI40. HTTP=' . $httpCode);
        }

        $responseArray = $this->parseSoapResponse($this->lastResponse);

        if ($httpCode >= 400 || isset($responseArray['Fault'])) {
            $fault = $responseArray['Fault'] ?? [];
            $faultCode = is_array($fault) ? (string)($fault['faultcode'] ?? 'Server') : 'Server';
            $faultString = is_array($fault) ? (string)($fault['faultstring'] ?? 'Error SOAP en GenerarCFDI40') : 'Error SOAP en GenerarCFDI40';
            throw new SoapFault($faultCode, $faultString);
        }

        return [
            'response' => $responseArray,
            'last_request' => $this->lastRequest,
            'last_response' => $this->lastResponse,
            'last_request_xml' => $this->lastRequest,
            'last_response_xml' => $this->lastResponse,
            'last_request_headers' => $this->lastRequestHeaders,
            'last_response_headers' => $this->lastResponseHeaders,
            'soap_call_mode' => 'manual_curl_xml',
            'wsdl_signature_generar_cfdi40' => null,
            'wsdl_param_names_generar_cfdi40' => [],
            'wsdl_functions' => [],
            'wsdl_types' => [],
            'http_code' => $httpCode,
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
        ];
    }

    private function resolveConfig(): array
    {
        $env = strtoupper((string)$this->env('FD_CR_ENV', 'TEST'));
        $isProd = in_array($env, ['PROD', 'PRODUCCION', 'PRODUCTION'], true);
        $prefix = $isProd ? 'FD_CR_PROD_' : 'FD_CR_TEST_';

        $wsdl = $this->env($prefix . 'WSDL');
        $endpoint = $this->env($prefix . 'ENDPOINT');
        if ($endpoint === null || $endpoint === '') {
            $endpoint = $this->resolveEndpointFromWsdl((string)$wsdl);
        }

        $config = [
            'env' => $isProd ? 'PROD' : 'TEST',
            'wsdl' => $wsdl,
            'endpoint' => $endpoint,
            'usuario' => $this->env($prefix . 'USUARIO'),
            'cuenta' => $this->env($prefix . 'CUENTA'),
            'password' => $this->env($prefix . 'PASSWORD'),
            'wrapper' => $this->env($prefix . 'GENERAR_CFDI40_WRAPPER'),
        ];

        foreach (['wsdl', 'endpoint', 'usuario', 'cuenta', 'password'] as $key) {
            if ($config[$key] === null || $config[$key] === '') {
                throw new RuntimeException("Falta configurar {$prefix}{$key} en variables de entorno.");
            }
        }

        return $config;
    }

    private function resolveEndpointFromWsdl(string $wsdl): string
    {
        if ($wsdl === '') {
            return '';
        }

        if (str_contains($wsdl, '?wsdl')) {
            return str_replace('?wsdl', '', $wsdl);
        }

        if (str_contains($wsdl, '&wsdl')) {
            return str_replace('&wsdl', '', $wsdl);
        }

        return $wsdl;
    }

    private function normalizePayloadForGenerarCfdi40(array $payload): array
    {
        $root = $this->toAssocArray($payload);

        $normalized = [
            'Credenciales' => $this->toAssocArray($root['Credenciales'] ?? $root['credenciales'] ?? []),
            'Emisor' => $this->toAssocArray($root['Emisor'] ?? $root['emisor'] ?? []),
            'Receptor' => $this->toAssocArray($root['Receptor'] ?? $root['receptor'] ?? []),
            'Conceptos' => $this->normalizeConceptosNode($root['Conceptos'] ?? $root['conceptos'] ?? []),
            'Comprobante40R' => $this->normalizeComprobanteNode($root['Comprobante40R'] ?? $root['comprobante'] ?? []),
        ];

        $infoGlobal = $this->toAssocArray($root['InformacionGlobal'] ?? $root['informacion_global'] ?? []);
        if ($this->shouldIncludeInformacionGlobal($normalized['Receptor'], $infoGlobal)) {
            $normalized['InformacionGlobal'] = $infoGlobal;
        }

        return $normalized;
    }

    private function shouldIncludeInformacionGlobal(array $receptor, array $informacionGlobal): bool
    {
        if (empty($informacionGlobal)) {
            return false;
        }

        $rfc = strtoupper(trim((string)($receptor['Rfc'] ?? $receptor['RFC'] ?? '')));
        $nombre = strtoupper(trim((string)($receptor['Nombre'] ?? '')));
        $nombreNormalizado = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $nombre);

        return $rfc === 'XAXX010101000' || str_contains($nombreNormalizado, 'PUBLICO EN GENERAL');
    }

    private function normalizeConceptosNode($conceptos): array
    {
        $conceptosArray = $this->toAssocArray($conceptos);
        $list = $conceptosArray['Concepto40R'] ?? $conceptosArray['concepto40R'] ?? [];

        if (!is_array($list)) {
            $list = [];
        }

        if ($this->isAssoc($list)) {
            $list = [$list];
        }

        $normalizedConceptos = [];
        foreach ($list as $concepto) {
            if (!is_array($concepto) && !is_object($concepto)) {
                continue;
            }
            $conceptoItem = $this->toAssocArray($concepto);
            foreach (['TrasladoConcepto40R', 'RetencionConcepto40R', 'RetencionLocal40R', 'TrasladoLocal40R'] as $taxNode) {
                if (!isset($conceptoItem[$taxNode])) {
                    continue;
                }
                $taxList = $conceptoItem[$taxNode];
                if (!is_array($taxList)) {
                    unset($conceptoItem[$taxNode]);
                    continue;
                }
                if ($this->isAssoc($taxList)) {
                    $taxList = [$taxList];
                }
                $conceptoItem[$taxNode] = array_values(array_filter($taxList, static fn($item) => is_array($item) || is_object($item)));
            }
            $normalizedConceptos[] = array_filter($conceptoItem, fn($v) => !$this->isEmptyNodeValue($v));
        }

        return ['Concepto40R' => $normalizedConceptos];
    }

    private function normalizeComprobanteNode($comprobante): array
    {
        $comprobanteArray = $this->toAssocArray($comprobante);

        if (!array_key_exists('Referencia', $comprobanteArray) || trim((string)$comprobanteArray['Referencia']) === '') {
            throw new RuntimeException('Comprobante40R.Referencia es obligatoria para GenerarCFDI40.');
        }

        foreach (['SubTotal', 'Total', 'Descuento'] as $decimalField) {
            if (isset($comprobanteArray[$decimalField]) && $comprobanteArray[$decimalField] !== '') {
                $comprobanteArray[$decimalField] = $this->formatDecimal($comprobanteArray[$decimalField], 2);
            }
        }

        if (isset($comprobanteArray['TipoCambio']) && $comprobanteArray['TipoCambio'] !== '') {
            $comprobanteArray['TipoCambio'] = $this->formatDecimal($comprobanteArray['TipoCambio'], 6);
        }

        return array_filter($comprobanteArray, fn($value) => !$this->isEmptyNodeValue($value));
    }

    private function formatDecimal($value, int $precision): string
    {
        return number_format((float)$value, $precision, '.', '');
    }

    private function buildSoapEnvelope(array $payload, ?string $internalWrapper): string
    {
        $childrenXml = '';
        $childrenXml .= $this->serializeSimpleObject('Credenciales', $payload['Credenciales'] ?? []);
        $childrenXml .= $this->serializeSimpleObject('Emisor', $payload['Emisor'] ?? []);
        $childrenXml .= $this->serializeSimpleObject('Receptor', $payload['Receptor'] ?? []);
        $childrenXml .= $this->serializeConceptos('Conceptos', $payload['Conceptos']['Concepto40R'] ?? []);
        $childrenXml .= $this->serializeSimpleObject('Comprobante40R', $payload['Comprobante40R'] ?? []);

        if (!empty($payload['InformacionGlobal'])) {
            $childrenXml .= $this->serializeSimpleObject('InformacionGlobal', $payload['InformacionGlobal']);
        }

        if ($internalWrapper !== null && $internalWrapper !== '') {
            $childrenXml = '<' . $internalWrapper . '>' . $childrenXml . '</' . $internalWrapper . '>';
        }

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/">'
            . '<soap:Body>'
            . '<tem:GenerarCFDI40>'
            . $childrenXml
            . '</tem:GenerarCFDI40>'
            . '</soap:Body>'
            . '</soap:Envelope>';
    }

    private function detectInternalWrapperName(): ?string
    {
        $wrapperFromEnv = trim((string)($this->config['wrapper'] ?? ''));
        if ($wrapperFromEnv !== '') {
            return $wrapperFromEnv;
        }

        $wsdl = trim((string)$this->config['wsdl']);
        if ($wsdl === '') {
            return null;
        }

        try {
            $soapClient = new SoapClient($wsdl, ['cache_wsdl' => WSDL_CACHE_NONE, 'trace' => 0, 'exceptions' => true]);
            $functions = (array)$soapClient->__getFunctions();
            foreach ($functions as $signature) {
                if (!is_string($signature) || stripos($signature, 'GenerarCFDI40(') === false) {
                    continue;
                }
                if (!preg_match('/\((.*)\)/', $signature, $matches)) {
                    continue;
                }
                $paramsRaw = trim((string)$matches[1]);
                if ($paramsRaw === '' || stripos($paramsRaw, 'void') === 0) {
                    return null;
                }

                $params = array_values(array_filter(array_map('trim', explode(',', $paramsRaw))));
                if (count($params) !== 1) {
                    return null;
                }

                $tokens = preg_split('/\s+/', (string)$params[0]);
                $candidate = ltrim((string)($tokens[count($tokens) - 1] ?? ''), '$');
                $candidateLower = strtolower($candidate);
                if ($candidate === '' || in_array($candidateLower, ['credenciales', 'comprobante', 'param1', 'parameters', 'request'], true)) {
                    return null;
                }

                return $candidate;
            }
        } catch (Throwable $e) {
            error_log('[CFDI40][GenerarCFDI40] wsdl_wrapper_detect_error=' . $e->getMessage());
        }

        return null;
    }

    private function parseSoapResponse(string $xml): array
    {
        $doc = new DOMDocument();
        $loaded = @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$loaded) {
            throw new RuntimeException('Respuesta SOAP inválida: XML mal formado.');
        }

        $xpath = new DOMXPath($doc);
        $bodyChildren = $xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]/*');
        if (!$bodyChildren instanceof DOMNodeList || $bodyChildren->length === 0) {
            return [];
        }

        $firstNode = $bodyChildren->item(0);
        if (!$firstNode instanceof DOMElement) {
            return [];
        }

        return [$firstNode->localName => $this->domElementToArray($firstNode)];
    }

    private function domElementToArray(DOMElement $element)
    {
        $hasElementChildren = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $hasElementChildren = true;
                break;
            }
        }

        if (!$hasElementChildren) {
            return trim($element->textContent);
        }

        $output = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $name = $child->localName;
            $value = $this->domElementToArray($child);
            if (!array_key_exists($name, $output)) {
                $output[$name] = $value;
                continue;
            }

            if (!is_array($output[$name]) || $this->isAssoc($output[$name])) {
                $output[$name] = [$output[$name]];
            }
            $output[$name][] = $value;
        }

        return $output;
    }

    private function serializeSimpleObject(string $nodeName, array $data): string
    {
        $xml = '';
        foreach ($data as $key => $value) {
            if ($this->isEmptyNodeValue($value)) {
                continue;
            }

            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $xml .= '<' . $key . '>' . $this->serializeSimpleChildren($value) . '</' . $key . '>';
                    continue;
                }

                $xml .= $this->serializeList($key, $value);
                continue;
            }

            $xml .= '<' . $key . '>' . $this->escapeXml((string)$value) . '</' . $key . '>';
        }

        return '<' . $nodeName . '>' . $xml . '</' . $nodeName . '>';
    }

    private function serializeSimpleChildren(array $data): string
    {
        $xml = '';
        foreach ($data as $key => $value) {
            if ($this->isEmptyNodeValue($value)) {
                continue;
            }

            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $xml .= '<' . $key . '>' . $this->serializeSimpleChildren($value) . '</' . $key . '>';
                    continue;
                }
                $xml .= $this->serializeList($key, $value);
                continue;
            }

            $xml .= '<' . $key . '>' . $this->escapeXml((string)$value) . '</' . $key . '>';
        }

        return $xml;
    }

    private function serializeConceptos(string $nodeName, array $conceptos): string
    {
        $xml = '';
        foreach ($conceptos as $concepto) {
            if (!is_array($concepto) && !is_object($concepto)) {
                continue;
            }
            $xml .= $this->serializeSimpleObject('Concepto40R', $this->toAssocArray($concepto));
        }

        return '<' . $nodeName . '>' . $xml . '</' . $nodeName . '>';
    }

    private function serializeList(string $itemName, array $items): string
    {
        $xml = '';
        foreach ($items as $item) {
            if ($this->isEmptyNodeValue($item)) {
                continue;
            }

            if (is_array($item) || is_object($item)) {
                $xml .= $this->serializeSimpleObject($itemName, $this->toAssocArray($item));
                continue;
            }

            $xml .= '<' . $itemName . '>' . $this->escapeXml((string)$item) . '</' . $itemName . '>';
        }

        return $xml;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function isEmptyNodeValue($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    private function toAssocArray($value): array
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (is_object($item)) {
                $out[$key] = $this->toAssocArray($item);
                continue;
            }

            if (is_array($item)) {
                if ($this->isAssoc($item)) {
                    $out[$key] = $this->toAssocArray($item);
                    continue;
                }

                $out[$key] = array_map(function ($listItem) {
                    if (is_array($listItem) || is_object($listItem)) {
                        return $this->toAssocArray($listItem);
                    }
                    return $listItem;
                }, $item);
                continue;
            }

            $out[$key] = $item;
        }

        return $out;
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function normalizeForDebug($value)
    {
        if (is_object($value)) {
            return $this->normalizeForDebug(get_object_vars($value));
        }

        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeForDebug($item);
        }

        return $normalized;
    }

    private function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return ($value === false || $value === null || $value === '') ? $default : $value;
    }
}
