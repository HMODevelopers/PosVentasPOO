<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__, 'inventarios.movimientos');

require_once __DIR__ . '/../models/KardexProductoModel.php';

header('Content-Type: application/json; charset=UTF-8');

$model = new KardexProductoModel();
$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
    case 'listar':
        $idProducto = (int)($_POST['id_producto'] ?? $_GET['id_producto'] ?? 0);
        $desde = trim($_POST['desde'] ?? $_GET['desde'] ?? '');
        $hasta = trim($_POST['hasta'] ?? $_GET['hasta'] ?? '');

        if ($idProducto <= 0) {
            echo json_encode([
                'ok' => false,
                'msg' => 'id_producto requerido.',
                'compras' => [],
                'ventas' => []
            ], JSON_UNESCAPED_UNICODE);
            break;
        }

        $compras = $model->obtenerComprasPorProducto($idProducto, $desde, $hasta);
        $ventas = $model->obtenerVentasPorProducto($idProducto, $desde, $hasta);

        echo json_encode([
            'ok' => true,
            'compras' => $compras,
            'ventas' => $ventas
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
        break;
}
