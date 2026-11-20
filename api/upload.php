<?php
/**
 * PhotoKY - Image Upload API Endpoint
 * Handles Drag & Drop, Multi-file Upload, UUID Renaming, File Path & Owner Info Persistence
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

if (!is_dir(ORIGINALS_PATH)) {
    @mkdir(ORIGINALS_PATH, 0775, true);
}
if (!is_dir(THUMBNAILS_PATH)) {
    @mkdir(THUMBNAILS_PATH, 0775, true);
}

$uploadedFiles = [];
if (isset($_FILES['photos'])) {
    if (is_array($_FILES['photos']['name'])) {
        $count = count($_FILES['photos']['name']);
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_FILES['photos']['name'][$i])) {
                $uploadedFiles[] = [
                    'name'     => $_FILES['photos']['name'][$i],
                    'type'     => $_FILES['photos']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error'    => $_FILES['photos']['error'][$i],
                    'size'     => $_FILES['photos']['size'][$i]
                ];
            }
        }
    } else {
        $uploadedFiles[] = $_FILES['photos'];
    }
} elseif (isset($_FILES['photo'])) {
    $uploadedFiles[] = $_FILES['photo'];
} elseif (isset($_FILES['file'])) {
    $uploadedFiles[] = $_FILES['file'];
}

if (empty($uploadedFiles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์รูปภาพ กรุณาเลือกรูปภาพก่อนกดส่ง']);
    exit;
}

if (count($uploadedFiles) > 15) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'อนุญาตให้อัปโหลดพร้อมกันสูงสุดไม่เกิน 15 รูปต่อครั้ง']);
    exit;
}

$folderId = null;
$db = getDB();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้']);
    exit;
}

$isPublic = isset($_POST['is_public']) ? ((int)$_POST['is_public'] ? 1 : 0) : 1;
$isApproved = isAdminLoggedIn() ? 1 : 0;
$ownerName = mb_substr(trim($_POST['owner_name'] ?? ''), 0, 150) ?: null;
$ownerPhone = mb_substr(trim($_POST['owner_phone'] ?? ''), 0, 50) ?: null;
$extraInfo = mb_substr(trim($_POST['extra_info'] ?? ''), 0, 5000) ?: null;
$clientIp = getClientIp();
$userAgent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// Server Protection: Per-IP Upload Rate Limiting (for non-admins)
if (!isAdminLoggedIn()) {
    try {
        $rateLimitMax = defined('UPLOAD_RATE_LIMIT_PER_MIN') ? UPLOAD_RATE_LIMIT_PER_MIN : 20;
        $rlStmt = $db->prepare("SELECT COUNT(*) FROM photos WHERE ip_address = :ip AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $rlStmt->execute([':ip' => $clientIp]);
        $recentCount = (int)$rlStmt->fetchColumn();
        if ($recentCount + count($uploadedFiles) > $rateLimitMax) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error'   => 'คุณอัปโหลดรูปภาพถี่เกินไป กรุณารอสักครู่แล้วลองใหม่อีกครั้ง (Rate Limit Protection)'
            ]);
            exit;
        }
    } catch (\Throwable $rlEx) {
        // Fallback if SQLite syntax differs
    }
}

$results = [];
$errors = [];

foreach ($uploadedFiles as $file) {
    $val = validateImageUpload($file);
    if (!$val['valid']) {
        $errors[] = [
            'file'  => $file['name'] ?? 'unknown',
            'error' => $val['error']
        ];
        continue;
    }

    $ext = $val['ext'];
    $uuid = generateUuid();
    $storedFilename = $uuid . '.' . $ext;
    $relativeFilePath = 'uploads/originals/' . $storedFilename;
    $deleteToken = generateSecurityToken(24);

    $originalDest = ORIGINALS_PATH . '/' . $storedFilename;
    $thumbDest = THUMBNAILS_PATH . '/' . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $originalDest)) {
        $errors[] = [
            'file'  => $file['name'],
            'error' => 'Failed to save uploaded file to storage.'
        ];
        continue;
    }

    generateThumbnail($originalDest, $thumbDest, $val['mime'], THUMB_MAX_WIDTH, THUMB_MAX_HEIGHT);

    $exif = extractExifData($originalDest);
    $exifJson = $exif ? json_encode($exif, JSON_UNESCAPED_UNICODE) : null;

    try {
        $insertStmt = $db->prepare("
            INSERT INTO photos (
                uuid, filename, file_path, original_name, file_size, mime_type, file_ext,
                width, height, delete_token, title, description,
                owner_name, owner_phone, extra_info,
                is_public, is_approved, ip_address, user_agent, exif_data
            ) VALUES (
                :uuid, :filename, :file_path, :original_name, :file_size, :mime_type, :file_ext,
                :width, :height, :delete_token, :title, :description,
                :owner_name, :owner_phone, :extra_info,
                :is_public, :is_approved, :ip_address, :user_agent, :exif_data
            )
        ");

        $photoTitle = trim($_POST['title'] ?? '');
        if (empty($photoTitle)) {
            $photoTitle = pathinfo($val['orig_name'], PATHINFO_FILENAME);
        }

        $insertStmt->execute([
            ':uuid'          => $uuid,
            ':filename'      => $storedFilename,
            ':file_path'     => $relativeFilePath,
            ':original_name' => $val['orig_name'],
            ':file_size'     => (int)$file['size'],
            ':mime_type'     => $val['mime'],
            ':file_ext'      => $ext,
            ':width'         => (int)$val['width'],
            ':height'        => (int)$val['height'],
            ':delete_token'  => $deleteToken,
            ':title'         => mb_substr($photoTitle, 0, 255),
            ':description'   => trim($_POST['description'] ?? ''),
            ':owner_name'    => $ownerName,
            ':owner_phone'   => $ownerPhone,
            ':extra_info'    => $extraInfo,
            ':is_public'     => $isPublic,
            ':is_approved'   => $isApproved,
            ':ip_address'    => $clientIp,
            ':user_agent'    => $userAgent,
            ':exif_data'     => $exifJson
        ]);

        $photoId = (int)$db->lastInsertId();

        $directUrl = BASE_URL . '/' . $relativeFilePath;
        $thumbUrl = BASE_URL . '/uploads/thumbnails/' . $storedFilename;
        $viewUrl = BASE_URL . '/photo.php?uuid=' . $uuid;

        $results[] = [
            'id'              => $photoId,
            'uuid'            => $uuid,
            'title'           => mb_substr($photoTitle, 0, 255),
            'filename'        => $storedFilename,
            'file_path'       => $relativeFilePath,
            'original_name'   => $val['orig_name'],
            'file_size'       => (int)$file['size'],
            'formatted_size'  => formatBytes((int)$file['size']),
            'mime_type'       => $val['mime'],
            'width'           => (int)$val['width'],
            'height'          => (int)$val['height'],
            'owner_name'      => $ownerName,
            'owner_phone'     => $ownerPhone,
            'extra_info'      => $extraInfo,
            'is_approved'     => $isApproved,
            'view_url'        => $viewUrl,
            'public_view_url' => $viewUrl,
            'direct_url'      => $directUrl,
            'thumb_url'       => $thumbUrl,
            'created_at'      => date('Y-m-d H:i:s')
        ];

        logSystemEvent('photo_uploaded', "ID: {$photoId}, UUID: {$uuid}, Approved: " . ($isApproved ? 'yes' : 'pending') . ", Size: " . formatBytes((int)$file['size']));

    } catch (PDOException $e) {
        @unlink($originalDest);
        @unlink($thumbDest);
        $errors[] = [
            'file'  => $file['name'],
            'error' => 'Database error during upload: ' . (APP_ENV === 'development' ? $e->getMessage() : 'Internal error')
        ];
    }
}

$success = count($results) > 0;
http_response_code($success ? 200 : 400);

$statusMsg = 'Upload failed.';
if ($success) {
    if ($isApproved) {
        $statusMsg = count($results) . ' image(s) uploaded and approved successfully.';
    } else {
        $statusMsg = count($results) . ' image(s) uploaded successfully! Pending administrator safety review before public display.';
    }
}

echo json_encode([
    'success'     => $success,
    'count'       => count($results),
    'is_approved' => $isApproved,
    'photos'      => $results,
    'errors'      => $errors,
    'message'     => $statusMsg
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
