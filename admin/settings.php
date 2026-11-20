<?php
/**
 * PhotoKY - Admin Settings, Password Change & Recovery Key Security
 */

$adminPageTitle = 'Settings & Security';
require_once __DIR__ . '/header.php';

$db = getDB();
$adminUser = getAdminUser();
$csrfToken = getCsrfToken();

$successMsg = '';
$errorMsg = '';
$generatedRecoveryKey = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $formType = $_POST['form_type'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $errorMsg = 'Invalid security token. Please refresh and try again.';
    } elseif ($formType === 'site_settings') {
        $siteName = mb_substr(trim($_POST['site_name'] ?? 'PhotoKY'), 0, 100);
        $siteTagline = mb_substr(trim($_POST['site_tagline'] ?? ''), 0, 255);
        $maxSize = max(1, min(50, (int)($_POST['max_upload_size_mb'] ?? 50)));
        $allowedExt = preg_replace('/[^a-zA-Z0-9,]/', '', strtolower(trim($_POST['allowed_extensions'] ?? 'jpg,jpeg,png,webp,gif')));
        $enablePublic = isset($_POST['enable_public_gallery']) ? '1' : '0';

        $updates = [
            'site_name'             => $siteName ?: 'PhotoKY',
            'site_tagline'          => $siteTagline,
            'max_upload_size_mb'    => (string)$maxSize,
            'allowed_extensions'    => $allowedExt ?: 'jpg,jpeg,png,webp',
            'enable_public_gallery' => $enablePublic
        ];

        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($updates as $k => $v) {
            $stmt->execute([':k' => $k, ':v' => $v]);
        }

        logSystemEvent('admin_settings_updated', "Updated site settings");
        $successMsg = 'Site settings saved successfully.';
    } elseif ($formType === 'change_password') {
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        if ($newPass !== $confirmPass) {
            $errorMsg = 'New password confirmation does not match.';
        } else {
            $res = adminChangePassword($adminUser['id'], $currentPass, $newPass);
            if ($res['success']) {
                $successMsg = $res['message'];
            } else {
                $errorMsg = $res['error'];
            }
        }
    } elseif ($formType === 'update_profile_recovery') {
        $newEmail = trim($_POST['admin_email'] ?? '');
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Please enter a valid email address.';
        } else {
            try {
                $db->prepare("UPDATE admins SET email = :e WHERE id = :id")
                   ->execute([':e' => $newEmail, ':id' => $adminUser['id']]);
                $_SESSION['admin_email'] = $newEmail;
                $adminUser['email'] = $newEmail;
                $successMsg = 'Admin email updated successfully.';
            } catch (PDOException $e) {
                $errorMsg = 'Could not update email (it may already be in use).';
            }
        }
    } elseif ($formType === 'regenerate_recovery_key') {
        $verifyPass = (string)($_POST['verify_password'] ?? '');
        $aRow = $db->prepare("SELECT password_hash FROM admins WHERE id = :id LIMIT 1");
        $aRow->execute([':id' => $adminUser['id']]);
        $hash = $aRow->fetchColumn();

        if (!$hash || !password_verify($verifyPass, $hash)) {
            $errorMsg = 'Current password verification failed. Recovery key was not changed.';
        } else {
            $generatedRecoveryKey = adminRegenerateRecoveryKey($adminUser['id']);
            $successMsg = 'A new Recovery Security Key has been generated. Copy and store it securely now — it will not be shown again.';
        }
    }
}

// Fetch current settings
$siteNameVal = getSetting('site_name', SITE_NAME);
$siteTaglineVal = getSetting('site_tagline', SITE_TAGLINE);
$maxSizeVal = getSetting('max_upload_size_mb', '50');
$allowedExtVal = getSetting('allowed_extensions', 'jpg,jpeg,png,webp,gif');
$enablePublicVal = getSetting('enable_public_gallery', '1');

// Fetch recent security logs
$recentLogs = [];
if ($db) {
    try {
        $recentLogs = $db->query("SELECT * FROM system_logs ORDER BY id DESC LIMIT 10")->fetchAll();
    } catch (\Throwable $e) {}
}
?>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-1">ตั้งค่าระบบและความปลอดภัย</h1>
    <p class="text-muted small mb-0">จัดการค่าเริ่มต้นของเว็บไซต์ เปลี่ยนรหัสผ่านผู้ดูแลระบบ และรหัสกู้คืนบัญชี</p>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success py-2 px-3 small rounded-3 mb-3"><?= e($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3"><?= e($errorMsg) ?></div>
<?php endif; ?>

