<?php

class FacturacionSoapClient
{
    private array $config;
    private ?SoapClient $client = null;
    private ?string $lastRequest = null;
    private ?string $lastResponse = null;
    private ?string $lastRequestHeaders = null;
    private ?string $lastResponseHeaders = null;
    private ?array $wsdlMetadata = null;

    public function __construct()
    {
        $this->config = $this->resolveConfig();
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function timbrar(array $payload): array
    {
        $client = $this->client();
        $wsdlMeta = $this->getWsdlMetadata($client);

        $payloadOriginal = $this->normalizeForDebug($payload);
        $payloadNormalized = $this->normalizePayloadForGenerarCfdi40($payload, $wsdlMeta);

        error_log('[CFDI40][GenerarCFDI40] payload_original=' . json_encode($payloadOriginal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] payload_normalized=' . json_encode($this->normalizeForDebug($payloadNormalized), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $callPlans = $this->buildCallPlans($payloadNormalized, $wsdlMeta);
        $lastFault = null;
        $response = null;
        $usedPlan = null;

        foreach ($callPlans as $plan) {
            $finalArgsDebug = $this->normalizeForDebug($plan['arguments'] ?? []);
            $finalArgNames = $this->extractArgumentNames($plan['arguments'] ?? []);
            $finalShape = $this->describeArgumentShape($plan['arguments'] ?? []);
            $finalUsesSoapParam = $this->containsSoapParam($plan['arguments'] ?? []);

            error_log('[CFDI40][GenerarCFDI40] soap_final_function_name=' . ($plan['function_name'] ?? 'GenerarCFDI40'));
            error_log('[CFDI40][GenerarCFDI40] soap_final_call_mode=' . ($plan['mode'] ?? 'unknown'));
            error_log('[CFDI40][GenerarCFDI40] soap_final_args_raw=' . print_r($plan['arguments'] ?? [], true));
            error_log('[CFDI40][GenerarCFDI40] soap_final_args_json=' . json_encode($finalArgsDebug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            error_log('[CFDI40][GenerarCFDI40] soap_final_args_count=' . (string)count($plan['arguments'] ?? []));
            error_log('[CFDI40][GenerarCFDI40] soap_final_param_names=' . json_encode($finalArgNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            error_log('[CFDI40][GenerarCFDI40] soap_final_argument_shape=' . json_encode($finalShape, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            error_log('[CFDI40][GenerarCFDI40] soap_final_uses_soapparam=' . ($finalUsesSoapParam ? 'true' : 'false'));
            error_log('[CFDI40][GenerarCFDI40] soap_final_wrapper_name=' . (($plan['wrapper_name'] ?? null) ?: ''));
            error_log('[CFDI40][GenerarCFDI40] soap_final_wrapper_child_names=' . json_encode($plan['wrapper_child_names'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            try {
                $response = $client->__soapCall('GenerarCFDI40', $plan['arguments']);
                $this->captureLastSoapExchange($client);
                $validation = $this->validateSerializedRequest($this->lastRequest, false);

                if (!$validation['is_valid']) {
                    error_log('[CFDI40][GenerarCFDI40] plan_discarded=' . ($plan['mode'] ?? 'unknown'));
                    error_log('[CFDI40][GenerarCFDI40] plan_discard_reason=' . $validation['reason']);
                    $response = null;
                    continue;
                }

                $usedPlan = $plan;
                error_log('[CFDI40][GenerarCFDI40] response_json=' . json_encode($this->normalizeForDebug($response), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                break;
            } catch (SoapFault $fault) {
                $lastFault = $fault;
                $this->captureLastSoapExchange($client);
                $validation = $this->validateSerializedRequest($this->lastRequest, false);

                error_log('[CFDI40][GenerarCFDI40] soap_fault_attempt_mode=' . ($plan['mode'] ?? 'unknown'));
                error_log('[CFDI40][GenerarCFDI40] soap_fault=' . print_r($fault, true));

                if (!$validation['is_valid']) {
                    error_log('[CFDI40][GenerarCFDI40] plan_discarded=' . ($plan['mode'] ?? 'unknown'));
                    error_log('[CFDI40][GenerarCFDI40] plan_discard_reason=' . $validation['reason']);
                    continue;
                }

                $usedPlan = $plan;
                break;
            }
        }

        if ($response === null && $lastFault instanceof SoapFault) {
            throw $lastFault;
        }

        return [
            'response' => $response,
            'last_request' => $this->lastRequest,
            'last_response' => $this->lastResponse,
            'last_request_xml' => $this->lastRequest,
            'last_response_xml' => $this->lastResponse,
            'last_request_headers' => $this->lastRequestHeaders,
            'last_response_headers' => $this->lastResponseHeaders,
            'soap_call_mode' => $usedPlan['mode'] ?? null,
            'wsdl_signature_generar_cfdi40' => $wsdlMeta['signature'] ?? null,
            'wsdl_param_names_generar_cfdi40' => $wsdlMeta['param_names'] ?? [],
            'wsdl_functions' => $wsdlMeta['functions'] ?? [],
            'wsdl_types' => $wsdlMeta['types'] ?? [],
        ];
    }

    public function getLastRequest(): ?string
    {
        return $this->lastRequest;
    }

    public function getLastResponse(): ?string
    {
        return $this->lastResponse;
    }

    private function client(): SoapClient
    {
        if ($this->client instanceof SoapClient) {
            return $this->client;
        }

        $options = [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 30,
            'encoding' => 'UTF-8',
        ];

        return $this->client = new SoapClient($this->config['wsdl'], $options);
    }

    private function resolveConfig(): array
    {
        $env = strtoupper((string)($this->env('FD_CR_ENV', 'TEST')));
        $isProd = in_array($env, ['PROD', 'PRODUCCION', 'PRODUCTION'], true);
        $prefix = $isProd ? 'FD_CR_PROD_' : 'FD_CR_TEST_';

        $config = [
            'env' => $isProd ? 'PROD' : 'TEST',
            'wsdl' => $this->env($prefix . 'WSDL'),
            'usuario' => $this->env($prefix . 'USUARIO'),
            'cuenta' => $this->env($prefix . 'CUENTA'),
            'password' => $this->env($prefix . 'PASSWORD'),
        ];

        foreach (['wsdl', 'usuario', 'cuenta', 'password'] as $key) {
            if ($config[$key] === null || $config[$key] === '') {
                throw new RuntimeException("Falta configurar {$prefix}{$key} en variables de entorno.");
            }
        }

        return $config;
    }

    private function getWsdlMetadata(SoapClient $client): array
    {
        if ($this->wsdlMetadata !== null) {
            return $this->wsdlMetadata;
        }

        $functions = [];
        $types = [];

        try {
            $functions = $client->__getFunctions();
        } catch (Throwable $e) {
            error_log('[CFDI40][GenerarCFDI40] wsdl_functions_error=' . $e->getMessage());
        }

        try {
            $types = $client->__getTypes();
        } catch (Throwable $e) {
            error_log('[CFDI40][GenerarCFDI40] wsdl_types_error=' . $e->getMessage());
        }

        $signature = null;
        $paramNames = [];
        $paramTypes = [];

        foreach ((array)$functions as $fn) {
            if (!is_string($fn) || stripos($fn, 'GenerarCFDI40(') === false) {
                continue;
            }

            $signature = $fn;
            [$paramNames, $paramTypes] = $this->parseFunctionSignatureParams($fn);
            break;
        }

        $this->wsdlMetadata = [
            'functions' => is_array($functions) ? $functions : [],
            'types' => is_array($types) ? $types : [],
            'signature' => $signature,
            'param_names' => $paramNames,
            'param_types' => $paramTypes,
        ];

        error_log('[CFDI40][GenerarCFDI40] wsdl_functions=' . json_encode($this->wsdlMetadata['functions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] wsdl_types=' . json_encode($this->wsdlMetadata['types'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        error_log('[CFDI40][GenerarCFDI40] wsdl_signature_generar_cfdi40=' . ($signature ?? 'not_found'));
        error_log('[CFDI40][GenerarCFDI40] wsdl_param_names_generar_cfdi40=' . json_encode($paramNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $this->wsdlMetadata;
    }

    private function parseFunctionSignatureParams(string $signature): array
    {
        $paramNames = [];
        $paramTypes = [];
        if (!preg_match('/\((.*)\)/', $signature, $m)) {
            return [$paramNames, $paramTypes];
        }

        $paramsRaw = trim((string)$m[1]);
        if ($paramsRaw === '' || stripos($paramsRaw, 'void') === 0) {
            return [$paramNames, $paramTypes];
        }

        foreach (array_filter(array_map('trim', explode(',', $paramsRaw))) as $part) {
            $tokens = preg_split('/\s+/', $part);
            if (!is_array($tokens) || count($tokens) < 2) {
                continue;
            }
            $type = trim((string)$tokens[count($tokens) - 2]);
            $name = ltrim(trim((string)$tokens[count($tokens) - 1]), '$');
            if ($name === '') {
                continue;
            }
            $paramNames[] = $name;
            $paramTypes[] = $type;
        }

        return [$paramNames, $paramTypes];
    }

    private function normalizePayloadForGenerarCfdi40(array $payload, array $wsdlMeta): array
    {
        $root = $this->toAssocArray($payload);

        $credenciales = $this->toAssocArray($root['Credenciales'] ?? $root['credenciales'] ?? []);
        $comprobante = $this->toAssocArray($root['Comprobante40R'] ?? $root['comprobante'] ?? []);
        $comprobante = $this->toAssocArray($this->unwrapNestedComprobanteWrapper($comprobante, $wsdlMeta));

        foreach (['Emisor', 'Receptor', 'Conceptos', 'InformacionGlobal', 'CfdiRelacionados40R'] as $node) {
            if (array_key_exists($node, $root) && !array_key_exists($node, $comprobante)) {
                $comprobante[$node] = $root[$node];
            }
        }

        if ($this->isTypeInWsdl($wsdlMeta['types'] ?? [], 'Comprobante40R')) {
            $comprobante = $this->normalizeComprobanteNode($comprobante);
        }

        return [
            'Credenciales' => $this->normalizeComplexNode($credenciales),
            'Comprobante40R' => $this->normalizeComplexNode($comprobante),
        ];
    }

    private function unwrapNestedComprobanteWrapper($comprobante, array $wsdlMeta)
    {
        if (!is_array($comprobante) && !is_object($comprobante)) {
            return $comprobante;
        }

        $paramTypes = $wsdlMeta['param_types'] ?? [];
        $secondParamType = strtoupper((string)($paramTypes[1] ?? ''));
        $raw = $this->toAssocArray($comprobante);

        if (!array_key_exists('Comprobante40R', $raw)) {
            return $comprobante;
        }

        $nested = $raw['Comprobante40R'];
        if (!is_array($nested) && !is_object($nested)) {
            return $comprobante;
        }

        $expectsTypeComprobante = $secondParamType === '' || str_contains($secondParamType, 'COMPROBANTE40R');
        if (!$expectsTypeComprobante) {
            return $comprobante;
        }

        error_log('[CFDI40][GenerarCFDI40] normalize_warning=Se detectó wrapper anidado Comprobante40R dentro del segundo parámetro. Se desempaqueta para cumplir contrato WSDL.');

        return $nested;
    }

    private function normalizeComprobanteNode($comprobante): array
    {
        $array = $this->toAssocArray($comprobante);

        if (isset($array['Conceptos'])) {
            $conceptosNode = $this->toAssocArray($array['Conceptos']);
            if (isset($conceptosNode['Concepto40R'])) {
                $conceptosNode['Concepto40R'] = $this->normalizeListNode($conceptosNode['Concepto40R']);
                foreach ($conceptosNode['Concepto40R'] as $index => $concepto) {
                    $conceptoArray = $this->toAssocArray($concepto);
                    foreach (['TrasladoConcepto40R', 'RetencionConcepto40R', 'RetencionLocal40R', 'TrasladoLocal40R'] as $taxNode) {
                        if (!array_key_exists($taxNode, $conceptoArray)) {
                            continue;
                        }
                        $conceptoArray[$taxNode] = $this->normalizeListNode($conceptoArray[$taxNode]);
                    }
                    $conceptosNode['Concepto40R'][$index] = $conceptoArray;
                }
            }
            $array['Conceptos'] = $conceptosNode;
        }

        if (isset($array['CfdiRelacionados40R'])) {
            $relNode = $this->toAssocArray($array['CfdiRelacionados40R']);
            if (isset($relNode['CfdiRelacionado40R'])) {
                $relNode['CfdiRelacionado40R'] = $this->normalizeListNode($relNode['CfdiRelacionado40R']);
            }
            $array['CfdiRelacionados40R'] = $relNode;
        }

        return $array;
    }

    private function buildCallPlans(array $payload, array $wsdlMeta): array
    {
        $credenciales = $payload['Credenciales'] ?? new stdClass();
        $comprobante = $payload['Comprobante40R'] ?? new stdClass();

        $signatureParamNames = array_values(array_filter((array)($wsdlMeta['param_names'] ?? []), static fn($n) => is_string($n) && $n !== ''));
        $firstParamName = $signatureParamNames[0] ?? 'credenciales';
        $secondParamName = $signatureParamNames[1] ?? 'comprobante';

        return [
            [
                'mode' => 'multiparam_named_signature',
                'function_name' => 'GenerarCFDI40',
                'wrapper_name' => null,
                'wrapper_child_names' => [],
                'arguments' => [
                    new SoapParam($credenciales, $firstParamName),
                    new SoapParam($comprobante, $secondParamName),
                ],
            ],
            [
                'mode' => 'multiparam_named_contract',
                'function_name' => 'GenerarCFDI40',
                'wrapper_name' => null,
                'wrapper_child_names' => [],
                'arguments' => [
                    new SoapParam($credenciales, 'credenciales'),
                    new SoapParam($comprobante, 'comprobante'),
                ],
            ],
            [
                'mode' => 'multiparam_positional',
                'function_name' => 'GenerarCFDI40',
                'wrapper_name' => null,
                'wrapper_child_names' => [],
                'arguments' => [$credenciales, $comprobante],
            ],
        ];
    }

    private function extractArgumentNames(array $arguments): array
    {
        $names = [];
        foreach ($arguments as $index => $argument) {
            if ($argument instanceof SoapParam) {
                $names[] = (string)($argument->param_name ?? '');
                continue;
            }
            $names[] = (string)$index;
        }
        return $names;
    }

    private function containsSoapParam(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if ($argument instanceof SoapParam) {
                return true;
            }
        }
        return false;
    }

    private function describeArgumentShape(array $arguments): array
    {
        $shape = [];
        foreach ($arguments as $index => $argument) {
            if ($argument instanceof SoapParam) {
                $shape[$index] = [
                    'type' => 'SoapParam',
                    'name' => $argument->param_name ?? null,
                    'value_type' => get_debug_type($argument->param_data ?? null),
                    'value_keys' => $this->safeKeys($argument->param_data ?? null),
                ];
                continue;
            }

            $shape[$index] = [
                'type' => get_debug_type($argument),
                'keys' => $this->safeKeys($argument),
            ];
        }

        return $shape;
    }

    private function safeKeys($value): array
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return [];
        }

        return array_map(static fn($key) => (string)$key, array_keys($value));
    }

    private function captureLastSoapExchange(SoapClient $client): void
    {
        $this->lastRequest = method_exists($client, '__getLastRequest') ? $client->__getLastRequest() : null;
        $this->lastResponse = method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null;
        $this->lastRequestHeaders = method_exists($client, '__getLastRequestHeaders') ? $client->__getLastRequestHeaders() : null;
        $this->lastResponseHeaders = method_exists($client, '__getLastResponseHeaders') ? $client->__getLastResponseHeaders() : null;

        error_log('[CFDI40][GenerarCFDI40] last_request_headers=' . ($this->lastRequestHeaders ?? ''));
        error_log('[CFDI40][GenerarCFDI40] last_request_xml=' . ($this->lastRequest ?? ''));
        error_log('[CFDI40][GenerarCFDI40] last_response_headers=' . ($this->lastResponseHeaders ?? ''));
        error_log('[CFDI40][GenerarCFDI40] last_response_xml=' . ($this->lastResponse ?? ''));
    }

    private function validateSerializedRequest(?string $xml, bool $strict = true): array
    {
        $status = [
            'is_valid' => false,
            'reason' => 'unknown',
            'soap_body_has_payload' => false,
            'body_contains_credenciales' => false,
            'body_contains_comprobante' => false,
            'body_contains_nested_comprobante_wrapper' => false,
            'body_has_empty_method' => false,
        ];

        if ($xml === null || trim($xml) === '') {
            $status['reason'] = 'no_last_request_xml';
            $this->logValidationStatus($status);
            if ($strict) {
                throw new RuntimeException('No hay XML SOAP serializado en __getLastRequest().');
            }
            return $status;
        }

        $doc = new DOMDocument();
        $loaded = @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($loaded) {
            $xpath = new DOMXPath($doc);
            $payloadNodes = $xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]/*');
            if ($payloadNodes instanceof DOMNodeList && $payloadNodes->length > 0) {
                $status['soap_body_has_payload'] = true;
                $status['body_contains_credenciales'] = $xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]//*[local-name()="Credenciales" or local-name()="credenciales"]')->length > 0;
                $status['body_contains_comprobante'] = $xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]//*[local-name()="Comprobante40R" or local-name()="comprobante" or local-name()="Comprobante"]')->length > 0;
                $status['body_contains_nested_comprobante_wrapper'] = $xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]//*[local-name()="Comprobante40R"]/*[local-name()="Comprobante40R"]')->length > 0;

                $methodNode = $payloadNodes->item(0);
                if ($methodNode instanceof DOMNode && !$methodNode->hasChildNodes()) {
                    $status['body_has_empty_method'] = true;
                    $status['soap_body_has_payload'] = false;
                }
            }
        } else {
            $status['soap_body_has_payload'] = strpos($xml, '<GenerarCFDI40/>') === false;
            $status['body_contains_credenciales'] = stripos($xml, 'Credenciales') !== false || stripos($xml, 'credenciales') !== false;
            $status['body_contains_comprobante'] = stripos($xml, 'Comprobante40R') !== false || stripos($xml, 'comprobante') !== false;
            $status['body_contains_nested_comprobante_wrapper'] = stripos($xml, '<Comprobante40R><Comprobante40R>') !== false;
            $status['body_has_empty_method'] = strpos($xml, '<GenerarCFDI40/>') !== false;
        }

        $status['is_valid'] =
            $status['soap_body_has_payload']
            && $status['body_contains_credenciales']
            && $status['body_contains_comprobante']
            && !$status['body_contains_nested_comprobante_wrapper']
            && !$status['body_has_empty_method'];

        if ($status['is_valid']) {
            $status['reason'] = 'ok';
        } elseif ($status['body_has_empty_method']) {
            $status['reason'] = 'empty_method_node';
        } elseif (!$status['soap_body_has_payload']) {
            $status['reason'] = 'missing_payload_children';
        } elseif (!$status['body_contains_credenciales']) {
            $status['reason'] = 'missing_credenciales';
        } elseif (!$status['body_contains_comprobante']) {
            $status['reason'] = 'missing_comprobante';
        } elseif ($status['body_contains_nested_comprobante_wrapper']) {
            $status['reason'] = 'nested_comprobante_wrapper';
        }

        $this->logValidationStatus($status);

        if (!$status['is_valid'] && $strict) {
            throw new RuntimeException('El XML SOAP de GenerarCFDI40 no contiene payload serializado válido (Credenciales/comprobante) o contiene anidación inválida de Comprobante40R. reason=' . $status['reason']);
        }

        return $status;
    }

    private function logValidationStatus(array $status): void
    {
        error_log('[CFDI40][GenerarCFDI40] soap_body_has_payload=' . ($status['soap_body_has_payload'] ? 'true' : 'false'));
        error_log('[CFDI40][GenerarCFDI40] body_contains_credenciales=' . ($status['body_contains_credenciales'] ? 'true' : 'false'));
        error_log('[CFDI40][GenerarCFDI40] body_contains_comprobante=' . ($status['body_contains_comprobante'] ? 'true' : 'false'));
        error_log('[CFDI40][GenerarCFDI40] body_contains_nested_comprobante_wrapper=' . ($status['body_contains_nested_comprobante_wrapper'] ? 'true' : 'false'));
        error_log('[CFDI40][GenerarCFDI40] body_has_empty_method=' . ($status['body_has_empty_method'] ? 'true' : 'false'));
        error_log('[CFDI40][GenerarCFDI40] soap_serialization_validation_reason=' . ($status['reason'] ?? 'unknown'));
    }

    private function normalizeComplexNode($value)
    {
        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                $obj = new stdClass();
                foreach ($value as $k => $v) {
                    $obj->{$k} = $this->normalizeComplexNode($v);
                }
                return $obj;
            }

            $list = [];
            foreach ($value as $item) {
                $list[] = $this->normalizeComplexNode($item);
            }
            return $list;
        }

        if (is_object($value)) {
            $obj = new stdClass();
            foreach (get_object_vars($value) as $k => $v) {
                $obj->{$k} = $this->normalizeComplexNode($v);
            }
            return $obj;
        }

        return $value;
    }

    private function normalizeListNode($value): array
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return [];
        }

        if ($this->isAssoc($value)) {
            return [$this->toAssocArray($value)];
        }

        $list = [];
        foreach ($value as $item) {
            if (!is_array($item) && !is_object($item)) {
                continue;
            }
            $list[] = $this->toAssocArray($item);
        }

        return $list;
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
        foreach ($value as $k => $v) {
            if (is_array($v) || is_object($v)) {
                if (is_array($v) && !$this->isAssoc($v)) {
                    $items = [];
                    foreach ($v as $item) {
                        $items[] = (is_array($item) || is_object($item)) ? $this->toAssocArray($item) : $item;
                    }
                    $out[$k] = $items;
                    continue;
                }
                $out[$k] = $this->toAssocArray($v);
                continue;
            }
            $out[$k] = $v;
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

    private function isTypeInWsdl(array $types, string $typeName): bool
    {
        foreach ($types as $typeDef) {
            if (!is_string($typeDef)) {
                continue;
            }
            if (stripos($typeDef, "struct {$typeName}") !== false || stripos($typeDef, " {$typeName} ") !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalizeForDebug($value)
    {
        if ($value instanceof SoapParam) {
            return [
                '__soap_param_name' => $value->param_name ?? null,
                '__soap_param_data' => $this->normalizeForDebug($value->param_data ?? null),
            ];
        }

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
