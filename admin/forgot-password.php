<?php
/**
 * PhotoKY - Admin Forgot Password (Identity Verification via Recovery Key)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isAdminLoggedIn()) {
    header("Location: " . BASE_URL . "/admin/settings.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $identity = trim($_POST['identity'] ?? '');
    $recoveryKey = trim($_POST['recovery_key'] ?? '');

    if (!verifyCsrfToken($csrf)) {
        $error = 'Invalid security session. Please refresh and try again.';
    } elseif (empty($identity) || empty($recoveryKey)) {
        $error = 'Please enter both your admin username/email and your Recovery Security Key.';
    } else {
        $res = adminInitiatePasswordReset($identity, $recoveryKey);
        if ($res['success']) {
            header("Location: " . BASE_URL . "/admin/reset-password.php?token=" . urlencode($res['token']));
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
    <title>Reset Admin Password - PhotoKY</title>
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
                    <h1 class="h4 fw-bold mb-1">Admin Password Recovery</h1>
                    <p class="text-muted small mb-0">Verify your identity using your account and Recovery Security Key to generate a one-time reset token.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/admin/forgot-password.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="identity" class="form-label small fw-semibold">Admin Username or Email</label>
                        <input type="text" id="identity" name="identity" class="form-control rounded-3" value="<?= e($_POST['identity'] ?? '') ?>" placeholder="admin or admin@photoky.local" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="recovery_key" class="form-label small fw-semibold">Recovery Security Key</label>
                        <input type="password" id="recovery_key" name="recovery_key" class="form-control rounded-3" placeholder="Enter your recovery key" required>
                        <div class="form-text small">Configured in Admin Settings & Security.</div>
                    </div>

                    <button type="submit" class="btn-pill-primary w-100 mt-2">
                        Verify Identity & Continue
                    </button>
                </form>

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
