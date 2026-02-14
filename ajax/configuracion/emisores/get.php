<?php
require_once __DIR__ . '/_bootstrap.php';

if ((int)($_GET['sucursales'] ?? $_POST['sucursales'] ?? 0) === 1) {
    try {
        json_ok($model->listarSucursalesActivas());
    } catch (Throwable $e) {
        json_err('No se pudieron obtener las sucursales.', 'CFG-SUC-500');
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
