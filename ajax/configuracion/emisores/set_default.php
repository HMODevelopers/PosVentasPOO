<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $d = payload();
    $id = (int)($d['id_config_fiscal_emisor'] ?? 0);
    if ($id <= 0) {
        json_err('Identificador inválido.', 'CFG-DFT-001');
    }

    $row = $model->obtenerPorId($id);
    if (!$row) {
        json_err('Emisor no encontrado.', 'CFG-DFT-404');
    }
    if ((int)$row['activo'] === 0) {
        json_err('No puedes marcar default un emisor inactivo.', 'CFG-DFT-002');
    }

    $ok = $model->setDefault($id);
    json_ok(['updated' => (bool)$ok]);
} catch (Throwable $e) {
    json_err('No se pudo definir el emisor default.', 'CFG-DFT-500');
}
