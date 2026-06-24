<?php
// ============================================================
// ZD Birthdays — Helper Functions
// ============================================================

require_once __DIR__ . '/bootstrap.php';

// ------------------------------------------------------------
// Timezone Helpers
// ------------------------------------------------------------

function appNow(): DateTime {
    return new DateTime('now', new DateTimeZone(APP_TIMEZONE));
}

function appToday(): string {
    return appNow()->format('m-d');
}

function appTomorrow(): string {
    $dt = appNow();
    $dt->modify('+1 day');
    return $dt->format('m-d');
}

function appCurrentMonth(): string {
    return appNow()->format('m');
}

function midnightTimestamp(): int {
    $dt = new DateTime('tomorrow midnight', new DateTimeZone(APP_TIMEZONE));
    return $dt->getTimestamp();
}

// ------------------------------------------------------------
// Birthday Queries
// ------------------------------------------------------------

// Each query folds the employee's primary photo in as `primary_image` via a
// single LIMIT-1 subquery, so list/calendar loops no longer fire one extra
// query per employee (see getPrimaryImageUrl).
const PRIMARY_IMAGE_SUBQUERY =
    "(SELECT ei.image_path FROM employee_images ei
      WHERE ei.employee_id = e.id ORDER BY ei.sort_order ASC LIMIT 1) AS primary_image";

function getTodaysBirthdays(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT e.*, c.name AS company_name, c.short_code,
               b.name AS branch_name, " . PRIMARY_IMAGE_SUBQUERY . "
        FROM employees e
        JOIN companies c ON e.company_id = c.id
        JOIN branches  b ON e.branch_id  = b.id
        WHERE e.birthdate = ?
        ORDER BY e.full_name
    ");
    $stmt->execute([appToday()]);
    return $stmt->fetchAll();
}

function getTomorrowsBirthdays(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT e.*, c.name AS company_name, b.name AS branch_name
        FROM employees e
        JOIN companies c ON e.company_id = c.id
        JOIN branches  b ON e.branch_id  = b.id
        WHERE e.birthdate = ?
        ORDER BY e.full_name
    ");
    $stmt->execute([appTomorrow()]);
    return $stmt->fetchAll();
}

