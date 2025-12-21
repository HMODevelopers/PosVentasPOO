<?php
    /**
     * Controllers/VentasController.php
     * Devuelve JSON en todas las rutas y acepta body JSON.
     */
    header('Content-Type: application/json; charset=UTF-8');

    // Sesión siempre disponible
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    // Carga modelo
    require_once __DIR__ . '/../models/VentaModel.php';
    $ventaModel = new VentaModel();

    // Lee body JSON si viene
    $RAW = json_decode(file_get_contents('php://input'), true);
    if (!is_array($RAW)) { $RAW = []; }

    // Acción (querystring, form-data o JSON)
    $accion = $_REQUEST['accion'] ?? $RAW['accion'] ?? '';

    /** Helper: mapea slug de tipo de precio a id_tipo_precio */
    function map_tipo_precio(?string $slug): int {
        $slug = strtolower(trim((string)$slug));
        $map = ['publico' => 1, 'taller' => 2, 'proveedor' => 3];
        return $map[$slug] ?? 2; // default -> taller (como en la UI)
    }

    /** Helper: respuesta de error estándar */
    function jserr(string $msg, int $code = 200): void {
        if ($code >= 400) http_response_code($code);
        echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

/** Helper: extrae enteros con seguridad */
function i($v): int { return (int) (is_numeric($v) ? $v : 0); }

try {

    switch ($accion) {

        /* ============================================================
         * Listar ventas (paginado + filtros)
         * POST/GET: pagina, limite, folio?, fecha?, estatus?
         * ============================================================ */
        case 'listar': {
            $pagina = i($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
            $limite = i($_POST['limite'] ?? $_GET['limite'] ?? 10);
            $folio  = trim($_POST['folio'] ?? $_GET['folio'] ?? '');
            $fecha  = trim($_POST['fecha'] ?? $_GET['fecha'] ?? '');
            $estatus= trim($_POST['estatus'] ?? $_GET['estatus'] ?? '');

            if ($pagina < 1) $pagina = 1;
            if ($limite < 1) $limite = 10;

            $ventas = $ventaModel->obtenerVentas($pagina, $limite, $folio, $fecha, $estatus);
            $total  = $ventaModel->contarVentas($folio, $fecha, $estatus);

            // Model ya trae: saldo, abonado, estatus_credito
            echo json_encode(['ok' => true, 'data' => $ventas, 'total' => $total], JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Crear venta (Activa/Guardada/Credito)
         * Body JSON: { venta:{...}, detalles:[...], pagos:[...] }
         *   - pagos: [
         *       { "id_forma_pago": 1, "monto": 500, "referencia": "xxx" },
         *       { "id_forma_pago": 4, "monto": 200, "referencia": "yyy" }
         *     ]
         * ============================================================ */
        case 'crear': {
            $data     = $RAW ?: [];
            $venta    = $data['venta']    ?? [];
            $detalles = $data['detalles'] ?? [];
            $pagos    = $data['pagos']    ?? []; // NUEVO: pagos para venta mixta

            // Sesión robusta
            $usr                = $_SESSION['usuario'] ?? [];
            $id_usuario_sesion  = $usr['id_usuario']  ?? ($usr['id'] ?? ($_SESSION['id_usuario'] ?? null));
            $id_sucursal_sesion = $usr['id_sucursal'] ?? ($_SESSION['id_sucursal'] ?? null);
            $id_caja_sesion     = $usr['id_caja']     ?? ($_SESSION['id_caja'] ?? ($_SESSION['caja_activa'] ?? null));

            if (empty($id_usuario_sesion)) jserr('Falta id_usuario en sesión. Vuelve a iniciar sesión.');

            // Permitir override desde payload, si no usar sesión
            $venta['id_usuario']  = $venta['id_usuario']  ?? $id_usuario_sesion;
            $venta['id_sucursal'] = $venta['id_sucursal'] ?? ($id_sucursal_sesion ?? 1);
            $venta['id_caja']     = $venta['id_caja']     ?? ($id_caja_sesion     ?? 1);

            // Cliente opcional → null si viene vacío/0
            if (!array_key_exists('id_cliente', $venta) || empty($venta['id_cliente'])) {
                $venta['id_cliente'] = null;
            }

            // Tipo de precio (id o slug). Default -> TALLER (2) si no viene.
            if (empty($venta['id_tipo_precio']) && !empty($venta['tipo_precio_slug'])) {
                $venta['id_tipo_precio'] = map_tipo_precio($venta['tipo_precio_slug']);
            }
            if (empty($venta['id_tipo_precio'])) {
                $venta['id_tipo_precio'] = 2;
            }

            // Validaciones mínimas
            if (!is_array($detalles) || !count($detalles)) jserr('Debes enviar al menos un detalle.');

            // Suave: si llega explícito "Credito" pero sin cliente
            $estatusIn = strtolower(trim((string)($venta['estatus'] ?? '')));
            if (($estatusIn === 'credito' || $estatusIn === 'crédito') && empty($venta['id_cliente'])) {
                jserr('Para ventas a crédito es obligatorio seleccionar un cliente.', 400);
            }

            $resp = $ventaModel->crearVenta($venta, $detalles, $pagos);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Detalle (cabecera + partidas + abonos + saldo + estatus_credito)
         * GET/POST/JSON: id_venta
         * ============================================================ */
        case 'detalle': {
            $idVenta  = i($_GET['id_venta'] ?? $_POST['id_venta'] ?? $RAW['id_venta'] ?? 0);
            if ($idVenta <= 0) jserr('id_venta requerido.');

            $venta    = $ventaModel->obtenerVentaPorId($idVenta);
            if (!$venta) jserr('Venta no encontrada.', 404);

            $detalles = $ventaModel->obtenerDetalleVenta($idVenta);

            // El modelo ya trae en $venta: abonado, saldo, estatus_credito y abonos[]
            $abonos  = $venta['abonos'] ?? [];
            $abonado = (float)($venta['abonado'] ?? 0);
            $saldo   = (float)($venta['saldo']   ?? max(0, (float)$venta['total'] - $abonado));

            // Totales de costo y utilidad desde ventas_detalle
            $costo_total = 0.0;
            $util_total  = 0.0;
            foreach ($detalles as $d) {
                $costo_total += (float)($d['costo_subtotal'] ?? 0);
                $util_total  += (float)($d['utilidad_subtotal'] ?? 0);
            }

            echo json_encode([
                'ok'              => true,
                'venta'           => $venta,          // incluye estatus_credito, saldo, abonado
                'detalles'        => $detalles,
                'abonos'          => $abonos,
                'abonado'         => round($abonado, 2),
                'saldo'           => round($saldo, 2),
                'estatus_credito' => $venta['estatus_credito'] ?? 'N/A',
                'costo_total'     => round($costo_total, 2),
                'utilidad_total'  => round($util_total, 2)
            ], JSON_UNESCAPED_UNICODE);

            break;
        }

        /* ============================================================
         * Registrar ABONO de una venta a crédito
         * - Simple:      id_venta, monto, id_forma_pago, fecha_abono?, referencia_pago?
         * - Mixto:       id_venta, tipo_pago = 'mixto', fecha_abono?, pagos = [ {id_forma_pago,monto,referencia_pago?}, ... ]
         * ============================================================ */
        case 'abonar-venta': {
            $idVenta    = i($_POST['id_venta'] ?? $_GET['id_venta'] ?? $RAW['id_venta'] ?? 0);
            $fechaAbono = trim($_POST['fecha_abono'] ?? $_GET['fecha_abono'] ?? $RAW['fecha_abono'] ?? '');
            $ref        = trim($_POST['referencia_pago'] ?? $_GET['referencia_pago'] ?? $RAW['referencia_pago'] ?? '');
            $tipoPago   = $_POST['tipo_pago'] ?? $_GET['tipo_pago'] ?? $RAW['tipo_pago'] ?? null;

            // Usuario de sesión
            $usr       = $_SESSION['usuario'] ?? [];
            $idUsuario = $usr['id_usuario'] ?? ($usr['id'] ?? ($_SESSION['id_usuario'] ?? null));
            if (!$idUsuario) jserr('No hay usuario en sesión (id_usuario).');

            if ($idVenta <= 0) jserr('id_venta requerido.');

            // === MODO MIXTO: arreglo de pagos ===
            if (is_string($tipoPago) && strtolower($tipoPago) === 'mixto') {
                $pagosRaw = $_POST['pagos'] ?? $_GET['pagos'] ?? $RAW['pagos'] ?? null;
                $pagosMixtos = null;
                if ($pagosRaw !== null && $pagosRaw !== '') {
                    $tmp = json_decode($pagosRaw, true);
                    if (is_array($tmp)) {
                        $pagosMixtos = $tmp;
                    }
                }

                if (empty($pagosMixtos) || !is_array($pagosMixtos)) {
                    jserr('Se requiere capturar los pagos para el abono mixto.');
                }
                if (!method_exists($ventaModel, 'abonarVentaMixto')) {
                    jserr('El modelo no soporta abonarVentaMixto(). Agrega el método al VentaModel.');
                }

                $resp = $ventaModel->abonarVentaMixto(
                    $idVenta,
                    $pagosMixtos,
                    $fechaAbono ?: null,
                    $idUsuario
                );
                echo json_encode($resp, JSON_UNESCAPED_UNICODE);
                break;
            }

            // === MODO SIMPLE: un solo renglón de abono ===
            $monto       = (float)($_POST['monto'] ?? $_GET['monto'] ?? $RAW['monto'] ?? 0);
            $idFormaPago = i($_POST['id_forma_pago'] ?? $_GET['id_forma_pago'] ?? $RAW['id_forma_pago'] ?? 0);

            if ($monto <= 0 || $idFormaPago <= 0) {
                jserr('Datos inválidos para abono (monto, id_forma_pago).');
            }

            if (!method_exists($ventaModel, 'abonarVenta')) {
                jserr('El modelo no soporta abonarVenta(). Agrega el método al VentaModel.');
            }

            $resp = $ventaModel->abonarVenta(
                $idVenta,
                $monto,
                $idFormaPago,
                $fechaAbono ?: null,
                $ref ?: null,
                $idUsuario
            );

            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Saldo de una venta (rápido)
         * GET/POST/JSON: id_venta
         * ============================================================ */
        case 'saldo-venta': {
            $id = i($_GET['id_venta'] ?? $_POST['id_venta'] ?? $RAW['id_venta'] ?? 0);
            if ($id <= 0) jserr('id_venta requerido.');
            if (!method_exists($ventaModel, 'saldoVenta')) {
                jserr('El modelo no soporta saldoVenta(). Agrega el método al VentaModel.');
            }
            $saldo = $ventaModel->saldoVenta($id);
            echo json_encode(['ok'=>true, 'saldo'=>$saldo], JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Listar abonos de una venta (para UI)
         * GET/POST/JSON: id_venta
         * ============================================================ */
        case 'listar-abonos-venta': {
            $id = i($_GET['id_venta'] ?? $_POST['id_venta'] ?? $RAW['id_venta'] ?? 0);
            if ($id <= 0) jserr('id_venta requerido.');
            if (!method_exists($ventaModel, 'obtenerAbonosVenta')) {
                jserr('El modelo no soporta obtenerAbonosVenta(). Agrega el método al VentaModel.');
            }
            $abonos = $ventaModel->obtenerAbonosVenta($id);
            echo json_encode(['ok'=>true, 'data'=>$abonos], JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Cambiar estatus (simple)
         * POST/GET/JSON: id_venta, estatus
         * ============================================================ */
        case 'cambiar-estatus': {
            $id      = i($_POST['id_venta'] ?? $_GET['id_venta'] ?? $RAW['id_venta'] ?? 0);
            $estatus = trim($_POST['estatus']  ?? $_GET['estatus'] ?? $RAW['estatus'] ?? 'Cancelada');
            if ($id <= 0) jserr('id_venta requerido');

            $ok = $ventaModel->cambiarEstatus($id, $estatus);
            echo json_encode(['ok' => $ok, 'resultado' => $ok ? 'ok' : 'error'], JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Cancelar/Eliminar (regresa stock)
         * POST/GET/JSON: id_venta, motivo?
         * ============================================================ */
        case 'cancelar':
        case 'eliminar': {
            $id     = i($_POST['id_venta'] ?? $_GET['id_venta'] ?? $RAW['id_venta'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? $_GET['motivo'] ?? $RAW['motivo'] ?? 'Cancelación de venta');

            if ($id <= 0) jserr('id_venta requerido.');

            // Sesión
            $usr         = $_SESSION['usuario'] ?? [];
            $id_usuario  = $usr['id_usuario'] ?? ($usr['id'] ?? ($_SESSION['id_usuario'] ?? null));
            $id_sucursal = $usr['id_sucursal'] ?? ($_SESSION['id_sucursal'] ?? 1);

            if (!$id_usuario) jserr('No hay usuario en sesión (id_usuario).');
            if (!$id_sucursal) $id_sucursal = 1;

            $resp = $ventaModel->cancelarVenta($id, $id_sucursal, $id_usuario, $motivo);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Folio sugerido por fecha
         * GET/POST/JSON: fecha (Y-m-d)
         * ============================================================ */
        case 'folio-sugerido': {
            $fecha = $_GET['fecha'] ?? $_POST['fecha'] ?? $RAW['fecha'] ?? date('Y-m-d');
            $resp  = $ventaModel->sugerirFolioPorFecha($fecha);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * ACTUALIZAR (editar TODO)
         * Body JSON: { venta:{ id_venta, ... }, detalles:[...] }
         * ============================================================ */
        case 'actualizar': {
            $data     = $RAW ?: [];
            $venta    = $data['venta']    ?? [];
            $detalles = $data['detalles'] ?? [];

            $idVenta = i($venta['id_venta'] ?? 0);
            if ($idVenta <= 0) jserr('venta.id_venta es requerido.');

            // Cliente opcional → null si vacío
            if (array_key_exists('id_cliente', $venta) && $venta['id_cliente'] === '') {
                $venta['id_cliente'] = null;
            }

            // Tipo de precio: permitir slug
            if (empty($venta['id_tipo_precio']) && !empty($venta['tipo_precio_slug'])) {
                $venta['id_tipo_precio'] = map_tipo_precio($venta['tipo_precio_slug']);
            }

            // Forma de pago puede venir vacío explícito => null
            if (array_key_exists('id_forma_pago', $venta) && $venta['id_forma_pago'] === '') {
                $venta['id_forma_pago'] = null;
            }

            if (!is_array($detalles)) $detalles = [];

            $resp = $ventaModel->actualizarVenta($venta, $detalles);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Activar una venta Guardada (ponerla Activa/Credito)
         * POST/GET/JSON: id_venta, id_forma_pago?, actualizar_fecha?, id_cliente?
         * ============================================================ */
        case 'activar-guardada': {
            $id_venta      = (int)($_POST['id_venta']      ?? $_GET['id_venta']      ?? $RAW['id_venta']      ?? 0);
            $id_forma_pago = (int)($_POST['id_forma_pago'] ?? $_GET['id_forma_pago'] ?? $RAW['id_forma_pago'] ?? 0);
            $id_cliente    = (int)($_POST['id_cliente']    ?? $_GET['id_cliente']    ?? $RAW['id_cliente']    ?? 0);

            $tipo_pago = $_POST['tipo_pago'] ?? $_GET['tipo_pago'] ?? $RAW['tipo_pago'] ?? null;

            // pagos viene como JSON desde el JS en caso de mixto
            $pagosRaw    = $_POST['pagos'] ?? $_GET['pagos'] ?? $RAW['pagos'] ?? null;
            $pagosMixtos = null;
            if ($pagosRaw !== null && $pagosRaw !== '') {
                $tmp = json_decode($pagosRaw, true);
                if (is_array($tmp)) {
                    $pagosMixtos = $tmp;
                }
            }

            $esMixto = is_string($tipo_pago) && strtolower($tipo_pago) === 'mixto';

            $actualizar_fecha = false;
            $af = ($_POST['actualizar_fecha'] ?? $_GET['actualizar_fecha'] ?? $RAW['actualizar_fecha'] ?? 0);
            if ($af === '1' || $af === 1 || $af === true || $af === 'true') {
                $actualizar_fecha = true;
            }

            if ($id_venta <= 0) {
                echo json_encode(['ok'=>false,'msg'=>'id_venta requerido.']);
                break;
            }

            // Si NO es mixto, seguimos exigiendo id_forma_pago
            if (!$esMixto && $id_forma_pago <= 0) {
                echo json_encode(['ok'=>false,'msg'=>'id_forma_pago requerido.']);
                break;
            }

            // Si es mixto, exigimos que vengan pagos
            if ($esMixto && (empty($pagosMixtos) || !is_array($pagosMixtos))) {
                echo json_encode(['ok'=>false,'msg'=>'Se requiere capturar los pagos para el esquema mixto.']);
                break;
            }

            $resp = $ventaModel->activarGuardada(
                $id_venta,
                $id_forma_pago > 0 ? $id_forma_pago : null,
                $actualizar_fecha,
                ($id_cliente ?: null),
                $tipo_pago,
                $pagosMixtos
            );
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Acción no soportada
         * ============================================================ */
        default:
            echo json_encode(['ok' => false, 'msg' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
            break;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'   => false,
        'msg'  => 'Error interno',
        'error'=> $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
