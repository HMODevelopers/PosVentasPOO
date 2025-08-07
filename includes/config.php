<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detecta si estás en localhost o dominio
$host = $_SERVER['HTTP_HOST'];

// Detecta la carpeta raíz del proyecto sin importar desde dónde accedes
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);
$rootFolder   = str_replace('\\', '/', str_replace($documentRoot, '', realpath(__DIR__ . '/../')));

// Si da cadena vacía (en localhost raíz), forzamos "/"
$rootFolder = $rootFolder ?: '/';

// BASE_URL se usa para rutas relativas públicas (css, js, img)
define('BASE_URL', "http://$host$rootFolder");

// APP_URL apunta a la raíz real del proyecto
define('APP_URL', BASE_URL);

// LOGIN_URL apunta al login (archivo real del login)
define('LOGIN_URL', BASE_URL . '/views/public/index.php');

// DASHBOARD_URL apunta al panel principal
define('DASHBOARD_URL', BASE_URL . '/views/private/inicio/index.php');

// Hora por defecto
date_default_timezone_set('America/Mexico_City');
