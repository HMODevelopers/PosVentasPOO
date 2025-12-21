<?php
$titulo = "Compras";
$modulo = "Productos Comprados";
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
    <title>Productos Comprados | REFASOFT-V4</title>
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

                <!-- Código producto -->
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="Codigo" class="control-label">Código Producto</label>
                    <div class="input-group">
                      <input type="text" id="Codigo" name="Codigo" class="form-control filtrar">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text">
                          <i class="mdi mdi-close-circle text-danger" onclick="clearField('Codigo')"></i>
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
                      <input type="date" id="Fecha" name="Fecha" class="form-control filtrar">
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

        <!-- =================== Tabla Detalle Compras =================== -->
        <div class="row mt-3">
          <div class="col-12">
            <div class="card-box">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="header-title">Listado de Productos Comprados</h4>
              </div>

              <div class="table-responsive">
                <table id="tablaComprasDetalle" class="table table-bordered table-hover table-striped">
                  <thead>
                    <tr>
                      <th>Factura</th>
                      <th>Código</th>
                      <th class="text-end">Cantidad</th>
                      <th>Fecha Factura</th>
                      <th>Proveedor</th>
                      <th class="text-end">Precio Proveedor</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <!-- Paginador -->
              <div class="row align-items-center justify-content-between mt-2">
                <div class="col-md-6">
                  <div id="infoComprasDetalle" class="dataTables_info" role="status" aria-live="polite"></div>
                </div>
                <div class="col-md-6 d-flex justify-content-end">
                  <nav aria-label="Page navigation">
                    <ul id="paginationDetalle" class="pagination justify-content-end mb-0"></ul>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- =================== /Tabla Detalle Compras =================== -->
      
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
      $(function () {
        let paginaActual = 1;
        const limitePorPagina = 20;

        // =================== Proveedores en filtro ===================
        cargarProveedoresSelect();

        function cargarProveedoresSelect(opts = {}) {
          const {
            selectId = 'Proveedor',
            selected = '',
            incluirTodos = true,
            limite = 200,
            q = ''
          } = opts;

          const $sel = $('#' + selectId);
          if ($sel.length === 0) return;

          $sel.prop('disabled', true)
              .html(incluirTodos ? '<option value=\"\">-- Todos --</option>' : '');

          $.ajax({
            url: '<?= BASE_URL ?>/controllers/ProveedoresController.php',
            method: 'GET',
            dataType: 'json',
            data: { accion: 'listar-min', limite, q }
          })
          .done(function(resp){
            const arr = resp?.data || [];
            let html = incluirTodos ? '<option value=\"\">-- Todos --</option>' : '';
            arr.forEach(p => {
              const id  = p.id_proveedor;
              const nom = p.nombre;
              if (id && nom) {
                const sel = (String(id) === String(selected)) ? ' selected' : '';
                html += `<option value=\"${id}\"${sel}>${nom}</option>`;
              }
            });
            $sel.html(html).prop('disabled', false);
          })
          .fail(function(){
            $sel.prop('disabled', false);
          });
        }

        // =================== Cargar detalle de compras ===================
        function cargarComprasDetalle(pagina) {
          const folio      = $('#Folio').val();
          const codigo     = $('#Codigo').val();
          const fecha      = $('#Fecha').val();
          const idProveedor= $('#Proveedor').val();

          $.ajax({
            url: '<?= BASE_URL ?>/controllers/ComprasController.php',
            method: 'GET',
            dataType: 'json',
            data: {
              accion: 'listar-detalle',
              pagina: pagina,
              limite: limitePorPagina,
              folio: folio,
              codigo: codigo,
              fecha: fecha,
              id_proveedor: idProveedor
            }
          })
          .done(function(resp){
            const items = resp.data || [];
            const total = parseInt(resp.total || 0, 10);

            renderizarTablaDetalle(items);

            let desde = (pagina - 1) * limitePorPagina + 1;
            let hasta = Math.min(pagina * limitePorPagina, total);
            $('#infoComprasDetalle').text(
              `Mostrando ${total === 0 ? 0 : desde} a ${hasta} de ${total} productos`
            );

            configurarPaginacionDetalle(pagina, total, limitePorPagina);
          })
          .fail(function(){
            toastr.error('Error al cargar el listado de productos de compras.');
          });
        }

        function renderizarTablaDetalle(items) {
          let tbody = '';
          if (!items.length) {
            tbody = '<tr><td colspan=\"6\" class=\"text-center\">No hay datos</td></tr>';
          } else {
            items.forEach(r => {
              const cant   = parseFloat(r.cantidad || 0);
              const precio = parseFloat(r.precio_proveedor || 0);

              tbody += `
                <tr>
                  <td><center>${r.factura || ''}</center></td>
                  <td><center>${r.codigo || ''}</center></td>
                  <td class=\"text-end\">${cant.toFixed(2)}</td>
                  <td><center>${r.fecha || ''}</center></td>
                  <td><center>${r.proveedor || ''}</center></td>
                  <td class=\"text-end\">${precio.toFixed(2)}</td>
                </tr>`;
            });
          }
          $('#tablaComprasDetalle tbody').html(tbody);
        }

        function configurarPaginacionDetalle(currentPage, totalItems, itemsPerPage = 20) {
          const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
          const $ul = $('#paginationDetalle');
          const maxVisiblePages = 5;
          $ul.empty();

          if (totalPages <= 1) {
            $ul.closest('nav').hide();
            return;
          } else {
            $ul.closest('nav').show();
          }

          let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
          let endPage   = Math.min(totalPages, startPage + maxVisiblePages - 1);
          if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
          }

          if (currentPage > 1) {
            $ul.append(`<li class=\"page-item\"><a class=\"page-link\" href=\"javascript:void(0);\" data-page=\"1\">Primera</a></li>`);
            $ul.append(`<li class=\"page-item\"><a class=\"page-link\" href=\"javascript:void(0);\" data-page=\"${currentPage-1}\">&laquo; Anterior</a></li>`);
          }

          for (let i = startPage; i <= endPage; i++) {
            const active = (i === currentPage) ? 'active' : '';
            $ul.append(`<li class=\"page-item ${active}\"><a class=\"page-link\" href=\"javascript:void(0);\" data-page=\"${i}\">${i}</a></li>`);
          }

          if (currentPage < totalPages) {
            $ul.append(`<li class=\"page-item\"><a class=\"page-link\" href=\"javascript:void(0);\" data-page=\"${currentPage+1}\">Siguiente &raquo;</a></li>`);
            $ul.append(`<li class=\"page-item\"><a class=\"page-link\" href=\"javascript:void(0);\" data-page=\"${totalPages}\">Última</a></li>`);
          }

          $ul.off('click', 'a.page-link').on('click', 'a.page-link', function(e){
            e.preventDefault();
            const page = Number($(this).data('page'));
            if (Number.isFinite(page)) {
              paginaActual = page;
              cargarComprasDetalle(paginaActual);
            }
          });
        }

        // Carga inicial
        cargarComprasDetalle(paginaActual);

        // =================== Filtros ===================
        $(".filtrar")
          .change(function () {
              const $el = $(this);
              if ($el.val().length > 0)
                $el.siblings(".clean-filter").css({ display: "flex" });
              else
                $el.siblings(".clean-filter").css({ display: "none" });

              $el.blur();
              setTimeout(function(){ cargarComprasDetalle(1); }, 200);
          })
          .keypress(function (e) {
              if (e.charCode == 13) cargarComprasDetalle(1);
          })
          .keyup(function () {
              if ($(this).val().length > 0)
                $(this).siblings(".clean-filter").css({ display: "flex" });
              else
                $(this).siblings(".clean-filter").css({ display: "none" });
          })
          .click(function () {
              if ($(this).is(":button")) cargarComprasDetalle(1);
          });

        $(".clean-filter").click(function () {
          const $el = $(this).parent().find(".filtrar");
          $el.val("").trigger("change");
          if ($el.hasClass("select2")) $el.select2("val", 0);
          cargarComprasDetalle(1);
        });

      }); // ready

      // Utilidad del ícono X en filtros
      function clearField(id){
        const el = document.getElementById(id);
        if (el){
          el.value='';
          el.dispatchEvent(new Event('change'));
        }
      }
    </script>
  </body>
</html>