<?php if ($generatedRecoveryKey): ?>
    <div class="alert alert-warning border rounded-4 p-4 mb-4">
        <div class="fw-bold mb-1">Save Your New Recovery Security Key</div>
        <p class="small mb-2">Use this key at <code>/admin/forgot-password.php</code> if you ever forget your admin password. Only its bcrypt hash is stored in the database.</p>
        <div class="input-group" style="max-width: 420px;">
            <input type="text" class="form-control code-copy-input fw-bold" value="<?= e($generatedRecoveryKey) ?>" readonly>
            <button type="button" class="btn btn-dark btn-sm px-3" onclick="window.copyToClipboard('<?= e($generatedRecoveryKey) ?>', 'Recovery Key copied!')">Copy Key</button>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Site Settings & Security Audit Log -->
    <div class="col-lg-6">
        <div class="admin-card mb-4">
            <h2 class="h5 fw-bold mb-3">ตั้งค่าพื้นฐานของระบบ</h2>
            <form method="POST" action="<?= BASE_URL ?>/admin/settings.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="form_type" value="site_settings">

                <div class="mb-3">
                    <label class="form-label small fw-semibold">ชื่อเว็บไซต์</label>
                    <input type="text" name="site_name" class="form-control rounded-3" value="<?= e($siteNameVal) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">คำโปรยเว็บไซต์ (Tagline)</label>
                    <input type="text" name="site_tagline" class="form-control rounded-3" value="<?= e($siteTaglineVal) ?>">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">ขนาดไฟล์สูงสุด (MB)</label>
                        <input type="number" name="max_upload_size_mb" class="form-control rounded-3" min="1" max="15" value="<?= min(15, (int)$maxSizeVal) ?>">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">นามสกุลไฟล์ที่อนุญาต</label>
                        <input type="text" name="allowed_extensions" class="form-control rounded-3" value="<?= e($allowedExtVal) ?>">
                    </div>
                </div>
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" name="enable_public_gallery" id="enablePublicGallery" <?= $enablePublicVal === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="enablePublicGallery">เปิดแสดงคลังรูปภาพสาธารณะ</label>
                </div>

                <button type="submit" class="btn-pill-primary btn-sm">บันทึกการตั้งค่า</button>
            </form>
        </div>

        <!-- Security Audit Log -->
        <div class="admin-card">
            <h2 class="h5 fw-bold mb-3">ประวัติการทำงานและความปลอดภัยล่าสุด</h2>
            <?php if (empty($recentLogs)): ?>
                <div class="text-muted small">ยังไม่มีประวัติการทำงาน</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm small align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>เหตุการณ์</th>
                                <th>รายละเอียด</th>
                                <th>IP / เวลา</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><code class="text-info"><?= e($log['event']) ?></code></td>
                                <td class="text-muted text-truncate" style="max-width: 210px;" title="<?= e($log['details']) ?>"><?= e($log['details']) ?></td>
                                <td class="text-muted text-nowrap" style="font-size: 0.74rem;">
                                    <?= e($log['ip_address']) ?><br><?= timeAgo($log['created_at']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Change Password & Recovery Security Key -->
    <div class="col-lg-6">
        <!-- Change Admin Password -->
        <div class="admin-card mb-4">
            <h2 class="h5 fw-bold mb-1">เปลี่ยนรหัสผ่านผู้ดูแลระบบ</h2>
            <p class="text-muted small mb-3">รหัสผ่านถูกเข้ารหัสอย่างปลอดภัยด้วยมาตรฐาน BCRYPT (`password_hash`)</p>

            <form method="POST" action="<?= BASE_URL ?>/admin/settings.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="form_type" value="change_password">

                <div class="mb-3">
                    <label class="form-label small fw-semibold">รหัสผ่านปัจจุบัน</label>
                    <input type="password" name="current_password" class="form-control rounded-3" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)</label>
                    <input type="password" name="new_password" class="form-control rounded-3" minlength="8" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password" class="form-control rounded-3" minlength="8" required>
                </div>

                <button type="submit" class="btn-pill-primary btn-sm">อัปเดตรหัสผ่าน</button>
            </form>
        </div>

        <!-- Admin Email & Recovery Security Key -->
        <div class="admin-card">
            <h2 class="h5 fw-bold mb-1">การกู้คืนรหัสผ่าน & ข้อมูลบัญชี</h2>
            <p class="text-muted small mb-3">เก็บรักษาอีเมลและรหัสกู้คืนความปลอดภัย (Recovery Security Key) ไว้เป็นความลับสำหรับใช้รีเซ็ตรหัสผ่าน</p>

            <form method="POST" action="<?= BASE_URL ?>/admin/settings.php" class="mb-4 pb-4 border-bottom">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="form_type" value="update_profile_recovery">

                <div class="mb-3">
                    <label class="form-label small fw-semibold">อีเมลผู้ดูแลระบบ</label>
                    <div class="input-group">
                        <input type="email" name="admin_email" class="form-control rounded-start-3" value="<?= e($adminUser['email']) ?>" required>
                        <button type="submit" class="btn btn-outline-dark btn-sm rounded-end-3 px-3">บันทึกอีเมล</button>
                    </div>
                </div>
            </form>

            <form method="POST" action="<?= BASE_URL ?>/admin/settings.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="form_type" value="regenerate_recovery_key">

                <div class="fw-semibold small mb-1">สร้างรหัสกู้คืนความปลอดภัยใหม่ (Recovery Security Key)</div>
                <p class="text-muted small mb-2">กรอกรหัสผ่านปัจจุบันเพื่อสุ่มรหัสกู้คืนชุดใหม่ (รหัสกู้คืนเดิมจะถูกยกเลิกทันที)</p>
                <div class="input-group">
                    <input type="password" name="verify_password" class="form-control form-control-sm rounded-start-3" placeholder="ยืนยันรหัสผ่านปัจจุบัน" required>
                    <button type="submit" class="btn btn-dark btn-sm rounded-end-3 px-3">สร้างรหัสกู้คืนใหม่</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
