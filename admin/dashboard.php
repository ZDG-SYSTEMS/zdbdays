<?php
// ============================================================
// Admin Dashboard
// ============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessionStart();
requireAdmin($pdo);

$flash     = getFlash();
$total     = countTotalEmployees($pdo);
$passed    = countBirthdaysPassed($pdo);
$remaining = countBirthdaysRemaining($pdo);
$noImage   = countMissingImages($pdo);
$noMsg     = countMissingMessages($pdo);
$due48     = getBirthdays48hrs($pdo);

// All companies and branches for filter dropdowns
$companies = $pdo->query("SELECT id, name, short_code FROM companies ORDER BY name")->fetchAll();
$branches  = $pdo->query("SELECT id, company_id, name FROM branches ORDER BY name")->fetchAll();

$months = [
    '01'=>'January','02'=>'February','03'=>'March','04'=>'April',
    '05'=>'May','06'=>'June','07'=>'July','08'=>'August',
    '09'=>'September','10'=>'October','11'=>'November','12'=>'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — ZD Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/admin.css">
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
</head>
<body class="admin-body">

<?php include __DIR__ . '/partials/nav.php'; ?>

<main class="admin-main">
  <div class="admin-topbar">
    <h2 class="page-title">Dashboard</h2>
    <a href="<?= APP_BASE ?>/admin/employees/add.php" class="btn btn-primary">+ Add Employee</a>
  </div>

  <?php renderFlash($flash); ?>

  <!-- Stats Grid -->
  <div class="stats-grid">
    <!-- Total Employees -->
    <div class="stat-card">
      <div class="stat-icon"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16"><path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/></svg></span></div>
      <div class="stat-info">
        <span class="stat-value"><?= $total ?></span>
        <span class="stat-label">Total Employees</span>
      </div>
    </div>

    <!-- Birthdays Passed -->
    <div class="stat-card">
      <div class="stat-icon"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check2-square" viewBox="0 0 16 16"><path d="M3 14.5A1.5 1.5 0 0 1 1.5 13V3A1.5 1.5 0 0 1 3 1.5h8a.5.5 0 0 1 0 1H3a.5.5 0 0 0-.5.5v10a.5.5 0 0 0 .5.5h10a.5.5 0 0 0 .5-.5V8a.5.5 0 0 1 1 0v5a1.5 1.5 0 0 1-1.5 1.5z"/><path d="m8.354 10.354 7-7a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0"/></svg></span></div>
      <div class="stat-info">
        <span class="stat-value"><?= $passed ?></span>
        <span class="stat-label">Birthdays Passed</span>
      </div>
    </div>

    <!-- Birthdays Remaining -->
    <div class="stat-card">
      <div class="stat-icon"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-list" viewBox="0 0 16 16"><path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2z"/><path d="M5 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 5 8m0-2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m0 5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m-1-5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0M4 8a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0m0 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/></svg></span></div>
      <div class="stat-info">
        <span class="stat-value"><?= $remaining ?></span>
        <span class="stat-label">Birthdays Remaining</span>
      </div>
    </div>
    
    <!-- Missing Images -->
    <div class="stat-card <?= $noImage > 0 ? 'stat-warn' : '' ?>">
      <div class="stat-icon"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-slash" viewBox="0 0 16 16"><path d="M13.879 10.414a2.501 2.501 0 0 0-3.465 3.465zm.707.707-3.465 3.465a2.501 2.501 0 0 0 3.465-3.465m-4.56-1.096a3.5 3.5 0 1 1 4.949 4.95 3.5 3.5 0 0 1-4.95-4.95ZM11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0M8 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m.256 7a4.5 4.5 0 0 1-.229-1.004H3c.001-.246.154-.986.832-1.664C4.484 10.68 5.711 10 8 10q.39 0 .74.025c.226-.341.496-.65.804-.918Q8.844 9.002 8 9c-5 0-6 3-6 4s1 1 1 1z"/></svg></span></div>
      <div class="stat-info">
        <span class="stat-value"><?= $noImage ?></span>
        <span class="stat-label">Missing Images</span>
      </div>
    </div>

    <!-- Missing Messages -->
    <div class="stat-card <?= $noMsg > 0 ? 'stat-warn' : '' ?>">
      <div class="stat-icon"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope-slash" viewBox="0 0 16 16"><path d="M2 2a2 2 0 0 0-2 2v8.01A2 2 0 0 0 2 14h5.5a.5.5 0 0 0 0-1H2a1 1 0 0 1-.966-.741l5.64-3.471L8 9.583l7-4.2V8.5a.5.5 0 0 0 1 0V4a2 2 0 0 0-2-2zm3.708 6.208L1 11.105V5.383zM1 4.217V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v.217l-7 4.2z"/><path d="M14.975 10.025a3.5 3.5 0 1 0-4.95 4.95 3.5 3.5 0 0 0 4.95-4.95m-4.243.707a2.5 2.5 0 0 1 3.147-.318l-3.465 3.465a2.5 2.5 0 0 1 .318-3.147m.39 3.854 3.464-3.465a2.501 2.501 0 0 1-3.465 3.465Z"/></svg></span></div>
      <div class="stat-info">
        <span class="stat-value"><?= $noMsg ?></span>
        <span class="stat-label">Missing Messages</span>
      </div>
    </div>
  </div>

  <!-- 48hr Reminders -->
  <?php if (!empty($due48)): ?>
  <div class="reminder-block">
    <button class="reminder-toggle" onclick="this.nextElementSibling.classList.toggle('hidden')">
      <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-circle" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/></svg></span> <?= count($due48) ?> birthday<?= count($due48) > 1 ? 's' : '' ?> is due soon! — click to review
    </button>
    <div class="reminder-list hidden">
      <?php foreach ($due48 as $emp): ?>
      <?php
        $hasImg = (int)$emp['image_count'] > 0;
        $hasMsg = !empty(trim($emp['primary_message'] ?? ''));
      ?>
      <div class="reminder-item">
        <img src="<?= htmlspecialchars(getPrimaryImageUrl($pdo, $emp['id'], $emp['gender'], $emp['primary_image'] ?? null)) ?>"
             alt="<?= htmlspecialchars($emp['full_name']) ?>"
             class="reminder-avatar">
        <div class="reminder-info">
          <strong><?= htmlspecialchars($emp['full_name']) ?></strong>
          <span class="text-mid"><?= formatBirthdate($emp['birthdate']) ?> · <?= htmlspecialchars($emp['company_name']) ?> · <?= htmlspecialchars($emp['branch_name']) ?></span>
          <div class="reminder-flags">
            <?php if (!$hasImg): ?><span class="flag flag-warn">No image</span><?php endif; ?>
            <?php if (!$hasMsg): ?><span class="flag flag-warn">No message</span><?php endif; ?>
            <?php if ($hasImg && $hasMsg): ?><span class="flag flag-ok">Ready</span><?php endif; ?>
          </div>
        </div>
        <a href="<?= APP_BASE ?>/admin/employees/edit.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Employee List -->
  <div class="section-header">
    <h3>All Employees</h3>
    <span class="text-mid"><?= $total ?> records</span>
  </div>

  <!-- Filters -->
  <div class="filter-bar"
       hx-get="<?= APP_BASE ?>/admin/employees/list-partial.php"
       hx-trigger="change from:select"
       hx-target="#employee-table-body"
       hx-include="[name='filter_company'],[name='filter_branch'],[name='filter_month']">

    <select name="filter_company" id="filter_company" onchange="syncBranches(this.value)">
      <option value="">All Companies</option>
      <?php foreach ($companies as $c): ?>
      <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="filter_branch" id="filter_branch">
      <option value="">All Branches</option>
      <?php foreach ($branches as $b): ?>
      <option value="<?= $b['id'] ?>" data-company="<?= $b['company_id'] ?>">
        <?= htmlspecialchars($b['name']) ?>
      </option>
      <?php endforeach; ?>
    </select>

    <select name="filter_month">
      <option value="">All Months</option>
      <?php foreach ($months as $num => $name): ?>
      <option value="<?= $num ?>"><?= $name ?></option>
      <?php endforeach; ?>
    </select>

    <button class="btn btn-sm btn-outline" onclick="clearFilters()">Clear</button>
  </div>

  <!-- Employee Table -->
  <div class="table-wrap">
    <table class="emp-table">
      <thead>
        <tr>
          <th></th>
          <th>Name</th>
          <th>Birthday</th>
          <th>Company</th>
          <th>Branch</th>
          <th>Position</th>
          <th class="th-actions">Actions</th>
        </tr>
      </thead>
      <tbody id="employee-table-body">
        <?php include __DIR__ . '/employees/list-partial.php'; ?>
      </tbody>
    </table>
  </div>
</main>

<script src="<?= APP_BASE ?>/assets/js/admin.js"></script>
<script>
// Sync branch dropdown to selected company
const allBranches = <?= json_encode($branches) ?>;

function syncBranches(companyId) {
    const sel = document.getElementById('filter_branch');
    const opts = sel.querySelectorAll('option');
    opts.forEach(opt => {
        if (!opt.value) return;
        opt.style.display = (!companyId || opt.dataset.company === companyId) ? '' : 'none';
    });
    sel.value = '';
}

function clearFilters() {
    document.querySelectorAll('.filter-bar select').forEach(s => s.value = '');
    syncBranches('');
    htmx.trigger('.filter-bar', 'change');
}
</script>
</body>
</html>
