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
            error_log('[CFDI40][GenerarCFDI40] auditoria=' . $auditJson);

            $soapResult = $this->soapClient()->timbrar($payload);
            $mapped = $this->responseMapper->map($soapResult['response'], $soapResult);

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

    private function handleException(array $cfdi, int $idVenta, string $payloadJson, Throwable $e, string $origen, string $codigo): array
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
}
