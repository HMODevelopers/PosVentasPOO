<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once __DIR__ . '/../models/CatSatUsoCfdiModel.php';
$model = new CatSatUsoCfdiModel();
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = str_replace('_', '-', ($_REQUEST['accion'] ?? ($raw['accion'] ?? '')));

switch ($accion) {
    case 'listar':
        $pagina = (int)($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
        $limite = (int)($_POST['limite'] ?? $_GET['limite'] ?? 10);
        $filtros = [
            'clave_uso_cfdi' => trim($_POST['clave_uso_cfdi'] ?? $_GET['clave_uso_cfdi'] ?? $_POST['clave'] ?? $_GET['clave'] ?? ''),
            'descripcion'    => trim($_POST['descripcion'] ?? $_GET['descripcion'] ?? ''),
            'q'              => trim($_POST['q'] ?? $_GET['q'] ?? ''),
        ];
        echo json_encode(['data' => $model->listar($pagina, $limite, $filtros), 'total' => $model->contar($filtros)]);
    break;
    case 'listar-min':
        echo json_encode(['data' => $model->listarMin(trim($_GET['q'] ?? $_POST['q'] ?? ''), (int)($_GET['limite'] ?? $_POST['limite'] ?? 50))]);
    break;
    case 'detalle':
        echo json_encode(['data' => $model->obtenerPorId((int)($_GET['id_uso_cfdi'] ?? $_POST['id_uso_cfdi'] ?? 0))]);
    break;
    case 'crear':
        if (trim($raw['clave_uso_cfdi'] ?? '') === '' || trim($raw['descripcion'] ?? '') === '') { echo json_encode(['ok'=>false,'msg'=>'Clave y descripción son requeridas']); break; }
        $id = $model->crear($raw);
        echo json_encode(['ok' => $id > 0, 'id_uso_cfdi' => $id]);
    break;
    case 'actualizar':
        $id = (int)($raw['id_uso_cfdi'] ?? $_POST['id_uso_cfdi'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_uso_cfdi requerido']); break; }
        echo json_encode(['ok' => (bool)$model->actualizar($id, $raw)]);
    break;
    case 'eliminar':
        $id = (int)($_POST['id_uso_cfdi'] ?? 0);
        echo json_encode(['ok' => $id > 0 ? (bool)$model->eliminar($id) : false]);
    break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}
