<?php
header('Content-Type: application/json');


include_once '../models/VentaModel.php';
$ventaModel = new VentaModel();

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    case 'listar':
        $pagina = $_POST['pagina'] ?? 1;
        $limite = $_POST['limite'] ?? 10;
        $folio  = $_POST['folio']  ?? '';
        $fecha  = $_POST['fecha']  ?? '';

        $ventas = $ventaModel->obtenerVentas($pagina, $limite, $folio, $fecha);
        $total  = $ventaModel->contarVentas($folio, $fecha);

        echo json_encode(['data' => $ventas, 'total' => $total]);
        break;

    // ➕ Crear nueva venta (con folio único y movimientos de inventario)
    case 'crear':
        session_start();

        $data     = json_decode(file_get_contents("php://input"), true) ?? [];
        $venta    = $data['venta']    ?? [];
        $detalles = $data['detalles'] ?? [];

        // ========= Mapear sesión con tolerancia a distintos nombres =========
        $usr                 = $_SESSION['usuario'] ?? [];
        $id_usuario_sesion   = $usr['id_usuario'] ?? ($usr['id'] ?? ($_SESSION['id_usuario'] ?? null));
        $id_sucursal_sesion  = $_SESSION['id_sucursal'] ?? ($usr['id_sucursal'] ?? null);
        $id_caja_sesion      = $_SESSION['id_caja'] ?? ($usr['id_caja'] ?? ($_SESSION['caja_activa'] ?? null));

        // Permite que vengan en el payload, pero si no, usa sesión
        $venta['id_usuario']  = $venta['id_usuario']  ?? $id_usuario_sesion;
        $venta['id_sucursal'] = $venta['id_sucursal'] ?? $id_sucursal_sesion ?? 1;  // fallback sucursal 1
        $venta['id_caja']     = $venta['id_caja']     ?? $id_caja_sesion     ?? 1;  // fallback caja 1

        // Cliente opcional (NULL si no mandan)
        if (!array_key_exists('id_cliente', $venta)) {
            $venta['id_cliente'] = null;
        } elseif ($venta['id_cliente'] === '' || $venta['id_cliente'] === 0) {
            $venta['id_cliente'] = null;
        }

        // Mapeo de tipo de precio si mandas el slug desde la vista
        if (empty($venta['id_tipo_precio']) && !empty($venta['tipo_precio_slug'])) {
            $map = ['publico'=>1, 'taller'=>2, 'proveedor'=>3];
            $venta['id_tipo_precio'] = $map[$venta['tipo_precio_slug']] ?? 1;
        }

        // Validación mínima: necesitamos saber quién vende
        if (empty($venta['id_usuario'])) {
            echo json_encode(['ok'=>false, 'msg'=>'Falta id_usuario en sesión. Inicia sesión nuevamente.']);
            break;
        }

        $resp = $ventaModel->crearVenta($venta, $detalles);
        echo json_encode($resp);
    break;

    // 🔎 Detalle de venta
    case 'detalle':
        $idVenta  = $_GET['id_venta'] ?? 0;
        $venta    = $ventaModel->obtenerVentaPorId($idVenta);
        $detalles = $ventaModel->obtenerDetalleVenta($idVenta);
        echo json_encode(['venta' => $venta, 'detalles' => $detalles]);
        break;

    // 🔁 Cambiar estatus
    case 'cambiar-estatus':
        $id      = $_POST['id_venta'] ?? 0;
        $estatus = $_POST['estatus']  ?? 'Cancelada';
        $ok = $ventaModel->cambiarEstatus($id, $estatus);
        echo json_encode(['resultado' => $ok ? 'ok' : 'error']);
        break;

    // 🗑️ Cancelar (borrado lógico + reponer stock)
    case 'eliminar':
        $id      = $_POST['id_venta'] ?? 0;
        $motivo  = $_POST['motivo']   ?? 'Cancelación de venta';

        $id_sucursal = $_SESSION['usuario']['id_sucursal'] ?? 1;
        $id_usuario  = $_SESSION['usuario']['id_usuario']  ?? null;

        if (!$id || !$id_sucursal || !$id_usuario) {
            echo json_encode(['ok' => false, 'msg' => 'Faltan datos de sesión o id_venta.']);
            break;
        }

        $resp = $ventaModel->cancelarVenta($id, $id_sucursal, $id_usuario, $motivo);
        echo json_encode($resp);
        break;

    // 🆕 Folio sugerido (para mostrar en la UI junto a la fecha)
    case 'folio-sugerido':
        $fecha = $_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d');
        $resp  = $ventaModel->sugerirFolioPorFecha($fecha);
        echo json_encode($resp);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
        break;
}
