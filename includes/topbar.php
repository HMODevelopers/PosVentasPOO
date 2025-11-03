<?php
// Asegura sesión y ACL antes de pintar el menú
if (session_status() === PHP_SESSION_NONE) session_start();

// Si este header se incluye de forma independiente, garantizamos config + ACL
if (!defined('BASE_URL')) {
  require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/acl.php';
acl_bootstrap();

$esAdmin            = (int)($_SESSION['usuario']['id_rol'] ?? 0) === 1;
$puedeMenuReportes  = ($esAdmin || can('reportes.menu'));
$puedeRepCreditos   = ($esAdmin || can('reportes.creditos'));
$puedeRepUtilidad   = ($esAdmin || can('reportes.utilidad'));

// (opcional) debug rápido en el HTML para verificar
// echo "<!-- esAdmin=".($esAdmin?'1':'0')." menu=$puedeMenuReportes cred=$puedeRepCreditos util=$puedeRepUtilidad -->";
?>

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
          <a href="javascript:void(0);" class="dropdown-item notify-item"><i class="fe-user"></i><span>Mi Cuenta</span></a>
          <a href="javascript:void(0);" class="dropdown-item notify-item"><i class="fe-settings"></i><span>Configuración</span></a>
          <a href="javascript:void(0);" class="dropdown-item notify-item"><i class="fe-lock"></i><span>Bloquear Pantalla</span></a>
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
