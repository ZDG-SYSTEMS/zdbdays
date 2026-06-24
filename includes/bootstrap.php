<?php
// ============================================================
// ZD Birthdays — Bootstrap
// Loads environment configuration from the project .env file and
// defines the application constants. Replaces the old (tracked,
// credential-bearing) includes/config.php — live DB data now lives
// in .env, which is git-ignored.
// ============================================================

// ------------------------------------------------------------
// Minimal .env loader (no Composer dependency).
// Parses KEY=VALUE lines, ignores blanks and # comments, strips
// surrounding quotes, and never overrides a real environment
// variable already set by the server.
// ------------------------------------------------------------
function loadEnv(string $path): void {
    static $loaded = [];
    if (isset($loaded[$path])) return;
    $loaded[$path] = true;

    if (!is_file($path) || !is_readable($path)) return;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;

        $eq = strpos($line, '=');
        if ($eq === false) continue;

        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));

        // Strip a single layer of surrounding quotes.
        $len = strlen($val);
        if ($len >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[$len - 1] === $val[0]) {
            $val = substr($val, 1, -1);
        }

        if ($key === '' || getenv($key) !== false) continue;
        putenv("$key=$val");
        $_ENV[$key] = $val;
    }
}

// Read a configuration value from the environment, falling back to $default.
function env(string $key, $default = null) {
    $val = getenv($key);
    if ($val === false) return $_ENV[$key] ?? $default;
    return $val;
}

loadEnv(dirname(__DIR__) . '/.env');

// ------------------------------------------------------------
// Database (credentials sourced from .env; the defaults are the
// harmless stock XAMPP values so a fresh local clone still runs).
// ------------------------------------------------------------
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'zdbd'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// ------------------------------------------------------------
// Application
// ------------------------------------------------------------
define('APP_ENV',      env('APP_ENV', 'development'));
define('APP_BASE',     env('APP_BASE', '/ZD_Birthdays'));   // subfolder under htdocs
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Africa/Lusaka'));
define('APP_ROOT',     dirname(__DIR__));
define('UPLOAD_PATH',  APP_ROOT . '/uploads');
define('UPLOAD_URL',   APP_BASE . '/uploads');
define('ADMIN_PATH',   APP_ROOT . '/admin');

// Per-app session store, kept out of XAMPP's shared C:\xampp\tmp so other
// apps' garbage collection can't expire our (long-lived) admin sessions.
define('SESSION_PATH',     APP_ROOT . '/storage/sessions');
define('SESSION_LIFETIME', (int)env('SESSION_LIFETIME', 28800)); // 8 hours

// ------------------------------------------------------------
// Record locking / editing
// ------------------------------------------------------------
define('LOCK_TIMEOUT',     (int)env('LOCK_TIMEOUT', 1800)); // 30 min idle window
define('WISH_EDIT_WINDOW', 30); // seconds

// ------------------------------------------------------------
// Image uploads
// ------------------------------------------------------------
define('MAX_IMAGES',    1);       // one photo per employee
define('MAX_FILE_SIZE', 5242880); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);
define('ALLOWED_IMAGE_EXTS',  ['jpg','jpeg','png','gif','webp']);
define('IMAGE_MAX_DIM', (int)env('IMAGE_MAX_DIM', 1000)); // px on the long edge
define('IMAGE_QUALITY', (int)env('IMAGE_QUALITY', 82));   // jpeg/webp quality
