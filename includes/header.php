<?php
/**
 * PhotoKY - Public Site Header (Clean White & Gray Pexels-Inspired Theme)
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME . ' - ' . SITE_TAGLINE;
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#FFFFFF">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="img/png" href="./img/logo01.png">
    <meta name="description" content="คลังภาพออนไลน์คุณภาพสูง อัปโหลดและแบ่งปันรูปภาพฟรี ไม่ต้องสมัครสมาชิก ปลอดภัยด้วยการตรวจสอบจากผู้ดูแลระบบ">
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Custom Minimalist Styling (Clean White & Soft Gray) -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    
    <script>
        window.BASE_URL = "<?= BASE_URL ?>";
    </script>
</head>
<body>

<!-- Database Connection Notice Banner (shown only when database is disconnected) -->
<?php if (!isDbConnected()): ?>
<div class="py-2 px-3 text-center small" style="background: #FEF3C7; border-bottom: 1px solid #FDE68A; color: #92400E;">
    <span>ยังไม่ได้เชื่อมต่อฐานข้อมูล</span>
    <a href="<?= BASE_URL ?>/db-setup.php" class="btn btn-sm btn-dark rounded-pill ms-2 py-0 px-3 fw-semibold" style="font-size: 0.75rem;">
        ตั้งค่าฐานข้อมูล &rarr;
    </a>
</div>
<?php endif; ?>

<!-- Navigation Bar -->
<header class="navbar-photoky">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between w-100 gap-3">
            <!-- Brand Logo -->
            <a href="<?= BASE_URL ?>/" class="brand-logo">
                photo<span>ky.</span>
            </a>

            <!-- Nav Links (Center) -->
            <nav class="d-none d-md-flex align-items-center gap-1">
                <a href="<?= BASE_URL ?>/" class="nav-link-custom <?= ($currentUri === '/' || str_contains($currentUri, 'index.php')) ? 'active' : '' ?>">หน้าหลัก</a>
                <a href="<?= BASE_URL ?>/explore.php" class="nav-link-custom <?= str_contains($currentUri, 'explore.php') ? 'active' : '' ?>">สำรวจรูปภาพ</a>
            </nav>

            <!-- Action CTAs (Right) -->
            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/upload.php" class="btn-pill-accent">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <span>อัปโหลดรูปภาพ</span>
                </a>

                <?php if (isAdminLoggedIn()): ?>
                    <a href="<?= BASE_URL ?>/admin/index.php" class="btn-pill-secondary btn-sm py-1 px-3 d-none d-md-inline-flex" title="แผงควบคุมผู้ดูแลระบบ">
                        ระบบผู้ดูแล
                    </a>
                <?php endif; ?>

                <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1 d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobilePublicNav" aria-label="Toggle Menu">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Collapsible Navigation -->
        <div class="collapse d-md-none mt-3 pt-2 border-top" id="mobilePublicNav">
            <nav class="d-flex flex-column gap-1">
                <a href="<?= BASE_URL ?>/" class="nav-link-custom">หน้าหลัก</a>
                <a href="<?= BASE_URL ?>/explore.php" class="nav-link-custom <?= str_contains($currentUri, 'explore.php') ? 'active' : '' ?>">สำรวจรูปภาพ</a>
                <a href="<?= BASE_URL ?>/upload.php" class="nav-link-custom <?= str_contains($currentUri, 'upload.php') ? 'active' : '' ?>">อัปโหลดรูปภาพ</a>
                <?php if (isAdminLoggedIn()): ?>
                    <a href="<?= BASE_URL ?>/admin/index.php" class="nav-link-custom">ระบบผู้ดูแล</a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</header>

<main>
