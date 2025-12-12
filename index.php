<?php
session_start();

// Incluye tu archivo de configuración con BASE_URL
require_once __DIR__ . '/includes/config.php'; // ajusta la ruta si está en otro lado

if (isset($_SESSION['usuario'])) {

    header('Location: ' . BASE_URL . '/views/private/inicio/index.php');

} else {

    header('Location: ' . BASE_URL . '/views/public/index.php');
    
}
exit();
?>