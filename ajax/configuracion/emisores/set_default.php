<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $d = payload();
    $id = (int)($d['id_config'] ?? $_POST['id_config'] ?? $_GET['id_config'] ?? 0);
    if ($id <= 0) {
        json_err('Identificador inválido.', 'CFG-DFT-001');
    }

    $ok = $model->setDefault($id);
    if (!$ok) {
        json_err('No se encontró el emisor a actualizar.', 'CFG-DFT-404');
    }

    json_ok(['updated' => true]);
} catch (Throwable $e) {
    json_err('No se pudo actualizar el emisor default.', 'CFG-DFT-500');
}
