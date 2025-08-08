<?php
header('Content-Type: application/json');

// Incluir modelo y conexión
include_once '../models/VentaModel.php';

// Instancia del modelo
$ventaModel = new VentaModel();

// Detectar acción enviada por POST o GET
$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    case 'listar':
    $pagina = $_POST['pagina'] ?? 1;
    $limite = $_POST['limite'] ?? 10;
    $folio = $_POST['folio'] ?? '';
    $fecha = $_POST['fecha'] ?? '';

    // Pasa los filtros al modelo
    $ventas = $ventaModel->obtenerVentas($pagina, $limite, $folio, $fecha);
    $total = $ventaModel->contarVentas($folio, $fecha);

    echo json_encode([
        'data' => $ventas,
        'total' => $total
    ]);
    break;

    // 🆕 Crear nueva venta
    case 'crear':
        $data = json_decode(file_get_contents("php://input"), true);
        
        $venta = $data['venta'] ?? [];
        $detalles = $data['detalles'] ?? [];

        $respuesta = $ventaModel->crearVenta($venta, $detalles);
        echo json_encode(['resultado' => $respuesta]);
        break;

    // 📄 Obtener detalle de una venta
    case 'detalle':
        $idVenta = $_GET['id_venta'] ?? 0;
        $venta = $ventaModel->obtenerVentaPorId($idVenta);
        $detalles = $ventaModel->obtenerDetalleVenta($idVenta);

        echo json_encode([
            'venta' => $venta,
            'detalles' => $detalles
        ]);
        break;

    // 🔄 Cambiar estatus de venta (ej: Cancelada, Devuelta)
    case 'cambiar-estatus':
        $id = $_POST['id_venta'] ?? 0;
        $estatus = $_POST['estatus'] ?? 'Cancelada';

        $ok = $ventaModel->cambiarEstatus($id, $estatus);
        echo json_encode(['resultado' => $ok ? 'ok' : 'error']);
        break;

    // 🗑️ Eliminar venta (borrado lógico)
    case 'eliminar':
        $id = $_POST['id_venta'] ?? 0;
        $ok = $ventaModel->eliminarVenta($id);
        echo json_encode(['resultado' => $ok ? 'ok' : 'error']);
        break;

    // 🚫 Acción no reconocida
    default:
        echo json_encode(['error' => 'Acción no válida']);
        break;
}
