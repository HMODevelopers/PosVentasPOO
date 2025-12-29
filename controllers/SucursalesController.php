<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once '../models/SucursalModel.php';
$sucursalModel = new SucursalModel();

// id_usuario para bitácora (si existe sesión)
$idUsuario = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0);

// soporta acción por JSON y normaliza guion_bajo -> guion
$raw    = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = $_REQUEST['accion'] ?? ($raw['accion'] ?? '');
$accion = str_replace('_', '-', $accion);

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 10);

        $filtros = [
            'q'         => trim($_GET['q'] ?? $_POST['q'] ?? ''),
            'nombre'    => trim($_GET['nombre'] ?? $_POST['nombre'] ?? ''),
            'direccion' => trim($_GET['direccion'] ?? $_POST['direccion'] ?? ''),
            'telefono'  => trim($_GET['telefono'] ?? $_POST['telefono'] ?? ''),
        ];

        $data  = $sucursalModel->listar($pagina, $limite, $filtros);
        $total = $sucursalModel->contar($filtros);

        echo json_encode(['data' => $data, 'total' => $total]);
    break;

    // ===== LISTA CORTA PARA SELECTS =====
    case 'listar-min':   // devuelve [{id_sucursal, nombre}, ...]
        $q   = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim = (int)($_GET['limite'] ?? $_POST['limite'] ?? 50);
        $data = $sucursalModel->listarMin($q, $lim);
        echo json_encode(['data' => $data]);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_sucursal'] ?? $_POST['id_sucursal'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_sucursal inválido']); break; }
        $row = $sucursalModel->obtenerPorId($id);
        echo json_encode(['data' => $row]);
    break;

    // ===== CREAR =====
    case 'crear':
        $payload = $raw ?: $_POST;
        $nombre = trim($payload['nombre'] ?? '');
        if ($nombre === '') { echo json_encode(['ok'=>false,'msg'=>'nombre es requerido']); break; }

        $id = $sucursalModel->crear($payload, $idUsuario);
        echo json_encode(['ok' => $id > 0, 'id_sucursal' => $id]);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        $payload = $raw ?: $_POST;
        $id = (int)($payload['id_sucursal'] ?? $_POST['id_sucursal'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_sucursal requerido']); break; }

        $nombre = trim($payload['nombre'] ?? '');
        if ($nombre === '') { echo json_encode(['ok'=>false,'msg'=>'nombre es requerido']); break; }

        $ok = $sucursalModel->actualizar($id, $payload, $idUsuario);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        $id = (int)($_POST['id_sucursal'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_sucursal requerido']); break; }
        $ok = $sucursalModel->eliminar($id, $idUsuario);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