function getMonthBirthdays(PDO $pdo, string $month): array {
    $m = str_pad((int)$month, 2, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("
        SELECT e.*, c.name AS company_name, b.name AS branch_name, " . PRIMARY_IMAGE_SUBQUERY . "
        FROM employees e
        JOIN companies c ON e.company_id = c.id
        JOIN branches  b ON e.branch_id  = b.id
        WHERE LEFT(e.birthdate, 2) = ?
        ORDER BY SUBSTRING(e.birthdate, 4, 2) + 0, e.full_name
    ");
    $stmt->execute([$m]);
    return $stmt->fetchAll();
}

function getAllBirthdays(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT e.*, c.name AS company_name, b.name AS branch_name, " . PRIMARY_IMAGE_SUBQUERY . "
        FROM employees e
        JOIN companies c ON e.company_id = c.id
        JOIN branches  b ON e.branch_id  = b.id
        ORDER BY e.birthdate, e.full_name
    ");
    return $stmt->fetchAll();
}

function getBirthdays48hrs(PDO $pdo): array {
    $now = appNow();
    $dates = [];
    for ($i = 1; $i <= 2; $i++) {
        $d = clone $now;
        $d->modify("+{$i} day");
        $dates[] = $d->format('m-d');
    }
    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $stmt = $pdo->prepare("
        SELECT e.*, c.name AS company_name, b.name AS branch_name, " . PRIMARY_IMAGE_SUBQUERY . "
        FROM employees e
        JOIN companies c ON e.company_id = c.id
        JOIN branches  b ON e.branch_id  = b.id
        WHERE e.birthdate IN ({$placeholders})
        ORDER BY e.birthdate
    ");
    $stmt->execute($dates);
    return $stmt->fetchAll();
}

// ------------------------------------------------------------
// Dashboard Counts
// ------------------------------------------------------------

function countBirthdaysPassed(PDO $pdo): int {
    $today = appToday();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE birthdate <= ?");
    $stmt->execute([$today]);
    return (int)$stmt->fetchColumn();
}

function countBirthdaysRemaining(PDO $pdo): int {
    $today = appToday();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE birthdate > ?");
    $stmt->execute([$today]);
    return (int)$stmt->fetchColumn();
}

function countMissingImages(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE image_count = 0")->fetchColumn();
}

function countMissingMessages(PDO $pdo): int {
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM employees
        WHERE primary_message IS NULL OR TRIM(primary_message) = ''
    ");
    return (int)$stmt->fetchColumn();
}

function countTotalEmployees(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
}

// ------------------------------------------------------------
// Positions (per-company job titles)
// ------------------------------------------------------------

// All positions, used to build the company-filtered dropdown (mirrors $branches).
function getAllPositions(PDO $pdo): array {
    return $pdo->query(
        "SELECT id, company_id, title FROM positions ORDER BY company_id, tier, title"
    )->fetchAll();
}

// Case-insensitive lookup of a position title within a company.
// Returns the canonical stored title, or null if not found.
function findPositionTitle(PDO $pdo, int $company_id, string $title): ?string {
    $stmt = $pdo->prepare(
        "SELECT title FROM positions WHERE company_id = ? AND LOWER(title) = LOWER(?) LIMIT 1"
    );
    $stmt->execute([$company_id, trim($title)]);
    $row = $stmt->fetch();
    return $row ? $row['title'] : null;
}

// ------------------------------------------------------------
// Employee Images
// ------------------------------------------------------------

function getEmployeeImages(PDO $pdo, int $employee_id): array {
    $stmt = $pdo->prepare(
        "SELECT * FROM employee_images WHERE employee_id = ? ORDER BY sort_order ASC"
    );
    $stmt->execute([$employee_id]);
    return $stmt->fetchAll();
}

function countEmployeeImages(PDO $pdo, int $employee_id): int {
    $stmt = $pdo->prepare("SELECT image_count FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    return (int)$stmt->fetchColumn();
}

// Recalculate and persist employees.image_count from the employee_images table.
// Recalculating (rather than incrementing) keeps the counter drift-proof.
function recalcEmployeeImageCount(PDO $pdo, int $employee_id): int {
    $stmt = $pdo->prepare("
        UPDATE employees
        SET image_count = (SELECT COUNT(*) FROM employee_images WHERE employee_id = ?)
        WHERE id = ?
    ");
    $stmt->execute([$employee_id, $employee_id]);

    $stmt = $pdo->prepare("SELECT image_count FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    return (int)$stmt->fetchColumn();
}

// Gender-based fallback image filenames, fetched once per request and cached
// so per-employee loops don't re-query site_settings on every row.
function fallbackImageName(PDO $pdo, string $gender): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [
            'M' => getSetting($pdo, 'fallback_image_male'),
            'F' => getSetting($pdo, 'fallback_image_female'),
        ];
    }
    return $cache[$gender] ?? $cache['M'] ?? null;
}

// Resolve the display image URL for an employee. $image_path is the already-
// fetched primary photo filename (from the PRIMARY_IMAGE_SUBQUERY column);
// pass null when the employee has no photo to get the gender-based fallback.
function getPrimaryImageUrl(PDO $pdo, int $employee_id, string $gender = 'M', ?string $image_path = null): string {
    if ($image_path) {
        return UPLOAD_URL . '/employees/' . $employee_id . '/' . rawurlencode($image_path);
    }

    // Gender-based default image fallback
    $fallback = fallbackImageName($pdo, $gender);
    if ($fallback) {
        return UPLOAD_URL . '/defaults/' . rawurlencode($fallback);
    }

    return '/assets/img/default-avatar.svg';
}

// Replace an employee's photo with a single uploaded file.
// Uploads the new image first; only if that succeeds are the previous
// image file(s) and row(s) removed, so a failed upload never loses the
// existing photo. One image per employee.
function setEmployeeImage(PDO $pdo, int $employee_id, array $file): string|false {
    $filename = handleImageUpload($file, $employee_id);
    if (!$filename) return false;

    // Remove previous image file(s) and row(s)
    $existing = getEmployeeImages($pdo, $employee_id);
    foreach ($existing as $img) {
        $old = UPLOAD_PATH . '/employees/' . $employee_id . '/' . $img['image_path'];
        if (file_exists($old)) unlink($old);
    }
    $pdo->prepare("DELETE FROM employee_images WHERE employee_id = ?")->execute([$employee_id]);

    $stmt = $pdo->prepare(
        "INSERT INTO employee_images (employee_id, image_path, sort_order) VALUES (?, ?, 1)"
    );
    $stmt->execute([$employee_id, $filename]);

    recalcEmployeeImageCount($pdo, $employee_id);
    return $filename;
}

function deleteEmployeeImage(PDO $pdo, int $image_id, int $employee_id): bool {
    $stmt = $pdo->prepare(
        "SELECT image_path FROM employee_images WHERE id = ? AND employee_id = ?"
    );
    $stmt->execute([$image_id, $employee_id]);
    $row = $stmt->fetch();
    if (!$row) return false;

    $filepath = UPLOAD_PATH . '/employees/' . $employee_id . '/' . $row['image_path'];
    if (file_exists($filepath)) unlink($filepath);

    $stmt = $pdo->prepare("DELETE FROM employee_images WHERE id = ?");
    $stmt->execute([$image_id]);

    recalcEmployeeImageCount($pdo, $employee_id);
    return true;
}

// ------------------------------------------------------------
// Record Locking
// ------------------------------------------------------------

function isLockStale(?string $locked_at): bool {
    if (!$locked_at) return true;
    return (time() - strtotime($locked_at)) >= LOCK_TIMEOUT;
}

// Acquire the lock atomically: a single conditional UPDATE claims the row only
// if it's free, already ours, or the previous lock has gone stale. This closes
// the check-then-act race where two admins could both pass a separate SELECT.
function lockEmployee(PDO $pdo, int $employee_id, int $admin_id): bool {
    $stmt = $pdo->prepare("
        UPDATE employees
        SET locked_by = ?, locked_at = NOW()
        WHERE id = ?
          AND (locked_by IS NULL
               OR locked_by = ?
               OR locked_at < (NOW() - INTERVAL ? SECOND))
    ");
    $stmt->execute([$admin_id, $employee_id, $admin_id, LOCK_TIMEOUT]);
    return $stmt->rowCount() > 0;
}

function releaseLock(PDO $pdo, int $employee_id, int $admin_id): void {
    $stmt = $pdo->prepare(
        "UPDATE employees SET locked_by = NULL, locked_at = NULL WHERE id = ? AND locked_by = ?"
    );
    $stmt->execute([$employee_id, $admin_id]);
}

function refreshLock(PDO $pdo, int $employee_id, int $admin_id): bool {
    $stmt = $pdo->prepare(
        "UPDATE employees SET locked_at = NOW() WHERE id = ? AND locked_by = ?"
    );
    $stmt->execute([$employee_id, $admin_id]);
    return $stmt->rowCount() > 0;
}

function getLockHolder(PDO $pdo, int $employee_id): ?array {
    $stmt = $pdo->prepare("
        SELECT a.username, e.locked_at
        FROM employees e
        LEFT JOIN admin_users a ON e.locked_by = a.id
        WHERE e.id = ? AND e.locked_by IS NOT NULL
    ");
    $stmt->execute([$employee_id]);
    return $stmt->fetch() ?: null;
}

function isLockedByOther(PDO $pdo, int $employee_id, int $admin_id): bool {
    $stmt = $pdo->prepare("SELECT locked_by, locked_at FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch();
    if (!$emp || !$emp['locked_by']) return false;
    if ((int)$emp['locked_by'] === $admin_id) return false;
    return !isLockStale($emp['locked_at']);
}

// ------------------------------------------------------------
// Site Settings
// ------------------------------------------------------------

function getSetting(PDO $pdo, string $key): ?string {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : null;
}

function setSetting(PDO $pdo, string $key, ?string $value): void {
    $stmt = $pdo->prepare("
        INSERT INTO site_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->execute([$key, $value, $value]);
}

// ------------------------------------------------------------
// Countdown Captions
// ------------------------------------------------------------

function getActiveCaptions(PDO $pdo): array {
    $stmt = $pdo->query("SELECT id, caption_text FROM countdown_captions WHERE is_active = 1 ORDER BY id");
    return $stmt->fetchAll();
}

// ------------------------------------------------------------
// Birthday Wishes
// ------------------------------------------------------------

function getActiveWishes(PDO $pdo, int $employee_id): array {
    $stmt = $pdo->prepare("
        SELECT * FROM birthday_wishes
        WHERE employee_id = ? AND is_active = 1
        ORDER BY created_at ASC
    ");
    $stmt->execute([$employee_id]);
    return $stmt->fetchAll();
}

function hasSessionWished(int $employee_id): bool {
    return isset($_SESSION['wishes_submitted'][$employee_id]);
}

function markSessionWished(int $employee_id): void {
    if (!isset($_SESSION['wishes_submitted'])) {
        $_SESSION['wishes_submitted'] = [];
    }
    $_SESSION['wishes_submitted'][$employee_id] = true;
}

// ------------------------------------------------------------
// Image Upload
// ------------------------------------------------------------

// Resize (if larger than IMAGE_MAX_DIM on the long edge) and re-encode an image
// so stored photos stay small — a 5 MB camera original becomes a few hundred KB,
// which speeds up every public page load and shrinks backups. Animated GIFs are
// copied untouched to keep their animation; if GD is missing or anything fails,
// we fall back to a plain move so an upload never silently disappears.
// $isUpload picks the safe mover (move_uploaded_file) for real HTTP uploads.
function storeOptimizedImage(string $tmp, string $dest, string $ext, bool $isUpload = true): bool {
    $moveRaw = static function (string $src, string $dst) use ($isUpload): bool {
        return $isUpload ? move_uploaded_file($src, $dst) : rename($src, $dst);
    };

    // GIFs may be animated — never resample them. No GD: nothing to optimise.
    if ($ext === 'gif' || !function_exists('imagecreatetruecolor')) {
        return $moveRaw($tmp, $dest);
    }

    $info = @getimagesize($tmp);
    if (!$info) return $moveRaw($tmp, $dest);
    [$w, $h] = $info;

    switch ($info['mime']) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($tmp); break;
        case 'image/png':  $src = @imagecreatefrompng($tmp);  break;
        case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false; break;
        default:           $src = false;
    }
    if (!$src) return $moveRaw($tmp, $dest);

    $scale = min(1, IMAGE_MAX_DIM / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    // Preserve transparency for PNG / WebP.
    if (in_array($info['mime'], ['image/png', 'image/webp'], true)) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = false;
    switch ($ext) {
        case 'jpg':
        case 'jpeg': $ok = imagejpeg($dst, $dest, IMAGE_QUALITY); break;
        case 'png':  $ok = imagepng($dst, $dest, 6); break;
        case 'webp': $ok = function_exists('imagewebp') ? imagewebp($dst, $dest, IMAGE_QUALITY) : false; break;
    }
    imagedestroy($src);
    imagedestroy($dst);

    if (!$ok) return $moveRaw($tmp, $dest); // re-encode failed — keep the original
    if ($isUpload) @unlink($tmp);           // tmp consumed by GD, not by move_uploaded_file
    return true;
}

function handleImageUpload(array $file, int $employee_id): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE) return false;

    $mime = false;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }
    if (!$mime) {
        $info = @getimagesize($file['tmp_name']);
        $mime = $info ? $info['mime'] : false;
    }

    if (!$mime || !in_array($mime, ALLOWED_IMAGE_TYPES)) return false;

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTS)) return false;

    $dir  = UPLOAD_PATH . '/employees/' . $employee_id;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = uniqid('img_', true) . '.' . $ext;
    $dest     = $dir . '/' . $filename;

    if (!storeOptimizedImage($file['tmp_name'], $dest, $ext)) return false;
    return $filename;
}

function handleDefaultImageUpload(array $file, string $name): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE) return false;

    $mime = false;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }
    if (!$mime) {
        $info = @getimagesize($file['tmp_name']);
        $mime = $info ? $info['mime'] : false;
    }

    if (!$mime || !in_array($mime, ALLOWED_IMAGE_TYPES)) return false;

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTS)) return false;

    $dir  = UPLOAD_PATH . '/defaults';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = $name . '.' . $ext;
    $dest     = $dir . '/' . $filename;

    if (!storeOptimizedImage($file['tmp_name'], $dest, $ext)) return false;
    return $filename;
}

