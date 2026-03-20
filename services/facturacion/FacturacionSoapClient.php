<?php

class FacturacionSoapClient
{
    private array $config;
    private ?SoapClient $client = null;

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
        $response = $client->__soapCall('GenerarCFDI40', [$payload]);

        return [
            'response' => $response,
            'last_request' => method_exists($client, '__getLastRequest') ? $client->__getLastRequest() : null,
            'last_response' => method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : null,
        ];
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

    private function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return ($value === false || $value === null || $value === '') ? $default : $value;
    }
}
