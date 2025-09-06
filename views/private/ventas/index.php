<?php
$titulo = "Ventas";
$modulo = "Gestionar Ventas";
$subtitulo = "";
session_start();

// Incluye la configuración con BASE_URL
require_once __DIR__ . '/../../../includes/config.php';

// Verifica si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/views/public/index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <title>Ventas | REFASOFT-V4</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Core CSS -->
    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/ticket.css" rel="stylesheet" />

    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

    <style>
      /* === sugerencias dentro del modal de edición (por encima del modal) === */
      .ed-search-wrap { position: relative; }
      .ed-sug-panel {
        position:absolute; left:0; right:0; top:100%;
        z-index: 3000;             /* por encima del modal bootstrap */
        max-height: 320px; overflow:auto; display:none;
        box-shadow: 0 8px 22px rgba(0,0,0,.15);
      }
      .ed-sug-panel .list-group-item { cursor:pointer; }
      .ed-sug-panel .list-group-item.disabled,
      .ed-sug-panel .list-group-item.disabled * { cursor:not-allowed!important; opacity:1; }

      /* Tabla carrito del editor (mismo estilo que caja) */
      #ed-tabla .table td, #ed-tabla .table th { vertical-align: middle; }
      .badge-stock { font-weight: 600; }
      .w-70px { width: 70px; }

      .text-right{ text-align:right!important; }
      .text-center{ text-align:center!important; }
    </style>
  </head>

  <body>
    <!-- Topbar -->
    <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

    <div class="wrapper">
      <!-- Loader -->
      <div class="wrapper-loader fade" id="LoadingImage" style="display:none;">
        <div class="loader">
          <div class="loader__figure"></div>
          <p class="loader__label">Cargando...</p>
        </div>
      </div>

      <div class="container-fluid">
        <!-- Breadcrumb -->
        <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>
        <!-- /Breadcrumb -->

        <!-- Filtros -->
        <div class="card-header" style="border-color:darkgray; border-style:dotted;">
          <h5>Filtros</h5>
          <div class="row">
            <div class="col-lg-12">
              <div class="row">
                <!-- Folio -->
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="Folio" class="control-label">Folio</label>
                    <div class="input-group">
                      <input type="text" id="Folio" class="form-control filtrar" autocomplete="off">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Folio')"></i></span>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Fecha -->
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="Fecha" class="control-label">Fecha</label>
                    <div class="input-group">
                      <input type="date" id="Fecha" class="form-control filtrar" value="<?php echo date('Y-m-d'); ?>">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Fecha')"></i></span>
                      </div>
                    </div>
                  </div>
                </div>
              </div><!-- /row -->
            </div>
          </div>
        </div>
        <!-- /Filtros -->

        <!-- Tabla Ventas -->
        <div class="row">
          <div class="col-12">
            <div class="card-box">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="header-title">Listado de Ventas</h4>
              </div>

              <div class="table-responsive">
                <table id="tablaVentas" class="table table-bordered table-hover table-striped">
                  <thead>
                    <tr>
                      <th>Folio</th>
                      <th>Cajero</th>
                      <th>Caja</th>
                      <th>Forma de Pago</th>
                      <th>Tipo de Precio</th>
                      <th class="text-end">Total</th>
                      <th>Estatus</th>
                      <th>Cliente</th>
                      <th>Fecha</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="row align-items-center justify-content-between mt-2">
                <div class="col-md-6">
                  <div id="infoVentas" class="dataTables_info"></div>
                </div>
                <div class="col-md-6 d-flex justify-content-end">
                  <nav aria-label="Page navigation">
                    <ul id="pagination" class="pagination justify-content-end mb-0"></ul>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- /Tabla Ventas -->

        <!-- Modales ya existentes -->
        <?php include_once __DIR__ . '/../ventas/modales/detalle.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/ticket.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/eliminar.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/editar.php'; ?>

        <!-- Modal Activar Venta Guardada -->
        <div class="modal fade" id="modalActivarVenta" tabindex="-1" role="dialog" aria-labelledby="lblActivarVenta" aria-hidden="true">
          <div class="modal-dialog modal-md modal-dialog-scrollable" role="document">
            <div class="modal-content">
              <div class="modal-header py-2">
                <h5 class="modal-title">Activar venta <span class="text-muted">Folio</span> <b id="ac-folio">—</b></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
              </div>
              <div class="modal-body">
                <input type="hidden" id="ac-id-venta" value="">
                <div class="form-group">
                  <label for="ac-selFormaPago">Forma de pago</label>
                  <select id="ac-selFormaPago" class="form-control">
                    <option value="">Cargando…</option>
                  </select>
                  <small class="text-muted">Se requiere una forma de pago para contabilizarla en el corte.</small>
                </div>
                <div class="form-group form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="ac-fechaAhora" checked>
                  <label class="form-check-label" for="ac-fechaAhora">
                    Usar fecha y hora actual al activar
                  </label>
                </div>
                <div id="ac-error" class="alert alert-danger d-none mt-2"></div>
              </div>
              <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarActivar">
                  <i class="mdi mdi-check-circle-outline"></i> Activar
                </button>
              </div>
            </div>
          </div>
        </div>
        <!-- /Modal Activar -->

      </div> <!-- /container-fluid -->
    </div> <!-- /wrapper -->

    <!-- Footer -->
    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
    <div class="rightbar-overlay"></div>

    <!-- Core JS -->
    <script>const BASE_URL='<?= BASE_URL ?>';</script>
    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
    // helper para íconos "limpiar"
    function clearField(id){ try { $('#'+id).val('').trigger('change'); } catch(e){} }

    $(function(){
      const BASE = BASE_URL;
      const VENTAS_URL     = `${BASE}/controllers/VentasController.php`;
      const PRODUCTOS_URL  = `${BASE}/controllers/ProductosController.php`;
      const CLIENTES_URL   = `${BASE}/controllers/ClientesController.php`;
      const FORMASPAGO_URL = `${BASE}/controllers/FormasPagoController.php`;

      const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
      const fix2 = v => (Number(v||0)).toFixed(2);
      const num  = v => parseFloat(v ?? 0) || 0;
      const norm = s => (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
      const fechaMx = dt => { try{ const d=new Date(dt); return d.toLocaleString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:true}); } catch { return dt||'—'; } };

      // =================== LISTADO ===================
      let paginaActual=1; const limitePorPagina=10;

      function getBadge(estatus){
        switch(estatus){
          case 'Activa': return '<span class="badge badge-light-success badge-pill">Activa</span>';
          case 'Cancelada': return '<span class="badge badge-light-danger badge-pill">Cancelada</span>';
          case 'Devuelta': return '<span class="badge badge-light-warning badge-pill">Devuelta</span>';
          case 'Guardada': return '<span class="badge badge-light-primary badge-pill">Guardada</span>';
          default: return `<span class="badge badge-light-secondary badge-pill">${estatus||'—'}</span>`;
        }
      }
      function accionesVenta(v){
        let out = `
          <a class="dropdown-item accion-ver-detalle" href="#" data-toggle="modal" data-target="#modalDetalle" data-id="${v.id_venta}">
            <i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver Detalle
          </a>`;
        if (v.estatus==='Activa' || v.estatus==='Guardada'){
          out += `
            <a class="dropdown-item" href="javascript:void(0);" onclick="abrirTicket(${v.id_venta});">
              <i class="mdi mdi-printer mr-2 text-muted font-18 vertical-middle"></i>Ticket / Imprimir
            </a>
            <a class="dropdown-item" href="javascript:void(0);" onclick="abrirEditarVenta(${v.id_venta});">
              <i class="mdi mdi-pencil mr-2 text-muted font-18 vertical-middle"></i>Editar
            </a>`;
        }
        // Botón Activar SOLO si está Guardada
        if (v.estatus==='Guardada'){
          out += `
            <a class="dropdown-item accion-activar" href="#" data-id="${v.id_venta}" data-folio="${v.folio}">
              <i class="mdi mdi-check-circle mr-2 text-muted font-18 vertical-middle"></i>Activar (contabilizar)
            </a>`;
        }
        if (v.estatus==='Activa'){
          out += `
            <a class="dropdown-item accion-eliminar" href="#" data-id="${v.id_venta}" data-folio="${v.folio}">
              <i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Cancelar
            </a>`;
        }
        return out;
      }

      function cargarVentas(pagina){
        const folio = $('#Folio').val() || '';
        const fecha = $('#Fecha').val() || new Date().toISOString().split('T')[0];

        $.post(VENTAS_URL,{accion:'listar', pagina, limite:limitePorPagina, folio, fecha}, function(resp){
          const ventas = resp?.data || [];
          const total  = parseInt(resp?.total||0,10);
          let tbody='';

          if (!ventas.length){
            tbody = '<tr><td colspan="10" class="text-center">No hay ventas disponibles</td></tr>';
          } else {
            ventas.forEach(v=>{
              tbody += `
                <tr>
                  <td class="text-center"><b>${v.folio}</b></td>
                  <td class="text-center">${v.usuario}</td>
                  <td class="text-center">${v.caja}</td>
                  <td class="text-center">${v.forma_pago}</td>
                  <td class="text-center">${v.tipo_precio}</td>
                  <td class="text-center"><b>${mxn(v.total)}</b></td>
                  <td class="text-center">${getBadge(v.estatus)}</td>
                  <td class="text-center">${v.cliente || 'Público en general'}</td>
                  <td class="text-center">${fechaMx(v.fecha)}</td>
                  <td class="text-center">
                    <div class="btn-group dropdown">
                      <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown">
                        <i class="mdi mdi-dots-horizontal"></i>
                      </a>
                      <div class="dropdown-menu dropdown-menu-right">
                        ${accionesVenta(v)}
                      </div>
                    </div>
                  </td>
                </tr>`;
            });
          }
          $('#tablaVentas tbody').html(tbody);

          // paginación
          configurarPaginacion(pagina, total, limitePorPagina);
          const desde=(pagina-1)*limitePorPagina+1, hasta=Math.min(pagina*limitePorPagina,total);
          $('#infoVentas').text(`Mostrando ${total===0?0:desde} a ${hasta} de ${total} ventas`);
        },'json');
      }
      function configurarPaginacion(currentPage,totalItems,itemsPerPage){
        const totalPages=Math.max(1,Math.ceil(totalItems/itemsPerPage));
        const $ul=$('#pagination').empty();
        const maxVisible=5;
        if (totalPages<=1){ $ul.closest('nav').hide(); return; } else { $ul.closest('nav').show(); }
        let start=Math.max(1,currentPage-Math.floor(maxVisible/2));
        let end=Math.min(totalPages,start+maxVisible-1);
        if (end-start+1<maxVisible) start=Math.max(1,end-maxVisible+1);
        if (currentPage>1){
          $ul.append(`<li class="page-item"><a class="page-link" data-page="1">Primera</a></li>`);
          $ul.append(`<li class="page-item"><a class="page-link" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);
        }
        for(let i=start;i<=end;i++){
          $ul.append(`<li class="page-item ${i===currentPage?'active':''}"><a class="page-link" data-page="${i}">${i}</a></li>`);
        }
        if (currentPage<totalPages){
          $ul.append(`<li class="page-item"><a class="page-link" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);
          $ul.append(`<li class="page-item"><a class="page-link" data-page="${totalPages}">Última</a></li>`);
        }
      }
      $('#pagination').on('click','a.page-link',function(e){
        e.preventDefault(); const p=Number($(this).data('page'));
        if (Number.isFinite(p)){ paginaActual=p; cargarVentas(paginaActual); }
      });

      // Filtros
      $(".filtrar")
        .on('change keyup', function(){
          const $el=$(this);
          if ($el.val().length>0) $el.siblings(".clean-filter").css({display:"flex"});
          else $el.siblings(".clean-filter").css({display:"none"});
        })
        .on('change', ()=> setTimeout(()=>cargarVentas(1),200))
        .on('keypress', e=>{ if(e.charCode===13) cargarVentas(1); });

      $(".clean-filter").click(function(){
        const $el=$(this).parent().find(".filtrar"); $el.val("").trigger("change");
        if ($el.hasClass("select2")) $el.select2("val", 0);
        cargarVentas(1);
      });

      // =================== DETALLE ===================
      $(document).on('click','a.accion-ver-detalle',function(e){
        e.preventDefault();
        const id=$(this).data('id'); if(!id) return;
        $('#det-error').hide(); $('#det-contenido').hide(); $('#det-loader').show();
        $('#modalDetalle').modal('show');

        $.get(VENTAS_URL,{accion:'detalle',id_venta:id},function(resp){
          if(!resp||!resp.venta){ $('#det-loader').hide(); $('#det-error').show().text('No se encontró la venta.'); return; }
          const v=resp.venta, dets=resp.detalles||[];
          $('#det-folio').text(v.folio||'—');
          $('#det-fecha').text(fechaMx(v.fecha));
          $('#det-estatus').html(getBadge(v.estatus||'—'));
          $('#det-cliente').text(v.cliente||'Público en general');
          $('#det-usuario').text(v.usuario||'—');
          $('#det-caja').text(v.caja||'—');
          $('#det-forma').text(v.forma_pago||'—');
          $('#det-tipo').text(v.tipo_precio||'—');

          let tb='', total=0;
          if (!dets.length){
            tb='<tr><td colspan="5" class="text-center text-muted">Sin productos</td></tr>';
          } else {
            dets.forEach(d=>{
              const c=Number(d.cantidad||0), u=Number(d.precio_unitario||0), s=Number(d.subtotal ?? c*u);
              total+=s;
              tb += `<tr>
                      <td>${d.codigo || ('#'+(d.id_producto||''))}</td>
                      <td>${d.producto || ('#'+(d.id_producto||''))}</td>
                      <td class="text-center">${c}</td>
                      <td class="text-right">${mxn(u)}</td>
                      <td class="text-right">${mxn(s)}</td>
                    </tr>`;
            });
          }
          $('#det-tbody').html(tb);
          $('#det-total').text(mxn(total||v.total||0));
          $('#det-loader').hide(); $('#det-contenido').show();
        },'json').fail(()=>{ $('#det-loader').hide(); $('#det-error').show().text('Error al cargar el detalle.'); });
      });

      // =================== TICKET ===================
      function renderTkItem({ cantidad, articulo, precio_unitario, subtotal, descripcion }){
        const cant=(Number(cantidad||0)).toFixed(2), art=(articulo||''), precio=mxn(precio_unitario), total=mxn(subtotal);
        const row1=`<div class="tk-item">
                      <div class="c-cant">${cant}</div><div class="c-art">${art}</div>
                      <div class="c-precio">${precio}</div><div class="c-total">${total}</div>
                    </div>`;
        const row2 = descripcion ? `<div style="margin-left:50px;font-size:11px;white-space:normal;overflow-wrap:anywhere;">${descripcion}</div>` : '';
        return row1+row2;
      }
      window.abrirTicket = function(idVenta){
        $('#tk-items').empty(); $('#tk-folio').text('—'); $('#tk-fecha').text('—'); $('#tk-total').text('$0.00'); $('#tk-idventa').val(idVenta);
        $.get(VENTAS_URL,{accion:'detalle',id_venta:idVenta},function(resp){
          if(!resp||!resp.venta){ alert('No se encontró la venta.'); return; }
          const v=resp.venta||{}, det=resp.detalles||[];
          $('#tk-folio').text(v.folio||'—'); $('#tk-fecha').text(fechaMx(v.fecha));
          let html='', total=0;
          det.forEach(d=>{
            const c=Number(d.cantidad||0), u=Number(d.precio_unitario||0), s=Number(d.subtotal??(c*u));
            total+=s;
            html += renderTkItem({cantidad:c, articulo:d.producto||d.clave||d.codigo||'', precio_unitario:u, subtotal:s, descripcion:d.descripcion||d.nombre||''});
          });
          $('#tk-items').html(html);
          $('#tk-total').text(mxn(v.total!=null ? v.total : total));
          $('#modalTicket').modal('show');
        },'json').fail(()=> alert('Error al cargar el ticket.'));
      };
      $(document).on('click','#btnImprimirTicket',function(){
        const id=$('#tk-idventa').val(); if(!id){ alert('No hay venta seleccionada'); return; }
        $.get(`${BASE}/utils/ticket_mike42.php`,{id_venta:id}).done(r=>console.log('Impresión:',r)).fail(xhr=>console.error('Error al imprimir:',xhr.responseText||'Error'));
      });

      // =================== ELIMINAR ===================
      $(document).on('click','a.accion-eliminar',function(e){
        e.preventDefault();
        const id=$(this).data('id'), folio=$(this).data('folio'); if(!id) return;
        $('#el-id-venta').val(id); $('#el-folio').text(folio); $('#modalEliminar').modal('show');
      });
      $(document).off('click','#btnConfirmarEliminar').on('click','#btnConfirmarEliminar',function(){
        const id=$('#el-id-venta').val(); if(!id) return;
        const $b=$(this), txt=$b.html();
        $b.prop('disabled',true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Eliminando...');
        $.post(VENTAS_URL,{accion:'eliminar', id_venta:id},function(r){
          if (r&&(r.ok===true||r.resultado==='ok')){ toastr.success(r?.msg||'Venta eliminada con éxito'); cargarVentas(paginaActual); }
          else { toastr.error(r?.msg||r?.resultado||'Error al eliminar.'); }
        },'json').fail(()=> toastr.error('No se pudo conectar con el servidor.'))
        .always(()=>{ $('#modalEliminar').modal('hide'); $b.prop('disabled',false).html(txt); });
      });

      // =================== ACTIVAR (Guardada → Activa) ===================
      // abrir modal
      $(document).on('click','a.accion-activar', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        const folio = $(this).data('folio') || '—';
        if (!id) return;

        $('#ac-id-venta').val(id);
        $('#ac-folio').text(folio);
        $('#ac-error').addClass('d-none').empty();
        $('#ac-fechaAhora').prop('checked', true);

        // cargar formas de pago
        $.get(FORMASPAGO_URL, {accion:'listar_select'}).done(r=>{
          const arr = r?.data || (Array.isArray(r)?r:[]);
          const $sel = $('#ac-selFormaPago').empty();
          if (!arr.length){
            $sel.append(`<option value="">(sin formas de pago)</option>`);
          } else {
            arr.forEach(fp => $sel.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`));
            // seleccionar efectivo si existe
            const $ef = $sel.find('option').filter(function(){ return $(this).text().toLowerCase().includes('efectivo'); });
            if ($ef.length) $ef.prop('selected', true);
          }
          $('#modalActivarVenta').modal('show');
        }).fail(()=>{
          const $sel = $('#ac-selFormaPago').empty();
          $sel.append('<option value="1">Efectivo</option><option value="2">Tarjeta</option><option value="3">Mixto</option>');
          $('#modalActivarVenta').modal('show');
        });
      });

      // confirmar activar
      $(document).off('click','#btnConfirmarActivar').on('click','#btnConfirmarActivar', function(){
        const idVenta = Number($('#ac-id-venta').val());
        const idForma = $('#ac-selFormaPago').val() ? Number($('#ac-selFormaPago').val()) : null;
        const fechaAhora = $('#ac-fechaAhora').is(':checked') ? 1 : 0;

        if (!idVenta){ return; }
        if (!idForma){
          $('#ac-error').removeClass('d-none').text('Selecciona una forma de pago.');
          return;
        }

        const $btn = $(this);
        const html = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Activando...');

        $.post(VENTAS_URL, { accion:'activar-guardada', id_venta:idVenta, id_forma_pago:idForma, actualizar_fecha:fechaAhora }, function(r){
          if (r && r.ok){
            toastr.success(r.msg || 'Venta activada.');
            $('#modalActivarVenta').modal('hide');
            cargarVentas(paginaActual);
          } else {
            $('#ac-error').removeClass('d-none').text(r?.msg || 'No se pudo activar la venta.');
          }
        }, 'json').fail(()=>{
          $('#ac-error').removeClass('d-none').text('Error de comunicación con el servidor.');
        }).always(()=>{
          $btn.prop('disabled', false).html(html);
        });
      });

      // =================== EDITAR (estilo CAJA) ===================
      const $modalEd = $('#modalEditarVenta');
      const $edFolio = $('#ed-folio');
      const $edEst   = $('#ed-estatus');
      const $edFechaInfo = $('#ed-fecha');
      const $edUsrCaja   = $('#ed-usr-caja');

      // NOTA: estos inputs deben existir en tu editar.php
      const $selCliente = $('#ed-selCliente');
      const $selForma   = $('#ed-selFormaPago');
      const $tpPrecio   = $('#ed-tpPrecio');        // slugs: publico, taller, proveedor
      const $fechaVenta = $('#ed-fechaVenta');

      const $buscar = $('#ed-buscar');
      const $sug    = $('#ed-sug');

      const $wrapEmpty = $('#ed-wrapCarritoVacio');
      const $wrapTable = $('#ed-wrapCarritoTabla');
      const $tbody     = $('#ed-tbody');
      const $totalEd   = $('#ed-total');
      const $btnSave   = $('#btnGuardarEdicion');
      const $errEd     = $('#ed-error');

      let edVentaId = 0;
      let carrito   = [];              // items del editor, mismos campos que caja
      const detalleCache = new Map();
      let debTimer = null;

      function vendibleDe(det){ return Math.max(0, num(det.stock_actual ?? det.existencia) - num(det.stock_minimo)); }
      function maxVendible(it){ return Math.max(0, num(it.stock_actual) - num(it.stock_minimo)); }
      function mapTipoPrecioId(slug){ const m={publico:1, taller:2, proveedor:3}; return m[slug]||1; }
      function mapIdToSlug(id){ const n=Number(id||1); const m={1:'publico',2:'taller',3:'proveedor'}; return m[n]||'publico'; }

      function precioDeItem(it){
        if (typeof it.override_unit === 'number' && !isNaN(it.override_unit)) return Number(it.override_unit);
        const t = $tpPrecio.val() || 'publico';
        if (t==='taller') return Number(it.precio_taller||0);
        if (t==='proveedor') return Number(it.precio_proveedor||0);
        return Number(it.precio_publico||0);
      }

      function setClientesOptions(arr, selected){
        $selCliente.empty().append(`<option value="">-- Seleccionar Opción --</option>`);
        (arr||[]).forEach(c=>{
          const id=c.id_cliente;
          const nombre = c.nombre ?? c.razon_social ?? c.nombre_comercial ?? 'Cliente';
          if (id!=null && id!=='') $selCliente.append(`<option value="${id}">${nombre}</option>`);
        });
        if (selected!=null) $selCliente.val(String(selected));
      }
      function cargarClientes(selected){
        const LIM=200;
        $.post(`${CLIENTES_URL}`,{accion:'listar-min', limite:LIM})
          .done(r=>{
            const data=r?.data || (Array.isArray(r)?r:[]);
            if (data.length) setClientesOptions(data, selected);
            else {
              $.post(`${CLIENTES_URL}`,{accion:'listar', pagina:1, limite:LIM})
               .done(r2=> setClientesOptions(r2?.data||[], selected))
               .fail(()=> setClientesOptions([], selected));
            }
          }).fail(()=>{
            $.post(`${CLIENTES_URL}`,{accion:'listar', pagina:1, limite:LIM})
             .done(r2=> setClientesOptions(r2?.data||[], selected))
             .fail(()=> setClientesOptions([], selected));
          });
      }
      function cargarFormasPago(selected, fallbackText){
        $.get(`${FORMASPAGO_URL}`,{accion:'listar_select'})
          .done(r=>{
            const arr=r?.data || (Array.isArray(r)?r:[]);
            $selForma.empty();
            if (!arr.length){ $selForma.append(`<option value="">(sin formas de pago)</option>`); return; }
            arr.forEach(fp=> $selForma.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`));
            if (selected!=null) $selForma.val(String(selected));
            else if (fallbackText){
              $selForma.find('option').filter(function(){return $(this).text()===fallbackText;}).prop('selected',true);
            }
          })
          .fail(()=>{
            $selForma.empty().append('<option value="1">Efectivo</option><option value="2">Tarjeta</option><option value="3">Mixto</option>');
            if (selected!=null) $selForma.val(String(selected));
          });
      }

      // ===== buscar productos (como caja) =====
      function sugHTMLBasico(p){
        return `
          <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             data-id="${p.id_producto}">
            <div class="me-2" style="min-width:0">
              <div class="text-truncate"><strong>${p.codigo}</strong> — ${p.descripcion||p.nombre||''}</div>
              <div class="small text-muted" data-slot="extra">Cargando detalles…</div>
            </div>
            <i class="mdi mdi-plus-circle-outline"></i>
          </a>`;
      }
      function sugHTMLDetallado(det){
        const pub = Number(det.precio_publico ?? 0);
        const tal = Number(det.precio_taller ?? 0);
        const stk = Number(det.stock_actual ?? det.existencia ?? 0);
        const extra = `<span>Taller: ${mxn(tal)}</span> · <span>Público: ${mxn(pub)}</span> · <span>Exist: ${fix2(stk)}</span>`;
        return { extra, sinStock:(vendibleDe(det) <= 0) };
      }
      function renderSugerencias(arr){
        $sug.empty();
        if(!arr.length){ $sug.hide(); return; }
        arr.forEach(p=>$sug.append(sugHTMLBasico(p)));
        $sug.show();

        // pintar detalles y marcar sin stock
        $sug.find('a.list-group-item').each(function(){
          const id=Number($(this).data('id')), $row=$(this);
          if (detalleCache.has(id)){
            const det=detalleCache.get(id);
            const {extra,sinStock}=sugHTMLDetallado(det);
            $row.find('[data-slot="extra"]').html(extra);
            $row.toggleClass('disabled', sinStock).attr('aria-disabled', sinStock ? 'true' : null);
          }
          $.post(PRODUCTOS_URL,{accion:'detalle', id_producto:id})
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
        if(!q||q.length<2){ $sug.hide().empty(); return; }
        $.post(PRODUCTOS_URL,{accion:'buscar-min', q, limite:20})
         .done(r=>renderSugerencias(r?.data||[]))
         .fail(()=> $sug.hide().empty());
      }
      $buscar.on('input', function(){
        const val=this.value.trim(); clearTimeout(debTimer);
        debTimer = setTimeout(()=>buscar(val), 220);
      });
      $sug.on('click','.list-group-item',function(e){
        e.preventDefault(); if($(this).hasClass('disabled')) return;
        seleccionarPorId(Number($(this).data('id')));
      });
      $(document).on('click', e=>{ if(!$(e.target).closest('#ed-buscar,#ed-sug').length){ $sug.hide().empty(); } });

      function seleccionarPorId(idProd){
        $.post(PRODUCTOS_URL,{accion:'detalle', id_producto:idProd})
         .done(r=>{
           const det=r?.data;
           if(!det){ toastr.error('No se encontró el detalle del producto'); return; }
           const vendible=vendibleDe(det);
           if (vendible<=0){ toastr.warning('Sin stock disponible.'); return; }
           detalleCache.set(idProd, det);
           agregarDesdeDetalle(det);
           $buscar.val(''); $sug.hide().empty();
         })
         .fail(()=> toastr.error('No se pudo obtener el detalle del producto'));
      }

      // ===== carrito editor (mismo comportamiento que caja) =====
      function agregarDesdeDetalle(p, originalCant=0){
        const idx = carrito.findIndex(x=>x.id_producto==p.id_producto);
        const itemBase = {
          id_producto:p.id_producto, codigo:p.codigo, descripcion:p.descripcion,
          stock_actual:Number(p.stock_actual ?? p.existencia ?? 0),
          stock_minimo:Number(p.stock_minimo ?? 0),
          precio_publico:Number(p.precio_publico ?? 0),
          precio_taller:Number(p.precio_taller ?? 0),
          precio_proveedor:Number(p.precio_proveedor ?? 0),
          proveedor:p.proveedor ?? null,
          original: Number(originalCant||0)   // cantidad que ya tenía la venta
        };
        const vendible=maxVendible(itemBase);
        if(vendible<=0 && itemBase.original<=0){ toastr.warning('Sin stock disponible para vender.'); return; }
        if(idx>=0){
          const max = itemBase.original + vendible;
          const next= Math.min(max, Number(carrito[idx].cantidad)+1);
          carrito[idx].cantidad = next;
        } else {
          carrito.push({...itemBase, cantidad: Math.max(1, itemBase.original || 1)});
        }
        pintarCarrito();
      }

      function pintarCarrito(){
        const tb=$tbody.empty();
        if(!carrito.length){
          $wrapEmpty.removeClass('d-none');
          $wrapTable.addClass('d-none');
          $totalEd.text('$0.00');
          return;
        }
        $wrapEmpty.addClass('d-none');
        $wrapTable.removeClass('d-none');

        let total=0;
        carrito.forEach((it,idx)=>{
          const unit = precioDeItem(it);
          const cantidad = Number(it.cantidad) || 0;
          const subtotal = cantidad * unit;
          total += subtotal;
          const vendible = maxVendible(it);
          const max = Number(it.original||0) + vendible;

          tb.append(`
            <tr>
              <td>
                <div class="d-flex align-items-center">
                  <div>
                    <div class="fw-semibold">${it.descripcion}</div>
                    <div class="small text-muted">
                      Cod: ${it.codigo} ${it.proveedor?`· Prov: ${it.proveedor}`:``}
                      · Exist: <span class="badge ${Number(it.stock_actual)>0?'bg-success':'bg-secondary'} badge-stock">${fix2(it.stock_actual)}</span>
                      · Máx vendible: ${fix2(max)}
                    </div>
                  </div>
                </div>
              </td>
              <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                  <button class="btn btn-outline-danger" data-ed-dec="${idx}"><i class="mdi mdi-minus"></i></button>
                  <input type="number" min="1" step="1" class="form-control form-control-sm text-center w-70px" value="${fix2(it.cantidad)}" data-ed-qty="${idx}" data-max="${max}">
                  <button class="btn btn-outline-success" data-ed-inc="${idx}"><i class="mdi mdi-plus"></i></button>
                </div>
              </td>
              <td class="text-end">
                <input type="number" min="0" step="0.01"
                       class="form-control form-control-sm text-end"
                       value="${fix2(subtotal)}" data-ed-sub="${idx}"
                       title="Editar subtotal (ajusta el precio unitario automáticamente)">
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-danger" data-ed-del="${idx}"><i class="mdi mdi-delete"></i></button>
              </td>
            </tr>`);
        });
        $totalEd.text(mxn(total));
      }

      // Cantidad +/-
      $tbody.on('click','button[data-ed-inc]', function(){
        const i=Number(this.dataset.edInc); if(isNaN(i)||!carrito[i]) return;
        const vendible=maxVendible(carrito[i]);
        const max = Number(carrito[i].original||0) + vendible;
        const next=Number(carrito[i].cantidad)+1;
        carrito[i].cantidad = next>max ? (toastr.info('Se alcanzó el máximo vendible.'), max) : next;
        pintarCarrito();
      });
      $tbody.on('click','button[data-ed-dec]', function(){
        const i=Number(this.dataset.edDec); if(isNaN(i)||!carrito[i]) return;
        carrito[i].cantidad=Math.max(1,Number(carrito[i].cantidad)-1);
        pintarCarrito();
      });
      $tbody.on('change','input[data-ed-qty]', function(){
        const i=Number(this.dataset.edQty); if(isNaN(i)||!carrito[i]) return;
        let val=Math.max(1, Number(this.value||1));
        const max=Number(this.dataset.max||0);
        if (val>max){ val=max; toastr.info('Se ajustó a máximo vendible.'); }
        carrito[i].cantidad=val; pintarCarrito();
      });
      // Editar subtotal => recalcula unitario override
      $tbody.on('change','input[data-ed-sub]', function(){
        const i=Number(this.dataset.edSub); if(isNaN(i)||!carrito[i]) return;
        let sub=Number(this.value); if(isNaN(sub)||sub<0) sub=0;
        const qty=Math.max(1, Number(carrito[i].cantidad)||1);
        carrito[i].override_unit = Number((sub/qty).toFixed(2));
        pintarCarrito();
      });
      // Eliminar
      $tbody.on('click','button[data-ed-del]', function(){
        const i=Number(this.dataset.edDel); if(isNaN(i)) return;
        carrito.splice(i,1); pintarCarrito();
      });

      // Cambiar tipo de precio => recalcula unitarios salvo override
      $tpPrecio.on('change', pintarCarrito);

      // ===== Guardar edición =====
      $btnSave.on('click', function(){
        if (!edVentaId){ toastr.error('No hay venta cargada.'); return; }
        if (!carrito.length){ toastr.warning('Agrega productos a la orden'); return; }

        const id_tipo_precio = mapTipoPrecioId($tpPrecio.val());
        const venta = {
          id_venta: edVentaId,
          fecha: $('#ed-fechaVenta').val(),          // fecha editable
          id_cliente: $selCliente.val() ? Number($selCliente.val()) : null,
          id_forma_pago: $selForma.val() ? Number($selForma.val()) : null,
          id_tipo_precio: id_tipo_precio
        };
        const detalles = carrito.map(it=>{
          const unit = precioDeItem(it);
          const cant = Math.max(1, Number(it.cantidad)||1);
          return { id_producto: it.id_producto, cantidad: cant, precio_unitario: unit, subtotal: cant*unit };
        });

        const payload = { venta, detalles };

        const $b=$(this), txt=$b.html();
        $b.prop('disabled',true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...');
        $.ajax({
          url: `${VENTAS_URL}?accion=actualizar`,
          method: 'POST',
          contentType: 'application/json; charset=utf-8',
          data: JSON.stringify(payload),
          dataType: 'json'
        }).done(r=>{
          if (r && r.ok){ toastr.success(r.msg||'Venta actualizada.'); $('#modalEditarVenta').modal('hide'); cargarVentas(paginaActual); }
          else { toastr.error(r?.msg||'No se pudo actualizar la venta.'); }
        }).fail(()=> toastr.error('Error de comunicación con el servidor.'))
          .always(()=> $b.prop('disabled',false).html(txt));
      });

      // ===== Abrir modal edición =====
      window.abrirEditarVenta = function(idVenta){
        if(!idVenta) return;
        edVentaId = Number(idVenta);
        $errEd.addClass('d-none').empty();
        $buscar.val(''); $sug.hide().empty();
        carrito=[]; pintarCarrito();

        $.get(VENTAS_URL,{accion:'detalle', id_venta: edVentaId}, function(r){
          if (!r || !r.venta){ $errEd.removeClass('d-none').text('No se encontró la venta.'); return; }
          const v=r.venta, det=r.detalles||[];

          // encabezado
          $edFolio.text(v.folio||'—');
          $edEst.html(' '+getBadge(v.estatus||'—'));
          $edFechaInfo.text(fechaMx(v.fecha));
          $edUsrCaja.text(`${v.usuario||'—'} / ${v.caja||'—'}`);

          // selects & fecha editable
          const slug = mapIdToSlug(v.id_tipo_precio || (v.tipo_precio_id||1));
          $tpPrecio.val(slug);
          const ymd = (v.fecha || '').slice(0,10);
          $fechaVenta.val( ymd || '<?= date('Y-m-d') ?>' );

          cargarClientes(v.id_cliente||'');
          cargarFormasPago(v.id_forma_pago||null, v.forma_pago||null);

          // construir carrito con 'original' = cantidad actual de la venta
          const proms = det.map(d=>{
            const idp=Number(d.id_producto);
            return $.post(PRODUCTOS_URL,{accion:'detalle', id_producto:idp})
              .then(rr=>{
                const p=rr?.data||{};
                const item = {
                  id_producto:idp,
                  codigo: p.codigo || d.codigo || '',
                  descripcion: p.descripcion || d.producto || '',
                  stock_actual:Number(p.stock_actual ?? p.existencia ?? 0),
                  stock_minimo:Number(p.stock_minimo ?? 0),
                  precio_publico:Number(p.precio_publico ?? 0),
                  precio_taller:Number(p.precio_taller ?? 0),
                  precio_proveedor:Number(p.precio_proveedor ?? 0),
                  proveedor:p.proveedor ?? null,
                  original:Number(d.cantidad||0),
                  cantidad:Number(d.cantidad||0)
                };
                const unitVenta = Number(d.precio_unitario||0);
                const unitTipo  = (function(){
                  const t=slug;
                  if (t==='taller') return item.precio_taller;
                  if (t==='proveedor') return item.precio_proveedor;
                  return item.precio_publico;
                })();
                if (Math.abs(unitVenta - unitTipo) > 0.009) item.override_unit = unitVenta;

                carrito.push(item);
              });
          });

          Promise.all(proms).then(()=>{
            pintarCarrito();
            $('#modalEditarVenta').modal('show');
          });
        },'json').fail(()=> $errEd.removeClass('d-none').text('Error al cargar la venta.'));
      };

      // ============== INICIO ==============
      cargarVentas(paginaActual);
    });
    </script>
  </body>
</html>
