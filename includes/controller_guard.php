<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/acl.php';

function controller_permission_map(): array {
    return [
        'BitacoraController.php'           => 'sistema.bitacora',
        'CajasController.php'              => 'catalogos.cajas',
        'CatGruposController.php'          => 'catalogos.menu',
        'ClientesController.php'           => 'catalogos.clientes',
        'ComprasClientesController.php'    => 'talleres.menu',
        'ComprasController.php'            => 'compras.gestion',
        'DashboardController.php'          => 'menu.inicio',
        'FaltantesController.php'          => 'inventarios.faltantes',
        'FormasPagoController.php'         => 'catalogos.menu',
        'InventarioMovimientosController.php' => 'inventarios.movimientos',
        'KardexProductoController.php'     => 'inventarios.movimientos',
        'LogoutController.php'             => 'menu.inicio',
        'PermisosController.php'           => 'sistema.roles',
        'PrestamosController.php'          => 'ventas.prestamos',
        'ProductosController.php'          => 'inventarios.productos',
        'ProveedoresController.php'        => 'catalogos.proveedores',
        'ReportesController.php'           => 'reportes.menu',
        'RolesController.php'              => 'sistema.roles',
        'SucursalesController.php'         => 'catalogos.sucursales',
        'UnidadesSatController.php'        => 'catalogos.unidades',
        'UsuariosController.php'           => 'sistema.usuarios',
        'VentasController.php'             => 'ventas.menu',
    ];
}

function controller_guard(string $controllerFile, ?string $requiredPermission = null, bool $requireAuth = true): void {
    if (!$requireAuth) {
        return;
    }

    $respondJson = (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false)
        || (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'json') !== false);

    $respond = static function (int $code, string $message) use ($respondJson): void {
        http_response_code($code);
        if ($respondJson) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'msg' => $message], JSON_UNESCAPED_UNICODE);
        } else {
            echo $message;
        }
        exit;
    };

    if (!isset($_SESSION['usuario'])) {
        $respond(401, 'No autenticado.');
    }

    $permissionMap = controller_permission_map();
    $permission    = $requiredPermission ?? ($permissionMap[basename($controllerFile)] ?? null);

    if ($permission !== null && !can($permission)) {
        $respond(403, 'No tienes permisos suficientes.');
    }
}
?>
