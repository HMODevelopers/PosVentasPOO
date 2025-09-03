<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

include_once '../models/CajaModel.php';
$cajaModel = new CajaModel();

// id_usuario para bitácora (si existe en la sesión)
$idUsuario = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0);

// Soporta acción en JSON y normaliza guion_bajo -> guion
$raw    = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = $_REQUEST['accion'] ?? ($raw['accion'] ?? '');
$accion = str_replace('_', '-', $accion);

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 10);

        $filtros = [
            'q'           => trim($_GET['q'] ?? $_POST['q'] ?? ''),
            'nombre'      => trim($_GET['nombre'] ?? $_POST['nombre'] ?? ''),
            'id_sucursal' => (int)($_GET['id_sucursal'] ?? $_POST['id_sucursal'] ?? 0),
        ];

        $data  = $cajaModel->listar($pagina, $limite, $filtros);
        $total = $cajaModel->contar($filtros);

        echo json_encode(['data' => $data, 'total' => $total]);
    break;

    // ===== LISTA CORTA PARA SELECTS =====
    // devuelve [{id_caja, nombre}, ...] y acepta filtro opcional por id_sucursal
    case 'listar-min':
        $q           = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim         = (int)($_GET['limite'] ?? $_POST['limite'] ?? 50);
        $idSucursal  = (int)($_GET['id_sucursal'] ?? $_POST['id_sucursal'] ?? 0);
        $data = $cajaModel->listarMin($q, $lim, $idSucursal > 0 ? $idSucursal : null);
        echo json_encode(['data' => $data]);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_caja'] ?? $_POST['id_caja'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_caja inválido']); break; }
        $row = $cajaModel->obtenerPorId($id);
        echo json_encode(['data' => $row]);
    break;

    // ===== CREAR =====
    case 'crear':
        $payload     = $raw ?: $_POST;
        $nombre      = trim($payload['nombre'] ?? '');
        $idSucursal  = (int)($payload['id_sucursal'] ?? 0);

        if ($nombre === '' || $idSucursal <= 0) {
            echo json_encode(['ok'=>false,'msg'=>'nombre e id_sucursal son requeridos']);
            break;
        }

        $id = $cajaModel->crear($payload, $idUsuario);
        echo json_encode(['ok' => $id > 0, 'id_caja' => $id]);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        $payload     = $raw ?: $_POST;
        $id          = (int)($payload['id_caja'] ?? $_POST['id_caja'] ?? 0);
        $nombre      = trim($payload['nombre'] ?? '');
        $idSucursal  = (int)($payload['id_sucursal'] ?? 0);

        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_caja requerido']); break; }
        if ($nombre === '' || $idSucursal <= 0) {
            echo json_encode(['ok'=>false,'msg'=>'nombre e id_sucursal son requeridos']);
            break;
        }

        $ok = $cajaModel->actualizar($id, $payload, $idUsuario);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    // ===== ELIMINAR (lógico) =====
    case 'eliminar':
        $id = (int)($_POST['id_caja'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_caja requerido']); break; }
        $ok = $cajaModel->eliminar($id, $idUsuario);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
