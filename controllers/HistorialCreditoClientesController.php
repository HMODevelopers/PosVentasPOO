<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__, 'ventas.creditos_historial');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../models/HistorialCreditoClientesModel.php';

header('Content-Type: application/json; charset=UTF-8');

$model = new HistorialCreditoClientesModel();
$accion = $_REQUEST['accion'] ?? ($_REQUEST['action'] ?? '');

try {
    switch ($accion) {
        case 'listar-resumen':
            $pagina = max(1, (int)($_REQUEST['pagina'] ?? 1));
            $limite = max(1, (int)($_REQUEST['limite'] ?? 20));
            $filtros = [
                'fecha_inicial'  => trim($_REQUEST['fecha_inicial'] ?? ''),
                'fecha_final'    => trim($_REQUEST['fecha_final'] ?? ''),
                'id_cliente'     => (int)($_REQUEST['id_cliente'] ?? 0),
                'estatus_credito'=> trim($_REQUEST['estatus_credito'] ?? ''),
            ];

            $data = $model->listarResumenClientes($pagina, $limite, $filtros);
            $total = $model->contarResumenClientes($filtros);
            echo json_encode(['ok' => true, 'data' => $data, 'total' => $total], JSON_UNESCAPED_UNICODE);
            break;

        case 'detalle-cliente':
        case 'por-cliente':
            $idCliente = (int)($_REQUEST['id_cliente'] ?? 0);
            if ($idCliente <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'msg' => 'id_cliente requerido'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $filtros = [
                'fecha_inicial'  => trim($_REQUEST['fecha_inicial'] ?? ''),
                'fecha_final'    => trim($_REQUEST['fecha_final'] ?? ''),
                'id_cliente'     => $idCliente,
                'estatus_credito'=> trim($_REQUEST['estatus_credito'] ?? ''),
            ];

            $payload = $model->obtenerDetalleCliente($idCliente, $filtros);
            echo json_encode(['ok' => true, 'data' => $payload], JSON_UNESCAPED_UNICODE);
            break;

        case 'articulos-venta':
            $idVenta = (int)($_REQUEST['id_venta'] ?? 0);
            if ($idVenta <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'msg' => 'id_venta requerido'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $articulos = $model->obtenerArticulosVenta($idVenta);
            echo json_encode(['ok' => true, 'data' => $articulos], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Acción inválida'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log('[HistorialCreditoClientesController] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error del servidor'], JSON_UNESCAPED_UNICODE);
}
