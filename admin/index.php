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
    // Login throttling removed for testing — to be re-added later.
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please enter your username and password.';
    } elseif (loginAdmin($pdo, $username, $password)) {
        header('Location: ' . APP_BASE . '/admin/dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/assets/img/zdg_logo.jpeg" type="image/jpeg">
<title>Admin Login — ZD Birthdays</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-body">

<div class="login-wrap">
  <div class="login-brand">
    <img src="/assets/img/zdg_logo.jpeg" class="brand-logo" alt="Zambezi Diamond">
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
        <input type="password" id="password" name="password"
               autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Sign In</button>
    </form>
  </div>
</div>

</body>
</html>
