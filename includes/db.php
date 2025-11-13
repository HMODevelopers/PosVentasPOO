<?php
// Detectar si estamos en local o en el servidor
$isLocal = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);

// Configuración dinámica según el entorno
if ($isLocal) {
    // Configuración local
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'refacc26_ventas_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Configuración para servidor (CPanel)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'refacc26_ventas_db');
    define('DB_USER', 'refacc26_root');
    define('DB_PASS', 'REFACCIONARIA123456789');
}

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
?>
