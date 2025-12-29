<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once '../models/BitacoraModel.php';
$bitModel = new BitacoraModel();

// Soporta acción en JSON y normaliza guion_bajo -> guion
$raw    = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = $_REQUEST['accion'] ?? ($raw['accion'] ?? '');
$accion = str_replace('_', '-', $accion);

switch ($accion) {

    // ===== LISTAR (solo lectura) =====
    case 'listar':
        $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 10);

        $filtros = [
            'q'                => trim($_GET['q'] ?? $_POST['q'] ?? ''),
            'tabla'            => trim($_GET['tabla'] ?? $_POST['tabla'] ?? ''),
            'accion'           => ($_GET['accion_b'] ?? $_POST['accion_b'] ?? ''), // usar 'accion_b' para no chocar con acción del controlador
            'id_usuario'       => (int)($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0),
            'registro_id'      => (int)($_GET['registro_id'] ?? $_POST['registro_id'] ?? 0),
            'campo_modificado' => trim($_GET['campo_modificado'] ?? $_POST['campo_modificado'] ?? ''),
            'ip_origen'        => trim($_GET['ip_origen'] ?? $_POST['ip_origen'] ?? ''),
            'desde'            => trim($_GET['desde'] ?? $_POST['desde'] ?? ''),
            'hasta'            => trim($_GET['hasta'] ?? $_POST['hasta'] ?? ''),
        ];

        // si accion_b viene como JSON array en body, úsalo
        if (isset($raw['accion_b'])) {
            $filtros['accion'] = $raw['accion_b'];
        }

        $data  = $bitModel->listar($pagina, $limite, $filtros);
        $total = $bitModel->contar($filtros);

        echo json_encode(['data' => $data, 'total' => $total]);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_bitacora'] ?? $_POST['id_bitacora'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_bitacora inválido']); break; }
        $row = $bitModel->obtenerPorId($id);
        echo json_encode(['data' => $row]);
    break;

    default:
        // Solo lectura: no exponemos crear/actualizar/eliminar
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
