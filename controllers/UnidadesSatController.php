<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once '../models/UnidadSatModel.php';
$unidadModel = new UnidadSatModel();

// id_usuario para bitácora (si existe sesión)
$idUsuario = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0);

// Soporta acción por JSON y normaliza guion_bajo -> guion
$raw    = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = $_REQUEST['accion'] ?? ($raw['accion'] ?? '');
$accion = str_replace('_', '-', $accion);

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 10);

        // Filtros homogéneos
        $filtros = [
            'q'                 => trim($_GET['q'] ?? $_POST['q'] ?? ''),
            'clave_unidad_sat'  => trim($_GET['clave_unidad_sat'] ?? $_POST['clave_unidad_sat'] ?? $_GET['clave'] ?? $_POST['clave'] ?? ''),
            'descripcion'       => trim($_GET['descripcion'] ?? $_POST['descripcion'] ?? '')
        ];

        $data  = $unidadModel->listar($pagina, $limite, $filtros);
        $total = $unidadModel->contar($filtros);

        echo json_encode(['data' => $data, 'total' => $total]);
    break;

    // ===== LISTA CORTA PARA SELECTS =====
    case 'listar-min':  // devuelve [{id_unidad_sat, clave, descripcion}, ...]
        $q   = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim = (int)($_GET['limite'] ?? $_POST['limite'] ?? 50);
        $data = $unidadModel->listarMin($q, $lim);
        echo json_encode(['data' => $data]);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_unidad_sat'] ?? $_POST['id_unidad_sat'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_unidad_sat inválido']); break; }
        $row = $unidadModel->obtenerPorId($id);
        echo json_encode(['data' => $row]);
    break;

    // ===== CREAR =====
    case 'crear':
        $payload = $raw ?: $_POST;
        $clave = strtoupper(trim($payload['clave_unidad_sat'] ?? ''));
        $desc  = trim($payload['descripcion'] ?? '');
        if ($clave === '' || $desc === '') {
            echo json_encode(['ok'=>false,'msg'=>'clave_unidad_sat y descripcion son requeridos']); break;
        }
        $id = $unidadModel->crear($payload, $idUsuario);
        if ($id === -1) { echo json_encode(['ok'=>false,'msg'=>'La clave_unidad_sat ya existe']); break; }
        echo json_encode(['ok' => $id > 0, 'id_unidad_sat' => $id]);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        $payload = $raw ?: $_POST;
        $id = (int)($payload['id_unidad_sat'] ?? $_POST['id_unidad_sat'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_unidad_sat requerido']); break; }

        $clave = strtoupper(trim($payload['clave_unidad_sat'] ?? ''));
        $desc  = trim($payload['descripcion'] ?? '');
        if ($clave === '' || $desc === '') {
            echo json_encode(['ok'=>false,'msg'=>'clave_unidad_sat y descripcion son requeridos']); break;
        }

        $ok = $unidadModel->actualizar($id, $payload, $idUsuario);
        if ($ok === -1) { echo json_encode(['ok'=>false,'msg'=>'La clave_unidad_sat ya existe']); break; }
        echo json_encode(['ok' => (bool)$ok]);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        $id = (int)($_POST['id_unidad_sat'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_unidad_sat requerido']); break; }
        $ok = $unidadModel->eliminar($id, $idUsuario);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
