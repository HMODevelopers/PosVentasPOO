<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $pagina = (int)($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
    $limite = (int)($_POST['limite'] ?? $_GET['limite'] ?? 10);
    $filtros = [
        'id_sucursal' => (int)($_POST['id_sucursal'] ?? $_GET['id_sucursal'] ?? 0),
        'rfc_emisor' => trim($_POST['rfc_emisor'] ?? $_GET['rfc_emisor'] ?? ''),
        'razon_social_emisor' => trim($_POST['razon_social_emisor'] ?? $_GET['razon_social_emisor'] ?? ''),
        'fd_ambiente' => trim($_POST['fd_ambiente'] ?? $_GET['fd_ambiente'] ?? ''),
        'activo' => $_POST['activo'] ?? $_GET['activo'] ?? '',
    ];

    $rows = $model->listar($pagina, $limite, $filtros);
    $total = $model->contar($filtros);
    json_ok(['rows' => $rows, 'total' => $total]);
} catch (Throwable $e) {
    json_err('No se pudo obtener el listado.', 'CFG-LIST-001');
}
