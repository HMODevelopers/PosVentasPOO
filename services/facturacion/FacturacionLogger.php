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

        $payload = $this->schema->filterData('ventas_cfdi_logs', [
            'id_cfdi' => $data['id_cfdi'] ?? null,
            'id_venta' => $data['id_venta'] ?? null,
            'metodo_servicio' => $data['metodo_servicio'] ?? 'GenerarCFDI40',
            'tipo_evento' => $data['tipo_evento'] ?? 'INFO',
            'operacion_exitosa' => isset($data['operacion_exitosa']) ? (int)(bool)$data['operacion_exitosa'] : 0,
            'codigo_interno' => $data['codigo_interno'] ?? null,
            'mensaje_general' => $data['mensaje_general'] ?? null,
            'mensaje_detallado' => $data['mensaje_detallado'] ?? null,
            'request_payload' => $data['request_payload'] ?? null,
            'response_payload' => $data['response_payload'] ?? null,
            'raw_error' => $data['raw_error'] ?? null,
            'origen_error' => $data['origen_error'] ?? null,
            'es_reintento' => isset($data['es_reintento']) ? (int)(bool)$data['es_reintento'] : 0,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        if (!$payload) {
            return;
        }

        $cols = array_keys($payload);
        $marks = array_map(fn($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO ventas_cfdi_logs (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $marks) . ')';
        $st = $this->conn->prepare($sql);
        foreach ($payload as $col => $value) {
            $st->bindValue(':' . $col, $value);
        }
        $st->execute();
    }
}
