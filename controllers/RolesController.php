<?php
header('Content-Type: application/json; charset=UTF-8');

include_once '../models/RolModel.php';
$rolModel = new RolModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
        $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? 10));
        $nombre = trim($_REQUEST['nombre'] ?? '');
        // Estatus: '' = todos, 1 activos por defecto
        $activo = isset($_REQUEST['activo']) ? (($_REQUEST['activo']==='') ? '' : (int)$_REQUEST['activo']) : 1;

        $data  = $rolModel->listar($pagina, $limite, $nombre, $activo);
        $total = $rolModel->contar($nombre, $activo);
        echo json_encode(['data'=>$data, 'total'=>$total], JSON_UNESCAPED_UNICODE);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_rol'] ?? $_POST['id_rol'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_rol inválido'], JSON_UNESCAPED_UNICODE); break; }
        $row = $rolModel->obtenerPorId($id);
        echo json_encode(['data'=>$row], JSON_UNESCAPED_UNICODE);
    break;

    // ===== LISTA CORTA (para selects) =====
    case 'listar-min':
    case 'buscar-min':
        $q   = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim = (int)($_GET['limite'] ?? $_POST['limite'] ?? 200);
        $lim = max(1, min($lim, 1000));
        $data = $rolModel->listarMin($q, $lim);
        echo json_encode(['data'=>$data], JSON_UNESCAPED_UNICODE);
    break;

    // ===== CREAR =====
    case 'crear':
        session_start();
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!is_array($payload)) $payload = [];

        // id_usuario que opera (requerido para bitácora)
        if (empty($payload['id_usuario'])) {
            $payload['id_usuario'] = $_SESSION['usuario']['id_usuario']
                                  ?? $_SESSION['usuario']['id']
                                  ?? $_SESSION['id_usuario']
                                  ?? null;
        }
        if (empty($payload['id_usuario'])) {
            echo json_encode(['ok'=>false,'msg'=>'Falta id_usuario (sesión).'], JSON_UNESCAPED_UNICODE);
            break;
        }

        // Validaciones mínimas
        if (empty(trim($payload['nombre'] ?? ''))) {
            echo json_encode(['ok'=>false,'msg'=>'El campo nombre es obligatorio.'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $resp = $rolModel->crear($payload);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        session_start();
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!is_array($payload)) $payload = [];

        $id = (int)($payload['id_rol'] ?? $_POST['id_rol'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_rol requerido'], JSON_UNESCAPED_UNICODE); break; }

        if (empty($payload['id_usuario'])) {
            $payload['id_usuario'] = $_SESSION['usuario']['id_usuario']
                                  ?? $_SESSION['usuario']['id']
                                  ?? $_SESSION['id_usuario']
                                  ?? null;
        }
        if (empty($payload['id_usuario'])) {
            echo json_encode(['ok'=>false,'msg'=>'Falta id_usuario (sesión).'], JSON_UNESCAPED_UNICODE);
            break;
        }

        if (isset($payload['nombre']) && trim($payload['nombre']) === '') {
            echo json_encode(['ok'=>false,'msg'=>'El nombre no puede estar vacío.'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $resp = $rolModel->actualizar($id, $payload);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== ELIMINAR (soft) =====
    case 'eliminar':
        session_start();
        $id = (int)($_POST['id_rol'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? 'Baja de rol');

        $idUsuario = $_SESSION['usuario']['id_usuario']
                  ?? $_SESSION['usuario']['id']
                  ?? $_SESSION['id_usuario']
                  ?? null;

        if ($id <= 0 || empty($idUsuario)) {
            echo json_encode(['ok'=>false,'msg'=>'Faltan datos: id_rol / usuario de sesión.'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $resp = $rolModel->eliminar($id, (int)$idUsuario, $motivo);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    default:
        echo json_encode(['error'=>'Acción no válida'], JSON_UNESCAPED_UNICODE);
    break;
}
