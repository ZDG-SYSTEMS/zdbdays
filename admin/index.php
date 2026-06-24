<?php
// ============================================================
// Admin Login
// ============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessionStart();

// Already logged in
if (isAdminLoggedIn($pdo)) {
    header('Location: ' . APP_BASE . '/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = clientIp();

    if (!verifyCsrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif (isLoginThrottled($pdo, $ip)) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            $error = 'Please enter your username and password.';
        } elseif (loginAdmin($pdo, $username, $password)) {
            clearLoginFailures($pdo, $ip);
            header('Location: ' . APP_BASE . '/admin/dashboard.php');
            exit;
        } else {
            recordLoginFailure($pdo, $ip);
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login — ZD Birthdays</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/admin.css">
</head>
<body class="login-body">

<div class="login-wrap">
  <div class="login-brand">
    <img src="<?= APP_BASE ?>/assets/img/zdg_logo.jpeg" class="brand-logo" alt="Zambezi Diamond">
    <h1>ZD Birthdays</h1>
    <p>Admin Access</p>
  </div>

  <div class="login-card">
    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrfField() ?>
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="<?= sanitize($_POST['username'] ?? '') ?>"
               autocomplete="username" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-wrap">
          <input type="password" id="password" name="password"
                 autocomplete="current-password" required>
          <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Toggle password visibility">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16" style="vertical-align:-0.125em"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Sign In</button>
    </form>
  </div>
</div>

<script>
(function () {
  var input  = document.getElementById('password');
  var btn    = document.getElementById('pw-toggle');
  var eyeOn  = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16" style="vertical-align:-0.125em"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>';
  var eyeOff = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-eye-slash" viewBox="0 0 16 16" style="vertical-align:-0.125em"><path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/><path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/></svg>';
  btn.addEventListener('click', function () {
    var visible = input.type === 'text';
    input.type  = visible ? 'password' : 'text';
    btn.innerHTML = visible ? eyeOn : eyeOff;
  });
}());
</script>
</body>
</html>
