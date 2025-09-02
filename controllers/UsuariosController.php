<?php
header('Content-Type: application/json; charset=UTF-8');

include_once '../models/UsuarioModel.php';
$usuarioModel = new UsuarioModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // ====== MIN para selects ======
    case 'listar-min':
        $q      = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 200);
        $limite = max(1, min($limite, 1000));

        $data = $usuarioModel->listarMin($q, $limite);
        echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
    break;

    // ====== LISTAR paginado (opcional, por si luego lo necesitas) ======
    case 'listar':
        $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
        $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? 10));
        $q      = trim($_REQUEST['q'] ?? '');
        $idRol  = (isset($_REQUEST['id_rol']) && $_REQUEST['id_rol'] !== '') ? (int)$_REQUEST['id_rol'] : null;
        // por defecto activos = 1; si quieres ver todos manda activo=''
        $activo = isset($_REQUEST['activo']) ? (($_REQUEST['activo']==='' ) ? null : (int)$_REQUEST['activo']) : 1;

        $data  = $usuarioModel->listar($pagina, $limite, $q, $idRol, $activo);
        $total = $usuarioModel->contar($q, $idRol, $activo);
        echo json_encode(['data' => $data, 'total' => $total], JSON_UNESCAPED_UNICODE);
    break;

    // ====== DETALLE ======
    case 'detalle':
        $id = (int)($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);
        if ($id <= 0) { echo json_encode(['error'=>'id_usuario inválido'], JSON_UNESCAPED_UNICODE); break; }
        $row = $usuarioModel->obtenerPorId($id);
        echo json_encode(['data' => $row], JSON_UNESCAPED_UNICODE);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
    break;
}
