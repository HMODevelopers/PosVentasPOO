<?php
header('Content-Type: application/json; charset=UTF-8');

include_once '../models/UnidadSatModel.php';
$unidadModel = new UnidadSatModel();

// soporta acción por JSON y normaliza guion_bajo -> guion
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = $_REQUEST['accion'] ?? ($raw['accion'] ?? '');
$accion = str_replace('_', '-', $accion);

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 10);
        $q      = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $data   = $unidadModel->listar($pagina, $limite, $q);
        $total  = $unidadModel->contar($q);
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
        if (empty($payload['clave_unidad_sat']) || empty($payload['descripcion'])) {
            echo json_encode(['ok'=>false,'msg'=>'clave_unidad_sat y descripcion son requeridos']); break;
        }
        $id = $unidadModel->crear($payload);
        if ($id === -1) { echo json_encode(['ok'=>false,'msg'=>'La clave_unidad_sat ya existe']); break; }
        echo json_encode(['ok' => $id > 0, 'id_unidad_sat' => $id]);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        $payload = $raw ?: $_POST;
        $id = (int)($payload['id_unidad_sat'] ?? $_POST['id_unidad_sat'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_unidad_sat requerido']); break; }
        if (empty($payload['clave_unidad_sat']) || empty($payload['descripcion'])) {
            echo json_encode(['ok'=>false,'msg'=>'clave_unidad_sat y descripcion son requeridos']); break;
        }
        $ok = $unidadModel->actualizar($id, $payload);
        if ($ok === -1) { echo json_encode(['ok'=>false,'msg'=>'La clave_unidad_sat ya existe']); break; }
        echo json_encode(['ok' => (bool)$ok]);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        $id = (int)($_POST['id_unidad_sat'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_unidad_sat requerido']); break; }
        $ok = $unidadModel->eliminar($id);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
