<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $d = payload();
    $id = (int)($d['id_config_fiscal_emisor'] ?? 0);
    $activo = (int)($d['activo'] ?? 0);
    if ($id <= 0) {
        json_err('Identificador inválido.', 'CFG-TGL-001');
    }

    $ok = $model->toggle($id, $activo ? 1 : 0);
    json_ok(['updated' => (bool)$ok]);
} catch (Throwable $e) {
    json_err('No se pudo cambiar el estatus.', 'CFG-TGL-500');
}
