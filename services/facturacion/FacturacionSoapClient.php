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
        error_log('[CFDI40][GenerarCFDI40] payload_shape=' . json_encode($this->buildPayloadShapeSummary($normalizedPayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] payload_validation=' . json_encode($this->validatePayloadStructure($normalizedPayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
            error_log('[CFDI40][GenerarCFDI40] soap_faultcode=' . $faultCode);
            error_log('[CFDI40][GenerarCFDI40] soap_faultstring=' . $faultString);
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

        $credenciales = $this->normalizeCredencialesNode($root);

        $comprobanteRoot = $this->toAssocArray(
            $root['Comprobante40R']
            ?? $root['comprobante40R']
            ?? $root['Comprobante']
            ?? $root['comprobante']
            ?? []
        );

        $emisor = $this->toAssocArray($comprobanteRoot['Emisor'] ?? $root['Emisor'] ?? $root['emisor'] ?? []);
        $receptor = $this->toAssocArray($comprobanteRoot['Receptor'] ?? $root['Receptor'] ?? $root['receptor'] ?? []);

        $conceptosSource = $comprobanteRoot['Conceptos'] ?? $root['Conceptos'] ?? $root['conceptos'] ?? [];
        $conceptos = $this->normalizeConceptosNode($conceptosSource);

        $impuestos = $this->normalizeOptionalNode($comprobanteRoot['Impuestos'] ?? $root['Impuestos'] ?? $root['impuestos'] ?? null);
        $cfdisRelacionados = $this->normalizeOptionalNode($comprobanteRoot['CfdisRelacionados'] ?? $root['CfdisRelacionados'] ?? null);

        $infoGlobal = $this->normalizeOptionalNode($comprobanteRoot['InformacionGlobal'] ?? $root['InformacionGlobal'] ?? $root['informacion_global'] ?? null);
        if (!$this->shouldIncludeInformacionGlobal($receptor, $infoGlobal)) {
            $infoGlobal = [];
        }

        $comprobante = $this->normalizeComprobanteNode($comprobanteRoot);
        $comprobante = $this->mergeComprobanteData($comprobante, $root);

        $comprobante['Emisor'] = $this->normalizeOptionalNode($emisor);
        $comprobante['Receptor'] = $this->normalizeOptionalNode($receptor);
        $comprobante['Conceptos'] = $conceptos;

        if (!empty($impuestos)) {
            $comprobante['Impuestos'] = $impuestos;
        }

        if (!empty($cfdisRelacionados)) {
            $comprobante['CfdisRelacionados'] = $cfdisRelacionados;
        }

        if (!empty($infoGlobal)) {
            $comprobante['InformacionGlobal'] = $infoGlobal;
        }

        $this->validateTotalsConsistency($comprobante);

        return [
            'Credenciales' => $credenciales,
            'Comprobante40R' => array_filter($comprobante, fn($value) => !$this->isEmptyNodeValue($value)),
        ];
    }

    private function normalizeCredencialesNode(array $root): array
    {
        $rawCredenciales = $this->toAssocArray($root['Credenciales'] ?? $root['credenciales'] ?? []);

        $usuario = $rawCredenciales['Usuario'] ?? $rawCredenciales['usuario'] ?? $this->config['usuario'] ?? '';
        $cuenta = $rawCredenciales['Cuenta'] ?? $rawCredenciales['cuenta'] ?? $this->config['cuenta'] ?? '';
        $password = $rawCredenciales['Password'] ?? $rawCredenciales['password'] ?? $this->config['password'] ?? '';

        $credenciales = [
            'Usuario' => $usuario,
            'Cuenta' => $cuenta,
            'Password' => $password,
        ];

        foreach ($credenciales as $key => $value) {
            if ($this->isEmptyNodeValue($value)) {
                throw new RuntimeException('Credenciales.' . $key . ' es obligatoria para GenerarCFDI40.');
            }
        }

        return $credenciales;
    }

    private function mergeComprobanteData(array $comprobante, array $root): array
    {
        $allowedGeneralFields = [
            'ClaveCFDI',
            'Exportacion',
            'Fecha',
            'Folio',
            'FormaDePago',
            'LugarExpedicion',
            'MetodoDePago',
            'Moneda',
            'Referencia',
            'SubTotal',
            'TipoCambio',
            'Total',
            'Confirmacion',
            'Descuento',
            'Serie',
            'TipoDeComprobante',
            'CondicionesDePago',
            'NoCertificado',
            'Certificado',
            'Sello',
            'Version',
            'TotalImpuestosTrasladados',
            'TotalImpuestosRetenidos',
        ];

        foreach ($allowedGeneralFields as $field) {
            if (!array_key_exists($field, $comprobante) || $this->isEmptyNodeValue($comprobante[$field])) {
                $fallback = $root[$field] ?? $root[lcfirst($field)] ?? null;
                if (!$this->isEmptyNodeValue($fallback)) {
                    $comprobante[$field] = $fallback;
                }
            }
        }

        return array_filter($comprobante, fn($value) => !$this->isEmptyNodeValue($value));
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
        $list = $conceptosArray['Concepto40R'] ?? $conceptosArray['concepto40R'] ?? $conceptosArray;

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

    private function normalizeOptionalNode($node): array
    {
        $arrayNode = $this->toAssocArray($node);
        return array_filter($arrayNode, fn($value) => !$this->isEmptyNodeValue($value));
    }

    private function validateTotalsConsistency(array $comprobante): void
    {
        $conceptos = $comprobante['Conceptos']['Concepto40R'] ?? [];
        if (!is_array($conceptos) || $conceptos === []) {
            return;
        }

        $sumImporte = 0.0;
        $sumDescuento = 0.0;
        foreach ($conceptos as $concepto) {
            if (!is_array($concepto)) {
                continue;
            }
            $sumImporte += (float)($concepto['Importe'] ?? 0);
            $sumDescuento += (float)($concepto['Descuento'] ?? 0);
        }

        $subtotal = (float)($comprobante['SubTotal'] ?? 0);
        $descuento = (float)($comprobante['Descuento'] ?? 0);
        $total = (float)($comprobante['Total'] ?? 0);

        $impuestosNode = $this->toAssocArray($comprobante['Impuestos'] ?? []);
        $impTras = (float)($impuestosNode['TotalImpuestosTrasladados'] ?? 0);
        $impRet = (float)($impuestosNode['TotalImpuestosRetenidos'] ?? 0);

        $expectedTotal = ($subtotal - $descuento) + $impTras - $impRet;

        $warnings = [];
        if (abs($sumImporte - $subtotal) > 0.02) {
            $warnings[] = 'SubTotal no coincide con suma de importes de conceptos.';
        }
        if (abs($sumDescuento - $descuento) > 0.02 && $descuento > 0) {
            $warnings[] = 'Descuento no coincide con suma de descuentos de conceptos.';
        }
        if ($total > 0 && abs($expectedTotal - $total) > 0.02) {
            $warnings[] = 'Total no coincide con SubTotal-Descuento+Trasladados-Retenidos.';
        }

        if ($warnings !== []) {
            error_log('[CFDI40][GenerarCFDI40] payload_totals_warnings=' . json_encode([
                'warnings' => $warnings,
                'sumImporteConceptos' => $this->formatDecimal($sumImporte, 2),
                'sumDescuentoConceptos' => $this->formatDecimal($sumDescuento, 2),
                'subTotal' => $this->formatDecimal($subtotal, 2),
                'descuento' => $this->formatDecimal($descuento, 2),
                'total' => $this->formatDecimal($total, 2),
                'totalImpuestosTrasladados' => $this->formatDecimal($impTras, 2),
                'totalImpuestosRetenidos' => $this->formatDecimal($impRet, 2),
                'expectedTotal' => $this->formatDecimal($expectedTotal, 2),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    private function normalizeComprobanteNode($comprobante): array
    {
        $comprobanteArray = $this->toAssocArray($comprobante);

        foreach (['SubTotal', 'Total', 'Descuento'] as $decimalField) {
            if (isset($comprobanteArray[$decimalField]) && $comprobanteArray[$decimalField] !== '') {
                $comprobanteArray[$decimalField] = $this->formatDecimal($comprobanteArray[$decimalField], 2);
            }
        }

        if (isset($comprobanteArray['TipoCambio']) && $comprobanteArray['TipoCambio'] !== '') {
            $comprobanteArray['TipoCambio'] = $this->formatDecimal($comprobanteArray['TipoCambio'], 6);
        }

        if (!array_key_exists('Referencia', $comprobanteArray) || trim((string)$comprobanteArray['Referencia']) === '') {
            $fallbackReferencia = $comprobanteArray['referencia'] ?? null;
            if (!$this->isEmptyNodeValue($fallbackReferencia)) {
                $comprobanteArray['Referencia'] = $fallbackReferencia;
            }
        }

        if (!array_key_exists('Referencia', $comprobanteArray) || trim((string)$comprobanteArray['Referencia']) === '') {
            throw new RuntimeException('Comprobante40R.Referencia es obligatoria para GenerarCFDI40.');
        }

        return array_filter($comprobanteArray, fn($value) => !$this->isEmptyNodeValue($value));
    }

    private function buildPayloadShapeSummary(array $payload): array
    {
        $comprobante = $payload['Comprobante40R'] ?? [];
        $shape = [
            'hasCredenciales' => isset($payload['Credenciales']) && is_array($payload['Credenciales']) && $payload['Credenciales'] !== [],
            'credencialesFields' => array_keys($payload['Credenciales'] ?? []),
            'hasComprobante40R' => isset($payload['Comprobante40R']) && is_array($payload['Comprobante40R']) && $payload['Comprobante40R'] !== [],
            'comprobanteFields' => array_keys($comprobante),
            'comprobanteNested' => [
                'Emisor' => isset($comprobante['Emisor']) && is_array($comprobante['Emisor']) && $comprobante['Emisor'] !== [],
                'Receptor' => isset($comprobante['Receptor']) && is_array($comprobante['Receptor']) && $comprobante['Receptor'] !== [],
                'Conceptos' => isset($comprobante['Conceptos']['Concepto40R']) && is_array($comprobante['Conceptos']['Concepto40R']),
                'Impuestos' => isset($comprobante['Impuestos']) && is_array($comprobante['Impuestos']),
                'InformacionGlobal' => isset($comprobante['InformacionGlobal']) && is_array($comprobante['InformacionGlobal']),
                'CfdisRelacionados' => isset($comprobante['CfdisRelacionados']) && is_array($comprobante['CfdisRelacionados']),
                'Referencia' => isset($comprobante['Referencia']) && !$this->isEmptyNodeValue($comprobante['Referencia']),
            ],
            'conceptosCount' => count($comprobante['Conceptos']['Concepto40R'] ?? []),
        ];

        return $shape;
    }

    private function validatePayloadStructure(array $payload): array
    {
        $issues = [];

        if (array_keys($payload) !== ['Credenciales', 'Comprobante40R']) {
            $issues[] = 'El payload raíz de GenerarCFDI40 debe contener solo Credenciales y Comprobante40R.';
        }

        $comprobante = $payload['Comprobante40R'] ?? [];
        foreach (['Emisor', 'Receptor', 'Conceptos', 'Referencia'] as $requiredNode) {
            if (!isset($comprobante[$requiredNode]) || $this->isEmptyNodeValue($comprobante[$requiredNode])) {
                $issues[] = 'Falta Comprobante40R.' . $requiredNode;
            }
        }

        return [
            'isValid' => $issues === [],
            'issues' => $issues,
        ];
    }

    private function formatDecimal($value, int $precision): string
    {
        return number_format((float)$value, $precision, '.', '');
    }

    private function buildSoapEnvelope(array $payload, ?string $internalWrapper): string
    {
        $childrenXml = '';
        $childrenXml .= $this->serializeSimpleObject('Credenciales', $payload['Credenciales'] ?? []);
        $childrenXml .= $this->serializeSimpleObject('Comprobante40R', $payload['Comprobante40R'] ?? []);

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
                    $child = $this->serializeSimpleChildren($value);
                    if ($child !== '') {
                        $xml .= '<' . $key . '>' . $child . '</' . $key . '>';
                    }
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
                    $child = $this->serializeSimpleChildren($value);
                    if ($child !== '') {
                        $xml .= '<' . $key . '>' . $child . '</' . $key . '>';
                    }
                    continue;
                }
                $xml .= $this->serializeList($key, $value);
                continue;
            }

            $xml .= '<' . $key . '>' . $this->escapeXml((string)$value) . '</' . $key . '>';
        }

        return $xml;
    }

    private function serializeList(string $itemName, array $items): string
    {
        $xml = '';
        foreach ($items as $item) {
            if ($this->isEmptyNodeValue($item)) {
                continue;
            }

            if (is_array($item) || is_object($item)) {
                $serialized = $this->serializeSimpleObject($itemName, $this->toAssocArray($item));
                if ($serialized !== '<' . $itemName . '></' . $itemName . '>') {
                    $xml .= $serialized;
                }
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
            if ($value === []) {
                return true;
            }

            foreach ($value as $item) {
                if (!$this->isEmptyNodeValue($item)) {
                    return false;
                }
            }

            return true;
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
