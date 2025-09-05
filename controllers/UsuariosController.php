<?php
header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once '../models/UsuarioModel.php';
$usuarioModel = new UsuarioModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
        $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? 10));

        // Filtros
        $filtros = [
            'q'        => trim($_REQUEST['q']        ?? ''),
            'nombre'   => trim($_REQUEST['nombre']   ?? ''),
            'usuario'  => trim($_REQUEST['usuario']  ?? ''),
            'correo'   => trim($_REQUEST['correo']   ?? ''),
            'telefono' => trim($_REQUEST['telefono'] ?? ''),
            'id_rol'   => (isset($_REQUEST['id_rol']) && $_REQUEST['id_rol'] !== '') ? (int)$_REQUEST['id_rol'] : null,
            'activo'   => (isset($_REQUEST['activo']))
                          ? (($_REQUEST['activo'] === '') ? '' : (int)$_REQUEST['activo'])
                          : 1,
        ];

        $data  = $usuarioModel->listar($pagina, $limite, $filtros);
        $total = $usuarioModel->contar($filtros);

        echo json_encode(['data' => $data, 'total' => $total], JSON_UNESCAPED_UNICODE);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_usuario inválido'], JSON_UNESCAPED_UNICODE); break; }
        $row = $usuarioModel->obtenerPorId($id);
        echo json_encode(['data' => $row], JSON_UNESCAPED_UNICODE);
    break;

    // ===== LISTA CORTA PARA SELECTS =====
    case 'listar-min':
    case 'buscar-min':
        $q   = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim = (int)($_GET['limite'] ?? $_POST['limite'] ?? 200);
        $lim = max(1, min($lim, 1000));
        $data = $usuarioModel->listarMin($q, $lim);
        echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
    break;

    // ===== CREAR =====
    case 'crear':
        session_start();
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!is_array($payload)) $payload = [];

        // Asegura id_usuario operador desde sesión (igual que Productos)
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

        // Normaliza rol vacío a null
        if (!array_key_exists('id_rol', $payload) || $payload['id_rol'] === '' || $payload['id_rol'] === null) {
            $payload['id_rol'] = null;
        } else {
            $payload['id_rol'] = (int)$payload['id_rol'];
        }

        $resp = $usuarioModel->crear($payload);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        session_start();
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!is_array($payload)) $payload = [];

        $id = (int)($payload['id_usuario'] ?? $_POST['id_usuario'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_usuario requerido'], JSON_UNESCAPED_UNICODE); break; }

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

        if (!array_key_exists('id_rol', $payload) || $payload['id_rol'] === '' || $payload['id_rol'] === null) {
            $payload['id_rol'] = null;
        } else {
            $payload['id_rol'] = (int)$payload['id_rol'];
        }

        $resp = $usuarioModel->actualizar($id, $payload);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        session_start();
        $id = (int)($_POST['id_usuario'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? 'Baja de usuario');

        $idUsuario = $_SESSION['usuario']['id_usuario']
                  ?? $_SESSION['usuario']['id']
                  ?? $_SESSION['id_usuario']
                  ?? null;

        if ($id <= 0 || empty($idUsuario)) {
            echo json_encode(['ok'=>false,'msg'=>'Faltan datos: id_usuario del registro / usuario de sesión.'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $resp = $usuarioModel->eliminar((int)$id, (int)$idUsuario, $motivo);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
    break;
}
