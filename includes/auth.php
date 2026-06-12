<?php
// ============================================================
// ZD Birthdays — Admin Authentication
// ============================================================

require_once __DIR__ . '/config.php';

function isHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function sessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Store sessions in a project-local, web-protected directory. This avoids
        // the common shared-hosting failure where the host's default save_path
        // isn't writable (or is blocked by open_basedir), which silently drops
        // every session and surfaces as "session expired" on login.
        $sessDir = APP_ROOT . '/storage/sessions';
        if (is_dir($sessDir) && is_writable($sessDir)) {
            session_save_path($sessDir);
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isHttps(), // auto-enabled once served over HTTPS in production
            'httponly' => true,
            'samesite' => 'Lax',     // Lax still blocks cross-site CSRF; Strict can drop the cookie on redirect-in flows
        ]);
        session_start();
    }
}

function isAdminLoggedIn(PDO $pdo): bool {
    if (!isset($_SESSION['admin_id'], $_SESSION['session_token'])) {
        return false;
    }
    $stmt = $pdo->prepare(
        "SELECT id FROM admin_users WHERE id = ? AND session_token = ?"
    );
    $stmt->execute([$_SESSION['admin_id'], $_SESSION['session_token']]);
    return (bool)$stmt->fetch();
}

function requireAdmin(PDO $pdo): void {
    if (!isAdminLoggedIn($pdo)) {
        header('Location: ' . APP_BASE . '/admin/index.php');
        exit;
    }
}

function getAdminId(): int {
    return (int)($_SESSION['admin_id'] ?? 0);
}

function getAdminUsername(): string {
    return $_SESSION['admin_username'] ?? '';
}

function loginAdmin(PDO $pdo, string $username, string $password): bool {
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ?");
    $stmt->execute([trim($username)]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    // Generate unique session token
    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare(
        "UPDATE admin_users SET session_token = ?, last_login = NOW() WHERE id = ?"
    );
    $stmt->execute([$token, $admin['id']]);

    $_SESSION['admin_id']       = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['session_token']  = $token;

    return true;
}

function logoutAdmin(PDO $pdo): void {
    $admin_id = getAdminId();
    if ($admin_id) {
        $stmt = $pdo->prepare("UPDATE admin_users SET session_token = NULL WHERE id = ?");
        $stmt->execute([$admin_id]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    session_destroy();
}

function createAdminAccount(PDO $pdo, string $username, string $password, int $created_by): bool {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO admin_users (username, password_hash, created_by) VALUES (?, ?, ?)"
        );
        $stmt->execute([trim($username), $hash, $created_by]);
        return true;
    } catch (PDOException) {
        return false; // Username already exists
    }
}

function updateAdminAccount(PDO $pdo, int $id, string $username, ?string $password, int $current_admin_id): bool {
    try {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "UPDATE admin_users SET username = ?, password_hash = ? WHERE id = ?"
            );
            $stmt->execute([trim($username), $hash, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE admin_users SET username = ? WHERE id = ?");
            $stmt->execute([trim($username), $id]);
        }
        // Update session if editing own account
        if ($id === $current_admin_id) {
            $_SESSION['admin_username'] = trim($username);
        }
        return true;
    } catch (PDOException) {
        return false;
    }
}

function deleteAdminAccount(PDO $pdo, int $id, int $current_admin_id): bool {
    if ($id === $current_admin_id) return false; // Cannot delete own account
    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function getAllAdmins(PDO $pdo): array {
    return $pdo->query("
        SELECT a.id, a.username, a.last_login, a.created_at,
               b.username AS created_by_name
        FROM admin_users a
        LEFT JOIN admin_users b ON a.created_by = b.id
        ORDER BY a.created_at ASC
    ")->fetchAll();
}

function adminExists(PDO $pdo): bool {
    return (int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn() > 0;
}

// ------------------------------------------------------------
// Login throttling — IP-based, backed by the login_attempts table.
// Blocks an IP after LOGIN_MAX_ATTEMPTS failures within LOGIN_WINDOW seconds.
// ------------------------------------------------------------

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW       = 900; // 15 minutes

function clientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Number of recent failed attempts from this IP inside the window.
function recentLoginFailures(PDO $pdo, string $ip): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE ip = ? AND attempted_at > (NOW() - INTERVAL ? SECOND)"
    );
    $stmt->execute([$ip, LOGIN_WINDOW]);
    return (int)$stmt->fetchColumn();
}

function isLoginThrottled(PDO $pdo, string $ip): bool {
    return recentLoginFailures($pdo, $ip) >= LOGIN_MAX_ATTEMPTS;
}

function recordLoginFailure(PDO $pdo, string $ip): void {
    $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (?)")->execute([$ip]);
}

// Wipe an IP's failures after a successful login.
function clearLoginFailures(PDO $pdo, string $ip): void {
    $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
}