// ------------------------------------------------------------
// Formatting
// ------------------------------------------------------------

function formatBirthdate(string $mm_dd): string {
    $dt = DateTime::createFromFormat('m-d', $mm_dd);
    return $dt ? $dt->format('F j') : $mm_dd;
}

function countWords(string $text): int {
    return str_word_count(strip_tags(trim($text)));
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return date('M j', strtotime($datetime));
}

// ------------------------------------------------------------
// Flash Messages
// ------------------------------------------------------------

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Render a flash message: success → auto-dismissing toast (10s),
// other types (danger/warning) → inline alert banner.
function renderFlash(?array $flash): void {
    if (!$flash) return;
    $msg = htmlspecialchars($flash['message']);
    if ($flash['type'] === 'success') {
        echo '<div class="toast toast-success" role="status">'
           . '<span class="toast-msg">' . $msg . '</span>'
           . '<button type="button" class="toast-close" aria-label="Dismiss">&times;</button>'
           . '</div>';
    } else {
        echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . '">' . $msg . '</div>';
    }
}

// ------------------------------------------------------------
// CSRF Protection
// Requires an active session (call sessionStart() first).
// ------------------------------------------------------------

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Hidden input for embedding in any POST form.
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf(): bool {
    $sent = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token'])
        && is_string($sent)
        && hash_equals($_SESSION['csrf_token'], $sent);
}

