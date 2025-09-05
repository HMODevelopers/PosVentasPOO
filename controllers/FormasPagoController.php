<?php
// controllers/FormasPagoController.php
header('Content-Type: application/json; charset=utf-8');

include_once '../models/FormasPagoModel.php';

$model = new FormasPagoModel();

// Evita warning por sesiones dobles
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Para bitácora (si existe en sesión)
$idUsuarioSesion = $_SESSION['usuario']['id_usuario'] ?? ($_SESSION['usuario']['id'] ?? 0);

$accion = $_REQUEST['accion'] ?? '';

try {
    switch ($accion) {
        /* ===== Listado para tabla (paginado + búsqueda) ===== */
        case 'listar':
            $pagina = (int)($_POST['pagina'] ?? 1);
            $limite = (int)($_POST['limite'] ?? 10);
            $q      = trim($_POST['q'] ?? '');

            $data  = $model->obtenerFormasPago($pagina, $limite, $q);
            $total = $model->contarFormasPago($q);

            echo json_encode(['data'=>$data, 'total'=>$total], JSON_UNESCAPED_UNICODE);
            break;

        /* ===== Listado para SELECT (activos) ===== */
        case 'listar_select':
        case 'listar_simple':
            $data = $model->listarActivas();
            echo json_encode(['data'=>$data], JSON_UNESCAPED_UNICODE);
            break;

        /* ===== Detalle ===== */
        case 'detalle':
            $id = (int)($_GET['id_forma_pago'] ?? 0);
            $row = $model->obtenerPorId($id);
            echo json_encode(['data'=>$row], JSON_UNESCAPED_UNICODE);
            break;

        /* ===== Crear ===== */
        case 'crear':
            $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $resp = $model->crear($payload, (int)$idUsuarioSesion);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;

        /* ===== Actualizar ===== */
        case 'actualizar':
            $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($payload['id_forma_pago'] ?? ($_POST['id_forma_pago'] ?? 0));
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID requerido']); break; }
            $resp = $model->actualizar($id, $payload, (int)$idUsuarioSesion);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;

        /* ===== Activar / Desactivar / Eliminar (lógico) ===== */
        case 'activar':
            $id = (int)($_POST['id_forma_pago'] ?? 0);
            $resp = $model->cambiarActivo($id, true, (int)$idUsuarioSesion);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;

        case 'desactivar':
            $id = (int)($_POST['id_forma_pago'] ?? 0);
            $resp = $model->cambiarActivo($id, false, (int)$idUsuarioSesion);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar':
            $id = (int)($_POST['id_forma_pago'] ?? 0);
            $resp = $model->eliminarLogico($id, (int)$idUsuarioSesion);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error'=>'Acción no válida'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
