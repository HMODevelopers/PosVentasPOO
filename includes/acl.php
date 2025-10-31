<?php
// includes/acl.php
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Devuelve true si el rol actual puede ver la clave del menú.
 * Edita el array $ACL para ajustar quién ve qué.
 *
 * Roles según tu catálogo (ajústalo si cambian los IDs):
 * 1 Administrador, 2 Cajero, 3 Almacén, 4 Supervisor, 5 Vendedor,
 * 6 Invitado, 8 Taller, 9 Cliente
 */
function can(string $key): bool {
  static $ACL = [
    'menu.inicio'              => [1,2,3,4,5,6,8,9],

    'ventas.menu'              => [1,2,5],
    'ventas.historial'         => [1,2,4,5],
    'ventas.prestamos'         => [1,2],
    'ventas.pos'               => [1,2,5],

    'compras.menu'             => [1,3],
    'compras.gestion'          => [1,3],

    'inventarios.menu'         => [1,3,4],
    'inventarios.productos'    => [1,3,4],
    'inventarios.faltantes'    => [1,3,4],
    'inventarios.movimientos'  => [1,3,4],

    'utilidades.menu'          => [1,3,4],
    'utilidades.comparador'    => [1,3,4],

    'catalogos.menu'           => [1,3,4],
    'catalogos.proveedores'    => [1,3,4],
    'catalogos.clientes'       => [1,3,4,2,5],
    'catalogos.unidades'       => [1,3],
    'catalogos.sucursales'     => [1,3],
    'catalogos.cajas'          => [1],

    'talleres.menu'            => [1,8],
    'talleres.lista'           => [1,8],
    'talleres.miscompras'      => [1,8],

    'sistema.menu'             => [1,4],
    'sistema.bitacora'         => [1,4],
    'sistema.usuarios'         => [1],
    'sistema.roles'            => [1],
  ];

  // **Clave:** lee el id_rol desde tu estructura actual
  $rol = (int)($_SESSION['usuario']['id_rol'] ?? 0);
  return isset($ACL[$key]) && in_array($rol, $ACL[$key], true);
}
