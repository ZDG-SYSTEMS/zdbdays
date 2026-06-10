<?php
// ============================================================
// Birthday Lineup Partial (HTMX)
// ============================================================

if (!isset($pdo)) {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/functions.php';
}

$month     = str_pad((int)($_GET['month'] ?? appCurrentMonth()), 2, '0', STR_PAD_LEFT);
$birthdays = getMonthBirthdays($pdo, $month);
$monthName = DateTime::createFromFormat('m', $month)->format('F');

$prevMonth = str_pad((int)$month - 1 < 1  ? 12 : (int)$month - 1, 2, '0', STR_PAD_LEFT);
$nextMonth = str_pad((int)$month + 1 > 12 ?  1 : (int)$month + 1, 2, '0', STR_PAD_LEFT);
?>
<div class="lineup-header">
  <button class="lineup-nav"
          hx-get="<?= APP_BASE ?>/partials/lineup.php?month=<?= $prevMonth ?>"
          hx-target="#lineup-container"
          hx-swap="innerHTML">‹</button>
  <h3 class="lineup-month"><?= $monthName ?></h3>
  <button class="lineup-nav"
          hx-get="<?= APP_BASE ?>/partials/lineup.php?month=<?= $nextMonth ?>"
          hx-target="#lineup-container"
          hx-swap="innerHTML">›</button>
</div>

<?php if (empty($birthdays)): ?>
<div class="lineup-empty">
  <p>No birthdays in <?= $monthName ?></p>
</div>
<?php else: ?>
<div class="lineup-grid">
  <?php foreach ($birthdays as $emp):
    $imgUrl = getPrimaryImageUrl($pdo, $emp['id'], $emp['gender'], $emp['primary_image'] ?? null);
    $parts  = explode('-', $emp['birthdate']);
    $dayNum = isset($parts[1]) ? ltrim($parts[1], '0') : '';
    $isToday = $emp['birthdate'] === appToday();
  ?>
  <div class="lineup-card <?= $isToday ? 'lineup-today' : '' ?>">
    <div class="lineup-img-wrap">
      <img src="<?= htmlspecialchars($imgUrl) ?>"
           alt="<?= htmlspecialchars($emp['full_name']) ?>">
      <?php if ($isToday): ?><span class="today-badge">Today!</span><?php endif; ?>
    </div>
    <div class="lineup-info">
      <strong><?= htmlspecialchars($emp['full_name']) ?></strong>
      <span><?= htmlspecialchars($emp['company_name']) ?></span>
      <span class="lineup-date">
        <?= htmlspecialchars($monthName) ?> <?= $dayNum ?>
      </span>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
