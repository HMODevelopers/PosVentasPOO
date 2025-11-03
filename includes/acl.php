<?php
// includes/acl.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php'; // $pdo

/**
 * Carga y cachea en $_SESSION los permisos por rol desde BD.
 */
function acl_bootstrap(): void {
  if (!isset($_SESSION['usuario']['id_rol'])) return;

  $idRol = (int)$_SESSION['usuario']['id_rol'];
  if (isset($_SESSION['acl_cache']['rol']) && $_SESSION['acl_cache']['rol'] === $idRol) {
    return; // ya cacheado
  }

  $_SESSION['acl_cache'] = [
    'rol'       => $idRol,
    'permisos'  => [],
    'startPath' => '/views/private/inicio/index.php'
  ];

  // Permisos del rol
  try {
    $stmt = $GLOBALS['pdo']->prepare("SELECT clave FROM acl_rol_permiso WHERE id_rol = ?");
    $stmt->execute([$idRol]);
    $_SESSION['acl_cache']['permisos'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'clave');

    // Página de inicio por rol (si la hay)
    $stmt2 = $GLOBALS['pdo']->prepare("SELECT start_path FROM acl_rol_inicio WHERE id_rol = ?");
    $stmt2->execute([$idRol]);
    if ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
      $_SESSION['acl_cache']['startPath'] = $row['start_path'] ?: '/views/private/inicio/index.php';
    }
  } catch (Throwable $e) {
    // fallback silencioso si las tablas aún no existen
  }
}

/**
 * Verifica si el rol actual tiene el permiso $key.
 * Si no hay BD/tables, puedes añadir aquí un fallback de emergencia (opcional).
 */
function can(string $key): bool {
  acl_bootstrap();
  $perms = $_SESSION['acl_cache']['permisos'] ?? [];
  return in_array($key, $perms, true);
}

/** Devuelve la página de inicio para el rol actual (ruta relativa SIN BASE_URL). */
function acl_start_path_for_current_role(): string {
  acl_bootstrap();
  return $_SESSION['acl_cache']['startPath'] ?? '/views/private/inicio/index.php';
}
