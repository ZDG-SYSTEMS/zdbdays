<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessionStart();

// POST + CSRF only — prevents an attacker from force-logging-out an admin
// via a cross-site GET request.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    logoutAdmin($pdo);
}
header('Location: ' . APP_BASE . '/admin/index.php');
exit;
