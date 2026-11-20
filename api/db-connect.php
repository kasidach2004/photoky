<?php
/**
 * PhotoKY - Database Test & Auto-Connect API
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $connected = isDbConnected();
    $config = Database::getActiveConfig();
    echo json_encode([
        'connected' => $connected,
        'config'    => $config,
        'error'     => $connected ? null : Database::getLastError()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $res = Database::testAndSaveConfig($input);
    if (!$res['success']) {
        http_response_code(400);
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
