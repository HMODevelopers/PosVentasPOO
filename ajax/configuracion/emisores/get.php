<?php
require_once __DIR__ . '/_bootstrap.php';

if ((int)($_GET['catalogos'] ?? $_POST['catalogos'] ?? 0) === 1) {
    try {
        json_ok([
            'sucursales' => $model->listarSucursalesActivas(),
            'regimenes' => $model->listarRegimenesFiscalesActivos(),
            'monedas' => $model->listarMonedasActivas(),
            'tipos_comprobante' => $model->listarTiposComprobanteActivos(),
            'exportaciones' => $model->listarExportacionesActivas(),
        ]);
    } catch (Throwable $e) {
        json_err('No se pudieron obtener los catálogos.', 'CFG-CAT-500');
    }
}

$id = (int)($_GET['id_config'] ?? $_POST['id_config'] ?? 0);
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
