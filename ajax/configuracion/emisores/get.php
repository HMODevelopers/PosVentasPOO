<?php
require_once __DIR__ . '/_bootstrap.php';

$id = (int)($_GET['id_config_fiscal_emisor'] ?? $_POST['id_config_fiscal_emisor'] ?? 0);
if ($id <= 0) {
    json_err('Identificador inválido.', 'CFG-GET-001');
}

try {
    $row = $model->obtenerPorId($id);
    if (!$row) {
        json_err('Emisor no encontrado.', 'CFG-GET-404');
    }
    json_ok($row);
} catch (Throwable $e) {
    json_err('No se pudo obtener el emisor.', 'CFG-GET-500');
}
