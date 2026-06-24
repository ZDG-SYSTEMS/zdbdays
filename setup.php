<?php
// ============================================================
// ZD Birthdays — First Admin Setup
// CAUTION! DELETE THIS FILE after creating your first admin account
// ============================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
sessionStart();

// Block access if any admin already exists
if (adminExists($pdo)) {
    die('<h2 style="font-family:sans-serif;color:#c0392b">Setup already complete.</h2><p style="font-family:sans-serif">An admin account exists. Delete this file from your server.</p>');
}


$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (!verifyCsrf()) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif (!$username || !$password) {
        $error = 'Username and password are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        $success = 'Admin account created. <strong>Delete this file (setup.php) now from the server.</strong>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup — ZD Birthdays</title>
<style>
  body{font-family:sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .box{background:#fff;padding:2rem;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.1);width:360px}
  h2{margin:0 0 1.5rem;color:#1A4BB5}
  label{display:block;font-size:.875rem;color:#1A1A2E;margin-bottom:.25rem}
  input{width:100%;padding:.6rem .8rem;border:1px solid #ccc;border-radius:6px;font-size:1rem;box-sizing:border-box;margin-bottom:1rem}
  button{width:100%;padding:.7rem;background:#F36D24;color:#fff;border:none;border-radius:6px;font-size:1rem;cursor:pointer}
  button:hover{background:#d8551a}
  .error{background:#fde8e8;color:#c0392b;padding:.7rem;border-radius:6px;margin-bottom:1rem;font-size:.875rem}
  .success{background:#e8f8ee;color:#1e7e44;padding:.7rem;border-radius:6px;margin-bottom:1rem;font-size:.875rem}
  .warn{background:#fff3cd;color:#856404;padding:.5rem .7rem;border-radius:6px;font-size:.8rem;margin-top:1rem}
</style>
</head>
<body>
<div class="box">
  <h2<?= $success ? ' style="text-align:center"' : '' ?>>Initial Admin Setup</h2>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
  <?php if (!$success): ?>
  <form method="POST">
    <?= csrfField() ?>
    <label>Username</label>
    <input type="text" name="username" autocomplete="off" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <label>Confirm Password</label>
    <input type="password" name="confirm" required>
    <button type="submit">Create Admin Account</button>
  </form>
  <div class="warn"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-exclamation-circle" viewBox="0 0 16 16" style="vertical-align: -0.125em"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/></svg> Delete <code>setup.php</code> from the server immediately after setup.</div>
  <?php endif; ?>
</div>
</body>
</html>
