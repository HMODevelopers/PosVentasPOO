<?php
// CAMBIO: encabezado igual que Bitácora; mantengo validaciones de sesión y admin
$titulo = "Sistema";
$modulo = "Permisos por Rol";
$subtitulo = "Control de permisos y página de inicio";
session_start();

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/acl.php';

if (!isset($_SESSION['usuario'])) {
  header('Location: ' . BASE_URL . '/views/public/index.php'); exit;
}
if ((int)$_SESSION['usuario']['id_rol'] !== 1) {
  die('No autorizado.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>ACL | REFASOFT-V4</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- CAMBIO: mismos CSS que Bitácora para unificar look&feel -->
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.ico">
  <link href="<?= BASE_URL ?>/assets/libs/jquery-vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/app.min.css" rel="stylesheet" type="text/css" />
  <link href="<?= BASE_URL ?>/assets/css/loader.css" rel="stylesheet" />

  <!-- Toastr -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>

  <style>
    /* CAMBIO: estilos consistentes con tus cards/listas */
    .perm-cat { background:#f8f9fa; font-weight:600; padding:.5rem .75rem; border:1px solid #e9ecef; }
    .perm-item { padding:.25rem .75rem; border-bottom:1px dotted #eee; }
    .sticky-top { top: 70px; z-index: 100; }
    .small-muted { font-size: .875rem; color:#6c757d; }
  </style>
</head>
<body>

  <!-- Navigation Bar -->
  <?php include __DIR__ . '/../../../includes/header.php'; ?>
  <!-- End Navigation Bar -->

  <div class="wrapper">

    <!-- CAMBIO: Loader como en Bitácora -->
    <div class="wrapper-loader fade" id="LoadingImage" style="display: none;">
      <div class="loader">
        <div class="loader__figure"></div>
        <p class="loader__label">Cargando...</p>
      </div>
    </div>

    <div class="container-fluid">

      <!-- CAMBIO: Breadcrumb como en Bitácora -->
      <?php include __DIR__ . '/../../../includes/breadcrumb.php'; ?>

      <div class="row mt-2">
        <div class="col-lg-3">
          <div class="card sticky-top">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Rol</h5>
                <!-- CAMBIO: contador visual de checks -->
                <span class="badge badge-primary" id="badgeSelected" title="Permisos seleccionados">0</span>
              </div>

              <!-- CAMBIO: select de roles con disabled mientras carga -->
              <select id="selRol" class="form-select form-control mt-2" disabled></select>

              <div class="mt-3">
                <label class="form-label">Página de inicio del rol</label>
                <select id="selStart" class="form-select form-control">
                  <!-- CAMBIO: rutas ajustadas a tu menú real -->
                  <option value="/views/private/inicio/index.php">Inicio (Dashboard)</option>
                  <option value="/views/private/caja/index.php">Ventas → POS</option>
                  <option value="/views/private/ventas/index.php">Ventas → Historial</option>
                  <option value="/views/private/compras/index.php">Compras</option>
                  <option value="/views/private/productos/index.php">Inventarios → Productos</option>
                  <option value="/views/private/catalogos/clientes.php">Catálogos → Clientes</option>
                  <option value="/views/private/talleres/miscompras.php">Talleres → Mis Compras</option>
                  <option value="/views/private/sistema/usuarios.php">Sistema → Usuarios</option>
                </select>
                <div class="small-muted mt-1">Estas rutas coinciden con tu menú actual.</div>
              </div>

              <button id="btnGuardar" class="btn btn-primary w-100 mt-3" disabled>Guardar cambios</button>
              <!-- CAMBIO: quité #msg inline y usé toastr; dejo un span por si quieres eco local -->
              <div id="msg" class="mt-2 small text-muted"></div>
            </div>
          </div>
        </div>

        <div class="col-lg-9">
          <div class="card">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Permisos</h5>
                <!-- CAMBIO: quick actions -->
                <div class="btn-group">
                  <button id="btnMarcarTodo" type="button" class="btn btn-sm btn-light">Marcar todo</button>
                  <button id="btnDesmarcarTodo" type="button" class="btn btn-sm btn-light">Desmarcar todo</button>
                </div>
              </div>
              <div id="permList" class="mt-2"></div>
              <!-- CAMBIO: estado vacío -->
              <div id="emptyState" class="alert alert-warning d-none mt-2">No hay permisos definidos en catálogo.</div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /container -->
  </div><!-- /wrapper -->

  <!-- Footer -->
  <?php include __DIR__ . '/../../../includes/footer.php'; ?>
  <div class="rightbar-overlay"></div>

  <!-- Vendor js -->
  <script src="<?= BASE_URL ?>/assets/js/vendor.min.js"></script>
  <!-- App js -->
  <script src="<?= BASE_URL ?>/assets/js/app.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/loader.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    (function(){
      'use strict';

      const BASE_URL = "<?= BASE_URL ?>";
      const URL_CTRL = `${BASE_URL}/controllers/PermisosController.php`; // CAMBIO: uso constante central
      let state = { roles:[], permisos:[], asig:[], inicios:[], currentRol:null, loading:false };

      // ===== Utils =====
      const $selRol   = $('#selRol');
      const $selStart = $('#selStart');
      const $permList = $('#permList');
      const $btnSave  = $('#btnGuardar');
      const $badgeSel = $('#badgeSelected');
      const $empty    = $('#emptyState');

      function setLoading(on){
        state.loading = !!on;
        if (on) {
          $('#LoadingImage').show();
          $btnSave.prop('disabled', true);
          $selRol.prop('disabled', true);
        } else {
          $('#LoadingImage').hide();
          $btnSave.prop('disabled', false);
          $selRol.prop('disabled', false);
        }
      }

      function groupBy(arr, key){
        return arr.reduce((acc,x)=>((acc[x[key]]=acc[x[key]]||[]).push(x),acc),{});
      }

      function checked(idRol, clave){
        return state.asig.some(a => +a.id_rol === +idRol && a.clave === clave);
      }

      function currentStart(idRol){
        const x = state.inicios.find(i => +i.id_rol === +idRol);
        return x ? x.start_path : '/views/private/inicio/index.php';
      }

      function updateBadge(){
        const total = $('.perm-chk:checked').length;
        $badgeSel.text(total);
      }

      function render(){
        // roles
        const wasNull = (state.currentRol == null);
        $selRol.empty();
        if (!state.roles.length){
          $selRol.append('<option value="">(Sin roles)</option>');
          $selRol.prop('disabled', true);
        } else {
          state.roles.forEach(r => $selRol.append(`<option value="${r.id_rol}">${r.id_rol} — ${r.nombre}</option>`));
          if (wasNull) state.currentRol = state.roles[0].id_rol;
          $selRol.prop('disabled', false);
        }
        $selRol.val(state.currentRol);

        // start path
        $selStart.val( currentStart(state.currentRol) );

        // permisos agrupados
        $permList.empty();
        if (!state.permisos.length){
          $empty.removeClass('d-none');
        } else {
          $empty.addClass('d-none');
          const grupos = groupBy(state.permisos,'categoria');
          Object.keys(grupos).sort().forEach(cat => {
            $permList.append(`<div class="perm-cat">${cat}</div>`);
            grupos[cat].forEach(p => {
              const id = `perm_${p.clave.replace(/\W/g,'_')}`;
              const isChecked = checked(state.currentRol, p.clave) ? 'checked' : '';
              $permList.append(`
                <div class="perm-item form-check">
                  <input class="form-check-input perm-chk" type="checkbox" data-clave="${p.clave}" id="${id}" ${isChecked}>
                  <label class="form-check-label" for="${id}">${p.nombre} <span class="text-muted">(${p.clave})</span></label>
                </div>
              `);
            });
          });
        }

        updateBadge();
      }

      function loadAll(){
        setLoading(true);
        $.getJSON(URL_CTRL, {action:'listar'})
          .done(resp=>{
            if(!resp || resp.ok !== true){
              toastr.error(resp?.msg || 'No fue posible cargar datos.');
              return;
            }
            // CAMBIO: normalizo arrays por si el backend retorna null
            state.roles    = Array.isArray(resp.roles)    ? resp.roles    : [];
            state.permisos = Array.isArray(resp.permisos) ? resp.permisos : [];
            state.asig     = Array.isArray(resp.asig)     ? resp.asig     : [];
            state.inicios  = Array.isArray(resp.inicios)  ? resp.inicios  : [];
            render();
          })
          .fail(()=> toastr.error('Error de red al cargar permisos/roles.'))
          .always(()=> setLoading(false));
      }

      // ===== Eventos =====
      $(document).on('change', '#selRol', function(){
        state.currentRol = +$(this).val();
        $selStart.val( currentStart(state.currentRol) );
        render();
      });

      $(document).on('change', '.perm-chk', updateBadge);

      $('#btnMarcarTodo').on('click', function(){
        $('.perm-chk').prop('checked', true);
        updateBadge();
      });
      $('#btnDesmarcarTodo').on('click', function(){
        $('.perm-chk').prop('checked', false);
        updateBadge();
      });

      $btnSave.on('click', function(){
        if (state.currentRol == null || state.currentRol === '' || isNaN(+state.currentRol)) {
          toastr.warning('Selecciona un rol.');
          return;
        }
        const permisos = [];
        $('.perm-chk:checked').each((_,el)=>permisos.push(el.dataset.clave));

        // CAMBIO: validación mínima
        const payload = {
          idRol: +state.currentRol,
          permisos,
          startPath: ($selStart.val() || '/views/private/inicio/index.php').trim()
        };

        setLoading(true);
        $.ajax({
          url: `${URL_CTRL}?action=guardar`,
          method: 'POST',
          data: JSON.stringify(payload),
          contentType: 'application/json',
          dataType: 'json'
        })
        .done(resp=>{
          if (resp && resp.ok) {
            // CAMBIO: reflejar en estado local el start_path guardado
            const idx = state.inicios.findIndex(i => +i.id_rol === +state.currentRol);
            if (idx >= 0) state.inicios[idx].start_path = payload.startPath;
            else state.inicios.push({ id_rol: +state.currentRol, start_path: payload.startPath });

            toastr.success('Cambios guardados.');
            $('#msg').text('Cambios guardados'); // opcional eco local
            // CAMBIO: no forzamos recarga completa; el estado ya quedó
          } else {
            toastr.error(resp?.msg || 'No se pudo guardar.');
          }
        })
        .fail(()=> toastr.error('Error de red al guardar.'))
        .always(()=> setLoading(false));
      });

      // Init
      loadAll();
    })();
  </script>
</body>
</html>
