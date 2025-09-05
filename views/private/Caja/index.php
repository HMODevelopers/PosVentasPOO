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

  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

  <style>
    .sugerencias { position:absolute; z-index:1050; width:99%; max-height:320px; overflow:auto; }
    .sugerencias .list-group-item { cursor:pointer; }
    .sugerencias .active { background:#f1f1f1; }
    .sugerencias .disabled, .sugerencias .disabled * { cursor:not-allowed!important; opacity:.9; }
    .total-destacado strong { font-size: 1.8rem; font-weight: 800; }
    .table td, .table th { vertical-align: middle; }
    .badge-stock { font-weight: 600; }
    .total-destacado { font-weight: 700; }
    .w-70px { width: 70px; }
    .pos-layout { display: block; }
    @media (min-width: 992px) {
      .pos-layout { display: flex; align-items: flex-start; gap: 1rem; }
      .pos-left  { flex: 1 1 auto; min-width: 0; }
      .pos-right { flex: 0 0 700px; max-width: 700px; }
    }
    .carrito-scroll { max-height: 300px; overflow-y: auto; border: 1px solid rgba(0,0,0,.075); border-radius: .25rem; }
    .carrito-scroll table { margin-bottom: 0; }
    .carrito-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; }
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

  <div class="pos-layout">
    <!-- Productos -->
    <div class="pos-left">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Buscar productos</h5>
        </div>

        <div class="card-body">
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
    </div>

    <!-- Orden -->
    <div class="pos-right">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Orden actual</h5>
          <span class="badge bg-secondary text-white" style="padding:6px 16px;border-radius:20px;min-width:82px;text-align:center;"
                title="Folio sugerido (aún no asignado)">
            #<span id="codigoOrden">—</span>
          </span>
        </div>

        <div class="card-body">
          <div class="mb-2">
            <label class="form-label" for="selCliente">Cliente</label>
            <select id="selCliente" class="form-control">
              <option value="">Cargando clientes…</option>
            </select>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-12 col-sm-6">
              <label class="form-label" for="tpPrecio">Tipo de precio</label>
              <select id="tpPrecio" class="form-control">
                <option value="publico">Mostrador (Público)</option>
                <option value="taller">Taller</option>
                <option value="proveedor">Proveedor</option>
              </select>
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label" for="fechaVenta">Fecha venta</label>
              <input type="date" id="fechaVenta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
          </div>

          <!-- Carrito -->
          <div id="wrapCarritoVacio" class="text-muted text-center py-4">
            No hay productos en la orden.
          </div>
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

          <div class="f-s-14">
            <div class="d-flex justify-content-between mt-2 total-destacado">
              <strong style="font-size: 1.6rem;">Total</strong>
              <strong id="resTotal" style="font-size: 1.6rem;">$0.00</strong>
            </div>
          </div>

          <!-- Forma de pago dinámica -->
          <div class="mt-3">
            <label class="form-label" for="selFormaPago">Forma de pago</label>
            <select id="selFormaPago" class="form-control form-select">
              <option value="">Cargando…</option>
            </select>
          </div>

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

  <!-- Footer fijo -->
  <nav class="navbar navbar-light bg-white border-top fixed-bottom">
    <div class="container-fluid justify-content-between">
      <span class="small text-muted"><?= date('Y') ?> &copy; Sistema desarrollado por <a href="javascript:void(0);">HMODevelopers</a></span>
      <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/views/private/ventas/index.php" class="btn btn-sm btn-outline-primary">← Admin</a>
      </div>
    </div>
  </nav>

  <!-- 🔒 Hidden: último id_venta para reimpresión -->
  <input type="hidden" id="tk-idventa" value="">
</div>

