<?php
// ============================================================
// ZD Birthdays — Scheduled Cleanup (CLI only)
// Purges birthday wishes older than 24 hours and prunes stale
// login-attempt rows. Run on a schedule, e.g. hourly.
//
// Windows Task Scheduler (or XAMPP shell):
//   "C:\xampp\php\php.exe" "C:\xampp\htdocs\ZD_Birthdays\cron\cleanup.php"
//
// Linux cron (hourly):
//   0 * * * * /usr/bin/php /var/www/ZD_Birthdays/cron/cleanup.php
// ============================================================

// Refuse to run over the web — this is a CLI maintenance task.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

cleanupExpiredWishes($pdo);

// Old throttling rows are only relevant inside LOGIN_WINDOW; drop the rest.
$pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");

echo '[' . date('Y-m-d H:i:s') . "] Cleanup complete.\n";
