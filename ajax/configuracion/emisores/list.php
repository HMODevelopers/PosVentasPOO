<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $pagina = (int)($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
    $limite = (int)($_POST['limite'] ?? $_GET['limite'] ?? 10);
    $pagina = max(1, $pagina);
    $limite = max(1, $limite);
    $filtros = [
        'id_sucursal' => (int)($_POST['id_sucursal'] ?? $_GET['id_sucursal'] ?? 0),
        'rfc_emisor' => trim($_POST['rfc_emisor'] ?? $_GET['rfc_emisor'] ?? ''),
        'razon_social_emisor' => trim($_POST['razon_social_emisor'] ?? $_GET['razon_social_emisor'] ?? ''),
        'activo' => $_POST['activo'] ?? $_GET['activo'] ?? '',
    ];

    $rows = $model->listar($pagina, $limite, $filtros);
    $total = $model->contar($filtros);
    json_ok([
        'rows' => $rows,
        'total' => $total,
        'page' => $pagina,
        'perPage' => $limite,
    ]);
} catch (Throwable $e) {
    $debug = is_dev_debug_enabled()
        ? ['debug' => ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]]
        : [];
    json_err('No se pudo obtener el listado.', 'CFG-LIST-001', $debug);
}
