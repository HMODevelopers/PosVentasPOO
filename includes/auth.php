<?php
// includes/auth.php

// ==============================
// Configuración de sesión (10 h)
// ==============================
$SESSION_LIFETIME = 10 * 60 * 60; // 10 horas

// Estas directivas deben ir ANTES de session_start()
ini_set('session.gc_maxlifetime', (string)$SESSION_LIFETIME);
session_set_cookie_params($SESSION_LIFETIME);

session_start();

require_once __DIR__ . '/config.php';

// ==============================
// Verificar que haya usuario
// ==============================
if (!isset($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/views/public/index.php');
    exit;
}

// ==============================
// Verificar tiempo absoluto (10 h)
// ==============================
if (!isset($_SESSION['LOGIN_TIME'])) {
    // Si es la primera vez que pasa por aquí (sesión vieja),
    // tomamos este momento como inicio.
    $_SESSION['LOGIN_TIME'] = time();
}

$elapsed = time() - (int)$_SESSION['LOGIN_TIME'];

if ($elapsed > $SESSION_LIFETIME) {
    // Sesión vencida
    session_unset();
    session_destroy();

    header('Location: ' . BASE_URL . '/views/public/index.php?expired=1');
    exit;
}

// Actualizar última actividad (por si luego quieres manejar inactividad)
$_SESSION['LAST_ACTIVITY'] = time();
