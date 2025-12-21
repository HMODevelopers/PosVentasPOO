<?php
$titulo = "Catalogos";
$modulo = "Sucursales";
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
  <title>Sucursales | REFASOFT-V4</title>
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

  <!-- Toastr -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

  <style>
    .clean-filter .input-group-text { cursor:pointer; }
    .table-responsive { overflow-y: visible !important; }
    .table-responsive .dropdown-menu { z-index: 2000; }
    .form-label.required:after { content:' *'; color:#dc3545; }
  </style>
</head>

<body>

  <!-- Navigation Bar-->
  <?php include_once __DIR__ . '/../../../includes/header.php'; ?>
  <!-- End Navigation Bar-->

  <!-- ============================================================== -->
  <!-- ================== Start Page Content here =================== -->
  <!-- ============================================================== -->

  <div class="wrapper">

    <!-- Loader (tu HTML, sin lógica aquí) -->
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

              <!-- Nombre -->
              <div class="col-md-4">
                <div class="form-group">
                  <label for="FiltroNombre" class="control-label">Nombre</label>
                  <div class="input-group">
                    <input type="text" id="FiltroNombre" class="form-control filtrar" placeholder="Ej. Matriz">
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroNombre')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Dirección -->
              <div class="col-md-5">
                <div class="form-group">
                  <label for="FiltroDireccion" class="control-label">Dirección</label>
                  <div class="input-group">
                    <input type="text" id="FiltroDireccion" class="form-control filtrar" placeholder="Calle, número, colonia...">
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroDireccion')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Teléfono -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="FiltroTelefono" class="control-label">Teléfono</label>
                  <div class="input-group">
                    <input type="text" id="FiltroTelefono" class="form-control filtrar" placeholder="662 123 4567">
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroTelefono')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

            </div><!--/row-->
          </div>
        </div>
      </div>
      <!-- =================== /Filtros =================== -->

      <!-- =================== Tabla =================== -->
      <div class="row mt-3">
        <div class="col-12">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="header-title">Catálogo de Sucursales</h4>
              <button id="btnNuevo" class="btn btn-primary"><i class="mdi mdi-plus"></i> Nueva sucursal</button>
            </div>

            <!-- Empty state -->
            <div id="emptyState" class="alert alert-warning d-none mb-2">
              No hay registros que coincidan con los filtros.
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-striped">
                <thead>
                  <tr>
                    <th class="text-center" style="width:90px;">ID</th>
                    <th>Nombre</th>
                    <th>Dirección</th>
                    <th style="width:160px;">Teléfono</th>
                    <th class="text-center" style="width:110px;">Acciones</th>
                  </tr>
                </thead>
                <tbody id="tbodySucursales"></tbody>
              </table>
            </div>

            <div class="row align-items-center justify-content-between mt-2">
              <div class="col-md-6">
                <div id="infoSucursales" class="dataTables_info" role="status" aria-live="polite"></div>
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
      <!-- =================== /Tabla =================== -->

    </div> <!-- end container -->
  </div>
  <!-- end wrapper -->

  <!-- =================== Modales =================== -->
  <div class="modal fade" id="modalSucursal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloModal">Nueva sucursal</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form id="formSucursal" autocomplete="off">
          <input type="hidden" id="id_sucursal" name="id_sucursal" />
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label required" for="nombre">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="100" />
              </div>
              <div class="col-md-6">
                <label class="form-label" for="telefono">Teléfono</label>
                <input type="text" class="form-control" id="telefono" name="telefono" maxlength="20" />
              </div>
              <div class="col-12">
                <label class="form-label" for="direccion">Dirección</label>
                <textarea class="form-control" id="direccion" name="direccion" rows="2" maxlength="200"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="btnGuardar">
              <i class="mdi mdi-content-save"></i> Guardar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Eliminar sucursal</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p class="mb-0">¿Seguro que deseas eliminar la sucursal <strong id="delNombre"></strong>? Esta acción es reversible.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-danger" id="btnConfirmEliminar">
            <i class="mdi mdi-delete"></i> Eliminar
          </button>
        </div>
      </div>
    </div>
  </div>
  <!-- =================== /Modales =================== -->

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

  <!-- Toastr -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    $(function(){
      let paginaActual = 1;
      const limitePorPagina = 10;
      const URL_CTRL = '<?= BASE_URL ?>/controllers/SucursalesController.php';

      // Inicial
      cargarSucursales(paginaActual);

      // ========= Utils =========
      const escapeHtml = (s) => (s==null? '' : String(s)
        .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
        .replaceAll('"','&quot;').replaceAll("'","&#039;"));
      const htmlAttr = (s) => escapeHtml(s).replaceAll('"','&quot;');

      // ========= Filtros =========
      $(".filtrar")
        .change(function(){
          const $el = $(this);
          if(($el.is(':checkbox') && $el.is(':checked')) || ($el.val() && $el.val().length>0))
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
          $el.blur();
          setTimeout(()=> cargarSucursales(1), 200);
        })
        .keypress(function(e){ if (e.charCode == 13) cargarSucursales(1); })
        .keyup(function(){
          if ($(this).val().length > 0) $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
        });

      $(".clean-filter").click(function(){
        const $el = $(this).closest('.input-group, .form-group').find('.filtrar');
        if ($el.is(':checkbox')){ $el.prop('checked', false).trigger('change'); }
        else { $el.val('').trigger('change'); if ($el.hasClass('select2')) $el.select2('val', 0); }
        cargarSucursales(1);
      });

      // ========= CRUD =========
      // Nuevo
      $('#btnNuevo').on('click', function(){
        $('#formSucursal')[0].reset();
        $('#id_sucursal').val('');
        $('#tituloModal').text('Nueva sucursal');
        $('#modalSucursal').modal('show');
      });

      // Editar
      $(document).on('click', 'a.accion-editar', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;
        $.ajax({
          url: URL_CTRL, method: 'GET', dataType: 'json',
          data: { accion: 'detalle', id_sucursal: id }
        })
        .done(function(resp){
          const r = resp?.data || null;
          if (!r){ toastr.error('No se encontró la sucursal.'); return; }
          $('#id_sucursal').val(r.id_sucursal);
          $('#nombre').val(r.nombre || '');
          $('#telefono').val(r.telefono || '');
          $('#direccion').val(r.direccion || '');
          $('#tituloModal').text('Editar sucursal');
          $('#modalSucursal').modal('show');
        })
        .fail(function(){ toastr.error('Error al obtener el detalle.'); });
      });

      // Guardar (crear/actualizar)
      $('#formSucursal').on('submit', function(e){
        e.preventDefault();
        const id = $('#id_sucursal').val();
        const payload = {
          id_sucursal: id || undefined,
          nombre: ($('#nombre').val() || '').trim(),
          direccion: ($('#direccion').val() || '').trim() || null,
          telefono: ($('#telefono').val() || '').trim() || null
        };
        if (!payload.nombre){ toastr.warning('El nombre es obligatorio.'); return; }

        const accion = id ? 'actualizar' : 'crear';
        $.ajax({
          url: URL_CTRL + '?accion=' + accion,
          method: 'POST',
          data: JSON.stringify(payload),
          contentType: 'application/json; charset=UTF-8',
          dataType: 'json'
        })
        .done(function(resp){
          const ok = !!resp?.ok || (accion==='crear' && resp?.id_sucursal>0);
          if (ok){
            $('#modalSucursal').modal('hide');
            toastr.success('Sucursal guardada correctamente.');
            cargarSucursales(accion==='crear' ? 1 : paginaActual);
          } else {
            toastr.error(resp?.msg || 'No se pudo guardar.');
          }
        })
        .fail(function(){ toastr.error('Error al guardar.'); });
      });

      // Eliminar (abrir modal confirmación)
      $(document).on('click', 'a.accion-eliminar', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        const nom = $(this).data('nombre') || '';
        $('#btnConfirmEliminar').data('id', id);
        $('#delNombre').text(nom);
        $('#modalEliminar').modal('show');
      });

      // Confirmar eliminar
      $('#btnConfirmEliminar').on('click', function(){
        const id = $(this).data('id');
        if (!id) return;
        $.ajax({
          url: URL_CTRL, method: 'POST', dataType: 'json',
          data: { accion: 'eliminar', id_sucursal: id }
        })
        .done(function(resp){
          if (resp?.ok){
            $('#modalEliminar').modal('hide');
            toastr.success('Sucursal eliminada.');
            cargarSucursales(paginaActual);
          } else {
            toastr.error(resp?.msg || 'No se pudo eliminar.');
          }
        })
        .fail(function(){ toastr.error('Error al eliminar.'); });
      });

      // ========= Tabla & Paginación =========
      function cargarSucursales(pagina){
        paginaActual = pagina;

        const filtros = {
          accion: 'listar',
          pagina,
          limite: limitePorPagina,
          nombre:    $('#FiltroNombre').val(),
          direccion: $('#FiltroDireccion').val(),
          telefono:  $('#FiltroTelefono').val()
        };

        $.ajax({
          url: URL_CTRL, method: 'POST', dataType: 'json', data: filtros
        })
        .done(function(resp){
          const arr = resp?.data || [];
          const total = parseInt(resp?.total || 0, 10);
          renderizarTabla(arr);

          if (total === 0) {
            $('#infoSucursales').text('No hay sucursales para mostrar');
          } else {
            const desde = (pagina - 1) * limitePorPagina + 1;
            const hasta = Math.min(pagina * limitePorPagina, total);
            $('#infoSucursales').text(`Mostrando ${desde} a ${hasta} de ${total} sucursales`);
          }

          configurarPaginacion(pagina, total, limitePorPagina);
        })
        .fail(function(){ toastr.error('Error al cargar las sucursales.'); });
      }

      function renderizarTabla(rows){
        let tbody = '';
        if (!rows.length){
          $('#emptyState').removeClass('d-none'); // mostrar aviso
          tbody = '<tr><td colspan="5" class="text-center text-muted">— No hay registros —</td></tr>';
        } else {
          $('#emptyState').addClass('d-none'); // ocultar aviso
          rows.forEach(v => {
            const id   = v.id_sucursal;
            const nom  = v.nombre || '—';
            const dir  = v.direccion || '—';
            const tel  = v.telefono || '—';
            tbody += `
              <tr>
                <td class="text-center text-muted"><b>${id}</b></td>
                <td>${escapeHtml(nom)}</td>
                <td>${escapeHtml(dir)}</td>
                <td>${escapeHtml(tel)}</td>
                <td class="text-center">
                  <div class="btn-group dropdown">
                    <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false">
                      <i class="mdi mdi-dots-horizontal"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                      <a class="dropdown-item accion-editar" href="#" data-id="${id}">
                        <i class="mdi mdi-square-edit-outline mr-2 text-muted font-18 vertical-middle"></i>Editar
                      </a>
                      <a class="dropdown-item accion-eliminar" href="#" data-id="${id}" data-nombre="${htmlAttr(nom)}">
                        <i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Eliminar
                      </a>
                    </div>
                  </div>
                </td>
              </tr>`;
          });
        }
        $('#tbodySucursales').html(tbody);
      }

      function configurarPaginacion(currentPage, totalItems, itemsPerPage=10){
        const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
        const $ul = $('#pagination');
        const maxVisiblePages = 5;
        $ul.empty();
        if (totalPages <= 1){ $ul.closest('nav').hide(); return; } else { $ul.closest('nav').show(); }

        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages/2));
        let endPage   = Math.min(totalPages, startPage + maxVisiblePages - 1);
        if (endPage - startPage + 1 < maxVisiblePages) startPage = Math.max(1, endPage - maxVisiblePages + 1);

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
          if (Number.isFinite(page)) { paginaActual = page; cargarSucursales(paginaActual); }
        });
      }

    }); // ready

    // util: limpiar filtros
    function clearField(id){
      const el = document.getElementById(id);
      if (!el) return;
      if (el.type === 'checkbox'){ el.checked=false; } else { el.value=''; }
      el.dispatchEvent(new Event('change'));
    }
  </script>
</body>
</html>
