<?php
$titulo    = "Ventas";
$modulo    = "Punto de Venta";
$subtitulo = "Caja";

session_start();
// ================================
    // Duración lógica de la sesión
    // ================================
    $SESSION_LIFETIME = 10 * 60 * 60; // 10 horas en segundos

    // Iniciar sesión solo si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once __DIR__ . '/../../../includes/config.php';
    require_once __DIR__ . '/../../../includes/constants.php';
    $ID_GRUPO_ACUMULADOR = defined('ID_GRUPO_ACUMULADOR') ? ID_GRUPO_ACUMULADOR : null;

    // ================================
    // Validar que haya usuario logueado
    // ================================
    if (!isset($_SESSION['usuario'])) {
        header('Location: ' . BASE_URL . '/views/public/index.php');
        exit();
    }

    // ================================
    // Control de tiempo de sesión (10h)
    // ================================
    $sessionStart = $_SESSION['SESSION_START'] ?? 0;
    $sessionTTL   = $_SESSION['SESSION_TTL']   ?? $SESSION_LIFETIME;

    // Si no hay marca de inicio o ya se pasó el tiempo, forzamos re-login
    if ($sessionStart === 0 || (time() - $sessionStart) > $sessionTTL) {
        session_unset();
        session_destroy();
        // Mandamos al index público con flag de expirado
        header('Location: ' . BASE_URL . '/views/public/index.php?expired=1');
        exit();
    }

    // Si la sesión sigue vigente, actualizamos banderas
    $_SESSION['SESION_VIGENTE'] = true;
    $_SESSION['LAST_ACTIVITY']  = time();

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Punto de Venta | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- ================= CSS núcleo del layout ================= -->
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />

  <!-- Notificaciones (Toastr) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

  <!-- ================= Estilos locales POS =================== -->
  <style>
    /* Panel flotante de sugerencias del buscador */
    .sugerencias { position:absolute; z-index:1050; width:99%; max-height:320px; overflow:auto; }
    .sugerencias .list-group-item { cursor:pointer; }
    .sugerencias .active { background:#f1f1f1; }
    .sugerencias .list-group-item:hover,
    .sugerencias .list-group-item.active { color:#1f2937 !important; }
    .sugerencias .list-group-item:hover .small,
    .sugerencias .list-group-item.active .small {
      color:#374151 !important;
      font-weight:500;
    }
    .sugerencias .disabled, .sugerencias .disabled * { cursor:not-allowed!important; opacity:.9; }

    /* Resumen de total */
    .total-destacado strong { font-size: 1.8rem; font-weight: 800; }
    .total-destacado { font-weight: 700; }
    .table td, .table th { vertical-align: middle; }
    .badge-stock { font-weight: 600; }

    /* Utilidades */
    .w-70px { width: 70px; }
    .w-80px { width: 80px; }
    .w-100px{ width: 100px; }

    /* Layout responsive de la pantalla POS */
    .pos-layout { display: block; }
    @media (min-width: 992px) {
      .pos-layout { display: flex; align-items: flex-start; gap: 1rem; }
      .pos-left  { flex: 1 1 auto; min-width: 0; }
      .pos-right { flex: 0 0 700px; max-width: 700px; }
    }

    /* Tabla del carrito dentro de scroll, con header fijo */
    .carrito-scroll {
      max-height: 300px;
      overflow-y: auto;
      border: 1px solid rgba(0,0,0,.075);
      border-radius: .25rem;
    }
    .carrito-scroll table { margin-bottom: 0; }
    .carrito-scroll thead th {
      position: sticky;
      top: 0;
      z-index: 1;
      background: #f8f9fa;
    }

    .mixto-popup{
      max-width: 560px !important;
      width: calc(100vw - 32px) !important;
      border-radius: 14px;
    }

    .mixto-html{
      margin: 0 !important;
      overflow-x: hidden !important;
    }

    .mixto-wrap{ text-align: left; }
    .mixto-total{ margin: 0 0 12px; font-size: 1.05rem; }

    .mixto-grid{
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
    }

    @media (min-width: 768px){
      .mixto-grid{
        grid-template-columns: 1fr 1fr;
        gap: 14px;
      }
      .mixto-span-2{ grid-column: span 2; }
    }

    .mixto-actions{
      display: flex !important;
      justify-content: center !important;
      gap: 12px !important;
      flex-wrap: wrap;
    }

    .busqueda-avanzada-table td,
    .busqueda-avanzada-table th { vertical-align: middle; }
    .busqueda-avanzada-table .badge-stock { min-width: 68px; }

  </style>
</head>
<body>

  <!-- ================= Topbar / menú global ================== -->
  <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

  <div class="wrapper">
    <!-- Loader de toda la página (opcional) -->
    <div class="wrapper-loader fade" id="LoadingImage" style="display:none;">
      <div class="loader">
        <div class="loader__figure"></div>
        <p class="loader__label">Cargando...</p>
      </div>
    </div>

    <div class="container-fluid">
      <!-- Migas de pan / navegación contextual -->
      <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

      <!-- ======================= CONTENIDO POS ======================= -->
      <div class="pos-layout">

        <!-- ========== Columna izquierda: Buscador de productos ========== -->
        <div class="pos-left">
          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Buscar productos</h5>
              <button id="btnBusquedaAvanzada" type="button" class="btn btn-sm btn-outline-primary">
                <i class="mdi mdi-table-search me-1"></i> Búsqueda avanzada
              </button>
            </div>

            <div class="card-body">
              <!-- Input de búsqueda con panel de sugerencias -->
              <div class="row g-2 align-items-end mb-3 position-relative">
                <div class="col-12">
                  <label class="form-label" for="txtBuscar">Buscar producto</label>
                  <input
                    id="txtBuscar"
                    type="text"
                    class="form-control"
                    placeholder="Nombre o código… (↑/↓ navega, Enter agrega)"
                    autocomplete="off">
                  <!-- Panel (dropdown) de sugerencias -->
                  <div id="panelSug" class="list-group sugerencias d-none"></div>
                </div>
              </div>
              <small class="text-muted">
                Escribe para buscar; Enter agrega el seleccionado o intenta por código exacto (escáner).
              </small>
            </div>
          </div>
        </div>

        <!-- ========== Columna derecha: Orden/Carrito y Cobro ========== -->
        <div class="pos-right">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Orden actual</h5>
              <!-- Folio sugerido (informativo; el folio real se asigna al guardar/cobrar) -->
              <span
                class="badge bg-primary text-white"
                style="padding:6px 16px;border-radius:20px;min-width:82px;text-align:center;"
                title="Folio sugerido (aún no asignado)">
                #<span id="codigoOrden">—</span>
              </span>
            </div>

            <div class="card-body">
              <!-- Selector de cliente -->
              <div class="mb-2">
                <label class="form-label" for="selCliente">Cliente</label>
                <select id="selCliente" class="form-control">
                  <option value="">Cargando clientes…</option>
                </select>
              </div>

              <!-- Tipo de precio y fecha de la venta -->
              <div class="row g-2 mb-3">
                <div class="col-12 col-sm-6">
                  <label class="form-label" for="tpPrecio">Tipo de precio</label>
                  <select id="tpPrecio" class="form-control">
                    <option value="taller">Taller</option>
                    <option value="publico">Mostrador (Público)</option>
                    <option value="proveedor">Proveedor</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6">
                  <label class="form-label" for="fechaVenta">Fecha venta</label>
                  <input type="date" id="fechaVenta" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
              </div>

              <!-- Carrito: estado vacío -->
              <div id="wrapCarritoVacio" class="text-muted text-center py-4">
                No hay productos en la orden.
              </div>

              <!-- Carrito: tabla de productos -->
              <div id="wrapCarritoTabla" class="d-none">
                <div class="carrito-scroll">
                  <table class="table align-middle mb-0" id="tablaCarrito">
                    <thead class="table-light">
                      <tr>
                        <th class="text-center" style="width:250px;">Producto</th>
                        <th class="text-center" style="width:180px;">Cant.</th>
                        <th class="text-center" style="width:180px;">P. unitario</th>
                        <th class="text-center" style="width:180px;">Subtotal</th>
                        <th class="text-center" style="width:40px;"></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>

              <hr>

              <!-- Total (solo display) -->
              <div class="f-s-14">
                <div class="d-flex justify-content-between mt-2 total-destacado">
                  <strong style="font-size: 1.6rem;">Total</strong>
                  <strong id="resTotal" style="font-size: 1.6rem;">$0.00</strong>
                </div>
              </div>

              <!-- Forma de pago (se carga dinámicamente desde backend) -->
              <div class="mt-3">
                <label class="form-label" for="selFormaPago">Forma de pago</label>
                <select id="selFormaPago" class="form-control form-select">
                  <option value="">Cargando…</option>
                </select>
              </div>

              <!-- Botones de acción principales -->
              <div class="mt-3 d-grid gap-2">
                <button id="btnGuardar" type="button" class="btn btn-outline-primary">
                  <i class="mdi mdi-content-save-outline me-1"></i> Guardar
                </button>
                <button id="btnCobrar" type="button" class="btn btn-success">
                  <i class="mdi mdi-cash-register me-1"></i> Cobrar
                </button>
                <button id="btnCancelar" type="button" class="btn btn-outline-danger">
                  <i class="mdi mdi-close-octagon me-1"></i> Cancelar
                </button>
              </div>
            </div>
          </div>
        </div>
      </div><!-- /pos-layout -->
      <!-- ===================== /CONTENIDO POS ===================== -->

      <!-- ========== Modal de búsqueda avanzada (apoyo operativo) ========== -->
      <div class="modal fade" id="modalBusquedaAvanzada" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="mdi mdi-table-search me-1"></i> Búsqueda avanzada de productos
              </h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label" for="txtBuscarAvanzado">Buscar por código, descripción o proveedor</label>
                <input id="txtBuscarAvanzado" type="text" class="form-control" autocomplete="off" placeholder="Escribe para filtrar resultados...">
              </div>
              <div class="table-responsive">
                <table class="table table-hover table-sm busqueda-avanzada-table mb-0" id="tablaBusquedaAvanzada">
                  <thead class="table-light">
                    <tr>
                      <th style="min-width: 120px;">Código</th>
                      <th style="min-width: 260px;">Producto</th>
                      <th class="text-end">Existencia</th>
                      <th class="text-end">Precio Taller</th>
                      <th class="text-end">Precio Público</th>
                      <th style="min-width: 140px;">Proveedor</th>
                      <th class="text-center" style="width: 85px;">Acción</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr><td colspan="7" class="text-center text-muted py-4">Escribe para buscar productos.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Guarda el último id_venta para posible reimpresión del ticket -->
      <input type="hidden" id="tk-idventa" value="">
    </div><!-- /container-fluid -->
  </div><!-- /wrapper -->

  <!-- ================= Footer global ================== -->
  <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
  <div class="rightbar-overlay"></div>

  <!-- ================= JS núcleo del layout ================= -->
  <script>const BASE_URL = '<?= BASE_URL ?>';</script>
  <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>

  <!-- Librerías de UI -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- =================== LÓGICA POS (JS) ===================== -->
  <script>
  (() => { 'use strict';

    // ============================================================
    // 1) CONSTANTES & HELPERS DE FORMATO
    // ============================================================
    const BASE = BASE_URL;
    const ID_GRUPO_ACUMULADOR = <?= $ID_GRUPO_ACUMULADOR !== null ? (int)$ID_GRUPO_ACUMULADOR : 'null' ?>;
    const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
    const fix2 = v => (Number(v||0)).toFixed(2);
    const num  = v => parseFloat(v ?? 0) || 0;
    const normalize = s => (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();

    function actualizarIDsFormasPago(arr){
      formasPagoActivas = Array.isArray(arr) ? arr : [];
      const norm = normalize;

      const esEfectivo = fp => fp && (fp.clave_sat === '01' || norm(fp.descripcion).includes('efectivo'));
      const esTransfer = fp => fp && (fp.clave_sat === '03' || norm(fp.descripcion).includes('transfer'));
      const esTarjeta = fp => fp && norm(fp.descripcion).includes('tarjeta');
      const esCredito = fp => esTarjeta(fp) && (norm(fp.descripcion).includes('credito') || norm(fp.descripcion).includes('crédito'));
      const esDebito  = fp => esTarjeta(fp) && norm(fp.descripcion).includes('debito');

      ID_FP.efectivo       = (arr.find(esEfectivo)     || {}).id_forma_pago ?? null;
      ID_FP.transferencia  = (arr.find(esTransfer)     || {}).id_forma_pago ?? null;
      ID_FP.tarjetaCredito = (arr.find(esCredito)      || {}).id_forma_pago ?? null;
      ID_FP.tarjetaDebito  = (arr.find(esDebito)       || {}).id_forma_pago ?? null;

      const tarjetaHallada = arr.find(esTarjeta);
      ID_FP.tarjeta        = (tarjetaHallada?.id_forma_pago) ?? ID_FP.tarjetaCredito ?? ID_FP.tarjetaDebito;

    }

    function opcionesTarjeta(){
      const norm = normalize;
      return formasPagoActivas
        .filter(fp => {
          const d = norm(fp.descripcion);
          const activo = Number(fp.activo ?? 1) === 1;
          const esTarjeta = d.includes('tarjeta');
          const esMixto = d.includes('mixto');
          return activo && esTarjeta && !esMixto;
        });
    }

    function asegurarIdFP(id, etiqueta){
      const val = Number(id || 0);
      if(!val){
        Swal.fire({
          icon:'error',
          title:'Forma de pago no disponible',
          text:`No se encontró la forma de pago de ${etiqueta}. Verifica el catálogo de formas de pago activas.`
        });
        return null;
      }
      return val;
    }

    const ID_FP = {
      efectivo: null,
      tarjeta: null,
      tarjetaCredito: null,
      tarjetaDebito: null,
      transferencia: null
    };

    let formasPagoActivas = [];

    // ============================================================
    // 2) ESTADO EN MEMORIA
    // ============================================================
    let carrito       = [];
    let idxFocus      = -1;
    let ultResultados = [];
    let debTimer      = null;
    let debTimerAvz   = null;
    let ultimaBusqueda = '';
    let filtroBaseAvanzado = '';
    const detalleCache = new Map();
    let totalActual   = 0;

    // ============================================================
    // 3) MAPEOS / LÓGICA DE NEGOCIO
    // ============================================================
    function formaPagoSlug(){
      const txt = $('#selFormaPago option:selected').text()?.trim() || '';
      const t   = normalize(txt);

      // Primero distinguir los mixtos específicos
      if (t.includes('mixto') && t.includes('tarjeta')) {
        return 'mixto_efectivo_tarjeta';       // Mixto (Efectivo + Tarjeta)
      }
      if (t.includes('mixto') && t.includes('transfer')) {
        return 'mixto_efectivo_transferencia'; // Mixto (Efectivo + Transferencia)
      }

      // Resto de formas de pago "simples"
      if (t.includes('efectivo')) return 'efectivo';
      if ((t.includes('credito') || t.includes('crédito')) && !t.includes('tarjeta')) return 'credito';
      if (t.includes('transfer')) return 'transferencia';
      if (t.includes('tarjeta') || t.includes('debito') || t.includes('débito')) return 'tarjeta';

      // Default: tarjeta (por si la descripción no matchea)
      return 'tarjeta';
    }

    function tipoPrecioActual() {
      return ($('#tpPrecio').val() || 'publico');
    }

    // Respeta override_unit si existe
    function precioDeItem(it){
      if (typeof it.override_unit === 'number' && !isNaN(it.override_unit)) {
        return Number(it.override_unit);
      }
      const t = tipoPrecioActual();
      if (t === 'taller')     return Number(it.precio_taller||0);
      if (t === 'proveedor')  return Number(it.precio_proveedor||0);
      return Number(it.precio_publico||0);
    }

    // Helper para pintar/usar el precio unitario
    function obtenerPrecioItem(it){
      let p = (it.precio_unitario != null)
            ? it.precio_unitario
            : (it.precio != null ? it.precio : precioDeItem(it));
      return Number(p) || 0;
    }

    const vendibleDe = det => Math.max(0, num(det.stock_actual ?? det.existencia));

    function maxVendible(it){
      const stock = num(it.stock_total ?? it.stock_actual ?? it.existencia ?? 0);
      return Math.max(0, stock);
    }

    // ==== NUEVOS HELPERS: control de stock considerando varias líneas ====
    function totalEnCarrito(idProd) {
      return carrito.reduce((sum, it) => {
        return sum + (it.id_producto == idProd ? num(it.cantidad) : 0);
      }, 0);
    }

    function stockProductoBase(p) {
      return num(p.stock_actual ?? p.existencia ?? 0);
    }

    function mapTipoPrecioId(slug){
      const m = { publico:1, taller:2, proveedor:3 };
      return m[slug] || 1;
    }

    const esAcumulador = (it) => {
      if (!ID_GRUPO_ACUMULADOR) return false;
      return Number(it?.id_grupo ?? 0) === Number(ID_GRUPO_ACUMULADOR);
    };

    // ============================================================
    // 4) SERVICIOS (AJAX) PARA CARGAR DATOS
    // ============================================================
    function pintarFolioSugerido(){
      const fecha = $('#fechaVenta').val();
      $.get(`${BASE}/controllers/VentasController.php`, { accion:'folio-sugerido', fecha })
        .done(r=>{
          if(r?.ok && r.folio){
            $('#codigoOrden').text(r.folio);
            $('#codigoOrden').closest('.badge')
              .removeClass('bg-success').addClass('bg-primary')
              .attr('title','Folio sugerido (aún no asignado)');
          }
        });
    }

    function setClientesOptions(arr){
      const sel = $('#selCliente').empty();
      sel.append(`<option value="">--Seleccione Opción--</option>`);
      (arr||[]).forEach(c=>{
        const id = c.id_cliente ?? c.id;
        const nombre = c.nombre ?? c.razon_social ?? c.nombre_comercial ?? 'Cliente';
        if(id!=null && id!=='') sel.append(`<option value="${id}">${nombre}</option>`);
      });
    }

    function cargarClientes(){
      const LIM = 200;
      $.post(`${BASE}/controllers/ClientesController.php`, {accion:'listar-min', limite: LIM})
        .done(r=>{
          const data = r?.data || (Array.isArray(r) ? r : []);
          if (Array.isArray(data) && data.length) {
            setClientesOptions(data);
          } else {
            $.post(`${BASE}/controllers/ClientesController.php`, {accion:'listar', pagina:1, limite:LIM})
              .done(r2=>{
                const data2 = r2?.data || [];
                setClientesOptions(data2);
                if (!data2.length) toastr.info('No hay clientes activos. Usando “Mostrador / Público general”.');
              })
              .fail(()=>{
                setClientesOptions([]); toastr.error('No se pudieron cargar clientes (listar).');
              });
          }
        })
        .fail(()=>{
          $.post(`${BASE}/controllers/ClientesController.php`, {accion:'listar', pagina:1, limite:LIM})
            .done(r2=>{
              const data2 = r2?.data || [];
              setClientesOptions(data2);
              if (!data2.length) toastr.info('No hay clientes activos. Usando “Mostrador / Público general”.');
            })
            .fail(()=>{ setClientesOptions([]); toastr.error('No se pudieron cargar clientes.'); });
        });
    }

    function cargarFormasPago(){
      $.get(`${BASE}/controllers/FormasPagoController.php`, {accion:'listar_select'})
        .done(r=>{
          const sel = $('#selFormaPago').empty();
          const arr = r?.data || (Array.isArray(r) ? r : []);
          if (!arr.length) {
            sel.append(`<option value="">(sin formas de pago)</option>`);
            return;
          }

          actualizarIDsFormasPago(arr);

          arr.forEach(fp=>{
            sel.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`);
          });

          const efectivo = ID_FP.efectivo || (arr[0]?.id_forma_pago ?? '');
          const idDefault = (arr.find(fp => Number(fp.id_forma_pago) === Number(efectivo)) || arr[0]).id_forma_pago;
          sel.val(idDefault);
        })
        .fail(()=>{
          const sel = $('#selFormaPago').empty();
          sel.append(`<option value="">(sin formas de pago)</option>`);
          toastr.error('No se pudieron cargar las formas de pago.');
        });
    }

    // ============================================================
    // 5) BUSCADOR DE PRODUCTOS
    // ============================================================
    const $input = $('#txtBuscar'), $panel = $('#panelSug');
    const $modalBusquedaAvz = $('#modalBusquedaAvanzada');
    const $inputBusquedaAvz = $('#txtBuscarAvanzado');
    const $tablaBusquedaAvzBody = $('#tablaBusquedaAvanzada tbody');

    function debounce(fn, ms){ clearTimeout(debTimer); debTimer = setTimeout(fn, ms); }
    function debounceAvanzada(fn, ms){ clearTimeout(debTimerAvz); debTimerAvz = setTimeout(fn, ms); }
    const esc = s => $('<div>').text((s ?? '').toString()).html();

    function precioAplicable(p){
      const t = tipoPrecioActual();
      if (t === 'taller') return Number(p.precio_taller ?? 0);
      if (t === 'proveedor') return Number(p.precio_proveedor ?? 0);
      return Number(p.precio_publico ?? 0);
    }

    function sugHTMLBasico(p){
      const stk = Number(p.stock_actual ?? p.existencia ?? 0);
      const prov = p.proveedor ?? '';
      const precioTaller = Number(p.precio_taller ?? 0);
      const precioPublico = Number(p.precio_publico ?? 0);
      const sinStock = stk <= 0;
      return `
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${sinStock ? 'disabled' : ''}"
          data-i="${p.__i}" data-id="${p.id_producto}" aria-disabled="${sinStock ? 'true' : 'false'}">
          <div class="me-2" style="min-width:0">
            <div class="text-truncate"><strong>${esc(p.codigo)}</strong> — ${esc(p.descripcion)}</div>
            <div class="small text-muted">
              Taller: ${mxn(precioTaller)} · Público: ${mxn(precioPublico)} · Exist: ${fix2(stk)}${prov ? ` · Prov: ${esc(prov)}` : ''}
            </div>
          </div>
          <span class="btn btn-sm btn-outline-primary"><i class="mdi mdi-plus-circle-outline"></i></span>
        </a>`;
    }

    function renderSugerencias(list){
      ultResultados = (list||[]).map((p,i)=>({...p,__i:i}));
      idxFocus = -1;
      $panel.empty();

      if(!ultResultados.length){
        $panel.addClass('d-none');
        return;
      }

      ultResultados.forEach(p=>$panel.append(sugHTMLBasico(p)));
      $panel.removeClass('d-none');
    }

    function enfocarBusqueda(){
      setTimeout(()=>{
        $input.trigger('focus');
      }, 40);
    }

    function buscar(q, {mantenerPanel=true, limite=20} = {}){
      if(!q || q.length < 2){
        if (!mantenerPanel) $panel.addClass('d-none').empty();
        return;
      }
      ultimaBusqueda = q;
      $.post(`${BASE}/controllers/ProductosController.php`, {accion:'buscar-min', q, limite})
        .done(r=>renderSugerencias(r?.data||[]))
        .fail(()=>{
          if (!mantenerPanel) $panel.addClass('d-none').empty();
        });
    }

    function renderTablaAvanzada(list){
      $tablaBusquedaAvzBody.empty();
      if (!list.length){
        $tablaBusquedaAvzBody.html('<tr><td colspan="7" class="text-center text-muted py-4">No se encontraron productos.</td></tr>');
        return;
      }

      list.forEach(p=>{
        const stk = num(p.stock_actual ?? p.existencia ?? 0);
        const badge = stk > 0 ? 'bg-success' : 'bg-secondary';
        $tablaBusquedaAvzBody.append(`
          <tr>
            <td><strong>${esc(p.codigo)}</strong></td>
            <td>${esc(p.descripcion)}</td>
            <td class="text-end"><span class="badge ${badge} badge-stock">${fix2(stk)}</span></td>
            <td class="text-end">${mxn(p.precio_taller ?? 0)}</td>
            <td class="text-end">${mxn(p.precio_publico ?? 0)}</td>
            <td>${esc(p.proveedor ?? '')}</td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-outline-primary" data-avz-add="${Number(p.id_producto)}" ${stk <= 0 ? 'disabled' : ''}>
                <i class="mdi mdi-plus"></i>
              </button>
            </td>
          </tr>
        `);
      });
    }

    function buscarAvanzado(qVisible){
      const qBase = (filtroBaseAvanzado || '').trim();
      const qRefinado = (qVisible || '').trim();
      const qCompuesto = [qBase, qRefinado].filter(Boolean).join(' ').trim();

      if (!qCompuesto || qCompuesto.length < 2){
        $tablaBusquedaAvzBody.html('<tr><td colspan="7" class="text-center text-muted py-4">Escribe al menos 2 caracteres para buscar.</td></tr>');
        return;
      }

      $.post(`${BASE}/controllers/ProductosController.php`, {accion:'buscar-min', q: qCompuesto, limite:120})
        .done(r=>renderTablaAvanzada(r?.data || []))
        .fail(()=> $tablaBusquedaAvzBody.html('<tr><td colspan="7" class="text-center text-danger py-4">No se pudo cargar la búsqueda avanzada.</td></tr>'));
    }

    function moverFocoSugerencias(delta){
      if (!$panel.children().length) return;
      idxFocus = Math.max(0, Math.min((idxFocus < 0 ? 0 : idxFocus + delta), ultResultados.length - 1));
      $panel.children().removeClass('active');
      const $target = $panel.children().eq(idxFocus).addClass('active');
      if ($target.length) {
        const panelTop = $panel.scrollTop();
        const panelHeight = $panel.innerHeight();
        const elTop = $target.position().top + panelTop;
        const elBottom = elTop + $target.outerHeight();
        if (elBottom > panelTop + panelHeight) $panel.scrollTop(elBottom - panelHeight);
        if (elTop < panelTop) $panel.scrollTop(elTop);
      }
    }

    function seleccionarPorId(idProd, opts = {}){
      const {preservarBusqueda=true, cerrarAvanzada=false} = opts;
      $.post(`${BASE}/controllers/ProductosController.php`, {accion:'detalle', id_producto:idProd})
        .done(r=>{
          const det = r?.data;
          if(!det){ toastr.error('No se encontró el detalle del producto'); return; }

          const vendible = vendibleDe(det);
          if (vendible <= 0) {
            const stk = num(det.stock_actual ?? det.existencia);
            toastr.warning(`Sin existencias para vender. (Existencia: ${fix2(stk)})`);
            return;
          }

          detalleCache.set(idProd, det);
          agregarDesdeDetalle(det);
          if (cerrarAvanzada) $modalBusquedaAvz.modal('hide');
          if (preservarBusqueda) {
            const termino = $input.val().trim();
            if (termino.length >= 2) {
              buscar(termino, {mantenerPanel:true});
            }
          } else {
            $input.val('');
            $panel.addClass('d-none').empty();
          }
          enfocarBusqueda();
        })
        .fail(()=> toastr.error('No se pudo obtener el detalle del producto'));
    }

    $('#txtBuscar').on('input', function(){
      debounce(()=>buscar(this.value.trim(), {mantenerPanel:true}), 180);
    });

    $('#txtBuscar').on('keydown', function(e){
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        moverFocoSugerencias(1);
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        moverFocoSugerencias(-1);
        return;
      }
      if (e.key === 'Enter') {
        if ($panel.hasClass('d-none')) return;
        e.preventDefault();
        let selected = idxFocus >= 0 ? ultResultados[idxFocus] : ultResultados[0];
        if (!selected && ultimaBusqueda.length >= 2) {
          buscar(ultimaBusqueda, {mantenerPanel:true});
          return;
        }
        if (selected) seleccionarPorId(Number(selected.id_producto), {preservarBusqueda:true});
      }
    });

    $('#panelSug').on('click','.list-group-item',function(e){
      e.preventDefault();
      if($(this).hasClass('disabled')) return;
      seleccionarPorId(Number($(this).data('id')), {preservarBusqueda:true});
    });

    $('#panelSug').on('mouseenter','.list-group-item',function(){
      idxFocus = Number($(this).data('i'));
      $panel.children().removeClass('active');
      $(this).addClass('active');
    });

    $('#btnBusquedaAvanzada').on('click', ()=>{
      $modalBusquedaAvz.modal('show');
    });

    $modalBusquedaAvz.on('shown.bs.modal', ()=>{
      filtroBaseAvanzado = $input.val().trim();
      $inputBusquedaAvz.val('');
      buscarAvanzado('');
      setTimeout(()=> $inputBusquedaAvz.trigger('focus'), 40);
    });

    $inputBusquedaAvz.on('input', function(){
      debounceAvanzada(()=>buscarAvanzado(this.value.trim()), 220);
    });

    $tablaBusquedaAvzBody.on('click', 'button[data-avz-add]', function(){
      const id = Number($(this).data('avz-add'));
      if (!id) return;
      seleccionarPorId(id, {preservarBusqueda:true, cerrarAvanzada:true});
    });

    $(document).on('click', e=>{
      if(!$(e.target).closest('#txtBuscar,#panelSug,#btnBusquedaAvanzada').length){
        $panel.addClass('d-none');
      }
    });

    // ============================================================
    // 6) CARRITO (agregar, pintar, editar, eliminar)
    // ============================================================
    // Siempre agrega NUEVA línea, respetando existencia total en todas las líneas
    function agregarDesdeDetalle(p){
      const stockTotal = stockProductoBase(p);
      const yaEnCarrito = totalEnCarrito(p.id_producto);
      const restante = stockTotal - yaEnCarrito;

      if (restante <= 0) {
        toastr.warning('Sin stock disponible para vender.');
        return;
      }

      const itemBase = {
        id_producto: p.id_producto,
        codigo: p.codigo,
        descripcion: p.descripcion,
        id_grupo: p.id_grupo ?? null,
        stock_total: stockTotal,       // stock real del sistema
        stock_actual: stockTotal,      // para compatibilidad si se usa en algún lado
        stock_minimo: Number(p.stock_minimo ?? 0),
        precio_publico: Number(p.precio_publico ?? 0),
        precio_taller: Number(p.precio_taller ?? 0),
        precio_proveedor: Number(p.precio_proveedor ?? 0),
        proveedor: p.proveedor ?? null,
        cantidad: 1,
        numero_poliza: ''
      };

      carrito.push(itemBase);
      pintarCarrito();
    }

    function pintarCarrito()
    {
      const $tb = $('#tablaCarrito tbody').empty();

      if(!carrito.length){
        $('#wrapCarritoVacio').removeClass('d-none');
        $('#wrapCarritoTabla').addClass('d-none');
        $('#resTotal').text('$0.00');
        totalActual = 0;
        return;
      }

      $('#wrapCarritoVacio').addClass('d-none');
      $('#wrapCarritoTabla').removeClass('d-none');

      let total = 0;

      carrito.forEach((it, idx) => {
        const precio   = obtenerPrecioItem(it);
        const cantidad = Number(it.cantidad) || 0;
        const subtotal = cantidad * precio;
        total += subtotal;

        const stockTotal = num(it.stock_total ?? it.stock_actual ?? 0);
        const usado = carrito.reduce((s, x) => x.id_producto == it.id_producto ? s + num(x.cantidad) : s, 0);
        const disponible = Math.max(0, stockTotal - usado);

        const badgeClass = disponible > 0 ? 'bg-success' : 'bg-secondary';
        const requierePoliza = esAcumulador(it);
        const polizaHtml = requierePoliza ? `
          <div class="mt-1">
            <label class="form-label mb-0"><small>Número de póliza *</small></label>
            <input type="text" class="form-control form-control-sm" data-poliza="${idx}" maxlength="80"
                   pattern="[A-Za-z0-9-]+" value="${it.numero_poliza ? it.numero_poliza : ''}" placeholder="Captura póliza">
          </div>` : '';
        const qtyAttrs = requierePoliza ? 'min="1" max="1" step="1" readonly' : 'min="0" step="1"';

        $tb.append(`
          <tr>
            <!-- Producto -->
            <td>
              <div class="d-flex align-items-center">
                <div>
                  <div class="fw-semibold">${it.descripcion}</div>
                  <div class="small text-muted">
                    Cod: ${it.codigo} ${it.proveedor ? `· Prov: ${it.proveedor}` : ``}
                    · Exist total: <span class="badge ${badgeClass} badge-stock">${fix2(stockTotal)}</span>
                    ${requierePoliza ? '· Requiere póliza' : ''}
                  </div>
                  ${polizaHtml}
                </div>
              </div>
            </td>

            <!-- Cantidad -->
            <td class="text-center">
              <div class="btn-group btn-group-sm" role="group">
                <input type="number"
                       ${qtyAttrs}
                       class="form-control form-control-sm text-center w-70px"
                       value="${fix2(cantidad)}"
                       data-qty="${idx}">
              </div>
            </td>

            <!-- Precio unitario -->
            <td class="text-center">
              <input type="number"
                     min="0"
                     step="1"
                     class="form-control form-control-sm text-end w-80px"
                     value="${fix2(precio)}"
                     data-precio="${idx}"
                     title="Precio unitario">
            </td>

            <!-- Subtotal -->
            <td class="text-end">
              <input type="number"
                     min="0"
                     step="1"
                     class="form-control form-control-sm text-end w-100px"
                     value="${fix2(subtotal)}"
                     data-sub="${idx}"
                     title="Editar subtotal (ajusta el precio unitario automáticamente)">
            </td>

            <!-- Eliminar -->
            <td class="text-end">
              <button class="btn btn-sm btn-outline-danger" data-del="${idx}">
                <i class="mdi mdi-delete"></i>
              </button>
            </td>
          </tr>
        `);
      });

      totalActual = total;
      $('#resTotal').text(mxn(total));
    }

    $('#tpPrecio').on('change', pintarCarrito);

    /* ========== CANTIDAD ========== */

    // (los botones data-inc / data-dec no se usan actualmente, pero se dejan por si los agregas después)
   /* $('#tablaCarrito').on('click','button[data-inc]',function(){
      const i = Number(this.dataset.inc);
      if(isNaN(i) || !carrito[i]) return;

      const it = carrito[i];
      const stockTotal = num(it.stock_total ?? it.stock_actual ?? it.existencia ?? 0);
      const usadoOtros = carrito.reduce((s,x,idx)=>(
        idx!==i && x.id_producto==it.id_producto ? s + num(x.cantidad) : s
      ),0);
      const maxLinea = Math.max(0, stockTotal - usadoOtros);

      const actual = num(it.cantidad);
      let next = actual + 1;
      if (next > maxLinea) {
        next = maxLinea;
        toastr.info('Se alcanzó la cantidad máxima disponible considerando el resto del carrito.');
      }

      it.cantidad = Number(next.toFixed(2));
      pintarCarrito();
    });

    $('#tablaCarrito').on('click','button[data-dec]',function(){
      const i = Number(this.dataset.dec);
      if(isNaN(i) || !carrito[i]) return;

      const it = carrito[i];
      const actual = num(it.cantidad);
      let next = actual - 1;
      if (next < 0.01) next = 0.01;

      it.cantidad = Number(next.toFixed(2));
      pintarCarrito();
    });*/
    
    /*-----------------------------------------------------------------------------*/
    $('#tablaCarrito').on('change','input[data-qty]',function(){
      const i = Number(this.dataset.qty);
      if(isNaN(i) || !carrito[i]) return;

      if (esAcumulador(carrito[i])) {
        carrito[i].cantidad = 1;
        this.value = '1.00';
        return;
      }

      let val = Number(this.value || 0);
      if (isNaN(val) || val <= 0) val = 0.01;

      const it = carrito[i];
      const stockTotal = num(it.stock_total ?? it.stock_actual ?? it.existencia ?? 0);

      // Suma de las demás líneas del mismo producto (sin contar esta)
      const usadoOtros = carrito.reduce((s,x,idx)=>(
        idx!==i && x.id_producto==it.id_producto ? s + num(x.cantidad) : s
      ),0);

      const maxLinea = Math.max(0, stockTotal - usadoOtros);

      if (val > maxLinea) {
        val = maxLinea;
        toastr.info('Se ajustó a la cantidad disponible considerando el resto del carrito.');
      }

      if (val <= 0) val = 0.01;

      carrito[i].cantidad = Number(val.toFixed(2));
      pintarCarrito();
    });

    $('#tablaCarrito').on('input change','input[data-poliza]', function(){
      const i = Number(this.dataset.poliza);
      if (isNaN(i) || !carrito[i]) return;
      carrito[i].numero_poliza = (this.value || '').trim();
    });

    /* ========== ELIMINAR ITEM ========== */

    $('#tablaCarrito').on('click','button[data-del]',function(){
      const i = Number(this.dataset.del);
      if(isNaN(i)) return;
      carrito.splice(i,1);
      pintarCarrito();
    });

    /* ========== CAMBIO DE PRECIO UNITARIO ========== */

    $('#tablaCarrito').on('change','input[data-precio]', function(){
      const i = Number(this.dataset.precio);
      if (isNaN(i) || !carrito[i]) return;

      let unit = Number(this.value);
      if (isNaN(unit) || unit < 0) unit = 0;

      unit = Number(unit.toFixed(2));

      carrito[i].override_unit   = unit;
      carrito[i].precio_unitario = unit;
      carrito[i].precio          = unit;

      pintarCarrito();
    });

    /* ========== SUBTOTAL EDITABLE (CAMBIO DE PRECIO) ========== */

    $('#tablaCarrito').on('change','input[data-sub]', function(){
      const i = Number(this.dataset.sub);
      if (isNaN(i) || !carrito[i]) return;

      let sub = Number(this.value);
      if (isNaN(sub) || sub < 0) sub = 0;

      sub = Number(sub.toFixed(2));

      let qty = Math.max(0.01, Number(carrito[i].cantidad) || 0.01);
      const unit = sub / qty;

      carrito[i].override_unit   = Number(unit.toFixed(2));
      carrito[i].precio_unitario = carrito[i].override_unit;
      carrito[i].precio          = carrito[i].override_unit;

      pintarCarrito();
    });

    // ============================================================
    // 7) IMPRESIÓN DE TICKET
    // ============================================================
    function imprimirTicketAjax(idVenta){
      if (!idVenta) return false;
      const url = `${BASE}/utils/ticket_pdf.php?id_venta=${encodeURIComponent(idVenta)}`;
      const win = window.open(url, '_blank');
      if (win) win.focus();
      return true;
    }

    // ============================================================
    // 8) HELPER AJAX PARA CREAR VENTA
    // ============================================================
    function postVenta(payload, onOk){
      $.ajax({
        url: `${BASE}/controllers/VentasController.php?accion=crear`,
        method: 'POST',
        contentType: 'application/json; charset=utf-8',
        data: JSON.stringify(payload)
      })
      .done(r=> onOk(r))
      .fail(()=> Swal.fire({
        icon:'error', title:'Error de comunicación',
        text:'No fue posible contactar al servidor.'
      }));
    }

    function validarPolizasCarrito(){
      if (!ID_GRUPO_ACUMULADOR) return true;
      const faltante = carrito.find(it => esAcumulador(it) && !(it.numero_poliza || '').trim());
      if (faltante) {
        Swal.fire({
          icon: 'warning',
          title: 'Número de póliza requerido',
          text: `Captura el número de póliza para ${faltante.descripcion || 'la batería'}.`
        });
        return false;
      }
      return true;
    }

    // ============================================================
    // 9) REGISTRO DE VENTA
    // ============================================================
    function registrarVenta({estatus='Activa', pagosInfo={}, pagosArr=[]} = {}){
      const slugPrecio = $('#tpPrecio').val();
      const clienteVal = $('#selCliente').val();
      const idCliente  = clienteVal ? Number(clienteVal) : null;

      if (!validarPolizasCarrito()) return;

      const payload = {
        venta: {
          fecha: $('#fechaVenta').val(),
          estatus,
          id_cliente: idCliente,
          // forma de pago principal (la del combo)
          id_forma_pago: estatus==='Guardada' ? null : (Number($('#selFormaPago').val()) || null),
          id_tipo_precio: mapTipoPrecioId(slugPrecio),
          tipo_precio_slug: slugPrecio,
          // info solo para mostrar en la alerta (cambio, recibido, etc.)
          ...pagosInfo
        },
        detalles: carrito.map(it => {
          const unit = precioDeItem(it);
          const cant = Number(it.cantidad);
          return {
            id_producto: it.id_producto,
            cantidad: cant,
            precio_unitario: unit,
            subtotal: cant * unit,
            numero_poliza: it.numero_poliza ? it.numero_poliza.trim() : null
          };
        }),
        // 🔥 Aquí viajan los pagos para tabla pagos_venta
        pagos: pagosArr
      };

      postVenta(payload, (r)=>{
        if(!r?.ok){
          return Swal.fire({
            icon:'error',
            title:'No se pudo registrar',
            text:(r?.msg||'Intenta de nuevo')
          });
        }

        $('#tk-idventa').val(r.id_venta || '');

        if (estatus === 'Guardada'){
          Swal.fire({
            icon:'success',
            title:'Venta guardada',
            html:`<p>Folio: <b>${r.folio}</b></p>`
          });
        }
        else if (estatus === 'Credito'){
          Swal.fire({
            icon:'success',
            title:'Venta a crédito registrada',
            html:`<p><small>Folio:</small> <b>${r.folio}</b></p>
                  <p class="mb-0">Realiza abonos desde el módulo de <b>Pagos Parciales</b>.</p>`,
            confirmButtonText: 'Imprimir ticket',
            showCancelButton: true,
            cancelButtonText: 'Cerrar'
          }).then(res=>{
            if(res.isConfirmed && r.id_venta){
              imprimirTicketAjax(r.id_venta);
            }
          });
        }
        else {
          const cambioTxt = (typeof pagosInfo.cambio === 'number')
            ? `<p><small>Cambio:</small> <b>${mxn(pagosInfo.cambio)}</b></p>` : '';
          Swal.fire({
            icon: 'success',
            title: 'Venta registrada',
            html: `<p><small>Folio:</small> <b>${r.folio}</b></p>${cambioTxt}`,
            confirmButtonText: 'Imprimir ticket',
            showCancelButton: true,
            cancelButtonText: 'Cerrar'
          }).then(res=>{
            if(res.isConfirmed && r.id_venta){
              imprimirTicketAjax(r.id_venta);
            }
          });
        }

        // Reset de pantalla
        carrito=[]; pintarCarrito(); $('#selCliente').val('');
        $('#tpPrecio').val('taller');
        cargarFormasPago();
        pintarFolioSugerido();
      });
    }

    // ============================================================
    // 10) FLUJO DE COBRO
    // ============================================================
    function flujoCobro(){
      if(!carrito.length){ toastr.warning('Agrega productos a la orden'); return; }

      const total  = totalActual;
      const fpSlug = formaPagoSlug();

      // ===== Crédito
      if (fpSlug === 'credito'){
        const idCliente = $('#selCliente').val() ? Number($('#selCliente').val()) : null;
        if (!idCliente){
          Swal.fire({
            icon:'warning',
            title:'Selecciona un cliente',
            text:'Para ventas a crédito es obligatorio elegir un cliente.'
          });
          return;
        }
        Swal.fire({
          icon:'question',
          title:'Confirmar venta a crédito',
          html:`<p>Total a crédito: <b>${mxn(total)}</b></p>
                <p class="mb-0">Se imprimirá ticket al confirmar. Podrás abonar o liquidar después en <b>Pagos Parciales</b>.</p>`,
          showCancelButton:true,
          confirmButtonText:'Registrar crédito'
        }).then(res=>{
          if(res.isConfirmed){
            registrarVenta({
              estatus:'Credito',
              pagosInfo:{ tipo:'credito' },
              pagosArr:[]
            });
          }
        });
        return;
      }

      // ===== Efectivo
      if(fpSlug === 'efectivo'){
        const idEf = asegurarIdFP(ID_FP.efectivo, 'efectivo');
        if (!idEf) return;

        Swal.fire({
          title: 'Cobro en efectivo',
          html: `<p>Total a pagar: <b>${mxn(total)}</b></p>`,
          input:'number', inputLabel:'Monto recibido', inputAttributes:{min:'0', step:'0.01'},
          showCancelButton:true, confirmButtonText:'Cobrar',
          preConfirm:(value)=>{
            const monto=Number(value);
            if(isNaN(monto)||monto<total){
              Swal.showValidationMessage('El monto recibido debe ser ≥ total.');
              return false;
            }
            return monto;
          }
        }).then(res=>{
          if(res.isConfirmed){
            const recibido=Number(res.value), cambio=recibido-total;

            const pagosArr = [{
              id_forma_pago: idEf,
              tipo: 'efectivo',
              monto: total,
              referencia: ''
            }];

            registrarVenta({
              estatus:'Activa',
              pagosInfo:{ tipo:'efectivo', recibido, cambio },
              pagosArr
            });
          }
        });
        return;
      }

      // ===== Mixtos
      if (fpSlug === 'mixto_efectivo_tarjeta' || fpSlug === 'mixto_efectivo_transferencia') {
      // Obtén IDs desde catálogo (no hardcode)
      const idEf = asegurarIdFP(ID_FP.efectivo, 'efectivo');
      if (!idEf) return;

      const labelSecundaria = fpSlug === 'mixto_efectivo_tarjeta'
        ? 'Tarjeta'
        : 'Transferencia';

      // Opciones de tarjeta desde forma_pago (sin "Mixto")
      let opcionesTar = [];
      if (fpSlug === 'mixto_efectivo_tarjeta') {
        opcionesTar = opcionesTarjeta(); // <- usa tu catálogo ya cargado
        if (!opcionesTar.length) {
          Swal.fire({
            icon: 'error',
            title: 'Configura las formas de pago',
            text: 'No hay formas de pago activas de tipo tarjeta. Agrega o activa alguna en el catálogo para cobrar con tarjeta.',
          });
          return;
        }
      }

      const idTransfer = fpSlug === 'mixto_efectivo_transferencia'
        ? asegurarIdFP(ID_FP.transferencia, 'transferencia')
        : null;

      if (fpSlug === 'mixto_efectivo_transferencia' && !idTransfer) return;

      Swal.fire({
        title: 'Cobro mixto',
        html: `
          <div class="mixto-wrap">
            <div class="mixto-total">
              Total a pagar: <b>${mxn(total)}</b>
            </div>

            <div class="mixto-grid">
              <div class="mixto-field">
                <label class="form-label mb-1" for="m_efectivo">Efectivo</label>
                <input id="m_efectivo" type="number" min="0" step="0.01"
                      class="form-control" value="${fix2(total)}">
              </div>

              ${fpSlug === 'mixto_efectivo_tarjeta' ? `
                <div class="mixto-field">
                  <label class="form-label mb-1" for="m_tipo_tarjeta">Tipo de tarjeta</label>
                  <select id="m_tipo_tarjeta" class="form-select">
                    <option value="">Seleccione tipo de tarjeta…</option>
                    ${opcionesTar.map(fp => `<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`).join('')}
                  </select>
                </div>

                <div class="mixto-field mixto-span-2">
                  <label class="form-label mb-1" for="m_secundario">Monto tarjeta</label>
                  <input id="m_secundario" type="number" min="0" step="0.01"
                        class="form-control" value="0.00" disabled>
                </div>
              ` : `
                <div class="mixto-field">
                  <label class="form-label mb-1" for="m_secundario">${labelSecundaria}</label>
                  <input id="m_secundario" type="number" min="0" step="0.01"
                        class="form-control" value="0.00">
                </div>
              `}
            </div>
          </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Cobrar',
        cancelButtonText: 'Cancelar',

        // Clases para diseño limpio
        customClass: {
          popup: 'mixto-popup',
          htmlContainer: 'mixto-html',
          actions: 'mixto-actions'
        },

        // Importante si estás usando botones bootstrap custom
        buttonsStyling: false,

        preConfirm: () => {
          const ef = Number(document.getElementById('m_efectivo').value || 0);
          const ms = Number(document.getElementById('m_secundario').value || 0);
          const tipoTarjetaId = document.getElementById('m_tipo_tarjeta')?.value || '';

          // Si hay monto tarjeta, obligar seleccionar tipo
          if (fpSlug === 'mixto_efectivo_tarjeta' && ms > 0 && !tipoTarjetaId) {
            Swal.showValidationMessage('Selecciona el tipo de tarjeta antes de capturar el monto.');
            return false;
          }

          // Validar suma exacta contra total (2 decimales)
          const suma = Number(fix2(ef + ms));
          const totalRed = Number(fix2(total));
          if (suma !== totalRed) {
            Swal.showValidationMessage('La suma de los montos debe coincidir con el total.');
            return false;
          }

          return { ef, ms, tipoTarjetaId };
        },

        didOpen: () => {
          const $tipo = document.getElementById('m_tipo_tarjeta');
          const $montoTar = document.getElementById('m_secundario');

          // Habilitar monto tarjeta solo si elige tipo
          if ($tipo && $montoTar) {
            $tipo.addEventListener('change', () => {
              const tieneTipo = !!$tipo.value;
              $montoTar.disabled = !tieneTipo;
              if (!tieneTipo) $montoTar.value = '0.00';
            });
          }
        }

      }).then(res => {
        if (!res.isConfirmed) return;

        const { ef, ms, tipoTarjetaId } = res.value;
        const cambio = Math.max(0, (ef + ms) - total);

        // Debug: confirma lo que el usuario eligió
        console.debug('Mixto seleccionado', {
          tipo: fpSlug,
          tipoTarjetaId,
          montoTarjeta: ms,
          montoEfectivo: ef
        });

        const pagosArr = [];

        // Efectivo
        if (ef > 0) {
          pagosArr.push({
            id_forma_pago: idEf,
            tipo: 'efectivo',
            monto: ef,
            referencia: ''
          });
        }

        // Segundo medio (tarjeta o transferencia)
        const tipoSec = (fpSlug === 'mixto_efectivo_tarjeta' ? 'tarjeta' : 'transferencia');
        const idFPsec = (fpSlug === 'mixto_efectivo_tarjeta'
          ? Number(tipoTarjetaId || 0)
          : idTransfer);

        if (ms > 0) {
          if (!idFPsec) {
            Swal.fire({
              icon: 'error',
              title: 'Forma de pago faltante',
              text: `No se encontró la forma de pago para ${tipoSec}.`
            });
            return;
          }

          pagosArr.push({
            id_forma_pago: idFPsec,
            tipo: tipoSec,
            monto: ms,
            referencia: ''
          });
        }

        const pagosInfo = {
          tipo: 'mixto',
          recibido_efectivo: ef,
          cambio
        };
        if (tipoSec === 'tarjeta') pagosInfo.recibido_tarjeta = ms;
        else pagosInfo.recibido_transferencia = ms;

        registrarVenta({ estatus: 'Activa', pagosInfo, pagosArr });
      });

      return;
    }


      // ===== Tarjeta / Transferencia "simples"
      if (fpSlug === 'tarjeta' || fpSlug === 'transferencia'){
        const idFP = (fpSlug==='tarjeta' ? asegurarIdFP(ID_FP.tarjeta, 'tarjeta') : asegurarIdFP(ID_FP.transferencia, 'transferencia'));
        if (!idFP) return;

        Swal.fire({
          title: (fpSlug==='tarjeta'?'Cobro con tarjeta':'Cobro por transferencia'),
          html:`<p>Total a cobrar: <b>${mxn(total)}</b></p>`,
          icon:'question', showCancelButton:true, confirmButtonText:'Confirmar'
        }).then(res=>{
          if(res.isConfirmed){
            const pagosArr = [{
              id_forma_pago: idFP,
              tipo: fpSlug,
              monto: total,
              referencia: ''
            }];

            registrarVenta({
              estatus:'Activa',
              pagosInfo:{ tipo:fpSlug },
              pagosArr
            });
          }
        });
        return;
      }
    }

    // ============================================================
    // 11) BINDINGS DE BOTONES
    // ============================================================
    $('#btnCobrar').on('click', flujoCobro);

    $('#btnGuardar').on('click', ()=>{
      if(!carrito.length) return toastr.warning('Agrega productos a la orden');
      Swal.fire({
        icon:'question',
        title:'Guardar venta',
        text:'Se reservará inventario pero NO contará para el corte hasta que la cobres. ¿Continuar?',
        showCancelButton:true,
        confirmButtonText:'Guardar'
      }).then(res=>{
        if(res.isConfirmed){ registrarVenta({estatus:'Guardada'}); }
      });
    });

    $('#btnCancelar').on('click', ()=>{
      carrito=[]; pintarCarrito(); $('#selCliente').val(''); $('#txtBuscar').val('');
      $panel.addClass('d-none').empty();
      $('#fechaVenta').val('<?= date('Y-m-d') ?>');
      $('#tpPrecio').val('taller').trigger('change');
      cargarFormasPago();
      pintarFolioSugerido();
      enfocarBusqueda();
    });

    // ============================================================
    // 12) INIT
    // ============================================================
    cargarClientes();
    cargarFormasPago();
    pintarFolioSugerido();
    $('#fechaVenta').on('change', pintarFolioSugerido);
    enfocarBusqueda();

  })();
  </script>
</body>
</html>
