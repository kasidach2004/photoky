<?php
/**
 * PhotoKY - Image Deletion Endpoint
 * STRICT SECURITY: Only authenticated Administrators (isAdminLoggedIn) can delete photos.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. POST required.']);
    exit;
}

// Strict Admin-Only Check: Non-admins and photo uploaders cannot delete photos
if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'ไม่อนุญาตให้ลบรูปภาพ เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถลบรูปภาพได้'
    ]);
    exit;
}

$uuid = trim($_POST['uuid'] ?? $_GET['uuid'] ?? '');

if (empty($uuid)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสรูปภาพ (UUID)']);
    exit;
}

$db = getDB();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']);
    exit;
}

$stmt = $db->prepare("SELECT id, uuid, filename FROM photos WHERE uuid = :u LIMIT 1");
$stmt->execute([':u' => $uuid]);
$photo = $stmt->fetch();

if (!$photo) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'ไม่พบรูปภาพนี้ในระบบ']);
    exit;
}

// 1. Delete physical files
$origPath = ORIGINALS_PATH . '/' . $photo['filename'];
$thumbPath = THUMBNAILS_PATH . '/' . $photo['filename'];

if (file_exists($origPath)) {
    @unlink($origPath);
}
if (file_exists($thumbPath)) {
    @unlink($thumbPath);
}

// 2. Delete database record
$delStmt = $db->prepare("DELETE FROM photos WHERE id = :id");
$delStmt->execute([':id' => $photo['id']]);

logSystemEvent('photo_deleted', "UUID: {$uuid} deleted by Admin");

echo json_encode([
    'success' => true,
    'message' => 'ลบรูปภาพออกจากระบบอย่างถาวรเรียบร้อยแล้ว',
    'uuid'    => $uuid
]);
