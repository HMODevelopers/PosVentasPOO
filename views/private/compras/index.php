<?php
$titulo = "Compras";
$modulo = "Gestionar Compras";
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
    <title>Compras | REFASOFT-V4</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
    <meta content="Coderthemes" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

    <!-- plugin css -->
    <link href="<?= BASE_URL ?>/assets/libs/jquery-vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />

    <!-- Toastr (para mensajes) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <style>
      /* Evitar recortes de overlays dentro del modal */
      .modal .modal-content { overflow: visible; }

      /* Estilo base para el popup de sugerencias (el JS lo pone en position:fixed al mostrar) */
      .ac-sug{
        display:none;
        background:#fff;
        border:1px solid rgba(0,0,0,.125);
        border-radius:.25rem;
        max-height:240px;
        overflow:auto;
        box-shadow:0 .5rem 1rem rgba(0,0,0,.15);
      }
    </style>
  </head>

  <body>

    <!-- Navigation Bar-->
    <?php include_once __DIR__ . '/../../../includes/header.php'; ?>
    <!-- End Navigation Bar-->

    <div class="wrapper">
      <!-- Loader -->
      <div class="wrapper-loader fade" id="LoadingImage" style="display: none;">
        <div class="loader">
          <div class="loader__figure"></div>
          <p class="loader__label">Cargando...</p>
        </div>
      </div>
      <!-- Fin Loader -->

      <div class="container-fluid">

        <!-- start page title -->
        <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>    
        <!-- end page title --> 

        <!-- =================== Filtros =================== -->
        <div class="card-header" style="border-color:darkgray; border-style:dotted;">
          <h5>Filtros</h5>
          <div class="row">
            <div class="col-lg-12">

              <div class="row">
                <!-- Folio factura -->
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="Folio" class="control-label">Folio de Factura</label>
                    <div class="input-group">
                      <input type="text" id="Folio" name="Folio" class="form-control filtrar">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text">
                          <i class="mdi mdi-close-circle text-danger" onclick="clearField('Folio')"></i>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Estatus -->
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="Estatus" class="control-label">Estatus</label>
                    <div class="input-group">
                      <select id="Estatus" name="Estatus" class="form-control filtrar">
                        <option value="">-- Todos --</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="Pagada">Pagada</option>
                        <option value="Parcial">Parcial</option>
                        <option value="Cancelada">Cancelada</option>
                      </select>
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text">
                          <i class="mdi mdi-close-circle text-danger" onclick="clearField('Estatus')"></i>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Proveedor -->
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="Proveedor" class="control-label">Proveedor</label>
                    <div class="input-group">
                      <select id="Proveedor" name="Proveedor" class="form-control filtrar" disabled>
                        <option value="">-- Todos --</option>
                      </select>
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text">
                          <i class="mdi mdi-close-circle text-danger" onclick="clearField('Proveedor')"></i>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Fecha factura -->
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="Fecha" class="control-label">Fecha de Factura</label>
                    <div class="input-group">
                      <input type="date" id="Fecha" name="Fecha" class="form-control filtrar" value="<?php echo date('Y-m-d'); ?>">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text">
                          <i class="mdi mdi-close-circle text-danger" onclick="clearField('Fecha')"></i>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

              </div><!-- row -->
            </div><!-- col-12 -->
          </div><!-- row -->
        </div>
        <!-- =================== /Filtros =================== -->

        <!-- =================== Tabla Compras =================== -->
        <div class="row">
          <div class="col-12">
            <div class="card-box">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="header-title">Listado de Compras</h4>
                <button type="button" class="btn btn-primary btn-sm" id="btnNuevaCompra">
                  <i class="mdi mdi-plus"></i> Nueva compra
                </button>
              </div>

              <div class="table-responsive">
                <table id="tablaCompras" class="table table-bordered table-hover table-striped">
                  <thead>
                    <tr>
                      <th>Folio Factura</th>
                      <th>Proveedor</th>
                      <th>Usuario Capturó</th>
                      <th>Sucursal</th>
                      <th class="text-end">Total</th>
                      <th>Estatus</th>
                      <th>Fecha Factura</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <!-- Paginador -->
              <div class="row align-items-center justify-content-between mt-2">
                <div class="col-md-6">
                  <div id="infoCompras" class="dataTables_info" role="status" aria-live="polite"></div>
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
        <!-- =================== /Tabla Compras =================== -->

        <!-- Modales -->
        <?php include_once __DIR__ . '/../compras/modales/agregar.php'; ?>  
        <?php include_once __DIR__ . '/../compras/modales/detalles.php'; ?>  
        <?php include_once __DIR__ . '/../compras/modales/eliminar.php'; ?>
      
      </div> <!-- end container -->
    </div>
    <!-- end wrapper -->

    <!-- Footer Start -->
    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
    <!-- End Footer -->

    <!-- Right bar overlay-->
    <div class="rightbar-overlay"></div>

    <!-- Vendor js -->
    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <!-- App js-->
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
      /*********************************************************
       * ESTADO GLOBAL y HELPERS
       *********************************************************/
      let AC_ROWS = [];                 // detalle en modal (renglones)
      let AC_MODO_EDICION = false;      // false=crear, true=editar
      let AC_ID_EDICION = null;         // id_compra en edición
      let AC_SNAPSHOT = null;           // foto inicial en editar

      $(function () {
        let paginaActual = 1;
        const limitePorPagina = 10;

        /******************* Helpers UI *******************/
        function ymdToEs(ymd) { if (!ymd) return '—'; const [y, m, d] = ymd.split('-'); return `${d}/${m}/${y}`; }
        function todayYMDLocal() {
          const d = new Date();
          const yyyy = d.getFullYear();
          const mm = String(d.getMonth() + 1).padStart(2, '0');
          const dd = String(d.getDate()).padStart(2, '0');
          return `${yyyy}-${mm}-${dd}`;
        }
        function mxn(v){ return Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'}); }
        function debounce(fn, delay=250){ let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), delay); };}

        /*********************************************************
         * SELECT DE PROVEEDORES (FILTRO PRINCIPAL)
         *********************************************************/
        cargarProveedoresSelect();  
        function cargarProveedoresSelect(opts = {}) {
          const { selectId = 'Proveedor', selected = '', incluirTodos = true, limite = 200, q = '' } = opts;
          const $sel = $('#' + selectId);
          if ($sel.length === 0) return;

          $sel.prop('disabled', true).html(incluirTodos ? '<option value="">-- Todos --</option>' : '');

          $.ajax({
            url: '<?= BASE_URL ?>/controllers/ProveedoresController.php',
            method: 'GET',
            dataType: 'json',
            data: { accion: 'listar-min', limite, q }
          })
          .done(function(resp){
            const arr = resp?.data || [];
            let html = incluirTodos ? '<option value="">-- Todos --</option>' : '';
            arr.forEach(p => {
              const id  = p.id_proveedor;
              const nom = p.nombre;
              if (id && nom) {
                  const sel = (String(id) === String(selected)) ? ' selected' : '';
                  html += `<option value="${id}"${sel}>${nom}</option>`;
              }
            });
            $sel.html(html).prop('disabled', false);
          })
          .fail(function(){
            $sel.prop('disabled', false);
          });
        }
        
        /*********************************************************
         * TABLA DE COMPRAS + PAGINACIÓN
         *********************************************************/
        function getBadge(estatus) {
          switch (estatus) {
            case 'Pendiente': return '<span class="badge badge-light-warning badge-pill">Pendiente</span>';
            case 'Pagada':    return '<span class="badge badge-light-success badge-pill">Pagada</span>';
            case 'Parcial':   return '<span class="badge badge-light-info badge-pill">Parcial</span>';
            case 'Cancelada': return '<span class="badge badge-light-danger badge-pill">Cancelada</span>';
            default:          return `<span class="badge badge-light-secondary badge-pill">${estatus || '—'}</span>`;
          }
        }

        function getAcciones(c) {
          let html = `
            <a class="dropdown-item accion-ver-detalle" href="#" data-toggle="modal" data-target="#modalDetalle" data-id="${c.id_compra}">
              <i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver Detalle
            </a>`;
          if (c.estatus !== 'Cancelada') {
            html += `
            <a class="dropdown-item accion-editar" href="#" data-id="${c.id_compra}">
              <i class="mdi mdi-pencil mr-2 text-muted font-18 vertical-middle"></i>Editar
            </a>
            <a class="dropdown-item accion-eliminar" href="#" data-id="${c.id_compra}" data-folio="${c.folio_factura || ('COMP-' + c.id_compra)}">
              <i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Cancelar
            </a>`;
          }
          return html;
        }

        function cargarCompras(pagina) {
          const folio = $('#Folio').val();
          const fecha = $('#Fecha').val() || todayYMDLocal();
          const estatus     = $('#Estatus').val();
          const idProveedor = $('#Proveedor').val();

          $.ajax({
              url: '<?= BASE_URL ?>/controllers/ComprasController.php',
              method: 'POST',
              dataType: 'json',
              data: {
                  accion: 'listar',
                  pagina: pagina,
                  limite: limitePorPagina,
                  folio: folio,
                  fecha: fecha, 
                  estatus: estatus,
                  id_proveedor: idProveedor
              }
          })
          .done(function(resp){
              const compras = resp.data || [];
              const total   = parseInt(resp.total || 0, 10);
              renderizarTabla(compras);

              let desde = (pagina - 1) * limitePorPagina + 1;
              let hasta = Math.min(pagina * limitePorPagina, total);
              $('#infoCompras').text(`Mostrando ${total === 0 ? 0 : desde} a ${hasta} de ${total} compras`);

              configurarPaginacion(pagina, total, limitePorPagina);
          })
          .fail(function(){
              alert('Error al cargar las compras.');
          });
        }

        function renderizarTabla(compras) {
          let tbody = '';
          if (!compras.length) {
              tbody = '<tr><td colspan="8" class="text-center">No hay compras disponibles</td></tr>';
          } else {
              compras.forEach(c => {
                tbody += `
                  <tr>
                    <td><center><b>${c.folio_factura || ('COMP-' + c.id_compra)}</b></center></td>
                    <td><center>${c.proveedor || '—'}</center></td>
                    <td><center>${c.usuario || '—'}</center></td>
                    <td><center>${c.sucursal || '—'}</center></td>
                    <td><center><b>${Number(c.total || 0).toLocaleString('es-MX', {style:'currency', currency:'MXN'})}</b></center></td>
                    <td><center>${getBadge(c.estatus)}</center></td>
                    <td><center>${c.fecha_factura ? ymdToEs(c.fecha_factura) : '—'}</center></td>
                    <td>
                      <center>
                        <div class="btn-group dropdown">
                          <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false">
                            <i class="mdi mdi-dots-horizontal"></i>
                          </a>
                          <div class="dropdown-menu dropdown-menu-right">
                            ${getAcciones(c)}
                          </div>
                        </div>
                      </center>
                    </td>
                  </tr>`;
              });
          }
          $('#tablaCompras tbody').html(tbody);
        }

        function configurarPaginacion(currentPage, totalItems, itemsPerPage = 10) {
            const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
            const $ul = $('#pagination');
            const maxVisiblePages = 5;
            $ul.empty();

            if (totalPages <= 1) { $ul.closest('nav').hide(); return; }
            else { $ul.closest('nav').show(); }

            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage   = Math.min(totalPages, startPage + maxVisiblePages - 1);
            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            if (currentPage > 1) {
                $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="1">Primera</a></li>`);
                $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);
            }
            for (let i = startPage; i <= endPage; i++) {
                const active = (i === currentPage) ? 'active' : '';
                $ul.append(`<li class="page-item ${active}"><a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a></li>`);
            }
            if (currentPage < totalPages) {
                $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);
                $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${totalPages}">Última</a></li>`);
            }

            $ul.off('click', 'a.page-link').on('click', 'a.page-link', function(e){
                e.preventDefault();
                const page = Number($(this).data('page'));
                if (Number.isFinite(page)) {
                  paginaActual = page;
                  cargarCompras(paginaActual);
                }
            });
        }

        // Carga inicial de la tabla
        cargarCompras(paginaActual);

        /*********************************************************
         * FILTROS
         *********************************************************/
        $(".filtrar")
          .change(function () {
              const $el = $(this);
              if ($el.val().length > 0) $el.siblings(".clean-filter").css({ display: "flex" });
              else $el.siblings(".clean-filter").css({ display: "none" });
              $el.blur();
              setTimeout(function(){ cargarCompras(1); }, 200);
          })
          .keypress(function (e) { if (e.charCode == 13) cargarCompras(1); })
          .keyup(function () {
              if ($(this).val().length > 0) $(this).siblings(".clean-filter").css({ display: "flex" });
              else $(this).siblings(".clean-filter").css({ display: "none" });
          })
          .click(function () { if ($(this).is(":button")) cargarCompras(1); });

        $(".clean-filter").click(function () {
          const $el = $(this).parent().find(".filtrar");
          $el.val("").trigger("change");
          if ($el.hasClass("select2")) $el.select2("val", 0);
          cargarCompras(1);
        });

        /*********************************************************
         * DETALLE (modal solo lectura)
         *********************************************************/
        $(document).on('click', 'a.accion-ver-detalle', function(e){
          e.preventDefault();
          const id = $(this).data('id');
          if (!id) return;

          $('#det-error').hide();
          $('#det-contenido').hide();
          $('#det-loader').show();
          $('#modalDetalle').modal('show');

          $.ajax({
              url: '<?= BASE_URL ?>/controllers/ComprasController.php',
              method: 'GET',
              dataType: 'json',
              data: { accion: 'detalle', id_compra: id }
          })
          .done(function(resp){
              if (!resp || !resp.compra) {
                $('#det-loader').hide();
                $('#det-error').show().text('No se encontró la compra.');
                return;
              }
              const c = resp.compra;
              const dets = Array.isArray(resp.detalles) ? resp.detalles : [];

              $('#det-folio').text(c.folio_factura || ('COMP-' + c.id_compra));
              $('#det-fecha').text( ymdToEs(c.fecha_factura) );
              $('#det-estatus').html(getBadge(c.estatus || '—'));
              $('#det-total').text(mxn(c.total || 0));
              $('#det-proveedor').text(c.proveedor || '—');
              $('#det-usuario').text(c.usuario || '—');
              $('#det-sucursal').text(c.sucursal || '—');

              let tbody = '';
              let total = 0;
              if (!dets.length) {
                tbody = `<tr><td colspan="5" class="text-center text-muted">Sin artículos</td></tr>`;
                total = Number(c.total || 0);
              } else {
                dets.forEach(d=>{
                    const cant = Number(d.cantidad || 0);
                    const prec = Number(d.precio_unitario || 0); // PPV de factura
                    const imp  = (d.subtotal != null) ? Number(d.subtotal) : cant*prec;
                    total += imp;
                    tbody += `
                    <tr>
                        <td>${d.codigo || ('#'+(d.id_producto||''))}</td>
                        <td>${d.producto || ('#'+(d.id_producto||''))}</td>
                        <td class="text-center">${cant}</td>
                        <td class="text-right">${mxn(prec)}</td>
                        <td class="text-right">${mxn(imp)}</td>
                    </tr>`;
                });
              }
              $('#det-tbody').html(tbody);
              if (!c.total) $('#det-total').text(mxn(total));

              $('#det-loader').hide();
              $('#det-contenido').show();
          })
          .fail(function(){
              $('#det-loader').hide();
              $('#det-error').show().text('Error al cargar el detalle.');
          });
        });

        /*********************************************************
         * CANCELAR / ELIMINAR (lógico)
         *********************************************************/
        $(document).on('click', 'a.accion-eliminar', function(e){
          e.preventDefault();
          const id    = $(this).data('id');
          const folio = $(this).data('folio');
          if (!id) return;

          $('#el-id-compra').val(id);
          $('#el-folio').text(folio || ('COMP-' + id));
          $('#modalEliminar').modal('show');
        });

        $(document).off('click','#btnConfirmarEliminarCompra')
        .on('click','#btnConfirmarEliminarCompra', function(){
          const id = $('#el-id-compra').val();
          if (!id) return;

          const $btn = $(this);
          const original = $btn.html();
          $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Cancelando...');

          $.ajax({
            url: '<?= BASE_URL ?>/controllers/ComprasController.php',
            method: 'POST',
            dataType: 'json',
            data: { accion: 'eliminar', id_compra: id }
          })
          .done(function(resp){
            if (resp && resp.ok === true) {
              toastr.success(resp.msg || 'Compra cancelada.');
              cargarCompras(paginaActual);
            } else {
              toastr.error(resp?.msg || 'No se pudo cancelar.');
            }
          })
          .fail(function(){
            toastr.error('Error de comunicación con el servidor.');
          })
          .always(function(){
            $('#modalEliminar').modal('hide');
            $btn.prop('disabled', false).html(original);
          });
        });

        /*********************************************************
         * MODAL AGREGAR/EDITAR: proveedores y estado base
         *********************************************************/
        function cargarProveedoresSelectModal(selected = ''){
          const $sel = $('#ac-proveedor');
          $sel.prop('disabled', true).html('<option value="">-- Selecciona --</option>');
          $.getJSON('<?= BASE_URL ?>/controllers/ProveedoresController.php', {accion:'listar-min', limite:200}, function(resp){
              const arr = resp?.data || [];
              let html = '<option value="">-- Selecciona --</option>';
              arr.forEach(p => { html += `<option value="${p.id_proveedor}">${p.nombre}</option>`; });
              $sel.html(html).prop('disabled', false);
              if (selected) $sel.val(String(selected));
          }).fail(()=> $sel.prop('disabled', false));
        }

        // Abre modal en modo CREAR
        $('#btnNuevaCompra').on('click', function(){
          AC_MODO_EDICION = false;
          AC_ID_EDICION   = null;
          AC_SNAPSHOT     = null;                 // sin snapshot en crear
          $('#ac-folio').val('');
          $('#ac-fecha').val(todayYMDLocal());
          $('#ac-estatus').val('Pendiente');
          $('#ac-error').hide().text('');
          $('#ac-btn-guardar').prop('disabled', false); // habilitado en crear

          cargarProveedoresSelectModal(); // proveedores activos
          // Fila inicial con cantidad mínima 1
          AC_ROWS = [{ id_producto:null, cantidad:1, precio_unitario:null, label:'' }];
          acRender();

          $('#modalAgregarCompra').modal('show');
        });

        // Abre modal en modo EDITAR
        $(document).on('click', '.accion-editar', function (e) {
          e.preventDefault();
          const id = Number($(this).data('id'));
          if (!id) return;

          AC_MODO_EDICION = true;
          AC_ID_EDICION   = id;
          $('#ac-error').hide().text('');
          $('#ac-folio').removeClass('is-invalid');

          // Cargar compra + detalles
          $.getJSON('<?= BASE_URL ?>/controllers/ComprasController.php', { accion: 'detalle', id_compra: id })
            .done(function (resp) {
              if (!resp || !resp.compra) {
                toastr.error('No se pudo cargar la compra.');
                return;
              }

              const c    = resp.compra;
              const dets = Array.isArray(resp.detalles) ? resp.detalles : [];

              // Encabezado
              cargarProveedoresSelectModal(c.id_proveedor);
              $('#ac-folio').val(c.folio_factura || '');
              $('#ac-fecha').val(c.fecha_factura || todayYMDLocal());
              $('#ac-estatus').val(c.estatus || 'Pendiente');

              // Renglones (cantidad mínima 1)
              AC_ROWS = dets.map(d => ({
                id_producto: Number(d.id_producto) || null,
                cantidad: Math.max(1, Number(d.cantidad || 1)),
                precio_unitario: Number(d.precio_unitario || 0), // PPV
                label: (d.codigo ? d.codigo + ' - ' : '') + (d.producto || '')
              }));
              if (AC_ROWS.length === 0) {
                AC_ROWS = [{ id_producto: null, cantidad: 1, precio_unitario: null, label: '' }];
              }

              // Renderiza tabla del modal
              acRender();

              // Snapshot del estado inicial y control del botón
              AC_SNAPSHOT = acSnapshotActual();
              acCheckDirty(); // deshabilita "Guardar" si no hay cambios

              // Mostrar modal
              $('#modalAgregarCompra').modal('show');
            })
            .fail(function () {
              toastr.error('Error al cargar la compra.');
            });
        });

        /*********************************************************
         * AUTOCOMPLETE PRODUCTOS (buscar-min)
         *********************************************************/
        function posSugFixed($input, $sug){
          const rect = $input[0].getBoundingClientRect();
          $sug.css({
            position: 'fixed', top: rect.bottom + 4, left: rect.left, width: rect.width, zIndex: 2000
          }).show();

          const $scrollParent = $input.closest('.table-responsive');
          $(window).off('.acpos').on('scroll.acpos resize.acpos', () => {
            const rr = $input[0].getBoundingClientRect();
            $sug.css({ top: rr.bottom + 4, left: rr.left, width: rr.width });
          });
          $scrollParent.off('.acpos').on('scroll.acpos', () => {
            const rr = $input[0].getBoundingClientRect();
            $sug.css({ top: rr.bottom + 4, left: rr.left, width: rr.width });
          });
        }
        function hideSug(){
          $('.ac-sug').hide().empty();
          $(window).off('.acpos');
          $('.table-responsive').off('.acpos');
        }

        // Busca productos por código/nombre → sugiere y trae PPV (precio_proveedor)
        const buscarProductos = debounce(function($input){
          const term = ($input.val()||'').trim();
          const $cell = $input.closest('.ac-prod-cell');
          const $sug  = $cell.find('.ac-sug');

          if (term.length < 2){ hideSug(); return; }

          $.getJSON('<?= BASE_URL ?>/controllers/ProductosController.php', {accion:'buscar-min', q: term, limite: 10})
            .done(function(resp){
              const items = resp?.data || [];
              if (!items.length){
                $sug.html('<div class="p-2 text-muted">Sin resultados</div>');
                posSugFixed($input, $sug);
                return;
              }
              let html = '<div class="list-group list-group-flush">';
              items.forEach(it=>{
                  const label = `${it.codigo ? it.codigo + ' - ' : ''}${it.descripcion}`;
                  const ppv = Number((it.precio_proveedor ?? it.costo_neto ?? 0)); // PPV
                  html += `
                  <a href="#" class="list-group-item list-group-item-action ac-pick"
                      data-id="${it.id_producto}" data-ppv="${ppv}"
                      title="${label}">${label}</a>`;
              });
              html += '</div>';
              $sug.html(html);
              posSugFixed($input, $sug);
            })
            .fail(()=> hideSug());
        }, 250);

        // tecleo → pedir sugerencias
        $(document).on('input', '#ac-tbody .ac-buscar', function(){
          buscarProductos($(this));
          acCheckDirty(); // escribir cuenta como cambio potencial
        });

        // clic en sugerencia → fija id_producto y si no hay precio, pone PPV sugerido
        $(document).on('click', '.ac-pick', function(e){
          e.preventDefault();
          const $a   = $(this);
          const id   = Number($a.data('id'));
          const ppv  = Number($a.data('ppv')||0); // PPV (precio proveedor)
          const $cell = $a.closest('.ac-prod-cell');
          const $tr   = $cell.closest('tr');
          const idx   = Number($tr.data('idx'));

          const label = $a.text();
          $cell.find('.ac-buscar').val(label);
          $cell.find('.ac-idp-hidden').val(id);

          if (Number.isFinite(idx)){
              AC_ROWS[idx].id_producto = id;
              if (!Number($tr.find('.ac-precio').val())) {
                AC_ROWS[idx].precio_unitario = ppv;
                $tr.find('.ac-precio').val(ppv.toFixed(2));
              }
          }

          $tr.find('.ac-precio').trigger('input');
          hideSug();
          acCheckDirty(); // cambio real
        });

        // cerrar sugerencias si se clickea fuera
        $(document).on('click', function(e){
          if (!$(e.target).closest('.ac-prod-cell, .ac-sug').length) {
            hideSug();
          }
        });
        // Ocultar al cerrar cualquier modal
        $(document).on('hidden.bs.modal', '.modal', hideSug);

        /*********************************************************
         * RENDER / SYNC DE FILAS DETALLE (validación cantidad ≥ 1)
         *********************************************************/
        function normalizaCantidad(v){
          v = Number(v);
          if (!isFinite(v) || v <= 0) return 1;
          return Math.floor(v); // enteros
        }

        function acRender() {
          const $tb = $('#ac-tbody');
          $tb.empty();
          let total = 0;

          if (AC_ROWS.length === 0) {
              $tb.append(`<tr><td colspan="5" class="text-center text-muted">Sin renglones. Agrega al menos uno.</td></tr>`);
          } else {
              AC_ROWS.forEach((r, idx) => {
                const cant = normalizaCantidad(r.cantidad);
                const prec = Number(r.precio_unitario || 0);
                const imp  = cant * prec;
                total += imp;

                $tb.append(`
                  <tr data-idx="${idx}">
                    <td class="ac-prod-cell" style="position:relative;">
                        <input type="hidden" class="ac-idp-hidden" value="${r.id_producto||''}">
                        <input type="text" class="form-control form-control-sm ac-buscar" placeholder="Buscar código o nombre…" value="${r.label||''}">
                        <div class="ac-sug" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:1050; background:#fff; border:1px solid #e0e0e0; max-height:220px; overflow:auto;"></div>
                    </td>
                    <td><input type="number" class="form-control form-control-sm ac-cant" min="1" step="1" value="${cant}" placeholder="1"></td>
                    <td><input type="number" class="form-control form-control-sm ac-precio" min="0" step="0.01" value="${prec||''}" placeholder="0.00"></td>
                    <td class="text-right align-middle ac-imp">${mxn(imp)}</td>
                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-outline-danger btn-sm ac-del" title="Eliminar">
                          <i class="mdi mdi-delete"></i>
                        </button>
                    </td>
                  </tr>
                `);
              });
          }
          $('#ac-total').text(mxn(total));
        }

        // Sincroniza inputs con AC_ROWS y recalcula totales
        $(document).on('input', '#ac-tbody .ac-cant, #ac-tbody .ac-precio, #ac-tbody .ac-buscar', function(){
          const $tr = $(this).closest('tr');
          const idx = Number($tr.data('idx'));
          if (!Number.isFinite(idx)) return;

          const idpHidden = Number($tr.find('.ac-idp-hidden').val());
          const cant  = normalizaCantidad($tr.find('.ac-cant').val());
          let   precio= Number($tr.find('.ac-precio').val());
          if (!isFinite(precio) || precio < 0) precio = 0;

          AC_ROWS[idx].id_producto     = idpHidden > 0 ? idpHidden : null;
          AC_ROWS[idx].cantidad        = cant;
          AC_ROWS[idx].precio_unitario = precio; // PPV capturado
          AC_ROWS[idx].label           = $tr.find('.ac-buscar').val();

          const imp = (AC_ROWS[idx].cantidad || 1) * (AC_ROWS[idx].precio_unitario || 0);
          $tr.find('.ac-imp').text(mxn(imp));
          const total = AC_ROWS.reduce((s,x)=> s + (normalizaCantidad(x.cantidad)*Number(x.precio_unitario||0)), 0);
          $('#ac-total').text(mxn(total));

          acCheckDirty(); // actualizar estado del botón
        });

        // eliminar fila
        $(document).on('click', '#ac-tbody .ac-del', function(){
          const idx = Number($(this).closest('tr').data('idx'));
          if (!Number.isFinite(idx)) return;
          AC_ROWS.splice(idx, 1);
          acRender();
          acCheckDirty();
        });

        // agregar/limpiar filas
        $('#ac-btn-agregar').on('click', function(){
          AC_ROWS.push({ id_producto:null, cantidad:1, precio_unitario:null, label:'' });
          acRender();
          acCheckDirty();
        });
        $('#ac-btn-limpiar').on('click', function(){
          AC_ROWS = [];
          acRender();
          acCheckDirty();
        });

        /*********************************************************
         * SNAPSHOT y DETECCIÓN DE CAMBIOS
         *********************************************************/
        function normCant(v){ v = Number(v); return (!isFinite(v) || v <= 0) ? 1 : Math.floor(v); }
        function normDet(d){
          return { id_producto: Number(d.id_producto || 0), cantidad: normCant(d.cantidad), precio_unitario: Number(d.precio_unitario || 0) };
        }
        function normalizeDetails(arr){
          const a = (arr || []).map(normDet);
          a.sort((x,y)=>{
            if (x.id_producto !== y.id_producto) return x.id_producto - y.id_producto;
            if (x.cantidad !== y.cantidad) return x.cantidad - y.cantidad;
            return x.precio_unitario - y.precio_unitario;
          });
          return a;
        }
        function acSnapshotActual(){
          return {
            id_proveedor: Number($('#ac-proveedor').val() || 0),
            folio_factura: ($('#ac-folio').val() || '').trim(),
            fecha_factura: ($('#ac-fecha').val() || '').trim(),
            estatus: ($('#ac-estatus').val() || '').trim(),
            detalles: normalizeDetails(AC_ROWS)
          };
        }
        function deepEqual(a,b){ try{ return JSON.stringify(a) === JSON.stringify(b); }catch{ return false; } }
        function headerChanged(snap, now){
          if (!snap || !now) return false;
          return snap.id_proveedor !== now.id_proveedor ||
                 snap.folio_factura !== now.folio_factura ||
                 snap.fecha_factura !== now.fecha_factura ||
                 snap.estatus !== now.estatus;
        }
        function detailsChanged(snap, now){
          if (!snap || !now) return false;
          return !deepEqual(normalizeDetails(snap.detalles), normalizeDetails(now.detalles));
        }
        function acCheckDirty(){
          if (!AC_MODO_EDICION){ $('#ac-btn-guardar').prop('disabled', false).attr('title',''); return; }
          if (!AC_SNAPSHOT){ $('#ac-btn-guardar').prop('disabled', true).attr('title','Sin cambios'); return; }
          const now = acSnapshotActual();
          const changed = headerChanged(AC_SNAPSHOT, now) || detailsChanged(AC_SNAPSHOT, now);
          $('#ac-btn-guardar').prop('disabled', !changed).attr('title', changed ? '' : 'Sin cambios');
        }

        // Encabezado: detectar cambios y habilitar botón
        $(document).on('input change', '#ac-proveedor, #ac-folio, #ac-fecha, #ac-estatus', acCheckDirty);

        /*********************************************************
         * GUARDAR (CREAR / ACTUALIZAR)
         *********************************************************/
        $('#ac-btn-guardar').off('click').on('click', function(){
          const $btn = $(this);
          const original = $btn.html();
          $('#ac-error').hide().text('');

          const isEdicion = !!AC_MODO_EDICION;
          const idEdicion = AC_ID_EDICION;

          const now  = acSnapshotActual();
          const snap = AC_SNAPSHOT || null;

          // Si editas y no hay cambios → no enviar
          if (isEdicion && snap && !headerChanged(snap, now) && !detailsChanged(snap, now)) {
            return;
          }

          // Validaciones encabezado
          const id_proveedor = Number($('#ac-proveedor').val());
          if (!id_proveedor){
            $('#ac-error').show().text('Selecciona un proveedor.');
            return;
          }

          // Folio obligatorio SOLO al crear
          const folioVal = ($('#ac-folio').val() || '').trim();
          if (!isEdicion && !folioVal){
            $('#ac-error').show().text('El folio de la factura es obligatorio.');
            $('#ac-folio').addClass('is-invalid').focus();
            return;
          } else {
            $('#ac-folio').removeClass('is-invalid');
          }

          // Encabezado
          const compra = {
            id_proveedor,
            folio_factura: (folioVal || null),
            fecha_factura: ($('#ac-fecha').val() || null),
            estatus: ($('#ac-estatus').val() || 'Pendiente')
          };

          // ¿Cambió detalle?
          const detChanged = isEdicion ? detailsChanged(snap, now) : true; // en crear siempre va detalle

          // Validar detalle solo si se enviará
          let filasValidas = [];
          if (detChanged) {
            $('#ac-tbody tr').removeClass('table-danger');

            const filasMap = (AC_ROWS || []).map((r,i)=>({
              _idx:i,
              id_producto: Number(r.id_producto),
              cantidad: normCant(r.cantidad),
              precio_unitario: Number(r.precio_unitario)
            }));

            filasMap.forEach(f=>{
              const inval = !(f.id_producto>0) || !(f.precio_unitario>0);
              if (inval) $('#ac-tbody tr[data-idx="'+f._idx+'"]').addClass('table-danger');
            });

            filasValidas = filasMap.filter(x => (x.id_producto>0) && (x.cantidad>=1) && (x.precio_unitario>0));
            if (!filasValidas.length){
              $('#ac-error').show().text('Agrega al menos un renglón válido (producto, cantidad ≥ 1 y PPV > 0).');
              return;
            }
          }

          // Payload y URL según caso
          let payload, url;
          if (isEdicion) {
            url = '<?= BASE_URL ?>/controllers/ComprasController.php?accion=actualizar';
            payload = detChanged
              ? { id_compra: idEdicion, compra, detalles: filasValidas, reemplazar_detalles: true }
              : { id_compra: idEdicion, compra }; // solo encabezado
          } else {
            url = '<?= BASE_URL ?>/controllers/ComprasController.php?accion=crear';
            payload = { compra, detalles: filasValidas };
          }

          // Envío protegido
          $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span><span class="txt">Guardando…</span>');

          $.ajax({
            url,
            method: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            dataType: 'json'
          })
          .done(function(resp){
            if (resp && resp.ok === true) {
              // Solo en éxito reseteamos modo/snapshot
              AC_MODO_EDICION = false;
              AC_ID_EDICION   = null;
              AC_SNAPSHOT     = null;

              toastr.success(isEdicion ? 'Compra actualizada correctamente' : 'Compra creada correctamente');
              $('#modalAgregarCompra').modal('hide');
              if (typeof cargarCompras === 'function') cargarCompras(1);
            } else {
              $('#ac-error').show().text(resp?.msg || 'Operación no completada.');
            }
          })
          .fail(function(xhr){
            $('#ac-error').show().text(xhr?.responseText || 'Error al conectar con el servidor.');
          })
          .always(function(){
            $btn.prop('disabled', false).html(original);
          });
        });

      }); // ready

      // Utilidad del ícono X en filtros
      function clearField(id){ const el = document.getElementById(id); if (el){ el.value=''; el.dispatchEvent(new Event('change')); } }
    </script>
  </body>
</html>
