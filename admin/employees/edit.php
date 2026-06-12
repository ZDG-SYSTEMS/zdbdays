<?php
// ============================================================
// Edit Employee  (with pessimistic record locking)
// ============================================================

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
sessionStart();
requireAdmin($pdo);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) { header('Location: ' . APP_BASE . '/admin/dashboard.php'); exit; }

$admin_id = getAdminId();

// Fetch employee
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();
if (!$emp) { setFlash('danger', 'Employee not found.'); header('Location: ' . APP_BASE . '/admin/dashboard.php'); exit; }

// Lock check on GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!lockEmployee($pdo, $id, $admin_id)) {
        $holder = getLockHolder($pdo, $id);
        setFlash('danger', 'This record is currently being edited by ' . htmlspecialchars($holder['username'] ?? 'another admin') . '. Please try again shortly.');
        header('Location: ' . APP_BASE . '/admin/dashboard.php');
        exit;
    }
}

$companies = $pdo->query("SELECT id, name, short_code FROM companies ORDER BY name")->fetchAll();
$branches  = $pdo->query("SELECT id, company_id, name FROM branches ORDER BY company_id, name")->fetchAll();
$positions = getAllPositions($pdo);
$images    = getEmployeeImages($pdo, $id);
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    // Verify lock still belongs to this admin
    $stmt = $pdo->prepare("SELECT locked_by FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $lockRow = $stmt->fetch();
    if (!$lockRow || (int)$lockRow['locked_by'] !== $admin_id) {
        setFlash('danger', 'Your edit session expired. Please try again.');
        header('Location: ' . APP_BASE . '/admin/dashboard.php');
        exit;
    }

    $full_name       = trim($_POST['full_name']       ?? '');
    $birthdate_raw   = trim($_POST['birthdate']        ?? '');
    $gender          = trim($_POST['gender']           ?? '');
    $company_id      = (int)($_POST['company_id']      ?? 0);
    $branch_id       = (int)($_POST['branch_id']       ?? 0);
    $position        = trim($_POST['position']         ?? '');
    $primary_message = trim($_POST['primary_message']  ?? '');

    if (!$full_name)   $errors[] = 'Full name is required.';
    if (!$birthdate_raw) $errors[] = 'Birthday is required.';
    if (!in_array($gender, ['M', 'F'])) $errors[] = 'Gender is required.';
    if (!$company_id)  $errors[] = 'Company is required.';
    if (!$branch_id)   $errors[] = 'Branch is required.';
    if (!$position)    $errors[] = 'Position is required.';

    // Validate position belongs to the selected company (store canonical title)
    if ($position && $company_id) {
        $canonical = findPositionTitle($pdo, $company_id, $position);
        if ($canonical === null) {
            $errors[] = 'Selected position does not belong to selected company.';
        } else {
            $position = $canonical;
        }
    }

    // Birthday dropdown submits MM-DD directly (no year)
    $birthdate = '';
    if ($birthdate_raw) {
        if (preg_match('/^(\d{2})-(\d{2})$/', $birthdate_raw, $m)
            && checkdate((int)$m[1], (int)$m[2], 2024)) {
            $birthdate = $birthdate_raw;
        } else {
            $errors[] = 'Invalid date.';
        }
    }

    // Handle photo removal (one photo per employee)
    if (!empty($_POST['remove_image'])) {
        foreach ($images as $img) {
            deleteEmployeeImage($pdo, (int)$img['id'], $id);
        }
        $images = getEmployeeImages($pdo, $id);
    }

    // Handle new image upload — replaces any existing photo
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        setEmployeeImage($pdo, $id, $_FILES['image']);
        $images = getEmployeeImages($pdo, $id);
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE employees
            SET full_name = ?, birthdate = ?, gender = ?, company_id = ?, branch_id = ?,
                position = ?, primary_message = ?, locked_by = NULL, locked_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$full_name, $birthdate, $gender, $company_id, $branch_id,
                        $position, $primary_message ?: null, $id]);

        setFlash('success', htmlspecialchars($full_name) . ' updated successfully.');
        header('Location: ' . APP_BASE . '/admin/dashboard.php');
        exit;
    }
}

