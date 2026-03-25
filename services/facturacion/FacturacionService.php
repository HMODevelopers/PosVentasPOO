<?php

require_once __DIR__ . '/FacturacionSchemaHelper.php';
require_once __DIR__ . '/FacturacionLogger.php';
require_once __DIR__ . '/FacturacionSoapClient.php';
require_once __DIR__ . '/FacturacionPayloadBuilder.php';
require_once __DIR__ . '/FacturacionPayloadAudit.php';
require_once __DIR__ . '/FacturacionResponseMapper.php';
require_once __DIR__ . '/FacturacionValidator.php';
require_once __DIR__ . '/../../models/FacturacionModel.php';

class FacturacionService
{
    private PDO $conn;
    private FacturacionModel $model;
    private FacturacionSchemaHelper $schema;
    private FacturacionLogger $logger;
    private FacturacionValidator $validator;
    private FacturacionPayloadBuilder $payloadBuilder;
    private FacturacionPayloadAudit $payloadAudit;
    private ?FacturacionSoapClient $soapClient = null;
    private FacturacionResponseMapper $responseMapper;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->schema = new FacturacionSchemaHelper($conn);
        $this->model = new FacturacionModel($conn, $this->schema);
        $this->logger = new FacturacionLogger($conn, $this->schema);
        $this->validator = new FacturacionValidator();
        $this->payloadBuilder = new FacturacionPayloadBuilder();
        $this->payloadAudit = new FacturacionPayloadAudit();
        $this->responseMapper = new FacturacionResponseMapper();
    }

    public function facturarVenta(int $idVenta, array $facturacionInput = []): array
    {
        try {
            $ctx = $this->model->loadContext($idVenta);
            if (!empty($facturacionInput)) {
                $idClienteSat = (int)($facturacionInput['id_cliente_sat'] ?? 0);
                $ctx = $this->model->aplicarDatosFacturacionExistente($ctx, $idClienteSat, $facturacionInput);
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }

        $errors = $this->validator->validate($ctx);

        if ($errors) {
            $message = implode(' ', $errors);
            $cfdi = $this->model->createOrGetCfdiRecord($idVenta, [
                'estatus' => 'ERROR',
                'mensaje_respuesta' => $message,
            ]);
            $this->logger->log([
                'id_cfdi' => $cfdi['id_cfdi'] ?? null,
                'id_venta' => $idVenta,
                'tipo_evento' => 'ERROR',
                'operacion_exitosa' => 0,
                'codigo_interno' => $this->guessValidationCode($errors),
                'mensaje_general' => 'Validación de facturación fallida.',
                'mensaje_detallado' => $message,
                'origen_error' => 'VALIDACION',
            ]);
            return ['ok' => false, 'msg' => $message, 'cfdi' => $cfdi];
        }

        $referencia = $this->model->buildReferencia($ctx['venta']);
        $cfdi = $this->model->createOrGetCfdiRecord($idVenta, [
            'estatus' => 'BORRADOR',
            'referencia' => $referencia,
            'mensaje_respuesta' => 'Factura en proceso.',
        ]);

        $ctx['referencia'] = $cfdi['referencia'] ?? $referencia;
        $ctx['fecha_emision'] = date('Y-m-d\TH:i:s');
        $payloadJson = null;
        error_log('[FACTURACION][ctx-normalizado] ' . json_encode([
            'id_venta' => $idVenta,
            'referencia' => $ctx['referencia'],
            'totales' => $ctx['totales'] ?? [],
            'forma_pago' => $ctx['forma_pago'] ?? [],
            'conceptos_count' => count($ctx['conceptos'] ?? []),
            'primer_concepto' => ($ctx['conceptos'][0] ?? null),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $payload = $this->payloadBuilder->build($ctx, $this->soapClient()->getConfig());
            $auditReport = $this->payloadAudit->validate($payload);
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $auditJson = json_encode($auditReport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->model->updateCfdiRecord((int)$cfdi['id_cfdi'], [
                'estatus' => 'BORRADOR',
                'request_payload' => $payloadJson,
                'referencia' => $ctx['referencia'],
            ]);

            $this->logger->log([
                'id_cfdi' => $cfdi['id_cfdi'] ?? null,
                'id_venta' => $idVenta,
                'tipo_evento' => 'REQUEST',
                'operacion_exitosa' => 1,
                'codigo_interno' => 'REQUEST_GENERAR_CFDI40',
                'mensaje_general' => 'Solicitud enviada a Folios Digitales.',
                'request_payload' => $payloadJson,
            ]);

            $this->logger->log([
                'id_cfdi' => $cfdi['id_cfdi'] ?? null,
                'id_venta' => $idVenta,
                'tipo_evento' => $auditReport['has_errors'] ? 'ERROR' : 'DEBUG',
                'operacion_exitosa' => $auditReport['has_errors'] ? 0 : 1,
                'codigo_interno' => $auditReport['has_errors'] ? 'REQUEST_AUDIT_FAIL' : 'REQUEST_AUDIT_OK',
                'metodo_servicio' => 'GenerarCFDI40',
                'mensaje_general' => 'Auditoría temporal de payload previa al __soapCall.',
                'mensaje_detallado' => $auditReport['has_errors']
                    ? implode(' ', $auditReport['errors'])
                    : 'Payload sin faltantes obligatorios, nulos o vacíos.',
                'request_payload' => $payloadJson,
                'response_payload' => $auditJson,
            ]);

            if ($auditReport['has_errors']) {
                throw new RuntimeException('Payload inválido para GenerarCFDI40. ' . implode(' ', $auditReport['errors']));
            }

            error_log('[CFDI40][GenerarCFDI40] payload=' . $payloadJson);
            error_log('[CFDI40][GenerarCFDI40][totales_payload] ' . json_encode([
                'subtotal_fiscal_base' => (float)($payload['cfdi']['SubTotal'] ?? 0),
                'impuestos_calculados' => (float)array_reduce(
                    $payload['cfdi']['Conceptos']['Concepto40R'] ?? [],
                    static function (float $carry, array $concepto): float {
                        $traslados = $concepto['Impuestos']['Traslados']['TrasladoConcepto40R'] ?? [];
                        if (isset($traslados['Importe'])) {
                            return $carry + (float)$traslados['Importe'];
                        }
                        foreach ((array)$traslados as $traslado) {
                            if (is_array($traslado)) {
                                $carry += (float)($traslado['Importe'] ?? 0);
                            }
                        }
                        return $carry;
                    },
                    0.0
                ),
                'total_final' => (float)($payload['cfdi']['Total'] ?? 0),
                'conceptos' => array_map(static function (array $concepto): array {
                    $traslados = $concepto['Impuestos']['Traslados']['TrasladoConcepto40R'] ?? [];
                    if (is_array($traslados) && isset($traslados['Base'])) {
                        $traslado = $traslados;
                    } else {
                        $traslado = is_array($traslados) && isset($traslados[0]) && is_array($traslados[0]) ? $traslados[0] : [];
                    }
                    return [
                        'valor_unitario' => (float)($concepto['ValorUnitario'] ?? 0),
                        'importe' => (float)($concepto['Importe'] ?? 0),
                        'base_traslado' => (float)($traslado['Base'] ?? 0),
                        'importe_traslado' => (float)($traslado['Importe'] ?? 0),
                    ];
                }, $payload['cfdi']['Conceptos']['Concepto40R'] ?? []),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            error_log('[CFDI40][GenerarCFDI40] auditoria=' . $auditJson);

            $soapResult = $this->soapClient()->timbrar($payload);
            error_log('[CFDI40][GenerarCFDI40] soap_call_mode=' . ($soapResult['soap_call_mode'] ?? 'unknown'));
            error_log('[CFDI40][GenerarCFDI40] wsdl_signature_generar_cfdi40=' . ($soapResult['wsdl_signature_generar_cfdi40'] ?? ''));
            error_log('[CFDI40][GenerarCFDI40] wsdl_param_names_generar_cfdi40=' . json_encode($soapResult['wsdl_param_names_generar_cfdi40'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            error_log('[CFDI40][GenerarCFDI40] wsdl_functions=' . json_encode($soapResult['wsdl_functions'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            error_log('[CFDI40][GenerarCFDI40] wsdl_types=' . json_encode($soapResult['wsdl_types'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            error_log('[CFDI40][GenerarCFDI40] last_request_headers=' . ($soapResult['last_request_headers'] ?? ''));
            error_log('[CFDI40][GenerarCFDI40] last_request_xml=' . ($soapResult['last_request_xml'] ?? $soapResult['last_request'] ?? ''));
            error_log('[CFDI40][GenerarCFDI40] last_response_headers=' . ($soapResult['last_response_headers'] ?? ''));
            error_log('[CFDI40][GenerarCFDI40] last_response_xml=' . ($soapResult['last_response_xml'] ?? $soapResult['last_response'] ?? ''));
            $mapped = $this->responseMapper->map($soapResult['response'], $soapResult);
            error_log('[CFDI40][GenerarCFDI40] response_node_path=' . ($mapped['response_node_path'] ?? 'unknown'));
            error_log('[CFDI40][GenerarCFDI40] error_detallado=' . (string)($mapped['mensaje_detallado'] ?? ''));
            $responsePayloadJson = $mapped['raw_response_json'] ?? json_encode($soapResult['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->guardarDebugSoap([
                'id_venta' => $idVenta,
                'id_cfdi' => $cfdi['id_cfdi'] ?? null,
                'metodo_servicio' => 'GenerarCFDI40',
                'request_payload' => $payloadJson,
                'response_payload' => $responsePayloadJson,
                'last_request' => $soapResult['last_request'] ?? null,
                'last_response' => $soapResult['last_response'] ?? null,
                'soap_fault_full' => null,
                'faultcode' => null,
                'faultstring' => null,
                'response_object' => print_r($soapResult['response'], true),
                'response_json' => json_encode($soapResult['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'response_node_path' => $mapped['response_node_path'] ?? null,
                'operacion_exitosa' => $mapped['operacion_exitosa'] ? 1 : 0,
                'error_general' => $mapped['operacion_exitosa'] ? null : ($mapped['mensaje_respuesta'] ?? null),
                'error_detallado' => $mapped['operacion_exitosa'] ? null : ($mapped['mensaje_detallado'] ?? null),
            ]);

            $estatus = $mapped['operacion_exitosa'] ? 'TIMBRADO' : 'ERROR';
            $this->model->updateCfdiRecord((int)$cfdi['id_cfdi'], [
                'referencia' => $mapped['referencia'] ?: $ctx['referencia'],
                'uuid' => $mapped['uuid'],
                'estatus' => $estatus,
                'fecha_timbrado' => $mapped['fecha_timbrado'],
                'xml_timbrado' => $mapped['xml_timbrado'],
                'pdf_base64' => $mapped['pdf_base64'],
                'codigo_respuesta' => $mapped['codigo_respuesta'],
                'mensaje_respuesta' => $mapped['mensaje_respuesta'] ?: ($mapped['operacion_exitosa'] ? 'CFDI timbrado correctamente.' : 'El servicio devolvió un error.'),
                'request_payload' => $payloadJson,
                'response_payload' => $mapped['raw_response_json'],
            ]);

            $this->logger->log([
                'id_cfdi' => $cfdi['id_cfdi'] ?? null,
                'id_venta' => $idVenta,
                'tipo_evento' => $mapped['operacion_exitosa'] ? 'RESPONSE' : 'ERROR',
                'operacion_exitosa' => $mapped['operacion_exitosa'] ? 1 : 0,
                'codigo_interno' => $mapped['operacion_exitosa'] ? 'TIMBRADO_OK' : 'ERROR_NEGOCIO',
                'mensaje_general' => $mapped['mensaje_respuesta'] ?: ($mapped['operacion_exitosa'] ? 'CFDI timbrado correctamente.' : 'Error funcional del servicio.'),
                'mensaje_detallado' => $mapped['mensaje_detallado'] ?? null,
                'request_payload' => $payloadJson,
                'response_payload' => $mapped['raw_response_json'],
                'origen_error' => $mapped['operacion_exitosa'] ? null : 'NEGOCIO',
            ]);

            return [
                'ok' => $mapped['operacion_exitosa'],
                'msg' => $mapped['mensaje_respuesta'] ?: ($mapped['operacion_exitosa'] ? 'CFDI timbrado correctamente.' : 'No fue posible timbrar el CFDI.'),
                'cfdi' => $this->model->getCfdiByVenta($idVenta),
                'response' => $mapped['raw_response_array'],
            ];
        } catch (SoapFault $e) {
            $lastRequest = $this->soapClient()->getLastRequest();
            $lastResponse = $this->soapClient()->getLastResponse();
            $soapFaultDebug = [
                'faultcode' => $e->faultcode ?? null,
                'faultcodens' => $e->faultcodens ?? null,
                'faultstring' => $e->faultstring ?? null,
                'detail' => $e->detail ?? null,
                'message' => $e->getMessage(),
            ];
            error_log('[CFDI40][GenerarCFDI40] soap_fault_full=' . print_r($e, true));
            error_log('[CFDI40][GenerarCFDI40] soap_fault=' . json_encode($soapFaultDebug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            error_log('[CFDI40][GenerarCFDI40] last_request=' . ($lastRequest ?? ''));
            error_log('[CFDI40][GenerarCFDI40] last_response=' . ($lastResponse ?? ''));

            $this->guardarDebugSoap([
                'id_venta' => $idVenta,
                'id_cfdi' => $cfdi['id_cfdi'] ?? null,
                'metodo_servicio' => 'GenerarCFDI40',
                'request_payload' => $payloadJson,
                'response_payload' => null,
                'last_request' => $lastRequest,
                'last_response' => $lastResponse,
                'soap_fault_full' => print_r($e, true),
                'faultcode' => $e->faultcode ?? null,
                'faultstring' => $e->faultstring ?? null,
                'response_object' => null,
                'response_json' => null,
                'response_node_path' => null,
                'operacion_exitosa' => 0,
                'error_general' => 'SOAP Fault al consumir GenerarCFDI40.',
                'error_detallado' => $e->getMessage(),
            ]);

            return $this->handleException($cfdi, $idVenta, $payloadJson, $e, 'SOAP', 'SOAP_ERROR');
        } catch (Throwable $e) {
            return $this->handleException($cfdi, $idVenta, $payloadJson, $e, 'SISTEMA', 'ERROR_DESCONOCIDO');
        }
    }


    private function soapClient(): FacturacionSoapClient
    {
        if (!$this->soapClient instanceof FacturacionSoapClient) {
            $this->soapClient = new FacturacionSoapClient();
        }
        return $this->soapClient;
    }

    private function handleException(array $cfdi, int $idVenta, ?string $payloadJson, Throwable $e, string $origen, string $codigo): array
    {
        $this->model->updateCfdiRecord((int)$cfdi['id_cfdi'], [
            'estatus' => 'ERROR',
            'mensaje_respuesta' => $e->getMessage(),
            'request_payload' => $payloadJson,
        ]);

        $this->logger->log([
            'id_cfdi' => $cfdi['id_cfdi'] ?? null,
            'id_venta' => $idVenta,
            'tipo_evento' => 'ERROR',
            'operacion_exitosa' => 0,
            'codigo_interno' => $codigo,
            'mensaje_general' => 'Error al consumir Folios Digitales.',
            'mensaje_detallado' => $e->getMessage(),
            'request_payload' => $payloadJson,
            'raw_error' => $e->getTraceAsString(),
            'origen_error' => $origen,
        ]);

        return [
            'ok' => false,
            'msg' => 'No fue posible facturar la venta. ' . $e->getMessage(),
            'cfdi' => $this->model->getCfdiByVenta($idVenta),
        ];
    }

    private function guessValidationCode(array $errors): string
    {
        $msg = strtolower(implode(' ', $errors));
        if (strpos($msg, 'receptor') !== false || strpos($msg, 'rfc') !== false || strpos($msg, 'uso cfdi') !== false) {
            return 'VALIDACION_RECEPTOR';
        }
        if (strpos($msg, 'concepto') !== false) {
            return 'VALIDACION_CONCEPTOS';
        }
        if (strpos($msg, 'emisor') !== false) {
            return 'VALIDACION_EMISOR';
        }
        return 'VALIDACION_VENTA';
    }

    private function guardarDebugSoap(array $debug): void
    {
        $sql = 'INSERT INTO ventas_cfdi_debug_soap (
            id_venta,
            id_cfdi,
            metodo_servicio,
            request_payload,
            response_payload,
            last_request,
            last_response,
            soap_fault_full,
            faultcode,
            faultstring,
            response_object,
            response_json,
            response_node_path,
            operacion_exitosa,
            error_general,
            error_detallado
        ) VALUES (
            :id_venta,
            :id_cfdi,
            :metodo_servicio,
            :request_payload,
            :response_payload,
            :last_request,
            :last_response,
            :soap_fault_full,
            :faultcode,
            :faultstring,
            :response_object,
            :response_json,
            :response_node_path,
            :operacion_exitosa,
            :error_general,
            :error_detallado
        )';

        try {
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':id_venta' => $debug['id_venta'] ?? null,
                ':id_cfdi' => $debug['id_cfdi'] ?? null,
                ':metodo_servicio' => $debug['metodo_servicio'] ?? null,
                ':request_payload' => $debug['request_payload'] ?? null,
                ':response_payload' => $debug['response_payload'] ?? null,
                ':last_request' => $debug['last_request'] ?? null,
                ':last_response' => $debug['last_response'] ?? null,
                ':soap_fault_full' => $debug['soap_fault_full'] ?? null,
                ':faultcode' => $debug['faultcode'] ?? null,
                ':faultstring' => $debug['faultstring'] ?? null,
                ':response_object' => $debug['response_object'] ?? null,
                ':response_json' => $debug['response_json'] ?? null,
                ':response_node_path' => $debug['response_node_path'] ?? null,
                ':operacion_exitosa' => $debug['operacion_exitosa'] ?? null,
                ':error_general' => $debug['error_general'] ?? null,
                ':error_detallado' => $debug['error_detallado'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('[CFDI40][GenerarCFDI40] no se pudo guardar debug SOAP: ' . $e->getMessage());
        }
    }
}
