<?php
header('Content-Type: application/json; charset=UTF-8');

include_once '../models/ProveedorModel.php';
$proveedorModel = new ProveedorModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 10);
        $q      = trim($_GET['q'] ?? $_POST['q'] ?? '');

        $data  = $proveedorModel->listar($pagina, $limite, $q);
        $total = $proveedorModel->contar($q);

        echo json_encode(['data' => $data, 'total' => $total]);
    break;

    // ===== LISTA CORTA PARA SELECTS =====
    case 'listar-min':   // devuelve [{id_proveedor, nombre}, ...]
        $q    = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim  = (int)($_GET['limite'] ?? $_POST['limite'] ?? 50);
        $data = $proveedorModel->listarMin($q, $lim);
        echo json_encode(['data' => $data]);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_proveedor'] ?? $_POST['id_proveedor'] ?? 0);
        if ($id <= 0) { echo json_encode(['error'=>'id_proveedor inválido']); break; }
        $row = $proveedorModel->obtenerPorId($id);
        echo json_encode(['data' => $row]);
    break;

    // ===== CREAR =====
    case 'crear':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = $proveedorModel->crear($payload ?? []);
        echo json_encode(['ok' => $id > 0, 'id_proveedor' => $id]);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($payload['id_proveedor'] ?? $_POST['id_proveedor'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_proveedor requerido']); break; }
        $ok = $proveedorModel->actualizar($id, $payload);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        $id = (int)($_POST['id_proveedor'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_proveedor requerido']); break; }
        $ok = $proveedorModel->eliminar($id);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
