<?php
/**
 * PhotoKY - Update Photo & Owner Information API
 * STRICT SECURITY: Only authenticated Administrators (isAdminLoggedIn) can edit photo or owner details.
 * Photo owners/uploaders cannot edit anything after upload (they can only post comments).
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST method required.']);
    exit;
}

// Strict Admin-Only Check: Non-admins and photo owners cannot edit photo details after upload
if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'ไม่อนุญาตให้แก้ไขข้อมูล เมื่ออัปโหลดแล้วเฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถแก้ไขข้อมูลได้'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$uuid = trim((string)($input['uuid'] ?? ''));
$photoId = isset($input['id']) ? (int)$input['id'] : 0;

if (empty($uuid) && $photoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ไม่พบรหัสรูปภาพ (UUID หรือ ID)']);
    exit;
}

$db = getDB();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']);
    exit;
}

if (!empty($uuid)) {
    $stmt = $db->prepare("SELECT * FROM photos WHERE uuid = :u LIMIT 1");
    $stmt->execute([':u' => $uuid]);
} else {
    $stmt = $db->prepare("SELECT * FROM photos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $photoId]);
}
$photo = $stmt->fetch();

if (!$photo) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'ไม่พบรูปภาพนี้ในระบบ']);
    exit;
}

// Clear owner metadata action (Admin only)
if (($input['action'] ?? '') === 'clear_owner_info') {
    try {
        $db->prepare("UPDATE photos SET owner_name = NULL, owner_phone = NULL, extra_info = NULL WHERE id = :id")
           ->execute([':id' => $photo['id']]);
        logSystemEvent('photo_owner_cleared', "Photo ID: {$photo['id']} cleared by Admin");
        echo json_encode(['success' => true, 'message' => 'ล้างข้อมูลเจ้าของภาพเรียบร้อยแล้ว']);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถล้างข้อมูลเจ้าของภาพได้']);
        exit;
    }
}

$title = array_key_exists('title', $input) ? mb_substr(trim((string)$input['title']), 0, 255) : $photo['title'];
$description = array_key_exists('description', $input) ? mb_substr(trim((string)$input['description']), 0, 5000) : $photo['description'];

$ownerName = array_key_exists('owner_name', $input)
    ? (mb_substr(trim((string)$input['owner_name']), 0, 150) ?: null)
    : $photo['owner_name'];

$ownerPhone = array_key_exists('owner_phone', $input)
    ? (mb_substr(trim((string)$input['owner_phone']), 0, 50) ?: null)
    : $photo['owner_phone'];

$extraInfo = array_key_exists('extra_info', $input)
    ? (mb_substr(trim((string)$input['extra_info']), 0, 5000) ?: null)
    : $photo['extra_info'];

$isPublic = array_key_exists('is_public', $input) ? ((int)$input['is_public'] ? 1 : 0) : (int)$photo['is_public'];

try {
    $upStmt = $db->prepare("
        UPDATE photos SET
            title = :title,
            description = :description,
            owner_name = :owner_name,
            owner_phone = :owner_phone,
            extra_info = :extra_info,
            is_public = :is_public
        WHERE id = :id
    ");
    $upStmt->execute([
        ':title'       => $title,
        ':description' => $description,
        ':owner_name'  => $ownerName,
        ':owner_phone' => $ownerPhone,
        ':extra_info'  => $extraInfo,
        ':is_public'   => $isPublic,
        ':id'          => $photo['id']
    ]);

    logSystemEvent('photo_updated', "Photo ID: {$photo['id']} updated by Admin");

    echo json_encode([
        'success' => true,
        'message' => 'บันทึกการแก้ไขข้อมูลรูปภาพเรียบร้อยแล้ว',
        'photo'   => [
            'id'          => (int)$photo['id'],
            'uuid'        => $photo['uuid'],
            'title'       => $title,
            'description' => $description,
            'owner_name'  => $ownerName,
            'owner_phone' => $ownerPhone,
            'extra_info'  => $extraInfo,
            'is_public'   => $isPublic
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'
    ]);
}
