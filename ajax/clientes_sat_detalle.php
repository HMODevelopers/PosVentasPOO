<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../models/ClientesSatModel.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID inválido', 'data' => null]);
    exit;
}

$model = new ClientesSatModel();
$row = $model->obtenerDetallePorId($id);

if (!$row) {
    echo json_encode(['ok' => false, 'msg' => 'Cliente SAT no encontrado', 'data' => null]);
    exit;
}

echo json_encode(['ok' => true, 'data' => $row]);
