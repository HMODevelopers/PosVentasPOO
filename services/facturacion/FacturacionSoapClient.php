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
            $usedPlan = $plan;
            error_log('[CFDI40][GenerarCFDI40] soap_call_mode=' . ($plan['mode'] ?? 'unknown'));
            try {
                $response = $client->__soapCall('GenerarCFDI40', $plan['arguments']);
                $this->captureLastSoapExchange($client);
                $this->validateSerializedRequest($this->lastRequest);
                error_log('[CFDI40][GenerarCFDI40] response_json=' . json_encode($this->normalizeForDebug($response), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                break;
            } catch (SoapFault $fault) {
                $lastFault = $fault;
                $this->captureLastSoapExchange($client);
                $this->validateSerializedRequest($this->lastRequest, false);
                error_log('[CFDI40][GenerarCFDI40] soap_fault_attempt_mode=' . ($plan['mode'] ?? 'unknown'));
                error_log('[CFDI40][GenerarCFDI40] soap_fault=' . print_r($fault, true));
            }
        }

        if ($response === null && $lastFault instanceof SoapFault) {
            throw $lastFault;
        }

        return [
            'response' => $response,
            'last_request' => $this->lastRequest,
            'last_response' => $this->lastResponse,
            'last_request_headers' => $this->lastRequestHeaders,
            'last_response_headers' => $this->lastResponseHeaders,
            'soap_call_mode' => $usedPlan['mode'] ?? null,
            'wsdl_signature_generar_cfdi40' => $wsdlMeta['signature'] ?? null,
            'wsdl_param_names_generar_cfdi40' => $wsdlMeta['param_names'] ?? [],
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
        $credenciales = $payload['Credenciales'] ?? $payload['credenciales'] ?? [];
        $comprobante = $payload['Comprobante40R'] ?? $payload['comprobante'] ?? [];

        $nestedCandidates = [
            'Emisor',
            'Receptor',
            'Conceptos',
            'InformacionGlobal',
            'CfdiRelacionados40R',
        ];

        foreach ($nestedCandidates as $node) {
            if (array_key_exists($node, $payload) && !array_key_exists($node, $comprobante)) {
                $comprobante[$node] = $payload[$node];
            }
        }

        $normalized = [
            'Credenciales' => $this->normalizeComplexNode($credenciales),
            'Comprobante40R' => $this->normalizeComplexNode($comprobante),
        ];

        if ($this->isTypeInWsdl($wsdlMeta['types'] ?? [], 'Comprobante40R')) {
            $normalized['Comprobante40R'] = $this->normalizeComprobanteNode($normalized['Comprobante40R']);
        }

        return $normalized;
    }

    private function normalizeComprobanteNode($comprobante)
    {
        if (!is_object($comprobante) && !is_array($comprobante)) {
            return $comprobante;
        }

        $array = is_object($comprobante) ? get_object_vars($comprobante) : $comprobante;

        if (isset($array['Conceptos']) && is_array($array['Conceptos']) && isset($array['Conceptos']['Concepto40R'])) {
            $conceptos = $array['Conceptos']['Concepto40R'];
            if (is_array($conceptos) && $this->isAssoc($conceptos)) {
                $array['Conceptos']['Concepto40R'] = [$conceptos];
            }
        }

        if (isset($array['Conceptos']['Concepto40R']) && is_array($array['Conceptos']['Concepto40R'])) {
            foreach ($array['Conceptos']['Concepto40R'] as $i => $concepto) {
                if (!is_array($concepto) && !is_object($concepto)) {
                    continue;
                }

                $c = is_object($concepto) ? get_object_vars($concepto) : $concepto;
                foreach (['TrasladoConcepto40R', 'RetencionConcepto40R', 'RetencionLocal40R', 'TrasladoLocal40R'] as $taxNode) {
                    if (!isset($c[$taxNode]) || !is_array($c[$taxNode])) {
                        continue;
                    }
                    if ($this->isAssoc($c[$taxNode])) {
                        $c[$taxNode] = [$c[$taxNode]];
                    }
                }
                $array['Conceptos']['Concepto40R'][$i] = $c;
            }
        }

        return $this->normalizeComplexNode($array);
    }

    private function buildCallPlans(array $payload, array $wsdlMeta): array
    {
        $paramNames = $wsdlMeta['param_names'] ?? [];
        $credName = $paramNames[0] ?? 'credenciales';
        $compName = $paramNames[1] ?? 'comprobante';

        $credenciales = $payload['Credenciales'] ?? [];
        $comprobante = $payload['Comprobante40R'] ?? [];

        $plans = [];

        $plans[] = [
            'mode' => 'multiparam',
            'arguments' => [[
                new SoapParam($credenciales, $credName),
                new SoapParam($comprobante, $compName),
            ]],
        ];

        if (!empty($paramNames)) {
            $wrappedPayload = new stdClass();
            $wrappedPayload->{$credName} = $credenciales;
            $wrappedPayload->{$compName} = $comprobante;
            $plans[] = [
                'mode' => 'wrapped_document_literal',
                'arguments' => [[$wrappedPayload]],
            ];
        }

        return $plans;
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

    private function validateSerializedRequest(?string $xml, bool $strict = true): void
    {
        if ($xml === null || trim($xml) === '') {
            error_log('[CFDI40][GenerarCFDI40] soap_body_has_payload=false');
            error_log('[CFDI40][GenerarCFDI40] body_contains_credenciales=false');
            error_log('[CFDI40][GenerarCFDI40] body_contains_comprobante=false');
            if ($strict) {
                throw new RuntimeException('No hay XML SOAP serializado en __getLastRequest().');
            }
            return;
        }

        $soapBodyHasPayload = false;
        $bodyContainsCredenciales = false;
        $bodyContainsComprobante = false;

        $doc = new DOMDocument();
        $loaded = @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($loaded) {
            $xpath = new DOMXPath($doc);
            $payloadNodes = $xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]/*');
            if ($payloadNodes instanceof DOMNodeList && $payloadNodes->length > 0) {
                $soapBodyHasPayload = true;
                $bodyContainsCredenciales = $xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]//*[local-name()="Credenciales" or local-name()="credenciales"]')->length > 0;
                $bodyContainsComprobante = $xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]//*[local-name()="Comprobante40R" or local-name()="comprobante"]')->length > 0;

                $methodNode = $payloadNodes->item(0);
                if ($methodNode instanceof DOMNode && !$methodNode->hasChildNodes()) {
                    $soapBodyHasPayload = false;
                }
            }
        } else {
            $soapBodyHasPayload = strpos($xml, '<GenerarCFDI40/>') === false;
            $bodyContainsCredenciales = stripos($xml, 'Credenciales') !== false;
            $bodyContainsComprobante = stripos($xml, 'Comprobante40R') !== false;
        }

        error_log('[CFDI40][GenerarCFDI40] soap_body_has_payload=' . ($soapBodyHasPayload ? 'true' : 'false'));
        error_log('[CFDI40][GenerarCFDI40] body_contains_credenciales=' . ($bodyContainsCredenciales ? 'true' : 'false'));
        error_log('[CFDI40][GenerarCFDI40] body_contains_comprobante=' . ($bodyContainsComprobante ? 'true' : 'false'));

        if (!$soapBodyHasPayload || !$bodyContainsCredenciales || !$bodyContainsComprobante) {
            if ($strict) {
                throw new RuntimeException('El XML SOAP de GenerarCFDI40 no contiene payload serializado válido (Credenciales/Comprobante40R).');
            }
        }
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
