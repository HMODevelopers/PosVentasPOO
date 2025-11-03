<?php
// controllers/PermisosController.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

// Requiere ser admin (id_rol == 1) o quien tú definas
if (!isset($_SESSION['usuario']) || (int)$_SESSION['usuario']['id_rol'] !== 1) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
  switch ($action) {
    case 'listar':
      // Permisos => agrupados x categoría
      $perms = $pdo->query("SELECT clave,nombre,categoria,orden FROM acl_permisos ORDER BY categoria, orden, nombre")->fetchAll(PDO::FETCH_ASSOC);
      // Roles
      $roles = $pdo->query("SELECT id_rol, nombre FROM roles ORDER BY id_rol")->fetchAll(PDO::FETCH_ASSOC);
      // Asignaciones
      $stmt = $pdo->query("SELECT id_rol, clave FROM acl_rol_permiso");
      $asig = $stmt->fetchAll(PDO::FETCH_ASSOC);
      // Inicios
      $inicios = $pdo->query("SELECT id_rol, start_path FROM acl_rol_inicio")->fetchAll(PDO::FETCH_ASSOC);

      echo json_encode([
        'ok'       => true,
        'permisos' => $perms,
        'roles'    => $roles,
        'asig'     => $asig,
        'inicios'  => $inicios
      ]);
      break;

    case 'guardar':
      $payload = json_decode(file_get_contents('php://input'), true);
      $idRol = (int)($payload['idRol'] ?? 0);
      $permisos = $payload['permisos'] ?? [];
      $startPath = trim($payload['startPath'] ?? '/views/private/inicio/index.php');

      if ($idRol <= 0) { throw new Exception('Rol inválido'); }

      $pdo->beginTransaction();

      // reset permisos del rol
      $stmtDel = $pdo->prepare("DELETE FROM acl_rol_permiso WHERE id_rol = ?");
      $stmtDel->execute([$idRol]);

      // insertar nuevos permisos
      if (!empty($permisos)) {
        $stmtIns = $pdo->prepare("INSERT INTO acl_rol_permiso (id_rol, clave) VALUES (?, ?)");
        foreach ($permisos as $k) {
          $stmtIns->execute([$idRol, $k]);
        }
      }

      // set start_path
      $stmtUp = $pdo->prepare("INSERT INTO acl_rol_inicio (id_rol, start_path) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE start_path = VALUES(start_path)");
      $stmtUp->execute([$idRol, $startPath]);

      $pdo->commit();

      // invalidar caché si el admin modifica su mismo rol
      if (isset($_SESSION['acl_cache']['rol']) && (int)$_SESSION['acl_cache']['rol'] === $idRol) {
        unset($_SESSION['acl_cache']);
      }

      echo json_encode(['ok'=>true]);      
      break;

    default:
      echo json_encode(['ok'=>false,'msg'=>'Acción inválida']);
  }
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error del servidor']);
}
