<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);
header('Content-Type: application/json; charset=UTF-8');
include_once __DIR__ . '/../models/CatSatRegimenFiscalModel.php';
$model = new CatSatRegimenFiscalModel();
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = str_replace('_', '-', ($_REQUEST['accion'] ?? ($raw['accion'] ?? '')));
switch ($accion) {
    case 'listar':
        $pagina = (int)($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
        $limite = (int)($_POST['limite'] ?? $_GET['limite'] ?? 10);
        $f = ['clave_regimen'=>trim($_POST['clave_regimen'] ?? $_GET['clave_regimen'] ?? ''),'descripcion'=>trim($_POST['descripcion'] ?? $_GET['descripcion'] ?? ''),'q'=>trim($_POST['q'] ?? $_GET['q'] ?? '')];
        echo json_encode(['data'=>$model->listar($pagina,$limite,$f),'total'=>$model->contar($f)]);
    break;
    case 'listar-min': echo json_encode(['data'=>$model->listarMin(trim($_GET['q'] ?? ''), (int)($_GET['limite'] ?? 50))]); break;
    case 'detalle': echo json_encode(['data'=>$model->obtenerPorId((int)($_GET['id_regimen_fiscal'] ?? $_POST['id_regimen_fiscal'] ?? 0))]); break;
    case 'crear': echo json_encode(['ok'=>($id=$model->crear($raw))>0,'id_regimen_fiscal'=>$id]); break;
    case 'actualizar': $id=(int)($raw['id_regimen_fiscal'] ?? 0); echo json_encode(['ok'=>$id>0?(bool)$model->actualizar($id,$raw):false]); break;
    case 'eliminar': $id=(int)($_POST['id_regimen_fiscal'] ?? 0); echo json_encode(['ok'=>$id>0?(bool)$model->eliminar($id):false]); break;
    default: echo json_encode(['error'=>'Acción no válida']);
}
