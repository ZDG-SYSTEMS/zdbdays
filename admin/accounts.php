<?php
// ============================================================
// Admin Account Management
// ============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessionStart();
requireAdmin($pdo);

$current_admin_id = getAdminId();
$flash  = getFlash();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm']   ?? '';

        if (!$username) $errors[] = 'Username is required.';
        elseif (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
        if (!$password) $errors[] = 'Password is required.';
        elseif (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        elseif ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            if (createAdminAccount($pdo, $username, $password, $current_admin_id)) {
                setFlash('success', "Admin account '{$username}' created.");
                header('Location: ' . APP_BASE . '/admin/accounts.php');
                exit;
            } else {
                $errors[] = "Username '{$username}' is already taken.";
            }
        }
    }

    if ($action === 'update') {
        $id       = (int)($_POST['edit_id'] ?? 0);
        $username = trim($_POST['edit_username'] ?? '');
        $password = $_POST['edit_password'] ?? '';
        $confirm  = $_POST['edit_confirm']   ?? '';

        if (!$username) $errors[] = 'Username is required.';
        if ($password && strlen($password) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($password && $password !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            if (updateAdminAccount($pdo, $id, $username, $password ?: null, $current_admin_id)) {
                setFlash('success', 'Account updated.');
                header('Location: ' . APP_BASE . '/admin/accounts.php');
                exit;
            } else {
                $errors[] = "Username '{$username}' is already taken.";
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['delete_id'] ?? 0);
        if (!deleteAdminAccount($pdo, $id, $current_admin_id)) {
            setFlash('danger', 'Cannot delete: either account not found or you cannot delete your own account.');
        } else {
            setFlash('success', 'Account deleted.');
        }
        header('Location: ' . APP_BASE . '/admin/accounts.php');
        exit;
    }
}

$admins = getAllAdmins($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Accounts — ZD Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/partials/nav.php'; ?>

<main class="admin-main">
  <div class="admin-topbar">
    <h2 class="page-title">Admin Accounts</h2>
  </div>

  <?php renderFlash($flash); ?>

  <div class="two-col-layout">

    <!-- Current Accounts -->
    <div class="form-card">
      <h3>Current Accounts</h3>
      <table class="emp-table">
        <thead>
          <tr><th>Username</th><th>Created By</th><th>Last Login</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($admins as $a): ?>
          <tr>
            <td>
              <?= htmlspecialchars($a['username']) ?>
              <?php if ($a['id'] == $current_admin_id): ?><span class="flag flag-ok">you</span><?php endif; ?>
            </td>
            <td><?= $a['created_by_name'] ? htmlspecialchars($a['created_by_name']) : '<span class="text-mid">—</span>' ?></td>
            <td><?= $a['last_login'] ? date('d M Y H:i', strtotime($a['last_login'])) : '<span class="text-mid">Never</span>' ?></td>
            <td class="td-actions">
              <button class="btn btn-sm btn-outline"
                      onclick="openEdit(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['username'])) ?>')">Edit</button>
              <?php if ($a['id'] != $current_admin_id): ?>
              <form method="POST" style="display:inline" class="js-confirm"
                    data-confirm="Delete account &quot;<?= htmlspecialchars($a['username']) ?>&quot;? This cannot be undone.">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="delete_id" value="<?= $a['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Create Account -->
    <div class="form-card">
      <h3>Create New Account</h3>
      <?php if (!empty($errors) && ($_POST['action'] ?? '') === 'create'): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" autocomplete="off"
                 value="<?= sanitize($_POST['username'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Password <span class="optional">(min 8 chars)</span></label>
          <div class="password-wrap">
            <input type="password" name="password" id="new_pass" autocomplete="new-password" required>
            <button type="button" class="pw-toggle" onclick="togglePw('new_pass')">👁</button>
          </div>
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <div class="password-wrap">
            <input type="password" name="confirm" id="new_confirm" required>
            <button type="button" class="pw-toggle" onclick="togglePw('new_confirm')">👁</button>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Create Account</button>
        </div>
      </form>
    </div>
  </div>
</main>

<!-- Edit Modal -->
<div class="modal-overlay hidden" id="edit-modal">
  <div class="modal-box">
    <h3>Edit Account</h3>
    <?php if (!empty($errors) && ($_POST['action'] ?? '') === 'update'): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="edit_id" id="edit_id">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="edit_username" id="edit_username" required>
      </div>
      <div class="form-group">
        <label>New Password <span class="optional">(leave blank to keep current)</span></label>
        <div class="password-wrap">
          <input type="password" name="edit_password" id="edit_pass" autocomplete="new-password">
          <button type="button" class="pw-toggle" onclick="togglePw('edit_pass')">👁</button>
        </div>
      </div>
      <div class="form-group">
        <label>Confirm New Password</label>
        <div class="password-wrap">
          <input type="password" name="edit_confirm" id="edit_confirm">
          <button type="button" class="pw-toggle" onclick="togglePw('edit_confirm')">👁</button>
        </div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-outline" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(id, username) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_pass').value = '';
    document.getElementById('edit_confirm').value = '';
    document.getElementById('edit-modal').classList.remove('hidden');
}
function closeEdit() {
    document.getElementById('edit-modal').classList.add('hidden');
}
function togglePw(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
// Close modal on overlay click
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});
</script>
</body>
</html>
