<?php
// views/private/ventas/index.php
$titulo   = "Punto de Venta";
$modulo   = "Ventas";
$subtitulo= "Caja";

session_start();
require_once __DIR__ . '/../../../includes/config.php';

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

  <!-- CSS del template -->
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/libs/toastr/build/toastr.min.css" rel="stylesheet"/>

  <style>
    .sugerencias { position:absolute; z-index:1050; width:99%; max-height:260px; overflow:auto; }
    .sugerencias .list-group-item { cursor:pointer; }
    .sugerencias .active { background:#f1f1f1; }
    .table td, .table th { vertical-align: middle; }
    .badge-stock { font-weight: 600; }
    .total-destacado { font-weight: 700; }
    .w-70px { width: 70px; }

    /* === Layout independiente (no usar .row para que no estire alturas) === */
    .pos-layout {
      display: block;
    }
    @media (min-width: 992px) { /* lg+ */
      .pos-layout {
        display: flex;
        align-items: flex-start;   /* <- evita estirar */
        gap: 1rem;
      }
      .pos-left {
        flex: 1 1 auto;            /* crece libremente */
        min-width: 0;
      }
      .pos-right {
        flex: 0 0 700px;           /* panel derecho ancho fijo */
        max-width: 700px;
      }
      /* Opcional: panel de orden “sticky” mientras haces scroll */
      /* .pos-right .card { position: sticky; top: 80px; } */
    }
  </style>
</head>
<body>
<div class="container-fluid my-3">

  <div class="row">
    <div class="col">
      <h3 class="mb-0"><?= $titulo ?></h3>
      <small class="text-muted"><?= $modulo ?> / <?= $subtitulo ?></small>
    </div>
  </div>
  <hr/>

  <!-- ====== Layout independiente: izquierda (productos) / derecha (orden) ====== -->
  <div class="pos-layout">
    <!-- 🟩 BUSCADOR (AUTOCOMPLETAR) -->
    <div class="pos-left">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Buscar productos</h5>
          <div class="d-flex align-items-center gap-2">
            <label class="me-2 mb-0 small text-muted">Tipo de precio</label>
            <select id="tpPrecio" class="form-control" style="width:auto;">
              <option value="publico">Mostrador (Público)</option>
              <option value="taller">Taller</option>
              <option value="proveedor">Proveedor</option>
            </select>
          </div>
        </div>

        <div class="card-body">
          <!-- Autocompletar -->
          <div class="row g-2 align-items-end mb-3 position-relative">
            <div class="col-12">
              <label class="form-label" for="txtBuscar">Buscar producto</label>
              <input id="txtBuscar" type="text" class="form-control"
                     placeholder="Nombre o código… (↑/↓ navega, Enter agrega)" autocomplete="off">
              <div id="panelSug" class="list-group sugerencias d-none"></div>
            </div>
          </div>

          <small class="text-muted">Escribe para buscar; Enter agrega el seleccionado o intenta por código exacto (escáner).</small>
        </div>
      </div>

      <!-- Si luego quieres más contenido de productos (lista, tabs, etc.), colócalo aquí
           y crecerá de forma independiente al panel de la orden -->
    </div>

    <!-- 🟧 ORDEN / CARRITO -->
    <div class="pos-right">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Orden actual</h5>
          <span class="badge bg-primary text-white" style="padding:6px 12px;border-radius:20px;min-width:82px;text-align:center;">
            #<span id="codigoOrden">—</span>
          </span>
        </div>

        <div class="card-body">
          <!-- Cliente (placeholder; conecta a tu endpoint si lo deseas) -->
          <div class="mb-3 position-relative">
            <label class="form-label" for="txtCliente">Seleccionar cliente</label>
            <div class="input-group">
              <input id="txtCliente" type="text" class="form-control" placeholder="Nombre / RFC" autocomplete="off">
              <button id="btnAltaRapidaCliente" class="btn btn-outline-primary" type="button" title="Alta rápida">
                <i class="mdi mdi-account-plus"></i>
              </button>
            </div>
            <div id="panelClientes" class="list-group sugerencias d-none"></div>
            <input type="hidden" id="idCliente">
          </div>

          <!-- Carrito -->
          <div id="wrapCarritoVacio" class="text-muted text-center py-4">
            No hay productos en la orden.
          </div>

          <div id="wrapCarritoTabla" class="table-responsive d-none">
            <table class="table align-middle" id="tablaCarrito">
              <thead class="table-light">
                <tr>
                  <th>Producto</th>
                  <th class="text-center" style="width:160px;">Cant.</th>
                  <th class="text-end" style="width:120px;">Subtotal</th>
                  <th class="text-end" style="width:54px;"></th>
                </tr>
              </thead>
              <tbody><!-- filas por JS --></tbody>
            </table>
          </div>

          <hr>

          <!-- Resumen -->
          <div class="f-s-14">
            <div class="d-flex justify-content-between mb-1"><span>Impuesto</span><span id="resImpuesto">$0.00</span></div>
            <div class="d-flex justify-content-between mb-1"><span>Cupon</span><span id="resCupon">-$0.00</span></div>
            <div class="d-flex justify-content-between mb-1"><span>Descuento</span><span id="resDescuento">-$0.00</span></div>
            <hr>
            <div class="d-flex justify-content-between mt-2 fs-4 total-destacado">
              <strong>Total</strong><strong id="resTotal">$0.00</strong>
            </div>
          </div>

          <!-- Forma de pago -->
          <div class="mt-3">
            <label class="form-label" for="selFormaPago">Forma de pago</label>
            <select id="selFormaPago" class="form-control form-select">
              <option value="1">Efectivo</option>
              <option value="2">Tarjeta</option>
              <option value="3">Mixto</option>
            </select>
          </div>

          <div class="mt-3 d-grid gap-2">
            <button id="btnCobrar" class="btn btn-success"><i class="mdi mdi-cash-register me-1"></i> Cobrar</button>
            <button id="btnCancelar" class="btn btn-outline-danger"><i class="mdi mdi-close-octagon me-1"></i> Cancelar</button>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /pos-layout -->

</div>

<script>const BASE_URL = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
<script src="<?= BASE_URL ?>/assets/libs/toastr/build/toastr.min.js"></script>

<script>
(() => {
  const BASE = BASE_URL;
  const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
  const fix2 = v => (Number(v||0)).toFixed(2);

  // ====== Estado ======
  let carrito = []; // {id_producto,codigo,descripcion,stock_actual,precio_publico,precio_taller,precio_proveedor,cantidad}
  let idxFocus = -1;
  let ultResultados = [];
  let debTimer = null;

  // ====== Precio activo (según selector) ======
  function tipoPrecioActual() {
    return ($('#tpPrecio').val() || 'publico'); // 'publico' | 'taller' | 'proveedor'
  }
  function precioDeItem(item) {
    const t = tipoPrecioActual();
    if (t === 'taller') return Number(item.precio_taller||0);
    if (t === 'proveedor') return Number(item.precio_proveedor||0);
    return Number(item.precio_publico||0);
  }

  // ====== Autocompletar contra ProductosController (buscar-min) ======
  const $input = $('#txtBuscar');
  const $panel = $('#panelSug');

  function debounce(fn, ms){ clearTimeout(debTimer); debTimer = setTimeout(fn, ms); }

  function renderSugerencias(list){
    ultResultados = list || [];
    idxFocus = -1;
    $panel.empty();
    if(!ultResultados.length){ $panel.addClass('d-none'); return; }

    ultResultados.forEach((p,i)=>{
      // buscar-min: id_producto, codigo, descripcion, precio_proveedor
      const prev = Number(p.precio_proveedor ?? 0);
      $panel.append(`
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           data-i="${i}">
          <div>
            <div><strong>${p.codigo}</strong> — ${p.descripcion}</div>
            <div class="small text-muted">Ref. (proveedor): ${mxn(prev)}</div>
          </div>
        </a>
      `);
    });
    $panel.removeClass('d-none');
  }

  function buscar(q){
    if(!q || q.length<2){ $panel.addClass('d-none').empty(); return; }

    $.post(`${BASE}/controllers/ProductosController.php`, {
      accion:'buscar-min', q, limite: 20
    })
    .done(r => renderSugerencias(r?.data || []))
    .fail(()=> $panel.addClass('d-none').empty());
  }

  // Al seleccionar una sugerencia → pedir detalle para obtener todos los precios y stock_actual
  function seleccionar(idx){
    const b = ultResultados[idx]; if(!b) return;
    $.post(`${BASE}/controllers/ProductosController.php`, {
      accion:'detalle', id_producto: b.id_producto
    })
    .done(r=>{
      const p = r?.data;
      if(!p){ toastr.error('No se encontró el detalle del producto'); return; }
      agregarDesdeDetalle(p);
    });
    $input.val(''); $panel.addClass('d-none').empty();
  }

  // Enter rápido (escáner): coincidencia exacta por código dentro de buscar-min
  function enterEscaner(q) {
    $.post(`${BASE}/controllers/ProductosController.php`, {
      accion:'buscar-min', q, limite: 20
    }).done(r=>{
      const lst = r?.data || [];
      const exact = lst.find(x => (x.codigo||'').toString().trim().toUpperCase() === q.toUpperCase());
      if(exact){
        $.post(`${BASE}/controllers/ProductosController.php`, {
          accion:'detalle', id_producto: exact.id_producto
        }).done(rr=>{
          if(rr?.data) agregarDesdeDetalle(rr.data);
        });
      } else if (lst.length) {
        // si no hay exacto, toma el primero (opcional)
        $.post(`${BASE}/controllers/ProductosController.php`, {
          accion:'detalle', id_producto: lst[0].id_producto
        }).done(rr=>{
          if(rr?.data) agregarDesdeDetalle(rr.data);
        });
      } else {
        toastr.warning('No se encontraron productos para ese código');
      }
      $input.val('');
    });
  }

  // Render panel activo con flechas
  $input.on('keydown', (e)=>{
    if(!$panel.is(':visible')) return;
    const max = ultResultados.length - 1;
    if(e.key==='ArrowDown'){ e.preventDefault(); idxFocus = Math.min(max, idxFocus+1); marcarActivo(); }
    if(e.key==='ArrowUp'){   e.preventDefault(); idxFocus = Math.max(0, idxFocus-1);  marcarActivo(); }
    if(e.key==='Enter'){     e.preventDefault(); if(idxFocus>=0) seleccionar(idxFocus); }
    if(e.key==='Escape'){    $panel.addClass('d-none').empty(); }
  });

  function marcarActivo(){
    $panel.children().removeClass('active');
    if(idxFocus>=0) $panel.children().eq(idxFocus).addClass('active');
  }

  // Escribir → debounce
  $input.on('input', function(){
    const q = this.value.trim();
    debounce(()=>buscar(q), 220);
  });

  // Enter cuando no hay panel → intenta escáner
  $input.on('keypress', function(e){
    if(e.key!=='Enter') return;
    if($panel.children().length) return; // si hay panel, se maneja arriba
    const q = this.value.trim();
    if(!q) return;
    enterEscaner(q);
  });

  // Click en sugerencia
  $panel.on('click','.list-group-item', function(e){
    e.preventDefault(); seleccionar(Number($(this).data('i')));
  });

  // Click fuera → cerrar
  $(document).on('click', (e)=>{
    if(!$(e.target).closest('#txtBuscar, #panelSug').length){
      $panel.addClass('d-none').empty();
    }
  });

  // ====== Agregar al carrito desde detalle ======
  function agregarDesdeDetalle(p){
    const i = carrito.findIndex(x => x.id_producto==p.id_producto);
    if(i>=0){
      carrito[i].cantidad = Number(carrito[i].cantidad) + 1;
    } else {
      carrito.push({
        id_producto: p.id_producto,
        codigo: p.codigo,
        descripcion: p.descripcion,
        stock_actual: Number(p.stock_actual ?? 0),
        precio_publico: Number(p.precio_publico ?? 0),
        precio_taller: Number(p.precio_taller ?? 0),
        precio_proveedor: Number(p.precio_proveedor ?? 0),
        cantidad: 1
      });
    }
    pintarCarrito();
  }

  // ====== Recalcular y pintar carrito ======
  function pintarCarrito(){
    const tb = $('#tablaCarrito tbody').empty();
    if(!carrito.length){
      $('#wrapCarritoVacio').removeClass('d-none');
      $('#wrapCarritoTabla').addClass('d-none');
      $('#resTotal').text('$0.00');
      return;
    }
    $('#wrapCarritoVacio').addClass('d-none');
    $('#wrapCarritoTabla').removeClass('d-none');

    let total=0;
    carrito.forEach((it,idx)=>{
      const precio = precioDeItem(it);
      const subtotal = Number(it.cantidad)*precio;
      total += subtotal;

      tb.append(`
        <tr>
          <td>
            <div class="d-flex align-items-center">
              <div>
                <div class="fw-semibold">${it.descripcion}</div>
                <div class="small text-muted">Cod: ${it.codigo} · Exist: <span class="badge ${it.stock_actual>0?'bg-success':'bg-secondary'} badge-stock">${fix2(it.stock_actual)}</span></div>
              </div>
            </div>
          </td>
          <td class="text-center">
            <div class="btn-group btn-group-sm" role="group">
              <button class="btn btn-outline-danger" data-dec="${idx}"><i class="mdi mdi-minus"></i></button>
              <center><input type="number" min="1" step="1" class="form-control form-control-sm text-center w-70px" value="${fix2(it.cantidad)}" data-qty="${idx}"></center>
              <button class="btn btn-outline-success" data-inc="${idx}"><i class="mdi mdi-plus"></i></button>
            </div>
          </td>
          <td class="text-end"><center>${mxn(subtotal)}</center></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-danger" data-del="${idx}">
              <i class="mdi mdi-delete"></i>
            </button>
          </td>
        </tr>
      `);
    });
    $('#resTotal').text(mxn(total));
  }

  // Cambiar tipo de precio → recalcular todo
  $('#tpPrecio').on('change', pintarCarrito);

  // Handlers de carrito
  $('#tablaCarrito').on('click','button[data-inc]', function(){
    const i = Number($(this).data('inc')); carrito[i].cantidad++; pintarCarrito();
  });
  $('#tablaCarrito').on('click','button[data-dec]', function(){
    const i = Number($(this).data('dec')); carrito[i].cantidad=Math.max(1,carrito[i].cantidad-1); pintarCarrito();
  });
  $('#tablaCarrito').on('change','input[data-qty]', function(){
    const i = Number($(this).data('qty')); carrito[i].cantidad = Math.max(1, Number(this.value||1)); pintarCarrito();
  });
  $('#tablaCarrito').on('click','button[data-del]', function(){
    const i = Number($(this).data('del')); carrito.splice(i,1); pintarCarrito();
  });

  // Cobrar / Cancelar (placeholder)
  $('#btnCobrar').on('click', ()=>{
    if(!carrito.length) return toastr.warning('Agrega productos a la orden');
    toastr.info('Flujo de cobro aquí. Luego conectamos con VentasController->registrar.');
  });
  $('#btnCancelar').on('click', ()=>{
    carrito=[]; pintarCarrito(); $('#txtCliente,#idCliente').val(''); $('#txtBuscar').val('');
  });

  // Init
  // $('#tpPrecio').val('publico'); // si quieres setear por default otro, cámbialo aquí
})();
</script>
</body>
</html>
