<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $d = payload();
    $id = (int)($d['id_config'] ?? 0);
    $idSucursal = (int)($d['id_sucursal'] ?? 0);
    $rfc = strtoupper(trim((string)($d['rfc_emisor'] ?? '')));
    $razon = trim((string)($d['razon_social_emisor'] ?? ''));
    $regimen = trim((string)($d['regimen_fiscal_emisor'] ?? ''));
    $cp = preg_replace('/\D+/', '', (string)($d['cp_expedicion'] ?? ''));
    $folio = (int)($d['folio_actual'] ?? 0);

    if ($id <= 0 || $idSucursal <= 0 || $rfc === '' || $razon === '' || $regimen === '' || $cp === '') {
        json_err('Datos obligatorios incompletos.', 'CFG-UPD-001');
    }
    if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $rfc)) {
        json_err('El RFC no cumple el formato esperado.', 'CFG-UPD-003');
    }
    if (strlen($cp) !== 5) {
        json_err('El CP de expedición debe tener 5 dígitos.', 'CFG-UPD-004');
    }
    if ($folio < 0) {
        json_err('El folio actual debe ser un número entero mayor o igual a 0.', 'CFG-UPD-005');
    }
    if ($model->existeRfcSucursal($idSucursal, $rfc, $id)) {
        json_err('Ya existe un emisor con ese RFC en la sucursal.', 'CFG-UPD-002');
    }

    $d['rfc_emisor'] = $rfc;
    $d['cp_expedicion'] = $cp;
    $ok = $model->actualizar($id, $d);
    if (!$ok) {
        json_err('No se encontró el emisor a editar.', 'CFG-UPD-404');
    }
    json_ok(['updated' => true]);
} catch (Throwable $e) {
    json_err('No se pudo actualizar el emisor.', 'CFG-UPD-500');
}
