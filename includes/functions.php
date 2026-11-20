<?php
/**
 * PhotoKY - Core Helper Functions
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Safe UTF-8 polyfills if mbstring extension is not enabled in local PHP
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $str, ?string $encoding = null): int {
        $chars = @preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($chars) ? count($chars) : strlen($str);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $str, int $start, ?int $length = null, ?string $encoding = null): string {
        $chars = @preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) {
            return $length === null ? substr($str, $start) : substr($str, $start, $length);
        }
        return implode('', array_slice($chars, $start, $length));
    }
}

if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth(string $str, int $start, int $width, string $trimMarker = '', ?string $encoding = null): string {
        $sliced = mb_substr($str, $start);
        if (mb_strlen($sliced) <= $width) {
            return $sliced;
        }
        $markerLen = mb_strlen($trimMarker);
        $cut = max(0, $width - $markerLen);
        return mb_substr($sliced, 0, $cut) . $trimMarker;
    }
}

/**
 * HTML Escaping for XSS prevention
 */
function e(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate cryptographically secure UUID v4
 */
function generateUuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Generate secure random token for delete and manage permissions
 */
function generateSecurityToken(int $bytes = 24): string {
    return bin2hex(random_bytes($bytes));
}

/**
 * Format bytes into human-readable representation
 */
function formatBytes(int $bytes, int $precision = 1): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Format timestamp into human-readable relative time (Thai)
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'เมื่อสักครู่';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' นาทีที่แล้ว';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ชั่วโมงที่แล้ว';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' วันที่แล้ว';
    } else {
        return date('d/m/Y', $time);
    }
}

/**
 * Get Client IP Address securely
 */
function getClientIp(): string {
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ipList = explode(',', $_SERVER[$key]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Validate Uploaded Image strictly using Fileinfo and getimagesize
 */
function validateImageUpload(array $file): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['valid' => false, 'error' => 'Invalid file upload parameter.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['valid' => false, 'error' => 'ไฟล์มีขนาดใหญ่เกินกำหนด (อนุญาตสูงสุดไม่เกิน 15 MB)'];
        case UPLOAD_ERR_PARTIAL:
            return ['valid' => false, 'error' => 'อัปโหลดไฟล์ไม่สมบูรณ์ กรุณาลองใหม่อีกครั้ง'];
        case UPLOAD_ERR_NO_FILE:
            return ['valid' => false, 'error' => 'ไม่พบไฟล์ที่อัปโหลด'];
        default:
            return ['valid' => false, 'error' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์'];
    }

    // Verify file size limit (15 MB)
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['valid' => false, 'error' => 'ขนาดไฟล์เกินขีดจำกัดสูงสุด 15 MB (' . formatBytes((int)$file['size']) . ')'];
    }

    if ($file['size'] <= 0) {
        return ['valid' => false, 'error' => 'ไฟล์ที่อัปโหลดว่างเปล่า (0 Bytes)'];
    }

    $tmpPath = $file['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
        return ['valid' => false, 'error' => 'ตรวจพบความพยายามอัปโหลดไฟล์ที่ผิดปกติ'];
    }

    // MIME verification using Fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    if (!array_key_exists($realMime, ALLOWED_MIMES)) {
        return ['valid' => false, 'error' => "ไม่รองรับไฟล์ประเภท ({$realMime}) รองรับเฉพาะ JPG, PNG, WEBP, GIF เท่านั้น"];
    }

    // Check extension whitelist
    $origName = $file['name'];
    $pathParts = pathinfo($origName);
    $ext = strtolower($pathParts['extension'] ?? '');

    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return ['valid' => false, 'error' => "นามสกุลไฟล์ '.{$ext}' ไม่ได้รับอนุญาต"];
    }

    // Make sure extension matches mime
    if (!in_array($ext, ALLOWED_MIMES[$realMime], true)) {
        if (!($realMime === 'image/jpeg' && in_array($ext, ['jpg', 'jpeg'], true))) {
            return ['valid' => false, 'error' => 'นามสกุลไฟล์ไม่ตรงกับข้อมูลรูปภาพจริง (ป้องกัน MIME Spoofing)'];
        }
    }

    // Verify it is a true readable image using getimagesize
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        return ['valid' => false, 'error' => 'ไฟล์นี้ไม่ใช่รูปภาพที่ถูกต้องหรือไฟล์เสียหาย'];
    }

    $width = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];
    $maxDim = defined('MAX_IMAGE_DIMENSION') ? MAX_IMAGE_DIMENSION : 12000;
    $maxMp = defined('MAX_IMAGE_MEGAPIXELS') ? MAX_IMAGE_MEGAPIXELS : 64000000;

    // Decompression Bomb / Pixel Flood DoS Protection
    if ($width > $maxDim || $height > $maxDim || ($width * $height) > $maxMp) {
        return ['valid' => false, 'error' => "ความละเอียดภาพสูงเกินขีดจำกัดความปลอดภัยของเซิร์ฟเวอร์ ({$width}x{$height} px)"];
    }

    // Polyglot / Embedded Script Payload Inspection (Scan first & last 64KB chunks)
    $fp = @fopen($tmpPath, 'rb');
    if ($fp) {
        $headChunk = (string)@fread($fp, 65536);
        $tailChunk = '';
        if ($file['size'] > 65536) {
            @fseek($fp, max(0, $file['size'] - 65536));
            $tailChunk = (string)@fread($fp, 65536);
        }
        @fclose($fp);
        $sample = strtolower($headChunk . ' ' . $tailChunk);
        $forbiddenSignatures = ['<?php', '<?=', '<script', 'php://', 'eval(', 'base64_decode(', 'shell_exec(', 'passthru(', 'system('];
        foreach ($forbiddenSignatures as $sig) {
            if (strpos($sample, $sig) !== false) {
                return ['valid' => false, 'error' => 'ตรวจพบโค้ดสคริปต์แปลกปลอมฝังอยู่ในไฟล์ภาพ (Security Threat Blocked)'];
            }
        }
    }

    return [
        'valid'     => true,
        'mime'      => $realMime,
        'ext'       => $ext === 'jpeg' ? 'jpg' : $ext,
        'width'     => $width,
        'height'    => $height,
        'orig_name' => basename($origName)
    ];
}

