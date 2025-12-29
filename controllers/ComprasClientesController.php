<?php
// controllers/ComprasClientesController.php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../models/ComprasClientesModel.php';
require_once __DIR__ . '/../includes/db.php'; // asegura $pdo

// Adapta a tu estructura real de sesión.
$usuarioSesion = $_SESSION['usuario'];

// Intentamos obtener rol/nombre desde la sesión.
$rolNombreSesion = $usuarioSesion['rol_nombre'] ?? $usuarioSesion['rol'] ?? $usuarioSesion['tipo'] ?? null;

// Ids útiles
$idUsuarioSesion = $usuarioSesion['id_usuario'] ?? null;   // INT esperado
$idClienteSesion = $usuarioSesion['id_cliente'] ?? null;    // INT esperado

$model = new ComprasClientesModel();

// Si no tenemos nombre de rol, lo resolvemos por BD (usuarios -> roles).
if (!$rolNombreSesion && $idUsuarioSesion) {
    $rolResuelto = $model->obtenerRolNombrePorUsuario((int)$idUsuarioSesion);
    if ($rolResuelto) { $rolNombreSesion = $rolResuelto; }
}

$accion = $_REQUEST['accion'] ?? 'listar';

switch ($accion) {
    case 'listar':
        try {
            $pagina      = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
            $limite      = (int)($_GET['limite'] ?? $_POST['limite'] ?? 20);
            $codigo      = trim($_GET['codigo'] ?? $_POST['codigo'] ?? '');
            $descripcion = trim($_GET['descripcion'] ?? $_POST['descripcion'] ?? '');
            $fechaVenta  = trim($_GET['fecha'] ?? $_POST['fecha'] ?? '');

            $scope = [
                'rol_nombre' => $rolNombreSesion ? strtoupper((string)$rolNombreSesion) : null,
                'id_cliente' => $idClienteSesion ? (int)$idClienteSesion : null,
                'id_usuario' => $idUsuarioSesion ? (int)$idUsuarioSesion : null,
            ];

            $r = $model->listar($pagina, $limite, $codigo, $descripcion, $fechaVenta ?: null, $scope);

            $paginas = $limite > 0 ? (int)ceil($r['total'] / $limite) : 1;

            echo json_encode([
                'ok'          => true,
                'data'        => $r['rows'],
                'total'       => $r['total'],
                'pagina'      => $pagina,
                'paginas'     => $paginas,
                'limite'      => $limite,
                'suma_total'  => $r['suma_total'],
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok'  => false,
                'msg' => 'Error al listar compras',
                'err' => $e->getMessage(),
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok'=>false, 'msg'=>'Acción no soportada']);
        break;
}