// Currently selected MM-DD for the birthday dropdown
$bdInput = $_POST['birthdate'] ?? $emp['birthdate'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Employee — ZD Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/zdbdays/assets/css/admin.css">
</head>
<body class="admin-body"
      data-lock-id="<?= $id ?>"
      data-lock-timeout="<?= LOCK_TIMEOUT ?>">

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="admin-main">
  <div class="admin-topbar">
    <h2 class="page-title">Edit Employee</h2>
    <a href="<?= APP_BASE ?>/admin/dashboard.php" class="btn btn-outline" id="cancel-edit"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/></svg></span> Cancel</a>
  </div>

  <div class="lock-notice" id="lock-notice">
    <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 0a4 4 0 0 1 4 4v2.05a2.5 2.5 0 0 1 2 2.45v5a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 2 13.5v-5a2.5 2.5 0 0 1 2-2.45V4a4 4 0 0 1 4-4M4.5 7A1.5 1.5 0 0 0 3 8.5v5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 11.5 7zM8 1a3 3 0 0 0-3 3v2h6V4a3 3 0 0 0-3-3"/></svg></span> You are editing this record. It is locked for other admins.

    <span class="inactivity-warning hidden" id="inactivity-warning">
      <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-circle" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/></svg></span> This page has been inactive for <span id="inactive-secs">0</span>s — lock will release in <span id="release-secs">?</span>s
    </span>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="form-card">
    <form method="POST" enctype="multipart/form-data" id="edit-form" novalidate>
      <?= csrfField() ?>
      <input type="hidden" name="id" value="<?= $id ?>">

      <div class="form-row">
        <div class="form-group">
          <label for="full_name">Full Name <span class="required">*</span></label>
          <input type="text" id="full_name" name="full_name"
                 value="<?= sanitize($_POST['full_name'] ?? $emp['full_name']) ?>" required>
        </div>
        <div class="form-group">
          <label for="birthdate-display">Birthday <span class="required">*</span></label>
          <div class="bday-picker" data-value="<?= sanitize($bdInput) ?>">
            <input type="hidden" name="birthdate" value="<?= sanitize($bdInput) ?>">
            <button type="button" id="birthdate-display" class="bday-display">
              <span class="bday-display-text">Select month and day</span>
              <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-event" viewBox="0 0 16 16"><path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5z"/><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/></svg></span>
            </button>
          </div>
          <small class="form-hint">Day and month only.</small>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="gender">Gender <span class="required">*</span></label>
          <select id="gender" name="gender" required>
            <option value="">Select gender</option>
            <option value="M" <?= ($_POST['gender'] ?? $emp['gender']) === 'M' ? 'selected' : '' ?>>Male</option>
            <option value="F" <?= ($_POST['gender'] ?? $emp['gender']) === 'F' ? 'selected' : '' ?>>Female</option>
          </select>
        </div>
        <div class="form-group">
          <label for="company_id">Company <span class="required">*</span></label>
          <select id="company_id" name="company_id" required onchange="filterBranches(this.value); filterPositions(this.value)">
            <option value="">Select company</option>
            <?php foreach ($companies as $c): ?>
            <option value="<?= $c['id'] ?>"
              <?= ($_POST['company_id'] ?? $emp['company_id']) == $c['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?> (<?= $c['short_code'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="branch_id">Branch <span class="required">*</span></label>
          <select id="branch_id" name="branch_id" required>
            <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>"
                    data-company="<?= $b['company_id'] ?>"
                    <?= ($_POST['branch_id'] ?? $emp['branch_id']) == $b['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="position">Position <span class="required">*</span></label>
          <select id="position" name="position" required>
            <option value="">Select position</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="primary_message">Birthday Message <span class="optional">(shown on birthday banner)</span></label>
        <textarea id="primary_message" name="primary_message" rows="3"
                  placeholder="e.g. Wishing you a day as wonderful as you are!"><?= sanitize($_POST['primary_message'] ?? $emp['primary_message'] ?? '') ?></textarea>
      </div>

      <!-- Current Photo -->
      <?php if (!empty($images)): ?>
      <div class="form-group">
        <label>Current Photo</label>
        <div class="existing-images">
          <div class="existing-img-wrap" id="current-photo-wrap">
            <img src="<?= UPLOAD_URL ?>/employees/<?= $id ?>/<?= rawurlencode($images[0]['image_path']) ?>"
                 alt="Current photo">
            <input type="checkbox" name="remove_image" value="1" id="remove_image" class="hidden">
            <button type="button" class="btn-remove-photo" id="remove-photo-btn"
                    onclick="toggleRemovePhoto()">Remove</button>
          </div>
        </div>
        <small class="form-hint hidden" id="remove-photo-hint">Photo will be removed when you save.</small>
      </div>
      <?php endif; ?>

      <!-- Photo Upload — replaces the current photo -->
      <div class="form-group">
        <label><?= !empty($images) ? 'Replace Photo' : 'Add Photo' ?></label>         
        <div class="upload-area">
          <input type="file" id="image" name="image" accept="image/*"
                 onchange="previewImages(this)">
          <label for="image" class="upload-label">
            <span class="upload-icon"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-camera" viewBox="0 0 16 16"><path d="M15 12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h1.172a3 3 0 0 0 2.12-.879l.83-.828A1 1 0 0 1 6.827 3h2.344a1 1 0 0 1 .707.293l.828.828A3 3 0 0 0 12.828 5H14a1 1 0 0 1 1 1zM2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4z"/><path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M3 6.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/>
           </svg></span></span>
            <span>Click to select a photo</span>
          </label>
        </div>
        <div class="image-previews" id="image-previews"></div>
      </div>

      <div class="form-actions">
        <a href="<?= APP_BASE ?>/admin/dashboard.php" class="btn btn-outline" id="cancel-btn">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</main>

<script src="/zdbdays/assets/js/admin.js"></script>
<script>
const branchData   = <?= json_encode($branches) ?>;
const positionData = <?= json_encode($positions) ?>;
const currentPosition = <?= json_encode($_POST['position'] ?? $emp['position'] ?? '') ?>;

function filterBranches(companyId) {
    const sel = document.getElementById('branch_id');
    const current = sel.value;
    sel.innerHTML = '';
    branchData.filter(b => !companyId || b.company_id == companyId).forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id;
        opt.dataset.company = b.company_id;
        opt.textContent = b.name;
        if (b.id == current) opt.selected = true;
        sel.appendChild(opt);
    });
}

function filterPositions(companyId) {
    const sel = document.getElementById('position');
    sel.innerHTML = '<option value="">' + (companyId ? 'Select position' : 'Select company first') + '</option>';
    positionData.filter(p => !companyId || p.company_id == companyId).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.title;
        opt.textContent = p.title;
        if (p.title === currentPosition) opt.selected = true;
        sel.appendChild(opt);
    });
}

// Init branch + position filters
filterBranches(document.getElementById('company_id').value);
filterPositions(document.getElementById('company_id').value);

// Cancel — release lock then navigate
document.getElementById('cancel-btn').addEventListener('click', function(e) {
    e.preventDefault();
    navigator.sendBeacon('<?= APP_BASE ?>/api/lock.php', JSON.stringify({action:'release',id:<?= $id ?>}));
    window.location.href = '<?= APP_BASE ?>/admin/dashboard.php';
});

// Toggle "remove current photo" — marks the photo for deletion on save
function toggleRemovePhoto() {
    const cb   = document.getElementById('remove_image');
    const wrap = document.getElementById('current-photo-wrap');
    const btn  = document.getElementById('remove-photo-btn');
    const hint = document.getElementById('remove-photo-hint');
    cb.checked = !cb.checked;
    wrap.classList.toggle('marked-remove', cb.checked);
    btn.textContent = cb.checked ? 'Undo' : 'Remove';
    hint.classList.toggle('hidden', !cb.checked);
}
</script>
</body>
</html>
