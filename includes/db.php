<?php
// Configuración de conexión (ajústala con tus datos reales)
define('DB_HOST', 'localhost');
define('DB_NAME', 'punto_venta_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Opciones de PDO seguras y eficientes
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Modo excepción
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Arreglo asociativo
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Previene SQL injection
];

try {
    $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // Opcional: mostrar errores solo en desarrollo
    if (ini_get('display_errors')) {
        die("Error de conexión: " . $e->getMessage());
    } else {
        // En producción, registra el error y muestra mensaje genérico
        error_log($e->getMessage());
        die("Ocurrió un problema al conectar con la base de datos.");
    }
}
