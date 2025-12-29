<?php
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/controller_guard.php';
controller_guard(__FILE__);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_unset();
session_destroy();

header("Location: " . LOGIN_URL);
exit();