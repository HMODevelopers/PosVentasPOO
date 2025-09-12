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
      .ed-search-wrap { position: relative; }
      .ed-sug-panel {
        position:absolute; left:0; right:0; top:100%;
        z-index: 3000;
        max-height: 320px; overflow:auto; display:none;
        box-shadow: 0 8px 22px rgba(0,0,0,.15);
      }
      .ed-sug-panel .list-group-item { cursor:pointer; }
      .ed-sug-panel .list-group-item.disabled,
      .ed-sug-panel .list-group-item.disabled * { cursor:not-allowed!important; opacity:1; }

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

        <!-- ========================= MÓDULO: FILTROS ========================= -->
        <div class="card-header" style="border-color:darkgray; border-style:dotted;">
          <h5>Filtros</h5>
          <div class="row">
            <div class="col-lg-12">
              <div class="row">
                <!-- Folio -->
                <div class="col-md-4">
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

                <!-- Estatus -->
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="FEstatus" class="control-label">Estatus</label>
                    <select id="FEstatus" class="form-control filtrar">
                      <option value="">Todos</option>
                      <option value="Activa">Activa</option>
                      <option value="Guardada">Guardada</option>
                      <option value="Credito">Crédito</option>
                      <option value="Cancelada">Cancelada</option>
                    </select>
                  </div>
                </div>

                <!-- Fecha -->
                <div class="col-md-4">
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

        <!-- ========================= MÓDULO: TABLA LISTADO ========================= -->
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
                      <th class="text-end">Saldo</th> <!-- NUEVA -->
                      <th>Estatus crédito</th>         <!-- NUEVA -->
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

        <!-- ========================= MÓDULO: MODALES (SOLO INCLUDES) ========================= -->
        <?php include_once __DIR__ . '/../ventas/modales/detalle.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/ticket.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/eliminar.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/editar.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/abono.php'; ?>
        <?php include_once __DIR__ . '/../ventas/modales/activar.php'; ?>
      </div> <!-- /container-fluid -->
    </div> <!-- /wrapper -->

    <!-- Footer -->
    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
    <div class="rightbar-overlay"></div>

    <!-- ========================= CORE JS ========================= -->
    <script>const BASE_URL='<?= BASE_URL ?>';</script>
    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- ========================= APP JS: ORGANIZADO POR MÓDULOS ========================= -->
    <script>
    /* ==========================================================================
       MÓDULO: Helpers y utilidades compartidas
       - Funciones auxiliares, normalización, formateo, limpieza de campos
       ========================================================================== */
    function clearField(id){ try { $('#'+id).val('').trigger('change'); } catch(e){} }
    const norm = s => (s||'').toString().toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();

    // Formateadores comunes
    const mxn  = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
    const fix2 = v => (Number(v||0)).toFixed(2);
    const num  = v => parseFloat(v ?? 0) || 0;
    const fechaMx = dt => { try{ const d=new Date(String(dt).replace(' ','T')); return d.toLocaleString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:true}); } catch { return dt||'—'; } };

    // Endpoints (centralizados)
    const BASE = BASE_URL;
    const VENTAS_URL     = `${BASE}/controllers/VentasController.php`;
    const PRODUCTOS_URL  = `${BASE}/controllers/ProductosController.php`;
    const CLIENTES_URL   = `${BASE}/controllers/ClientesController.php`;
    const FORMASPAGO_URL = `${BASE}/controllers/FormasPagoController.php`;

    // Badges de estatus (UI)
    function getBadge(estatus){
      switch(estatus){
        case 'Activa':     return '<span class="badge badge-light-success badge-pill">Activa</span>';
        case 'Cancelada':  return '<span class="badge badge-light-danger badge-pill">Cancelada</span>';
        case 'Devuelta':   return '<span class="badge badge-light-warning badge-pill">Devuelta</span>';
        case 'Guardada':   return '<span class="badge badge-light-primary badge-pill">Guardada</span>';
        case 'Credito':    return '<span class="badge badge-light-info badge-pill">Crédito</span>';
        default:           return `<span class="badge badge-light-secondary badge-pill">${estatus||'—'}</span>`;
      }
    }

    // Menú de acciones por venta (según estatus)
    function accionesVenta(v){
        let out = `
          <a class="dropdown-item accion-ver-detalle" href="#" data-toggle="modal" data-target="#modalDetalle" data-id="${v.id_venta}">
            <i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver Detalle
          </a>`;

        if (v.estatus==='Activa' || v.estatus==='Guardada' || v.estatus==='Credito'){
          out += `
            <a class="dropdown-item" href="javascript:void(0);" onclick="abrirTicket(${v.id_venta});">
              <i class="mdi mdi-printer mr-2 text-muted font-18 vertical-middle"></i>Ticket / Imprimir
            </a>
            <a class="dropdown-item" href="javascript:void(0);" onclick="abrirEditarVenta(${v.id_venta});">
              <i class="mdi mdi-pencil mr-2 text-muted font-18 vertical-middle"></i>Editar
            </a>`;
        }

        if (v.estatus==='Guardada'){
          out += `
            <a class="dropdown-item accion-activar" href="#" data-id="${v.id_venta}" data-folio="${v.folio}">
              <i class="mdi mdi-check-circle mr-2 text-muted font-18 vertical-middle"></i>Activar (contabilizar)
            </a>`;
        }

        // Mostrar "Abonar" SOLO si es Crédito y el saldo > 0
        const saldo = num(v.saldo ?? (num(v.total) - num(v.abonado)));
        if (v.estatus==='Credito' && saldo > 0.0001){
          out += `
            <a class="dropdown-item accion-abonar-venta" href="#"
              data-id="${v.id_venta}" data-folio="${v.folio}">
              <i class="mdi mdi-cash mr-2 text-muted font-18 vertical-middle"></i>Abonar
            </a>`;
        }

        if (v.estatus==='Activa' || v.estatus==='Credito'){
          out += `
            <a class="dropdown-item accion-eliminar" href="#" data-id="${v.id_venta}" data-folio="${v.folio}">
              <i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Cancelar
            </a>`;
        }
        return out;
    }

    function getBadgeCredito(st){
      switch(st){
        case 'Pendiente': 
          return '<span class="badge badge-light-danger badge-pill">Pendiente</span>';
        case 'En Proceso': 
          return '<span class="badge badge-light-warning badge-pill">En Proceso</span>';
        case 'Liquidado': 
          return '<span class="badge badge-light-success badge-pill">Liquidado</span>';
        case 'N/A': 
          return '<span class="badge badge-light-secondary badge-pill">N/A</span>';
        default: 
          return `<span class="badge badge-light-secondary badge-pill">${st||'N/A'}</span>`;
      }
    }


    /* ==========================================================================
       MÓDULO: Listado y paginación de ventas
       - Obtiene ventas con filtros
       - Pinta tabla y pagina resultados
       ========================================================================== */
    let paginaActual=1; const limitePorPagina=10;

    function cargarVentas(pagina){
      const folio = $('#Folio').val() || '';
      const fecha = $('#Fecha').val() || new Date().toISOString().split('T')[0];
      const estatus = $('#FEstatus').val() || '';

      $.post(VENTAS_URL,{accion:'listar', pagina, limite:limitePorPagina, folio, fecha, estatus}, function(resp){
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
                  <td class="text-right"><b>${mxn(v.total)}</b></td>
                  <td class="text-right">${mxn(v.saldo ?? (num(v.total) - num(v.abonado)))}</td>             <!-- NUEVO -->
                  <td class="text-center">${getBadgeCredito(v.estatus_credito || 'N/A')}</td>                 <!-- NUEVO -->
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

    // Paginación (click)
    $('#pagination').on('click','a.page-link',function(e){
      e.preventDefault(); const p=Number($(this).data('page'));
      if (Number.isFinite(p)){ paginaActual=p; cargarVentas(paginaActual); }
    });

    // Filtros (change/enter/borrar)
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

    /* ==========================================================================
       MÓDULO: Detalle de venta (modal Detalle)
       - Abre modal
       - Carga encabezado y renglones
       ========================================================================== */
       $(document).on('click','a.accion-ver-detalle',function(e){
            e.preventDefault();
            const id=$(this).data('id'); if(!id) return;

            // Reset visual
            $('#det-error').hide(); 
            $('#det-contenido').hide(); 
            $('#det-loader').show();
            $('#modalDetalle').modal('show');

            // Siempre ocultar campos exclusivos de crédito al inicio
            const $wrapsCredito = $('#wrap-det-estatus-credito, #wrap-det-abonado, #wrap-det-saldo, #wrap-det-abonos, #det-btn-abonar');
            $wrapsCredito.addClass('d-none');

            $.get(VENTAS_URL,{accion:'detalle',id_venta:id},function(resp){
              if(!resp || !resp.venta){
                $('#det-loader').hide();
                $('#det-error').show().text('No se encontró la venta.');
                return;
              }

              const v       = resp.venta;
              const dets    = resp.detalles || [];
              const abonado = num(resp.abonado ?? v.abonado ?? 0);
              const saldo   = num(resp.saldo   ?? v.saldo   ?? (num(v.total) - abonado));
              const estCred = (v.estatus_credito || resp.estatus_credito || 'N/A');

              // Encabezado
              $('#det-folio').text(v.folio || '—');
              $('#det-fecha').text(fechaMx(v.fecha));
              $('#det-estatus').html(getBadge(v.estatus || '—'));
              $('#det-cliente').text(v.cliente || 'Público en general');
              $('#det-usuario').text(v.usuario || '—');
              $('#det-caja').text(v.caja || '—');
              $('#det-forma').text(v.forma_pago || '—');
              $('#det-tipo').text(v.tipo_precio || '—');

              // Productos
              let tb='', total=0;
              if (!dets.length){
                tb = '<tr><td colspan="5" class="text-center text-muted">Sin productos</td></tr>';
              } else {
                dets.forEach(d=>{
                  const c = Number(d.cantidad || 0);
                  const u = Number(d.precio_unitario || 0);
                  const s = Number(d.subtotal ?? (c * u));
                  total += s;
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
              $('#det-total').text(mxn(total || v.total || 0));

              // ------ Exclusivos de crédito ------
              if (v.estatus === 'Credito') {
                // Mostrar wrappers
                $('#wrap-det-estatus-credito, #wrap-det-abonado, #wrap-det-saldo, #wrap-det-abonos')
                  .removeClass('d-none');

                // Pintar datos
                if ($('#det-estatus-credito').length) $('#det-estatus-credito').html(getBadgeCredito(estCred));
                if ($('#det-abonado').length)         $('#det-abonado').text(mxn(abonado));
                if ($('#det-saldo').length)           $('#det-saldo').text(mxn(saldo));

                // Lista de abonos
                if ($('#det-abonos-body').length){
                  const $ab = $('#det-abonos-body').empty();
                  const abonos = Array.isArray(resp.abonos) ? resp.abonos : (Array.isArray(v.abonos) ? v.abonos : []);
                  if (!abonos.length){
                    $ab.append('<tr><td colspan="4" class="text-center text-muted">Sin abonos</td></tr>');
                  } else {
                    abonos.forEach(a=>{
                      $ab.append(`
                        <tr>
                          <td>${fechaMx(a.fecha_abono)}</td>
                          <td>${a.forma_pago_desc || '—'}</td>
                          <td class="text-right">${mxn(a.monto)}</td>
                          <td>${a.usuario_nombre || '—'}</td>
                        </tr>
                      `);
                    });
                  }
                }

                // Botón "Abonar": visible solo con saldo > 0
                if ($('#det-btn-abonar').length){
                  if (saldo > 0.0001) {
                    $('#det-btn-abonar').removeClass('d-none');
                  } else {
                    $('#det-btn-abonar').addClass('d-none');
                  }

                  // Click abre modal de abono existente
                  $('#det-btn-abonar').off('click').on('click', function(){
                    $('#modalDetalle').modal('hide');
                    $(`a.accion-abonar-venta[data-id="${v.id_venta}"]`).trigger('click');
                  });
                }
              } else {
                // No crédito: aseguramos que permanezcan ocultos
                $wrapsCredito.addClass('d-none');
              }
              // -----------------------------------

              $('#det-loader').hide();
              $('#det-contenido').show();

            },'json').fail(()=>{
              $('#det-loader').hide();
              $('#det-error').show().text('Error al cargar el detalle.');
            });
          });


    /* ==========================================================================
       MÓDULO: Ticket (modal Ticket)
       - Renderiza renglones del ticket
       - Dispara impresión (php Mike42)
       ========================================================================== */
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

    // Botón imprimir (usa util php Mike42 en /utils/ticket_mike42.php)
    $(document).on('click','#btnImprimirTicket',function(){
      const id=$('#tk-idventa').val(); if(!id){ alert('No hay venta seleccionada'); return; }
      $.get(`${BASE}/utils/ticket_mike42.php`,{id_venta:id})
        .done(r=>console.log('Impresión:',r))
        .fail(xhr=>console.error('Error al imprimir:',xhr.responseText||'Error'));
    });

    /* ==========================================================================
       MÓDULO: Cancelación (modal Eliminar)
       - Abre modal de confirmación
       - Llama a eliminar y refresca listado
       ========================================================================== */
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

    /* ==========================================================================
       MÓDULO: Activar venta guardada (modal Activar)
       - Carga detalle + formas de pago
       - Si forma de pago es Crédito (id=99 o texto), cliente es obligatorio
       - Envía activar-guardada
       ========================================================================== */
    // === Estado del modal "Activar venta" ===
    let AC_TIENE_CLIENTE = false;   // true si la venta ya trae un id_cliente en BD
    let AC_FP_CREDITO_ID = 21;      // ID por defecto de "Crédito (PPD)" en tu BD (antes asumías 99)
    let AC_CLIENTES_CARGADOS = false; // Evita recargar clientes innecesariamente

    /**
     * Carga opciones de clientes en el <select>, con preselección opcional.
     * @param {number|string|null} preselectId ID de cliente a preseleccionar (si viene de la venta)
     */
    function ac_cargarClientes(preselectId) {
      const $sel = $('#ac-selCliente');
      if (AC_CLIENTES_CARGADOS && !preselectId) return; // ya cargado y no se pidió preselección

      $.post(CLIENTES_URL, {accion:'listar-min', limite:200})
        .done(r=>{
          const data = r?.data || (Array.isArray(r)?r:[]);
          $sel.empty().append(`<option value="">-- Selecciona cliente --</option>`);
          data.forEach(c=>{
            const id = c.id_cliente ?? c.id;
            const nombre = c.nombre ?? c.razon_social ?? c.nombre_comercial ?? 'Cliente';
            if (id!=null && id!=='') $sel.append(`<option value="${id}">${nombre}</option>`);
          });
          if (preselectId) $sel.val(String(preselectId));
          AC_CLIENTES_CARGADOS = true;
        })
        .fail(()=>{ 
          $sel.empty().append(`<option value="">(No se pudieron cargar clientes)</option>`); 
        });
    }

    /**
     * Determina si la forma de pago seleccionada corresponde a "Crédito (PPD)".
     * Regla:
     *  1) Si conocemos el ID de crédito (AC_FP_CREDITO_ID), comparamos por value (robusto).
     *  2) Fallback: si el texto incluye "credito" y NO incluye "tarjeta", lo consideramos crédito.
     */
    function ac_esCreditoSeleccionado(){
      const $sel = $('#ac-selFormaPago');
      const val  = ($sel.val() ?? '').toString().trim();

      // Comparación por ID conocido (más confiable)
      if (AC_FP_CREDITO_ID != null && val === String(AC_FP_CREDITO_ID)) return true;

      // Fallback por texto (por si no llegó el catálogo o cambió el ID)
      const txt = norm($sel.find('option:selected').text());
      return txt.includes('credito') && !txt.includes('tarjeta');
    }

    /**
     * Muestra/oculta el bloque de selección de cliente en función de si es crédito.
     * Además refresca el catálogo de clientes cuando sea necesario.
     */
    function ac_toggleClienteRequired(){
      const $wrap = $('#ac-wrapCliente');
      const $help = $('#ac-helpCliente');

      if (ac_esCreditoSeleccionado()){
        $wrap.removeClass('d-none').show();
        $help.text(AC_TIENE_CLIENTE
          ? 'La venta ya tiene cliente; puedes cambiarlo si es necesario.'
          : 'Selecciona el cliente para activar como Crédito.'
        );
        ac_cargarClientes(null);
      } else {
        $wrap.addClass('d-none').hide();
      }
    }

    // === Abrir modal "Activar" ===
    $(document).on('click','a.accion-activar', function(e){
      e.preventDefault();
      const id = $(this).data('id');
      const folio = $(this).data('folio') || '—';
      if (!id) return;

      // Reset estado UI
      $('#ac-id-venta').val(id);
      $('#ac-folio').text(folio);
      $('#ac-error').addClass('d-none').empty();
      $('#ac-fechaAhora').prop('checked', true);
      AC_TIENE_CLIENTE = false;
      // AC_FP_CREDITO_ID se queda con 21 como valor por defecto (BD)
      $('#ac-wrapCliente').addClass('d-none').hide();

      // 1) Traer detalle de venta (para saber si tiene cliente y preseleccionarlo)
      $.get(VENTAS_URL, {accion:'detalle', id_venta:id})
        .done(r=>{
          const v = r?.venta || {};
          AC_TIENE_CLIENTE = !!(v.id_cliente);
          const preIdCliente = v.id_cliente || '';

          // 2) Cargar formas de pago y detectar el ID real de Crédito (PPD)
          $.get(FORMASPAGO_URL, {accion:'listar_select'})
            .done(rr=>{
              const arr = rr?.data || (Array.isArray(rr)?rr:[]);
              const $sel = $('#ac-selFormaPago').empty();

              if (!arr.length){
                // Fallback mínimo coherente con tu BD: Crédito (PPD) = 21
                $sel.append(
                  '<option value="1">Efectivo</option>'+
                  '<option value="2">Tarjeta</option>'+
                  '<option value="3">Mixto</option>'+
                  '<option value="21">Crédito (PPD)</option>'
                );
                AC_FP_CREDITO_ID = 21;
              } else {
                arr.forEach(fp=>{
                  const opt = $('<option/>', { value: fp.id_forma_pago, text: fp.descripcion });
                  $sel.append(opt);

                  // Detecta "Crédito (PPD)" SIN confundir "Tarjeta de crédito"
                  const t = norm(fp.descripcion || '');
                  if (t.includes('credito') && !t.includes('tarjeta')) {
                    AC_FP_CREDITO_ID = fp.id_forma_pago; // p. ej., 21 en tu BD
                  }
                });

                // Selecciona Efectivo por defecto si existe
                const $ef = $sel.find('option').filter(function(){
                  return norm($(this).text()).includes('efectivo');
                });
                if ($ef.length) $ef.prop('selected', true);
              }

              // Si la venta ya tenía cliente, recárgalo cuando cambie a crédito
              if (AC_TIENE_CLIENTE) {
                $(document).one('change','#ac-selFormaPago', () => ac_cargarClientes(preIdCliente));
              }

              // Evaluar si mostrar el selector de cliente de entrada
              ac_toggleClienteRequired();
              $('#modalActivarVenta').modal('show');
            })
            .fail(()=>{
              // Fallback cuando el endpoint de formas de pago falla
              const $sel = $('#ac-selFormaPago').empty();
              $sel.append(
                '<option value="1">Efectivo</option>'+
                '<option value="2">Tarjeta</option>'+
                '<option value="3">Mixto</option>'+
                '<option value="21">Crédito (PPD)</option>'
              );
              AC_FP_CREDITO_ID = 21;
              ac_toggleClienteRequired();
              $('#modalActivarVenta').modal('show');
            });
        })
        .fail(()=>{
          $('#ac-error').removeClass('d-none').text('No se pudo verificar la venta. Intenta de nuevo.');
          $('#modalActivarVenta').modal('show');
        });
    });

    // Cuando cambia la forma de pago, recalcular si se requiere cliente
    $(document)
      .off('change.acfp', '#ac-selFormaPago')
      .on('change.acfp',  '#ac-selFormaPago', ac_toggleClienteRequired);

    /**
     * Confirmar "Activar": valida requisitos y llama al backend.
     * - Si es crédito y la venta no traía cliente, obliga a elegir uno.
     */
    $(document)
      .off('click','#btnConfirmarActivar')
      .on('click','#btnConfirmarActivar', function(){
        const idVenta   = Number($('#ac-id-venta').val());
        const idForma   = $('#ac-selFormaPago').val() ? Number($('#ac-selFormaPago').val()) : null;
        const fechaAhora= $('#ac-fechaAhora').is(':checked') ? 1 : 0;

        if (!idVenta){ return; }
        if (!idForma){
          $('#ac-error').removeClass('d-none').text('Selecciona una forma de pago.');
          return;
        }

        const esCredito = ac_esCreditoSeleccionado();
        const idClienteSel = $('#ac-selCliente').val() ? Number($('#ac-selCliente').val()) : null;

        // Si se va a activar como Crédito y la venta no tenía cliente, obligar selección
        if (esCredito && !AC_TIENE_CLIENTE && !idClienteSel){
          $('#ac-error').removeClass('d-none').text('Para activar como Crédito debes seleccionar un cliente.');
          return;
        }

        // UI: spinner en botón
        const $btn = $(this);
        const html = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Activando...');

        // Llamada al backend
        $.post(VENTAS_URL, {
          accion:'activar-guardada',
          id_venta:idVenta,
          id_forma_pago:idForma,
          id_cliente: idClienteSel || '',
          actualizar_fecha:fechaAhora
        }, function(r){
          if (r && r.ok){
            toastr.success(r.msg || 'Venta activada.');
            $('#modalActivarVenta').modal('hide');
            cargarVentas(paginaActual);
          } else {
            $('#ac-error').removeClass('d-none').text(r?.msg || 'No se pudo activar la venta.');
          }
        }, 'json')
        .fail(()=>{
          $('#ac-error').removeClass('d-none').text('Error de comunicación con el servidor.');
        })
        .always(()=>{
          $btn.prop('disabled', false).html(html);
        });
    });


    /* ==========================================================================
       MÓDULO: Editor de venta (modal Editar)
       - Busca productos con sugerencias
       - Maneja carrito editable (cantidades, precios, subtotales)
       - Valida crédito -> cliente obligatorio
       - Guarda actualización de venta
       ========================================================================== */
    const $modalEd = $('#modalEditarVenta');
    const $edFolio = $('#ed-folio');
    const $edEst   = $('#ed-estatus');
    const $edFechaInfo = $('#ed-fecha');
    const $edUsrCaja   = $('#ed-usr-caja');

    const $selCliente = $('#ed-selCliente');
    const $selForma   = $('#ed-selFormaPago');
    const $tpPrecio   = $('#ed-tpPrecio');
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
    let carrito   = [];
    let debTimer = null;

    function vendibleDe(det){ return Math.max(0, num(det.stock_actual ?? det.existencia) - num(det.stock_minimo)); }
    function mapTipoPrecioId(slug){ const m={publico:1, taller:2, proveedor:3}; return m[slug]||1; }

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

          if (!arr.length){
            $selForma.append(`<option value="">(sin formas de pago)</option>`);
            return;
          }

          arr.forEach(fp=>{
            const opt = $('<option/>', { value: fp.id_forma_pago, text: fp.descripcion });
            $selForma.append(opt);
          });

          if (selected!=null) {
            $selForma.val(String(selected));
          } else if (fallbackText){
            $selForma.find('option').filter(function(){return $(this).text()===fallbackText;}).prop('selected',true);
          }
        })
        .fail(()=>{
          $selForma.empty()
            .append('<option value="1">Efectivo</option>')
            .append('<option value="2">Tarjeta</option>')
            .append('<option value="3">Mixto</option>')
            .append('<option value="99">Crédito</option>');
          if (selected!=null) $selForma.val(String(selected));
        });
    }

    function isCreditoByOption($opt){
      const txtN = norm($opt.text());
      return !txtN.includes('tarjeta') && /^\s*credito(?:\s*\(.*\))?\s*$/.test(txtN);
    }

    // Sugerencias (UI rápida con datos básicos + enrich async)
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

    function renderSugerencias(arr){
      $sug.empty();
      if(!arr.length){ $sug.hide(); return; }
      arr.forEach(p=>$sug.append(sugHTMLBasico(p)));
      $sug.show();

      // Enriquecer cada ítem con precios/stock
      $sug.find('a.list-group-item').each(function(){
        const id=Number($(this).data('id')), $row=$(this);
        $.post(PRODUCTOS_URL,{accion:'detalle', id_producto:id})
         .done(r=>{
           const det=r?.data||{};
           const pub = Number(det.precio_publico ?? 0);
           const tal = Number(det.precio_taller ?? 0);
           const stk = Number(det.stock_actual ?? det.existencia ?? 0);
           const extra = `<span>Taller: ${mxn(tal)}</span> · <span>Público: ${mxn(pub)}</span> · <span>Exist: ${fix2(stk)}</span>`;
           const sinStock = Math.max(0, num(det.stock_actual ?? det.existencia) - num(det.stock_minimo)) <= 0;
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

    $('#ed-buscar').on('input', function(){
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
         const vendible=Math.max(0, num(det.stock_actual ?? det.existencia) - num(det.stock_minimo));
         if (vendible<=0){ toastr.warning('Sin stock disponible.'); return; }
         agregarDesdeDetalle(det);
         $('#ed-buscar').val(''); $sug.hide().empty();
       })
       .fail(()=> toastr.error('No se pudo obtener el detalle del producto'));
    }

    // Carrito (agregar desde detalle)
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
        original: Number(originalCant||0)
      };
      const vendible=Math.max(0, itemBase.stock_actual - itemBase.stock_minimo);
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

    // Determina precio unitario efectivo
    function precioDeItemCarrito(it){
      if (typeof it.override_unit === 'number' && !isNaN(it.override_unit)) return Number(it.override_unit);
      const t = $tpPrecio.val() || 'publico';
      if (t==='taller') return Number(it.precio_taller||0);
      if (t==='proveedor') return Number(it.precio_proveedor||0);
      return Number(it.precio_publico||0);
    }

    // Redibuja tabla del carrito y total
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
        const unit = precioDeItemCarrito(it);
        const cantidad = Number(it.cantidad) || 0;
        const subtotal = cantidad * unit;
        total += subtotal;
        const vendible = Math.max(0, Number(it.stock_actual) - Number(it.stock_minimo));
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

    // Controles de cantidad / subtotal / borrar
    $tbody.on('click','button[data-ed-inc]', function(){
      const i=Number(this.dataset.edInc); if(isNaN(i)||!carrito[i]) return;
      const vendible=Math.max(0, Number(carrito[i].stock_actual) - Number(carrito[i].stock_minimo));
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
    $tbody.on('change','input[data-ed-sub]', function(){
      const i=Number(this.dataset.edSub); if(isNaN(i)||!carrito[i]) return;
      let sub=Number(this.value); if(isNaN(sub)||sub<0) sub=0;
      const qty=Math.max(1, Number(carrito[i].cantidad)||1);
      carrito[i].override_unit = Number((sub/qty).toFixed(2));
      pintarCarrito();
    });
    $tbody.on('click','button[data-ed-del]', function(){
      const i=Number(this.dataset.edDel); if(isNaN(i)) return;
      carrito.splice(i,1); pintarCarrito();
    });

    $tpPrecio.on('change', pintarCarrito);

    // Guardar edición de la venta
    $('#btnGuardarEdicion').on('click', function(){
      if (!edVentaId){ toastr.error('No hay venta cargada.'); return; }
      if (!carrito.length){ toastr.warning('Agrega productos a la orden'); return; }

      const $opt = $selForma.find('option:selected');
      const esCredito = isCreditoByOption($opt);

      const idClienteSel = $selCliente.val() ? Number($selCliente.val()) : null;
      if (esCredito && !idClienteSel){
        toastr.error('Para ventas a crédito es obligatorio seleccionar un cliente.');
        $selCliente.focus();
        return;
      }

      const id_forma_pago = $selForma.val() ? Number($selForma.val()) : null;
      const nuevoEstatus = esCredito ? 'Credito' : 'Activa';
      const id_tipo_precio = mapTipoPrecioId($tpPrecio.val());

      const venta = {
        id_venta: edVentaId,
        fecha: $('#ed-fechaVenta').val(),
        id_cliente: idClienteSel ?? null,
        id_forma_pago: id_forma_pago ?? null,
        id_tipo_precio: id_tipo_precio,
        estatus: nuevoEstatus
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

    // Abrir modal de edición (carga venta + carrito con control de stock máx vendible)
    window.abrirEditarVenta = function(idVenta){
      if(!idVenta) return;
      edVentaId = Number(idVenta);
      $errEd.addClass('d-none').empty();
      $('#ed-buscar').val(''); $sug.hide().empty();
      carrito=[]; pintarCarrito();

      $.get(VENTAS_URL,{accion:'detalle', id_venta: edVentaId}, function(r){
        if (!r || !r.venta){ $errEd.removeClass('d-none').text('No se encontró la venta.'); return; }
        const v=r.venta, det=r.detalles||[];

        $edFolio.text(v.folio||'—');
        $edEst.html(' '+getBadge(v.estatus||'—'));
        $edFechaInfo.text(fechaMx(v.fecha));
        $edUsrCaja.text(`${v.usuario||'—'} / ${v.caja||'—'}`);

        const slug = (function(id){ const m={1:'publico',2:'taller',3:'proveedor'}; return m[id||1]||'publico'; })(v.id_tipo_precio||1);
        $tpPrecio.val(slug);
        const ymd = (v.fecha || '').slice(0,10);
        $fechaVenta.val( ymd || '<?= date('Y-m-d') ?>' );

        cargarClientes(v.id_cliente||'');
        cargarFormasPago(v.id_forma_pago||null, v.forma_pago||null);

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

    /* ==========================================================================
       MÓDULO: Abonos a ventas (modal Abono)
       - Carga formas de pago
       - Muestra saldo disponible
       - Valida y guarda abono
       ========================================================================== */
    function cargarFormasPagoAbono(selected){
      const $sel = $('#ab-forma');
      if (!$sel.length) return;

      // Si conoces IDs exactos a excluir, ponlos aquí (opcional)
      const EXCLUDE_FP_IDS = []; // ej. [6]

      // Normaliza texto: quita acentos y pasa a minúsculas
      const norm = (t)=> String(t||'')
        .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
        .toLowerCase().trim();

      // Detecta "Crédito", "Crédito (PPD)", etc. (no coincide con "Tarjeta de crédito")
      const esCreditoPuro = (desc)=>{
        const txt = norm(desc);
        return /^credito\b/.test(txt) && !/tarjeta/.test(txt);
      };

      $sel.prop('disabled', true).empty().append('<option value="">Cargando…</option>');

      $.get(FORMASPAGO_URL, {accion:'listar_select'})
        .done(r=>{
          const arr = r?.data || (Array.isArray(r)?r:[]);

          // Filtra fuera "Crédito …" y los IDs excluidos (si los agregas)
          const filtradas = arr.filter(fp => {
            const id   = Number(fp.id_forma_pago);
            const desc = fp.descripcion ?? '';
            return !EXCLUDE_FP_IDS.includes(id) && !esCreditoPuro(desc);
          });

          $sel.empty();

          if(!filtradas.length){
            $sel.append('<option value="">(sin formas de pago)</option>');
          } else {
            filtradas.forEach(fp => {
              $sel.append(`<option value="${fp.id_forma_pago}">${fp.descripcion}</option>`);
            });

            // Respetar selected si existe tras el filtrado
            if (selected != null && $sel.find(`option[value="${String(selected)}"]`).length){
              $sel.val(String(selected));
            }

            // Si aún no hay selección, intenta con "Efectivo"
            if(!$sel.val()){
              const opEfe = $sel.find('option').filter(function(){
                return norm($(this).text()) === 'efectivo';
              }).first().val();
              if(opEfe) $sel.val(opEfe);
            }
          }
        })
        .fail(()=>{
          // Fallback sin incluir "Crédito"
          $sel.empty()
              .append('<option value="1">Efectivo</option>')
              .append('<option value="2">Tarjeta de crédito</option>')
              .append('<option value="3">Tarjeta de débito</option>')
              .append('<option value="4">Transferencia electrónica de fondos</option>');
        })
        .always(()=> $sel.prop('disabled', false));
    }

    // Abrir modal Abono: carga saldo y formas de pago
    $(document).on('click','a.accion-abonar-venta', function(e){
      e.preventDefault();
      const id = Number($(this).data('id'));
      const folio = $(this).data('folio') || '—';
      if(!id) return;

      $('#ab-id-venta').val(id);
      $('#ab-folio').text(folio);
      $('#ab-monto').val('');
      $('#ab-ref').val('');
      $('#ab-error').addClass('d-none').empty();

      cargarFormasPagoAbono();

      $.get(VENTAS_URL,{accion:'detalle', id_venta:id}, function(resp){
        if(!resp || !resp.venta){
          toastr.error('No se encontró la venta.'); 
          return;
        }
        const saldo = Number(resp?.saldo ?? ((Number(resp?.venta?.total||0) - Number(resp?.abonado||0))));
        $('#ab-saldo').text( Number.isFinite(saldo) ? saldo.toLocaleString('es-MX',{style:'currency',currency:'MXN'}) : '$0.00' );
        $('#ab-monto').attr('max', Math.max(0, saldo||0));
        $('#modalAbonarVenta').modal('show');
      }, 'json').fail(()=> toastr.error('No se pudo obtener el saldo.'));
    });

    // Guardar abono
    $('#formAbonoVenta').on('submit', function(e){
      e.preventDefault();
      const id_venta = Number($('#ab-id-venta').val());
      const monto    = Number($('#ab-monto').val());
      const id_fp    = Number($('#ab-forma').val()) || 0;
      const fecha    = $('#ab-fecha').val() || '';
      const ref      = $('#ab-ref').val().trim();

      if(!id_venta){ return; }
      if(!monto || monto<=0){ $('#ab-error').removeClass('d-none').text('Captura un monto válido.'); return; }
      if(!id_fp){ $('#ab-error').removeClass('d-none').text('Selecciona una forma de pago.'); return; }

      $('#ab-error').addClass('d-none').empty();
      const $btn = $('#btnConfirmarAbono'); const bak = $btn.html();
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando…');

      $.post(VENTAS_URL, {
        accion: 'abonar-venta',
        id_venta: id_venta,
        monto: monto,
        id_forma_pago: id_fp,
        fecha_abono: fecha,
        referencia_pago: ref
      }, function(r){
        if(r && r.ok){
          toastr.success('Abono registrado.');
          $('#modalAbonarVenta').modal('hide');
          if (typeof cargarVentas === 'function') { cargarVentas(paginaActual || 1); }
        } else {
          $('#ab-error').removeClass('d-none').text(r?.msg || 'No se pudo registrar el abono.');
        }
      }, 'json').fail(()=>{
        $('#ab-error').removeClass('d-none').text('Error de comunicación con el servidor.');
      }).always(()=>{
        $btn.prop('disabled', false).html(bak);
      });
    });

    /* ==========================================================================
       MÓDULO: Inicialización
       ========================================================================== */
    $(function(){
      cargarVentas(paginaActual); // primer render del listado
    });
    </script>
  </body>
</html>
