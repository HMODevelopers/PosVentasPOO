<?php
// Detectar entorno según el hostname
$isLocal = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);

// Configuración según entorno
if ($isLocal) {
    // 🖥️ Entorno local (XAMPP, Laragon, etc.)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'punto_venta_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // 🌐 Producción (cPanel)
    define('DB_HOST', 'localhost');        // o el host MySQL del cPanel, a veces es distinto
    define('DB_NAME', 'USUARIOCPANEL_punto_venta_db');
    define('DB_USER', 'USUARIOCPANEL_root');
    define('DB_PASS', 'TU_PASSWORD_AQUI');
}

// Charset
define('DB_CHARSET', 'utf8mb4');

// Opciones de PDO seguras
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    if (ini_get('display_errors')) {
        die("Error de conexión: " . $e->getMessage());
    } else {
        error_log($e->getMessage());
        die("Ocurrió un problema al conectar con la base de datos.");
    }
}
