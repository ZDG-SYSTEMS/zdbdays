<?php
// ============================================================
// Admin Settings
// ============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessionStart();
requireAdmin($pdo);

$flash = getFlash();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    // --- Captions ---
    if ($action === 'save_captions') {
        // Update existing
        $existing_ids  = $_POST['caption_id']   ?? [];
        $existing_text = $_POST['caption_text']  ?? [];
        $existing_act  = $_POST['caption_active'] ?? [];

        foreach ($existing_ids as $i => $cid) {
            $text   = trim($existing_text[$i] ?? '');
            $active = isset($existing_act[$cid]) ? 1 : 0;
            if ($text) {
                $pdo->prepare("UPDATE countdown_captions SET caption_text = ?, is_active = ? WHERE id = ?")->execute([$text, $active, $cid]);
            } else {
                $pdo->prepare("DELETE FROM countdown_captions WHERE id = ?")->execute([$cid]);
            }
        }
        // Add new
        $new_captions = array_filter(array_map('trim', $_POST['new_captions'] ?? []));
        foreach ($new_captions as $caption) {
            $pdo->prepare("INSERT INTO countdown_captions (caption_text) VALUES (?)")->execute([$caption]);
        }
        setFlash('success', 'Captions saved.');
        header('Location: ' . APP_BASE . '/admin/settings.php');
        exit;
    }

    // --- Default Images ---
    if ($action === 'save_defaults') {
        $defaults = [
            'fallback_image_male'   => 'fallback_male',
            'fallback_image_female' => 'fallback_female',
            'no_birthday_image'     => 'no_birthday',
        ];
        foreach ($defaults as $key => $name) {
            if (!empty($_FILES[$name]['tmp_name']) && $_FILES[$name]['error'] === UPLOAD_ERR_OK) {
                // Delete old file
                $old = getSetting($pdo, $key);
                if ($old) {
                    $oldPath = UPLOAD_PATH . '/defaults/' . $old;
                    if (file_exists($oldPath)) unlink($oldPath);
                }
                $filename = handleDefaultImageUpload($_FILES[$name], $name);
                if ($filename) {
                    setSetting($pdo, $key, $filename);
                } else {
                    $errors[] = "Failed to upload image for '{$name}'.";
                }
            }
        }
        if (empty($errors)) {
            setFlash('success', 'Default images updated.');
            header('Location: ' . APP_BASE . '/admin/settings.php');
            exit;
        }
    }
}

$captions  = $pdo->query("SELECT * FROM countdown_captions ORDER BY id")->fetchAll();

$fallbackMale   = getSetting($pdo, 'fallback_image_male');
$fallbackFemale = getSetting($pdo, 'fallback_image_female');
$noBirthdayImg  = getSetting($pdo, 'no_birthday_image');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings — ZD Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/partials/nav.php'; ?>

<main class="admin-main">
  <div class="admin-topbar">
    <h2 class="page-title">Settings</h2>
  </div>

  <?php renderFlash($flash); ?>
  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <!-- Countdown Captions -->
  <div class="settings-section">
    <h3>Countdown Captions</h3>
    <p class="text-mid">These rotate on the "birthday tomorrow" banner. Leave a text field empty to delete that caption.</p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_captions">
      <div class="captions-list" id="captions-list">
        <?php foreach ($captions as $cap): ?>
        <div class="caption-row">
          <input type="hidden" name="caption_id[]" value="<?= $cap['id'] ?>">
          <input type="text" name="caption_text[]"
                 value="<?= htmlspecialchars($cap['caption_text']) ?>"
                 placeholder="Caption text" class="caption-input">
          <label class="toggle-label">
            <input type="checkbox" name="caption_active[<?= $cap['id'] ?>]"
                   <?= $cap['is_active'] ? 'checked' : '' ?>>
            Active
          </label>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="new-captions" id="new-captions"></div>
      <button type="button" class="btn btn-sm btn-outline" onclick="addCaption()">+ Add Caption</button>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Captions</button>
      </div>
    </form>
  </div>

  <!-- Default Images -->
  <div class="settings-section">
    <h3>Default Images</h3>
    <p class="text-mid">Shown when an employee has no photo uploaded. The "No Birthday" image shows on the main banner when no one is celebrating today or tomorrow.</p>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_defaults">
      <div class="defaults-grid">
        <div class="default-img-item">
          <label>Male Fallback</label>
          <?php if ($fallbackMale): ?>
          <img src="<?= UPLOAD_URL ?>/defaults/<?= rawurlencode($fallbackMale) ?>" class="settings-preview" alt="Male fallback">
          <?php else: ?><div class="no-preview">Not set</div><?php endif; ?>
          <input type="file" name="fallback_male" accept="image/*">
        </div>
        <div class="default-img-item">
          <label>Female Fallback</label>
          <?php if ($fallbackFemale): ?>
          <img src="<?= UPLOAD_URL ?>/defaults/<?= rawurlencode($fallbackFemale) ?>" class="settings-preview" alt="Female fallback">
          <?php else: ?><div class="no-preview">Not set</div><?php endif; ?>
          <input type="file" name="fallback_female" accept="image/*">
        </div>
        <div class="default-img-item">
          <label>No Birthday Image <span class="optional">(animated GIF supported)</span></label>
          <?php if ($noBirthdayImg): ?>
          <img src="<?= UPLOAD_URL ?>/defaults/<?= rawurlencode($noBirthdayImg) ?>" class="settings-preview" alt="No birthday">
          <?php else: ?><div class="no-preview">Not set — using placeholder</div><?php endif; ?>
          <input type="file" name="no_birthday" accept="image/*,image/gif">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Images</button>
      </div>
    </form>
  </div>

</main>

<script>
function addCaption() {
    const container = document.getElementById('new-captions');
    const div = document.createElement('div');
    div.className = 'caption-row';
    div.innerHTML = '<input type="text" name="new_captions[]" placeholder="New caption..." class="caption-input"><button type="button" onclick="this.parentElement.remove()">×</button>';
    container.appendChild(div);
}
</script>
</body>
</html>
