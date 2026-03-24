<?php
/**
 * Controllers/VentasController.php
 * Devuelve JSON en todas las rutas y acepta body JSON.
 */
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);
require_once __DIR__ . '/../models/VentaModel.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../services/facturacion/FacturacionService.php';
require_once __DIR__ . '/../services/facturacion/FacturacionSchemaHelper.php';
require_once __DIR__ . '/../services/facturacion/FacturacionValidator.php';
require_once __DIR__ . '/../models/FacturacionModel.php';

header('Content-Type: application/json; charset=UTF-8');

class VentasController
{
    public static function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        $ventaModel = new VentaModel();

        $raw = json_decode(file_get_contents('php://input'), true);
        if (!is_array($raw)) { $raw = []; }

        $accion = $_REQUEST['accion'] ?? $raw['accion'] ?? '';

        try {

    switch ($accion) {

        /* ============================================================
         * Listar ventas (paginado + filtros)
         * POST/GET: pagina, limite, folio?, fecha?, estatus?
         * ============================================================ */
        case 'listar': {
            $pagina = self::asInt($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
            $limite = self::asInt($_POST['limite'] ?? $_GET['limite'] ?? 10);
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
         * Listar ventas detalle (paginado + filtros)
         * POST/GET: pagina, limite, folio?, fecha?, estatus?
         * ============================================================ */
        case 'listar-detalle': {
            $pagina = self::asInt($_POST['pagina'] ?? $_GET['pagina'] ?? 1);
            $limite = self::asInt($_POST['limite'] ?? $_GET['limite'] ?? 10);
            $folio  = trim($_POST['folio'] ?? $_GET['folio'] ?? '');
            $fecha  = trim($_POST['fecha'] ?? $_GET['fecha'] ?? '');
            $estatus= trim($_POST['estatus'] ?? $_GET['estatus'] ?? '');

            if ($pagina < 1) $pagina = 1;
            if ($limite < 1) $limite = 10;

            $items = $ventaModel->obtenerVentasDetalle($pagina, $limite, $folio, $fecha, $estatus);
            $total = $ventaModel->contarVentasDetalle($folio, $fecha, $estatus);

            echo json_encode(['ok' => true, 'data' => $items, 'total' => $total], JSON_UNESCAPED_UNICODE);
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
            $data     = $raw ?: [];
            $venta    = $data['venta']    ?? [];
            $detalles = $data['detalles'] ?? [];
            $pagos    = $data['pagos']    ?? []; // NUEVO: pagos para venta mixta

            error_log('[VENTA] Forma pago principal UI='.json_encode($venta['id_forma_pago'] ?? null).' pagos='.json_encode($pagos));

            // Sesión robusta
            $usr                = $_SESSION['usuario'] ?? [];
            $id_usuario_sesion  = $usr['id_usuario']  ?? ($usr['id'] ?? ($_SESSION['id_usuario'] ?? null));
            $id_sucursal_sesion = $usr['id_sucursal'] ?? ($_SESSION['id_sucursal'] ?? null);
            $id_caja_sesion     = $usr['id_caja']     ?? ($_SESSION['id_caja'] ?? ($_SESSION['caja_activa'] ?? null));

            if (empty($id_usuario_sesion)) self::jsonError('Falta id_usuario en sesión. Vuelve a iniciar sesión.');

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
                $venta['id_tipo_precio'] = self::mapTipoPrecio($venta['tipo_precio_slug']);
            }
            if (empty($venta['id_tipo_precio'])) {
                $venta['id_tipo_precio'] = 2;
            }

            // Validaciones mínimas
            if (!is_array($detalles) || !count($detalles)) self::jsonError('Debes enviar al menos un detalle.');

            // Suave: si llega explícito "Credito" pero sin cliente
            $estatusIn = strtolower(trim((string)($venta['estatus'] ?? '')));
            if (($estatusIn === 'credito' || $estatusIn === 'crédito') && empty($venta['id_cliente'])) {
                self::jsonError('Para ventas a crédito es obligatorio seleccionar un cliente.', 400);
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
            $idVenta  = self::asInt($_GET['id_venta'] ?? $_POST['id_venta'] ?? $raw['id_venta'] ?? 0);
            if ($idVenta <= 0) self::jsonError('id_venta requerido.');

            $venta    = $ventaModel->obtenerVentaPorId($idVenta);
            if (!$venta) self::jsonError('Venta no encontrada.', 404);

            $detalles = $ventaModel->obtenerDetalleVenta($idVenta);
            $pagos    = method_exists($ventaModel, 'obtenerPagosVenta') ? $ventaModel->obtenerPagosVenta($idVenta) : [];
            global $pdo;
            $cfdiModel = new FacturacionModel($pdo, new FacturacionSchemaHelper($pdo));
            $cfdiVenta = $cfdiModel->getCfdiByVenta($idVenta);

            $idFp    = (int)($venta['id_forma_pago'] ?? 0);
            $fpDesc  = (string)($venta['forma_pago'] ?? '');
            $esMixto = ($idFp === 3) || (stripos($fpDesc, 'mixt') === 0);

            $pagosVenta = $esMixto ? array_map(function($p){
                return [
                    'id_forma_pago'     => $p['id_forma_pago'] ?? null,
                    'nombre_forma_pago' => $p['nombre_forma_pago'] ?? ($p['descripcion'] ?? null),
                    'monto'             => $p['monto'] ?? null
                ];
            }, $pagos) : [];

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
                'utilidad_total'  => round($util_total, 2),
                'pagos'           => $pagos,
                'pagos_venta'     => $pagosVenta,
                'formas_tarjeta'  => method_exists($ventaModel, 'obtenerFormasPagoTarjetaCreditoDebito')
                    ? $ventaModel->obtenerFormasPagoTarjetaCreditoDebito()
                    : [],
                'cfdi'            => $cfdiVenta
            ], JSON_UNESCAPED_UNICODE);

            break;
        }

        /* ============================================================
         * Registrar ABONO de una venta a crédito
         * - Simple:      id_venta, monto, id_forma_pago, fecha_abono?, referencia_pago?
         * - Mixto:       id_venta, tipo_pago = 'mixto', fecha_abono?, pagos = [ {id_forma_pago,monto,referencia_pago?}, ... ]
         * ============================================================ */
        case 'abonar-venta': {
            $idVenta    = self::asInt($_POST['id_venta'] ?? $_GET['id_venta'] ?? $raw['id_venta'] ?? 0);
            $fechaAbono = trim($_POST['fecha_abono'] ?? $_GET['fecha_abono'] ?? $raw['fecha_abono'] ?? '');
            $ref        = trim($_POST['referencia_pago'] ?? $_GET['referencia_pago'] ?? $raw['referencia_pago'] ?? '');
            $tipoPago   = $_POST['tipo_pago'] ?? $_GET['tipo_pago'] ?? $raw['tipo_pago'] ?? null;

            // Usuario de sesión
            $usr       = $_SESSION['usuario'] ?? [];
            $idUsuario = $usr['id_usuario'] ?? ($usr['id'] ?? ($_SESSION['id_usuario'] ?? null));
            if (!$idUsuario) self::jsonError('No hay usuario en sesión (id_usuario).');

            if ($idVenta <= 0) self::jsonError('id_venta requerido.');

            // === MODO MIXTO: arreglo de pagos ===
            if (is_string($tipoPago) && strtolower($tipoPago) === 'mixto') {
                $pagosRaw = $_POST['pagos'] ?? $_GET['pagos'] ?? $raw['pagos'] ?? null;
                $pagosMixtos = null;
                if ($pagosRaw !== null && $pagosRaw !== '') {
                    $tmp = json_decode($pagosRaw, true);
                    if (is_array($tmp)) {
                        $pagosMixtos = $tmp;
                    }
                }

                if (empty($pagosMixtos) || !is_array($pagosMixtos)) {
                    self::jsonError('Se requiere capturar los pagos para el abono mixto.');
                }
                if (!method_exists($ventaModel, 'abonarVentaMixto')) {
                    self::jsonError('El modelo no soporta abonarVentaMixto(). Agrega el método al VentaModel.');
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
            $monto       = (float)($_POST['monto'] ?? $_GET['monto'] ?? $raw['monto'] ?? 0);
            $idFormaPago = self::asInt($_POST['id_forma_pago'] ?? $_GET['id_forma_pago'] ?? $raw['id_forma_pago'] ?? 0);

            if ($monto <= 0 || $idFormaPago <= 0) {
                self::jsonError('Datos inválidos para abono (monto, id_forma_pago).');
            }

            if (!method_exists($ventaModel, 'abonarVenta')) {
                self::jsonError('El modelo no soporta abonarVenta(). Agrega el método al VentaModel.');
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
            $id = self::asInt($_GET['id_venta'] ?? $_POST['id_venta'] ?? $raw['id_venta'] ?? 0);
            if ($id <= 0) self::jsonError('id_venta requerido.');
            if (!method_exists($ventaModel, 'saldoVenta')) {
                self::jsonError('El modelo no soporta saldoVenta(). Agrega el método al VentaModel.');
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
            $id = self::asInt($_GET['id_venta'] ?? $_POST['id_venta'] ?? $raw['id_venta'] ?? 0);
            if ($id <= 0) self::jsonError('id_venta requerido.');
            if (!method_exists($ventaModel, 'obtenerAbonosVenta')) {
                self::jsonError('El modelo no soporta obtenerAbonosVenta(). Agrega el método al VentaModel.');
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
            $id      = self::asInt($_POST['id_venta'] ?? $_GET['id_venta'] ?? $raw['id_venta'] ?? 0);
            $estatus = trim($_POST['estatus']  ?? $_GET['estatus'] ?? $raw['estatus'] ?? 'Cancelada');
            if ($id <= 0) self::jsonError('id_venta requerido');

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
            $id     = self::asInt($_POST['id_venta'] ?? $_GET['id_venta'] ?? $raw['id_venta'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? $_GET['motivo'] ?? $raw['motivo'] ?? 'Cancelación de venta');

            if ($id <= 0) self::jsonError('id_venta requerido.');

            // Sesión
            $usr         = $_SESSION['usuario'] ?? [];
            $id_usuario  = $usr['id_usuario'] ?? ($usr['id'] ?? ($_SESSION['id_usuario'] ?? null));
            $id_sucursal = $usr['id_sucursal'] ?? ($_SESSION['id_sucursal'] ?? 1);

            if (!$id_usuario) self::jsonError('No hay usuario en sesión (id_usuario).');
            if (!$id_sucursal) $id_sucursal = 1;

            $resp = $ventaModel->cancelarVenta($id, $id_sucursal, $id_usuario, $motivo);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Folio sugerido por fecha
         * GET/POST/JSON: fecha (Y-m-d)
         * ============================================================ */
        case 'facturacion-preview': {
            $idVenta = self::asInt($_POST['id_venta'] ?? $_GET['id_venta'] ?? $raw['id_venta'] ?? 0);
            if ($idVenta <= 0) self::jsonError('id_venta requerido.');

            global $pdo;
            $schema = new FacturacionSchemaHelper($pdo);
            $model = new FacturacionModel($pdo, $schema);
            $validator = new FacturacionValidator();

            $ctx = $model->loadContext($idVenta);
            $validationReport = $validator->validateDetailed($ctx);
            $validaciones = $validationReport['listaErrores'];
            $ctx['factura_draft']['validaciones'] = $validationReport;
            $ctx['factura_draft']['listoParaTimbrar'] = $validationReport['listoParaTimbrar'];
            $cfdiActual = $ctx['cfdi_actual'] ?? [];
            $advertencias = [];

            if (strtoupper((string)($cfdiActual['estatus'] ?? '')) === 'TIMBRADO') {
                $advertencias[] = 'La venta ya cuenta con un CFDI timbrado.';
            }

            echo json_encode([
                'ok' => true,
                'facturable' => empty($validaciones),
                'msg' => empty($validaciones) ? 'Información de facturación cargada.' : 'La venta tiene observaciones antes de facturar.',
                'contexto' => $ctx,
                'validaciones' => $validaciones,
                'validation_report' => $validationReport,
                'advertencias' => $advertencias,
            ], JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'facturacion-buscar-clientes': {
            $q = trim((string)($_POST['q'] ?? $_GET['q'] ?? $raw['q'] ?? ''));
            $limite = self::asInt($_POST['limite'] ?? $_GET['limite'] ?? $raw['limite'] ?? 20);

            global $pdo;
            $schema = new FacturacionSchemaHelper($pdo);
            $model = new FacturacionModel($pdo, $schema);

            echo json_encode([
                'ok' => true,
                'results' => $model->buscarClientesFacturacion($q, $limite),
            ], JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'facturacion-cliente-detalle': {
            $idCliente = self::asInt($_POST['id'] ?? $_GET['id'] ?? $raw['id'] ?? 0);
            if ($idCliente <= 0) self::jsonError('id requerido.');

            global $pdo;
            $schema = new FacturacionSchemaHelper($pdo);
            $model = new FacturacionModel($pdo, $schema);
            $cliente = $model->obtenerClienteFacturacion($idCliente);

            if (!$cliente) {
                self::jsonError('Cliente SAT no encontrado.', 404);
            }

            echo json_encode([
                'ok' => true,
                'data' => $cliente,
            ], JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'facturacion-guardar-receptor': {
            $idVenta = self::asInt($_POST['id_venta'] ?? $_GET['id_venta'] ?? $raw['id_venta'] ?? 0);
            if ($idVenta <= 0) self::jsonError('id_venta requerido.');

            global $pdo;
            $schema = new FacturacionSchemaHelper($pdo);
            $model = new FacturacionModel($pdo, $schema);
            $validator = new FacturacionValidator();

            $guardado = $model->guardarDatosFiscalesVenta($idVenta, $raw ?: $_POST);
            if (empty($guardado['guardado'])) {
                self::jsonError($guardado['msg'] ?? 'No fue posible guardar los datos fiscales.', 422);
            }
            $ctx = $model->loadContext($idVenta);
            $validationReport = $validator->validateDetailed($ctx);
            $validaciones = $validationReport['listaErrores'];
            $ctx['factura_draft']['validaciones'] = $validationReport;
            $ctx['factura_draft']['listoParaTimbrar'] = $validationReport['listoParaTimbrar'];

            echo json_encode([
                'ok' => true,
                'facturable' => empty($validaciones),
                'msg' => 'Datos fiscales actualizados.',
                'contexto' => $ctx,
                'validaciones' => $validaciones,
                'validation_report' => $validationReport,
                'advertencias' => [],
            ], JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'facturar': {
            $idVenta = self::asInt($_POST['id_venta'] ?? $_GET['id_venta'] ?? $raw['id_venta'] ?? 0);
            if ($idVenta <= 0) self::jsonError('id_venta requerido.');
            $idClienteSat = self::asInt($_POST['id_cliente_sat'] ?? $_GET['id_cliente_sat'] ?? $raw['id_cliente_sat'] ?? 0);
            if ($idClienteSat <= 0) self::jsonError('Debe seleccionar un receptor existente.', 422);

            global $pdo;
            $service = new FacturacionService($pdo);
            $facturacionInput = [
                'id_cliente_sat' => $idClienteSat,
                'moneda' => trim((string)($_POST['moneda'] ?? $_GET['moneda'] ?? $raw['moneda'] ?? '')),
                'metodo_pago' => trim((string)($_POST['metodo_pago'] ?? $_GET['metodo_pago'] ?? $raw['metodo_pago'] ?? '')),
                'forma_pago' => trim((string)($_POST['forma_pago'] ?? $_GET['forma_pago'] ?? $raw['forma_pago'] ?? '')),
                'tipo_cambio' => trim((string)($_POST['tipo_cambio'] ?? $_GET['tipo_cambio'] ?? $raw['tipo_cambio'] ?? '')),
                'condiciones_pago' => trim((string)($_POST['condiciones_pago'] ?? $_GET['condiciones_pago'] ?? $raw['condiciones_pago'] ?? '')),
                'tipo_comprobante' => trim((string)($_POST['tipo_comprobante'] ?? $_GET['tipo_comprobante'] ?? $raw['tipo_comprobante'] ?? '')),
                'exportacion' => trim((string)($_POST['exportacion'] ?? $_GET['exportacion'] ?? $raw['exportacion'] ?? '')),
            ];
            $resp = $service->facturarVenta($idVenta, $facturacionInput);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'descargar-cfdi-archivo': {
            $idVenta = self::asInt($_POST['id_venta'] ?? $_GET['id_venta'] ?? $raw['id_venta'] ?? 0);
            $tipo = strtolower(trim((string)($_POST['tipo'] ?? $_GET['tipo'] ?? $raw['tipo'] ?? 'xml')));
            if ($idVenta <= 0) self::jsonError('id_venta requerido.');

            global $pdo;
            $schema = new FacturacionSchemaHelper($pdo);
            $model = new FacturacionModel($pdo, $schema);
            $cfdi = $model->getCfdiByVenta($idVenta);
            if (!$cfdi) {
                self::jsonError('No existe CFDI para la venta.', 404);
            }

            if ($tipo === 'pdf') {
                $pdf = (string)($cfdi['pdf_base64'] ?? '');
                if ($pdf === '') self::jsonError('El CFDI no tiene PDF disponible.', 404);
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename=cfdi-' . ($cfdi['uuid'] ?? $idVenta) . '.pdf');
                echo base64_decode($pdf, true) ?: '';
                exit;
            }

            $xml = (string)($cfdi['xml_timbrado'] ?? '');
            if ($xml === '') self::jsonError('El CFDI no tiene XML disponible.', 404);
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: inline; filename=cfdi-' . ($cfdi['uuid'] ?? $idVenta) . '.xml');
            echo $xml;
            exit;
        }

        case 'folio-sugerido': {
            $fecha = $_GET['fecha'] ?? $_POST['fecha'] ?? $raw['fecha'] ?? date('Y-m-d');
            $resp  = $ventaModel->sugerirFolioPorFecha($fecha);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * ACTUALIZAR (editar TODO)
         * Body JSON: { venta:{ id_venta, ... }, detalles:[...] }
         * ============================================================ */
        case 'actualizar': {
            $data     = $raw ?: [];
            $venta    = $data['venta']    ?? [];
            $detalles = $data['detalles'] ?? [];
            $pagos    = $data['pagos']    ?? [];

            $idVenta = self::asInt($venta['id_venta'] ?? 0);
            if ($idVenta <= 0) self::jsonError('venta.id_venta es requerido.');

            // Cliente opcional → null si vacío
            if (array_key_exists('id_cliente', $venta) && $venta['id_cliente'] === '') {
                $venta['id_cliente'] = null;
            }

            // Tipo de precio: permitir slug
            if (empty($venta['id_tipo_precio']) && !empty($venta['tipo_precio_slug'])) {
                $venta['id_tipo_precio'] = self::mapTipoPrecio($venta['tipo_precio_slug']);
            }

            // Forma de pago puede venir vacío explícito => null
            if (array_key_exists('id_forma_pago', $venta) && $venta['id_forma_pago'] === '') {
                $venta['id_forma_pago'] = null;
            }

            if (!is_array($detalles)) $detalles = [];

            $resp = $ventaModel->actualizarVenta($venta, $detalles, $pagos);
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
            break;
        }

        /* ============================================================
         * Activar una venta Guardada (ponerla Activa/Credito)
         * POST/GET/JSON: id_venta, id_forma_pago?, actualizar_fecha?, id_cliente?
         * ============================================================ */
        case 'activar-guardada': {
            $id_venta      = (int)($_POST['id_venta']      ?? $_GET['id_venta']      ?? $raw['id_venta']      ?? 0);
            $id_forma_pago = (int)($_POST['id_forma_pago'] ?? $_GET['id_forma_pago'] ?? $raw['id_forma_pago'] ?? 0);
            $id_cliente    = (int)($_POST['id_cliente']    ?? $_GET['id_cliente']    ?? $raw['id_cliente']    ?? 0);

            $tipo_pago = $_POST['tipo_pago'] ?? $_GET['tipo_pago'] ?? $raw['tipo_pago'] ?? null;

            // pagos viene como JSON desde el JS en caso de mixto
            $pagosRaw    = $_POST['pagos'] ?? $_GET['pagos'] ?? $raw['pagos'] ?? null;
            $pagosMixtos = null;
            if ($pagosRaw !== null && $pagosRaw !== '') {
                $tmp = json_decode($pagosRaw, true);
                if (is_array($tmp)) {
                    $pagosMixtos = $tmp;
                }
            }

            $esMixto = is_string($tipo_pago) && strtolower($tipo_pago) === 'mixto';

            $actualizar_fecha = false;
            $af = ($_POST['actualizar_fecha'] ?? $_GET['actualizar_fecha'] ?? $raw['actualizar_fecha'] ?? 0);
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
            self::jsonError('Error interno', 500, $e->getMessage());
        }
    }

    /** Helper: mapea slug de tipo de precio a id_tipo_precio */
    private static function mapTipoPrecio(?string $slug): int
    {
        $slug = strtolower(trim((string)$slug));
        $map = ['publico' => 1, 'taller' => 2, 'proveedor' => 3];
        return $map[$slug] ?? 2; // default -> taller (como en la UI)
    }

    /** Helper: respuesta de error estándar */
    private static function jsonError(string $msg, int $code = 200, ?string $error = null): void
    {
        if ($code >= 400) {
            http_response_code($code);
        }

        $payload = ['ok' => false, 'msg' => $msg];
        if ($error !== null) {
            $payload['error'] = $error;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Helper: extrae enteros con seguridad */
    private static function asInt($v): int
    {
        return (int) (is_numeric($v) ? $v : 0);
    }
}

VentasController::handle();
