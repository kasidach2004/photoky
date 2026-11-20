<?php
/**
 * PhotoKY - Admin Photo Approval API
 * Approve, reject, or mark photos as pending review
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin access required.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired security token.']);
    exit;
}

$db = getDB();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit;
}

$action = strtolower(trim($_POST['action'] ?? 'approve'));
$allowedActions = [
    'approve' => 1,
    'reject'  => 2,
    'pending' => 0
];

if (!array_key_exists($action, $allowedActions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action. Must be approve, reject, or pending.']);
    exit;
}

$targetStatus = $allowedActions[$action];

// Collect IDs to update
$idsToUpdate = [];
if (!empty($_POST['photo_id'])) {
    $idsToUpdate[] = (int)$_POST['photo_id'];
} elseif (!empty($_POST['uuid'])) {
    $s = $db->prepare("SELECT id FROM photos WHERE uuid = :u LIMIT 1");
    $s->execute([':u' => trim($_POST['uuid'])]);
    $foundId = (int)$s->fetchColumn();
    if ($foundId > 0) {
        $idsToUpdate[] = $foundId;
    }
} elseif (!empty($_POST['photo_ids']) && is_array($_POST['photo_ids'])) {
    foreach ($_POST['photo_ids'] as $pid) {
        $cleanId = (int)$pid;
        if ($cleanId > 0) {
            $idsToUpdate[] = $cleanId;
        }
    }
}

if (empty($idsToUpdate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid photo IDs provided.']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($idsToUpdate), '?'));
    $params = array_merge([$targetStatus], $idsToUpdate);

    $stmt = $db->prepare("UPDATE photos SET is_approved = ? WHERE id IN ({$placeholders})");
    $stmt->execute($params);
    $affected = $stmt->rowCount();

    $actionLabels = [
        1 => 'อนุมัติภาพเรียบร้อย (Approved)',
        2 => 'ปฏิเสธภาพเรียบร้อย (Rejected)',
        0 => 'เปลี่ยนสถานะเป็นรอการตรวจสอบ (Pending)'
    ];

    logSystemEvent('admin_photo_approval', "Action: {$action}, TargetStatus: {$targetStatus}, Count: {$affected}");

    echo json_encode([
        'success'       => true,
        'action'        => $action,
        'status'        => $targetStatus,
        'updated_count' => $affected,
        'message'       => $actionLabels[$targetStatus] ?? 'Status updated successfully.'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error: ' . (APP_ENV === 'development' ? $e->getMessage() : 'Internal error')
    ]);
}
