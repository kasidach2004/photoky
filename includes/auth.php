<?php
/**
 * PhotoKY - Authentication & Password Security Manager
 * - Default Web Admin: user: admin | password: photoky123 (from .env)
 * - Crash-proof (never throws HTTP 500 on login)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_username']);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        $loginUrl = BASE_URL . '/admin/login.php';
        header("Location: {$loginUrl}");
        exit;
    }
}

function getAdminUser(): ?array {
    if (!isAdminLoggedIn()) return null;
    return [
        'id'       => (int)$_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'email'    => $_SESSION['admin_email'] ?? ENV_ADMIN_EMAIL,
        'role'     => $_SESSION['admin_role'] ?? 'superadmin'
    ];
}

function adminLogin(string $username, string $password): array {
    $inputUser = trim($username);
    $cleanPass = trim($password);
    $db = getDB();

    // Direct verification against .env configured admin credentials
    $isEnvAdminMatch = (
        ENV_ADMIN_USER !== '' && ENV_ADMIN_PASS !== ''
        && (strcasecmp($inputUser, ENV_ADMIN_USER) === 0 || strcasecmp($inputUser, ENV_ADMIN_EMAIL) === 0)
        && (hash_equals(ENV_ADMIN_PASS, $password) || hash_equals(ENV_ADMIN_PASS, $cleanPass))
    );

    if ($db) {
        try {
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = :u1 OR email = :u2 LIMIT 1");
            $stmt->execute([':u1' => $inputUser, ':u2' => $inputUser]);
            $admin = $stmt->fetch();

            if ($isEnvAdminMatch) {
                $newHash = password_hash(ENV_ADMIN_PASS, PASSWORD_BCRYPT);
                $recHash = password_hash(ENV_ADMIN_RECOVERY, PASSWORD_BCRYPT);
                if (!$admin) {
                    $ins = $db->prepare("INSERT INTO admins (username, email, password_hash, recovery_code_hash, role) VALUES (:u, :e, :p, :r, 'superadmin')");
                    $ins->execute([':u' => ENV_ADMIN_USER, ':e' => ENV_ADMIN_EMAIL, ':p' => $newHash, ':r' => $recHash]);
                    $adminId = (int)$db->lastInsertId() ?: 1;
                    $admin = [
                        'id'       => $adminId,
                        'username' => ENV_ADMIN_USER,
                        'email'    => ENV_ADMIN_EMAIL,
                        'role'     => 'superadmin'
                    ];
                } else {
                    if (!password_verify(ENV_ADMIN_PASS, $admin['password_hash'])) {
                        $db->prepare("UPDATE admins SET password_hash = :h WHERE id = :id")
                           ->execute([':h' => $newHash, ':id' => $admin['id']]);
                    }
                }
            } elseif (!$admin || (!password_verify($password, $admin['password_hash']) && !password_verify($cleanPass, $admin['password_hash']))) {
                logSystemEvent('admin_login_failed', "Failed login attempt for: {$inputUser}");
                return ['success' => false, 'error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
            }

            @session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)($admin['id'] ?? 1);
            $_SESSION['admin_username'] = $admin['username'] ?? ENV_ADMIN_USER;
            $_SESSION['admin_email'] = $admin['email'] ?? ENV_ADMIN_EMAIL;
            $_SESSION['admin_role'] = $admin['role'] ?? 'superadmin';

            try {
                $nowStr = date('Y-m-d H:i:s');
                $db->prepare("UPDATE admins SET last_login = :dt WHERE id = :id")
                   ->execute([':dt' => $nowStr, ':id' => $_SESSION['admin_id']]);
            } catch (\Throwable $e) {}

            logSystemEvent('admin_login_success', "Admin {$_SESSION['admin_username']} logged in");
            return ['success' => true];
        } catch (\Throwable $e) {
            if ($isEnvAdminMatch) {
                @session_regenerate_id(true);
                $_SESSION['admin_id'] = 1;
                $_SESSION['admin_username'] = ENV_ADMIN_USER;
                $_SESSION['admin_email'] = ENV_ADMIN_EMAIL;
                $_SESSION['admin_role'] = 'superadmin';
                return ['success' => true];
            }
            return ['success' => false, 'error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }
    }

    if ($isEnvAdminMatch) {
        @session_regenerate_id(true);
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_username'] = ENV_ADMIN_USER;
        $_SESSION['admin_email'] = ENV_ADMIN_EMAIL;
        $_SESSION['admin_role'] = 'superadmin';
        return ['success' => true];
    }

    return [
        'success' => false,
        'error'   => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
    ];
}

function adminLogout(): void {
    if (isAdminLoggedIn()) {
        logSystemEvent('admin_logout', "Admin {$_SESSION['admin_username']} logged out");
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    @session_destroy();
}

function adminChangePassword(int $adminId, string $currentPassword, string $newPassword): array {
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร'];
    }

    $db = getDB();
    if (!$db) {
        return ['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้'];
    }

    $stmt = $db->prepare("SELECT id, username, password_hash FROM admins WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $adminId]);
    $admin = $stmt->fetch();

    $currentValid = ($admin && password_verify($currentPassword, $admin['password_hash'])) || hash_equals(ENV_ADMIN_PASS, $currentPassword);
    if (!$admin || !$currentValid) {
        return ['success' => false, 'error' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'];
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $db->prepare("UPDATE admins SET password_hash = :h WHERE id = :id")
       ->execute([':h' => $newHash, ':id' => $adminId]);

    logSystemEvent('admin_password_changed', "Admin {$admin['username']} changed password");

    return ['success' => true, 'message' => 'เปลี่ยนรหัสผ่านผู้ดูแลระบบเรียบร้อยแล้ว'];
}

function adminInitiatePasswordReset(string $identity, string $recoveryKey): array {
    $db = getDB();
    if (!$db) {
        return ['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้'];
    }
    $clientIp = getClientIp();
    $cleanId = trim($identity);

    $stmt = $db->prepare("SELECT id, username, email, recovery_code_hash FROM admins WHERE username = :u1 OR email = :u2 LIMIT 1");
    $stmt->execute([':u1' => $cleanId, ':u2' => $cleanId]);
    $admin = $stmt->fetch();

    $recValid = ($admin && !empty($admin['recovery_code_hash']) && password_verify(trim($recoveryKey), $admin['recovery_code_hash']))
        || hash_equals(ENV_ADMIN_RECOVERY, trim($recoveryKey));

    if (!$admin || !$recValid) {
        logSystemEvent('admin_reset_failed', "Failed password reset verification for: {$cleanId}");
        return ['success' => false, 'error' => 'ชื่อผู้ใช้หรือรหัสกู้คืนความปลอดภัย (Recovery Key) ไม่ถูกต้อง'];
    }

    $nowStr = date('Y-m-d H:i:s');
    $db->prepare("UPDATE admin_password_resets SET used_at = :now WHERE admin_id = :aid AND used_at IS NULL")
       ->execute([':now' => $nowStr, ':aid' => $admin['id']]);

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 900);

    $ins = $db->prepare("
        INSERT INTO admin_password_resets (admin_id, token_hash, expires_at, ip_address)
        VALUES (:aid, :thash, :exp, :ip)
    ");
    $ins->execute([
        ':aid'   => $admin['id'],
        ':thash' => $tokenHash,
        ':exp'   => $expiresAt,
        ':ip'    => $clientIp
    ]);

    logSystemEvent('admin_reset_initiated', "Reset token generated for admin {$admin['username']}");

    return [
        'success' => true,
        'token'   => $rawToken,
        'expires' => $expiresAt
    ];
}

function adminVerifyResetToken(string $rawToken): ?array {
    if (empty($rawToken) || !ctype_xdigit($rawToken)) {
        return null;
    }

    $db = getDB();
    if (!$db) return null;

    $tokenHash = hash('sha256', $rawToken);
    $nowStr = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        SELECT r.id as reset_id, r.admin_id, r.expires_at, a.username, a.email
        FROM admin_password_resets r
        INNER JOIN admins a ON a.id = r.admin_id
        WHERE r.token_hash = :thash AND r.used_at IS NULL AND r.expires_at > :now
        LIMIT 1
    ");
    $stmt->execute([':thash' => $tokenHash, ':now' => $nowStr]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function adminCompletePasswordReset(string $rawToken, string $newPassword): array {
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร'];
    }

    $record = adminVerifyResetToken($rawToken);
    if (!$record) {
        return ['success' => false, 'error' => 'ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว'];
    }

    $db = getDB();
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $nowStr = date('Y-m-d H:i:s');

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE admins SET password_hash = :h WHERE id = :id")
           ->execute([':h' => $newHash, ':id' => $record['admin_id']]);

        $db->prepare("UPDATE admin_password_resets SET used_at = :now WHERE admin_id = :aid AND used_at IS NULL")
           ->execute([':now' => $nowStr, ':aid' => $record['admin_id']]);

        $db->commit();
        logSystemEvent('admin_password_reset_completed', "Admin {$record['username']} reset password via recovery token");
        return ['success' => true, 'message' => 'ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว สามารถเข้าสู่ระบบได้ทันที'];
    } catch (\Throwable $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'ไม่สามารถบันทึกรหัสผ่านใหม่ได้'];
    }
}

function adminRegenerateRecoveryKey(int $adminId): string {
    $segments = [];
    for ($i = 0; $i < 4; $i++) {
        $segments[] = strtoupper(bin2hex(random_bytes(2)));
    }
    $plainKey = 'PK-' . implode('-', $segments);
    $hash = password_hash($plainKey, PASSWORD_BCRYPT);

    $db = getDB();
    if ($db) {
        $db->prepare("UPDATE admins SET recovery_code_hash = :h WHERE id = :id")
           ->execute([':h' => $hash, ':id' => $adminId]);
    }

    logSystemEvent('admin_recovery_key_regenerated', "Admin ID {$adminId} regenerated recovery key");
    return $plainKey;
}
