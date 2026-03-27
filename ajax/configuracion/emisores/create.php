<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $d = payload();
    $idSucursal = (int)($d['id_sucursal'] ?? 0);
    $rfc = strtoupper(trim((string)($d['rfc_emisor'] ?? '')));
    $razon = trim((string)($d['razon_social_emisor'] ?? ''));
    $regimen = trim((string)($d['regimen_fiscal_emisor'] ?? ''));
    $cp = preg_replace('/\D+/', '', (string)($d['cp_expedicion'] ?? ''));
    $folio = (int)($d['folio_actual'] ?? 0);

    if ($idSucursal <= 0 || $rfc === '' || $razon === '' || $regimen === '' || $cp === '') {
        json_err('Sucursal, RFC, razón social, régimen fiscal y CP expedición son obligatorios.', 'CFG-CRT-001');
    }
    if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $rfc)) {
        json_err('El RFC no cumple el formato esperado.', 'CFG-CRT-003');
    }
    if (strlen($cp) !== 5) {
        json_err('El CP de expedición debe tener 5 dígitos.', 'CFG-CRT-004');
    }
    if ($folio < 0) {
        json_err('El folio actual debe ser un número entero mayor o igual a 0.', 'CFG-CRT-005');
    }
    if ($model->existeRfcSucursal($idSucursal, $rfc)) {
        json_err('Ya existe un emisor con ese RFC en la sucursal.', 'CFG-CRT-002');
    }

    $d['rfc_emisor'] = $rfc;
    $d['cp_expedicion'] = $cp;
    $id = $model->crear($d);
    json_ok(['id_config' => $id]);
} catch (Throwable $e) {
    json_err('No se pudo crear el emisor.', 'CFG-CRT-500');
}
