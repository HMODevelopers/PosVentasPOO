<?php
header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once '../models/ClienteModel.php';
$clienteModel = new ClienteModel();

$accion = $_REQUEST['accion'] ?? '';

// Toma el id del usuario logueado (ajusta las claves a tu sesión real)
$idUsuario = null;
if (!empty($_SESSION['usuario'])) {
    $u = $_SESSION['usuario'];
    $idUsuario = $u['id_usuario'] ?? $u['idUsuario'] ?? $u['id'] ?? null;
}

switch ($accion) {

    // ===== LISTAR PAGINADO (con filtros por campo y q) =====
    case 'listar':
        $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 10);

        $filtros = [
            'q'         => trim($_GET['q']         ?? $_POST['q']         ?? ''),
            'nombre'    => trim($_GET['nombre']    ?? $_POST['nombre']    ?? ''),
            'rfc'       => trim($_GET['rfc']       ?? $_POST['rfc']       ?? ''),
            'correo'    => trim($_GET['correo']    ?? $_POST['correo']    ?? ''),
            'telefono'  => trim($_GET['telefono']  ?? $_POST['telefono']  ?? ''),
            'uso_cfdi'  => trim($_GET['uso_cfdi']  ?? $_POST['uso_cfdi']  ?? ''),
        ];

        $data  = $clienteModel->listar($pagina, $limite, $filtros);
        $total = $clienteModel->contar($filtros);

        echo json_encode(['data' => $data, 'total' => $total]);
    break;

    // ===== LISTA CORTA PARA SELECTS =====
    case 'listar-min':   // devuelve [{id_cliente, nombre}, ...]
        $q    = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim  = (int)($_GET['limite'] ?? $_POST['limite'] ?? 50);
        $data = $clienteModel->listarMin($q, $lim);
        echo json_encode(['data' => $data]);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_cliente'] ?? $_POST['id_cliente'] ?? 0);
        if ($id <= 0) { echo json_encode(['error'=>'id_cliente inválido']); break; }
        $row = $clienteModel->obtenerPorId($id);
        echo json_encode(['data' => $row]);
    break;

    // ===== CREAR =====
    case 'crear':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = $clienteModel->crear($payload ?? [], $idUsuario);
        echo json_encode(['ok' => $id > 0, 'id_cliente' => $id]);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($payload['id_cliente'] ?? $_POST['id_cliente'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_cliente requerido']); break; }
        $ok = $clienteModel->actualizar($id, $payload, $idUsuario);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        $id = (int)($_POST['id_cliente'] ?? $_GET['id_cliente'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_cliente requerido']); break; }
        $ok = $clienteModel->eliminar($id, $idUsuario);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
