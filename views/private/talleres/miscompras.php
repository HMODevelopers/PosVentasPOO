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

        <div class="card-header" style="border-color:darkgray; border-style:dotted;">
          <h5>Filtros</h5>

          <div class="row">
            <div class="col-lg-12">
              <div class="row">

                <!-- Filtro por Código -->
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="Codigo" class="control-label">Código</label>
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

                <!-- Filtro por Descripción -->
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="Descripcion" class="control-label">Descripción</label>
                    <div class="input-group">
                      <input type="text" id="Descripcion" name="Descripcion" class="form-control filtrar">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text">
                          <i class="mdi mdi-close-circle text-danger" onclick="clearField('Descripcion')"></i>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Filtro por Fecha -->
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="FechaVenta" class="control-label">Fecha de Venta</label>
                    <div class="input-group">
                      <input type="date" id="FechaVenta" name="FechaVenta" class="form-control filtrar" value="<?php echo date('Y-m-d'); ?>">
                      <div class="input-group-append clean-filter">
                        <span class="input-group-text">
                          <i class="mdi mdi-close-circle text-danger" onclick="clearField('FechaVenta')"></i>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

              </div><!-- .row -->
            </div><!-- .col -->
          </div><!-- .row -->
        </div><!-- .card-header -->

        <div class="row">
          <div class="col-lg-12">
            <div class="card-box">

              <div class="table-responsive">
                <table class="table table-bordered table-striped" id="ventas-table">
                  <thead>
                    <tr>
                      <th>No Tiket</th>
                      <th>Cliente</th> <!-- NUEVO -->
                      <th>Código</th>
                      <th>Descripción</th>
                      <th>Cantidad</th>
                      <th>Precio</th>
                      <th>Total</th>
                      <th>Fecha Venta</th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Los datos se llenarán aquí con jQuery -->
                  </tbody>
                </table>
              </div> <!-- end .table-responsive -->

              <div class="row">
                <div class="col col-md-4">
                  <!-- Total de ventas -->
                  <h4>Total compras del día:</h4>
                  <h5><span id="total-venta"><strong> $ 0.00</strong></span></h5>
                </div>
                <div class="col col-md-8">
                  <nav aria-label="Page navigation example">
                    <ul id="pagination" class="pagination justify-content-end"></ul>
                  </nav>
                </div>
              </div>

            </div> <!-- end card-box -->
          </div> <!-- end col -->
        </div><!-- .row -->

      </div><!-- container-fluid -->
    </div><!-- wrapper -->

    <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>

    <div class="rightbar-overlay"></div>

    <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
      (function () {
        const BASE = '<?= BASE_URL ?>';
        const $tabla = $('#ventas-table tbody');
        const $total = $('#total-venta');
        const $pagination = $('#pagination');

        const state = {
          pagina: 1,
          limite: 20,
          codigo: '',
          descripcion: '',
          fecha: $('#FechaVenta').val() || '',
          cargando: false
        };

        // Exponer para íconos limpiar
        function clearField(id) {
          const $i = $('#'+id);
          $i.val('');
          $i.trigger('change');
        }
        window.clearField = clearField;

        function formatMoney(n) {
          return Number(n || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
        }

        let t = null;
        function debounceLoad() { if (t) clearTimeout(t); t = setTimeout(load, 250); }

        function readFilters() {
          state.codigo = $('#Codigo').val().trim();
          state.descripcion = $('#Descripcion').val().trim();
          state.fecha = $('#FechaVenta').val();
        }

        function setLoading(flag) {
          state.cargando = !!flag;
          if (flag) $('#LoadingImage').show(); else $('#LoadingImage').hide();
        }

        function buildRows(rows) {
          if (!rows || !rows.length) {
            return `<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>`;
          }
          return rows.map(r => {
            const total = Number(r.total ?? 0);
            const precio = Number(r.precio ?? 0);
            const fecha = r.fecha_venta ? new Date(r.fecha_venta).toLocaleString('es-MX') : '';
            return `
              <tr>
                <td>${r.no_tiket ?? ''}</td>
                <td>${r.cliente ?? ''}</td>
                <td>${r.codigo ?? ''}</td>
                <td>${r.descripcion ?? ''}</td>
                <td class="text-right">${Number(r.cantidad ?? 0)}</td>
                <td class="text-right">${formatMoney(precio)}</td>
                <td class="text-right">${formatMoney(total)}</td>
                <td>${fecha}</td>
              </tr>
            `;
          }).join('');
        }

        function buildPagination(paginaActual, paginas) {
          $pagination.empty();
          if (paginas <= 1) return;

          function li(page, text, disabled=false, active=false) {
            const cls = ['page-item'];
            if (disabled) cls.push('disabled');
            if (active) cls.push('active');
            return `
              <li class="${cls.join(' ')}">
                <a class="page-link" href="#" data-page="${page}">${text}</a>
              </li>
            `;
          }

          $pagination.append(li(Math.max(1, paginaActual - 1), '&laquo;', paginaActual === 1));

          const maxButtons = 7;
          let start = Math.max(1, paginaActual - Math.floor(maxButtons/2));
          let end = Math.min(paginas, start + maxButtons - 1);
          if ((end - start + 1) < maxButtons) start = Math.max(1, end - maxButtons + 1);

          for (let p = start; p <= end; p++) {
            $pagination.append(li(p, String(p), false, p === paginaActual));
          }

          $pagination.append(li(Math.min(paginas, paginaActual + 1), '&raquo;', paginaActual === paginas));

          $pagination.find('a.page-link').on('click', function (e) {
            e.preventDefault();
            const p = Number($(this).data('page'));
            if (!isNaN(p) && p !== state.pagina) {
              state.pagina = p;
              load();
            }
          });
        }

        function load() {
          if (state.cargando) return;
          readFilters();
          setLoading(true);

          $.get(`${BASE}/controllers/ComprasClientesController.php`, {
            accion: 'listar',
            pagina: state.pagina,
            limite: state.limite,
            codigo: state.codigo,
            descripcion: state.descripcion,
            fecha: state.fecha
          })
          .done((res) => {
            if (!res || res.ok === false) {
              toastr.error(res?.msg || 'No fue posible cargar las compras');
              return;
            }
            $tabla.html(buildRows(res.data || []));
            $total.html(`<strong>${formatMoney(res.suma_total || 0)}</strong>`);
            buildPagination(Number(res.pagina || 1), Number(res.paginas || 1));
          })
          .fail((xhr) => {
            const msg = xhr?.responseJSON?.msg || 'Error de red al cargar';
            toastr.error(msg);
          })
          .always(() => setLoading(false));
        }

        // Filtros
        $('.filtrar').on('input change', function () {
          state.pagina = 1;
          debounceLoad();
        });

        // Primer render
        load();
      })();
    </script>

  </body>
</html>
