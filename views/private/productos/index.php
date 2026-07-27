<?php
$titulo = "Inventarios";
$modulo = "Productos";
$subtitulo = ""; // puedes dejarlo vacío si no se necesita
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
    <title>Productos | REFASOFT-V4</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
    <meta content="Coderthemes" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">

    <!-- plugin css -->
    <link href="<?= BASE_URL ?>/assets/libs/jquery-vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/libs/select2/select2.min.css" rel="stylesheet" type="text/css" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <style>
      .clean-filter .input-group-text{ cursor:pointer; }
      .badge-pill{ border-radius: 50rem; }
      /* Evita que la .table-responsive "corte" el dropdown vertical */
      .table-responsive { overflow-y: visible !important; }
      /* Asegura que el menú quede por encima de otros elementos si es necesario */
      .table-responsive .dropdown-menu { z-index: 2000; }
    </style>
  </head>

  <body>
    <?php include_once __DIR__ . '/../../../includes/header.php'; ?>

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
        <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

        <!-- =================== Filtros =================== -->
        <div class="card-header" style="border-color:darkgray; border-style:dotted;">
          <h5>Filtros</h5>
          <div class="row">
            <div class="col-lg-12">
              <div class="row">

                <!-- Código -->
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="Codigo" class="control-label">Código</label>
                    <div class="input-group">
                      <input type="text" id="Codigo" name="Codigo" class="form-control filtrar" placeholder="A0001000, SKU, etc.">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Codigo')"></i></span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Descripción -->
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="Descripcion" class="control-label">Descripción</label>
                    <div class="input-group">
                      <input type="text" id="Descripcion" name="Descripcion" class="form-control filtrar" placeholder="Filtro de aceite, Anticongelante...">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Descripcion')"></i></span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Grupo (NUEVO) -->
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="Grupo" class="control-label">Grupo</label>
                    <div class="input-group">
                      <select id="Grupo" name="Grupo" class="form-control filtrar" disabled>
                        <option value="">-- Todos --</option>
                      </select>
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text">
                          <i class="mdi mdi-close-circle text-danger" onclick="clearField('Grupo')"></i>
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
                        <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Proveedor')"></i></span>
                      </div>
                    </div>
                  </div>
                </div>

              </div><!-- row -->
            </div>
          </div>
        </div>
        <!-- =================== /Filtros =================== -->

        <!-- =================== Tabla Productos =================== -->
        <div class="row">
          <div class="col-12">
            <div class="card-box">
             <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="header-title">Listado de Productos</h4>

                <div class="btn-group">
                   <!-- Botón Agregar producto -->
                  <button id="btnAgregarProducto"
                          type="button"
                          class="btn btn-primary btn-sm waves-effect waves-light"
                          data-toggle="modal"
                          data-target="#modalProducto">
                    <i class="mdi mdi-plus"></i> Agregar producto
                  </button>
                  <!-- Botón Exportar Excel -->
                  <button id="btnExportarExcel"
                          type="button"
                          class="btn btn-success btn-sm waves-effect waves-light">
                    <i class="mdi mdi-file-excel"></i> Exportar Excel
                  </button>

                 
                </div>
              </div>

              <div class="table-responsive">
                <table id="tablaProductos" class="table table-bordered table-hover table-striped">
                  <thead>
                    <tr>
                      <th class="text-center" style="width:120px;">Código</th>
                      <th>Descripción</th>
                      <th class="text-center" style="width:220px;">Proveedor</th>
                      <th class="text-center" style="width:120px;">Stock</th>
                      <th class="text-center" style="width:140px;">Precio Público</th>
                      <th class="text-center" style="width:140px;">Precio Taller</th>
                      <th class="text-center" style="width:140px;">Precio Proveedor</th>
                      <th class="text-center" style="width:140px;">Costo Neto</th>
                      <th class="text-center" style="width:90px;">Acciones</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <!-- Paginador -->
              <div class="row align-items-center justify-content-between mt-2">
                <div class="col-md-6">
                  <div id="infoProductos" class="dataTables_info" role="status" aria-live="polite"></div>
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
        <!-- =================== /Tabla Productos =================== -->

        <!-- =================== Modal Detalle =================== -->
        <?php include_once __DIR__ . '/../productos/modales/detalles.php'; ?>  
        <!-- =================== /Modal Detalle =================== -->

        <!-- =================== Modal Agregar =================== -->
        <?php include_once __DIR__ . '/../productos/modales/agregar.php'; ?>  
        <!-- =================== /Modal Agregar =================== -->

        <!-- =================== Modal Editar =================== -->
        <?php include_once __DIR__ . '/../productos/modales/editar.php'; ?>  
        <!-- =================== /Modal Editar =================== -->

        <!-- =================== Modal Eliminar =================== -->
        <?php include_once __DIR__ . '/../productos/modales/eliminar.php'; ?>  
        <!-- =================== /Modal Eliminar =================== -->

        <!-- =================== Modal Etiqueta (ya agregado por ti) =================== -->
        <?php include_once __DIR__ . '/../productos/modales/etiqueta.php'; ?>  
        <!-- =================== /Modal Etiqueta =================== -->

      </div><!-- container-fluid -->
    </div><!-- wrapper -->

    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>

    <div class="rightbar-overlay"></div>

    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
    <script src="<?= BASE_URL ?>/assets/libs/select2/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
    /* =====================================================================================
    LISTADO DE PRODUCTOS + FILTROS + DETALLE
    ===================================================================================== */
    $(function(){
      let paginaActual = 1;
      const limitePorPagina = 10;

      cargarProveedoresSelect();
      cargarUnidadesSelect();
      cargarGruposSelectFiltro(); // NUEVO
      cargarProductos(paginaActual);

      function mxn(v){ return Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'}); }
      function ymdToEs(ymd){ if(!ymd) return '—'; const [y,m,d]=String(ymd).split('-'); return `${d}/${m}/${y}`; }

      function cargarProveedoresSelect(opts={})
      {
        const { selectId='Proveedor', selected='', incluirTodos=true, limite=200, q='' } = opts;
        const $sel = $('#'+selectId);
        if ($sel.length === 0) return;
        $sel.prop('disabled', true).html(incluirTodos? '<option value="">-- Todos --</option>' : '');

        $.ajax({
          url: '<?= BASE_URL ?>/controllers/ProveedoresController.php',
          method: 'GET', dataType: 'json',
          data: { accion:'listar-min', limite, q }
        })
        .done(function(resp){
          const arr = resp?.data || [];
          let html = incluirTodos? '<option value="">-- Todos --</option>': '';
          arr.forEach(p => {
            if(p.id_proveedor && p.nombre){
              const sel = (String(p.id_proveedor) === String(selected)) ? ' selected' : '';
              html += `<option value="${p.id_proveedor}"${sel}>${p.nombre}</option>`;
            }
          });
          $sel.html(html).prop('disabled', false);
        })
        .fail(function(){ $sel.prop('disabled', false); });
      }

      function cargarUnidadesSelect(opts={})
      {
        const { selectId='Unidad', selected='', incluirTodos=true, limite=500, q='' } = opts;
        const $sel = $('#'+selectId);
        if ($sel.length === 0) return;
        $sel.prop('disabled', true).html(incluirTodos? '<option value="">-- Todos --</option>' : '');

        $.ajax({
          url: '<?= BASE_URL ?>/controllers/UnidadesSatController.php',
          method: 'GET', dataType: 'json',
          data: { accion:'listar-min', limite, q }
        })
        .done(function(resp){
          const arr = resp?.data || [];
          let html = incluirTodos? '<option value="">-- Todos --</option>': '';
          arr.forEach(u => {
            const id = u.id_unidad_sat || u.id || u.IdUnidad;
            const nom = u.descripcion || u.nombre || u.Unidad;
            if(id && nom){
              const sel = (String(id) === String(selected)) ? ' selected' : '';
              html += `<option value="${id}"${sel}>${nom}</option>`;
            }
          });
          $sel.html(html).prop('disabled', false);
        })
        .fail(function(){ $sel.prop('disabled', false); });
      }

      // ===== NUEVO: cargar grupos para el filtro =====
      function cargarGruposSelectFiltro(opts={})
      {
        const { selectId='Grupo', selected='', incluirTodos=true, limite=500, q='' } = opts;
        const $sel = $('#'+selectId);
        if (!$sel.length) return;
        $sel.prop('disabled', true).html(incluirTodos? '<option value="">-- Todos --</option>' : '');

        $.ajax({
          url: '<?= BASE_URL ?>/controllers/CatGruposController.php',
          method: 'GET', dataType: 'json',
          data: { accion:'listar-min', limite, q }
        })
        .done(function(resp){
          const arr = resp?.data || [];
          let html = incluirTodos? '<option value="">-- Todos --</option>': '';
          arr.forEach(g => {
            if (g.id_grupo && g.nombre_grupo){
              const sel = (String(g.id_grupo) === String(selected)) ? ' selected' : '';
              html += `<option value="${g.id_grupo}"${sel}>${g.nombre_grupo}</option>`;
            }
          });
          $sel.html(html).prop('disabled', false);
        })
        .fail(function(){ $sel.prop('disabled', false); });
      }

      function cargarProductos(pagina)
      {
        const codigo      = $('#Codigo').val();
        const descripcion = $('#Descripcion').val();
        const idProv      = $('#Proveedor').val();
        const idGrupo     = $('#Grupo').val(); // NUEVO

        $.ajax({
          url: '<?= BASE_URL ?>/controllers/ProductosController.php',
          method: 'POST',
          dataType: 'json',
          data: {
            accion: 'listar',
            pagina: pagina,
            limite: limitePorPagina,
            codigo: codigo,
            descripcion: descripcion,
            id_proveedor: idProv || '',
            id_grupo: idGrupo || '' // NUEVO
          }
        })
        .done(function(resp){
          const productos = resp?.data || [];
          const total     = parseInt(resp?.total || 0, 10);
          renderizarTabla(productos);

          const desde = (pagina - 1) * limitePorPagina + 1;
          const hasta = Math.min(pagina * limitePorPagina, total);
          $('#infoProductos').text(`Mostrando ${total === 0 ? 0 : desde} a ${hasta} de ${total} productos`);

          configurarPaginacion(pagina, total, limitePorPagina);
        })
        .fail(function(){
          toastr.error('Error al cargar los productos.');
        });
      }

      // Exponer para que otros bloques puedan llamar cargarProductos
      window.cargarProductos = cargarProductos;

      function renderizarTabla(productos){
        let tbody='';
        if (!productos.length){
          tbody = '<tr><td colspan="9" class="text-center">No hay productos</td></tr>';
        } else {
          productos.forEach(p => {
            const cod  = p.codigo || ('#'+(p.id_producto||''));
            const desc = p.descripcion || '—';
            const prov = p.proveedor || '—';
            const stk  = Number(p.stock_actual ?? 0);
            const pb   = Number(p.precio_publico ?? 0);
            const pt   = Number(p.precio_taller ?? 0);
            const ppv  = Number(p.precio_proveedor ?? 0);
            const cn   = Number(p.costo_neto ?? 0);

            tbody += `
              <tr>
                <td class="text-center"><b>${cod}</b></td>
                <td>${desc}</td>
                <td class="text-center">${prov}</td>
                <td class="text-center">${stk.toLocaleString('es-MX')}</td>
                <td class="text-center"><b>${mxn(pb)}</b></td>
                <td class="text-center"><b>${mxn(pt)}</b></td>
                <td class="text-center"><b>${mxn(ppv)}</b></td>
                <td class="text-center"><b>${mxn(cn)}</b></td>
                <td class="text-center">
                  <div class="btn-group dropdown">
                    <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false">
                      <i class="mdi mdi-dots-horizontal"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                      <a class="dropdown-item accion-ver-detalle" href="#" data-id="${p.id_producto}">
                        <i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>Ver Detalle
                      </a>
                      <a class="dropdown-item accion-editar" href="#" data-id="${p.id_producto}">
                        <i class="mdi mdi-pencil mr-2 text-muted font-18 vertical-middle"></i>Editar
                      </a>

                      <!-- NUEVO: acción Etiquetas -->
                      <a class="dropdown-item accion-etiquetas" href="#" data-id="${p.id_producto}">
                        <i class="mdi mdi-barcode mr-2 text-muted font-18 vertical-middle"></i>Etiquetas
                      </a>

                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item text-danger accion-eliminar" href="#" data-id="${p.id_producto}" data-cod="${cod}">
                        <i class="mdi mdi-trash-can-outline mr-2 text-danger font-18 vertical-middle"></i>Eliminar
                      </a>
                    </div>
                  </div>
                </td>
              </tr>`;
          });
        }
        $('#tablaProductos tbody').html(tbody);
      }

      function configurarPaginacion(currentPage, totalItems, itemsPerPage=10)
      {
        const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
        const $ul = $('#pagination');
        const maxVisiblePages = 5;
        $ul.empty();
        if (totalPages <= 1){ $ul.closest('nav').hide(); return; } else { $ul.closest('nav').show(); }

        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage   = Math.min(totalPages, startPage + maxVisiblePages - 1);
        if (endPage - startPage + 1 < maxVisiblePages){ startPage = Math.max(1, endPage - maxVisiblePages + 1); }

        if (currentPage > 1){
          $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="1">Primera</a></li>`);
          $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage-1}">&laquo; Anterior</a></li>`);
        }
        for (let i=startPage; i<=endPage; i++){
          const active = (i===currentPage)? 'active' : '';
          $ul.append(`<li class="page-item ${active}"><a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a></li>`);
        }
        if (currentPage < totalPages){
          $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${currentPage+1}">Siguiente &raquo;</a></li>`);
          $ul.append(`<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="${totalPages}">Última</a></li>`);
        }

        $ul.off('click','a.page-link').on('click','a.page-link', function(e){
          e.preventDefault();
          const page = Number($(this).data('page'));
          if (Number.isFinite(page)) { paginaActual = page; cargarProductos(paginaActual); }
        });
      }

      // Filtros
      $(".filtrar")
        .change(function(){
          const $el = $(this);
          if(($el.is(':checkbox') && $el.is(':checked')) || ($el.val() && $el.val().length>0)) $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
          $el.blur();
          setTimeout(function(){ cargarProductos(1); }, 200);
        })
        .keypress(function(e){ if (e.charCode == 13) cargarProductos(1); })
        .keyup(function(){
          if ($(this).val().length > 0) $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
        })
        .click(function(){ if ($(this).is(":button")) cargarProductos(1); });

      $(".clean-filter").click(function(){
        const $el = $(this).closest('.input-group, .form-group').find('.filtrar');
        if ($el.is(':checkbox')){ $el.prop('checked', false).trigger('change'); }
        else { $el.val('').trigger('change'); if ($el.hasClass('select2')) $el.select2('val', 0); }
        cargarProductos(1);
      });

      // Modal Detalle
      $(document).on('click', 'a.accion-ver-detalle', function(e)
      {
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;

        $('#det-error').hide().text('');
        $('#det-contenido').hide();
        $('#det-loader').show();
        $('#modalDetalleProducto').modal('show');

        $.ajax({
          url: '<?= BASE_URL ?>/controllers/ProductosController.php',
          method: 'GET', dataType: 'json',
          data: { accion: 'detalle', id_producto: id }
        })
        .done(function(resp){
          const p = resp?.data || null;
          if (!p){
            $('#det-loader').hide();
            $('#det-error').show().text('No se encontró el producto.');
            return;
          }

          $('#det-codigo').text(p.codigo || ('#'+(p.id_producto||'')));
          $('#det-descripcion').text(p.descripcion || '—');
          $('#det-proveedor').text(p.proveedor || p.nombre_proveedor || (p.id_proveedor ? `#${p.id_proveedor}` : '—'));
          $('#det-unidad').text(p.unidad_sat || p.descripcion_unidad || (p.id_unidad_sat ? `#${p.id_unidad_sat}` : '—'));
          $('#det-clave').text(p.clave_prod_serv_sat || '—');
          $('#det-creado').text(p.fecha_creacion ? p.fecha_creacion : '—');
          $('#det-grupo').text(p.grupo || '—'); // NUEVO (si existe el elemento en el modal)

          $('#det-stock').text(Number(p.stock_actual ?? 0).toLocaleString('es-MX'));
          $('#det-stock-min').text(p.stock_minimo ?? '—');
          $('#det-stock-max').text(p.stock_maximo ?? '—');

          $('#det-precio-publico').text(mxn(p.precio_publico ?? 0));
          $('#det-precio-proveedor').text(mxn(p.precio_proveedor ?? 0));
          $('#det-costo-neto').text(mxn(p.costo_neto ?? 0));
          $('#det-precio-taller').text(mxn(p.precio_taller ?? 0));

          const ubic = [p.piso ?? 0, p.pasillo ?? 0, p.estante ?? 0, p['peldaño'] ?? 0].join(' / ');
          $('#det-ubicacion').text(ubic);

          $('#det-loader').hide();
          $('#det-contenido').show();
        })
        .fail(function(){
          $('#det-loader').hide();
          $('#det-error').show().text('Error al cargar el detalle.');
        });
      });
    });

    // util para filtros
    function clearField(id)
    {
      const el = document.getElementById(id);
      if (!el) return;
      if(el.type==='checkbox'){ el.checked=false; } else { el.value=''; }
      el.dispatchEvent(new Event('change'));
    }

    function initGrupoSelect2(modalSelector, selectSelector) {
      const $modal = $(modalSelector);
      if (!$modal.length || typeof $.fn.select2 !== 'function') return;

      const $select = $modal.find(selectSelector);
      if (!$select.length) return;

      $select.each(function(){
        const $current = $(this);
        if ($current.hasClass('select2-hidden-accessible')) {
          $current.select2('destroy');
        }

        $current.select2({
          width: '100%',
          placeholder: 'Selecciona un grupo',
          allowClear: true,
          dropdownParent: $modal
        });
      });
    }

    function destroyGrupoSelect2(modalSelector, selectSelector) {
      const $modal = $(modalSelector);
      if (!$modal.length || typeof $.fn.select2 !== 'function') return;

      $modal.find(selectSelector).each(function(){
        const $current = $(this);
        if ($current.hasClass('select2-hidden-accessible')) {
          $current.select2('destroy');
        }
      });
    }


    /* =====================================================================================
      MODAL AGREGAR PRODUCTO (validación + cálculo en tiempo real + recarga)
      ===================================================================================== */

    // Bandera global: recargar listado al cerrar si guardó OK
    let _reloadOnClose = false;

    // Resetear modal y cargar catálogos al abrir
    $('#modalProducto').on('show.bs.modal', function () {
      $('#formProducto')[0].reset();
      $('#p_activo').prop('checked', true);
      $('#modalProductoLabel').text('Agregar producto');
      $('#p_objeto_imp').val('02');
      $('#p_tasa_iva').val('0.160000');
      $('#p_clave_prod_serv_sat').val('');
      setSubmitEnabled('p', false);

      // Limpiar marcas de error previas
      $('#formProducto .is-invalid').removeClass('is-invalid');

      // Cargar proveedores
      $.post('<?= BASE_URL ?>/controllers/ProveedoresController.php', { accion: 'listar-min' })
        .done(resp => {
          const $sel = $('#p_id_proveedor').empty().append('<option value="">— Selecciona —</option>');
          (resp?.data || []).forEach(p => $sel.append(`<option value="${p.id_proveedor}">${p.nombre}</option>`));
        });

      // Cargar unidades SAT + fallback
      $.post('<?= BASE_URL ?>/controllers/UnidadesSatController.php', { accion: 'listar_min' })
        .done(resp => {
          const $sel = $('#p_id_unidad_sat').empty().append('<option value="">— Selecciona —</option>');
          (resp?.data || []).forEach(u => $sel.append(`<option value="${u.id_unidad_sat}">${u.clave} - ${u.descripcion}</option>`));
        })
        .always(() => {
          const $sel = $('#p_id_unidad_sat');
          if ($sel.find('option').length <= 1) {
            $.post('<?= BASE_URL ?>/controllers/UnidadesSatController.php', { accion: 'listar-min' })
              .done(resp => {
                $sel.empty().append('<option value="">— Selecciona —</option>');
                (resp?.data || []).forEach(u => $sel.append(
                  `<option value="${u.id_unidad_sat}">${u.clave} - ${u.descripcion}</option>`
                ));
              });
          }
        });

      // Cargar grupos (NUEVO) y preseleccionar "SIN GRUPO" si existe
      $.getJSON('<?= BASE_URL ?>/controllers/CatGruposController.php', { accion:'listar-min', limite: 1000 })
        .done(function(resp){
          const $sel = $('#p_id_grupo');
          if (!$sel.length) return; // por si el modal aún no tiene el campo
          $sel.empty().append('<option value="">— Selecciona —</option>');
          const arr = resp?.data || [];
          let idSin = '';
          arr.forEach(g => {
            if (g.id_grupo && g.nombre_grupo){
              if ((g.nombre_grupo||'').toUpperCase()==='SIN GRUPO') idSin = g.id_grupo;
              $sel.append(`<option value="${g.id_grupo}">${g.nombre_grupo}</option>`);
            }
          });
          if (idSin) {
            $sel.val(String(idSin)).trigger('change');
          }

          if ($('#modalProducto').hasClass('show')) {
            initGrupoSelect2('#modalProducto', '#p_id_grupo');
          }
        });

    })
    .on('shown.bs.modal', function(){
      initGrupoSelect2('#modalProducto', '#p_id_grupo');

      // Reiniciar flags y, si ya hay PPV, calcular al mostrar
      _manual.cn = _manual.pt = _manual.pb = false;
      if (parseFloat($('#p_precio_proveedor').val() || 0) > 0) debouncedCalc();
    })
    .on('hidden.bs.modal', function(){
      destroyGrupoSelect2('#modalProducto', '#p_id_grupo');

      // Recargar listado solo si guardamos con éxito
      if (_reloadOnClose) {
        _reloadOnClose = false;
        reloadProductos();
      }
    });

    // Helper para recargar listado
    function reloadProductos(){
      // DataTables
      if ($.fn.DataTable && $.fn.dataTable.isDataTable('#tablaProductos')) {
        $('#tablaProductos').DataTable().ajax.reload(null, false);
        return;
      }
      // Paginador propio
      const $active = $('#pagination .page-item.active a');
      const page = parseInt($active?.data('page') || $active?.text() || 1, 10) || 1;

      if (typeof window.cargarProductos === 'function') {
        window.cargarProductos(page);  // usar la función expuesta
      } else {
        location.reload();             // último recurso
      }
    }


    async function getClaveSatPorGrupo(idGrupo){
      if (!idGrupo) return { ok:false, message:'Grupo es requerido.' };
      try {
        const resp = await $.ajax({
          url: '<?= BASE_URL ?>/controllers/CatGruposController.php',
          method: 'GET', dataType: 'json',
          data: { accion: 'getById', id_grupo: idGrupo }
        });
        return resp || { ok:false, message:'No fue posible consultar el grupo.' };
      } catch(e) {
        return { ok:false, message:'Error al consultar la Clave SAT del grupo.' };
      }
    }

    function alertClaveSatInvalida(message){
      const txt = message || 'El grupo seleccionado no tiene Clave SAT configurada.';
      if (window.Swal && typeof window.Swal.fire === 'function') {
        window.Swal.fire({ icon: 'error', title: 'Grupo sin clave SAT', text: txt });
      } else {
        toastr.error(txt);
      }
    }

    function setSubmitEnabled(prefix, enabled){
      const formId = prefix === 'e' ? '#formProductoEdit' : '#formProducto';
      const $btn = $(formId + ' button[type="submit"]');
      if ($btn.length) $btn.prop('disabled', !enabled);
    }

    async function sincronizarClaveSatGrupo(prefix, mostrarError=true){
      const $grupo = $('#'+prefix+'_id_grupo');
      const $clave = $('#'+prefix+'_clave_prod_serv_sat');
      if (!$grupo.length || !$clave.length) return false;

      const idGrupo = ($grupo.val() || '').toString().trim();
      if (!idGrupo){
        $clave.val('');
        setSubmitEnabled(prefix, false);
        return false;
      }

      const resp = await getClaveSatPorGrupo(idGrupo);
      const clave = (resp?.data?.clave_h || '').toString().trim();
      if (!resp?.ok || !clave) {
        $clave.val('');
        setSubmitEnabled(prefix, false);
        if (mostrarError) {
          alertClaveSatInvalida(resp?.message || resp?.msg);
        }
        return false;
      }

      $clave.val(clave);
      setSubmitEnabled(prefix, true);
      return true;
    }

    $('#p_id_grupo').on('change', function(){ sincronizarClaveSatGrupo('p'); });
    $(document).on('change', '#e_id_grupo', function(){ sincronizarClaveSatGrupo('e'); });

    // Enviar a backend con validaciones
    $('#formProducto').on('submit', async function (e) {
      e.preventDefault();

      const errores = [];
      let firstInvalid = null;

      const setInvalid = (sel) => { $(sel).addClass('is-invalid'); if (!firstInvalid) firstInvalid = sel; };
      const clearInvalids = () => $('#formProducto .is-invalid').removeClass('is-invalid');
      clearInvalids();

      const valTrim = sel => ($(sel).val() || '').toString().trim();
      const num2 = v => {
        const x = parseFloat((v||'').toString().replace(',', '.'));
        return isNaN(x) ? NaN : x;
      };
      const int0 = v => {
        const n = parseInt((v||'0'), 10);
        return isNaN(n) ? NaN : n;
      };

      // Requeridos básicos
      const idProv = valTrim('#p_id_proveedor');
      if (!idProv) { errores.push('Proveedor es requerido.'); setInvalid('#p_id_proveedor'); }

      const idUnidad = valTrim('#p_id_unidad_sat');
      if (!idUnidad) { errores.push('Unidad SAT es requerida.'); setInvalid('#p_id_unidad_sat'); }

      // Grupo requerido (NUEVO)
      const idGrupo = valTrim('#p_id_grupo');
      if (!idGrupo) { errores.push('Grupo es requerido.'); setInvalid('#p_id_grupo'); }

      const codigo = valTrim('#p_codigo');
      if (!codigo) {
        errores.push('Código es requerido.');
        setInvalid('#p_codigo');
      } else {
        const reCod = /^[A-Za-z0-9._\-\/]{1,50}$/;
        if (!reCod.test(codigo)) {
          errores.push('Código inválido. Permitidos A-Z, 0-9, ".", "-", "_", "/". Máx. 50.');
          setInvalid('#p_codigo');
        }
      }

      const descripcion = valTrim('#p_descripcion');
      if (!descripcion) { errores.push('Descripción es requerida.'); setInvalid('#p_descripcion'); }

      // Numéricos requeridos (> 0 en precios)
      const ppv = num2($('#p_precio_proveedor').val());
      if (isNaN(ppv) || ppv <= 0) { errores.push('Precio proveedor es requerido y debe ser > 0.'); setInvalid('#p_precio_proveedor'); }

      const cn = num2($('#p_costo_neto').val());
      if (isNaN(cn) || cn <= 0) { errores.push('Costo neto es requerido y debe ser > 0.'); setInvalid('#p_costo_neto'); }

      const pt = num2($('#p_precio_taller').val());
      if (isNaN(pt) || pt <= 0) { errores.push('Precio taller es requerido y debe ser > 0.'); setInvalid('#p_precio_taller'); }

      const pb = num2($('#p_precio_publico').val());
      if (isNaN(pb) || pb <= 0) { errores.push('Precio público es requerido y debe ser > 0.'); setInvalid('#p_precio_publico'); }

      // Relación de precios
      if (!isNaN(pb) && !isNaN(pt) && pb < pt) {
        errores.push('Precio Público no debe ser menor que Precio Taller.');
        setInvalid('#p_precio_publico');
      }

      // Stocks
      const sa = int0($('#p_stock_actual').val());
      const smax = int0($('#p_stock_maximo').val());
      const smin = int0($('#p_stock_minimo').val());

      if (isNaN(sa) || sa < 0)   { errores.push('Stock actual es requerido y debe ser entero ≥ 0.'); setInvalid('#p_stock_actual'); }
      if (isNaN(smax) || smax < 0){ errores.push('Stock máximo es requerido y debe ser entero ≥ 0.'); setInvalid('#p_stock_maximo'); }
      if (isNaN(smin) || smin < 0){ errores.push('Stock mínimo es requerido y debe ser entero ≥ 0.'); setInvalid('#p_stock_minimo'); }

      if (!isNaN(smin) && !isNaN(smax) && smin > smax) {
        errores.push('Stock mínimo no puede ser mayor que Stock máximo.');
        setInvalid('#p_stock_minimo');
      }


      await sincronizarClaveSatGrupo('p');
      const claveSat = valTrim('#p_clave_prod_serv_sat');
      if (!claveSat) {
        errores.push('Clave Prod/Serv SAT inválida para el grupo seleccionado.');
        setInvalid('#p_clave_prod_serv_sat');
      }

      const objetoImp = valTrim('#p_objeto_imp') || '02';
      const tasaIva = num2($('#p_tasa_iva').val());
      if (!objetoImp) {
        errores.push('Objeto Impuesto es requerido.');
        setInvalid('#p_objeto_imp');
      }
      if (isNaN(tasaIva)) {
        errores.push('Tasa IVA inválida.');
        setInvalid('#p_tasa_iva');
      }

      if (errores.length) {
        toastr.warning(errores.join('<br>'));
        if (firstInvalid) { try { $(firstInvalid).focus(); } catch(e){} }
        return;
      }

      const payload = {
        accion: 'crear',
        id_producto: $('#p_id_producto').val() || null,
        id_proveedor: $('#p_id_proveedor').val() || '',
        id_unidad_sat: $('#p_id_unidad_sat').val() || '',
        id_grupo: $('#p_id_grupo').val() || '', // NUEVO
        clave_prod_serv_sat: $('#p_clave_prod_serv_sat').val().trim() || '',
        objeto_imp: ($('#p_objeto_imp').val() || '02').trim(),
        tasa_iva: isNaN(tasaIva) ? 0.160000 : tasaIva,
        codigo: $('#p_codigo').val().trim(),
        descripcion: $('#p_descripcion').val().trim(),
        costo_neto: cn,
        precio_publico: pb,
        precio_taller: pt,
        precio_proveedor: ppv,
        stock_actual: isNaN(sa) ? 0 : sa,
        stock_maximo: isNaN(smax) ? 0 : smax,
        stock_minimo: isNaN(smin) ? 0 : smin,
        piso: $('#p_piso').val() || 0,
        pasillo: $('#p_pasillo').val() || 0,
        estante: $('#p_estante').val() || 0,
        peldano: $('#p_peldano').val() || 0,
        activo: $('#p_activo').is(':checked') ? 1 : 0
      };

      $.ajax({
        url: '<?= BASE_URL ?>/controllers/ProductosController.php',
        method: 'POST',
        dataType: 'json',
        data: payload
      })
      .done(resp => {
        if (resp?.ok) {
          _reloadOnClose = true;              // marcar que hay que recargar al cerrar
          $('#modalProducto').modal('hide');  // cerrar modal
          toastr.success('Producto guardado correctamente');
        } else {
          toastr.error(resp?.msg || 'No se pudo guardar el producto');
        }
      })
      .fail(() => toastr.error('Error de comunicación al guardar'));
    });

    // ============ CÁLCULO EN TIEMPO REAL (usa backend simular-precios) ============
    const _manual = { cn:false, pt:false, pb:false }; // flags: no pisar si usuario edita
    $('#p_costo_neto').on('input',     () => _manual.cn = true);
    $('#p_precio_taller').on('input',  () => _manual.pt = true);
    $('#p_precio_publico').on('input', () => _manual.pb = true);

    function debounce(fn, ms=250){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }

    let _calcXHR = null;
    function calcAutoPreciosServer(){
      const id_proveedor = $('#p_id_proveedor').val() || 0;
      const ppvRaw = ($('#p_precio_proveedor').val() || '').toString().replace(',', '.');
      const ppv = parseFloat(ppvRaw);
      if (!(ppv > 0)) return;

      if (_calcXHR && _calcXHR.readyState !== 4) { try { _calcXHR.abort(); } catch(e){} }

      _calcXHR = $.post('<?= BASE_URL ?>/controllers/ProductosController.php', {
        accion: 'simular-precios',
        id_proveedor,
        precio_proveedor: ppv
      })
      .done(resp => {
        const d = resp?.data || {};
        if (!_manual.cn && d.costo_neto != null)     $('#p_costo_neto').val(Number(d.costo_neto).toFixed(2));
        if (!_manual.pt && d.precio_taller != null)  $('#p_precio_taller').val(Number(d.precio_taller).toFixed(2));
        if (!_manual.pb && d.precio_publico != null) $('#p_precio_publico').val(Number(d.precio_publico).toFixed(2));
      });
    }
    const debouncedCalc = debounce(calcAutoPreciosServer, 250);

    // Disparadores: al teclear PPV y al cambiar proveedor
    $('#p_precio_proveedor').on('input', debouncedCalc);
    $('#p_id_proveedor').on('change', debouncedCalc);


    // ======================= ABRIR MODAL EDITAR =======================
    $(document).off('click', '.accion-editar').on('click', '.accion-editar', function(e){
      e.preventDefault();
      const id = $(this).data('id');
      if (!id) { toastr.warning('ID de producto inválido'); return; }
      abrirModalEditarProducto(id);
    });

    $('#modalProductoEdit')
      .on('shown.bs.modal', function(){
        initGrupoSelect2('#modalProductoEdit', '#e_id_grupo');
      })
      .on('hidden.bs.modal', function(){
        destroyGrupoSelect2('#modalProductoEdit', '#e_id_grupo');
      });

    function abrirModalEditarProducto(idProducto){
      const $m = $('#modalProductoEdit');
      if ($m.length === 0){
        toastr.error('No existe el modal de edición (#modalProductoEdit).');
        return;
      }

      // Reset básico
      const $f = $('#formProductoEdit')[0];
      if ($f) $f.reset();
      $('#e_id_producto').val(idProducto);
      $('#modalProductoEditLabel').text('Editar producto');
      setSubmitEnabled('e', false);
      $('#e_activo').prop('checked', true);
      $('#formProductoEdit .is-invalid').removeClass('is-invalid');

      // Abrir modal
      $m.modal('show');

      // 1) Traer detalle
      $.ajax({
        url: '<?= BASE_URL ?>/controllers/ProductosController.php',
        method: 'GET', dataType: 'json',
        data: { accion: 'detalle', id_producto: idProducto }
      })
      .done(async function(resp){
        const p = resp?.data;
        if (!p){ toastr.error('No se encontró el producto.'); return; }

        // 2) Cargar catálogos con selección actual (incluye grupo NUEVO)
        await Promise.all([
          cargarProveedoresEn('#e_id_proveedor', p.id_proveedor),
          cargarUnidadesEn('#e_id_unidad_sat', p.id_unidad_sat),
          cargarGruposEn('#e_id_grupo', p.id_grupo) // NUEVO
        ]);

        initGrupoSelect2('#modalProductoEdit', '#e_id_grupo');

        await sincronizarClaveSatGrupo('e', false);

        // 3) Rellenar campos
        if (!($('#e_clave_prod_serv_sat').val() || '').trim()) {
          $('#e_clave_prod_serv_sat').val(p.clave_prod_serv_sat || '');
        }
        $('#e_objeto_imp').val(p.objeto_imp || '02');
        $('#e_tasa_iva').val(Number(p.tasa_iva ?? 0.160000).toFixed(6));
        $('#e_codigo').val(p.codigo || '');
        $('#e_descripcion').val(p.descripcion || '');

        $('#e_precio_proveedor').val(Number(p.precio_proveedor ?? 0).toFixed(2));
        $('#e_costo_neto').val(Number(p.costo_neto ?? 0).toFixed(2));
        $('#e_precio_taller').val(Number(p.precio_taller ?? 0).toFixed(2));
        $('#e_precio_publico').val(Number(p.precio_publico ?? 0).toFixed(2));

        $('#e_stock_actual').val(parseInt(p.stock_actual ?? 0, 10));
        $('#e_stock_maximo').val(parseInt(p.stock_maximo ?? 0, 10));
        $('#e_stock_minimo').val(parseInt(p.stock_minimo ?? 0, 10));

        $('#e_piso').val(parseInt(p.piso ?? 0, 10));
        $('#e_pasillo').val(parseInt(p.pasillo ?? 0, 10));
        $('#e_estante').val(parseInt(p.estante ?? 0, 10));
        $('#e_peldano').val(parseInt(p['peldaño'] ?? p.peldano ?? 0, 10));

        $('#e_activo').prop('checked', String(p.activo) === '1');

        // Primer cálculo si ya hay PPV
        _manualEdit.cn = _manualEdit.pt = _manualEdit.pb = false;
        const ppv = parseFloat(String($('#e_precio_proveedor').val()||'').replace(',', '.'));
        if (ppv > 0) debouncedCalcEdit();
      })
      .fail(function(){ toastr.error('Error al cargar el detalle.'); });
    }

    // ======================= HELPERS DE CATÁLOGOS (EDITAR) =======================
    function cargarProveedoresEn(selector, selectedId){
      return new Promise((resolve) => {
        $.ajax({
          url: '<?= BASE_URL ?>/controllers/ProveedoresController.php',
          method: 'GET', dataType: 'json',
          data: { accion: 'listar-min', limite: 500 }
        })
        .done(function(resp){
          const arr = resp?.data || [];
          const $sel = $(selector).empty().append('<option value="">— Selecciona —</option>');
          arr.forEach(p => {
            if (p.id_proveedor && p.nombre){
              const sel = (String(p.id_proveedor) === String(selectedId)) ? ' selected' : '';
              $sel.append(`<option value="${p.id_proveedor}"${sel}>${p.nombre}</option>`);
            }
          });
          resolve();
        })
        .fail(function(){ resolve(); });
      });
    }

    function cargarUnidadesEn(selector, selectedId){
      return new Promise((resolve) => {
        $.ajax({
          url: '<?= BASE_URL ?>/controllers/UnidadesSatController.php',
          method: 'GET', dataType: 'json',
          data: { accion: 'listar_min', limite: 1000 }
        })
        .done(function(resp){
          const arr = resp?.data || [];
          const $sel = $(selector).empty().append('<option value="">— Selecciona —</option>');
          arr.forEach(u => {
            const id  = u.id_unidad_sat;
            const nom = (u.clave ? u.clave+' - ' : '') + (u.descripcion || '');
            if (id){
              const sel = (String(id) === String(selectedId)) ? ' selected' : '';
              $sel.append(`<option value="${id}"${sel}>${nom}</option>`);
            }
          });
          resolve();
        })
        .fail(function(){
          // fallback a 'listar-min' si aplica
          $.ajax({
            url: '<?= BASE_URL ?>/controllers/UnidadesSatController.php',
            method: 'GET', dataType: 'json',
            data: { accion: 'listar-min', limite: 1000 }
          })
          .done(function(resp){
            const arr = resp?.data || [];
            const $sel = $(selector).empty().append('<option value="">— Selecciona —</option>');
            arr.forEach(u => {
              const id  = u.id_unidad_sat || u.id;
              const nom = (u.clave ? u.clave+' - ' : '') + (u.descripcion || u.nombre || '');
              if (id){
                const sel = (String(id) === String(selectedId)) ? ' selected' : '';
                $sel.append(`<option value="${id}"${sel}>${nom}</option>`);
              }
            });
            resolve();
          })
          .fail(() => resolve());
        });
      });
    }

    // NUEVO: cargar grupos en edición
    function cargarGruposEn(selector, selectedId){
      return new Promise((resolve) => {
        $.ajax({
          url: '<?= BASE_URL ?>/controllers/CatGruposController.php',
          method: 'GET', dataType: 'json',
          data: { accion: 'listar-min', limite: 1000 }
        })
        .done(function(resp){
          const arr = resp?.data || [];
          const $sel = $(selector);
          if (!$sel.length) { resolve(); return; }
          $sel.empty().append('<option value="">— Selecciona —</option>');
          arr.forEach(g => {
            if (g.id_grupo && g.nombre_grupo){
              const sel = (String(g.id_grupo) === String(selectedId)) ? ' selected' : '';
              $sel.append(`<option value="${g.id_grupo}"${sel}>${g.nombre_grupo}</option>`);
            }
          });

          const $modal = $sel.closest('.modal');
          if ($modal.length && $modal.hasClass('show')) {
            initGrupoSelect2('#' + $modal.attr('id'), selector);
          }

          resolve();
        })
        .fail(function(){ resolve(); });
      });
    }

    // ======================= CÁLCULO EN TIEMPO REAL (EDITAR) =======================
    // Misma idea que en “Agregar”, pero con campos #e_*
    const _manualEdit = { cn:false, pt:false, pb:false };
    $('#e_costo_neto').on('input',     () => _manualEdit.cn = true);
    $('#e_precio_taller').on('input',  () => _manualEdit.pt = true);
    $('#e_precio_publico').on('input', () => _manualEdit.pb = true);

    function debounceEdit(fn, ms=250){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
    let _calcXHREdit = null;

    function calcAutoPreciosServerEdit(){
      const id_proveedor = $('#e_id_proveedor').val() || 0;
      const ppvRaw = ($('#e_precio_proveedor').val() || '').toString().replace(',', '.');
      const ppv = parseFloat(ppvRaw);
      if (!(ppv > 0)) return;

      if (_calcXHREdit && _calcXHREdit.readyState !== 4) { try { _calcXHREdit.abort(); } catch(e){} }

      _calcXHREdit = $.post('<?= BASE_URL ?>/controllers/ProductosController.php', {
        accion: 'simular-precios',
        id_proveedor,
        precio_proveedor: ppv
      })
      .done(resp => {
        const d = resp?.data || {};
        if (!_manualEdit.cn && d.costo_neto     != null) $('#e_costo_neto').val(Number(d.costo_neto).toFixed(2));
        if (!_manualEdit.pt && d.precio_taller  != null) $('#e_precio_taller').val(Number(d.precio_taller).toFixed(2));
        if (!_manualEdit.pb && d.precio_publico != null) $('#e_precio_publico').val(Number(d.precio_publico).toFixed(2));
      });
    }
    const debouncedCalcEdit = debounceEdit(calcAutoPreciosServerEdit, 250);

    // Disparadores de cálculo en EDITAR
    $(document)
      .off('input',  '#e_precio_proveedor').on('input',  '#e_precio_proveedor', debouncedCalcEdit)
      .off('change', '#e_id_proveedor').on('change',     '#e_id_proveedor',     debouncedCalcEdit);

    // ======================= SUBMIT ACTUALIZAR =======================
    $(document).off('submit', '#formProductoEdit').on('submit', '#formProductoEdit', async function(e){
      e.preventDefault();

      // Normaliza números (igual que en Agregar)
      const num2 = v => {
        const x = parseFloat((v||'').toString().replace(',', '.'));
        return isNaN(x) ? 0 : x;
      };
      const int0 = v => {
        const n = parseInt((v||'0'), 10);
        return isNaN(n) ? 0 : n;
      };

      const id = $('#e_id_producto').val();
      if (!id){ toastr.warning('ID de producto requerido'); return; }

      const idUnidad = ($('#e_id_unidad_sat').val() || '').toString().trim();
      const idGrupo = ($('#e_id_grupo').val() || '').toString().trim();
      await sincronizarClaveSatGrupo('e');
      const claveSat = ($('#e_clave_prod_serv_sat').val() || '').toString().trim();
      if (!idUnidad) { toastr.warning('Unidad SAT es requerida.'); $('#e_id_unidad_sat').focus(); return; }
      if (!idGrupo) { toastr.warning('Grupo es requerido.'); $('#e_id_grupo').focus(); return; }
      if (!claveSat) { toastr.warning('Clave SAT inválida para el grupo seleccionado.'); $('#e_id_grupo').focus(); return; }

      const payload = {
        accion: 'actualizar',
        id_producto: id,
        id_proveedor: $('#e_id_proveedor').val() || '',
        id_unidad_sat: $('#e_id_unidad_sat').val() || '',
        id_grupo: $('#e_id_grupo').val() || '', // NUEVO
        clave_prod_serv_sat: ($('#e_clave_prod_serv_sat').val() || '').trim(),
        objeto_imp: ($('#e_objeto_imp').val() || '02').trim(),
        tasa_iva: num2($('#e_tasa_iva').val() || '0.160000'),
        codigo: ($('#e_codigo').val() || '').trim(),
        descripcion: ($('#e_descripcion').val() || '').trim(),
        precio_proveedor: num2($('#e_precio_proveedor').val()),
        costo_neto:      num2($('#e_costo_neto').val()),
        precio_taller:   num2($('#e_precio_taller').val()),
        precio_publico:  num2($('#e_precio_publico').val()),
        stock_actual: int0($('#e_stock_actual').val()),
        stock_maximo: int0($('#e_stock_maximo').val()),
        stock_minimo: int0($('#e_stock_minimo').val()),
        piso:    int0($('#e_piso').val()),
        pasillo: int0($('#e_pasillo').val()),
        estante: int0($('#e_estante').val()),
        peldano: int0($('#e_peldano').val()),
        activo: $('#e_activo').is(':checked') ? 1 : 0
      };

      $.ajax({
        url: '<?= BASE_URL ?>/controllers/ProductosController.php',
        method: 'POST', dataType: 'json',
        data: payload
      })
      .done(resp => {
        if (resp?.ok){
          toastr.success('Producto actualizado correctamente');
          $('#modalProductoEdit').modal('hide');
          // Recargar listado manteniendo la página actual (usa tu cargarProductos existente)
          if (typeof window.cargarProductos === 'function'){
            const $active = $('#pagination .page-item.active a');
            const page = parseInt($active?.data('page') || $active?.text() || 1, 10) || 1;
            window.cargarProductos(page);
          } else {
            location.reload();
          }
        } else {
          toastr.error(resp?.msg || 'No se pudo actualizar el producto');
        }
      })
      .fail(() => toastr.error('Error de comunicación al actualizar'));
    });


    // Eliminar producto
    function parseJSONSafe(txt){ try { return JSON.parse(txt); } catch(e){ return null; } }

    // Abrir modal desde el dropdown de acciones (delegado, porque las filas se crean dinámicamente)
    $(document).off('click', 'a.accion-eliminar').on('click', 'a.accion-eliminar', function (e) {
      e.preventDefault();
      e.stopPropagation();

      const id  = $(this).data('id');
      const cod = $(this).data('cod');

      if (!id) { toastr.warning('ID de producto inválido'); return; }

      $('#el-id-producto').val(id);
      $('#el-codigo-prod').text(cod || '—');

      // Si usas motivo:
      // $('#el-motivo-baja').val('');

      $('#modalEliminarProducto').modal('show');
    });

    // Confirmar eliminación
    document.addEventListener('DOMContentLoaded', function(){
      const btn = document.getElementById('btnConfirmarEliminarProducto');
      if (!btn) return;

      btn.addEventListener('click', function(){
        const id = document.getElementById('el-id-producto').value;
        if (!id) return;

        const original = btn.innerHTML;
        const mot = document.getElementById('el-motivo-baja') ? document.getElementById('el-motivo-baja').value.trim() : '';

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Eliminando...';

        fetch('<?= BASE_URL ?>/controllers/ProductosController.php?accion=eliminar', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: new URLSearchParams({ id_producto: id, ...(mot ? {motivo: mot} : {}) })
        })
        .then(async r => {
          const text = await r.text();
          const json = parseJSONSafe(text);
          if (!r.ok) throw new Error('HTTP '+r.status+' '+(json?.msg || text || 'Error'));
          return json;
        })
        .then(resp => {
          if (resp && resp.ok) {
            $('#modalEliminarProducto').modal('hide');
            if (typeof reloadProductos === 'function') reloadProductos();
            else if (typeof window.cargarProductos === 'function') window.cargarProductos(1);
            if (window.toastr) toastr.success(resp.msg || 'Producto desactivado y stock ajustado.');
          } else {
            const msg = (resp && resp.msg) || 'No se pudo desactivar el producto.';
            if (window.toastr) toastr.error(msg); else alert(msg);
          }
        })
        .catch(err => {
          if (window.toastr) toastr.error(err.message || 'Error de conexión.');
          else alert(err.message || 'Error de conexión.');
        })
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = original;
        });
      });
    });

    // ===================== EXPORTAR EXCEL =====================
      function exportarProductosExcel() {
        const params = new URLSearchParams();

        const codigo      = ($('#Codigo').val() || '').trim();
        const descripcion = ($('#Descripcion').val() || '').trim();
        const idProv      = $('#Proveedor').val() || '';
        const idGrupo     = $('#Grupo').val() || '';

        if (codigo)      params.append('codigo', codigo);
        if (descripcion) params.append('descripcion', descripcion);
        if (idProv)      params.append('id_proveedor', idProv);
        if (idGrupo)     params.append('id_grupo', idGrupo);

        params.append('accion', 'exportar-excel');

        const url = '<?= BASE_URL ?>/controllers/ProductosController.php?' + params.toString();
        window.location.href = url;
      }

      $('#btnExportarExcel').on('click', function(e){
        e.preventDefault();
        exportarProductosExcel();
      });
    </script>

    <!-- =================== Lógica del modal de Etiquetas =================== -->
    <script>
    (function($){
      'use strict';
      if (window.__etqInit) return;
      window.__etqInit = true;

      const PX_PER_MM = 96 / 25.4;
      const MIN_MODULE_MM = 0.125; // un punto a 203 dpi; por debajo no se imprime
      const MAX_MODULE_MM = 0.42;
      const MAX_COPIES = 500;
      const JSBARCODE_URL = 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';
      const DEFAULTS = Object.freeze({
        w: 50, h: 30, modo: 'rollo', copias: 1,
        precio_campo: 'precio_publico', show_price: false, show_desc: true,
        barcode_fmt: 'CODE128', barcode_field: 'codigo', tienda: 'REFACCIONARIA RIVERA'
      });
      let jsBarcodePromise = null;

      function notify(type, message){
        if (window.toastr && typeof toastr[type] === 'function') toastr[type](message);
        else alert(message);
      }

      function loadJsBarcode(){
        if (window.JsBarcode) return Promise.resolve(window.JsBarcode);
        if (jsBarcodePromise) return jsBarcodePromise;
        jsBarcodePromise = new Promise(function(resolve, reject){
          const script = document.createElement('script');
          script.src = JSBARCODE_URL;
          script.onload = function(){ resolve(window.JsBarcode); };
          script.onerror = function(){ reject(new Error('No fue posible cargar la librería de códigos de barras.')); };
          document.head.appendChild(script);
        });
        return jsBarcodePromise;
      }

      function resetControls(){
        $('#etq-w').val(DEFAULTS.w);
        $('#etq-h').val(DEFAULTS.h);
        $('#etq-modo').val(DEFAULTS.modo);
        $('#etq-copias').val(DEFAULTS.copias);
        $('#etq-precio').val(DEFAULTS.precio_campo);
        $('#etq-show-price').prop('checked', DEFAULTS.show_price);
        $('#etq-show-desc').prop('checked', DEFAULTS.show_desc);
        $('#etq-barcode-fmt').val(DEFAULTS.barcode_fmt);
        $('#etq-barcode-field').val(DEFAULTS.barcode_field);
        $('#etq-tienda').val(DEFAULTS.tienda);
      }

      function readOptions(){
        return {
          w: Number($('#etq-w').val()),
          h: Number($('#etq-h').val()),
          modo: $('#etq-modo').val(),
          copias: Number($('#etq-copias').val()),
          precio_campo: $('#etq-precio').val(),
          show_price: $('#etq-show-price').is(':checked'),
          show_desc: $('#etq-show-desc').is(':checked'),
          barcode_fmt: $('#etq-barcode-fmt').val(),
          barcode_field: $('#etq-barcode-field').val(),
          tienda: String($('#etq-tienda').val() || '')
        };
      }

      function barcodeValue(prod, opts){
        return opts.barcode_field === 'id_producto'
          ? String(prod.id_producto == null ? '' : prod.id_producto)
          : String(prod.codigo == null ? '' : prod.codigo);
      }

      function validateOptions(prod, opts){
        if (!Number.isFinite(opts.w) || opts.w <= 0) return 'El ancho debe ser mayor que cero.';
        if (!Number.isFinite(opts.h) || opts.h <= 0) return 'El alto debe ser mayor que cero.';
        if (opts.w < 10 || opts.w > 100 || opts.h < 10 || opts.h > 100) return 'El tamaño permitido es de 10 a 100 mm.';
        const minHeight = opts.show_price ? 22 : 18;
        if (opts.h < minHeight) return 'La altura mínima para esta combinación de contenido es ' + minHeight + ' mm.';
        if (!Number.isInteger(opts.copias) || opts.copias < 1 || opts.copias > MAX_COPIES) return 'Las copias deben ser un entero entre 1 y 500.';
        if (!opts.tienda.trim()) return 'El nombre de la tienda no puede estar vacío.';

        const value = barcodeValue(prod, opts);
        if (!value.trim()) return 'El código del producto no puede estar vacío.';
        if (opts.barcode_fmt === 'CODE128' && !/^[\x20-\x7E]+$/.test(value)) return 'CODE128 admite únicamente caracteres ASCII imprimibles.';
        if (opts.barcode_fmt === 'EAN13' && !/^\d{12,13}$/.test(value)) return 'EAN-13 requiere 12 o 13 dígitos.';
        if (opts.barcode_fmt === 'EAN8' && !/^\d{7,8}$/.test(value)) return 'EAN-8 requiere 7 u 8 dígitos.';
        if (opts.barcode_fmt === 'UPC' && !/^\d{11,12}$/.test(value)) return 'UPC requiere 11 o 12 dígitos.';
        return '';
      }

      function formatPrice(value){
        return '$' + Number(value || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      }

      function appendText(parent, className, value){
        const node = document.createElement('div');
        node.className = className;
        node.textContent = value;
        parent.appendChild(node);
        return node;
      }

      function buildLabel(prod, opts){
        const value = barcodeValue(prod, opts);
        const label = document.createElement('div');
        label.className = 'etq-label';

        const header = document.createElement('div');
        header.className = 'etq-header';
        appendText(header, 'etq-brand', opts.tienda.trim());
        if (opts.show_desc) appendText(header, 'etq-desc', String(prod.descripcion == null ? '' : prod.descripcion));
        if (opts.show_price) appendText(header, 'etq-price', formatPrice(prod[opts.precio_campo]));
        label.appendChild(header);

        const barWrap = document.createElement('div');
        barWrap.className = 'etq-barwrap';
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'etq-barcode');
        svg.dataset.value = value;
        svg.dataset.format = opts.barcode_fmt;
        barWrap.appendChild(svg);
        label.appendChild(barWrap);
        appendText(label, 'etq-code', value);
        return label;
      }

      function barcodeHeightPx(opts){
        let reservedMm = 3 + 3.4 + 3.1 + 1.5; // padding, tienda, código y separaciones
        if (opts.show_desc) reservedMm += 5.8;
        if (opts.show_price) reservedMm += 4.2;
        return Math.max(20, Math.min(52, (opts.h - reservedMm) * PX_PER_MM));
      }

      function renderBarcode(svg, opts){
        const value = svg.dataset.value;
        const format = svg.dataset.format;
        const availableMm = opts.w - 3.6; // 2 mm de padding + 1.6 mm de zona silenciosa interna
        if (availableMm <= 0) throw new Error('El ancho no deja espacio útil para el código de barras.');

        const base = { format: format, displayValue: false, lineColor: '#000', width: 1, height: barcodeHeightPx(opts), margin: 0 };
        window.JsBarcode(svg, value, base);
        const modules = parseFloat(svg.getAttribute('width'));
        if (!Number.isFinite(modules) || modules <= 0) throw new Error('No fue posible calcular el ancho del código de barras.');

        const availablePx = availableMm * PX_PER_MM;
        const modulePx = Math.min(MAX_MODULE_MM * PX_PER_MM, availablePx / modules);
        const moduleMm = modulePx / PX_PER_MM;
        if (moduleMm + 0.0001 < MIN_MODULE_MM) {
          throw new Error('El código necesita al menos ' + (modules * MIN_MODULE_MM + 3.6).toFixed(1) + ' mm de ancho para imprimirse de forma legible.');
        }

        window.JsBarcode(svg, value, Object.assign({}, base, { width: modulePx }));
        svg.dataset.modules = String(modules);
        svg.dataset.moduleMm = moduleMm.toFixed(4);
        svg.setAttribute('aria-label', value);
        return { modules: modules, moduleMm: moduleMm };
      }

      function renderPreview(prod, opts){
        const error = validateOptions(prod, opts);
        if (error) return Promise.reject(new Error(error));
        const root = document.getElementById('etqRoot');
        const grid = document.getElementById('etqGrid');
        root.style.setProperty('--lab-w', opts.w + 'mm');
        root.style.setProperty('--lab-h', opts.h + 'mm');
        root.classList.toggle('etq-rollo', opts.modo === 'rollo');
        grid.replaceChildren(buildLabel(prod, opts)); // preview: siempre exactamente una etiqueta

        return loadJsBarcode().then(function(){
          renderBarcode(grid.querySelector('.etq-barcode'), opts);
          $('#modalEtiquetas').data('opciones-etiqueta', opts);
        });
      }

      function printLabels(opts){
        const source = document.querySelector('#etqGrid .etq-label');
        const sharedStyles = document.getElementById('etqLabelStyles');
        if (!source || !sharedStyles) throw new Error('No se encontró una vista previa válida para imprimir.');

        const iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
        document.body.appendChild(iframe);
        const doc = iframe.contentDocument || iframe.contentWindow.document;
        const pageSize = opts.modo === 'rollo' ? opts.w + 'mm ' + opts.h + 'mm' : 'A4';
        const pageMargin = opts.modo === 'rollo' ? '0' : '10mm';

        doc.open();
        doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Imprimir etiquetas</title></head><body></body></html>');
        doc.close();
        const style = doc.createElement('style');
        style.textContent = sharedStyles.textContent + '\n' +
          '@page{size:' + pageSize + ';margin:' + pageMargin + '}' +
          'html,body{margin:0;padding:0;background:#fff}' +
          '*,*::before,*::after{box-sizing:border-box}' +
          '.etq-sheet{padding:' + (opts.modo === 'rollo' ? '0' : '10mm') + '}' +
          '.etq-grid{display:' + (opts.modo === 'rollo' ? 'block' : 'grid;grid-template-columns:repeat(auto-fill,var(--lab-w));gap:var(--gap)') + '}' +
          '.etq-label{break-inside:avoid;page-break-inside:avoid}' +
          (opts.modo === 'rollo'
            ? '.etq-label{break-after:page;page-break-after:always}.etq-label:last-child{break-after:auto;page-break-after:auto}'
            : '');
        doc.head.appendChild(style);

        const root = doc.createElement('div');
        root.className = 'etq-root' + (opts.modo === 'rollo' ? ' etq-rollo' : '');
        root.style.setProperty('--lab-w', opts.w + 'mm');
        root.style.setProperty('--lab-h', opts.h + 'mm');
        const sheet = doc.createElement('div');
        sheet.className = 'etq-sheet';
        const grid = doc.createElement('div');
        grid.className = 'etq-grid';
        for (let i = 0; i < opts.copias; i++) grid.appendChild(doc.importNode(source, true));
        sheet.appendChild(grid);
        root.appendChild(sheet);
        doc.body.appendChild(root);

        setTimeout(function(){
          iframe.contentWindow.focus();
          iframe.contentWindow.print();
          setTimeout(function(){ iframe.remove(); }, 1000);
        }, 150);
      }

      function applyAndReport(prod, opts, onSuccess){
        renderPreview(prod, opts).then(function(){
          if (onSuccess) onSuccess();
        }).catch(function(error){
          notify('error', error.message || 'No fue posible generar la etiqueta.');
        });
      }

      window.mostrarModalEtiquetas = function(producto, opciones){
        const prod = Object.assign({
          id_producto: 0, codigo: '', descripcion: '',
          precio_publico: 0, precio_taller: 0, precio_proveedor: 0
        }, producto || {});
        resetControls();
        const opts = Object.assign({}, DEFAULTS, opciones || {});
        $('#etq-w').val(opts.w);
        $('#etq-h').val(opts.h);
        $('#etq-modo').val(opts.modo);
        $('#etq-copias').val(opts.copias);
        $('#etq-precio').val(opts.precio_campo);
        $('#etq-show-price').prop('checked', !!opts.show_price);
        $('#etq-show-desc').prop('checked', !!opts.show_desc);
        $('#etq-barcode-fmt').val(opts.barcode_fmt);
        $('#etq-barcode-field').val(opts.barcode_field);
        $('#etq-tienda').val(opts.tienda);
        $('#etq-nombre-prod').text(' · ' + String(prod.descripcion || ''));
        $('#modalEtiquetas').data('producto', prod);
        $('#modalEtiquetas').modal('show');
        applyAndReport(prod, readOptions());
      };

      window.mostrarModalEtiquetasPorId = function(idProducto, opciones){
        $.get('<?= BASE_URL ?>/controllers/ProductosController.php', { accion: 'detalle', id_producto: idProducto })
          .done(function(resp){
            const p = resp && (resp.data || resp.producto || resp);
            if (!p) { notify('error', 'Producto no encontrado.'); return; }
            window.mostrarModalEtiquetas({
              id_producto: p.id_producto,
              codigo: p.codigo,
              descripcion: p.descripcion,
              precio_publico: Number(p.precio_publico),
              precio_taller: Number(p.precio_taller),
              precio_proveedor: Number(p.precio_proveedor)
            }, opciones);
          })
          .fail(function(){ notify('error', 'Error al cargar el producto.'); });
      };

      $(document).off('click', '.accion-etiquetas').on('click', '.accion-etiquetas', function(event){
        event.preventDefault();
        const id = Number($(this).data('id'));
        if (!Number.isInteger(id) || id < 1) { notify('warning', 'ID de producto inválido.'); return; }
        window.mostrarModalEtiquetasPorId(id);
      });

      $('#btnAplicarEtiquetas').on('click', function(){
        applyAndReport($('#modalEtiquetas').data('producto') || {}, readOptions());
      });

      $('#btnImprimirEtiquetas').on('click', function(){
        const prod = $('#modalEtiquetas').data('producto') || {};
        const opts = readOptions();
        applyAndReport(prod, opts, function(){
          try { printLabels(opts); }
          catch (error) { notify('error', error.message); }
        });
      });
    })(jQuery);
    </script>
    <!-- =================== /Lógica del modal de Etiquetas =================== -->


    

  </body>
</html>
