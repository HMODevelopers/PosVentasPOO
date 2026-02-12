<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);
header('Content-Type: application/json; charset=UTF-8');
include_once __DIR__ . '/../models/ClientesSatModel.php';
$model = new ClientesSatModel();
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = str_replace('_', '-', ($_REQUEST['accion'] ?? ($raw['accion'] ?? '')));
switch ($accion) {
    case 'listar':
        $pagina=(int)($_POST['pagina'] ?? 1); $limite=(int)($_POST['limite'] ?? 10);
        $f=[
            'rfc'=>trim($_POST['rfc'] ?? ''), 'razon_social'=>trim($_POST['razon_social'] ?? ''),
            'codigo_postal'=>trim($_POST['codigo_postal'] ?? ''), 'q'=>trim($_POST['q'] ?? '')
        ];
        echo json_encode(['data'=>$model->listar($pagina,$limite,$f),'total'=>$model->contar($f)]);
    break;
    case 'detalle': echo json_encode(['data'=>$model->obtenerPorId((int)($_GET['id_cliente_sat'] ?? $_POST['id_cliente_sat'] ?? 0))]); break;
    case 'crear': echo json_encode(['ok'=>($id=$model->crear($raw))>0,'id_cliente_sat'=>$id]); break;
    case 'actualizar': $id=(int)($raw['id_cliente_sat'] ?? 0); echo json_encode(['ok'=>$id>0?(bool)$model->actualizar($id,$raw):false]); break;
    case 'eliminar': $id=(int)($_POST['id_cliente_sat'] ?? 0); echo json_encode(['ok'=>$id>0?(bool)$model->eliminar($id):false]); break;
    default: echo json_encode(['error'=>'Acción no válida']);
}
