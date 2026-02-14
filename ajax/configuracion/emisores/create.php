<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $d = payload();
    $idSucursal = (int)($d['id_sucursal'] ?? 0);
    $rfc = strtoupper(trim($d['rfc_emisor'] ?? ''));
    $razon = trim($d['razon_social_emisor'] ?? '');

    if ($idSucursal <= 0 || $rfc === '' || $razon === '') {
        json_err('Sucursal, RFC y razón social son obligatorios.', 'CFG-CRT-001');
    }
    if ($model->existeRfcSucursal($idSucursal, $rfc)) {
        json_err('Ya existe un emisor con ese RFC en la sucursal.', 'CFG-CRT-002');
    }

    $id = $model->crear($d);
    json_ok(['id_config_fiscal_emisor' => $id]);
} catch (Throwable $e) {
    json_err('No se pudo crear el emisor.', 'CFG-CRT-500');
}
