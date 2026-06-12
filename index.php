<?php
// ============================================================
// ZD Birthdays — Public Homepage
// ============================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
sessionStart();

// Expired-wish cleanup runs on a schedule now — see cron/cleanup.php

$todayBirthdays    = getTodaysBirthdays($pdo);
$tomorrowBirthdays = getTomorrowsBirthdays($pdo);
$captions          = getActiveCaptions($pdo);
$allBirthdays      = getAllBirthdays($pdo);
$isAdminUser       = isAdminLoggedIn($pdo);
$siteName          = getSetting($pdo, 'site_name') ?: 'Birthdays.ZambeziDiamond';

// Birthday map for calendar: ['MM-DD' => [emp, emp, ...]]
$bdMap = [];
foreach ($allBirthdays as $e) {
    $bdMap[$e['birthdate']][] = $e;
}

// Banner state
$bannerState = 'none'; // 'birthday' | 'countdown' | 'none'
if (!empty($todayBirthdays))    $bannerState = 'birthday';
elseif (!empty($tomorrowBirthdays)) $bannerState = 'countdown';

$midnightTs = midnightTimestamp(); // JS uses this for countdown

// Captions JSON for JS
$captionsJson = json_encode(array_column($captions, 'caption_text'));

// No-birthday image
$noBdImg = getSetting($pdo, 'no_birthday_image');
$noBdSrc = $noBdImg ? (UPLOAD_URL . '/defaults/' . rawurlencode($noBdImg)) : '/assets/img/sadt.gif';

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
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/assets/img/zdg_logo.jpeg" type="image/jpeg">
<title><?= htmlspecialchars($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
</head>
<body>

<!-- Site logo (top-left) -->
<a href="<?= APP_BASE ?>/" class="site-logo" aria-label="Home">
  <img src="/assets/img/zdg_logo.jpeg" alt="Zambezi Diamond">
</a>

<!-- ======================================================
     BANNER
======================================================= -->
<section class="banner banner--<?= $bannerState ?>">

<?php if ($bannerState === 'birthday'): ?>
  <!-- Birthday Banner -->
  <canvas id="confetti-canvas"></canvas>
  <div class="banner-inner">
    <?php if (count($todayBirthdays) === 1):
      $b   = $todayBirthdays[0];
    ?>
    <!-- Single birthday -->
    <div class="bday-single">
      <div class="bday-photo">
        <img src="<?= htmlspecialchars(getPrimaryImageUrl($pdo, $b['id'], $b['gender'], $b['primary_image'] ?? null)) ?>"
             alt="<?= htmlspecialchars($b['full_name']) ?>">
      </div>
      <div class="bday-info">
        <p class="bday-script">Happy Birthday!</p>
        <h1 class="bday-name"><?= htmlspecialchars($b['full_name']) ?></h1>
        <?php if (!empty($b['position'])): ?>
        <p class="bday-position"><?= htmlspecialchars($b['position']) ?></p>
        <?php endif; ?>
        <p class="bday-company">
          <?= htmlspecialchars($b['company_name']) ?> · <?= htmlspecialchars($b['branch_name']) ?>
        </p>
        <?php if (!empty(trim($b['primary_message'] ?? ''))): ?>
        <blockquote class="bday-msg"><?= nl2br(sanitize($b['primary_message'])) ?></blockquote>
        <?php endif; ?>
      </div>
    </div>

    <?php else: ?>
    <!-- B-day Buddies -->
    <div class="bday-twins-label">
      <img src="/assets/img/left.png" alt="" class="bday-cake bday-cake-left">
      Birthday Buddies!
      <img src="/assets/img/right.png" alt="" class="bday-cake bday-cake-right">
    </div>
    <div class="bday-twins">
      <?php foreach ($todayBirthdays as $idx => $b): ?>
      <div class="bday-twin">
        <div class="bday-photo">
          <img src="<?= htmlspecialchars(getPrimaryImageUrl($pdo, $b['id'], $b['gender'], $b['primary_image'] ?? null)) ?>"
               alt="<?= htmlspecialchars($b['full_name']) ?>">
        </div>
        <div class="twin-info">
          <p class="bday-script">Happy Birthday!</p>
          <h2><?= htmlspecialchars($b['full_name']) ?></h2>
          <?php if (!empty($b['position'])): ?>
          <p class="bday-position"><?= htmlspecialchars($b['position']) ?></p>
          <?php endif; ?>
          <p class="bday-company"><?= htmlspecialchars($b['company_name']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

<?php elseif ($bannerState === 'countdown'): ?>
  <!-- Countdown Banner -->
  <div class="banner-inner banner-inner--countdown">
    <div class="countdown-mystery">
      <div class="mystery-circle">?</div>
    </div>
    <div class="countdown-content">
      <p class="countdown-caption" id="countdown-caption">
        <?= !empty($captions) ? htmlspecialchars($captions[0]['caption_text']) : 'Someone\'s birthday is almost here! 🎂' ?>
      </p>
      <div class="countdown-timer">
        <div class="time-block"><span id="ct-hours">00</span><small>HRS</small></div>
        <div class="time-sep">:</div>
        <div class="time-block"><span id="ct-mins">00</span><small>MINS</small></div>
        <div class="time-sep">:</div>
        <div class="time-block"><span id="ct-secs">00</span><small>SECS</small></div>
      </div>
      <p class="countdown-names">
        <?= implode(' & ', array_map(fn($e) => '<strong>' . htmlspecialchars($e['full_name']) . '</strong>', $tomorrowBirthdays)) ?>
        <?= count($tomorrowBirthdays) > 1 ? 'are' : 'is' ?> celebrating tomorrow!
      </p>
    </div>
  </div>

<?php else: ?>
  <!-- No Birthday Banner -->
  <div class="banner-inner banner-inner--none">
    <div class="no-bday-img">
      <img src="<?= htmlspecialchars($noBdSrc) ?>" alt="No birthdays today">
    </div>
    <div class="no-bday-text">
      <h2>No birthdays today</h2>
      <p>But someone's special day is always just around the corner! <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cake2" viewBox="0 0 16 16">
  <path d="m3.494.013-.595.79A.747.747 0 0 0 3 1.814v2.683q-.224.051-.432.107c-.702.187-1.305.418-1.745.696C.408 5.56 0 5.954 0 6.5v7c0 .546.408.94.823 1.201.44.278 1.043.51 1.745.696C3.978 15.773 5.898 16 8 16s4.022-.227 5.432-.603c.701-.187 1.305-.418 1.745-.696.415-.261.823-.655.823-1.201v-7c0-.546-.408-.94-.823-1.201-.44-.278-1.043-.51-1.745-.696A12 12 0 0 0 13 4.496v-2.69a.747.747 0 0 0 .092-1.004l-.598-.79-.595.792A.747.747 0 0 0 12 1.813V4.3a22 22 0 0 0-2-.23V1.806a.747.747 0 0 0 .092-1.004l-.598-.79-.595.792A.747.747 0 0 0 9 1.813v2.204a29 29 0 0 0-2 0V1.806A.747.747 0 0 0 7.092.802l-.598-.79-.595.792A.747.747 0 0 0 6 1.813V4.07c-.71.05-1.383.129-2 .23V1.806A.747.747 0 0 0 4.092.802zm-.668 5.556L3 5.524v.967q.468.111 1 .201V5.315a21 21 0 0 1 2-.242v1.855q.488.036 1 .054V5.018a28 28 0 0 1 2 0v1.964q.512-.018 1-.054V5.073c.72.054 1.393.137 2 .242v1.377q.532-.09 1-.201v-.967l.175.045c.655.175 1.15.374 1.469.575.344.217.356.35.356.356s-.012.139-.356.356c-.319.2-.814.4-1.47.575C11.87 7.78 10.041 8 8 8c-2.04 0-3.87-.221-5.174-.569-.656-.175-1.151-.374-1.47-.575C1.012 6.639 1 6.506 1 6.5s.012-.139.356-.356c.319-.2.814-.4 1.47-.575M15 7.806v1.027l-.68.907a.94.94 0 0 1-1.17.276 1.94 1.94 0 0 0-2.236.363l-.348.348a1 1 0 0 1-1.307.092l-.06-.044a2 2 0 0 0-2.399 0l-.06.044a1 1 0 0 1-1.306-.092l-.35-.35a1.935 1.935 0 0 0-2.233-.362.935.935 0 0 1-1.168-.277L1 8.82V7.806c.42.232.956.428 1.568.591C3.978 8.773 5.898 9 8 9s4.022-.227 5.432-.603c.612-.163 1.149-.36 1.568-.591m0 2.679V13.5c0 .006-.012.139-.356.355-.319.202-.814.401-1.47.576C11.87 14.78 10.041 15 8 15c-2.04 0-3.87-.221-5.174-.569-.656-.175-1.151-.374-1.47-.575-.344-.217-.356-.35-.356-.356v-3.02a1.935 1.935 0 0 0 2.298.43.935.935 0 0 1 1.08.175l.348.349a2 2 0 0 0 2.615.185l.059-.044a1 1 0 0 1 1.2 0l.06.044a2 2 0 0 0 2.613-.185l.348-.348a.94.94 0 0 1 1.082-.175c.781.39 1.718.208 2.297-.426"/>
</svg></p>
    </div>
  </div>
<?php endif; ?>
</section>

<!-- ======================================================
     BIRTHDAY WISHES — display (collage / carousel)
     Shown right under the banner on someone's birthday
======================================================= -->
<?php if ($bannerState === 'birthday'): ?>
<section class="section wishes-section">
  <div class="container">
    <div class="section-title">
      <span class="section-tag">Share the Love</span>
      <h2>Birthday Wishes</h2>
    </div>
  </div>

  <?php foreach ($todayBirthdays as $b):
    $wishes = getActiveWishes($pdo, $b['id']);
  ?>
  <div class="wishes-block" data-employee-id="<?= $b['id'] ?>">
    <?php if (count($todayBirthdays) > 1): ?>
    <h3 class="wishes-for">For <?= htmlspecialchars($b['full_name']) ?></h3>
    <?php endif; ?>

    <div class="wishes-carousel">
      <button type="button" class="wish-arrow wish-arrow--prev" aria-label="Previous wishes">&#8249;</button>
      <div class="wishes-viewport">
        <div class="wishes-track" id="wishes-list-<?= $b['id'] ?>">
          <?php foreach ($wishes as $w): ?>
          <?php include __DIR__ . '/partials/wish-card.php'; ?>
          <?php endforeach; ?>
          <?php if (empty($wishes)): ?>
          <p class="no-wishes" id="no-wishes-<?= $b['id'] ?>">Be the first to wish <?= htmlspecialchars(explode(' ', $b['full_name'])[0]) ?>! 🎂</p>
          <?php endif; ?>
        </div>
      </div>
      <button type="button" class="wish-arrow wish-arrow--next" aria-label="Next wishes">&#8250;</button>
    </div>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ======================================================
     BIRTHDAY LINEUP
======================================================= -->
<section class="section lineup-section">
  <div class="container">
    <div class="section-title">
      <span class="section-tag">This Month</span>
      <h2>Birthday Lineup</h2>
    </div>
    <div id="lineup-container"
         hx-get="<?= APP_BASE ?>/partials/lineup.php"
         hx-trigger="load">
      <div class="loading-pulse">Loading lineup…</div>
    </div>
  </div>
</section>

<!-- ======================================================
     BIRTHDAY CALENDAR
======================================================= -->
<section class="section calendar-section">
  <div class="container">
    <div class="section-title">
      <span class="section-tag">Year at a Glance</span>
      <h2>Birthday Calendar</h2>
    </div>
    <div class="calendar-grid-wrap">
      <?php foreach ($months as $num => $name):
        $daysInMonth = (int)(new DateTime("2024-{$num}-01"))->format('t');
        $firstDow    = (int)(new DateTime("2024-{$num}-01"))->format('w'); // 0=Sun
      ?>
      <div class="cal-month">
        <h4 class="cal-month-name"><?= $name ?></h4>
        <div class="cal-grid">
          <?php foreach (['S','M','T','W','T','F','S'] as $d): ?>
            <div class="cal-dh"><?= $d ?></div>
          <?php endforeach; ?>
          <?php for ($pad = 0; $pad < $firstDow; $pad++): ?>
            <div class="cal-empty"></div>
          <?php endfor; ?>
          <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $key     = $num . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            $hasBday = isset($bdMap[$key]);
            $isToday = $key === appToday();
          ?>
          <div class="cal-day <?= $hasBday ? 'has-bday' : '' ?> <?= $isToday ? 'is-today' : '' ?>">
            <span class="cal-num"><?= $day ?></span>
            <?php if ($hasBday): ?>
            <div class="cal-markers">
              <?php foreach ($bdMap[$key] as $emp):
                $src = getPrimaryImageUrl($pdo, $emp['id'], $emp['gender'], $emp['primary_image'] ?? null);
              ?>
              <div class="cal-marker" title="<?= htmlspecialchars($emp['full_name']) ?>">
                <img src="<?= htmlspecialchars($src) ?>"
                     alt="<?= htmlspecialchars($emp['full_name']) ?>">
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endfor; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ======================================================
     BIRTHDAY WISH FORM  (bottom — only on someone's birthday)
======================================================= -->
<?php if ($bannerState === 'birthday'): ?>
<section class="section wish-form-section">
  <div class="container">
    <div class="section-title">
      <span class="section-tag">Send Your Wishes</span>
      <h2>Leave a Birthday Wish</h2>
    </div>

    <?php foreach ($todayBirthdays as $b): ?>
    <div class="wish-form-block" data-employee-id="<?= $b['id'] ?>">
      <?php if (count($todayBirthdays) > 1): ?>
      <h3 class="wishes-for">For <?= htmlspecialchars($b['full_name']) ?></h3>
      <?php endif; ?>

      <?php if (!hasSessionWished($b['id'])): ?>
      <div class="wish-form-wrap" id="wish-form-<?= $b['id'] ?>">
        <form class="wish-form" data-employee-id="<?= $b['id'] ?>">
          <div class="form-group">
            <label>Your Name / Alias <span class="required">*</span></label>
            <input type="text" name="author_name" maxlength="100" required
                   placeholder="Enter your name">
          </div>
          <div class="form-group">
            <label>Your Message <span class="required">*</span></label>
            <textarea name="message" rows="4" maxlength="500"
                      placeholder="Write your birthday wish…" required></textarea>
            <span class="word-count">0 / 500 characters</span>
          </div>
          <div class="wish-form-error hidden" id="wish-err-<?= $b['id'] ?>"></div>
          <button type="submit" class="btn-wish">Send Birthday Wish 🎉</button>
        </form>
      </div>
      <?php else: ?>
      <p class="wish-submitted">✅ You've already sent a wish today!</p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Admin quick-access (shown when logged in) -->
<?php if ($isAdminUser): ?>
<div class="admin-bar">
  <span>Logged in as admin</span>
  <a href="<?= APP_BASE ?>/admin/dashboard.php">Dashboard</a>
  <form method="POST" action="<?= APP_BASE ?>/admin/logout.php" class="logout-form">
    <?= csrfField() ?>
    <button type="submit">Sign out</button>
  </form>
</div>
<?php endif; ?>

<footer class="site-footer">
  <img src="/assets/img/zdg_logo.jpeg" class="footer-logo" alt="Zambezi Diamond">
  <p>© <?= date('Y') ?> <?= htmlspecialchars(getSetting($pdo, 'site_name') ?: 'Birthdays.ZambeziDiamond') ?></p>
</footer>

<!-- Reusable confirmation modal -->
<div class="modal-overlay hidden" id="confirm-modal">
  <div class="modal-box">
    <h3 id="confirm-title">Please confirm</h3>
    <p id="confirm-message"></p>
    <div class="confirm-actions">
      <button type="button" class="confirm-btn-cancel" id="confirm-cancel">Cancel</button>
      <button type="button" class="confirm-btn-ok" id="confirm-ok">Confirm</button>
    </div>
  </div>
</div>

<script>
const APP_BASE      = '<?= APP_BASE ?>';
const MIDNIGHT_TS   = <?= $midnightTs * 1000 ?>;
const CAPTIONS      = <?= $captionsJson ?: '[]' ?>;
const BANNER_STATE  = '<?= $bannerState ?>';
const WISH_WINDOW   = <?= WISH_EDIT_WINDOW ?>;
</script>
<script src="/assets/js/app.js"></script>
</body>
</html>
