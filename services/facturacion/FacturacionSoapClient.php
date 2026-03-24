<?php

class FacturacionSoapClient
{
    private array $config;
    private ?SoapClient $client = null;
    private ?string $lastRequest = null;
    private ?string $lastResponse = null;

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
        error_log('[CFDI40][GenerarCFDI40] payload_pre_soapcall=' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $signature = $this->describeMethodSignature($client, 'GenerarCFDI40');
        $callContext = $this->buildGenerarCfdi40CallContext($payload, $signature);
        error_log('[CFDI40][GenerarCFDI40] wsdl_signature=' . ($signature['signature'] ?? 'not_available'));
        error_log('[CFDI40][GenerarCFDI40] wsdl_param=' . ($callContext['param_name'] ?? 'none'));

        try {
            $response = $client->__soapCall('GenerarCFDI40', $callContext['arguments']);
            $this->lastRequest = method_exists($client, '__getLastRequest') ? $client->__getLastRequest() : null;
            $this->lastResponse = method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null;
            error_log('[CFDI40][GenerarCFDI40] response_object=' . print_r($response, true));
            error_log('[CFDI40][GenerarCFDI40] response_json=' . json_encode($this->normalizeForDebug($response), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->validateSerializedRequest($this->lastRequest);
        } catch (SoapFault $fault) {
            $this->lastRequest = method_exists($client, '__getLastRequest') ? $client->__getLastRequest() : null;
            $this->lastResponse = method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null;
            error_log('[CFDI40][GenerarCFDI40] soap_fault_object=' . print_r($fault, true));
            $this->validateSerializedRequest($this->lastRequest);
            throw $fault;
        }

        return [
            'response' => $response,
            'last_request' => $this->lastRequest,
            'last_response' => $this->lastResponse,
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


    private function describeMethodSignature(SoapClient $client, string $method): array
    {
        $result = [
            'signature' => null,
            'param_name' => null,
        ];

        if (!method_exists($client, '__getFunctions')) {
            return $result;
        }

        try {
            $functions = $client->__getFunctions();
        } catch (Throwable $e) {
            return $result;
        }

        if (!is_array($functions)) {
            return $result;
        }

        foreach ($functions as $signature) {
            if (!is_string($signature) || stripos($signature, $method . '(') === false) {
                continue;
            }

            $result['signature'] = $signature;
            if (preg_match('/\((.*)\)/', $signature, $matches)) {
                $params = trim($matches[1]);
                if ($params !== '' && stripos($params, 'void') !== 0) {
                    if (preg_match('/(?:^|,\s*)(?:[\w\\]+\s+)?(\w+)\s*$/', $params, $paramMatch)) {
                        $result['param_name'] = $paramMatch[1];
                    }
                }
            }

            return $result;
        }

        return $result;
    }

    private function buildGenerarCfdi40CallContext(array $payload, array $signature): array
    {
        $paramName = $signature['param_name'] ?? null;

        if ($paramName === null || $paramName === '') {
            $paramName = 'request';
        }

        $argument = new SoapParam($this->arrayToObject($payload), $paramName);

        return [
            'param_name' => $paramName,
            'arguments' => [[$paramName => $argument]],
        ];
    }

    private function arrayToObject($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if (!$isAssoc) {
            return array_map([$this, 'arrayToObject'], $value);
        }

        $obj = new stdClass();
        foreach ($value as $key => $item) {
            $obj->{$key} = $this->arrayToObject($item);
        }

        return $obj;
    }

    private function validateSerializedRequest(?string $xml): void
    {
        if ($xml === null || trim($xml) === '') {
            error_log('[CFDI40][GenerarCFDI40] soap_request_validation=No hay XML en __getLastRequest().');
            return;
        }

        $requiredNodes = [
            'Credenciales',
            'Usuario',
            'Cuenta',
            'Password',
            'Emisor',
            'Receptor',
            'Conceptos',
            'Concepto40R',
            'Comprobante40R',
        ];
        $missing = [];
        foreach ($requiredNodes as $node) {
            if (!$this->containsXmlNode($xml, $node)) {
                $missing[] = $node;
            }
        }

        $badCredentialCasing = [];
        foreach (['usuario', 'cuenta', 'password', 'UsuarioRemoto', 'user', 'pass'] as $badNode) {
            if ($this->containsXmlNode($xml, $badNode)) {
                $badCredentialCasing[] = $badNode;
            }
        }

        if ($missing || $badCredentialCasing) {
            $errors = [];
            if ($missing) {
                $errors[] = 'nodos faltantes en XML: ' . implode(', ', $missing);
            }
            if ($badCredentialCasing) {
                $errors[] = 'nodos de credenciales inválidos detectados: ' . implode(', ', $badCredentialCasing);
            }
            error_log('[CFDI40][GenerarCFDI40] soap_request_validation=WARNING ' . implode('. ', $errors) . '.');
            return;
        }

        error_log('[CFDI40][GenerarCFDI40] soap_request_validation=OK nodos y casing preservados.');
    }

    private function containsXmlNode(string $xml, string $node): bool
    {
        $doc = new DOMDocument();
        $loaded = @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($loaded) {
            $xpath = new DOMXPath($doc);
            $query = "//*[local-name()='{$node}']";
            $nodes = $xpath->query($query);
            return $nodes instanceof DOMNodeList && $nodes->length > 0;
        }

        return (bool)preg_match('/<([a-zA-Z0-9_]+:)?' . preg_quote($node, '/') . '(\\s|>|\\/)/u', $xml);
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
