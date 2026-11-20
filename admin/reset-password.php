<?php
/**
 * PhotoKY - Complete Admin Password Reset (Single-Use Timed Token)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isAdminLoggedIn()) {
    header("Location: " . BASE_URL . "/admin/settings.php");
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenRecord = adminVerifyResetToken($token);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $newPass = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    if (!verifyCsrfToken($csrf)) {
        $error = 'Invalid security session. Please try again.';
    } elseif (!$tokenRecord) {
        $error = 'This password reset token is invalid or has expired.';
    } elseif (strlen($newPass) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Password confirmation does not match.';
    } else {
        $res = adminCompletePasswordReset($token, $newPass);
        if ($res['success']) {
            header("Location: " . BASE_URL . "/admin/login.php?reset=success");
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
    <title>Set New Password - PhotoKY Admin</title>
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
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="card border rounded-4 p-4 p-md-5 shadow-sm">
                <div class="text-center mb-4">
                    <a href="<?= BASE_URL ?>/" class="brand-logo mb-2 d-inline-block">photo<span>ky.</span></a>
                    <h1 class="h4 fw-bold mb-1">Set New Password</h1>
                    <?php if ($tokenRecord): ?>
                        <p class="text-muted small mb-0">Resetting password for <strong><?= e($tokenRecord['username']) ?></strong></p>
                    <?php endif; ?>
                </div>

                <?php if (!$tokenRecord): ?>
                    <div class="alert alert-danger py-3 px-3 small rounded-3 mb-4 text-center">
                        This reset link is invalid, already used, or has expired (tokens are valid for 15 minutes).
                    </div>
                    <a href="<?= BASE_URL ?>/admin/forgot-password.php" class="btn-pill-primary w-100">
                        Request New Reset Token
                    </a>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>/admin/reset-password.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div class="mb-3">
                            <label for="new_password" class="form-label small fw-semibold">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control rounded-3" minlength="8" placeholder="Minimum 8 characters" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label small fw-semibold">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control rounded-3" minlength="8" placeholder="Re-enter new password" required>
                        </div>

                        <button type="submit" class="btn-pill-primary w-100 mt-2">
                            Update Password
                        </button>
                    </form>
                <?php endif; ?>

                <div class="text-center mt-4 pt-3 border-top">
                    <a href="<?= BASE_URL ?>/admin/login.php" class="text-muted small text-decoration-none">
                        &larr; Back to Admin Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
