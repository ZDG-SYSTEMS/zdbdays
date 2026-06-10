<?php
// ============================================================
// Employee List Partial — returned for both initial load & HTMX
// ============================================================

// When called standalone via HTMX, need full bootstrap
if (!isset($pdo)) {
    require_once __DIR__ . '/../../includes/db.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_once __DIR__ . '/../../includes/functions.php';
    sessionStart();
    requireAdmin($pdo);
}

$fc = (int)($_GET['filter_company'] ?? $_POST['filter_company'] ?? 0);
$fb = (int)($_GET['filter_branch']  ?? $_POST['filter_branch']  ?? 0);
$fm =       $_GET['filter_month']   ?? $_POST['filter_month']   ?? '';

$where  = ['1=1'];
$params = [];

if ($fc) { $where[] = 'e.company_id = ?'; $params[] = $fc; }
if ($fb) { $where[] = 'e.branch_id = ?';  $params[] = $fb; }
if ($fm) { $where[] = "LEFT(e.birthdate, 2) = ?"; $params[] = str_pad((int)$fm, 2, '0', STR_PAD_LEFT); }

$sql = "
    SELECT e.*, c.name AS company_name, c.short_code, b.name AS branch_name,
           (SELECT ei.image_path FROM employee_images ei
            WHERE ei.employee_id = e.id ORDER BY ei.sort_order ASC LIMIT 1) AS primary_image
    FROM employees e
    JOIN companies c ON e.company_id = c.id
    JOIN branches  b ON e.branch_id  = b.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$admin_id = getAdminId();

foreach ($employees as $emp):
    $hasImg  = (int)$emp['image_count'] > 0;
    $hasMsg  = !empty(trim($emp['primary_message'] ?? ''));
    $imgUrl  = getPrimaryImageUrl($pdo, $emp['id'], $emp['gender'], $emp['primary_image'] ?? null);
    $locked  = isLockedByOther($pdo, $emp['id'], $admin_id);
    $lockHolder = $locked ? getLockHolder($pdo, $emp['id']) : null;
?>
<tr>
  <td class="td-avatar">
    <img src="<?= htmlspecialchars($imgUrl) ?>"
         alt="<?= htmlspecialchars($emp['full_name']) ?>"
         class="table-avatar">
  </td>
  <td class="td-name">
    <?= htmlspecialchars($emp['full_name']) ?>
    <?php if (!$hasImg): ?><span class="flag flag-warn" title="No image uploaded">!</span><?php endif; ?>
    <?php if (!$hasMsg): ?><span class="flag flag-info" title="No birthday message">msg</span><?php endif; ?>
  </td>
  <td><?= htmlspecialchars(formatBirthdate($emp['birthdate'])) ?></td>
  <td><?= htmlspecialchars($emp['short_code']) ?></td>
  <td><?= htmlspecialchars($emp['branch_name']) ?></td>
  <td>
    <?= $emp['position'] ? htmlspecialchars($emp['position']) : '<span class="text-lt">—</span>' ?>
    <?php if ($locked): ?>
      <span class="flag flag-lock" title="Being edited by <?= htmlspecialchars($lockHolder['username'] ?? '') ?>"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 0a4 4 0 0 1 4 4v2.05a2.5 2.5 0 0 1 2 2.45v5a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 2 13.5v-5a2.5 2.5 0 0 1 2-2.45V4a4 4 0 0 1 4-4M4.5 7A1.5 1.5 0 0 0 3 8.5v5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 11.5 7zM8 1a3 3 0 0 0-3 3v2h6V4a3 3 0 0 0-3-3"/></svg></span></span>
    <?php endif; ?>
  </td>
  <td class="td-actions">
    <div class="action-group">
    <?php if ($locked): ?>
      <button class="btn-icon" disabled title="Being edited by another admin"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/></svg></button>
    <?php else: ?>
      <a href="<?= APP_BASE ?>/admin/employees/edit.php?id=<?= $emp['id'] ?>" class="btn-icon" title="Edit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/></svg></a>
    <?php endif; ?>
    <form method="POST" action="<?= APP_BASE ?>/admin/employees/delete.php"
          class="js-confirm" style="display:inline-flex"
          data-confirm="Delete <?= htmlspecialchars($emp['full_name']) ?>? All their photos and birthday wishes will also be removed. This cannot be undone.">
      <?= csrfField() ?>
      <input type="hidden" name="id" value="<?= $emp['id'] ?>">
      <button type="submit" class="btn-icon btn-icon-danger" title="Delete"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16"><path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47M8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5"/></svg></button>
    </form>
    </div>
  </td>
</tr>
<?php endforeach; ?>

<?php if (empty($employees)): ?>
<tr><td colspan="7" class="td-empty">No employees found matching the selected filters.</td></tr>
<?php endif; ?>
