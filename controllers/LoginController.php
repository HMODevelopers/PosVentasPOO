<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: text/plain'); // Para evitar malformaciones por HTML o headers inesperados

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método no permitido
    echo "Método no permitido.";
    exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$contrasena = trim($_POST['contrasena'] ?? '');

if ($usuario === '' || $contrasena === '') {
    echo "Por favor completa todos los campos.";
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.nombre AS nombre_rol
        FROM usuarios u
        LEFT JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.usuario = ?
        LIMIT 1
    ");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "Usuario no encontrado.";
        exit;
    }

    if ((int)$user['activo'] !== 1) {
        echo "El usuario está inactivo.";
        exit;
    }

    if (!password_verify($contrasena, $user['contrasena'])) {
        echo "Contraseña incorrecta.";
        exit;
    }

    $_SESSION['usuario'] = [
        'id' => $user['id_usuario'],
        'nombre' => $user['nombre'],
        'usuario' => $user['usuario'],
        'id_rol' => $user['id_rol'],
        'rol' => $user['nombre_rol']
    ];

    echo "ok";
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo "Error del servidor. Intenta más tarde.";
    // log_error($e); // En producción, loguea el error en vez de mostrarlo
    exit;
}