// Gate a POST handler. Halts with 403 if the token is missing/invalid.
function requireCsrf(): void {
    if (!verifyCsrf()) {
        http_response_code(403);
        die('Invalid or expired form token. Please go back, reload the page, and try again.');
    }
}

// ------------------------------------------------------------
// CAPTCHA (simple math)
// ------------------------------------------------------------

function generateCaptcha(): array {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $ops = ['+', '-'];
    $op  = $ops[array_rand($ops)];
    $answer = ($op === '+') ? ($a + $b) : ($a - $b);
    $_SESSION['captcha_answer'] = $answer;
    return ['a' => $a, 'b' => $b, 'op' => $op];
}

function verifyCaptcha(string $submitted): bool {
    if (!isset($_SESSION['captcha_answer'])) return false;
    $valid = (int)$submitted === (int)$_SESSION['captcha_answer'];
    unset($_SESSION['captcha_answer']);
    return $valid;
}

// ------------------------------------------------------------
// CSV Template
// ------------------------------------------------------------

// Day-first birthdate parser for CSV import. Returns 'MM-DD' or null.
// Tolerates the formats Excel produces after it auto-converts "dd/mm":
//   dd/mm, dd-mm, dd.mm, dd/mm/yyyy, dd-mm-yyyy, dd-Mon, Mon-dd,
//   and Excel's text-formula wrapper ="dd/mm". Day is always first.
function parseBirthdateInput(string $raw): ?string {
    $raw = trim($raw);
    // Strip Excel text-formula wrapper: ="08/06" -> 08/06
    $raw = preg_replace('/^=?"(.*)"$/', '$1', $raw);
    $raw = trim($raw);
    if ($raw === '') return null;

    $months = ['jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,
               'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12];

    $day = $month = null;

    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})(?:[\/\-.]\d{2,4})?$/', $raw, $m)) {
        // dd/mm  or  dd/mm/yyyy  (day first)
        $day = (int)$m[1]; $month = (int)$m[2];
    } elseif (preg_match('/^(\d{1,2})[\/\-.\s]([A-Za-z]{3,})(?:[\/\-.]\d{2,4})?$/', $raw, $m)) {
        // dd-Mon  (e.g. 08-Jun)
        $day = (int)$m[1]; $month = $months[strtolower(substr($m[2], 0, 3))] ?? null;
    } elseif (preg_match('/^([A-Za-z]{3,})[\/\-.\s](\d{1,2})$/', $raw, $m)) {
        // Mon-dd  (e.g. Jun-08)
        $month = $months[strtolower(substr($m[1], 0, 3))] ?? null; $day = (int)$m[2];
    }

    if ($day === null || $month === null) return null;
    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) return null;

    return str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
}

function outputCSVTemplate(): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="zd_birthday_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Full Name', 'Birthdate (dd/mm)', 'Gender (M/F)', 'Company Code', 'Branch', 'Position']);
    // Birthdate written as an Excel text formula (="dd/mm") so spreadsheets
    // keep it literally as dd/mm instead of converting it to a real date.
    fputcsv($out, ['John Doe',   '="15/03"', 'M', 'ZDL', 'Ndola',  'Sales Consultant']);
    fputcsv($out, ['Jane Smith', '="22/08"', 'F', 'IBS', 'Lusaka', 'Loan Officer']);
    fclose($out);
    exit;
}

// ------------------------------------------------------------
// Cleanup (called on index.php load)
// ------------------------------------------------------------

function cleanupExpiredWishes(PDO $pdo): void {
    // Hard-delete wishes created more than 24 hours ago
    $pdo->exec("DELETE FROM birthday_wishes WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}
