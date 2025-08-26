<?php
header('Content-Type: application/json; charset=UTF-8');

include_once '../models/ProductoModel.php';
$productoModel = new ProductoModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
        $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? 10));

        // Nuevos filtros
        $codigo      = trim($_GET['codigo']      ?? $_POST['codigo']      ?? '');
        $descripcion = trim($_GET['descripcion'] ?? $_POST['descripcion'] ?? '');
        $idProveedor = (isset($_REQUEST['id_proveedor']) && $_REQUEST['id_proveedor'] !== '')
                    ? (int)$_REQUEST['id_proveedor']
                    : null;

        // OJO: el modelo debe aceptar (codigo, descripcion, idProveedor)
        $data  = $productoModel->listar($pagina, $limite, $codigo, $descripcion, $idProveedor);
        $total = $productoModel->contar($codigo, $descripcion, $idProveedor);

        echo json_encode(['data' => $data, 'total' => $total]);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_producto'] ?? $_POST['id_producto'] ?? 0);
        if ($id <= 0) { echo json_encode(['error'=>'id_producto inválido']); break; }
        $row = $productoModel->obtenerPorId($id);
        echo json_encode(['data' => $row]);
    break;

    // ===== CREAR =====
    case 'crear':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = $productoModel->crear($payload ?? []);
        echo json_encode(['ok' => $id > 0, 'id_producto' => $id]);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($payload['id_producto'] ?? $_POST['id_producto'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_producto requerido']); break; }
        $ok = $productoModel->actualizar($id, $payload);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        $id = (int)($_POST['id_producto'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_producto requerido']); break; }
        $ok = $productoModel->eliminar($id);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    // ===== BUSCAR-MIN (para selects y typeahead) =====
    case 'buscar-min':
        $q   = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim = (int)($_GET['limite'] ?? $_POST['limite'] ?? 50);
        $lim = max(1, min($lim, 500)); // tope opcional
        $data = $productoModel->buscarMin($q, $lim);
        echo json_encode(['data' => $data]);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
