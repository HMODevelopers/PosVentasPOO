<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/controller_guard.php';
controller_guard(__FILE__, 'sistema.menu');
require_once __DIR__ . '/../../../models/ConfigEmisoresModel.php';

$model = new ConfigEmisoresModel();

function json_ok($data = null): void {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $message, string $errorId, array $extra = []): void {
    echo json_encode(array_merge(['ok' => false, 'message' => $message, 'errorId' => $errorId], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function is_dev_debug_enabled(): bool {
    $env = strtolower((string)($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?? ''));
    if (in_array($env, ['dev', 'local', 'development', 'test'], true)) {
        return true;
    }
    return in_array(strtolower((string)ini_get('display_errors')), ['1', 'on'], true);
}

function payload(): array {
    $raw = json_decode(file_get_contents('php://input'), true);
    return is_array($raw) ? $raw : $_POST;
}
