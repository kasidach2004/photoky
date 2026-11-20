<?php
/**
 * PhotoKY - Application Configuration & .env Loader
 * All sensitive credentials are loaded strictly from .env
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    @session_start();
}

// Base paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ORIGINALS_PATH', UPLOADS_PATH . '/originals');
define('THUMBNAILS_PATH', UPLOADS_PATH . '/thumbnails');

// Load .env file
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if ($k !== '') {
            $_ENV[$k] = $v;
            @putenv("{$k}={$v}");
        }
    }
}

function envVal(string $key, string $default = ''): string {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string)$_ENV[$key];
    }
    $val = getenv($key);
    return ($val !== false && $val !== '') ? (string)$val : $default;
}

// Environment & Admin Credentials loaded strictly from .env
define('APP_ENV', envVal('APP_ENV', 'production'));
define('ENV_ADMIN_USER', envVal('ADMIN_USERNAME'));
define('ENV_ADMIN_EMAIL', envVal('ADMIN_EMAIL'));
define('ENV_ADMIN_PASS', envVal('ADMIN_PASSWORD'));
define('ENV_ADMIN_RECOVERY', envVal('ADMIN_RECOVERY_KEY'));

// Base URL detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim(str_replace('\\', '/', $scriptDir), '/');

if (!empty($_SERVER['HTTP_HOST'])) {
    $baseUri = preg_replace('/(\/(admin|api).*)$/', '', $scriptDir);
    define('BASE_URL', $protocol . $host . $baseUri);
} else {
    define('BASE_URL', rtrim(envVal('APP_URL', 'http://localhost:8080'), '/'));
}

// Upload configuration (Strict 15 MB max per image for server performance & security)
define('MAX_UPLOAD_SIZE', 15 * 1024 * 1024); // 15 Megabytes in bytes
define('MAX_IMAGE_MEGAPIXELS', 64000000); // 64 MP cap against decompression bomb DoS
define('MAX_IMAGE_DIMENSION', 12000); // Max 12000px width or height
define('UPLOAD_RATE_LIMIT_PER_MIN', 20); // Max 20 uploads per minute per IP
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
define('ALLOWED_MIMES', [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png'  => ['png'],
    'image/webp' => ['webp'],
    'image/gif'  => ['gif']
]);

// Thumbnail configuration
define('THUMB_MAX_WIDTH', 800);
define('THUMB_MAX_HEIGHT', 800);
define('THUMB_QUALITY', 82);

// App metadata defaults
define('SITE_NAME', 'PhotoKY');
define('SITE_TAGLINE', 'คลังรูปภาพออนไลน์และการแบ่งปันคุณภาพสูง');
define('SITE_VERSION', '1.0.0');
