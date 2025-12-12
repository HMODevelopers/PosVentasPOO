<?php
$titulo = "Catalogos";
$modulo = "Proveedores";
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
  <title>Proveedores | REFASOFT-V4</title>
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

  <style>
    .clean-filter .input-group-text { cursor:pointer; }
    .badge-pill { border-radius: 50rem; }
    .table-responsive { overflow-y: visible !important; }
    .table-responsive .dropdown-menu { z-index: 2000; }
    .form-label.required:after { content:' *'; color:#dc3545; }
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
    <!-- /Loader -->

    <div class="container-fluid">
      <?php include_once __DIR__ . '/../../../includes/breadcrumb.php'; ?>

      <!-- =================== Filtros =================== -->
      <div class="card-header" style="border-color:darkgray; border-style:dotted;">
        <h5>Filtros</h5>
        <div class="row">
          <div class="col-lg-12">
            <div class="row">
              <!-- Nombre / Razón social -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="FiltroNombre" class="control-label">Nombre / Razón social</label>
                  <div class="input-group">
                    <input type="text" id="FiltroNombre" class="form-control filtrar" placeholder="Ej. Proveedora del Norte">
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroNombre')"></i></span>
                    </div>
                  </div>
                </div>
              </div>
              <!-- RFC -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="FiltroRFC" class="control-label">RFC</label>
                  <div class="input-group">
                    <input type="text" id="FiltroRFC" class="form-control filtrar" placeholder="XAXX010101000">
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroRFC')"></i></span>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Correo -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="FiltroCorreo" class="control-label">Correo</label>
                  <div class="input-group">
                    <input type="text" id="FiltroCorreo" class="form-control filtrar" placeholder="ventas@dominio.com">
                    <div class="input-group-append clean-filter">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroCorreo')"></i></span>
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

      <!-- =================== Tabla Proveedores =================== -->
      <div class="row mt-3">
        <div class="col-12">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="header-title">Listado de Proveedores</h4>
              <button id="btnNuevo" class="btn btn-primary"><i class="mdi mdi-plus"></i> Nuevo proveedor</button>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-striped">
                <thead>
                  <tr>
                    <th class="text-center" style="width:90px;">ID</th>
                    <th>Nombre</th>
                    <th>RFC</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th class="text-center" style="width:110px;">Acciones</th>
                  </tr>
                </thead>
                <tbody id="tbodyProveedores"></tbody>
              </table>
            </div>

            <div class="row align-items-center justify-content-between mt-2">
              <div class="col-md-6">
                <div id="infoProveedores" class="dataTables_info" role="status" aria-live="polite"></div>
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
      <!-- =================== /Tabla Proveedores =================== -->

      <!-- =================== Modales =================== -->
      <div class="modal fade" id="modalProveedor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="tituloModal">Nuevo proveedor</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <form id="formProveedor" autocomplete="off">
              <input type="hidden" id="id_proveedor" name="id_proveedor" />
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label required" for="nombre">Nombre/Razón social</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="200" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="rfc">RFC</label>
                    <input type="text" class="form-control" id="rfc" name="rfc" maxlength="20" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="correo">Correo</label>
                    <input type="email" class="form-control" id="correo" name="correo" maxlength="120" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="telefono">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" maxlength="20" />
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="direccion">Dirección</label>
                    <textarea class="form-control" id="direccion" name="direccion" rows="2" maxlength="300"></textarea>
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
              <h5 class="modal-title">Eliminar proveedor</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <p class="mb-0">¿Seguro que deseas eliminar al proveedor <strong id="delNombre"></strong>? Esta acción es reversible (borrado lógico).</p>
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

    </div><!--/container-->
  </div><!--/wrapper-->

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
      const URL_CTRL = '<?= BASE_URL ?>/controllers/ProveedoresController.php';

      // Inicial
      cargarProveedores(paginaActual);

      // ========= Utils =========
      const escapeHtml = (s) => (s==null? '' : String(s)
        .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
        .replaceAll('"','&quot;').replaceAll("'",'&#039;'));
      const htmlAttr = (s) => escapeHtml(s).replaceAll('"','&quot;');
      const showLoader = (v) => v ? $('#LoadingImage').fadeIn(100) : $('#LoadingImage').fadeOut(150);

      // ========= Filtros =========
      $(".filtrar")
        .change(function(){
          const $el = $(this);
          if(($el.is(':checkbox') && $el.is(':checked')) || ($el.val() && $el.val().length>0))
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
          $el.blur();
          setTimeout(()=> cargarProveedores(1), 200);
        })
        .keypress(function(e){ if (e.charCode == 13) cargarProveedores(1); })
        .keyup(function(){
          if ($(this).val().length > 0) $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
        });

      $(".clean-filter").click(function(){
        const $el = $(this).closest('.input-group, .form-group').find('.filtrar');
        if ($el.is(':checkbox')){ $el.prop('checked', false).trigger('change'); }
        else { $el.val('').trigger('change'); if ($el.hasClass('select2')) $el.select2('val', 0); }
        cargarProveedores(1);
      });

      // ========= CRUD =========
      // Nuevo
      $('#btnNuevo').on('click', function(){
        $('#formProveedor')[0].reset();
        $('#id_proveedor').val('');
        $('#tituloModal').text('Nuevo proveedor');
        $('#modalProveedor').modal('show');
      });

      // Editar
      $(document).on('click', 'a.accion-editar', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;
        showLoader(true);
        $.ajax({ url: URL_CTRL, method: 'GET', dataType: 'json', data: { accion: 'detalle', id_proveedor: id }})
        .done(function(resp){
          const r = resp?.data || null;
          if (!r){ toastr.error('No se encontró el proveedor.'); return; }
          $('#id_proveedor').val(r.id_proveedor);
          $('#nombre').val(r.nombre || '');
          $('#rfc').val(r.rfc || '');
          $('#correo').val(r.correo || '');
          $('#telefono').val(r.telefono || '');
          $('#direccion').val(r.direccion || '');
          $('#tituloModal').text('Editar proveedor');
          $('#modalProveedor').modal('show');
        })
        .fail(function(){ toastr.error('Error al obtener el detalle.'); })
        .always(function(){ showLoader(false); });
      });

      // Guardar (crear/actualizar)
      $('#formProveedor').on('submit', function(e){
        e.preventDefault();
        const id = $('#id_proveedor').val();
        const payload = {
          id_proveedor: id || undefined,
          nombre: $('#nombre').val().trim(),
          rfc: $('#rfc').val().trim() || null,
          correo: $('#correo').val().trim() || null,
          telefono: $('#telefono').val().trim() || null,
          direccion: $('#direccion').val().trim() || null,
        };
        if (!payload.nombre){ toastr.warning('El nombre es obligatorio.'); return; }
        const accion = id ? 'actualizar' : 'crear';
        showLoader(true);
        $.ajax({
          url: URL_CTRL + '?accion=' + accion,
          method: 'POST', data: JSON.stringify(payload),
          contentType: 'application/json; charset=UTF-8', dataType: 'json'
        })
        .done(function(resp){
          const ok = !!resp?.ok || (accion==='crear' && resp?.id_proveedor>0);
          if (ok){
            $('#modalProveedor').modal('hide');
            toastr.success('Proveedor guardado correctamente.');
            cargarProveedores(accion==='crear' ? 1 : paginaActual);
          } else {
            toastr.error(resp?.msg || 'No se pudo guardar.');
          }
        })
        .fail(function(){ toastr.error('Error al guardar.'); })
        .always(function(){ showLoader(false); });
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
        showLoader(true);
        $.ajax({ url: URL_CTRL, method: 'POST', dataType: 'json', data: { accion: 'eliminar', id_proveedor: id }})
        .done(function(resp){
          if (resp?.ok){
            $('#modalEliminar').modal('hide');
            toastr.success('Proveedor eliminado.');
            cargarProveedores(paginaActual);
          } else {
            toastr.error(resp?.msg || 'No se pudo eliminar.');
          }
        })
        .fail(function(){ toastr.error('Error al eliminar.'); })
        .always(function(){ showLoader(false); });
      });

      // ========= Tabla & Paginación =========
      function buildQueryFromFilters(){
        const vals = [ $('#FiltroNombre').val(), $('#FiltroRFC').val(), $('#FiltroCorreo').val(), $('#FiltroTelefono').val() ]
          .map(v => String(v||'').trim())
          .filter(v => v.length>0);
        return vals.join(' ');
      }

      function cargarProveedores(pagina){
        paginaActual = pagina;
        const q = buildQueryFromFilters(); // backend soporta 'q' (nombre, rfc, correo, telefono)
        $.ajax({
          url: URL_CTRL, method: 'POST', dataType: 'json',
          data: { accion:'listar', pagina, limite: limitePorPagina, q }
        })
        .done(function(resp){
          const arr = resp?.data || [];
          const total = parseInt(resp?.total || 0, 10);
          renderizarTabla(arr);
          let desde = (pagina - 1) * limitePorPagina + 1;
          let hasta = Math.min(pagina * limitePorPagina, total);
          $('#infoProveedores').text(`Mostrando ${total === 0 ? 0 : desde} a ${hasta} de ${total} proveedores`);
          configurarPaginacion(pagina, total, limitePorPagina);
        })
        .fail(function(){ toastr.error('Error al cargar los proveedores.'); })
        .always(function(){ showLoader(false); });
      }

      function renderizarTabla(rows){
        let tbody = '';
        if (!rows.length){
          tbody = '<tr><td colspan="7" class="text-center">No hay proveedores</td></tr>';
        } else {
          rows.forEach(v => {
            const id   = v.id_proveedor;
            const nom  = v.nombre || '—';
            const rfc  = v.rfc || '—';
            const cor  = v.correo || '—';
            const tel  = v.telefono || '—';
            const dir  = v.direccion || '—';
            tbody += `
              <tr>
                <td class="text-center text-muted"><b>${id}</b></td>
                <td>${escapeHtml(nom)}</td>
                <td>${escapeHtml(rfc)}</td>
                <td>${escapeHtml(cor)}</td>
                <td>${escapeHtml(tel)}</td>
                <td>${escapeHtml(dir)}</td>
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
        $('#tbodyProveedores').html(tbody);
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
          if (Number.isFinite(page)) { paginaActual = page; cargarProveedores(paginaActual); }
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
