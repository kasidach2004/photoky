<?php
/**
 * PhotoKY - Simplified Database Setup (Username & Password Only)
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$connected = isDbConnected();
$activeConfig = Database::getActiveConfig();
$lastError = Database::getLastError();
$pageTitle = 'เชื่อมต่อฐานข้อมูล';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - PhotoKY</title>
    <link rel="icon" type="img/png" href="./img/logo01.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body style="background-color: #F7F8FA; min-height: 100vh;">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="text-center mb-4">
                <a href="<?= BASE_URL ?>/" class="brand-logo mb-2 d-inline-block fs-3">photo<span>ky.</span></a>
                <h1 class="h4 fw-bold mb-1">เชื่อมต่อฐานข้อมูล</h1>
                <p class="text-muted small">ใส่เฉพาะชื่อผู้ใช้ (Username) และรหัสผ่าน (Password) ตามไฟล์ <code>.env</code></p>
            </div>

            <!-- Status Banner -->
            <div class="card mb-4 border rounded-4 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background: <?= $connected ? '#D1FAE5' : '#FEE2E2' ?>; color: <?= $connected ? '#059669' : '#DC2626' ?>; flex-shrink: 0;">
                            <?php if ($connected): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <?php else: ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold small"><?= $connected ? 'เชื่อมต่อฐานข้อมูลเรียบร้อยแล้ว' : 'ยังไม่ได้เชื่อมต่อฐานข้อมูล' ?></div>
                            <div class="text-muted" style="font-size: 0.78rem;">
                                <?= $connected ? e($activeConfig['label'] ?? 'พร้อมใช้งาน') : 'กรุณากรอก Username และ Password ด้านล่าง' ?>
                            </div>
                        </div>
                        <?php if ($connected): ?>
                            <a href="<?= BASE_URL ?>/admin/login.php" class="btn-pill-accent btn-sm py-1 px-3">เข้าหน้า Admin</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Simplified Username & Password Only Form -->
            <div class="card border rounded-4 mb-4 shadow-sm">
                <div class="card-body p-4">
                    <form id="dbConnectForm">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">ชื่อผู้ใช้ฐานข้อมูล (Username)</label>
                            <input type="text" id="db_user" class="form-control rounded-3" value="<?= e(envVal('DB_USER')) ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold">รหัสผ่านฐานข้อมูล (Password)</label>
                            <input type="password" id="db_pass" class="form-control rounded-3" placeholder="กรอกรหัสผ่านฐานข้อมูล">
                        </div>

                        <button type="submit" id="submitBtn" class="btn-pill-primary w-100">
                            เข้าสู่ระบบและเชื่อมต่อฐานข้อมูล
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.all.min.js"></script>
<script>
document.getElementById('dbConnectForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerText = 'กำลังเชื่อมต่อ...';

    const payload = {
        username: document.getElementById('db_user').value.trim(),
        password: document.getElementById('db_pass').value
    };

    try {
        const res = await fetch('<?= BASE_URL ?>/api/db-connect.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'เชื่อมต่อสำเร็จ!',
                text: data.message,
                confirmButtonText: 'ไปที่หน้าเข้าสู่ระบบ Admin',
                customClass: { confirmButton: 'btn-pill-primary' },
                buttonsStyling: false
            }).then(() => {
                location.href = '<?= BASE_URL ?>/admin/login.php';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เชื่อมต่อไม่สำเร็จ',
                text: data.error,
                confirmButtonText: 'ตกลง',
                customClass: { confirmButton: 'btn-pill-primary' },
                buttonsStyling: false
            });
        }
    } catch (err) {
        Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย', 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = 'เข้าสู่ระบบและเชื่อมต่อฐานข้อมูล';
    }
});
</script>
</body>
</html>
