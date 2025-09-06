<?php
header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once '../models/PrestamoModel.php';
$model = new PrestamoModel();

$accion = $_REQUEST['accion'] ?? '';

// Toma el id del usuario logueado, igual que tu controller de clientes
$idUsuario = null;
if (!empty($_SESSION['usuario'])) {
    $u = $_SESSION['usuario'];
    $idUsuario = $u['id_usuario'] ?? $u['idUsuario'] ?? $u['id'] ?? null;
}

switch ($accion) {

    /* ===== LISTAR ===== */
    case 'listar':
        $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
        $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 10);
        $filtros = [
            'q'              => trim($_GET['q']              ?? $_POST['q']              ?? ''),
            'tipo_operacion' => trim($_GET['tipo_operacion'] ?? $_POST['tipo_operacion'] ?? ''),
            'estatus'        => trim($_GET['estatus']        ?? $_POST['estatus']        ?? ''),
            'tipo'           => trim($_GET['tipo']           ?? $_POST['tipo']           ?? ''),
            'id_cliente'     => trim($_GET['id_cliente']     ?? $_POST['id_cliente']     ?? ''),
            'id_empleado'    => trim($_GET['id_empleado']    ?? $_POST['id_empleado']    ?? ''),
            'desde'          => trim($_GET['desde']          ?? $_POST['desde']          ?? ''),
            'hasta'          => trim($_GET['hasta']          ?? $_POST['hasta']          ?? ''),
        ];
        $data  = $model->listar($pagina, $limite, $filtros);
        $total = $model->contar($filtros);
        echo json_encode(['ok'=>true, 'data'=>$data, 'total'=>$total]);
    break;

    /* ===== LISTA CORTA (préstamos con saldo) ===== */
    case 'listar-min':
        $q   = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim = (int)($_GET['limite'] ?? $_POST['limite'] ?? 50);
        $data = $model->listarMin($q, $lim);
        echo json_encode(['ok'=>true, 'data'=>$data]);
    break;

    /* ===== DETALLE ===== */
    case 'detalle':
        $id = (int)($_GET['id_prestamo'] ?? $_POST['id_prestamo'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_prestamo inválido']); break; }
        $row = $model->obtenerPorId($id);
        echo json_encode(['ok'=>true, 'data' => $row]);
    break;

    /* ===== CREAR ===== */
    case 'crear':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = $model->crear($payload ?? [], $idUsuario);
        echo json_encode(['ok' => $id > 0, 'id_prestamo' => $id]);
    break;

    /* ===== ACTUALIZAR (editar datos del registro) ===== */
    case 'actualizar':
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($payload['id_prestamo'] ?? $_POST['id_prestamo'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_prestamo requerido']); break; }
        $ok = $model->actualizar($id, $payload, $idUsuario);
        echo json_encode(['ok'=>(bool)$ok]);
    break;

    /* ===== ABONAR ===== */
    case 'abonar':
        $idPrestamo = (int)($_POST['id_prestamo'] ?? $_GET['id_prestamo'] ?? 0);
        $monto      = (float)($_POST['monto'] ?? $_GET['monto'] ?? 0);
        $fecha      = $_POST['fecha_abono'] ?? $_GET['fecha_abono'] ?? date('Y-m-d H:i:s');
        $ref        = $_POST['referencia_pago'] ?? $_GET['referencia_pago'] ?? null;

        if ($idPrestamo<=0 || $monto<=0) { echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); break; }

        $ok = $model->abonar($idPrestamo, $monto, $fecha, $ref, $idUsuario);
        echo json_encode(['ok'=>(bool)$ok]);
    break;

    /* ===== CANCELAR ===== */
    case 'cancelar':
        $id = (int)($_POST['id_prestamo'] ?? $_GET['id_prestamo'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_prestamo requerido']); break; }
        $ok = $model->cancelar($id, $idUsuario);
        echo json_encode(['ok'=>(bool)$ok]);
    break;

    /* ===== ELIMINAR (lógico) ===== */
    case 'eliminar':
        $id = (int)($_POST['id_prestamo'] ?? $_GET['id_prestamo'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_prestamo requerido']); break; }
        $ok = $model->eliminar($id, $idUsuario);
        echo json_encode(['ok'=>(bool)$ok]);
    break;

    default:
        echo json_encode(['ok'=>false, 'msg'=>'Acción no válida']);
    break;
}
