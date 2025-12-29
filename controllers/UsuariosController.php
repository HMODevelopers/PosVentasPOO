<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('BASE_URL')) {
  include_once __DIR__ . '/../includes/config.php';
}

include_once __DIR__ . '/../models/UsuarioModel.php';
$usuarioModel = new UsuarioModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
        $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? 10));

        $filtros = [
            'q'          => trim($_REQUEST['q']          ?? ''),
            'nombre'     => trim($_REQUEST['nombre']     ?? ''),
            'usuario'    => trim($_REQUEST['usuario']    ?? ''),
            'correo'     => trim($_REQUEST['correo']     ?? ''),
            'telefono'   => trim($_REQUEST['telefono']   ?? ''),
            'id_rol'     => (isset($_REQUEST['id_rol'])     && $_REQUEST['id_rol']     !== '') ? (int)$_REQUEST['id_rol']     : null,
            'id_cliente' => (isset($_REQUEST['id_cliente']) && $_REQUEST['id_cliente'] !== '') ? (int)$_REQUEST['id_cliente'] : null,
            'activo'     => (isset($_REQUEST['activo'])) ? (($_REQUEST['activo'] === '') ? '' : (int)$_REQUEST['activo']) : 1,
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
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!is_array($payload)) $payload = [];

        if (empty($payload['id_usuario'])) {
            $payload['id_usuario'] = $_SESSION['usuario']['id_usuario']
                                  ?? $_SESSION['usuario']['id']
                                  ?? $_SESSION['id_usuario']
                                  ?? 0;
        }

        $payload['id_rol'] = (array_key_exists('id_rol',$payload) && $payload['id_rol']!=='')
                                ? (int)$payload['id_rol']
                                : null;

        if (!array_key_exists('id_cliente', $payload) || $payload['id_cliente'] === '') {
            $payload['id_cliente'] = null;
        } else {
            $payload['id_cliente'] = (int)$payload['id_cliente'];
        }

        $resp = $usuarioModel->crear($payload);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!is_array($payload)) $payload = [];

        $id = (int)($payload['id_usuario'] ?? $_POST['id_usuario'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_usuario requerido'], JSON_UNESCAPED_UNICODE); break; }

        if (empty($payload['id_usuario'])) {
            $payload['id_usuario'] = $_SESSION['usuario']['id_usuario']
                                  ?? $_SESSION['usuario']['id']
                                  ?? $_SESSION['id_usuario']
                                  ?? 0;
        }

        $payload['id_rol'] = (array_key_exists('id_rol',$payload) && $payload['id_rol']!=='')
                                ? (int)$payload['id_rol']
                                : null;

        if (!array_key_exists('id_cliente', $payload) || $payload['id_cliente'] === '') {
            $payload['id_cliente'] = null;
        } else {
            $payload['id_cliente'] = (int)$payload['id_cliente'];
        }

        $resp = $usuarioModel->actualizar($id, $payload);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        $id = (int)($_POST['id_usuario'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? 'Baja de usuario');

        $idOper = $_SESSION['usuario']['id_usuario']
               ?? $_SESSION['usuario']['id']
               ?? $_SESSION['id_usuario']
               ?? 0;

        if ($id <= 0 || empty($idOper)) {
            echo json_encode(['ok'=>false,'msg'=>'Faltan datos: id_usuario del registro / usuario de sesión.'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $resp = $usuarioModel->eliminar((int)$id, (int)$idOper, $motivo);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== CAMBIAR CONTRASEÑA =====
    case 'cambiar-password':
        $idSesion = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['id_usuario'] ?? 0);
        if ($idSesion <= 0) { echo json_encode(['ok'=>false,'msg'=>'Sesión no válida.']); break; }

        // CSRF (si lo usas)
        $csrf = $_POST['csrf_token'] ?? '';
        if (!empty($_SESSION['csrf_token'])) {
            if (!$csrf || !hash_equals($_SESSION['csrf_token'], $_SESSION['csrf_token'])) {
                echo json_encode(['ok'=>false,'msg'=>'Token inválido. Recarga la página.']); break;
            }
        }

        $actual    = trim($_POST['actual']    ?? '');
        $nueva     = trim($_POST['nueva']     ?? '');
        $confirmar = trim($_POST['confirmar'] ?? '');

        if ($actual === '' || $nueva === '' || $confirmar === '') {
            echo json_encode(['ok'=>false,'msg'=>'Completa todos los campos.']); break;
        }
        if ($nueva !== $confirmar) {
            echo json_encode(['ok'=>false,'msg'=>'La confirmación no coincide.']); break;
        }
        if (strlen($nueva) < 8) {
            echo json_encode(['ok'=>false,'msg'=>'La nueva contraseña debe tener mínimo 8 caracteres.']); break;
        }

        try {
            $resp = $usuarioModel->cambiarPassword($idSesion, $actual, $nueva, $idSesion);
            if (!$resp['ok']) { echo json_encode($resp, JSON_UNESCAPED_UNICODE); break; }

            // Cerrar sesión en servidor
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();

            echo json_encode([
                'ok'=>true,
                'logout_url'=>(defined('BASE_URL') ? BASE_URL : '..') . '/controllers/LogoutController.php'
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok'=>false,'msg'=>'Error inesperado al cambiar la contraseña.'], JSON_UNESCAPED_UNICODE);
        }
    break;

    default:
        echo json_encode(['error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
    break;
}
