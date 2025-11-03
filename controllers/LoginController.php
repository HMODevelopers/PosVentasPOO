<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/acl.php';

header('Content-Type: text/plain');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo "Método no permitido."; exit; }

$usuario    = trim($_POST['usuario']    ?? '');
$contrasena = trim($_POST['contrasena'] ?? '');

if ($usuario === '' || $contrasena === '') { echo "Por favor completa todos los campos."; exit; }

try{
  $sql = "SELECT u.id_usuario,u.nombre,u.usuario,u.contrasena,u.id_rol,u.activo,u.id_cliente,
                 r.nombre AS rol_nombre,
                 c.id_cliente AS cliente_id,
                 c.nombre AS cliente_nombre
          FROM usuarios u
          LEFT JOIN roles r    ON r.id_rol = u.id_rol
          LEFT JOIN clientes c ON c.id_cliente = u.id_cliente
          WHERE u.usuario = :usuario
          LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':usuario'=>$usuario]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) { echo "Usuario no encontrado."; exit; }
  if ((int)$user['activo'] !== 1) { echo "El usuario está inactivo."; exit; }
  if (!password_verify($contrasena, $user['contrasena'])) { echo "Contraseña incorrecta."; exit; }

  if (function_exists('session_regenerate_id')) session_regenerate_id(true);

  $idCliente = null;
  if (isset($user['cliente_id']) && $user['cliente_id'] !== null)       $idCliente = (int)$user['cliente_id'];
  elseif (isset($user['id_cliente']) && $user['id_cliente'] !== null)   $idCliente = (int)$user['id_cliente'];

  $_SESSION['usuario'] = [
    'id_usuario'     => (int)$user['id_usuario'],
    'nombre'         => $user['nombre'],
    'usuario'        => $user['usuario'],
    'id_rol'         => (int)$user['id_rol'],
    'rol_nombre'     => $user['rol_nombre'],
    'id_cliente'     => $idCliente,
    'cliente_nombre' => $user['cliente_nombre'] ?? null
  ];

  // Calcular start_path del rol y regresarlo
  $start = acl_start_path_for_current_role();  // e.g. "/views/private/ventas/pos.php"
  echo "ok|{$start}";
  exit;

} catch (PDOException $e){
  http_response_code(500);
  echo "Error del servidor. Intenta más tarde.";
  exit;
}
