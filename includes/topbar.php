<?php
// ================== HEADER / NAV + MODAL CAMBIAR PASSWORD ==================

// Asegura sesión y ACL antes de pintar el menú
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('BASE_URL')) {
  require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/acl.php';
acl_bootstrap();

// CSRF simple para formularios POST
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$esAdmin            = (int)($_SESSION['usuario']['id_rol'] ?? 0) === 1;
$puedeMenuReportes  = ($esAdmin || can('reportes.menu'));
$puedeRepCreditos   = ($esAdmin || can('reportes.creditos'));
$puedeRepUtilidad   = ($esAdmin || can('reportes.utilidad'));
?>
<!-- Forzar modal al frente -->
<style>
  .modal-backdrop { z-index: 1050 !important; }
  .modal          { z-index: 1060 !important; }
</style>

<div class="navbar-custom">
  <div class="container-fluid">
    <ul class="list-unstyled topnav-menu float-right mb-0">

      <li class="dropdown notification-list">
        <a class="navbar-toggle nav-link">
          <div class="lines">
            <span></span><span></span><span></span>
          </div>
        </a>
      </li>

      <!-- USUARIO -->
      <li class="dropdown notification-list">
        <a class="nav-link dropdown-toggle nav-user mr-0 waves-effect" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
          <img src="<?= BASE_URL ?>/assets/images/users/user-1.jpg" alt="user-image" class="rounded-circle">
          <span class="pro-user-name ml-1">
            <?= htmlspecialchars($_SESSION['usuario']['nombre'] ?? 'Invitado') ?> <i class="mdi mdi-chevron-down"></i>
          </span>
        </a>
        <div class="dropdown-menu dropdown-menu-right profile-dropdown ">
          <div class="dropdown-item noti-title"><h5 class="m-0">Bienvenido</h5></div>

          <!-- Abrir modal -->
          <a href="javascript:void(0);" class="dropdown-item notify-item js-open-cambiar-pass">
            <i class="fe-lock"></i><span>Cambiar Password</span>
          </a>

          <a href="javascript:void(0);" class="dropdown-item notify-item"><i class="fe-settings"></i><span>Configuración</span></a>
          <a href="javascript:void(0);" class="dropdown-item notify-item"><i class="fe-monitor"></i><span>Bloquear Pantalla</span></a>
          <div class="dropdown-divider"></div>
          <a href="<?= BASE_URL ?>/controllers/LogoutController.php" class="dropdown-item notify-item"><i class="fe-log-out"></i><span>Cerrar Sesión</span></a>
        </div>
      </li>

    </ul>

    <!-- LOGO -->
    <div class="logo-box">
      <a href="#" class="logo text-center">
        <span class="logo-lg"><img src="<?= BASE_URL ?>/assets/images/rr1_black.png" alt="" height="50"></span>
        <span class="logo-sm"><img src="<?= BASE_URL ?>/assets/images/rr1_black.png" alt="" height="50"></span>
      </a>
    </div>

    <ul class="list-unstyled topnav-menu topnav-menu-left m-0">
      <?php if ($puedeMenuReportes && ($puedeRepCreditos || $puedeRepUtilidad)): ?>
        <li class="dropdown d-none d-lg-block">
          <a class="nav-link dropdown-toggle waves-effect" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
            Reportes <i class="mdi mdi-chevron-down"></i>
          </a>
          <div class="dropdown-menu">
            <?php if ($puedeRepCreditos): ?>
              <a href="<?= BASE_URL ?>/views/private/reportes/valechalio.php" class="dropdown-item">Reporte Créditos</a>
            <?php endif; ?>
            <?php if ($puedeRepUtilidad): ?>
              <a href="<?= BASE_URL ?>/views/private/reportes/reporteUtilidad.php" class="dropdown-item">Utilidades Productos</a>
            <?php endif; ?>
          </div>
        </li>
      <?php endif; ?>
      <!-- otros menús aquí... -->
    </ul>

    <div class="clearfix"></div>
  </div>
</div>

<!-- =================== MODAL CAMBIAR CONTRASEÑA =================== -->
<div class="modal fade" id="modalCambiarPassword" tabindex="-1" role="dialog" aria-labelledby="modalCambiarPasswordLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form id="formCambiarPassword" class="modal-content" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCambiarPasswordLabel">Cambiar contraseña</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="form-group">
          <label>Contraseña actual</label>
          <div class="input-group">
            <input type="password" class="form-control" name="actual" required minlength="6" />
            <div class="input-group-append">
              <button class="btn btn-outline-secondary toggle-pass" type="button" tabindex="-1" aria-label="Mostrar contraseña">
                <i class="fe-eye"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Nueva contraseña</label>
          <div class="input-group">
            <input type="password" class="form-control" name="nueva" required minlength="8" />
            <div class="input-group-append">
              <button class="btn btn-outline-secondary toggle-pass" type="button" tabindex="-1" aria-label="Mostrar contraseña">
                <i class="fe-eye"></i>
              </button>
            </div>
          </div>
          <small class="form-text text-muted">Mínimo 8 caracteres, combina mayúsculas, minúsculas y números.</small>
        </div>

        <div class="form-group">
          <label>Confirmar nueva contraseña</label>
          <div class="input-group">
            <input type="password" class="form-control" name="confirmar" required minlength="8" />
            <div class="input-group-append">
              <button class="btn btn-outline-secondary toggle-pass" type="button" tabindex="-1" aria-label="Mostrar contraseña">
                <i class="fe-eye"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="alert d-none" id="alertCambiarPass" role="alert"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btnGuardarPass">
          <span class="spinner-border spinner-border-sm d-none mr-1" id="spCambiarPass" role="status" aria-hidden="true"></span>
          Guardar y cerrar sesión
        </button>
      </div>
    </form>
  </div>
</div>

<!-- =================== JS para el modal =================== -->
<script>
(function(){
  const BASE = "<?= BASE_URL ?>";

  // Abre el modal desde el menú (y lo mueve al body para evitar stacking issues)
  document.addEventListener('click', function(e){
    const t = e.target.closest('.js-open-cambiar-pass');
    if (!t) return;
    e.preventDefault();

    const form = document.getElementById('formCambiarPassword');
    if (form) form.reset();

    const alert = document.getElementById('alertCambiarPass');
    if (alert) { alert.className = 'alert d-none'; alert.textContent = ''; }

    // Mover y abrir
    const $modal = $('#modalCambiarPassword');
    if ($modal.length) {
      $modal.appendTo('body').modal('show');
    }
  });

  // Toggle ver/ocultar con icono feather
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.toggle-pass');
    if (!btn) return;

    const input = btn.closest('.input-group').querySelector('input');
    if (!input) return;

    const icon = btn.querySelector('i');
    const isPwd = input.type === 'password';
    input.type = isPwd ? 'text' : 'password';

    if (icon) {
      icon.classList.toggle('fe-eye', !isPwd);
      icon.classList.toggle('fe-eye-off', isPwd);
    }
    btn.setAttribute('aria-label', isPwd ? 'Ocultar contraseña' : 'Mostrar contraseña');
  });

  // Enviar formulario
  document.getElementById('formCambiarPassword').addEventListener('submit', function(e){
    e.preventDefault();
    const f = e.currentTarget;
    const data = new FormData(f);
    const actual    = data.get('actual')    || '';
    const nueva     = data.get('nueva')     || '';
    const confirmar = data.get('confirmar') || '';

    const alert = document.getElementById('alertCambiarPass');
    const btn   = document.getElementById('btnGuardarPass');
    const sp    = document.getElementById('spCambiarPass');

    function showAlert(type, msg){
      alert.className = 'alert alert-' + type;
      alert.textContent = msg;
      alert.classList.remove('d-none');
    }

    // Validaciones rápidas
    if (nueva !== confirmar) {
      showAlert('warning', 'La confirmación no coincide.');
      return;
    }
    if (nueva.length < 8) {
      showAlert('warning', 'La nueva contraseña debe tener al menos 8 caracteres.');
      return;
    }
    if (actual === nueva) {
      showAlert('warning', 'La nueva contraseña no puede ser igual a la actual.');
      return;
    }

    btn.disabled = true; sp.classList.remove('d-none'); alert.classList.add('d-none');

    // Usa el UsuarioController con la acción cambiar-password
    fetch(BASE + '/controllers/UsuariosController.php?accion=cambiar-password', {
      method: 'POST',
      body: data,
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(json => {
      if (json && json.ok) {
        showAlert('success', 'Contraseña actualizada. Cerrando sesión...');
        setTimeout(() => {
          // Si te interesa usar la URL que devuelve el controller:
          if (json.logout_url) { window.location.href = json.logout_url; }
          else { window.location.href = BASE + '/controllers/LogoutController.php'; }
        }, 700);
      } else {
        showAlert('danger', (json && json.msg) ? json.msg : 'No se pudo cambiar la contraseña.');
      }
    })
    .catch(() => showAlert('danger', 'Error de red al cambiar la contraseña.'))
    .finally(() => { btn.disabled = false; sp.classList.add('d-none'); });
  });
})();
</script>
