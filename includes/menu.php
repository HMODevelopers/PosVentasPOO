<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/acl.php'; // incluye el helper can(...)
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
            <li><a href="<?= BASE_URL ?>/views/private/inicio/index.php">Panel Principal</a></li>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('ventas.menu')): ?>
        <li class="has-submenu">
          <a href="#"><i class="la la-money"></i>Ventas <div class="arrow-down"></div></a>
          <ul class="submenu">
            <?php if (can('ventas.historial')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/ventas/index.php">Historial Ventas</a></li>
            <?php endif; ?>
            <?php if (can('ventas.prestamos')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/prestamos/index.php">Prestamos y Abonos</a></li>
            <?php endif; ?>
            <?php if (can('ventas.pos')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/caja/index.php">Punto de Venta (POS)</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('compras.menu')): ?>
        <li class="has-submenu">
          <a href="#"><i class="la la-file-text-o"></i>Compras <div class="arrow-down"></div></a>
          <ul class="submenu">
            <?php if (can('compras.gestion')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/compras/index.php">Gestionar Compras</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('inventarios.menu')): ?>
        <li class="has-submenu">
          <a href="#"><i class="la la-cubes"></i>Inventarios <div class="arrow-down"></div></a>
          <ul class="submenu megamenu">
            <li>
              <ul>
                <?php if (can('inventarios.productos')): ?>
                  <li><a href="<?= BASE_URL ?>/views/private/productos/index.php">Productos</a></li>
                <?php endif; ?>
                <?php if (can('inventarios.faltantes')): ?>
                  <li><a href="<?= BASE_URL ?>/views/private/inventarios/faltantes.php">Faltantes Inventario</a></li>
                <?php endif; ?>
                <?php if (can('inventarios.movimientos')): ?>
                  <li><a href="<?= BASE_URL ?>/views/private/inventarios/index.php">Movimiento Inventario</a></li>
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
              <li><a href="<?= BASE_URL ?>/views/private/utilidades/comparador.php">Comparador proveedores</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if (can('catalogos.menu')): ?>
        <li class="has-submenu">
          <a href="#"><i class="la la-folder"></i>Catalogos <div class="arrow-down"></div></a>
          <ul class="submenu">
            <?php if (can('catalogos.proveedores')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/catalogos/proveedores.php">Proveedores</a></li>
            <?php endif; ?>
            <?php if (can('catalogos.clientes')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/catalogos/clientes.php">Clientes</a></li>
            <?php endif; ?>
            <?php if (can('catalogos.unidades')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/catalogos/unidadsat.php">Unidades Sat</a></li>
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
          <a href="#"><i class="la la-car ms-1"></i>Talleres <div class="arrow-down"></div></a>
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
              <li><a href="<?= BASE_URL ?>/views/private/sistema/bitacora.php">Bitacora Movimientos</a></li>
            <?php endif; ?>
            <?php if (can('sistema.usuarios')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/sistema/usuarios.php">Usuarios</a></li>
            <?php endif; ?>
            <?php if (can('sistema.roles')): ?>
              <li><a href="<?= BASE_URL ?>/views/private/sistema/roles.php">Roles</a></li>
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
