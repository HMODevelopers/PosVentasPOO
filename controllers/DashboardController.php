<?php
// controllers/DashboardController.php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=utf-8');

include_once '../models/DashboardModel.php';

// Evita warning por sesiones dobles
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$accion = $_REQUEST['accion'] ?? 'resumen';

try {
    $model = new DashboardModel();

    switch ($accion) {
        case 'tendencia_30d': {
            $sucursalId = isset($_REQUEST['id_sucursal'])
                ? (int)$_REQUEST['id_sucursal']
                : (int)($_SESSION['usuario']['id_sucursal'] ?? 0);
            $data = $model->tendencia30d($sucursalId ?: null);
            echo json_encode(['ok'=>true, 'data'=>$data], JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'top_prod_mes': {
            $sucursalId = isset($_REQUEST['id_sucursal'])
                ? (int)$_REQUEST['id_sucursal']
                : (int)($_SESSION['usuario']['id_sucursal'] ?? 0);
            $data = $model->topProductosMes($sucursalId ?: null);
            echo json_encode(['ok'=>true, 'data'=>$data], JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'top_prov_mes': {
            $sucursalId = isset($_REQUEST['id_sucursal'])
                ? (int)$_REQUEST['id_sucursal']
                : (int)($_SESSION['usuario']['id_sucursal'] ?? 0);
            $data = $model->topProveedoresMes($sucursalId ?: null);
            echo json_encode(['ok'=>true, 'data'=>$data], JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'resumen':
        default: {
            $fecha = $_REQUEST['fecha'] ?? date('Y-m-d');
            $sucursalId = isset($_REQUEST['id_sucursal'])
                ? (int)$_REQUEST['id_sucursal']
                : (int)($_SESSION['usuario']['id_sucursal'] ?? 0);
            $data = $model->resumenDia($fecha, $sucursalId ?: null);
            echo json_encode(['ok'=>true, 'data'=>$data], JSON_UNESCAPED_UNICODE);
            break;
        }
    }
} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
