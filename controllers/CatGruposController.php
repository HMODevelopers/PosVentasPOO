<?php
// controllers/CatGruposController.php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');

include_once __DIR__ . '/../models/CatGrupoModel.php';
$grupoModel = new CatGrupoModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 10);
        $q      = trim($_GET['q'] ?? $_POST['q'] ?? '');

        $data  = $grupoModel->listar($pagina, $limite, $q);
        $total = $grupoModel->contar($q);

        echo json_encode(['data' => $data, 'total' => $total]);
    break;

    // ===== LISTA CORTA PARA SELECTS (combos) =====
    case 'listar-min':   // devuelve [{id_grupo, nombre_grupo}, ...]
        $q    = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim  = (int)($_GET['limite'] ?? $_POST['limite'] ?? 100);
        $data = $grupoModel->listarMin($q, $lim);
        echo json_encode(['data' => $data]);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_grupo'] ?? $_POST['id_grupo'] ?? 0);
        if ($id <= 0) { echo json_encode(['error'=>'id_grupo inválido']); break; }
        $row = $grupoModel->obtenerPorId($id);
        echo json_encode(['data' => $row]);
    break;

    // ===== CREAR =====
    case 'crear':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = $grupoModel->crear($payload ?? []);
        echo json_encode(['ok' => $id > 0, 'id_grupo' => $id]);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($payload['id_grupo'] ?? $_POST['id_grupo'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_grupo requerido']); break; }
        $ok = $grupoModel->actualizar($id, $payload);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        $id = (int)($_POST['id_grupo'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_grupo requerido']); break; }
        $ok = $grupoModel->eliminar($id);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
