<?php
// CAMBIO: aseguramos sesión activa
if (session_status() === PHP_SESSION_NONE) session_start();

// CAMBIO: garantizamos BASE_URL; si ya la incluyes en header.php, este require no afecta.
if (!defined('BASE_URL')) {
  require_once __DIR__ . '/config.php';
}

// CAMBIO: iniciamos el ACL dinámico para tener permisos y start_path en caché
require_once __DIR__ . '/acl.php'; // incluye can(), acl_bootstrap()
acl_bootstrap();

// Helper opcional para marcar activo (si te sirve para CSS)
// function is_active($path) { return str_contains($_SERVER['REQUEST_URI'] ?? '', $path) ? 'active' : ''; }
?>

<div class="topbar-menu">
  <div class="container-fluid">
    <div id="navigation">
      <!-- Navigation Menu-->
      <ul class="navigation-menu">

        <?php if (can('menu.inicio')): ?>
        <li class="has-submenu">
          <a href="#"><i class="la la-home"></i>Inicio <div class="arrow-down"></div></a>
          <ul class="submenu">
            <!-- CAMBIO: etiqueta con tilde y consistencia -->
            <li><a href="<?= BASE_URL ?>/views/private/inicio/index.php">Panel principal</a></li>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('ventas.menu')): ?>
        <li class="has-submenu">
          <a href="#"><i class="la la-money"></i>Ventas <div class="arrow-down"></div></a>
          <ul class="submenu">
            <?php if (can('ventas.historial')): ?>
              <!-- CAMBIO: mantenemos tu ruta actual -->
              <li><a href="<?= BASE_URL ?>/views/private/ventas/index.php">Historial de ventas</a></li>
            <?php endif; ?>
            <?php if (can('ventas.prestamos')): ?>
              <!-- CAMBIO: ortografía "Préstamos" -->
              <li><a href="<?= BASE_URL ?>/views/private/prestamos/index.php">Préstamos y abonos</a></li>
            <?php endif; ?>
            <?php if (can('ventas.pos')): ?>
              <!-- CAMBIO: conservamos tu POS en /caja/index.php para no romper rutas -->
              <li><a href="<?= BASE_URL ?>/views/private/caja/index.php">Punto de venta (POS)</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('compras.menu')): ?>
        <li class="has-submenu">
          <a href="#"><i class="la la-file-text-o"></i>Compras <div class="arrow-down"></div></a>
          <ul class="submenu">
            <?php if (can('compras.gestion')): ?>
              <!-- CAMBIO: etiqueta más clara -->
              <li><a href="<?= BASE_URL ?>/views/private/compras/index.php">Gestionar compras</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('inventarios.menu')): ?>
        <li class="has-submenu">
          <a href="#"><i class="la la-cubes"></i>Inventarios <div class="arrow-down"></div></a>
          <ul class="submenu">
            <li>
              <ul>
                <?php if (can('inventarios.productos')): ?>
                  <!-- CAMBIO: mantenemos tu ruta actual a productos -->
                  <li><a href="<?= BASE_URL ?>/views/private/productos/index.php">Productos</a></li>
                <?php endif; ?>
                <?php if (can('inventarios.faltantes')): ?>
                  <li><a href="<?= BASE_URL ?>/views/private/inventarios/faltantes.php">Faltantes de inventario</a></li>
                <?php endif; ?>
                <?php if (can('inventarios.movimientos')): ?>
                  <li><a href="<?= BASE_URL ?>/views/private/inventarios/index.php">Movimientos de inventario</a></li>
                <?php endif; ?>
              </ul>
            </li>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('utilidades.menu')): ?>
        <li class="has-submenu">
          <a href="#"><i class="la la-wrench"></i>Utilidades <div class="arrow-down"></div></a>
          <ul class="submenu">
            <?php if (can('utilidades.comparador')): ?>
              <!-- CAMBIO: etiqueta con mayúscula en la segunda palabra -->
              <li><a href="<?= BASE_URL ?>/views/private/utilidades/comparador.php">Comparador de proveedores</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('catalogos.menu')): ?>
        <li class="has-submenu">
          <!-- CAMBIO: acento → "Catálogos" -->
          <a href="#"><i class="la la-folder"></i>Catálogos <div class="arrow-down"></div></a>
          <ul class="submenu">
            <?php if (can('catalogos.proveedores')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/catalogos/proveedores.php">Proveedores</a></li>
            <?php endif; ?>
            <?php if (can('catalogos.clientes')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/catalogos/clientes.php">Clientes</a></li>
            <?php endif; ?>
            <?php if (can('catalogos.unidades')): ?>
              <!-- CAMBIO: "SAT" en mayúsculas -->
              <li><a href="<?= BASE_URL ?>/views/private/catalogos/unidadsat.php">Unidades SAT</a></li>
            <?php endif; ?>
            <?php if (can('catalogos.sucursales')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/catalogos/sucursales.php">Sucursales</a></li>
            <?php endif; ?>
            <?php if (can('catalogos.cajas')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/catalogos/cajas.php">Cajas</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('talleres.menu')): ?>
        <li class="has-submenu">
          <!-- CAMBIO: removí la clase ms-1 para mayor compatibilidad si no usas Bootstrap 5 -->
          <a href="#"><i class="la la-car"></i>Talleres <div class="arrow-down"></div></a>
          <ul class="submenu">
            <?php if (can('talleres.lista')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/talleres/listasproductos.php">Lista de productos</a></li>
            <?php endif; ?>
            <?php if (can('talleres.miscompras')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/talleres/miscompras.php">Mis compras</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('sistema.menu')): ?>
        <li class="has-submenu">
          <a href="#"><i class="la la-cog"></i>Sistema <div class="arrow-down"></div></a>
          <ul class="submenu">
            <?php if (can('sistema.bitacora')): ?>
              <!-- CAMBIO: acento en "Bitácora" -->
              <li><a href="<?= BASE_URL ?>/views/private/sistema/bitacora.php">Bitácora de movimientos</a></li>
            <?php endif; ?>
            <?php if (can('sistema.usuarios')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/sistema/usuarios.php">Usuarios</a></li>
            <?php endif; ?>
            <?php if (can('sistema.roles')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/sistema/roles.php">Roles</a></li>
            <?php endif; ?>

            <?php
              // CAMBIO: acceso directo a la vista de Permisos (ACL) SOLO para Admin (id_rol=1),
              // sin depender de un permiso extra (útil si te bloqueas por error).
              $esAdmin = (int)($_SESSION['usuario']['id_rol'] ?? 0) === 1;
              if ($esAdmin):
            ?>
              <li><a href="<?= BASE_URL ?>/views/private/sistema/permisos.php">Permisos</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

      </ul>
      <!-- End navigation menu -->

      <div class="clearfix"></div>
    </div>
    <!-- end #navigation -->
  </div>
  <!-- end container -->
</div>
