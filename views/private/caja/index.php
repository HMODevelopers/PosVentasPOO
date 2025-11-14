<?php
/**
 * views/private/ventas/caja.php
 * --------------------------------------------------------------------------
 * Vista de Punto de Venta (POS) "Caja".
 * - Protege la sesión (solo usuarios autenticados).
 * - Carga el layout global (header/breadcrumb/footer).
 * - UI: Buscador de productos + Orden/Carrito + Flujo de Cobro.
 * - JS: Manejo de carrito, búsqueda con sugerencias, cálculo de totales,
 *       alta de venta (AJAX) y flujo de cobro con SweetAlert2, incluyendo CRÉDITO.
 * --------------------------------------------------------------------------
 */

$titulo    = "Ventas";
$modulo    = "Punto de Venta";
$subtitulo = "Caja";

session_start();
require_once __DIR__ . '/../../../includes/config.php';

// --- Guard: si no hay usuario en sesión, redirige al login público.
if (!isset($_SESSION['usuario'])) {
  header('Location: ' . BASE_URL . '/views/public/index.php');
  exit();
}
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
    .sugerencias .disabled, .sugerencias .disabled * { cursor:not-allowed!important; opacity:.9; }

    /* Resumen de total */
    .total-destacado strong { font-size: 1.8rem; font-weight: 800; }
    .total-destacado { font-weight: 700; }
    .table td, .table th { vertical-align: middle; }
    .badge-stock { font-weight: 600; }

    /* Utilidades */
    .w-70px { width: 70px; }

    /* Layout responsive de la pantalla POS */
    .pos-layout { display: block; }
    @media (min-width: 992px) {
      .pos-layout { display: flex; align-items: flex-start; gap: 1rem; }
      .pos-left  { flex: 1 1 auto; min-width: 0; }
      .pos-right { flex: 0 0 700px; max-width: 700px; }
    }

    /* Tabla del carrito dentro de scroll, con header fijo */
    .carrito-scroll { max-height: 300px; overflow-y: auto; border: 1px solid rgba(0,0,0,.075); border-radius: .25rem; }
    .carrito-scroll table { margin-bottom: 0; }
    .carrito-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; }
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
                        <th>Producto</th>
                        <th class="text-center" style="width:210px;">Cant.</th>
                        <th class="text-end" style="width:160px;">Subtotal</th>
                        <th class="text-end" style="width:54px;"></th>
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
                <!-- Tip: type="button" evita submits si algún día se envuelve en <form> -->
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
    const BASE = BASE_URL; // URL base del sistema (inyectada por PHP).
    const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
    const fix2 = v => (Number(v||0)).toFixed(2);
    const num  = v => parseFloat(v ?? 0) || 0;
    const normalize = s => (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();

    // ============================================================
    // 2) ESTADO EN MEMORIA (solo del cliente)
    // ============================================================
    let carrito       = [];        // Lista de ítems en carrito
    let idxFocus      = -1;        // Índice de navegación en sugerencias
    let ultResultados = [];        // Cache de últimos resultados del buscador (básico)
    let debTimer      = null;      // Temporizador para debounce
    const detalleCache = new Map();// Cache por id_producto de detalles
    let totalActual   = 0;         // Total vigente mostrado en UI

    // ============================================================
    // 3) MAPEOS / LÓGICA DE NEGOCIO (precio/formapago/stock)
    // ============================================================
    function formaPagoSlug(){
      const txt = $('#selFormaPago option:selected').text()?.trim() || '';
      const t = normalize(txt);
      if (t.includes('efectivo')) return 'efectivo';
      if (t.includes('mixto') || t.includes('mixta')) return 'mixto';
      if ((t.includes('credito') || t.includes('crédito')) && !t.includes('tarjeta')) return 'credito'; // Crédito a cliente (no tarjeta)
      if (t.includes('transfer')) return 'transferencia';
      if (t.includes('tarjeta') || t.includes('debito') || t.includes('débito')) return 'tarjeta';
      return 'tarjeta'; // Fallback conservador
    }

    function tipoPrecioActual() { return ($('#tpPrecio').val() || 'publico'); }

    function precioDeItem(it){
      if (typeof it.override_unit === 'number' && !isNaN(it.override_unit)) {
        return Number(it.override_unit);
      }
      const t = tipoPrecioActual();
      if (t === 'taller')     return Number(it.precio_taller||0);
      if (t === 'proveedor')  return Number(it.precio_proveedor||0);
      return Number(it.precio_publico||0); // público por defecto
    }

    // Ahora solo cuenta la existencia real, ignorando el stock mínimo para POS
    const vendibleDe = det => Math.max(0, num(det.stock_actual ?? det.existencia));

    function maxVendible(it){
      const stock = num(it.stock_actual ?? it.existencia ?? 0);
      return Math.max(0, stock); // puedes vender todo lo que haya
    }

    function mapTipoPrecioId(slug){ const m = { publico:1, taller:2, proveedor:3 }; return m[slug] || 1; }

    // ============================================================
    // 4) SERVICIOS (AJAX) PARA CARGAR DATOS A LA VISTA
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
            // Fallback a listar normal
            $.post(`${BASE}/controllers/ClientesController.php`, {accion:'listar', pagina:1, limite:LIM})
              .done(r2=>{
                const data2 = r2?.data || [];
                setClientesOptions(data2);
                if (!data2.length) toastr.info('No hay clientes activos. Usando “Mostrador / Público general”.');
              })
              .fail(()=> {
                setClientesOptions([]); toastr.error('No se pudieron cargar clientes (listar).');
              });
          }
        })
        .fail(()=>{
          // Fallback ante error
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

            let idxDefault = 0; // por defecto, primera opción
            arr.forEach((fp, i)=>{
              sel.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`);
              const d = normalize(fp.descripcion);
              // 🔹 Ahora preferimos “Efectivo”
              if (d.includes('efectivo')) idxDefault = i;
            });

            sel.prop('selectedIndex', idxDefault);
          })
          .fail(()=>{
            // Fallback simple para no dejar vacío
            const sel = $('#selFormaPago').empty();
            sel.append(`
              <option value="1" selected>Efectivo</option>
              <option value="2">Tarjeta</option>
              <option value="3">Mixto</option>
              <option value="4">Crédito</option>
            `);
          });
    }


    // ============================================================
    // 5) BUSCADOR DE PRODUCTOS (sugerencias + detalle diferido)
    // ============================================================
    const $input = $('#txtBuscar'), $panel = $('#panelSug');

    function debounce(fn, ms){ clearTimeout(debTimer); debTimer = setTimeout(fn, ms); }

    function sugHTMLBasico(p){
      return `
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
          data-i="${p.__i}" data-id="${p.id_producto}">
          <div class="me-2" style="min-width:0">
            <div class="text-truncate"><strong>${p.codigo}</strong> — ${p.descripcion}</div>
            <div class="small text-muted" data-slot="extra">Cargando detalles…</div>
          </div>
          <i class="mdi mdi-plus-circle-outline"></i>
        </a>`;
    }

    function sugHTMLDetallado(det){
      const pub  = Number(det.precio_publico ?? 0);
      const tal  = Number(det.precio_taller ?? 0);
      const stk  = Number(det.stock_actual ?? det.existencia ?? 0);
      const prov = det.proveedor ?? '';
      const extra = `<span>Taller: ${mxn(tal)}</span> · <span>Público: ${mxn(pub)}</span> · <span>Exist: ${fix2(stk)}</span>${prov?` · <span>Prov: ${prov}</span>`:''}`;
      return { extra, sinStock: (vendibleDe(det) <= 0) };
    }

    function renderSugerencias(list){
      ultResultados = (list||[]).map((p,i)=>({...p,__i:i}));
      idxFocus = -1;
      $panel.empty();

      if(!ultResultados.length) return $panel.addClass('d-none');

      // 1) Dibuja filas básicas
      ultResultados.forEach(p=>$panel.append(sugHTMLBasico(p)));
      $panel.removeClass('d-none');

      // 2) Completa con detalle (cache → servidor)
      ultResultados.forEach(p=>{
        const id = p.id_producto;
        const $row = $panel.find(`[data-id="${id}"]`);

        if(detalleCache.has(id)){
          const det = detalleCache.get(id);
          const {extra,sinStock} = sugHTMLDetallado(det);
          $row.find('[data-slot="extra"]').html(extra);
          $row.toggleClass('disabled', sinStock).attr('aria-disabled', sinStock ? 'true' : null);
        }

        $.post(`${BASE}/controllers/ProductosController.php`, {accion:'detalle', id_producto:id})
          .done(r=>{
            const det = r?.data || {};
            detalleCache.set(id, det);
            const {extra,sinStock} = sugHTMLDetallado(det);
            $row.find('[data-slot="extra"]').html(extra);
            $row.toggleClass('disabled', sinStock).attr('aria-disabled', sinStock ? 'true' : null);
          });
      });
    }

    function buscar(q){
      if(!q || q.length < 2){ $panel.addClass('d-none').empty(); return; }
      $.post(`${BASE}/controllers/ProductosController.php`, {accion:'buscar-min', q, limite:20})
        .done(r=>renderSugerencias(r?.data||[]))
        .fail(()=> $panel.addClass('d-none').empty());
    }

    function seleccionarPorId(idProd){
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
          $input.val('');
          $panel.addClass('d-none').empty();
        })
        .fail(()=> toastr.error('No se pudo obtener el detalle del producto'));
    }

    $('#txtBuscar').on('input', function(){ debounce(()=>buscar(this.value.trim()), 220); });
    $('#panelSug').on('click','.list-group-item',function(e){
      e.preventDefault();
      if($(this).hasClass('disabled')) return;
      seleccionarPorId(Number($(this).data('id')));
    });
    $(document).on('click', e=>{
      if(!$(e.target).closest('#txtBuscar,#panelSug').length){
        $panel.addClass('d-none').empty();
      }
    });

    // ============================================================
    // 6) CARRITO (agregar, pintar, editar cant/subtotal, eliminar)
    // ============================================================
    function agregarDesdeDetalle(p){
      const idx = carrito.findIndex(x => x.id_producto == p.id_producto);

      const itemBase = {
        id_producto: p.id_producto,
        codigo: p.codigo,
        descripcion: p.descripcion,
        stock_actual: Number(p.stock_actual ?? p.existencia ?? 0),
        stock_minimo: Number(p.stock_minimo ?? 0),
        precio_publico: Number(p.precio_publico ?? 0),
        precio_taller: Number(p.precio_taller ?? 0),
        precio_proveedor: Number(p.precio_proveedor ?? 0),
        proveedor: p.proveedor ?? null
      };

      const vendible = maxVendible(itemBase);
      if(vendible <= 0){ toastr.warning('Sin stock disponible para vender.'); return; }

      if(idx >= 0){
        const next = Math.min(vendible, Number(carrito[idx].cantidad) + 1);
        carrito[idx].cantidad = next;
      } else {
        carrito.push({...itemBase, cantidad: 1});
      }
      pintarCarrito();
    }

    function pintarCarrito(){
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
      const precio   = precioDeItem(it);
      const cantidad = Number(it.cantidad) || 0;
      const subtotal = cantidad * precio;
      total += subtotal;

      $tb.append(`
        <tr>
          <td>
            <div class="d-flex align-items-center">
              <div>
                <div class="fw-semibold">${it.descripcion}</div>
                <div class="small text-muted">
                  Cod: ${it.codigo} ${it.proveedor?`· Prov: ${it.proveedor}`:``}
                  · Exist: <span class="badge ${Number(it.stock_actual)>0?'bg-success':'bg-secondary'} badge-stock">${fix2(it.stock_actual)}</span>
                </div>
              </div>
            </div>
          </td>

          <!-- Cantidad con +/- y edición directa (permite decimales) -->
          <td class="text-center">
            <div class="btn-group btn-group-sm" role="group">
              <button class="btn btn-outline-danger" data-dec="${idx}">
                <i class="mdi mdi-minus"></i>
              </button>
              <input type="number"
                    min="0.01"
                    step="0.01"
                    class="form-control form-control-sm text-center w-70px"
                    value="${fix2(it.cantidad)}"
                    data-qty="${idx}">
              <button class="btn btn-outline-success" data-inc="${idx}">
                <i class="mdi mdi-plus"></i>
              </button>
            </div>
          </td>

          <!-- Subtotal editable: step=1 para que 20.70 pase a 21.70 -->
          <td class="text-end">
            <input type="number"
                  min="0"
                  step="1"
                  class="form-control form-control-sm text-end"
                  value="${fix2(subtotal)}"
                  data-sub="${idx}"
                  title="Editar subtotal (ajusta el precio unitario automáticamente)">
          </td>

          <!-- Eliminar ítem -->
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

  $('#tpPrecio').on('change',pintarCarrito);

  /* ========== CANTIDAD ========== */

  $('#tablaCarrito').on('click','button[data-inc]',function(){
    const i = Number(this.dataset.inc);
    if(isNaN(i) || !carrito[i]) return;

    const vendible = maxVendible(carrito[i]);
    const actual   = Number(carrito[i].cantidad) || 0;
    const next     = actual + 1; // sigue sumando de 1 en 1 (para piezas)

    carrito[i].cantidad = next > vendible
      ? (toastr.info('Se alcanzó el máximo vendible.'), vendible)
      : next;

    // Redondeamos a 2 decimales por si hay decimales
    carrito[i].cantidad = Number(carrito[i].cantidad.toFixed(2));
    pintarCarrito();
  });

  $('#tablaCarrito').on('click','button[data-dec]',function(){
    const i = Number(this.dataset.dec);
    if(isNaN(i) || !carrito[i]) return;

    const actual = Number(carrito[i].cantidad) || 0;
    let next = actual - 1; // resta de 1 en 1
    if (next < 0.01) next = 0.01; // MIN para permitir cable 0.70, 0.50, etc.

    carrito[i].cantidad = Number(next.toFixed(2));
    pintarCarrito();
  });

  $('#tablaCarrito').on('change','input[data-qty]',function(){
    const i = Number(this.dataset.qty);
    if(isNaN(i) || !carrito[i]) return;

    let val = Number(this.value || 0);
    if (isNaN(val) || val <= 0) val = 0.01;      // mínimo 0.01
    const vendible = maxVendible(carrito[i]);

    if (val > vendible) {
      val = vendible;
      toastr.info('Se ajustó a máximo vendible.');
    }

    carrito[i].cantidad = Number(val.toFixed(2)); // siempre 2 decimales
    pintarCarrito();
  });

  /* ========== ELIMINAR ITEM ========== */

  $('#tablaCarrito').on('click','button[data-del]',function(){
    const i = Number(this.dataset.del);
    if(isNaN(i)) return;
    carrito.splice(i,1);
    pintarCarrito();
  });

  /* ========== SUBTOTAL EDITABLE (CAMBIO DE PRECIO) ========== */

  $('#tablaCarrito').on('change','input[data-sub]', function(){
    const i = Number(this.dataset.sub);
    if (isNaN(i) || !carrito[i]) return;

    let sub = Number(this.value);
    if (isNaN(sub) || sub < 0) sub = 0;

    // Redondeamos el subtotal a 2 decimales
    sub = Number(sub.toFixed(2));

    const qty = Math.max(0.01, Number(carrito[i].cantidad) || 0.01);
    const unit = sub / qty;

    // Precio unitario forzado (2 decimales)
    carrito[i].override_unit = Number(unit.toFixed(2));
    pintarCarrito();
  });

    // ============================================================
    // 7) IMPRESIÓN DE TICKET (requiere script server-side Mike42)
    // ============================================================
    function imprimirTicketAjax(idVenta){
       if (!idVenta) return false;
        const url = `${BASE}/utils/ticket_pdf.php?id_venta=${encodeURIComponent(idVenta)}`;
        const win = window.open(url, '_blank');
        if (win) win.focus();
        return true; // por si alguien lo "await"
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
      .fail(()=> Swal.fire({icon:'error', title:'Error de comunicación', text:'No fue posible contactar al servidor.'}));
    }

    // ============================================================
    // 9) REGISTRO DE VENTA (arma payload y maneja respuesta)
    // ============================================================
    // ============================================================
    function registrarVenta({estatus='Activa', pagos={}} = {}){
      const slugPrecio = $('#tpPrecio').val();
      const clienteVal = $('#selCliente').val();
      const idCliente  = clienteVal ? Number(clienteVal) : null;

      const payload = {
        venta: {
          fecha: $('#fechaVenta').val(),
          estatus,                         // 'Activa' | 'Guardada' | 'Credito'
          id_cliente: idCliente,           // En crédito: requerido (validado en flujoCobro)
          id_forma_pago: estatus==='Guardada' ? null : (Number($('#selFormaPago').val()) || null),
          id_tipo_precio: mapTipoPrecioId(slugPrecio),
          tipo_precio_slug: slugPrecio,
          ...pagos                         // { tipo:'efectivo'|'mixto'|'tarjeta'|'transferencia'|'credito', ... }
        },
        detalles: carrito.map(it => {
          const unit = precioDeItem(it), cant = Number(it.cantidad);
          return { id_producto: it.id_producto, cantidad: cant, precio_unitario: unit, subtotal: cant*unit };
        })
      };

      postVenta(payload, (r)=>{
        if(!r?.ok){
          return Swal.fire({ icon:'error', title:'No se pudo registrar', text:(r?.msg||'Intenta de nuevo') });
        }

        // Guardar último id de venta para reimpresión
        $('#tk-idventa').val(r.id_venta || '');

        if (estatus === 'Guardada'){
          Swal.fire({ icon:'success', title:'Venta guardada', html:`<p>Folio: <b>${r.folio}</b></p>` });
        }
        else if (estatus === 'Credito'){
          // === Botón para imprimir ticket en crédito ===
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
          // Activa (efectivo/mixto/tarjeta/transferencia)
          const cambioTxt = (typeof pagos.cambio === 'number')
            ? `<p><small>Cambio:</small> <b>${mxn(pagos.cambio)}</b></p>` : '';
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

        // Reset UI post-venta
        carrito=[]; pintarCarrito(); $('#selCliente').val('');
        $('#tpPrecio').val('taller');
        cargarFormasPago();
        pintarFolioSugerido();
      });
    }


    // ============================================================
    // 10) FLUJO DE COBRO (define la UX según forma de pago)
    // ============================================================
    function flujoCobro(){
      if(!carrito.length){ toastr.warning('Agrega productos a la orden'); return; }

      const total  = totalActual;
      const fpSlug = formaPagoSlug();

      // ---- CRÉDITO: requiere cliente y no pide monto ----
      if (fpSlug === 'credito'){
        const idCliente = $('#selCliente').val() ? Number($('#selCliente').val()) : null;
        if (!idCliente){
          Swal.fire({icon:'warning', title:'Selecciona un cliente', text:'Para ventas a crédito es obligatorio elegir un cliente.'});
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
            // El ticket se ofrece en el Swal de 'registrarVenta' (estatus === 'Credito')
            registrarVenta({ estatus:'Credito', pagos:{ tipo:'credito' } });
          }
        });
        return;
      }

      // ---- EFECTIVO: pide monto recibido y calcula cambio ----
      if(fpSlug === 'efectivo'){
        Swal.fire({
          title: 'Cobro en efectivo',
          html: `<p>Total a pagar: <b>${mxn(total)}</b></p>`,
          input:'number', inputLabel:'Monto recibido', inputAttributes:{min:'0', step:'0.01'},
          showCancelButton:true, confirmButtonText:'Cobrar',
          preConfirm:(value)=>{
            const monto=Number(value);
            if(isNaN(monto)||monto<total){ Swal.showValidationMessage('El monto recibido debe ser ≥ total.'); return false; }
            return monto;
          }
        }).then(res=>{
          if(res.isConfirmed){
            const recibido=Number(res.value), cambio=recibido-total;
            registrarVenta({estatus:'Activa', pagos:{ tipo:'efectivo', recibido, cambio }});
          }
        });
        return;
      }

      // ---- MIXTO: efectivo + tarjeta; validar suma ≥ total ----
      if(fpSlug === 'mixto'){
        Swal.fire({
          title:'Cobro mixto',
          html:`<div class="text-start">
            <p>Total a pagar: <b>${mxn(total)}</b></p>
            <div class="mb-2"><label class="form-label">Efectivo</label>
              <input id="m_efectivo" type="number" min="0" step="0.01" class="swal2-input" style="width:auto" value="${fix2(total)}">
            </div>
            <div class="mb-2"><label class="form-label">Tarjeta</label>
              <input id="m_tarjeta" type="number" min="0" step="0.01" class="swal2-input" style="width:auto" value="0.00">
            </div>
          </div>`,
          focusConfirm:false, showCancelButton:true, confirmButtonText:'Cobrar',
          preConfirm:()=>{
            const ef=Number(document.getElementById('m_efectivo').value||0);
            const tj=Number(document.getElementById('m_tarjeta').value||0);
            if((ef+tj)<total){ Swal.showValidationMessage('Efectivo + tarjeta debe ser ≥ total.'); return false; }
            return {ef,tj};
          }
        }).then(res=>{
          if(res.isConfirmed){
            const {ef,tj}=res.value; const cambio=Math.max(0,(ef+tj)-total);
            registrarVenta({estatus:'Activa', pagos:{ tipo:'mixto', recibido_efectivo:ef, recibido_tarjeta:tj, cambio }});
          }
        });
        return;
      }

      // ---- TARJETA / TRANSFERENCIA: confirmar y registrar ----
      if (fpSlug === 'tarjeta' || fpSlug === 'transferencia'){
        Swal.fire({
          title: (fpSlug==='tarjeta'?'Cobro con tarjeta':'Cobro por transferencia'),
          html:`<p>Total a cobrar: <b>${mxn(total)}</b></p>`,
          icon:'question', showCancelButton:true, confirmButtonText:'Confirmar'
        }).then(res=>{
          if(res.isConfirmed){
            registrarVenta({estatus:'Activa', pagos:{ tipo:fpSlug }});
          }
        });
        return;
      }
    }


    // ============================================================
    // 11) BINDINGS DE BOTONES (Guardar/Cobrar/Cancelar)
    // ============================================================
    $('#btnCobrar').on('click', flujoCobro);

    $('#btnGuardar').on('click', ()=>{
      if(!carrito.length) return toastr.warning('Agrega productos a la orden');
      Swal.fire({
        icon:'question', title:'Guardar venta',
        text:'Se reservará inventario pero NO contará para el corte hasta que la cobres. ¿Continuar?',
        showCancelButton:true, confirmButtonText:'Guardar'
      }).then(res=>{ if(res.isConfirmed){ registrarVenta({estatus:'Guardada'}); }});
    });

    $('#btnCancelar').on('click', ()=>{
      // Limpia carrito y controles básicos de la vista
      carrito=[]; pintarCarrito(); $('#selCliente').val(''); $('#txtBuscar').val('');
      $('#fechaVenta').val('<?= date('Y-m-d') ?>'); // Fecha del día (servidor)
      $('#tpPrecio').val('taller').trigger('change');               // Regresa a “público”
      cargarFormasPago();                           // Refresca y aplica default (Crédito si existe)
      pintarFolioSugerido();
    });

    // ============================================================
    // 12) INIT (al cargar la pantalla)
    // ============================================================
    cargarClientes();
    cargarFormasPago();
    pintarFolioSugerido();
    $('#fechaVenta').on('change', pintarFolioSugerido);
  })();
  </script>
</body>
</html>
