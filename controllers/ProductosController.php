<?php
header('Content-Type: application/json; charset=UTF-8');

include_once '../models/ProductoModel.php';
$productoModel = new ProductoModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // ===== LISTAR PAGINADO =====
    case 'listar':
        $pagina = max(1, (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
        $limite = max(1, (int)($_GET['limite'] ?? $_POST['limite'] ?? 10));

        // Filtros
        $codigo      = trim($_GET['codigo']      ?? $_POST['codigo']      ?? '');
        $descripcion = trim($_GET['descripcion'] ?? $_POST['descripcion'] ?? '');
        $idProveedor = (isset($_REQUEST['id_proveedor']) && $_REQUEST['id_proveedor'] !== '')
                        ? (int)$_REQUEST['id_proveedor']
                        : null;
        // NUEVO: filtro por grupo
        $idGrupo     = (isset($_REQUEST['id_grupo']) && $_REQUEST['id_grupo'] !== '')
                        ? (int)$_REQUEST['id_grupo']
                        : null;

        // Asegúrate de que tu ProductoModel::listar/contar acepten $idGrupo como último parámetro
        $data  = $productoModel->listar($pagina, $limite, $codigo, $descripcion, $idProveedor, $idGrupo);
        $total = $productoModel->contar($codigo, $descripcion, $idProveedor, $idGrupo);

        echo json_encode(['data' => $data, 'total' => $total], JSON_UNESCAPED_UNICODE);
    break;

    // ===== DETALLE =====
    case 'detalle':
        $id = (int)($_GET['id_producto'] ?? $_POST['id_producto'] ?? 0);
        if ($id <= 0) { echo json_encode(['error'=>'id_producto inválido'], JSON_UNESCAPED_UNICODE); break; }
        // Sugerido en el modelo: JOIN con cat_grupos y alias g.nombre_grupo AS grupo
        $row = $productoModel->obtenerPorId($id);
        echo json_encode(['data' => $row], JSON_UNESCAPED_UNICODE);
    break;

    // ===== CREAR =====
    case 'crear':
        session_start();
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!is_array($payload)) $payload = [];

        // Asegura id_usuario desde sesión si no viene en payload (igual que Compras)
        if (empty($payload['id_usuario'])) {
            $payload['id_usuario'] = $_SESSION['usuario']['id_usuario']
                                  ?? $_SESSION['usuario']['id']
                                  ?? $_SESSION['id_usuario']
                                  ?? null;
        }

        // Asegura id_sucursal si manejas multi-sucursal (igual que Compras: sin default aquí)
        if (!isset($payload['id_sucursal'])) {
            $payload['id_sucursal'] = $_SESSION['id_sucursal'] ?? ($_SESSION['usuario']['id_sucursal'] ?? null);
        }

        if (empty($payload['id_usuario'])) {
            echo json_encode(['ok' => false, 'msg' => 'Falta id_usuario (sesión).'], JSON_UNESCAPED_UNICODE);
            break;
        }

        // NUEVO: normaliza id_grupo (null si no viene)
        if (!array_key_exists('id_grupo', $payload) || $payload['id_grupo'] === '' || $payload['id_grupo'] === null) {
            $payload['id_grupo'] = null;
        } else {
            $payload['id_grupo'] = (int)$payload['id_grupo'];
        }

        // Llama al modelo y devuelve la respuesta tal cual (sin envolver)
        $resp = $productoModel->crear($payload);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== ACTUALIZAR =====
    case 'actualizar':
        session_start();
        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!is_array($payload)) $payload = [];

        $id = (int)($payload['id_producto'] ?? $_POST['id_producto'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id_producto requerido'], JSON_UNESCAPED_UNICODE); break; }

        // Igual que Compras: asegura id_usuario desde sesión si no viene en payload
        if (empty($payload['id_usuario'])) {
            $payload['id_usuario'] = $_SESSION['usuario']['id_usuario']
                                  ?? $_SESSION['usuario']['id']
                                  ?? $_SESSION['id_usuario']
                                  ?? null;
        }

        // id_sucursal desde sesión si no viene
        if (!isset($payload['id_sucursal'])) {
            $payload['id_sucursal'] = $_SESSION['id_sucursal'] ?? ($_SESSION['usuario']['id_sucursal'] ?? null);
        }

        if (empty($payload['id_usuario'])) {
            echo json_encode(['ok' => false, 'msg' => 'Falta id_usuario (sesión).'], JSON_UNESCAPED_UNICODE);
            break;
        }

        // NUEVO: normaliza id_grupo (null si no viene)
        if (!array_key_exists('id_grupo', $payload) || $payload['id_grupo'] === '' || $payload['id_grupo'] === null) {
            $payload['id_grupo'] = null;
        } else {
            $payload['id_grupo'] = (int)$payload['id_grupo'];
        }

        // Devuelve lo que regrese el modelo (consistente con Compras)
        $resp = $productoModel->actualizar($id, $payload);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== ELIMINAR (lógico) =====
    // Firma al estilo Compras::eliminar (cancelar): requiere usuario y sucursal
    case 'eliminar':
        session_start();

        $id = (int)($_POST['id_producto'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? 'Desactivación de producto');

        // Igual que Compras: de sesión con default 1 para sucursal
        $idSucursal = $_SESSION['id_sucursal'] ?? ($_SESSION['usuario']['id_sucursal'] ?? 1);
        $idUsuario  = $_SESSION['usuario']['id_usuario'] ?? ($_SESSION['usuario']['id'] ?? $_SESSION['id_usuario'] ?? null);

        if ($id <= 0 || empty($idUsuario) || empty($idSucursal)) {
            echo json_encode(['ok'=>false,'msg'=>'Faltan datos: id_producto / sesión (usuario/sucursal).'], JSON_UNESCAPED_UNICODE);
            break;
        }

        // Llama a la firma del modelo basada en Compras: eliminar($idProducto, $idSucursal, $idUsuario, $motivo)
        $resp = $productoModel->eliminar((int)$id, (int)$idSucursal, (int)$idUsuario, $motivo);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    break;

    // ===== BUSCAR-MIN (para selects y typeahead) =====
    case 'buscar-min':
        $q   = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $lim = (int)($_GET['limite'] ?? $_POST['limite'] ?? 50);
        $lim = max(1, min($lim, 500));
        $data = $productoModel->buscarMin($q, $lim);
        echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
    break;

    // ===== SIMULAR PRECIOS =====
    case 'simular-precios':
        $idProv = (int)($_REQUEST['id_proveedor'] ?? 0);
        $ppv    = (float)($_REQUEST['precio_proveedor'] ?? 0);
        $data   = $productoModel->simularPrecios($idProv, $ppv);
        echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
    break;

    default:
        echo json_encode(['error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
    break;
}
