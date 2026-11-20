<?php
/**
 * PhotoKY - Admin Login Page
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isAdminLoggedIn()) {
    header("Location: " . BASE_URL . "/admin/index.php");
    exit;
}

$error = '';
$successMsg = '';
if (!empty($_GET['reset']) && $_GET['reset'] === 'success') {
    $successMsg = 'Password has been reset successfully. Please sign in with your new password.';
}
if (!empty($_GET['logged_out'])) {
    $successMsg = 'You have been securely signed out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!verifyCsrfToken($csrf)) {
        $error = 'Invalid security session. Please refresh and try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $res = adminLogin($username, $password);
        if ($res['success']) {
            header("Location: " . BASE_URL . "/admin/index.php");
            exit;
        }
        $error = $res['error'];
    }
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบผู้ดูแล (Admin Sign In) - PhotoKY</title>
    <link rel="icon" type="img/png" href="./img/logo01.png">
    <link rel="shortcut icon" type="img/png" href="<?= BASE_URL ?>/img/logo01.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="background: #F7F8FA; min-height: 100vh;">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-4">
            <div class="card border rounded-4 p-4 p-md-5 shadow-sm">
                <div class="text-center mb-4">
                    <a href="<?= BASE_URL ?>/" class="brand-logo mb-2 d-inline-block">photo<span>ky.</span></a>
                    <h1 class="h4 fw-bold mb-1">เข้าสู่ระบบผู้ดูแล</h1>
                    <p class="text-muted small mb-0">สำหรับผู้ดูแลระบบเพื่อตรวจสอบและอนุมัติรูปภาพ</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success py-2 px-3 small rounded-3 mb-3"><?= e($successMsg) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/admin/login.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label small fw-semibold">ชื่อผู้ใช้ หรือ อีเมล</label>
                        <input type="text" id="username" name="username" class="form-control rounded-3" value="<?= e($_POST['username'] ?? '') ?>" placeholder="admin" required autofocus>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label small fw-semibold mb-0">รหัสผ่าน</label>
                            <a href="<?= BASE_URL ?>/admin/forgot-password.php" class="small text-decoration-none text-muted">ลืมรหัสผ่าน?</a>
                        </div>
                        <input type="password" id="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn-pill-primary w-100 mt-2">
                        เข้าสู่ระบบจัดการ
                    </button>
                </form>

                <div class="text-center mt-4 pt-3 border-top">
                    <a href="<?= BASE_URL ?>/" class="text-muted small text-decoration-none">
                        &larr; กลับสู่หน้าหลัก PhotoKY
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
