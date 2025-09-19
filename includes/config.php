<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* --- Detección básica --- */
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
$host = trim($host);
$hostOnly = explode(':', $host)[0];

/* --- Si es tu dominio público, SIEMPRE usa https para BASE_URL --- */
$forceHttpsHosts = ['refaccionariarivera.com','www.refaccionariarivera.com'];
if (in_array(strtolower($hostOnly), array_map('strtolower',$forceHttpsHosts), true)) {
    $scheme = 'https';
} else {
    // detección normal cuando no es producción
    $xfp = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    $xfs = strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '');
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || ($xfp === 'https') || ($xfs === 'on')
        || (strpos($_SERVER['HTTP_CF_VISITOR'] ?? '', '"https"') !== false)
    );
    $scheme = $isHttps ? 'https' : 'http';
}

/* --- Raíz del proyecto --- */
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);
$projectRoot  = realpath(__DIR__ . '/../');
$rootFolder   = str_replace('\\', '/', str_replace($documentRoot, '', $projectRoot));
$rootFolder   = $rootFolder ?: '/';
$rootFolder   = ($rootFolder === '/') ? '' : '/' . ltrim($rootFolder, '/');

/* --- Puerto sólo si es no estándar y no viene en host --- */
$port = '';
$serverPort = (int)($_SERVER['HTTP_X_FORWARDED_PORT'] ?? $_SERVER['SERVER_PORT'] ?? 0);
if ($serverPort && !(($scheme === 'https' && $serverPort === 443) || ($scheme === 'http' && $serverPort === 80))) {
    if (strpos($host, ':') === false) $port = ':' . $serverPort;
}

/* --- BASE_URL --- */
$baseUrl = rtrim("{$scheme}://{$host}{$port}{$rootFolder}", '/');
define('BASE_URL', $baseUrl);
define('APP_URL',  BASE_URL);
define('LOGIN_URL', BASE_URL . '/views/public/index.php');
define('DASHBOARD_URL', BASE_URL . '/views/private/inicio/index.php');

/* --- Cookies más seguras --- */
if ($scheme === 'https') { @ini_set('session.cookie_secure','1'); }
@ini_set('session.cookie_httponly','1');
@ini_set('session.use_strict_mode','1');

date_default_timezone_set('America/Hermosillo');
