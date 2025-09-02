<?php
header('Content-Type: application/json; charset=UTF-8');

include_once '../models/InventarioMovimientoModel.php';
$movModel = new InventarioMovimientoModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
  case 'listar':
    $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
    $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? 10));

    // Filtros solicitados
    $q           = trim($_REQUEST['q']           ?? '');
    $codigo      = trim($_REQUEST['codigo']      ?? '');
    $descripcion = trim($_REQUEST['descripcion'] ?? '');
    $idUsuario   = (isset($_REQUEST['id_usuario']) && $_REQUEST['id_usuario']!=='') ? (int)$_REQUEST['id_usuario'] : null;
    $desde       = trim($_REQUEST['desde'] ?? '');
    $hasta       = trim($_REQUEST['hasta'] ?? '');

    $data  = $movModel->listar($pagina, $limite, $q, $codigo, $descripcion, $idUsuario, $desde, $hasta);
    $total = $movModel->contar($q, $codigo, $descripcion, $idUsuario, $desde, $hasta);

    echo json_encode(['data'=>$data, 'total'=>$total], JSON_UNESCAPED_UNICODE);
  break;

  case 'detalle':
    $id = (int)($_GET['id_movimiento'] ?? $_POST['id_movimiento'] ?? 0);
    if ($id <= 0) { echo json_encode(['error'=>'id_movimiento inválido'], JSON_UNESCAPED_UNICODE); break; }
    $row = $movModel->obtenerPorId($id);
    echo json_encode(['data'=>$row], JSON_UNESCAPED_UNICODE);
  break;

  default:
    echo json_encode(['error'=>'Acción no válida'], JSON_UNESCAPED_UNICODE);
  break;
}
