<?php
$titulo = "Inventarios";
$modulo = "Productos";
$subtitulo = ""; // puedes dejarlo vacío si no se necesita
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

                <!-- Botón Exportar Excel -->
                <button id="btnExportarExcel"
                        type="button"
                        class="btn btn-success btn-sm waves-effect waves-light">
                <i class="mdi mdi-file-excel"></i> Exportar a Excel
                </button>
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

      </div><!-- container-fluid -->
    </div><!-- wrapper -->

    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>

    <div class="rightbar-overlay"></div>

    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
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


            tbody += `
              <tr>
                <td class="text-center"><b>${cod}</b></td>
                <td>${desc}</td>
                <td class="text-center">${prov}</td>
                <td class="text-center">${stk.toLocaleString('es-MX')}</td>
                <td class="text-center"><b>${mxn(pb)}</b></td>
                <td class="text-center"><b>${mxn(pt)}</b></td>
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

      
    });

    // util para filtros
    function clearField(id)
    {
      const el = document.getElementById(id);
      if (!el) return;
      if(el.type==='checkbox'){ el.checked=false; } else { el.value=''; }
      el.dispatchEvent(new Event('change'));
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
          if (idSin) $sel.val(String(idSin));
        });

    })
    .on('shown.bs.modal', function(){
      // Reiniciar flags y, si ya hay PPV, calcular al mostrar
      _manual.cn = _manual.pt = _manual.pb = false;
      if (parseFloat($('#p_precio_proveedor').val() || 0) > 0) debouncedCalc();
    })
    .on('hidden.bs.modal', function(){
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

   
    // ===== Exportar a Excel =====
  $('#btnExportarExcel').on('click', function () {
    const params = new URLSearchParams({
      accion: 'exportar-excel',
      // mismos nombres que usa tu controller para filtros:
      codigo: $('#Codigo').val() || '',
      descripcion: $('#Descripcion').val() || '',
      id_proveedor: $('#Proveedor').val() || '',
      id_grupo: $('#Grupo').val() || ''
    });

    // Dispara la descarga (GET)
    const url = '<?= BASE_URL ?>/controllers/ProductosController.php?' + params.toString();
    window.location.href = url;
  });


    

    </script>


  </body>
</html>
