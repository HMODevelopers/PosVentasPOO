<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $d = payload();
    $id = (int)($d['id_config'] ?? 0);
    $idSucursal = (int)($d['id_sucursal'] ?? 0);
    $rfc = strtoupper(trim($d['rfc_emisor'] ?? ''));
    $razon = trim($d['razon_social_emisor'] ?? '');

    if ($id <= 0 || $idSucursal <= 0 || $rfc === '' || $razon === '') {
        json_err('Datos obligatorios incompletos.', 'CFG-UPD-001');
    }
    if ($model->existeRfcSucursal($idSucursal, $rfc, $id)) {
        json_err('Ya existe un emisor con ese RFC en la sucursal.', 'CFG-UPD-002');
    }

    $ok = $model->actualizar($id, $d);
    if (!$ok) {
        json_err('No se encontró el emisor a editar.', 'CFG-UPD-404');
    }
    json_ok(['updated' => true]);
} catch (Throwable $e) {
    json_err('No se pudo actualizar el emisor.', 'CFG-UPD-500');
}
