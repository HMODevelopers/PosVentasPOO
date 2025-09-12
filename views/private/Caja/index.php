<?php
// views/private/ventas/caja.php
// =======================================================
// Vista: Punto de Venta (Caja)
// - Protege sesión
// - Carga layout base (header / breadcrumb / footer)
// - UI: Buscador de productos (izquierda) + Orden/Carrito (derecha)
// - JS: Manejo de carrito, búsqueda, flujo de cobro, registro de venta
// =======================================================

$titulo    = "Ventas";
$modulo    = "Punto de Venta";
$subtitulo = "Caja";

session_start();
require_once __DIR__ . '/../../../includes/config.php';

// --- Guard: usuario autenticado ---
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

  <!-- ========== CSS núcleo del layout ========== -->
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />

  <!-- Toastr (notificaciones) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

  <!-- ========== Estilos locales de la vista POS ========== -->
  <style>
    /* Panel de sugerencias del buscador */
    .sugerencias { position:absolute; z-index:1050; width:99%; max-height:320px; overflow:auto; }
    .sugerencias .list-group-item { cursor:pointer; }
    .sugerencias .active { background:#f1f1f1; }
    .sugerencias .disabled, .sugerencias .disabled * { cursor:not-allowed!important; opacity:.9; }

    /* Resumen de total */
    .total-destacado strong { font-size: 1.8rem; font-weight: 800; }
    .total-destacado { font-weight: 700; }

    /* Tablas / badges */
    .table td, .table th { vertical-align: middle; }
    .badge-stock { font-weight: 600; }

    /* Utilerías */
    .w-70px { width: 70px; }

    /* Layout responsive de la pantalla POS */
    .pos-layout { display: block; }
    @media (min-width: 992px) {
      .pos-layout { display: flex; align-items: flex-start; gap: 1rem; }
      .pos-left  { flex: 1 1 auto; min-width: 0; }
      .pos-right { flex: 0 0 700px; max-width: 700px; }
    }

    /* Tabla dentro de scroll, con header fijo */
    .carrito-scroll { max-height: 300px; overflow-y: auto; border: 1px solid rgba(0,0,0,.075); border-radius: .25rem; }
    .carrito-scroll table { margin-bottom: 0; }
    .carrito-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; }
  </style>
