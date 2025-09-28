<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/FaltantesModel.php';

$model = new FaltantesModel();

// Leer body JSON (opcional) y normalizar acción
$raw    = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = $_REQUEST['accion'] ?? ($raw['accion'] ?? 'listar');
$accion = str_replace('_', '-', $accion);

switch ($accion) {
  case 'listar':
    $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? $raw['pagina'] ?? 1);
    $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? $raw['limite'] ?? 10);

    $modo  = $_GET['Modo'] ?? $_POST['Modo'] ?? $raw['Modo'] ?? 'rango';
    $desde = $_GET['Desde'] ?? $_POST['Desde'] ?? $raw['Desde'] ?? '';
    $hasta = $_GET['Hasta'] ?? $_POST['Hasta'] ?? $raw['Hasta'] ?? '';

    // Si no es modo por rango, ignorar fechas
    if ($modo !== 'rango') { $desde = ''; $hasta = ''; }

    try {
      if ($modo === 'negativos') {
        $resp = $model->negativosPaginado([], $pagina, $limite);
      } else {
        $resp = $model->faltantesPaginado([
          'modo'  => $modo,
          'desde' => $desde,
          'hasta' => $hasta,
        ], $pagina, $limite);
      }
      echo json_encode(['data' => $resp['data'], 'total' => $resp['total']]);
    } catch (Throwable $e) {
      echo json_encode(['data' => [], 'total' => 0, 'msg' => $e->getMessage()]);
    }
  break;

  default:
    echo json_encode(['data'=>[], 'total'=>0, 'msg'=>'Acción no válida']);
  break;
}
