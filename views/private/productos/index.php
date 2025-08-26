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
                <div class="col-md-4">
                <div class="form-group">
                <label for="Codigo" class="control-label">Código</label>
                <div class="input-group">
                <input type="text" id="Codigo" name="Codigo" class="form-control filtrar" placeholder="A0001000, SKU, etc.">
                <div class="input-group-append clean-filter"><span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Codigo')"></i></span></div>
                </div>
                </div>
                </div>

                <!-- Descripción -->
                <div class="col-md-4">
                <div class="form-group">
                <label for="Descripcion" class="control-label">Descripción</label>
                <div class="input-group">
                <input type="text" id="Descripcion" name="Descripcion" class="form-control filtrar" placeholder="Filtro de aceite, Anticongelante...">
                <div class="input-group-append clean-filter"><span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Descripcion')"></i></span></div>
                </div>
                </div>
                </div>

                <!-- Proveedor -->
                <div class="col-md-4">
                <div class="form-group">
                <label for="Proveedor" class="control-label">Proveedor</label>
                <div class="input-group">
                <select id="Proveedor" name="Proveedor" class="form-control filtrar" disabled>
                <option value="">-- Todos --</option>
                </select>
                <div class="input-group-append clean-filter"><span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('Proveedor')"></i></span></div>
                </div>
                </div>
                </div>
                </div>
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

      </div>
    </div>

    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>

    <div class="rightbar-overlay"></div>

    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
      $(function(){
        let paginaActual = 1;
        const limitePorPagina = 10;

        cargarProveedoresSelect();
        cargarUnidadesSelect();
        cargarProductos(paginaActual);

        function mxn(v){ return Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'}); }
        function ymdToEs(ymd){ if(!ymd) return '—'; const [y,m,d]=String(ymd).split('-'); return `${d}/${m}/${y}`; }

        function cargarProveedoresSelect(opts={}){
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

        function cargarUnidadesSelect(opts={}){
          const { selectId='Unidad', selected='', incluirTodos=true, limite=500, q='' } = opts;
          const $sel = $('#'+selectId);
          if ($sel.length === 0) return;
          $sel.prop('disabled', true).html(incluirTodos? '<option value="">-- Todas --</option>' : '');

          // Ajusta a tu controlador real si difiere el nombre
          $.ajax({
            url: '<?= BASE_URL ?>/controllers/UnidadesSatController.php',
            method: 'GET', dataType: 'json',
            data: { accion:'listar-min', limite, q }
          })
          .done(function(resp){
            const arr = resp?.data || [];
            let html = incluirTodos? '<option value="">-- Todas --</option>': '';
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

        function cargarProductos(pagina){
            const codigo      = $('#Codigo').val();        // <input id="Codigo">
            const descripcion = $('#Descripcion').val();   // <input id="Descripcion">
            const idProv      = $('#Proveedor').val();     // <select id="Proveedor">

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
                id_proveedor: idProv || ''
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
                      </div>
                    </div>
                  </td>
                </tr>`;
            });
          }
          $('#tablaProductos tbody').html(tbody);
        }

        function configurarPaginacion(currentPage, totalItems, itemsPerPage=10){
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

        // Filtros (igual patrón que Compras)
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

        // =================== Detalle ===================
        $(document).on('click', 'a.accion-ver-detalle', function(e){
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
            const p = resp?.data || null; // <== TU CONTROLADOR responde { data: row }
            if (!p){
              $('#det-loader').hide();
              $('#det-error').show().text('No se encontró el producto.');
              return;
            }

            $('#det-codigo').text(p.codigo || ('#'+(p.id_producto||'')));
            $('#det-descripcion').text(p.descripcion || '—');

            // nombre proveedor/unidad si los traes en el detalle; si no, se muestra el id
            $('#det-proveedor').text(p.proveedor || p.nombre_proveedor || (p.id_proveedor ? `#${p.id_proveedor}` : '—'));
            $('#det-unidad').text(p.unidad_sat || p.descripcion_unidad || (p.id_unidad_sat ? `#${p.id_unidad_sat}` : '—'));
            $('#det-clave').text(p.clave_prod_serv_sat || '—');
            $('#det-creado').text(p.fecha_creacion ? p.fecha_creacion : '—');

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

      function clearField(id){ const el = document.getElementById(id); if (!el) return; if(el.type==='checkbox'){ el.checked=false; } else { el.value=''; } el.dispatchEvent(new Event('change')); }
    </script>
  </body>
</html>