<?php
/**
 * PhotoKY - Admin Logout Handler
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

adminLogout();

header("Location: " . BASE_URL . "/admin/login.php?logged_out=1");
exit;
