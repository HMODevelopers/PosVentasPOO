<?php

require_once __DIR__ . '/FacturacionSchemaHelper.php';

class FacturacionLogger
{
    private PDO $conn;
    private FacturacionSchemaHelper $schema;

    public function __construct(PDO $conn, FacturacionSchemaHelper $schema)
    {
        $this->conn = $conn;
        $this->schema = $schema;
    }

    public function log(array $data): void
    {
        if (!$this->schema->tableExists('ventas_cfdi_logs')) {
            return;
        }

        $debugEnabled = defined('CFDI_LOGGER_DEBUG') && CFDI_LOGGER_DEBUG === true;

        $rawTipoEvento = (string)($data['tipo_evento'] ?? 'INFO');
        $cleanTipoEvento = preg_replace('/[\x00-\x1F\x7F\x{00A0}]+/u', '', $rawTipoEvento);
        $tipoEvento = trim((string)$cleanTipoEvento);
        $allowedTipoEvento = ['REQUEST', 'RESPONSE', 'ERROR', 'INFO'];
        if (!in_array($tipoEvento, $allowedTipoEvento, true)) {
            $tipoEvento = 'INFO';
        }

        $origenError = $data['origen_error'] ?? null;
        if ($origenError !== null) {
            $origenError = trim((string)preg_replace('/[\x00-\x1F\x7F\x{00A0}]+/u', '', (string)$origenError));
            $allowedOrigenError = ['SOAP', 'VALIDACION', 'NEGOCIO', 'SISTEMA'];
            if (!in_array($origenError, $allowedOrigenError, true)) {
                $origenError = null;
            }
        }

        $payload = $this->schema->filterData('ventas_cfdi_logs', [
            'id_cfdi' => $data['id_cfdi'] ?? null,
            'id_venta' => $data['id_venta'] ?? null,
            'metodo_servicio' => $data['metodo_servicio'] ?? 'GenerarCFDI40',
            'tipo_evento' => $tipoEvento,
            'operacion_exitosa' => isset($data['operacion_exitosa']) ? (int)(bool)$data['operacion_exitosa'] : 0,
            'codigo_interno' => $data['codigo_interno'] ?? null,
            'mensaje_general' => $data['mensaje_general'] ?? null,
            'mensaje_detallado' => $data['mensaje_detallado'] ?? null,
            'request_payload' => $data['request_payload'] ?? null,
            'response_payload' => $data['response_payload'] ?? null,
            'raw_error' => $data['raw_error'] ?? null,
            'origen_error' => $origenError,
            'es_reintento' => isset($data['es_reintento']) ? (int)(bool)$data['es_reintento'] : 0,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        if (!$payload) {
            return;
        }

        $sql = 'INSERT INTO ventas_cfdi_logs (
            id_cfdi,
            id_venta,
            metodo_servicio,
            tipo_evento,
            operacion_exitosa,
            codigo_interno,
            mensaje_general,
            mensaje_detallado,
            request_payload,
            response_payload,
            raw_error,
            origen_error,
            es_reintento,
            created_at
        ) VALUES (
            :id_cfdi,
            :id_venta,
            :metodo_servicio,
            :tipo_evento,
            :operacion_exitosa,
            :codigo_interno,
            :mensaje_general,
            :mensaje_detallado,
            :request_payload,
            :response_payload,
            :raw_error,
            :origen_error,
            :es_reintento,
            :created_at
        )';

        $executeData = [
            ':id_cfdi' => $payload['id_cfdi'] ?? null,
            ':id_venta' => $payload['id_venta'] ?? null,
            ':metodo_servicio' => $payload['metodo_servicio'] ?? null,
            ':tipo_evento' => $payload['tipo_evento'] ?? 'INFO',
            ':operacion_exitosa' => $payload['operacion_exitosa'] ?? 0,
            ':codigo_interno' => $payload['codigo_interno'] ?? null,
            ':mensaje_general' => $payload['mensaje_general'] ?? null,
            ':mensaje_detallado' => $payload['mensaje_detallado'] ?? null,
            ':request_payload' => $payload['request_payload'] ?? null,
            ':response_payload' => $payload['response_payload'] ?? null,
            ':raw_error' => $payload['raw_error'] ?? null,
            ':origen_error' => $payload['origen_error'] ?? null,
            ':es_reintento' => $payload['es_reintento'] ?? 0,
            ':created_at' => $payload['created_at'] ?? date('Y-m-d H:i:s'),
        ];

        if ($debugEnabled) {
            error_log('[CFDI_LOGGER][DEBUG] tipo_evento_raw=' . json_encode($rawTipoEvento));
            error_log('[CFDI_LOGGER][DEBUG] tipo_evento_trim=' . json_encode($tipoEvento));
            error_log('[CFDI_LOGGER][DEBUG] tipo_evento_len=' . strlen($tipoEvento));
            error_log('[CFDI_LOGGER][DEBUG] execute_payload=' . json_encode($executeData, JSON_UNESCAPED_UNICODE));
        }

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($executeData);
        } catch (Throwable $e) {
            error_log('[CFDI_LOGGER][ERROR] No se pudo insertar log CFDI: ' . $e->getMessage());
        }
    }
}
