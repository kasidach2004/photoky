<?php
/**
 * PhotoKY - Admin Header Template (Clean White & Soft Gray Theme)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$adminUser = getAdminUser();
$adminPageTitle = isset($adminPageTitle) ? $adminPageTitle . ' - PhotoKY Admin' : 'Admin Dashboard - PhotoKY';
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

$pendingPhotosNavCount = 0;
$dbNav = getDB();
if ($dbNav) {
    try {
        $pendingPhotosNavCount = (int)$dbNav->query("SELECT COUNT(*) FROM photos WHERE is_approved = 0")->fetchColumn();
    } catch (\Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminPageTitle) ?></title>
    <link rel="icon" type="img/png" href="./img/logo01.png">
    <link rel="shortcut icon" type="img/png" href="<?= BASE_URL ?>/img/logo01.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Custom Style (White & Gray Theme) -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    
    <script>
        window.BASE_URL = "<?= BASE_URL ?>";
    </script>
</head>
<body style="background-color: #F7F8FA; min-height: 100vh;">

<!-- Database Status Banner for Admin -->
<?php if (!isDbConnected()): ?>
<div class="py-2 px-3 text-center small" style="background: #FEF3C7; border-bottom: 1px solid #FDE68A; color: #92400E;">
    <span>ยังไม่ได้เชื่อมต่อฐานข้อมูล</span>
    <a href="<?= BASE_URL ?>/db-setup.php" class="btn btn-sm btn-dark rounded-pill ms-2 py-0 px-2 fw-semibold" style="font-size: 0.75rem;">
        ตั้งค่าฐานข้อมูล &rarr;
    </a>
</div>
<?php endif; ?>

<!-- Mobile Admin Bar -->
<div class="d-md-none bg-white border-bottom px-3 py-2 d-flex justify-content-between align-items-center">
    <a href="<?= BASE_URL ?>/admin/index.php" class="brand-logo fs-5">photo<span>ky.</span></a>
    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" type="button" data-bs-toggle="collapse" data-bs-target="#adminMobileMenu">
        เมนู
    </button>
</div>

<div class="container-fluid p-0">
    <div class="row g-0">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 admin-sidebar d-md-flex flex-column collapse" id="adminMobileMenu">
            <div class="d-none d-md-flex align-items-center justify-content-between mb-4 px-2">
                <a href="<?= BASE_URL ?>/admin/index.php" class="brand-logo">
                    photo<span>ky.</span>
                </a>
                <span class="badge bg-dark text-white rounded-pill" style="font-size: 0.7rem;">ผู้ดูแลระบบ</span>
            </div>

            <div class="nav flex-column mb-auto">
                <a href="<?= BASE_URL ?>/admin/index.php" class="admin-nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    ภาพรวมระบบ
                </a>
                <a href="<?= BASE_URL ?>/admin/photos.php" class="admin-nav-item <?= $currentScript === 'photos.php' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                    จัดการและอนุมัติภาพ
                    <?php if ($pendingPhotosNavCount > 0): ?>
                        <span class="badge bg-warning text-dark rounded-pill ms-auto px-2 py-0 fw-bold" style="font-size: 0.7rem;" title="<?= $pendingPhotosNavCount ?> รูปภาพรอการอนุมัติ">
                            <?= $pendingPhotosNavCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/admin/comments.php" class="admin-nav-item <?= $currentScript === 'comments.php' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    ความคิดเห็น
                </a>
                <a href="<?= BASE_URL ?>/admin/settings.php" class="admin-nav-item <?= $currentScript === 'settings.php' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    ตั้งค่าและความปลอดภัย
                </a>
                
                <hr class="my-3">
                
                <a href="<?= BASE_URL ?>/" target="_blank" class="admin-nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    เปิดหน้าเว็บไซต์
                </a>
            </div>

            <div class="mt-4 pt-3 border-top">
                <div class="d-flex align-items-center justify-content-between px-2">
                    <div class="small">
                        <div class="fw-bold text-dark"><?= e($adminUser['username'] ?? 'admin') ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">ผู้ดูแลระบบสูงสุด</div>
                    </div>
                    <a href="<?= BASE_URL ?>/admin/logout.php" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1" style="font-size: 0.75rem;">
                        ออกจากระบบ
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="col-md-9 col-lg-10 p-3 p-md-4 p-lg-5">
