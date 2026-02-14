<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../includes/controller_guard.php';
controller_guard(__FILE__, 'sistema.menu');
require_once __DIR__ . '/../../../models/ConfigEmisoresModel.php';

$model = new ConfigEmisoresModel();

function json_ok($data = null): void {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $message, string $errorId): void {
    echo json_encode(['ok' => false, 'message' => $message, 'errorId' => $errorId], JSON_UNESCAPED_UNICODE);
    exit;
}

function payload(): array {
    $raw = json_decode(file_get_contents('php://input'), true);
    return is_array($raw) ? $raw : $_POST;
}