<script>const BASE_URL = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(() => {
  const BASE = BASE_URL;
  const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
  const fix2 = v => (Number(v||0)).toFixed(2);
  const num  = v => parseFloat(v ?? 0) || 0;

  // ===== estado =====
  let carrito = [];
  let idxFocus = -1;
  let ultResultados = [];
  let debTimer = null;
  const detalleCache = new Map();
  let totalActual = 0;

  // ===== utils =====
  const normalize = s => (s||'').toString().toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();

  function formaPagoSlug(){
    const txt = $('#selFormaPago option:selected').text()?.trim() || '';
    const t = normalize(txt);
    if (t.includes('efectivo')) return 'efectivo';
    if (t.includes('mixto') || t.includes('mixta')) return 'mixto';
    if (t.includes('tarjeta') || t.includes('debito') || t.includes('credito')) return 'tarjeta';
    return 'tarjeta';
  }

  // ===== precios / stock =====
  function tipoPrecioActual() { return ($('#tpPrecio').val() || 'publico'); }

  // respeta override de unitario si existe
  function precioDeItem(it){
    if (typeof it.override_unit === 'number' && !isNaN(it.override_unit)) {
      return Number(it.override_unit);
    }
    const t = tipoPrecioActual();
    if (t === 'taller')     return Number(it.precio_taller||0);
    if (t === 'proveedor')  return Number(it.precio_proveedor||0);
    return Number(it.precio_publico||0);
  }

  const vendibleDe = det => Math.max(0, num(det.stock_actual ?? det.existencia) - num(det.stock_minimo));
  function maxVendible(it){
    const stock = Number(it.stock_actual ?? 0);
    const smin  = Number(it.stock_minimo ?? 0);
    return Math.max(0, stock - smin);
  }

  // ===== folio sugerido =====
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
  function mapTipoPrecioId(slug){ const m = { publico:1, taller:2, proveedor:3 }; return m[slug] || 1; }

  // ===== clientes (USANDO TU CONTROLLER) =====
  function setClientesOptions(arr){
    const sel = $('#selCliente').empty();
    sel.append(`<option value="">--Seleccione Opción--</option>`);
    (arr||[]).forEach(c=>{
      // Tu listar-min devuelve {id_cliente, nombre}
      const id = c.id_cliente;
      const nombre = c.nombre
        ?? c.razon_social
        ?? c.nombre_comercial
        ?? 'Cliente';
      if(id!=null && id!=='') sel.append(`<option value="${id}">${nombre}</option>`);
    });
  }

  function cargarClientes(){
    const LIM = 200;

    // 1) rápido: listar-min (POST o GET)
    $.post(`${BASE}/controllers/ClientesController.php`, {accion:'listar-min', limite: LIM})
      .done(r=>{
        const data = r?.data || (Array.isArray(r) ? r : []);
        if (Array.isArray(data) && data.length) {
          setClientesOptions(data);
        } else {
          // 2) fallback: listar paginado (más completo)
          $.post(`${BASE}/controllers/ClientesController.php`, {accion:'listar', pagina:1, limite:LIM})
            .done(r2=>{
              const data2 = r2?.data || [];
              setClientesOptions(data2);
              if (!data2.length) toastr.info('No hay clientes activos. Usando “Mostrador / Público general”.');
            })
            .fail(()=> {
              setClientesOptions([]);
              toastr.error('No se pudieron cargar clientes (listar).');
            });
        }
      })
      .fail(()=>{
        // 2) fallback si listar-min falla
        $.post(`${BASE}/controllers/ClientesController.php`, {accion:'listar', pagina:1, limite:LIM})
          .done(r2=>{
            const data2 = r2?.data || [];
            setClientesOptions(data2);
            if (!data2.length) toastr.info('No hay clientes activos. Usando “Mostrador / Público general”.');
          })
          .fail(()=>{
            setClientesOptions([]);
            toastr.error('No se pudieron cargar clientes.');
          });
      });
  }

  // ===== formas de pago =====
  function cargarFormasPago(){
    $.get(`${BASE}/controllers/FormasPagoController.php`, {accion:'listar_select'})
      .done(r=>{
        const sel = $('#selFormaPago').empty();
        const arr = r?.data || (Array.isArray(r) ? r : []);
        if (!arr.length) return sel.append(`<option value="">(sin formas de pago)</option>`);
        let idxDefault = 0;
        arr.forEach((fp, i)=>{
          sel.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`);
          if (normalize(fp.descripcion).includes('efectivo')) idxDefault = i;
        });
        sel.prop('selectedIndex', idxDefault);
      })
      .fail(()=>{
        const sel = $('#selFormaPago').empty();
        sel.append('<option value="1">Efectivo</option><option value="2">Tarjeta</option><option value="3">Mixto</option>');
      });
  }

  // ===== autocomplete productos =====
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
    const pub = Number(det.precio_publico ?? 0);
    const tal = Number(det.precio_taller ?? 0);
    const stk = Number(det.stock_actual ?? det.existencia ?? 0);
    const prov = det.proveedor ?? '';
    const extra = `<span>Taller: ${mxn(tal)}</span> · <span>Público: ${mxn(pub)}</span> · <span>Exist: ${fix2(stk)}</span>${prov?` · <span>Prov: ${prov}</span>`:''}`;
    return { extra, sinStock: (vendibleDe(det) <= 0) };
  }
  function renderSugerencias(list){
    ultResultados = (list||[]).map((p,i)=>({...p,__i:i}));
    idxFocus = -1; $panel.empty();
    if(!ultResultados.length) return $panel.addClass('d-none');
    ultResultados.forEach(p=>$panel.append(sugHTMLBasico(p)));
    $panel.removeClass('d-none');

    // pinta cache si existe y refresca en background
    ultResultados.forEach(p=>{
      const id=p.id_producto, $row=$panel.find(`[data-id="${id}"]`);

      if(detalleCache.has(id)){
        const det=detalleCache.get(id);
        const {extra,sinStock}=sugHTMLDetallado(det);
        $row.find('[data-slot="extra"]').html(extra);
        $row.toggleClass('disabled', sinStock).attr('aria-disabled', sinStock ? 'true' : null);
      }

      $.post(`${BASE}/controllers/ProductosController.php`,{accion:'detalle', id_producto:id})
       .done(r=>{
         const det=r?.data||{};
         detalleCache.set(id,det);
         const {extra,sinStock}=sugHTMLDetallado(det);
         $row.find('[data-slot="extra"]').html(extra);
         $row.toggleClass('disabled', sinStock).attr('aria-disabled', sinStock ? 'true' : null);
       });
    });
  }
  function buscar(q){
    if(!q||q.length<2){ $panel.addClass('d-none').empty(); return; }
    $.post(`${BASE}/controllers/ProductosController.php`,{accion:'buscar-min', q, limite:20})
     .done(r=>renderSugerencias(r?.data||[]))
     .fail(()=> $panel.addClass('d-none').empty());
  }

  // SIEMPRE consulta detalle fresco antes de agregar
  function seleccionarPorId(idProd){
    $.post(`${BASE}/controllers/ProductosController.php`, {accion:'detalle', id_producto:idProd})
     .done(r=>{
       const det = r?.data;
       if(!det){ toastr.error('No se encontró el detalle del producto'); return; }

       const vendible = vendibleDe(det);
       if(vendible <= 0){
         const stk = num(det.stock_actual ?? det.existencia);
         const smin= num(det.stock_minimo);
         toastr.warning(`Sin stock suficiente para vender. (Exist: ${fix2(stk)} · Mín: ${fix2(smin)})`);
         return;
       }

       detalleCache.set(idProd, det);
       agregarDesdeDetalle(det);
       $input.val(''); $panel.addClass('d-none').empty();
     })
     .fail(()=> toastr.error('No se pudo obtener el detalle del producto'));
  }

  $input.on('input',function(){ debounce(()=>buscar(this.value.trim()),220); });
  $panel.on('click','.list-group-item',function(e){
    e.preventDefault(); if($(this).hasClass('disabled')) return;
    seleccionarPorId(Number($(this).data('id')));
  });
  $(document).on('click',e=>{ if(!$(e.target).closest('#txtBuscar,#panelSug').length){ $panel.addClass('d-none').empty(); } });

  // ===== carrito =====
  function agregarDesdeDetalle(p){
    const idx = carrito.findIndex(x=>x.id_producto==p.id_producto);
    const itemBase = {
      id_producto:p.id_producto, codigo:p.codigo, descripcion:p.descripcion,
      stock_actual:Number(p.stock_actual ?? p.existencia ?? 0),
      stock_minimo:Number(p.stock_minimo ?? 0),
      precio_publico:Number(p.precio_publico ?? 0),
      precio_taller:Number(p.precio_taller ?? 0),
      precio_proveedor:Number(p.precio_proveedor ?? 0),
      proveedor:p.proveedor ?? null
    };
    const vendible=maxVendible(itemBase);
    if(vendible<=0){ toastr.warning('Sin stock disponible para vender.'); return; }
    if(idx>=0){
      const next = Math.min(vendible, Number(carrito[idx].cantidad)+1);
      carrito[idx].cantidad = next;
    } else {
      carrito.push({...itemBase, cantidad:1});
    }
    pintarCarrito();
  }

  function pintarCarrito(){
    const tb=$('#tablaCarrito tbody').empty();
    if(!carrito.length){
      $('#wrapCarritoVacio').removeClass('d-none');
      $('#wrapCarritoTabla').addClass('d-none');
      $('#resTotal').text('$0.00');
      totalActual=0;
      return;
    }
    $('#wrapCarritoVacio').addClass('d-none');
    $('#wrapCarritoTabla').removeClass('d-none');

    let total=0;
    carrito.forEach((it,idx)=>{
      const precio = precioDeItem(it);
      const cantidad = Number(it.cantidad) || 0;
      const subtotal = cantidad * precio;
      total += subtotal;

      tb.append(`
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
          <td class="text-center">
            <div class="btn-group btn-group-sm" role="group">
              <button class="btn btn-outline-danger" data-dec="${idx}"><i class="mdi mdi-minus"></i></button>
              <input type="number" min="1" step="1" class="form-control form-control-sm text-center w-70px" value="${fix2(it.cantidad)}" data-qty="${idx}">
              <button class="btn btn-outline-success" data-inc="${idx}"><i class="mdi mdi-plus"></i></button>
            </div>
          </td>
          <td class="text-end">
            <input type="number" min="0" step="0.01"
                   class="form-control form-control-sm text-end"
                   value="${fix2(subtotal)}" data-sub="${idx}"
                   title="Editar subtotal (ajusta el precio unitario automáticamente)">
          </td>
          <td class="text-end"><button class="btn btn-sm btn-outline-danger" data-del="${idx}"><i class="mdi mdi-delete"></i></button></td>
        </tr>`);
    });
    totalActual=total;
    $('#resTotal').text(mxn(total));
  }

  $('#tpPrecio').on('change',pintarCarrito);

  // Cantidad +/- / input
  $('#tablaCarrito').on('click','button[data-inc]',function(){
    const i=Number(this.dataset.inc);
    if(isNaN(i)||!carrito[i]) return;
    const vendible=maxVendible(carrito[i]);
    const next=Number(carrito[i].cantidad)+1;
    carrito[i].cantidad = next>vendible ? (toastr.info('Se alcanzó el máximo vendible.'), vendible) : next;
    pintarCarrito();
  });

  $('#tablaCarrito').on('click','button[data-dec]',function(){
    const i=Number(this.dataset.dec);
    if(isNaN(i)||!carrito[i]) return;
    carrito[i].cantidad=Math.max(1,Number(carrito[i].cantidad)-1);
    pintarCarrito();
  });

  $('#tablaCarrito').on('change','input[data-qty]',function(){
    const i=Number(this.dataset.qty);
    if(isNaN(i)||!carrito[i]) return;
    let val=Math.max(1, Number(this.value||1));
    const vendible=maxVendible(carrito[i]);
    if(val>vendible){ val=vendible; toastr.info('Se ajustó a máximo vendible.'); }
    carrito[i].cantidad=val;
    pintarCarrito();
  });

  $('#tablaCarrito').on('click','button[data-del]',function(){
    const i=Number(this.dataset.del);
    if(isNaN(i)) return;
    carrito.splice(i,1);
    pintarCarrito();
  });

  // Editar subtotal => recalcula unitario override
  $('#tablaCarrito').on('change','input[data-sub]', function(){
    const i = Number(this.dataset.sub);
    if (isNaN(i) || !carrito[i]) return;

    let sub = Number(this.value);
    if (isNaN(sub) || sub < 0) sub = 0;

    const qty = Math.max(1, Number(carrito[i].cantidad) || 1);
    const unit = sub / qty;

    carrito[i].override_unit = Number(unit.toFixed(2));
    pintarCarrito();
  });

  // ===== impresión (AJAX a utils/ticket_mike42.php) =====
  function imprimirTicketAjax(idVenta){
    if(!idVenta){ return; }
    $.get(`${BASE}/utils/ticket_mike42.php`, { id_venta: idVenta })
      .done(resp => { console.log("Impresión:", resp); })
      .fail(xhr => { console.error("Error al imprimir:", xhr.responseText || 'Error al imprimir'); });
  }

  // Reimpresión manual (si agregas un botón en algún lado)
  $(document).on('click', '#btnImprimirTicket', function () {
    const id = $('#tk-idventa').val();
    if(!id){ alert('No hay venta seleccionada'); return; }
    imprimirTicketAjax(Number(id));
  });

  // ===== AJAX helper =====
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

  // ===== registrar venta (Activa o Guardada) =====
  function registrarVenta({estatus='Activa', pagos={}} = {}){
    const slugPrecio = $('#tpPrecio').val();
    const clienteVal = $('#selCliente').val();
    const idCliente  = clienteVal ? Number(clienteVal) : null;

    const payload = {
      venta: {
        fecha: $('#fechaVenta').val(),       // el backend agrega hora de Hermosillo
        estatus,                             // 'Activa' o 'Guardada'
        id_cliente: idCliente,
        id_forma_pago: estatus==='Guardada' ? null : (Number($('#selFormaPago').val()) || null),
        id_tipo_precio: mapTipoPrecioId(slugPrecio),
        tipo_precio_slug: slugPrecio,
        ...pagos
      },
      detalles: carrito.map(it => {
        const unit = precioDeItem(it), cant = Number(it.cantidad);
        return { id_producto: it.id_producto, cantidad: cant, precio_unitario: unit, subtotal: cant*unit };
      })
    };

    postVenta(payload, (r)=>{
      if(!r?.ok) return Swal.fire({icon:'error', title:'No se pudo registrar', text:(r?.msg||'Intenta de nuevo')});

      // guarda id_venta para reimpresión
      $('#tk-idventa').val(r.id_venta || '');

      if(estatus==='Guardada'){
        Swal.fire({icon:'success', title:'Venta guardada', html:`<p>Folio: <b>${r.folio}</b></p>`});
      } else {
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

      // limpiar y pedir siguiente folio
      carrito=[]; pintarCarrito(); $('#selCliente').val(''); $('#tpPrecio').val('publico'); pintarFolioSugerido();
    });
  }

  // ===== flujo de cobro (Activa) =====
  function flujoCobro(){
    if(!carrito.length){ toastr.warning('Agrega productos a la orden'); return; }
    const total = totalActual;
    const fpSlug = formaPagoSlug();

    if(fpSlug === 'efectivo'){
      Swal.fire({
        title: 'Cobro en efectivo', html: `<p>Total a pagar: <b>${mxn(total)}</b></p>`,
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

    // tarjeta / genérico
    Swal.fire({
      title:'Cobro con tarjeta', html:`<p>Total a pagar: <b>${mxn(total)}</b></p>`,
      icon:'question', showCancelButton:true, confirmButtonText:'Confirmar cobro'
    }).then(res=>{ if(res.isConfirmed){ registrarVenta({estatus:'Activa', pagos:{ tipo:'tarjeta' }}); }});
  }

  // ===== botones =====
  $('#btnCobrar').on('click', flujoCobro);
  $('#btnGuardar').on('click', ()=>{
    if(!carrito.length) return toastr.warning('Agrega productos a la orden');
    Swal.fire({
      icon:'question', title:'Guardar venta',
      text:'Se reservará inventario pero NO contará para el corte hasta que la cobres. ¿Continuar?',
      showCancelButton:true, confirmButtonText:'Guardar'
    }).then(res=>{ if(res.isConfirmed){ registrarVenta({estatus:'Guardada'}); }});
  });

  // Cancelar (limpia)
  $('#btnCancelar').on('click', ()=>{
    carrito=[]; pintarCarrito(); $('#selCliente').val(''); $('#txtBuscar').val('');
    $('#fechaVenta').val('<?= date('Y-m-d') ?>'); $('#tpPrecio').val('publico'); pintarFolioSugerido();
  });

  // init
  cargarClientes();        // <-- ahora usa tu ClientesController (listar-min / listar)
  cargarFormasPago();
  pintarFolioSugerido();
  $('#fechaVenta').on('change', pintarFolioSugerido);
})();
</script>
</body>
</html>
