<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');
include_once __DIR__ . '/../models/CatSatMonedaModel.php';

$model = new CatSatMonedaModel();
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = str_replace('_', '-', ($_REQUEST['accion'] ?? ($raw['accion'] ?? '')));

switch ($accion) {
    case 'listar':
        $pagina = (int)($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
        $limite = (int)($_POST['limite'] ?? $_GET['limite'] ?? 10);
        $f = [
            'ClaveMoneda' => trim($_POST['ClaveMoneda'] ?? $_GET['ClaveMoneda'] ?? $_POST['clave'] ?? $_GET['clave'] ?? ''),
            'Descripcion' => trim($_POST['descripcion'] ?? $_GET['descripcion'] ?? $_POST['Descripcion'] ?? $_GET['Descripcion'] ?? ''),
            'Activo' => $_POST['activo'] ?? $_GET['activo'] ?? '',
        ];
        echo json_encode(['data' => $model->listar($pagina, $limite, $f), 'total' => $model->contar($f)]);
        break;

    case 'listar-min':
        echo json_encode(['data' => $model->listarMin(trim($_GET['q'] ?? $_POST['q'] ?? ''), (int)($_GET['limite'] ?? $_POST['limite'] ?? 50))]);
        break;

    case 'detalle':
        $clave = (string)($_GET['ClaveMoneda'] ?? $_POST['ClaveMoneda'] ?? '');
        echo json_encode(['data' => $model->obtenerPorId($clave)]);
        break;

    case 'crear':
        echo json_encode(['ok' => $model->crear($raw)]);
        break;

    case 'actualizar':
        $original = trim($raw['OriginalClaveMoneda'] ?? $raw['ClaveMoneda'] ?? '');
        echo json_encode(['ok' => $original !== '' ? $model->actualizar($original, $raw) : false]);
        break;

    case 'toggle-activo':
        $clave = trim($_POST['ClaveMoneda'] ?? $raw['ClaveMoneda'] ?? '');
        $activo = (int)($_POST['activo'] ?? $raw['activo'] ?? 0);
        echo json_encode(['ok' => $clave !== '' ? $model->toggleActivo($clave, $activo) : false]);
        break;

    case 'eliminar':
        $clave = trim($_POST['ClaveMoneda'] ?? '');
        echo json_encode(['ok' => $clave !== '' ? $model->toggleActivo($clave, 0) : false]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
