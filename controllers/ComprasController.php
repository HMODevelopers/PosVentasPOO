<?php
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

header('Content-Type: application/json; charset=UTF-8');

// Modelo
include_once '../models/CompraModel.php';

$compraModel = new CompraModel();

// Acción por GET/POST
$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    // ================================
    // LISTAR COMPRAS (con paginación)
    // ================================
    case 'listar':
        $pagina      = (int)($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
        $limite      = (int)($_POST['limite'] ?? $_GET['limite'] ?? 10);
        $folio       = trim($_POST['folio'] ?? $_GET['folio'] ?? '');
        $fecha       = trim($_POST['fecha'] ?? $_GET['fecha'] ?? '');         // Y-m-d
        $estatus     = trim($_POST['estatus'] ?? $_GET['estatus'] ?? '');     // Pendiente|Pagada|Parcial|Cancelada
        $idProveedor = $_POST['id_proveedor'] ?? $_GET['id_proveedor'] ?? null;

        $compras = $compraModel->obtenerCompras($pagina, $limite, $folio, $fecha, $estatus, $idProveedor);
        $total   = $compraModel->contarCompras($folio, $fecha, $estatus, $idProveedor);

        echo json_encode([
            'data'  => $compras,
            'total' => (int)$total
        ]);
    break;
    
    // ================================
    // LISTAR DETALLE DE COMPRAS (PRODUCTOS)
    // ================================
    case 'listar-detalle':
        $pagina      = (int)($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
        $limite      = (int)($_POST['limite'] ?? $_GET['limite'] ?? 50);

        $codigo      = trim($_POST['codigo'] ?? $_GET['codigo'] ?? '');
        $fecha       = trim($_POST['fecha']  ?? $_GET['fecha']  ?? ''); // Y-m-d
        $folio       = trim($_POST['folio']  ?? $_GET['folio']  ?? '');
        $idProveedor = $_POST['id_proveedor'] ?? $_GET['id_proveedor'] ?? null;
        if ($idProveedor !== null && $idProveedor !== '') {
            $idProveedor = (int)$idProveedor;
        } else {
            $idProveedor = null;
        }

        $items = $compraModel->obtenerComprasDetalle(
            $pagina,
            $limite,
            $codigo,
            $fecha,
            $folio,
            $idProveedor
        );
        $total = $compraModel->contarComprasDetalle(
            $codigo,
            $fecha,
            $folio,
            $idProveedor
        );

        echo json_encode([
            'data'  => $items,
            'total' => (int)$total
        ]);
    break;

    // ================================
    // CREAR COMPRA (encabezado+detalle)
    // ================================
    case 'crear':
        session_start();
        $payload   = json_decode(file_get_contents('php://input'), true) ?? [];
        $compra    = $payload['compra']   ?? [];
        $detalles  = $payload['detalles'] ?? [];

        // Asegura id_usuario desde sesión si no viene en payload
        if (empty($compra['id_usuario'])) {
            $compra['id_usuario'] = $_SESSION['usuario']['id_usuario']
                                ?? $_SESSION['usuario']['id']
                                ?? $_SESSION['id_usuario']
                                ?? null;
        }

        // Asegura id_sucursal si manejas multi-sucursal
        if (!isset($compra['id_sucursal'])) {
            $compra['id_sucursal'] = $_SESSION['id_sucursal'] ?? ($_SESSION['usuario']['id_sucursal'] ?? null);
        }

        if (empty($compra['id_usuario'])) {
            echo json_encode(['ok' => false, 'msg' => 'Falta id_usuario (sesión).']);
            break;
        }

        if (empty($compra['id_proveedor'])) {
            echo json_encode(['ok' => false, 'msg' => 'Falta id_proveedor.']);
            break;
        }

        if (empty($detalles) || !is_array($detalles)) {
            echo json_encode(['ok' => false, 'msg' => 'Debes enviar al menos un renglón en "detalles".']);
            break;
        }

        $resp = $compraModel->crearCompra($compra, $detalles);
        echo json_encode($resp);
    break;

    // ================================
    // ACTUALIZAR COMPRA
    //  - Puede actualizar solo encabezado
    //  - O reemplazar completamente el detalle (si se envía "detalles")
    // ================================
    case 'actualizar':
        session_start();
        $payload   = json_decode(file_get_contents('php://input'), true) ?? [];
        $idCompra  = (int)($payload['id_compra'] ?? 0);
        $compra    = $payload['compra']    ?? [];
        $detalles  = $payload['detalles']  ?? null;   // puede venir null o []
        // si no te mandan el flag, infiere reemplazo si vienen detalles
        $reemplazar = (bool)($payload['reemplazar_detalles'] ?? (is_array($detalles) && count($detalles) > 0));

        if ($idCompra <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'id_compra inválido']);
            break;
        }

        // completa sesión
        if (empty($compra['id_usuario'])) {
            $compra['id_usuario'] = $_SESSION['usuario']['id_usuario']
                                ?? $_SESSION['usuario']['id']
                                ?? $_SESSION['id_usuario']
                                ?? null;
        }
        if (!isset($compra['id_sucursal'])) {
            $compra['id_sucursal'] = $_SESSION['id_sucursal'] ?? ($_SESSION['usuario']['id_sucursal'] ?? null);
        }
        if (empty($compra['id_usuario'])) {
            echo json_encode(['ok' => false, 'msg' => 'Falta id_usuario (sesión).']);
            break;
        }

        $resp = $compraModel->actualizarCompra($idCompra, $compra, $reemplazar ? (array)$detalles : null, $reemplazar);
        echo json_encode($resp);
    break;

    // ================================
    // DETALLE (encabezado + renglones)
    // ================================
    case 'detalle':
        $idCompra = (int)($_GET['id_compra'] ?? $_POST['id_compra'] ?? 0);
        if ($idCompra <= 0) {
            echo json_encode(['error' => 'id_compra inválido']);
            break;
        }
        $compra   = $compraModel->obtenerCompraPorId($idCompra);
        $detalles = $compraModel->obtenerDetalleCompra($idCompra);

        echo json_encode([
            'compra'   => $compra,
            'detalles' => $detalles
        ]);
    break;

    // ================================
    // CAMBIAR ESTATUS (opcional)
    // ================================
    case 'cambiar-estatus':
        $idCompra = (int)($_POST['id_compra'] ?? 0);
        $estatus  = trim($_POST['estatus'] ?? 'Pagada'); // Pendiente|Pagada|Parcial|Cancelada

        if ($idCompra <= 0) {
            echo json_encode(['resultado' => 'error', 'msg' => 'id_compra inválido']);
            break;
        }

        if (method_exists($compraModel, 'cambiarEstatus')) {
            $ok = $compraModel->cambiarEstatus($idCompra, $estatus);
        } else {
            // fallback (requiere $pdo global de includes/db.php)
            global $pdo;
            $st = $pdo->prepare("UPDATE compras SET estatus = :e WHERE id_compra = :id");
            $ok = $st->execute([':e' => $estatus, ':id' => $idCompra]);
        }

        echo json_encode(['resultado' => $ok ? 'ok' : 'error']);
    break;

    // =====================================
    // ELIMINAR (CANCELAR) + REVERSA STOCK
    // =====================================
    case 'eliminar': // cancela la compra y descuenta el stock
        session_start();

        $idCompra   = (int)($_POST['id_compra'] ?? 0);
        $motivo     = trim($_POST['motivo'] ?? 'Cancelación de compra');

        $idSucursal = $_SESSION['id_sucursal']  ?? ($_SESSION['usuario']['id_sucursal'] ?? 1);
        $idUsuario  = $_SESSION['usuario']['id_usuario'] ?? ($_SESSION['usuario']['id'] ?? null);

        if (!$idCompra || !$idSucursal || !$idUsuario) {
            echo json_encode(['ok' => false, 'msg' => 'Faltan datos: id_compra / sesión (usuario/sucursal).']);
            break;
        }

        $resp = $compraModel->cancelarCompra($idCompra, (int)$idSucursal, (int)$idUsuario, $motivo);
        echo json_encode($resp);
    break;

    // =====================================
    // RECALCULAR TOTAL DESDE DETALLE
    // =====================================
    case 'actualizar-total':
        $idCompra = (int)($_POST['id_compra'] ?? 0);
        if ($idCompra <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'id_compra inválido']);
            break;
        }
        if (!method_exists($compraModel, 'actualizarTotal')) {
            echo json_encode(['ok' => false, 'msg' => 'El modelo no expone actualizarTotal().']);
            break;
        }
        $ok = $compraModel->actualizarTotal($idCompra);
        echo json_encode(['ok' => (bool)$ok]);
    break;

    // ================================
    // ACCIÓN NO RECONOCIDA
    // ================================
    default:
        echo json_encode(['error' => 'Acción no válida']);
    break;
}
