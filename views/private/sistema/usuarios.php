<?php
$titulo = "Sistema";
$modulo = "Gestionar Usuarios";
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
  <title>Usuarios | REFASOFT-V4</title>
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

  <style>
    .clean-filter .input-group-text { cursor:pointer; }
    .table-responsive { overflow-y: visible !important; }
    .table-responsive .dropdown-menu { z-index: 2000; }
    .badge-pill { border-radius: 50rem; }
    .form-label.required:after { content:' *'; color:#dc3545; }
  </style>
</head>

<body>
  <!-- Navigation Bar-->
  <?php include_once __DIR__ . '/../../../includes/header.php'; ?>
  <!-- End Navigation Bar-->

  <div class="wrapper">

    <!-- Loader (markup; tu lógica vive en loader.js) -->
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
              <div class="col-md-3">
                <div class="form-group">
                  <label for="FiltroNombre" class="control-label">Nombre</label>
                  <div class="input-group">
                    <input type="text" id="FiltroNombre" class="form-control filtrar" placeholder="Juan Pérez">
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroNombre')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Usuario (login) -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="FiltroUsuarioLogin" class="control-label">Usuario</label>
                  <div class="input-group">
                    <input type="text" id="FiltroUsuarioLogin" class="form-control filtrar" placeholder="j.perez">
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroUsuarioLogin')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Rol (select dinámico) -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="FiltroRolId" class="control-label">Rol</label>
                  <div class="input-group">
                    <select id="FiltroRolId" class="form-control filtrar">
                      <option value="">Todos</option>
                    </select>
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroRolId')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Estatus -->
              <div class="col-md-3">
                <div class="form-group">
                  <label for="FiltroActivo" class="control-label">Estatus</label>
                  <div class="input-group">
                    <select id="FiltroActivo" class="form-control filtrar">
                      <option value="1" selected>Activos</option>
                      <option value="0">Inactivos</option>
                      <option value="">Todos</option>
                    </select>
                    <div class="input-group-append clean-filter" style="display:none;">
                      <span class="input-group-text"><i class="mdi mdi-close-circle text-danger" onclick="clearField('FiltroActivo')"></i></span>
                    </div>
                  </div>
                </div>
              </div>

            </div><!--/row-->
          </div>
        </div>
      </div>
      <!-- =================== /Filtros =================== -->

      <!-- =================== Tabla Usuarios =================== -->
      <div class="row">
        <div class="col-12">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h4 class="header-title">Listado de Usuarios</h4>
              <button id="btnNuevo" class="btn btn-primary"><i class="mdi mdi-plus"></i> Nuevo usuario</button>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-striped">
                <thead>
                  <tr>
                    <th class="text-center" style="width:80px;">ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th class="text-center" style="width:90px;">Rol</th>
                    <th class="text-center" style="width:110px;">Estatus</th>
                    <th class="text-center" style="width:170px;">Creado</th>
                    <th class="text-center" style="width:110px;">Acciones</th>
                  </tr>
                </thead>
                <tbody id="tbodyUsuarios"></tbody>
              </table>
            </div>

            <div class="row align-items-center justify-content-between mt-2">
              <div class="col-md-6">
                <div id="infoUsuarios" class="dataTables_info" role="status" aria-live="polite"></div>
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

  <!-- ============= Modal Crear/Editar ============= -->
  <div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloModal">Nuevo usuario</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <form id="formUsuario" autocomplete="off">
          <input type="hidden" id="id_usuario" name="id_usuario" />

          <div class="modal-body">

            <!-- fila 1 -->
            <div class="form-row mb-3">
              <div class="col-md-6">
                <label class="form-label required" for="nombre">Nombre Completo</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="200" />
              </div>
              <div class="col-md-6">
                <label class="form-label required" for="usuario">Usuario</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required maxlength="100" />
              </div>
            </div>

            <!-- fila 2 -->
            <div class="form-row mb-3">
              <div class="col-md-6">
                <label class="form-label" for="correo">Correo</label>
                <input type="email" class="form-control" id="correo" name="correo" maxlength="150" />
              </div>
              <div class="col-md-6">
                <label class="form-label" for="telefono">Teléfono</label>
                <input type="text" class="form-control" id="telefono" name="telefono" maxlength="20" />
              </div>
            </div>

            <!-- fila 3 -->
            <div class="form-row mb-3">
              <div class="col-md-12">
                <label class="form-label required" for="id_rol">Rol</label>
                <select class="form-control" id="id_rol" name="id_rol" required>
                  <option value="">Seleccione un rol...</option>
                </select>
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
  <!-- ============= /Modal Crear/Editar ============= -->

  <!-- ============= Modal Eliminar ============= -->
  <div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Eliminar usuario</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p class="mb-0">¿Seguro que deseas eliminar al usuario <strong id="delNombre"></strong>? Esta acción es reversible.</p>
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
  <!-- ============= /Modal Eliminar ============= -->

  <!-- Footer Start -->
  <?php include_once __DIR__ . '/../../../includes/footer.php'; ?>
  <!-- End Footer -->

  <div class="rightbar-overlay"></div>

  <!-- Vendor js -->
  <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
  <!-- App js-->
  <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    $(function(){
      let paginaActual = 1;
      const limitePorPagina = 10;
      const URL_CTRL  = '<?= BASE_URL ?>/controllers/UsuariosController.php';
      const URL_ROLES = '<?= BASE_URL ?>/controllers/RolesController.php';

      // Inicial
      cargarUsuarios(paginaActual);
      // Cargar opciones de roles en el filtro (con opción "Todos")
      cargarRolesEnSelect('FiltroRolId', '', true).then(() => {
        const $f = $('#FiltroRolId');
        if ($f.val()) $f.closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
      });

      // ===== Utils =====
      const escapeHtml = (s) => (s==null? '' : String(s)
        .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
        .replaceAll('"','&quot;').replaceAll("'","&#039;"));
      function ymdHisToEs(dt){
        if(!dt) return '—';
        const d = new Date((dt||'').replace(' ', 'T'));
        return isNaN(d.getTime()) ? dt : d.toLocaleString('es-MX');
      }
      const badgeActivo = (v) => {
        const on = String(v) === '1';
        return `<span class="badge badge-${on?'success':'secondary'} badge-pill">${on?'Activo':'Inactivo'}</span>`;
      };

      // ===== Función: Cargar roles en cualquier select (usa listar-min) =====
      function cargarRolesEnSelect(selectId, selectedId = '', incluirTodos = false) {
        return $.ajax({
          url: URL_ROLES,
          method: 'POST',
          dataType: 'json',
          data: { accion: 'listar-min', limite: 200 } // <- usa tu acción para selects
        })
        .done(function(resp){
          const $sel = $('#' + selectId);
          $sel.empty();

          if (incluirTodos) {
            $sel.append('<option value="">Todos</option>');
          } else {
            $sel.append('<option value="">Seleccione un rol...</option>');
          }

          // Tu controller devuelve { data: [ { id_rol, nombre, nombre_mostrar? } ] }
          const arr = Array.isArray(resp?.data) ? resp.data : [];
          arr.forEach(r => {
            const text = r.nombre_mostrar || r.nombre || ('Rol ' + r.id_rol);
            const opt  = $('<option/>', { value: r.id_rol, text });
            $sel.append(opt);
          });

          if (selectedId !== '' && selectedId != null) {
            $sel.val(String(selectedId));
          }
        })
        .fail(function(xhr){
          console.error('Error cargando roles', xhr);
          (typeof toastr !== 'undefined' && toastr.error)
            ? toastr.error('No se pudieron cargar los roles')
            : alert('No se pudieron cargar los roles');
        });
      }

      // ===== Filtros =====
      $(".filtrar")
        .change(function(){
          const $el = $(this);
          if(($el.is(':checkbox') && $el.is(':checked')) || ($el.val() && $el.val().length>0))
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else
            $el.closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
          $el.blur();
          setTimeout(()=> cargarUsuarios(1), 200);
        })
        .keypress(function(e){ if (e.charCode == 13) cargarUsuarios(1); })
        .keyup(function(){
          if ($(this).val().length > 0) $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'flex'});
          else $(this).closest('.input-group, .form-group').find(".clean-filter").css({display:'none'});
        });

      $(".clean-filter").click(function(){
        const $el = $(this).closest('.input-group, .form-group').find('.filtrar');
        if ($el.is(':checkbox')){ $el.prop('checked', false).trigger('change'); }
        else { $el.val('').trigger('change'); if ($el.hasClass('select2')) $el.select2('val', 0); }
        cargarUsuarios(1);
      });

      // ===== Cargar usuarios =====
      function cargarUsuarios(pagina){
        paginaActual = pagina;

        const filtros = {
          accion: 'listar',
          pagina,
          limite: limitePorPagina,
          q:        $('#FiltroQ').val(),
          nombre:   $('#FiltroNombre').val(),
          usuario:  $('#FiltroUsuarioLogin').val(),
          correo:   $('#FiltroCorreo').val(),
          telefono: $('#FiltroTelefono').val(),
          id_rol:   $('#FiltroRolId').val() || '',
          activo:   $('#FiltroActivo').val()
        };

        $.ajax({
          url: URL_CTRL, method: 'POST', dataType: 'json', data: filtros
        })
        .done(function(resp){
          const arr = resp?.data || [];
          const total = parseInt(resp?.total || 0, 10);

          renderizarTabla(arr);

          if (total === 0) {
            $('#infoUsuarios').text('No hay usuarios para mostrar');
          } else {
            const desde = (pagina - 1) * limitePorPagina + 1;
            const hasta = Math.min(pagina * limitePorPagina, total);
            $('#infoUsuarios').text(`Mostrando ${desde} a ${hasta} de ${total} usuarios`);
          }

          configurarPaginacion(pagina, total, limitePorPagina);
        })
        .fail(function(){
          toastr.error('Error al cargar los usuarios.');
        });
      }

      function renderizarTabla(rows){
        let tbody = '';
        if (!rows.length){
          $('#emptyState').removeClass('d-none');
          tbody = '<tr><td colspan="9" class="text-center text-muted">— No hay registros —</td></tr>';
        } else {
          $('#emptyState').addClass('d-none');
          rows.forEach(v => {
            const id   = v.id_usuario;
            const nom  = v.nombre || '—';
            const usr  = v.usuario || '—';
            const cor  = v.correo || '—';
            const tel  = v.telefono || '—';
            const rol  = (v.nombre_rol != null) ? v.nombre_rol : '—';
            const est  = badgeActivo(v.activo);
            const fcre = ymdHisToEs(v.fecha_creacion);

            tbody += `
              <tr>
                <td class="text-center text-muted"><b>${id}</b></td>
                <td>${escapeHtml(nom)}</td>
                <td>${escapeHtml(usr)}</td>
                <td>${escapeHtml(cor)}</td>
                <td>${escapeHtml(tel)}</td>
                <td class="text-center">${escapeHtml(rol)}</td>
                <td class="text-center">${est}</td>
                <td class="text-center">${fcre}</td>
                <td class="text-center">
                  <div class="btn-group dropdown">
                    <a href="javascript:void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false">
                      <i class="mdi mdi-dots-horizontal"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                      <a class="dropdown-item accion-editar" href="#" data-id="${id}">
                        <i class="mdi mdi-square-edit-outline mr-2 text-muted font-18 vertical-middle"></i>Editar
                      </a>
                      <a class="dropdown-item accion-eliminar" href="#" data-id="${id}" data-nombre="${escapeHtml(nom)}">
                        <i class="mdi mdi-delete mr-2 text-muted font-18 vertical-middle"></i>Eliminar
                      </a>
                    </div>
                  </div>
                </td>
              </tr>`;
          });
        }
        $('#tbodyUsuarios').html(tbody);
      }

      function configurarPaginacion(currentPage, totalItems, itemsPerPage=10)
      {
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
          if (Number.isFinite(page)) { paginaActual = page; cargarUsuarios(paginaActual); }
        });
      }

      // ===== Detalle =====
      $(document).on('click', 'a.accion-ver', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;

        $.ajax({
          url: URL_CTRL, method: 'GET', dataType: 'json',
          data: { accion: 'detalle', id_usuario: id }
        })
        .done(function(resp){
          const v = resp?.data || null;
          if (!v){ toastr.error('No se encontró el usuario.'); return; }

          $('#id_usuario').val(v.id_usuario ?? '');
          $('#nombre').val(v.nombre || '');
          $('#usuario').val(v.usuario || '');
          $('#correo').val(v.correo || '');
          $('#telefono').val(v.telefono || '');

          $('#tituloModal').text('Detalle de usuario');
          $('#btnGuardar').hide();

          cargarRolesEnSelect('id_rol', v.id_rol).then(() => {
            $('#modalUsuario').modal('show');
          });
        })
        .fail(function(){ toastr.error('Error al cargar el detalle.'); });
      });

      // ===== Nuevo =====
      $('#btnNuevo').on('click', function(){
        $('#formUsuario')[0].reset();
        $('#id_usuario').val('');
        $('#tituloModal').text('Nuevo usuario');
        $('#btnGuardar').show();

        // Cargar roles y luego abrir modal
        cargarRolesEnSelect('id_rol').then(() => {
          $('#modalUsuario').modal('show');
        });
      });

      // ===== Editar =====
      $(document).on('click', 'a.accion-editar', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;

        $.ajax({
          url: URL_CTRL, method: 'GET', dataType: 'json',
          data: { accion: 'detalle', id_usuario: id }
        })
        .done(function(resp){
          const r = resp?.data || null;
          if (!r){ toastr.error('No se encontró el usuario.'); return; }
          $('#id_usuario').val(r.id_usuario);
          $('#nombre').val(r.nombre || '');
          $('#usuario').val(r.usuario || '');
          $('#correo').val(r.correo || '');
          $('#telefono').val(r.telefono || '');

          $('#tituloModal').text('Editar usuario');
          $('#btnGuardar').show();

          // Cargar roles y preseleccionar
          cargarRolesEnSelect('id_rol', r.id_rol).then(() => {
            $('#modalUsuario').modal('show');
          });
        })
        .fail(function(){ toastr.error('Error al obtener el detalle.'); });
      });

      // ===== Guardar (crear/actualizar) =====
      $('#formUsuario').on('submit', function(e){
        e.preventDefault();
        const id = $('#id_usuario').val();
        const payload = {
          id_usuario: id || undefined,
          nombre: $('#nombre').val().trim(),
          usuario: $('#usuario').val().trim(),
          correo: $('#correo').val().trim() || null,
          telefono: $('#telefono').val().trim() || null,
          id_rol: $('#id_rol').val() !== '' ? Number($('#id_rol').val()) : null
        };
        if (!payload.nombre || !payload.usuario){
          toastr.warning('Los campos Nombre y Usuario son obligatorios.');
          return;
        }
        const accion = id ? 'actualizar' : 'crear';
        $.ajax({
          url: URL_CTRL + '?accion=' + accion,
          method: 'POST',
          data: JSON.stringify(payload),
          contentType: 'application/json; charset=UTF-8',
          dataType: 'json'
        })
        .done(function(resp){
          const ok = !!resp?.ok || (accion==='crear' && resp?.id_usuario>0);
          if (ok){
            $('#modalUsuario').modal('hide');
            toastr.success('Usuario guardado correctamente.');
            cargarUsuarios(accion==='crear' ? 1 : paginaActual);
          } else {
            toastr.error(resp?.msg || 'No se pudo guardar.');
          }
        })
        .fail(function(){ toastr.error('Error al guardar.'); });
      });

      // ===== Eliminar =====
      $(document).on('click', 'a.accion-eliminar', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        const nom = $(this).data('nombre') || '';
        $('#btnConfirmEliminar').data('id', id);
        $('#delNombre').text(nom);
        $('#modalEliminar').modal('show');
      });

      $('#btnConfirmEliminar').on('click', function(){
        const id = $(this).data('id');
        if (!id) return;
        $.ajax({
          url: URL_CTRL, method: 'POST', dataType: 'json',
          data: { accion: 'eliminar', id_usuario: id }
        })
        .done(function(resp){
          if (resp?.ok){
            $('#modalEliminar').modal('hide');
            toastr.success('Usuario eliminado.');
            cargarUsuarios(paginaActual);
          } else {
            toastr.error(resp?.msg || 'No se pudo eliminar.');
          }
        })
        .fail(function(){ toastr.error('Error al eliminar.'); });
      });

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
