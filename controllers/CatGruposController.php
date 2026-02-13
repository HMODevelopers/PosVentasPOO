<?php
// controllers/CatGruposController.php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');

include_once __DIR__ . '/../models/CatGrupoModel.php';
$grupoModel = new CatGrupoModel();

$accion = $_REQUEST['accion'] ?? $_REQUEST['action'] ?? '';

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 10);
        $filtros = [
            'nombre_grupo' => trim($_GET['nombre_grupo'] ?? $_POST['nombre_grupo'] ?? $_GET['q'] ?? $_POST['q'] ?? ''),
            'clave_h' => trim($_GET['clave_h'] ?? $_POST['clave_h'] ?? ''),
        ];

        $data  = $grupoModel->listar($pagina, $limite, $filtros);
        $total = $grupoModel->contar($filtros);

        echo json_encode(['data' => $data, 'total' => $total]);
    break;

    // ===== LISTA CORTA PARA SELECTS (combos) =====
    case 'listar-min':   // devuelve [{id_grupo, nombre_grupo}, ...]
        $q    = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim  = (int)($_GET['limite'] ?? $_POST['limite'] ?? 100);
        $data = $grupoModel->listarMin($q, $lim);
        echo json_encode(['data' => $data]);
    break;

    // ===== GET BY ID (AJAX para autollenado SAT) =====

    case 'getById':
        $id = (int)($_GET['id_grupo'] ?? $_POST['id_grupo'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'message' => 'id_grupo inválido']);
            break;
        }

        $row = $grupoModel->obtenerPorId($id);
        if (!$row) {
            echo json_encode(['ok' => false, 'message' => 'Grupo no encontrado']);
            break;
        }

        $clave = trim((string)($row['clave_h'] ?? ''));
        if ($clave === '') {
            echo json_encode(['ok' => false, 'message' => 'Grupo sin clave SAT']);
            break;
        }

        echo json_encode([
            'ok' => true,
            'data' => [
                'id_grupo' => (int)$row['id_grupo'],
                'clave_h' => $clave,
            ]
        ]);
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
