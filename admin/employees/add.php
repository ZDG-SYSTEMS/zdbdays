<?php
// ============================================================
// Add Employee
// ============================================================

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
sessionStart();
requireAdmin($pdo);

$companies = $pdo->query("SELECT id, name, short_code FROM companies ORDER BY name")->fetchAll();
$branches  = $pdo->query("SELECT id, company_id, name FROM branches ORDER BY company_id, name")->fetchAll();
$positions = getAllPositions($pdo);
$errors    = [];
$values    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $values = [
        'full_name'       => trim($_POST['full_name']       ?? ''),
        'birthdate_raw'   => trim($_POST['birthdate']        ?? ''),
        'gender'          => trim($_POST['gender']           ?? ''),
        'company_id'      => (int)($_POST['company_id']      ?? 0),
        'branch_id'       => (int)($_POST['branch_id']       ?? 0),
        'position'        => trim($_POST['position']         ?? ''),
        'primary_message' => trim($_POST['primary_message']  ?? ''),
    ];

    // Validate required
    if (!$values['full_name'])   $errors[] = 'Full name is required.';
    if (!$values['birthdate_raw']) $errors[] = 'Birthday is required.';
    if (!in_array($values['gender'], ['M', 'F'])) $errors[] = 'Gender is required.';
    if (!$values['company_id'])  $errors[] = 'Company is required.';
    if (!$values['branch_id'])   $errors[] = 'Branch is required.';
    if (!$values['position'])    $errors[] = 'Position is required.';

    // Validate position belongs to the selected company (store canonical title)
    if ($values['position'] && $values['company_id']) {
        $canonical = findPositionTitle($pdo, $values['company_id'], $values['position']);
        if ($canonical === null) {
            $errors[] = 'Selected position does not belong to selected company.';
        } else {
            $values['position'] = $canonical;
        }
    }

    // Birthday dropdown submits MM-DD directly (no year)
    $birthdate = '';
    if ($values['birthdate_raw']) {
        if (preg_match('/^(\d{2})-(\d{2})$/', $values['birthdate_raw'], $m)
            && checkdate((int)$m[1], (int)$m[2], 2024)) {
            $birthdate = $values['birthdate_raw'];
        } else {
            $errors[] = 'Invalid date.';
        }
    }

    // Validate branch belongs to company
    if ($values['branch_id'] && $values['company_id']) {
        $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND company_id = ?");
        $stmt->execute([$values['branch_id'], $values['company_id']]);
        if (!$stmt->fetch()) $errors[] = 'Selected branch does not belong to selected company.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO employees (full_name, birthdate, gender, company_id, branch_id, position, primary_message)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $values['full_name'], $birthdate, $values['gender'],
            $values['company_id'], $values['branch_id'], $values['position'],
            $values['primary_message'] ?: null
        ]);
        $new_id = (int)$pdo->lastInsertId();

        // Handle single image upload (one photo per employee)
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            setEmployeeImage($pdo, $new_id, $_FILES['image']);
        }

        setFlash('success', htmlspecialchars($values['full_name']) . ' has been added successfully.');
        header('Location: ' . APP_BASE . '/admin/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/assets/img/zdg_logo.jpeg" type="image/jpeg">
<title>Add Employee — ZD Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="admin-main">
  <div class="admin-topbar">
    <h2 class="page-title">Add Employee</h2>
    <a href="<?= APP_BASE ?>/admin/dashboard.php" class="btn btn-outline"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/></svg></span> Back</a>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="form-card">
    <form method="POST" enctype="multipart/form-data" novalidate>
      <?= csrfField() ?>

      <div class="form-row">
        <div class="form-group">
          <label for="full_name">Full Name <span class="required">*</span></label>
          <input type="text" id="full_name" name="full_name"
                 value="<?= sanitize($values['full_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="birthdate-display">Birthday <span class="required">*</span></label>
          <div class="bday-picker" data-value="<?= sanitize($values['birthdate_raw'] ?? '') ?>">
            <input type="hidden" name="birthdate" value="<?= sanitize($values['birthdate_raw'] ?? '') ?>">
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
            <option value="M" <?= ($values['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Male</option>
            <option value="F" <?= ($values['gender'] ?? '') === 'F' ? 'selected' : '' ?>>Female</option>
          </select>
        </div>
        <div class="form-group">
          <label for="company_id">Company <span class="required">*</span></label>
          <select id="company_id" name="company_id" required onchange="filterBranches(this.value); filterPositions(this.value)">
            <option value="">Select company</option>
            <?php foreach ($companies as $c): ?>
            <option value="<?= $c['id'] ?>"
              <?= ($values['company_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?> (<?= $c['short_code'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="branch_id">Branch <span class="required">*</span></label>
          <select id="branch_id" name="branch_id" required>
            <option value="">Select company first</option>
            <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>"
                    data-company="<?= $b['company_id'] ?>"
                    <?= ($values['branch_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="position">Position <span class="required">*</span></label>
          <select id="position" name="position" required>
            <option value="">Select company first</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="primary_message">Birthday Message <span class="optional">(optional — can be added later)</span></label>
        <textarea id="primary_message" name="primary_message" rows="3"
                  placeholder="e.g. Wishing you a day as wonderful as you are!"><?= sanitize($values['primary_message'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label>Add Image <span class="optional">(one per employee)</span></label>
        <div class="upload-area" id="upload-area">
          <input type="file" id="image" name="image" accept="image/*"
                 onchange="previewImages(this)">
          <label for="image" class="upload-label">
            <span class="upload-icon"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-camera" viewBox="0 0 16 16"><path d="M15 12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h1.172a3 3 0 0 0 2.12-.879l.83-.828A1 1 0 0 1 6.827 3h2.344a1 1 0 0 1 .707.293l.828.828A3 3 0 0 0 12.828 5H14a1 1 0 0 1 1 1zM2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4z"/><path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M3 6.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/>
           </svg></span></span>
            <span>Click to select a photo, or drag and drop</span>
            <small>JPG, PNG, GIF, WEBP — max 5 MB</small>
          </label>
        </div>
        <div class="image-previews" id="image-previews"></div>
      </div>

      <div class="form-actions">
        <a href="<?= APP_BASE ?>/admin/dashboard.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Employee</button>
      </div>
    </form>
  </div>
</main>

<script src="/assets/js/admin.js"></script>
<script>
const branchData   = <?= json_encode($branches) ?>;
const positionData = <?= json_encode($positions) ?>;
const currentPosition = <?= json_encode($values['position'] ?? '') ?>;

function filterBranches(companyId) {
    const sel = document.getElementById('branch_id');
    sel.innerHTML = '<option value="">Select branch</option>';
    branchData.filter(b => !companyId || b.company_id == companyId).forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id;
        opt.textContent = b.name;
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

// Init if company pre-selected (after error)
const preSelected = document.getElementById('company_id').value;
if (preSelected) { filterBranches(preSelected); filterPositions(preSelected); }
</script>
</body>
</html>
