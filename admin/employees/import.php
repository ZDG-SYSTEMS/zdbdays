<?php
// ============================================================
// CSV Import
// ============================================================

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
sessionStart();
requireAdmin($pdo);

// Template download
if (isset($_GET['action']) && $_GET['action'] === 'download-template') {
    outputCSVTemplate();
}

$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $results = ['error' => 'No file uploaded or upload error occurred.'];
    } else {
        $handle   = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $inserted = 0;
        $skipped  = 0;
        $row_errors = [];
        $row_num  = 1;

        // Skip header row
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $row_num++;

            // Trim every cell up front
            $cells = array_map(fn($c) => trim((string)$c), $row);

            // Completely blank rows are skipped silently (Excel exports many)
            if (implode('', $cells) === '') {
                continue;
            }

            // Row has some data but not all six columns — that's incomplete
            if (count($cells) < 6) {
                $row_errors[] = "Row {$row_num}: Incomplete data — skipped.";
                $skipped++;
                continue;
            }

            [$full_name, $birthdate_raw, $gender, $company_code, $branch_name, $position] = $cells;

            if ($full_name === '') {
                $row_errors[] = "Row {$row_num}: Missing full name — skipped.";
                $skipped++;
                continue;
            }

            // Parse dd/mm (tolerant of Excel's date conversions) -> MM-DD
            $birthdate = parseBirthdateInput($birthdate_raw);
            if ($birthdate === null) {
                $row_errors[] = "Row {$row_num}: Invalid date '{$birthdate_raw}' for '{$full_name}'. Expected dd/mm.";
                $skipped++;
                continue;
            }

            // Validate gender
            $gender = strtoupper($gender);
            if (!in_array($gender, ['M', 'F'])) {
                $row_errors[] = "Row {$row_num}: Invalid gender '{$gender}' for '{$full_name}'. Expected M or F.";
                $skipped++;
                continue;
            }

            // Look up company
            $company_code = strtoupper($company_code);
            $stmt = $pdo->prepare("SELECT id FROM companies WHERE short_code = ?");
            $stmt->execute([$company_code]);
            $company = $stmt->fetch();
            if (!$company) {
                $row_errors[] = "Row {$row_num}: Unknown company code '{$company_code}' for '{$full_name}'. Valid: ZDG, ZDL, ZDC, IBS, BR.";
                $skipped++;
                continue;
            }

            // Look up branch
            $stmt = $pdo->prepare("SELECT id FROM branches WHERE company_id = ? AND LOWER(name) = LOWER(?)");
            $stmt->execute([$company['id'], $branch_name]);
            $branch = $stmt->fetch();
            if (!$branch) {
                $row_errors[] = "Row {$row_num}: Unknown branch '{$branch_name}' for company '{$company_code}'.";
                $skipped++;
                continue;
            }

            // Validate position against the company's position list
            if ($position === '') {
                $row_errors[] = "Row {$row_num}: Missing position for '{$full_name}'.";
                $skipped++;
                continue;
            }
            $position = findPositionTitle($pdo, (int)$company['id'], $position);
            if ($position === null) {
                $row_errors[] = "Row {$row_num}: Unknown position for company '{$company_code}'. Check the company's job titles.";
                $skipped++;
                continue;
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO employees (full_name, birthdate, gender, company_id, branch_id, position)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$full_name, $birthdate, $gender, $company['id'], $branch['id'], $position]);
                $inserted++;
            } catch (PDOException $e) {
                error_log('CSV import insert failed (row ' . $row_num . '): ' . $e->getMessage());
                $row_errors[] = "Row {$row_num}: Could not save '{$full_name}' — a database error occurred.";
                $skipped++;
            }
        }
        fclose($handle);

        $results = [
            'inserted'   => $inserted,
            'skipped'    => $skipped,
            'row_errors' => $row_errors,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/assets/img/zdg_logo.jpeg" type="image/jpeg">
<title>Import CSV — ZD Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="admin-main">
  <div class="admin-topbar">
    <h2 class="page-title">Import Employees via CSV</h2>
    <a href="<?= APP_BASE ?>/admin/dashboard.php" class="btn btn-outline"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/></svg></span> Back</a>
  </div>

  <?php if ($results && isset($results['error'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($results['error']) ?></div>
  <?php endif; ?>

  <?php if ($results && !isset($results['error'])): ?>
  <div class="alert alert-<?= $results['inserted'] > 0 ? 'success' : 'warning' ?>">
    <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check2-square" viewBox="0 0 16 16"><path d="M3 14.5A1.5 1.5 0 0 1 1.5 13V3A1.5 1.5 0 0 1 3 1.5h8a.5.5 0 0 1 0 1H3a.5.5 0 0 0-.5.5v10a.5.5 0 0 0 .5.5h10a.5.5 0 0 0 .5-.5V8a.5.5 0 0 1 1 0v5a1.5 1.5 0 0 1-1.5 1.5z"/><path d="m8.354 10.354 7-7a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0"/></svg></span> <strong><?= $results['inserted'] ?></strong> employee<?= $results['inserted'] != 1 ? 's' : '' ?> imported.
    <?php if ($results['skipped']): ?>
      
    <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-circle" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0_0_1-1.1_0"/></svg></span> <strong><?= $results['skipped'] ?></strong> row<?= $results['skipped'] != 1 ? 's' : '' ?> skipped.
    <?php endif; ?>
  </div>
  <?php if (!empty($results['row_errors'])): ?>
  <div class="import-errors">
    <h4>Row Errors</h4>
    <ul>
      <?php foreach ($results['row_errors'] as $err): ?>
      <li><?= htmlspecialchars($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <div class="form-card">
    <div class="import-info">
      <h3>Instructions</h3>
      <ol>
        <li>Download the CSV template below.</li>
        <li>Fill in employee data — <strong>do not change column headers</strong>.</li>
        <li>Save the file as CSV (UTF-8) from Excel or Google Sheets.</li>
        <li>Upload the completed file here.</li>
        <li>Add photos to imported employees from the dashboard.</li>
      </ol>

      <div class="template-box">
        <div>
          <strong>Template Columns</strong>
          <p class="text-mid">Full Name · Birthdate (dd/mm) · Gender (M/F) · Company Code · Branch · Position</p>
          <p class="text-mid"><strong>Company codes:</strong> ZDG · ZDL · ZDC · IBS · BR</p>
        </div>
        <a href="?action=download-template" class="btn btn-outline"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download" viewBox="0 0 16 16"><path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"/><path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708z"/></svg></span> Download Template</a>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <div class="form-group">
        <label for="csv_file">Select CSV File</label>
        <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Import</button>
      </div>
    </form>
  </div>
</main>

</body>
</html>