</head>
<body>

  <!-- ========== Topbar / menú global ========== -->
  <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

  <div class="wrapper">
    <!-- Loader global del layout -->
    <div class="wrapper-loader fade" id="LoadingImage" style="display:none;">
      <div class="loader">
        <div class="loader__figure"></div>
        <p class="loader__label">Cargando...</p>
      </div>
    </div>

    <div class="container-fluid">
      <!-- Migas de pan / navegación -->
      <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

      <!-- =======================================================
           CONTENIDO POS
           ======================================================= -->
      <div class="pos-layout">

        <!-- =====================================================
             Columna izquierda: Buscador de productos
             - Input de búsqueda con sugerencias
             - Ideal para lector de códigos/enter
             ===================================================== -->
        <div class="pos-left">
          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Buscar productos</h5>
            </div>

            <div class="card-body">
              <div class="row g-2 align-items-end mb-3 position-relative">
                <div class="col-12">
                  <label class="form-label" for="txtBuscar">Buscar producto</label>
                  <input
                    id="txtBuscar"
                    type="text"
                    class="form-control"
                    placeholder="Nombre o código… (↑/↓ navega, Enter agrega)"
                    autocomplete="off">
                  <!-- Panel de sugerencias -->
                  <div id="panelSug" class="list-group sugerencias d-none"></div>
                </div>
              </div>
              <small class="text-muted">
                Escribe para buscar; Enter agrega el seleccionado o intenta por código exacto (escáner).
              </small>
            </div>
          </div>
        </div>

        <!-- =====================================================
             Columna derecha: Orden / Carrito y Cobro
             - Cliente, tipo de precio, fecha
             - Carrito con cantidades, subtotal editable
             - Selección de forma de pago, acciones
             ===================================================== -->
        <div class="pos-right">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Orden actual</h5>

              <!-- Folio sugerido (informativo) -->
              <span
                class="badge bg-secondary text-white"
                style="padding:6px 16px;border-radius:20px;min-width:82px;text-align:center;"
                title="Folio sugerido (aún no asignado)">
                #<span id="codigoOrden">—</span>
              </span>
            </div>

            <div class="card-body">
              <!-- Cliente -->
              <div class="mb-2">
                <label class="form-label" for="selCliente">Cliente</label>
                <select id="selCliente" class="form-control">
                  <option value="">Cargando clientes…</option>
                </select>
              </div>

              <!-- Tipo de precio / Fecha -->
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

              <!-- Carrito vacío -->
              <div id="wrapCarritoVacio" class="text-muted text-center py-4">
                No hay productos en la orden.
              </div>

              <!-- Carrito tabla -->
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

              <!-- Total -->
              <div class="f-s-14">
                <div class="d-flex justify-content-between mt-2 total-destacado">
                  <strong style="font-size: 1.6rem;">Total</strong>
                  <strong id="resTotal" style="font-size: 1.6rem;">$0.00</strong>
                </div>
              </div>

              <!-- Forma de pago -->
              <div class="mt-3">
                <label class="form-label" for="selFormaPago">Forma de pago</label>
                <select id="selFormaPago" class="form-control form-select">
                  <option value="">Cargando…</option>
                </select>
              </div>

              <!-- Acciones -->
              <div class="mt-3 d-grid gap-2">
                <button id="btnGuardar" class="btn btn-outline-primary">
                  <i class="mdi mdi-content-save-outline me-1"></i> Guardar
                </button>
                <button id="btnCobrar" class="btn btn-success">
                  <i class="mdi mdi-cash-register me-1"></i> Cobrar
                </button>
                <button id="btnCancelar" class="btn btn-outline-danger">
                  <i class="mdi mdi-close-octagon me-1"></i> Cancelar
                </button>
              </div>
            </div>
          </div>
        </div>
      </div><!-- /pos-layout -->
      <!-- ========================= /CONTENIDO POS ========================= -->

      <!-- Hidden: último id_venta para reimpresión -->
      <input type="hidden" id="tk-idventa" value="">
    </div><!-- /container-fluid -->
  </div><!-- /wrapper -->

  <!-- ========== Footer global ========== -->
  <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
  <div class="rightbar-overlay"></div>

  <!-- ========== JS núcleo del layout ========== -->
  <script>const BASE_URL = '<?= BASE_URL ?>';</script>
  <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>

  <!-- Librerías de UI -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- =======================================================
       LÓGICA POS (Carrito, Búsqueda, Ventas)
       ======================================================= -->
  <script>
  (() => {
    // =====================================================
    // Utilidades básicas de formato / conversión
    // =====================================================
    const BASE = BASE_URL;
    const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
    const fix2 = v => (Number(v||0)).toFixed(2);
    const num  = v => parseFloat(v ?? 0) || 0;
    const normalize = s => (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();

    // =====================================================
    // Estado de UI / Datos en memoria
    // =====================================================
    let carrito       = [];                 // Ítems en carrito
    let idxFocus      = -1;                 // Navegación en sugerencias
    let ultResultados = [];                 // Últimos resultados del buscador
    let debTimer      = null;               // Debounce
    const detalleCache = new Map();         // Cache de detalles por id_producto
    let totalActual   = 0;                  // Total acumulado del carrito

    // Comportamiento post-registro (normal / autoprint / credito / guardada)
    let POST_BEHAVIOR = 'normal';

    // =====================================================
    // Forma de pago (derivar un "slug" legible)
    // - Efectivo, transferencia, tarjeta, crédito
    // =====================================================
    function formaPagoSlug(){
      const txt = $('#selFormaPago option:selected').text()?.trim() || '';
      const t = normalize(txt);
      if (t.includes('efectivo')) return 'efectivo';
      if (t.includes('transfer')) return 'transferencia';
      if (t.includes('tarjeta') || t.includes('debito')) return 'tarjeta';
      if (t.includes('credito') || t.includes('crédito')) return 'credito'; // Venta a crédito (no tarjeta)
      return 'tarjeta'; // fallback conservador
    }

    // =====================================================
    // Precios y stock
    // - tipoPrecioActual(): devuelve slug del select (publico/taller/proveedor)
    // - precioDeItem(it): elige precio según slug o override_unit
    // - vendibleDe(det)/maxVendible(it): controla no vender por debajo del stock mínimo
    // =====================================================
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

    const vendibleDe = det => Math.max(0, num(det.stock_actual ?? det.existencia) - num(det.stock_minimo));
    function maxVendible(it){
      const stock = Number(it.stock_actual ?? 0);
      const smin  = Number(it.stock_minimo ?? 0);
      return Math.max(0, stock - smin);
    }

    // =====================================================
    // Folio sugerido (solo display)
    // =====================================================
    function pintarFolioSugerido(){
      const fecha = $('#fechaVenta').val();
      $.get(`${BASE}/controllers/VentasController.php`, { accion:'folio-sugerido', fecha })
      .done(r=>{
        if(r?.ok && r.folio){
          $('#codigoOrden').text(r.folio);
          $('#codigoOrden').closest('.badge')
            .removeClass('bg-success').addClass('bg-secondary')
            .attr('title','Folio sugerido (aún no asignado)');
        }
      });
    }

    // Mapeo de slug de precio a IdTipoPrecio (si lo usas en backend)
    function mapTipoPrecioId(slug){ const m = { publico:1, taller:2, proveedor:3 }; return m[slug] || 1; }

    // =====================================================
    // Clientes (cargar lista y poblar <select>)
    // =====================================================
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

    // =====================================================
    // Formas de pago (cargar y prefijar selección)
    // - Default: si existe “Crédito” (no tarjeta), lo selecciona
    // =====================================================
    function cargarFormasPago(){
      $.get(`${BASE}/controllers/FormasPagoController.php`, {accion:'listar_select'})
        .done(r=>{
          const sel = $('#selFormaPago').empty();
          const arr = r?.data || (Array.isArray(r) ? r : []);
          if (!arr.length) return sel.append(`<option value="">(sin formas de pago)</option>`);
          let idxDefault = 0;
          arr.forEach((fp, i)=>{
            sel.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`);
            const desc = normalize(fp.descripcion);
            if ((desc.includes('credito') || desc.includes('crédito')) && !desc.includes('tarjeta')) {
              idxDefault = i; // crédito preferente
            }
          });
          sel.prop('selectedIndex', idxDefault);
        })
        .fail(()=>{
          const sel = $('#selFormaPago').empty();
          sel.append('<option value="">(sin formas de pago)</option>');
          $('#btnCobrar').prop('disabled', true);
          toastr.error('No se pudieron cargar las formas de pago.');
        });
    }

    // =====================================================
    // Buscador de productos con sugerencias + cache de detalle
    // =====================================================
    const $input = $('#txtBuscar'), $panel = $('#panelSug');

    function debounce(fn, ms){ clearTimeout(debTimer); debTimer = setTimeout(fn, ms); }

    // Fila “básica” en la lista (rellena “extra” después con detalle)
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

    // Texto extra con detalle (precios, stock, proveedor)
    function sugHTMLDetallado(det){
      const pub  = Number(det.precio_publico ?? 0);
      const tal  = Number(det.precio_taller ?? 0);
      const stk  = Number(det.stock_actual ?? det.existencia ?? 0);
      const prov = det.proveedor ?? '';
      const extra = `<span>Taller: ${mxn(tal)}</span> · <span>Público: ${mxn(pub)}</span> · <span>Exist: ${fix2(stk)}</span>${prov?` · <span>Prov: ${prov}</span>`:''}`;
      return { extra, sinStock: (vendibleDe(det) <= 0) };
    }

    // Renderiza lista de resultados y, en paralelo, completa los detalles
    function renderSugerencias(list){
      ultResultados = (list||[]).map((p,i)=>({...p,__i:i}));
      idxFocus = -1;
      $panel.empty();

      if(!ultResultados.length) return $panel.addClass('d-none');

      // Pinta básicos
      ultResultados.forEach(p=>$panel.append(sugHTMLBasico(p)));
      $panel.removeClass('d-none');

      // Enriquecer cada fila con detalle (desde cache o AJAX)
      ultResultados.forEach(p=>{
        const id = p.id_producto;
        const $row = $panel.find(`[data-id="${id}"]`);

        // Desde cache (inmediato)
        if(detalleCache.has(id)){
          const det = detalleCache.get(id);
          const {extra,sinStock} = sugHTMLDetallado(det);
          $row.find('[data-slot="extra"]').html(extra);
          $row.toggleClass('disabled', sinStock).attr('aria-disabled', sinStock ? 'true' : null);
        }

        // Desde servidor
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

    // Realiza búsqueda mínima por texto
    function buscar(q){
      if(!q || q.length < 2){ $panel.addClass('d-none').empty(); return; }
      $.post(`${BASE}/controllers/ProductosController.php`, {accion:'buscar-min', q, limite:20})
        .done(r=>renderSugerencias(r?.data||[]))
        .fail(()=> $panel.addClass('d-none').empty());
    }

    // Selecciona un producto por id y lo agrega al carrito (validando stock)
    function seleccionarPorId(idProd){
      $.post(`${BASE}/controllers/ProductosController.php`, {accion:'detalle', id_producto:idProd})
        .done(r=>{
          const det = r?.data;
          if(!det){ toastr.error('No se encontró el detalle del producto'); return; }

          const vendible = vendibleDe(det);
          if(vendible <= 0){
            const stk  = num(det.stock_actual ?? det.existencia);
            const smin = num(det.stock_minimo);
            toastr.warning(`Sin stock suficiente para vender. (Exist: ${fix2(stk)} · Mín: ${fix2(smin)})`);
            return;
          }

          detalleCache.set(idProd, det);
          agregarDesdeDetalle(det);
          $input.val('');
          $panel.addClass('d-none').empty();
        })
        .fail(()=> toastr.error('No se pudo obtener el detalle del producto'));
    }

    // Eventos del buscador
    $input.on('input', function(){ debounce(()=>buscar(this.value.trim()), 220); });
    $panel.on('click','.list-group-item',function(e){
      e.preventDefault();
      if($(this).hasClass('disabled')) return;
      seleccionarPorId(Number($(this).data('id')));
    });
    // Cerrar sugerencias al hacer click fuera
    $(document).on('click', e=>{
      if(!$(e.target).closest('#txtBuscar,#panelSug').length){
        $panel.addClass('d-none').empty();
      }
    });

    // =====================================================
    // Carrito: agregar, pintar, editar cantidad/subtotal, eliminar
    // =====================================================
    function agregarDesdeDetalle(p){
      const idx = carrito.findIndex(x => x.id_producto == p.id_producto);

      // Base del ítem con campos relevantes para la vista
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

      // No vender por debajo del stock mínimo
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

    // Renderiza la tabla del carrito + total
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
        const sub      = cantidad * precio;    // <- FIX: variable const local (antes `theSubtotal` sin declaración)
        total += sub;

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

            <!-- Cantidad con +/- y edición directa -->
            <td class="text-center">
              <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-danger" data-incdec="dec" data-idx="${idx}">
                  <i class="mdi mdi-minus"></i>
                </button>
                <input type="number" min="1" step="1"
                       class="form-control form-control-sm text-center w-70px"
                       value="${fix2(it.cantidad)}" data-qty="${idx}">
                <button class="btn btn-outline-success" data-incdec="inc" data-idx="${idx}">
                  <i class="mdi mdi-plus"></i>
                </button>
              </div>
            </td>

            <!-- Subtotal editable: recalcula precio unitario si lo cambias -->
            <td class="text-end">
              <input type="number" min="0" step="0.01"
                     class="form-control form-control-sm text-end"
                     value="${fix2(sub)}" data-sub="${idx}"
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

    // Incremento / decremento con botones
    $('#tablaCarrito').on('click','button[data-incdec]', function(){
      const i = Number(this.dataset.idx);
      if(isNaN(i) || !carrito[i]) return;
      const vendible = maxVendible(carrito[i]);
      const sign     = this.dataset.incdec === 'inc' ? 1 : -1;
      const next     = Math.max(1, Math.min(vendible, Number(carrito[i].cantidad) + sign));
      carrito[i].cantidad = next;
      pintarCarrito();
    });

    // Cambio directo en input cantidad
    $('#tablaCarrito').on('change','input[data-qty]', function(){
      const i = Number(this.dataset.qty);
      if(isNaN(i) || !carrito[i]) return;

      let val = Math.max(1, Number(this.value || 1));
      const vendible = maxVendible(carrito[i]);
      if(val > vendible){ val = vendible; toastr.info('Se ajustó a máximo vendible.'); }

      carrito[i].cantidad = val;
      pintarCarrito();
    });

    // Eliminar producto del carrito
    $('#tablaCarrito').on('click','button[data-del]', function(){
      const i = Number(this.dataset.del);
      if(isNaN(i)) return;
      carrito.splice(i, 1);
      pintarCarrito();
    });

    // Editar subtotal => recalcula y fija precio unitario (override_unit)
    $('#tablaCarrito').on('change','input[data-sub]', function(){
      const i = Number(this.dataset.sub);
      if (isNaN(i) || !carrito[i]) return;

      let sub = Number(this.value);
      if (isNaN(sub) || sub < 0) sub = 0;

      const qty  = Math.max(1, Number(carrito[i].cantidad) || 1);
      const unit = sub / qty;

      carrito[i].override_unit = Number(unit.toFixed(2));
      pintarCarrito();
    });

    // =====================================================
    // Impresión de ticket por AJAX (Mike42 en servidor)
    // =====================================================
    function imprimirTicketAjax(idVenta){
      if(!idVenta) return;
      $.get(`${BASE}/utils/ticket_mike42.php`, { id_venta: idVenta })
        .done(resp => { console.log("Impresión:", resp); })
        .fail(xhr => { console.error("Error al imprimir:", xhr.responseText || 'Error al imprimir'); });
    }

    // =====================================================
    // Helper genérico para POST de venta
    // - Envia JSON al controlador VentasController.php?accion=crear
    // =====================================================
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

    // =====================================================
    // Registrar venta
    // - estatus: Activa / Credito / Guardada
    // - pagos: objeto con info de cobro (efectivo/mixto/tarjeta/transferencia)
    // =====================================================
    function registrarVenta({estatus='Activa', pagos={}} = {}){
      const slugPrecio = $('#tpPrecio').val();
      const clienteVal = $('#selCliente').val();
      const idCliente  = clienteVal ? Number(clienteVal) : null;

      // Payload compacto para backend
      const payload = {
        venta: {
          fecha: $('#fechaVenta').val(),
          estatus, // respeta estatus solicitado
          id_cliente: idCliente,
          id_forma_pago: estatus==='Guardada' ? null : (Number($('#selFormaPago').val()) || null),
          id_tipo_precio: mapTipoPrecioId(slugPrecio),
          tipo_precio_slug: slugPrecio,
          ...pagos
        },
        detalles: carrito.map(it => {
          const unit = precioDeItem(it);
          const cant = Number(it.cantidad);
          return {
            id_producto: it.id_producto,
            cantidad:   cant,
            precio_unitario: unit,
            subtotal:   cant * unit
          };
        })
      };

      postVenta(payload, (r)=>{
        if(!r?.ok){
          return Swal.fire({icon:'error', title:'No se pudo registrar', text:(r?.msg||'Intenta de nuevo')});
        }

        // Guardamos id para posible reimpresión
        $('#tk-idventa').val(r.id_venta || '');

        // Mensajería / impresión según comportamiento
        if(estatus==='Guardada'){
          Swal.fire({icon:'success', title:'Venta guardada', html:`<p>Folio: <b>${r.folio}</b></p>`});
        }
        else if (estatus==='Credito' || POST_BEHAVIOR==='credito') {
          Swal.fire({
            icon:'success',
            title:'Venta a crédito registrada',
            html:`<p><small>Folio:</small> <b>${r.folio}</b></p>
                  <p class="mb-0">Podrás realizar <b>abonos</b> o liquidar la venta desde el módulo de <b>Pagos Parciales</b>.</p>`
          });
        }
        else if (POST_BEHAVIOR==='autoprint') {
          // Tarjeta / Transferencia: imprime directo
          if (r.id_venta) imprimirTicketAjax(r.id_venta);
          Swal.fire({icon:'success', title:'Venta registrada', html:`<p><small>Folio:</small> <b>${r.folio}</b></p>`});
        }
        else {
          // Efectivo / Mixto: pregunta si imprimir
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
            if(res.isConfirmed && r.id_venta){ imprimirTicketAjax(r.id_venta); }
          });
        }

        // Reset UI / Estado
        carrito=[]; pintarCarrito(); $('#selCliente').val('');
        $('#tpPrecio').val('taller'); // vuelve a "taller" por defecto
        cargarFormasPago();           // recarga y vuelve a “Crédito” si existe
        pintarFolioSugerido();
        POST_BEHAVIOR = 'normal';
      });
    }

    // =====================================================
    // Flujo de cobro (según forma de pago seleccionada)
    // - Valida cliente obligatorio en crédito
    // - Efectivo: pide monto recibido y calcula cambio
    // - Mixto: solicita montos
    // - Tarjeta/Transferencia: confirma y autoprint
    // =====================================================
    function flujoCobro(){
      if(!carrito.length){ toastr.warning('Agrega productos a la orden'); return; }

      const total  = totalActual;
      const fpSlug = formaPagoSlug();

      // Crédito => requiere cliente
      if (fpSlug === 'credito'){
        const idCliente = $('#selCliente').val() ? Number($('#selCliente').val()) : null;
        if (!idCliente){
          Swal.fire({icon:'warning', title:'Selecciona un cliente', text:'Para ventas a crédito es obligatorio elegir un cliente.'});
          return;
        }
        POST_BEHAVIOR = 'credito';
        Swal.fire({
          icon:'question',
          title:'Confirmar venta a crédito',
          html:`<p>Total a crédito: <b>${mxn(total)}</b></p>
                <p class="mb-0">No se imprimirá ticket ahora. Podrás abonar o liquidar después en <b>Pagos Parciales</b>.</p>`,
          showCancelButton:true,
          confirmButtonText:'Registrar crédito'
        }).then(res=>{
          if(res.isConfirmed){ registrarVenta({estatus:'Credito', pagos:{ tipo:'credito' }}); }
        });
        return;
      }

      // Efectivo => pedir monto recibido
      if(fpSlug === 'efectivo'){
        POST_BEHAVIOR = 'normal';
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

      // Mixto => pedir desglose
      if(fpSlug === 'mixto'){
        POST_BEHAVIOR = 'normal';
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

      // Tarjeta / Transferencia => registra y autoprint
      if (fpSlug === 'tarjeta' || fpSlug === 'transferencia'){
        POST_BEHAVIOR = 'autoprint';
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

    // =====================================================
    // Botones principales: Cobrar / Guardar / Cancelar
    // =====================================================
 
    $('#btnGuardar').on('click', ()=>{
      if (!carrito.length) {
        toastr.warning('Agrega productos a la orden');
        return;
      }

      // 🔹 Ahora ya NO validamos cliente ni forma de pago al guardar
      POST_BEHAVIOR = 'guardada';

      Swal.fire({
        icon: 'question',
        title: 'Guardar venta',
        text: 'Se reservará inventario pero NO contará para el corte hasta que la cobres. ¿Continuar?',
        showCancelButton: true,
        confirmButtonText: 'Guardar'
      }).then(res => {
        if (res.isConfirmed) {
          // Al guardar se manda id_forma_pago = null (ya está controlado en registrarVenta)
          registrarVenta({ estatus: 'Guardada' });
        }
      });
    });

    // Cancelar venta (limpia UI y resetea selectores)
    $('#btnCancelar').on('click', ()=>{
      carrito=[]; pintarCarrito(); $('#selCliente').val(''); $('#txtBuscar').val('');
      $('#fechaVenta').val('<?= date('Y-m-d') ?>');
      $('#tpPrecio').val('taller'); // reset tipo de precio
      cargarFormasPago();           // reset formas de pago (preferirá “Crédito”)
      pintarFolioSugerido();
      POST_BEHAVIOR='normal';
    });

    // =====================================================
    // Init de pantalla
    // =====================================================
    cargarClientes();
    cargarFormasPago();   // intentará dejar “Crédito” seleccionado si existe
    pintarFolioSugerido();
    $('#fechaVenta').on('change', pintarFolioSugerido);
  })();
  </script>
</body>
</html>
