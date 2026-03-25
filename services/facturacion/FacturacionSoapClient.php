<?php

class FacturacionSoapClient
{
    private const SOAP_ACTION_GENERAR_CFDI40 = 'http://tempuri.org/IConexionRemota/GenerarCFDI40';
    private const NS_SOAPENV = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const NS_TEM = 'http://tempuri.org/';
    private const NS_TES = 'http://schemas.datacontract.org/2004/07/TES.V33.CFDI.Negocios';

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
        $xml = $this->buildSoapEnvelope($normalizedPayload);
        $xmlContract = $this->validateXmlContract($xml);

        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . self::SOAP_ACTION_GENERAR_CFDI40 . '"',
            'Content-Length: ' . strlen($xml),
        ];

        $this->lastRequest = $xml;
        $this->lastRequestHeaders = implode("\r\n", $headers);

        $payloadShape = $this->buildPayloadShapeSummary($normalizedPayload);
        $payloadValidation = $this->validatePayloadStructure($normalizedPayload);

        error_log('[CFDI40][GenerarCFDI40] payload_normalized=' . json_encode($this->normalizeForDebug($normalizedPayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] payload_shape=' . json_encode($payloadShape, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] payload_validation=' . json_encode($payloadValidation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] wsdl_url=' . $this->config['wsdl']);
        error_log('[CFDI40][GenerarCFDI40] soap_post_url=' . $this->config['endpoint']);
        error_log('[CFDI40][GenerarCFDI40] soap_action=' . $this->config['soap_action']);
        error_log('[CFDI40][GenerarCFDI40] xml_final=' . $xml);
        error_log('[CFDI40][GenerarCFDI40] root_operation=' . ($xmlContract['root_operation'] ?? ''));
        error_log('[CFDI40][GenerarCFDI40] root_children=' . json_encode($xmlContract['root_children'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] namespace_tem=' . ($xmlContract['namespace_tem'] ?? ''));
        error_log('[CFDI40][GenerarCFDI40] namespace_tes=' . ($xmlContract['namespace_tes'] ?? ''));
        error_log('[CFDI40][GenerarCFDI40] payload_logico_normalizado=' . json_encode($this->normalizeForDebug($normalizedPayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] resumen_fiscal=' . json_encode($this->buildFiscalSummary($normalizedPayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

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
            $this->lastResponseHeaders = substr($rawResponse, 0, $headerSize);
            $this->lastResponse = (string)substr($rawResponse, $headerSize);
        }

        curl_close($curl);

        error_log('[CFDI40][GenerarCFDI40] http_code=' . (string)$httpCode);
        error_log('[CFDI40][GenerarCFDI40] curl_errno=' . (string)$curlErrno);
        error_log('[CFDI40][GenerarCFDI40] curl_error=' . $curlError);
        error_log('[CFDI40][GenerarCFDI40] soap_manual_response_headers=' . ($this->lastResponseHeaders ?? ''));
        error_log('[CFDI40][GenerarCFDI40] soap_manual_response_body=' . ($this->lastResponse ?? ''));
        error_log('[CFDI40][GenerarCFDI40] response_body=' . ($this->lastResponse ?? ''));

        if ($curlErrno !== 0) {
            throw new RuntimeException('Error de transporte cURL al consumir GenerarCFDI40. errno=' . $curlErrno . ' error=' . $curlError);
        }

        if ($this->lastResponse === null || trim($this->lastResponse) === '') {
            throw new RuntimeException('Respuesta vacía del PAC en GenerarCFDI40. HTTP=' . $httpCode);
        }

        $responseArray = $this->parseSoapResponse($this->lastResponse);
        error_log('[CFDI40][GenerarCFDI40] parsed_response=' . json_encode($responseArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($httpCode >= 400 || isset($responseArray['Fault'])) {
            $fault = $responseArray['Fault'] ?? [];
            $faultCode = is_array($fault) ? (string)($fault['faultcode'] ?? 'Server') : 'Server';
            $faultString = is_array($fault) ? (string)($fault['faultstring'] ?? 'Error SOAP en GenerarCFDI40') : 'Error SOAP en GenerarCFDI40';
            error_log('[CFDI40][GenerarCFDI40] faultcode=' . $faultCode);
            error_log('[CFDI40][GenerarCFDI40] faultstring=' . $faultString);
            error_log('[CFDI40][GenerarCFDI40] error_detallado=' . $faultString);
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
            'soap_action' => self::SOAP_ACTION_GENERAR_CFDI40,
        ];

        foreach (['wsdl', 'endpoint', 'usuario', 'cuenta', 'password'] as $key) {
            if ($config[$key] === null || $config[$key] === '') {
                throw new RuntimeException("Falta configurar {$prefix}{$key} en variables de entorno.");
            }
        }

        if (str_contains((string)$config['endpoint'], '?wsdl') || str_contains((string)$config['endpoint'], '&wsdl')) {
            throw new RuntimeException('El endpoint SOAP de POST no puede incluir ?wsdl/. Debe apuntar al .svc real.');
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

        if (isset($root['parameters']) && is_array($root['parameters'])) {
            $root = $this->toAssocArray($root['parameters']);
        }

        if (isset($root['Credenciales']) || isset($root['Comprobante40R'])) {
            throw new RuntimeException('El contrato real de GenerarCFDI40 no acepta Credenciales/Comprobante40R. Debe usarse tem:credenciales y tem:cfdi.');
        }

        $credenciales = $this->normalizeCredencialesNode($root);
        $cfdi = $this->normalizeCfdiNode($root);
        $this->validateTotalsConsistency($cfdi);

        return [
            'credenciales' => $credenciales,
            'cfdi' => array_filter($cfdi, fn($value) => !$this->isEmptyNodeValue($value)),
        ];
    }

    private function normalizeCredencialesNode(array $root): array
    {
        $rawCredenciales = $this->toAssocArray($root['credenciales'] ?? []);

        $usuario = $rawCredenciales['Usuario'] ?? $rawCredenciales['usuario'] ?? $this->config['usuario'] ?? '';
        $cuenta = $rawCredenciales['Cuenta'] ?? $rawCredenciales['cuenta'] ?? $this->config['cuenta'] ?? '';
        $password = $rawCredenciales['Password'] ?? $rawCredenciales['password'] ?? $this->config['password'] ?? '';

        $credenciales = [
            'Cuenta' => $cuenta,
            'Password' => $password,
            'Usuario' => $usuario,
        ];

        foreach ($credenciales as $key => $value) {
            if ($this->isEmptyNodeValue($value)) {
                throw new RuntimeException('credenciales.' . $key . ' es obligatoria para GenerarCFDI40.');
            }
        }

        return $credenciales;
    }

    private function normalizeCfdiNode(array $root): array
    {
        $rawCfdi = $this->toAssocArray($root['cfdi'] ?? []);

        $cfdi = $this->mergeComprobanteData($rawCfdi, $root);

        $emisor = $this->toAssocArray($rawCfdi['Emisor'] ?? []);
        $receptor = $this->toAssocArray($rawCfdi['Receptor'] ?? []);
        $conceptosSource = $rawCfdi['Conceptos'] ?? [];
        $conceptos = $this->normalizeConceptosNode($conceptosSource);

        $emisorNorm = $this->normalizeOptionalNode($emisor);
        $receptorNorm = $this->normalizeOptionalNode($receptor);

        $infoGlobal = $this->normalizeOptionalNode($rawCfdi['InformacionGlobal'] ?? []);
        if ($this->shouldIncludeInformacionGlobal($receptor, $infoGlobal)) {
            $cfdi['InformacionGlobal'] = $infoGlobal;
        }

        $cfdiRelacionados = $this->normalizeOptionalNode($rawCfdi['CfdiRelacionados40R'] ?? []);
        if (!empty($cfdiRelacionados)) {
            $cfdi['CfdiRelacionados40R'] = $cfdiRelacionados;
        }

        if (!isset($cfdi['Referencia']) || $this->isEmptyNodeValue($cfdi['Referencia'])) {
            throw new RuntimeException('cfdi.Referencia es obligatoria para GenerarCFDI40.');
        }

        return $this->orderCfdiForPac($cfdi, $conceptos, $emisorNorm, $receptorNorm, $infoGlobal, $cfdiRelacionados);
    }

    private function orderCfdiForPac(
        array $cfdi,
        array $conceptos,
        array $emisor,
        array $receptor,
        array $infoGlobal,
        array $cfdiRelacionados
    ): array {
        $ordered = [];
        $preferredOrder = [
            'ClaveCFDI',
            'Conceptos',
            'Emisor',
            'Exportacion',
            'Fecha',
            'Folio',
            'FormaPago',
            'LugarExpedicion',
            'MetodoPago',
            'Moneda',
            'Receptor',
            'Referencia',
            'Serie',
            'SubTotal',
            'TipoCambio',
            'TipoDeComprobante',
            'Total',
            'CondicionesDePago',
            'Descuento',
            'Confirmacion',
            'InformacionGlobal',
            'CfdiRelacionados40R',
        ];

        $cfdi['Conceptos'] = $conceptos;
        $cfdi['Emisor'] = $emisor;
        $cfdi['Receptor'] = $receptor;
        if (!empty($infoGlobal)) {
            $cfdi['InformacionGlobal'] = $infoGlobal;
        }
        if (!empty($cfdiRelacionados)) {
            $cfdi['CfdiRelacionados40R'] = $cfdiRelacionados;
        }

        foreach ($preferredOrder as $field) {
            if (!array_key_exists($field, $cfdi) || $this->isEmptyNodeValue($cfdi[$field])) {
                continue;
            }
            $ordered[$field] = $cfdi[$field];
        }

        foreach ($cfdi as $field => $value) {
            if (array_key_exists($field, $ordered) || $this->isEmptyNodeValue($value)) {
                continue;
            }
            $ordered[$field] = $value;
        }

        return $ordered;
    }

    private function mergeComprobanteData(array $rawCfdi, array $root): array
    {
        $allowedGeneralFields = [
            'ClaveCFDI',
            'Exportacion',
            'Fecha',
            'Folio',
            'FormaPago',
            'MetodoPago',
            'LugarExpedicion',
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
        ];

        $cfdi = [];

        foreach ($allowedGeneralFields as $field) {
            $value = $rawCfdi[$field] ?? $root[$field] ?? $root[lcfirst($field)] ?? null;

            if ($this->isEmptyNodeValue($value)) {
                continue;
            }

            if (in_array($field, ['SubTotal', 'Total', 'Descuento'], true)) {
                $value = $this->formatDecimal($value, 2);
            }
            if ($field === 'TipoCambio') {
                $value = $this->formatDecimal($value, 6);
            }

            $cfdi[$field] = $value;
        }

        return array_filter($cfdi, fn($value) => !$this->isEmptyNodeValue($value));
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
            $impuestos = $this->normalizeConceptoImpuestosNode($conceptoItem);

            unset(
                $conceptoItem['TrasladoConcepto40R'],
                $conceptoItem['RetencionConcepto40R'],
                $conceptoItem['RetencionLocal40R'],
                $conceptoItem['TrasladoLocal40R']
            );

            if (!empty($impuestos)) {
                $conceptoItem['Impuestos'] = $impuestos;
            }

            $normalizedConceptos[] = array_filter($conceptoItem, fn($v) => !$this->isEmptyNodeValue($v));
        }

        return ['Concepto40R' => $normalizedConceptos];
    }

    private function normalizeConceptoImpuestosNode(array $concepto): array
    {
        $rawImpuestos = $this->toAssocArray($concepto['Impuestos'] ?? []);

        $traslados = $rawImpuestos['Traslados']['TrasladoConcepto40R']
            ?? $rawImpuestos['Traslados']
            ?? $concepto['TrasladoConcepto40R']
            ?? [];
        $retenciones = $rawImpuestos['Retenciones']['RetencionConcepto40R']
            ?? $rawImpuestos['Retenciones']
            ?? $concepto['RetencionConcepto40R']
            ?? [];

        $trasladosList = $this->normalizeTaxList($traslados, 6);
        $retencionesList = $this->normalizeTaxList($retenciones, 6);

        $impuestos = [];
        if (!empty($trasladosList)) {
            $impuestos['Traslados'] = ['TrasladoConcepto40R' => $trasladosList];
        }
        if (!empty($retencionesList)) {
            $impuestos['Retenciones'] = ['RetencionConcepto40R' => $retencionesList];
        }

        return $impuestos;
    }

    private function normalizeTaxList($taxes, int $ratePrecision): array
    {
        if (!is_array($taxes)) {
            return [];
        }
        if ($this->isAssoc($taxes)) {
            $taxes = [$taxes];
        }

        $out = [];
        foreach ($taxes as $tax) {
            if (!is_array($tax)) {
                continue;
            }
            $item = $this->toAssocArray($tax);
            if (isset($item['Base']) && $item['Base'] !== '') {
                $item['Base'] = $this->formatDecimal($item['Base'], 2);
            }
            if (isset($item['Importe']) && $item['Importe'] !== '') {
                $item['Importe'] = $this->formatDecimal($item['Importe'], 2);
            }
            if (isset($item['TasaOCuota']) && $item['TasaOCuota'] !== '') {
                $item['TasaOCuota'] = $this->formatDecimal($item['TasaOCuota'], $ratePrecision);
            }
            $out[] = array_filter($item, fn($v) => !$this->isEmptyNodeValue($v));
        }

        return $out;
    }

    private function normalizeOptionalNode($node): array
    {
        $arrayNode = $this->toAssocArray($node);
        return array_filter($arrayNode, fn($value) => !$this->isEmptyNodeValue($value));
    }

    private function validateTotalsConsistency(array $cfdi): void
    {
        $conceptos = $cfdi['Conceptos']['Concepto40R'] ?? [];
        if (!is_array($conceptos) || $conceptos === []) {
            return;
        }

        $sumImporte = 0.0;
        $sumDescuento = 0.0;
        $sumTraslados = 0.0;
        $sumRetenciones = 0.0;

        foreach ($conceptos as $concepto) {
            if (!is_array($concepto)) {
                continue;
            }
            $sumImporte += (float)($concepto['Importe'] ?? 0);
            $sumDescuento += (float)($concepto['Descuento'] ?? 0);

            $traslados = $concepto['Impuestos']['Traslados']['TrasladoConcepto40R'] ?? [];
            if (is_array($traslados) && isset($traslados['Importe'])) {
                $sumTraslados += (float)$traslados['Importe'];
            } else {
                foreach ((array)$traslados as $traslado) {
                    if (is_array($traslado)) {
                        $sumTraslados += (float)($traslado['Importe'] ?? 0);
                    }
                }
            }

            $retenciones = $concepto['Impuestos']['Retenciones']['RetencionConcepto40R'] ?? [];
            if (is_array($retenciones) && isset($retenciones['Importe'])) {
                $sumRetenciones += (float)$retenciones['Importe'];
            } else {
                foreach ((array)$retenciones as $retencion) {
                    if (is_array($retencion)) {
                        $sumRetenciones += (float)($retencion['Importe'] ?? 0);
                    }
                }
            }
        }

        $subtotal = (float)($cfdi['SubTotal'] ?? 0);
        $descuento = (float)($cfdi['Descuento'] ?? 0);
        $total = (float)($cfdi['Total'] ?? 0);
        $expectedTotal = ($subtotal - $descuento) + $sumTraslados - $sumRetenciones;

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

        error_log('[CFDI40][GenerarCFDI40] payload_totals=' . json_encode([
            'sumImporteConceptos' => $this->formatDecimal($sumImporte, 2),
            'sumDescuentoConceptos' => $this->formatDecimal($sumDescuento, 2),
            'sumTrasladosConceptos' => $this->formatDecimal($sumTraslados, 2),
            'sumRetencionesConceptos' => $this->formatDecimal($sumRetenciones, 2),
            'subTotal' => $this->formatDecimal($subtotal, 2),
            'descuento' => $this->formatDecimal($descuento, 2),
            'total' => $this->formatDecimal($total, 2),
            'expectedTotal' => $this->formatDecimal($expectedTotal, 2),
            'warnings' => $warnings,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function buildPayloadShapeSummary(array $payload): array
    {
        $cfdi = $payload['cfdi'] ?? [];
        $conceptos = $cfdi['Conceptos']['Concepto40R'] ?? [];
        $rootChildren = array_keys($payload);

        return [
            'generarCFDI40RootChildren' => $rootChildren,
            'hasCredenciales' => isset($payload['credenciales']) && is_array($payload['credenciales']) && $payload['credenciales'] !== [],
            'hasCfdi' => isset($payload['cfdi']) && is_array($payload['cfdi']) && $payload['cfdi'] !== [],
            'credencialesFields' => array_keys($payload['credenciales'] ?? []),
            'cfdiFields' => array_keys($cfdi),
            'conceptosCount' => count(is_array($conceptos) ? $conceptos : []),
            'cfdiHasGlobalImpuestosNode' => isset($cfdi['Impuestos']) && is_array($cfdi['Impuestos']) && $cfdi['Impuestos'] !== [],
            'impuestosConceptosResumen' => $this->buildImpuestosConceptosResumen(is_array($conceptos) ? $conceptos : []),
            'structural_validation' => [
                'rootOperation' => 'tem:GenerarCFDI40',
                'child1' => 'tem:credenciales',
                'child2' => 'tem:cfdi',
                'namespaceTem' => self::NS_TEM,
                'namespaceTes' => self::NS_TES,
            ],
        ];
    }

    private function buildFiscalSummary(array $payload): array
    {
        $cfdi = $payload['cfdi'] ?? [];
        $conceptos = $cfdi['Conceptos']['Concepto40R'] ?? [];
        $traslados = 0.0;
        $retenciones = 0.0;

        foreach ((array)$conceptos as $concepto) {
            if (!is_array($concepto)) {
                continue;
            }
            $tmpTras = $concepto['Impuestos']['Traslados']['TrasladoConcepto40R'] ?? [];
            foreach ((array)$tmpTras as $t) {
                if (is_array($t)) {
                    $traslados += (float)($t['Importe'] ?? 0);
                }
            }
            $tmpRet = $concepto['Impuestos']['Retenciones']['RetencionConcepto40R'] ?? [];
            foreach ((array)$tmpRet as $r) {
                if (is_array($r)) {
                    $retenciones += (float)($r['Importe'] ?? 0);
                }
            }
        }

        return [
            'subtotal_fiscal' => $this->formatDecimal((float)($cfdi['SubTotal'] ?? 0), 2),
            'total' => $this->formatDecimal((float)($cfdi['Total'] ?? 0), 2),
            'traslados_concepto' => $this->formatDecimal($traslados, 2),
            'retenciones_concepto' => $this->formatDecimal($retenciones, 2),
            'conceptos_count' => is_array($conceptos) ? count($conceptos) : 0,
        ];
    }

    private function buildImpuestosConceptosResumen(array $conceptos): array
    {
        $traslados = 0;
        $retenciones = 0;
        $trasladadoImporte = 0.0;
        $retenidoImporte = 0.0;

        foreach ($conceptos as $concepto) {
            if (!is_array($concepto)) {
                continue;
            }

            $cTraslados = $concepto['Impuestos']['Traslados']['TrasladoConcepto40R'] ?? [];
            if (is_array($cTraslados) && isset($cTraslados['Importe'])) {
                $traslados++;
                $trasladadoImporte += (float)$cTraslados['Importe'];
            } else {
                foreach ((array)$cTraslados as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $traslados++;
                    $trasladadoImporte += (float)($item['Importe'] ?? 0);
                }
            }

            $cRetenciones = $concepto['Impuestos']['Retenciones']['RetencionConcepto40R'] ?? [];
            if (is_array($cRetenciones) && isset($cRetenciones['Importe'])) {
                $retenciones++;
                $retenidoImporte += (float)$cRetenciones['Importe'];
            } else {
                foreach ((array)$cRetenciones as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $retenciones++;
                    $retenidoImporte += (float)($item['Importe'] ?? 0);
                }
            }
        }

        return [
            'trasladosCount' => $traslados,
            'retencionesCount' => $retenciones,
            'trasladadoImporte' => $this->formatDecimal($trasladadoImporte, 2),
            'retenidoImporte' => $this->formatDecimal($retenidoImporte, 2),
        ];
    }

    private function validatePayloadStructure(array $payload): array
    {
        $issues = [];
        $rootChildren = array_keys($payload);

        if ($rootChildren !== ['credenciales', 'cfdi']) {
            $issues[] = 'GenerarCFDI40 debe contener exactamente credenciales y cfdi como hijos raíz.';
        }

        $cfdi = $payload['cfdi'] ?? [];
        foreach (['Emisor', 'Receptor', 'Conceptos', 'Referencia'] as $requiredNode) {
            if (!isset($cfdi[$requiredNode]) || $this->isEmptyNodeValue($cfdi[$requiredNode])) {
                $issues[] = 'Falta cfdi.' . $requiredNode;
            }
        }

        $hasGlobalImpuestos = isset($cfdi['Impuestos']) && is_array($cfdi['Impuestos']) && $cfdi['Impuestos'] !== [];
        if ($hasGlobalImpuestos) {
            $issues[] = 'No debe enviarse cfdi.Impuestos global si el contrato real no lo usa.';
        }

        return [
            'isValid' => $issues === [],
            'issues' => $issues,
            'rootOperation' => 'tem:GenerarCFDI40',
            'child1' => 'tem:credenciales',
            'child2' => 'tem:cfdi',
            'namespaceTem' => self::NS_TEM,
            'namespaceTes' => self::NS_TES,
            'generarCFDI40RootChildren' => $rootChildren,
            'cfdiHasGlobalImpuestosNode' => $hasGlobalImpuestos,
        ];
    }

    private function formatDecimal($value, int $precision): string
    {
        return number_format((float)$value, $precision, '.', '');
    }

    private function buildSoapEnvelope(array $payload): string
    {
        $doc = new DOMDocument('1.0', 'utf-8');

        $envelope = $doc->createElementNS(self::NS_SOAPENV, 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:tem', self::NS_TEM);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:tes', self::NS_TES);
        $doc->appendChild($envelope);

        $header = $doc->createElementNS(self::NS_SOAPENV, 'soapenv:Header');
        $envelope->appendChild($header);

        $body = $doc->createElementNS(self::NS_SOAPENV, 'soapenv:Body');
        $envelope->appendChild($body);

        $operation = $doc->createElementNS(self::NS_TEM, 'tem:GenerarCFDI40');
        $body->appendChild($operation);

        $credencialesNode = $doc->createElementNS(self::NS_TEM, 'tem:credenciales');
        $operation->appendChild($credencialesNode);
        $this->appendTesChildren($doc, $credencialesNode, $payload['credenciales'] ?? []);

        $cfdiNode = $doc->createElementNS(self::NS_TEM, 'tem:cfdi');
        $operation->appendChild($cfdiNode);
        $this->appendTesChildren($doc, $cfdiNode, $payload['cfdi'] ?? []);

        return $doc->saveXML();
    }

    private function validateXmlContract(string $xml): array
    {
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException('El XML SOAP final no es válido.');
        }

        $xpath = new DOMXPath($doc);
        $operation = $xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]/*[local-name()="GenerarCFDI40"]')->item(0);
        if (!$operation instanceof DOMElement) {
            throw new RuntimeException('No existe la operación raíz tem:GenerarCFDI40 en el XML final.');
        }

        $rootChildren = [];
        foreach ($operation->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            $rootChildren[] = $child->localName;
        }

        if ($rootChildren !== ['credenciales', 'cfdi']) {
            throw new RuntimeException('GenerarCFDI40 debe tener exactamente tem:credenciales y tem:cfdi como hijos.');
        }

        if (str_contains($xml, '<tem:Credenciales') || str_contains($xml, '<tes:Credenciales') || str_contains($xml, '<tes:Comprobante40R')) {
            throw new RuntimeException('El XML final contiene wrappers obsoletos (Credenciales/Comprobante40R).');
        }
        if (str_contains($xml, 'FormaDePago') || str_contains($xml, 'MetodoDePago')) {
            throw new RuntimeException('El XML final contiene nombres obsoletos (FormaDePago/MetodoDePago).');
        }

        return [
            'root_operation' => $operation->tagName,
            'root_children' => $rootChildren,
            'namespace_tem' => self::NS_TEM,
            'namespace_tes' => self::NS_TES,
        ];
    }

    private function appendTesChildren(DOMDocument $doc, DOMElement $parent, array $data): void
    {
        foreach ($data as $key => $value) {
            if ($this->isEmptyNodeValue($value)) {
                continue;
            }

            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $child = $doc->createElementNS(self::NS_TES, 'tes:' . $key);
                    $parent->appendChild($child);
                    $this->appendTesChildren($doc, $child, $value);
                    continue;
                }

                foreach ($value as $item) {
                    if ($this->isEmptyNodeValue($item)) {
                        continue;
                    }
                    $child = $doc->createElementNS(self::NS_TES, 'tes:' . $key);
                    $parent->appendChild($child);
                    if (is_array($item)) {
                        $this->appendTesChildren($doc, $child, $item);
                    } else {
                        $child->appendChild($doc->createTextNode((string)$item));
                    }
                }
                continue;
            }

            $child = $doc->createElementNS(self::NS_TES, 'tes:' . $key);
            $child->appendChild($doc->createTextNode((string)$value));
            $parent->appendChild($child);
        }
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