/**
 * Generate high quality thumbnail using GD
 */
function generateThumbnail(string $sourcePath, string $targetPath, string $mimeType, int $maxW = THUMB_MAX_WIDTH, int $maxH = THUMB_MAX_HEIGHT): bool {
    if (!extension_loaded('gd')) {
        // Fallback: copy source if GD is missing
        return @copy($sourcePath, $targetPath);
    }

    $image = null;
    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null;
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($sourcePath);
            break;
    }

    if (!$image) {
        return @copy($sourcePath, $targetPath);
    }

    $origW = imagesx($image);
    $origH = imagesy($image);

    if ($origW <= 0 || $origH <= 0) {
        imagedestroy($image);
        return false;
    }

    // Calculate aspect ratio
    $ratio = min($maxW / $origW, $maxH / $origH);
    if ($ratio >= 1.0) {
        // Source is smaller than max thumbnail dimensions
        $newW = $origW;
        $newH = $origH;
    } else {
        $newW = (int)round($origW * $ratio);
        $newH = (int)round($origH * $ratio);
    }

    $thumb = imagecreatetruecolor($newW, $newH);

    // Maintain transparency for PNG, WebP, GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $newW, $newH, $transparent);
    } elseif ($mimeType === 'image/gif') {
        $transIndex = imagecolortransparent($image);
        if ($transIndex >= 0) {
            $transColor = imagecolorsforindex($image, $transIndex);
            $transIndexNew = imagecolorallocate($thumb, $transColor['red'], $transColor['green'], $transColor['blue']);
            imagefill($thumb, 0, 0, $transIndexNew);
            imagecolortransparent($thumb, $transIndexNew);
        }
    }

    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    // Save thumbnail
    $saved = false;
    if (function_exists('imagewebp')) {
        $saved = imagewebp($thumb, $targetPath, THUMB_QUALITY);
    } elseif ($mimeType === 'image/png') {
        $saved = imagepng($thumb, $targetPath, 8);
    } else {
        $saved = imagejpeg($thumb, $targetPath, THUMB_QUALITY);
    }

    imagedestroy($image);
    imagedestroy($thumb);

    return $saved;
}

/**
 * Extract EXIF metadata safely
 */
function extractExifData(string $filePath): ?array {
    if (!function_exists('exif_read_data')) {
        return null;
    }

    try {
        $exif = @exif_read_data($filePath, 'IFD0,EXIF', true);
        if (!$exif) return null;

        $result = [];
        if (!empty($exif['IFD0']['Make'])) $result['camera_make'] = trim($exif['IFD0']['Make']);
        if (!empty($exif['IFD0']['Model'])) $result['camera_model'] = trim($exif['IFD0']['Model']);
        if (!empty($exif['EXIF']['LensModel'])) $result['lens'] = trim($exif['EXIF']['LensModel']);
        if (!empty($exif['EXIF']['ExposureTime'])) $result['exposure_time'] = $exif['EXIF']['ExposureTime'] . 's';
        if (!empty($exif['EXIF']['FNumber'])) {
            $parts = explode('/', $exif['EXIF']['FNumber']);
            $f = count($parts) === 2 && $parts[1] > 0 ? round($parts[0] / $parts[1], 1) : $exif['EXIF']['FNumber'];
            $result['f_number'] = 'f/' . $f;
        }
        if (!empty($exif['EXIF']['ISOSpeedRatings'])) {
            $iso = is_array($exif['EXIF']['ISOSpeedRatings']) ? $exif['EXIF']['ISOSpeedRatings'][0] : $exif['EXIF']['ISOSpeedRatings'];
            $result['iso'] = 'ISO ' . $iso;
        }
        if (!empty($exif['EXIF']['FocalLength'])) {
            $parts = explode('/', $exif['EXIF']['FocalLength']);
            $focal = count($parts) === 2 && $parts[1] > 0 ? round($parts[0] / $parts[1]) : $exif['EXIF']['FocalLength'];
            $result['focal_length'] = $focal . 'mm';
        }
        if (!empty($exif['EXIF']['DateTimeOriginal'])) {
            $result['date_taken'] = $exif['EXIF']['DateTimeOriginal'];
        }

        return !empty($result) ? $result : null;
    } catch (\Throwable $t) {
        return null;
    }
}

/**
 * Get system setting with fallback
 */
function getSetting(string $key, ?string $default = null): ?string {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1");
        $stmt->execute([':k' => $key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * Log System Event
 */
function logSystemEvent(string $event, string $details = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO system_logs (event, details, ip_address) VALUES (:ev, :det, :ip)");
        $stmt->execute([
            ':ev' => $event,
            ':det' => $details,
            ':ip' => getClientIp()
        ]);
    } catch (\Throwable $e) {
        // Silently ignore logging errors
    }
}

/**
 * Generate URL Slug from text
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'folder-' . substr(bin2hex(random_bytes(4)), 0, 6) : $text;
}

/**
 * CSRF Protection Helpers
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
