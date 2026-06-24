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
$noBdSrc = $noBdImg ? (UPLOAD_URL . '/defaults/' . rawurlencode($noBdImg)) : APP_BASE . '/assets/img/sadt.gif';

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
<title><?= htmlspecialchars($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;0,900;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
</head>
<body>

<!-- Site logo (top-left) -->
<a href="<?= APP_BASE ?>/" class="site-logo" aria-label="Home">
  <img src="<?= APP_BASE ?>/assets/img/zdg_logo.jpeg" alt="Zambezi Diamond">
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
      <img src="assets/img/left.png" alt="" class="bday-cake bday-cake-left">
      Birthday Buddies!
      <img src="assets/img/right.png" alt="" class="bday-cake bday-cake-right">
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
          <p class="no-wishes" id="no-wishes-<?= $b['id'] ?>">Be the first to wish <?= htmlspecialchars(explode(' ', $b['full_name'])[0]) ?>! <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cake2" viewBox="0 0 16 16"><path d="m3.494.013-.595.79A.747.747 0 0 0 3 1.814v2.683q-.224.051-.432.107c-.702.187-1.305.418-1.745.696C.408 5.56 0 5.954 0 6.5v7c0 .546.408.94.823 1.201.44.278 1.043.51 1.745.696C3.978 15.773 5.898 16 8 16s4.022-.227 5.432-.603c.701-.187 1.305-.418 1.745-.696.415-.261.823-.655.823-1.201v-7c0-.546-.408-.94-.823-1.201-.44-.278-1.043-.51-1.745-.696A12 12 0 0 0 13 4.496v-2.69a.747.747 0 0 0 .092-1.004l-.598-.79-.595.792A.747.747 0 0 0 12 1.813V4.3a22 22 0 0 0-2-.23V1.806a.747.747 0 0 0 .092-1.004l-.598-.79-.595.792A.747.747 0 0 0 9 1.813v2.204a29 29 0 0 0-2 0V1.806A.747.747 0 0 0 7.092.802l-.598-.79-.595.792A.747.747 0 0 0 6 1.813V4.07c-.71.05-1.383.129-2 .23V1.806A.747.747 0 0 0 4.092.802zm-.668 5.556L3 5.524v.967q.468.111 1 .201V5.315a21 21 0 0 1 2-.242v1.855q.488.036 1 .054V5.018a28 28 0 0 1 2 0v1.964q.512-.018 1-.054V5.073c.72.054 1.393.137 2 .242v1.377q.532-.09 1-.201v-.967l.175.045c.655.175 1.15.374 1.469.575.344.217.356.35.356.356s-.012.139-.356.356c-.319.2-.814.4-1.47.575C11.87 7.78 10.041 8 8 8c-2.04 0-3.87-.221-5.174-.569-.656-.175-1.151-.374-1.47-.575C1.012 6.639 1 6.506 1 6.5s.012-.139.356-.356c.319-.2.814-.4 1.47-.575M15 7.806v1.027l-.68.907a.94.94 0 0 1-1.17.276 1.94 1.94 0 0 0-2.236.363l-.348.348a1 1 0 0 1-1.307.092l-.06-.044a2 2 0 0 0-2.399 0l-.06.044a1 1 0 0 1-1.306-.092l-.35-.35a1.935 1.935 0 0 0-2.233-.362.935.935 0 0 1-1.168-.277L1 8.82V7.806c.42.232.956.428 1.568.591C3.978 8.773 5.898 9 8 9s4.022-.227 5.432-.603c.612-.163 1.149-.36 1.568-.591m0 2.679V13.5c0 .006-.012.139-.356.355-.319.202-.814.401-1.47.576C11.87 14.78 10.041 15 8 15c-2.04 0-3.87-.221-5.174-.569-.656-.175-1.151-.374-1.47-.575-.344-.217-.356-.35-.356-.356v-3.02a1.935 1.935 0 0 0 2.298.43.935.935 0 0 1 1.08.175l.348.349a2 2 0 0 0 2.615.185l.059-.044a1 1 0 0 1 1.2 0l.06.044a2 2 0 0 0 2.613-.185l.348-.348a.94.94 0 0 1 1.082-.175c.781.39 1.718.208 2.297-.426"/></svg></p>
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
          <button type="submit" class="btn-wish">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 512.001 512.001" style="transform:scaleX(-1)"><path d="M244.469,265.441c9.16,8.429,29.556,27.196-0.014-0.013c-5.936-5.462-4.103-3.775-0.008-0.007 c-19.195-17.661-40.354-31.086-59.58-37.802c-21.662-7.566-39.01-5.848-48.842,4.838c-9.834,10.686-10.105,28.115-0.766,49.075 c8.289,18.604,23.428,38.579,42.626,56.244c19.198,17.664,40.359,31.092,59.588,37.809c1.222,0.426,2.427,0.817,3.62,1.185 L38.356,489.166l78.803-176.245c2.001-4.476-0.005-9.729-4.481-11.73c-4.48-2-9.729,0.004-11.73,4.481L17.235,492.898 c-4.914,10.915,8.791,23.411,19.181,17.648l239.431-132.739c4.26-2.055,7.333-3.952,10.468-7.061 C306.213,351.016,287.298,304.857,244.469,265.441z M273.246,358.725c-6.012,7.716-22.256,2.773-29.918,0.096 c-16.993-5.936-35.964-18.05-53.42-34.112c-17.455-16.06-31.103-33.962-38.43-50.402c-6.093-13.677-6.986-24.826-2.387-29.824 c2.315-2.516,6.297-3.774,11.486-3.774c5.119,0,11.414,1.225,18.432,3.677c16.993,5.936,35.964,18.05,53.42,34.112 C270.891,313.888,281.303,348.38,273.246,358.725z"/><path d="M256.079,31.831c3.971-2.878,4.857-8.429,1.979-12.401c-2.879-3.971-8.428-4.855-12.401-1.979 c-21.857,15.841-32.753,32.189-32.385,48.593c0.265,11.886,6.06,19.148,11.665,26.171c4.834,6.057,9.832,12.32,12.28,23.056 c3.749,16.44-1.106,31.041-5.389,43.923c-4.737,14.25-12.152,27.321-22.039,38.852c-3.193,3.724-2.763,9.329,0.96,12.521 c3.672,3.15,9.37,2.711,12.519-0.961c11.394-13.288,19.943-28.363,25.411-44.809c4.516-13.586,10.703-32.193,5.851-53.474 c-3.352-14.689-10.206-23.28-15.714-30.183c-5.033-6.308-7.666-9.86-7.791-15.491C230.741,52.909,244.493,40.227,256.079,31.831z"/><path d="M335.792,70.703c-0.006,0-0.011,0-0.015,0c-4.898,0-8.871,3.966-8.879,8.864c-0.025,14.689-2.931,28.845-8.639,42.073 c-10.961,25.405-29.288,40.692-42.734,49.044c-4.165,2.588-5.443,8.063-2.856,12.229c1.682,2.706,4.583,4.194,7.551,4.194 c1.599,0,3.219-0.432,4.677-1.338c15.602-9.692,36.883-27.465,49.668-57.093c6.668-15.455,10.063-31.968,10.09-49.08 C344.664,74.693,340.695,70.711,335.792,70.703z"/><path d="M459.986,116.786c-4.283-2.393-9.691-0.863-12.084,3.414c-12.118,21.662-27.532,36.776-45.812,44.923 c-12.973,5.782-23.731,6.514-35.119,7.287c-12.262,0.835-24.943,1.696-39.101,9.214c-22.45,11.917-38.839,35.804-48.713,70.998 c-1.258,4.486,1.286,9.316,5.699,10.809c4.758,1.609,10.042-1.174,11.398-6.01c8.51-30.337,21.949-50.561,39.941-60.112 c10.813-5.742,20.622-6.408,31.979-7.18c11.855-0.806,25.291-1.719,41.143-8.784c21.857-9.742,40.053-27.396,54.081-52.473 C465.793,124.591,464.264,119.18,459.986,116.786z"/><path d="M464.442,282.184c-2.583-4.17-8.059-5.451-12.223-2.866c-14.134,8.765-27.049,11.956-38.377,9.484 c-7.095-1.55-11.392-4.799-16.833-8.914c-4.296-3.249-9.164-6.93-15.792-9.878c-19.998-8.893-45.393-6.129-75.482,8.214 c-4.426,2.111-6.304,7.41-4.194,11.836c2.11,4.425,7.406,6.302,11.836,4.194c25.189-12.01,45.587-14.706,60.623-8.02 c4.76,2.117,8.42,4.886,12.296,7.817c6.023,4.556,12.85,9.72,23.758,12.1c3.76,0.82,7.592,1.231,11.494,1.231 c12.698,0,26.114-4.341,40.03-12.974C465.746,291.824,467.028,286.35,464.442,282.184z"/><path d="M485.246,197.544c-0.708-4.838-5.186-8.181-10.026-7.503c1.061-0.155,1.591-0.233-0.045,0.004 c-6.043,0.884-2.25,0.331-0.118,0.02c-9.309,1.374-18.279,4.163-26.662,8.292c-11.659,5.745-22.19,14.199-30.457,24.45 c-2.813,3.488-2.537,8.695,0.639,11.859c3.685,3.67,9.92,3.337,13.184-0.711c6.658-8.256,15.124-15.058,24.485-19.669 c6.752-3.328,13.985-5.571,21.501-6.671C482.598,206.904,485.957,202.396,485.246,197.544z"/><path d="M371.678,223.199c-5.494,0-9.963,4.47-9.963,9.963c0,5.494,4.469,9.963,9.963,9.963c5.494,0,9.963-4.469,9.963-9.963 C381.641,227.668,377.172,223.199,371.678,223.199z"/><path d="M392.598,94.899c-5.494,0-9.963,4.469-9.963,9.963c0,5.493,4.47,9.962,9.963,9.962c5.494,0,9.963-4.469,9.963-9.962 C402.561,99.368,398.092,94.899,392.598,94.899z"/><path d="M317.367,0c-5.494,0-9.963,4.469-9.963,9.963c0,5.493,4.469,9.962,9.963,9.962s9.963-4.469,9.963-9.962 C327.33,4.469,322.861,0,317.367,0z"/><path d="M482.191,63.059c-5.494,0-9.963,4.469-9.963,9.962c0,5.493,4.47,9.963,9.964,9.963c5.494,0,9.963-4.469,9.963-9.963 C492.154,67.528,487.685,63.059,482.191,63.059z"/><path d="M421.006,18.742c-5.493,0-9.962,4.469-9.962,9.963s4.469,9.963,9.962,9.963c5.494,0,9.963-4.469,9.963-9.963 S426.5,18.742,421.006,18.742z"/><path d="M280.212,53.687c-5.494,0-9.963,4.469-9.963,9.963c0,5.492,4.469,9.962,9.963,9.962c5.494,0,9.963-4.469,9.963-9.962 C290.176,58.156,285.707,53.687,280.212,53.687z"/><path d="M485.832,241.589c-5.494,0-9.963,4.469-9.963,9.962c0,5.494,4.469,9.963,9.963,9.963c5.494,0,9.963-4.469,9.963-9.963 C495.796,246.06,491.327,241.589,485.832,241.589z"/><path d="M193.644,131.083c-5.494,0-9.963,4.469-9.963,9.963c0,5.492,4.469,9.962,9.963,9.962c5.493,0,9.962-4.469,9.962-9.962 C203.607,135.552,199.137,131.083,193.644,131.083z"/><path d="M159.442,348.684c-15.223,0-27.609,12.386-27.609,27.609s12.386,27.609,27.609,27.609 c15.223,0,27.609-12.386,27.609-27.609S174.666,348.684,159.442,348.684z M159.442,386.144c-5.432,0-9.851-4.421-9.851-9.851 c0-5.432,4.419-9.851,9.851-9.851s9.851,4.419,9.851,9.851C169.293,381.725,164.874,386.144,159.442,386.144z"/><path d="M114.096,409.146c-2.597-6.902-7.727-12.38-14.444-15.423c-4.47-2.027-9.73-0.045-11.752,4.422 c-2.024,4.467-0.044,9.729,4.422,11.752c2.396,1.087,4.226,3.04,5.153,5.503s0.839,5.139-0.247,7.536 c-2.241,4.946-8.087,7.146-13.038,4.906c-4.466-2.024-9.73-0.044-11.752,4.422c-2.024,4.466-0.044,9.728,4.422,11.752 c3.687,1.67,7.549,2.461,11.356,2.461c10.51-0.001,20.574-6.032,25.187-16.211C116.444,423.549,116.692,416.049,114.096,409.146z"/></svg>Send Birthday Wish<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 512.001 512.001"><path d="M244.469,265.441c9.16,8.429,29.556,27.196-0.014-0.013c-5.936-5.462-4.103-3.775-0.008-0.007 c-19.195-17.661-40.354-31.086-59.58-37.802c-21.662-7.566-39.01-5.848-48.842,4.838c-9.834,10.686-10.105,28.115-0.766,49.075 c8.289,18.604,23.428,38.579,42.626,56.244c19.198,17.664,40.359,31.092,59.588,37.809c1.222,0.426,2.427,0.817,3.62,1.185 L38.356,489.166l78.803-176.245c2.001-4.476-0.005-9.729-4.481-11.73c-4.48-2-9.729,0.004-11.73,4.481L17.235,492.898 c-4.914,10.915,8.791,23.411,19.181,17.648l239.431-132.739c4.26-2.055,7.333-3.952,10.468-7.061 C306.213,351.016,287.298,304.857,244.469,265.441z M273.246,358.725c-6.012,7.716-22.256,2.773-29.918,0.096 c-16.993-5.936-35.964-18.05-53.42-34.112c-17.455-16.06-31.103-33.962-38.43-50.402c-6.093-13.677-6.986-24.826-2.387-29.824 c2.315-2.516,6.297-3.774,11.486-3.774c5.119,0,11.414,1.225,18.432,3.677c16.993,5.936,35.964,18.05,53.42,34.112 C270.891,313.888,281.303,348.38,273.246,358.725z"/><path d="M256.079,31.831c3.971-2.878,4.857-8.429,1.979-12.401c-2.879-3.971-8.428-4.855-12.401-1.979 c-21.857,15.841-32.753,32.189-32.385,48.593c0.265,11.886,6.06,19.148,11.665,26.171c4.834,6.057,9.832,12.32,12.28,23.056 c3.749,16.44-1.106,31.041-5.389,43.923c-4.737,14.25-12.152,27.321-22.039,38.852c-3.193,3.724-2.763,9.329,0.96,12.521 c3.672,3.15,9.37,2.711,12.519-0.961c11.394-13.288,19.943-28.363,25.411-44.809c4.516-13.586,10.703-32.193,5.851-53.474 c-3.352-14.689-10.206-23.28-15.714-30.183c-5.033-6.308-7.666-9.86-7.791-15.491C230.741,52.909,244.493,40.227,256.079,31.831z"/><path d="M335.792,70.703c-0.006,0-0.011,0-0.015,0c-4.898,0-8.871,3.966-8.879,8.864c-0.025,14.689-2.931,28.845-8.639,42.073 c-10.961,25.405-29.288,40.692-42.734,49.044c-4.165,2.588-5.443,8.063-2.856,12.229c1.682,2.706,4.583,4.194,7.551,4.194 c1.599,0,3.219-0.432,4.677-1.338c15.602-9.692,36.883-27.465,49.668-57.093c6.668-15.455,10.063-31.968,10.09-49.08 C344.664,74.693,340.695,70.711,335.792,70.703z"/><path d="M459.986,116.786c-4.283-2.393-9.691-0.863-12.084,3.414c-12.118,21.662-27.532,36.776-45.812,44.923 c-12.973,5.782-23.731,6.514-35.119,7.287c-12.262,0.835-24.943,1.696-39.101,9.214c-22.45,11.917-38.839,35.804-48.713,70.998 c-1.258,4.486,1.286,9.316,5.699,10.809c4.758,1.609,10.042-1.174,11.398-6.01c8.51-30.337,21.949-50.561,39.941-60.112 c10.813-5.742,20.622-6.408,31.979-7.18c11.855-0.806,25.291-1.719,41.143-8.784c21.857-9.742,40.053-27.396,54.081-52.473 C465.793,124.591,464.264,119.18,459.986,116.786z"/><path d="M464.442,282.184c-2.583-4.17-8.059-5.451-12.223-2.866c-14.134,8.765-27.049,11.956-38.377,9.484 c-7.095-1.55-11.392-4.799-16.833-8.914c-4.296-3.249-9.164-6.93-15.792-9.878c-19.998-8.893-45.393-6.129-75.482,8.214 c-4.426,2.111-6.304,7.41-4.194,11.836c2.11,4.425,7.406,6.302,11.836,4.194c25.189-12.01,45.587-14.706,60.623-8.02 c4.76,2.117,8.42,4.886,12.296,7.817c6.023,4.556,12.85,9.72,23.758,12.1c3.76,0.82,7.592,1.231,11.494,1.231 c12.698,0,26.114-4.341,40.03-12.974C465.746,291.824,467.028,286.35,464.442,282.184z"/><path d="M485.246,197.544c-0.708-4.838-5.186-8.181-10.026-7.503c1.061-0.155,1.591-0.233-0.045,0.004 c-6.043,0.884-2.25,0.331-0.118,0.02c-9.309,1.374-18.279,4.163-26.662,8.292c-11.659,5.745-22.19,14.199-30.457,24.45 c-2.813,3.488-2.537,8.695,0.639,11.859c3.685,3.67,9.92,3.337,13.184-0.711c6.658-8.256,15.124-15.058,24.485-19.669 c6.752-3.328,13.985-5.571,21.501-6.671C482.598,206.904,485.957,202.396,485.246,197.544z"/><path d="M371.678,223.199c-5.494,0-9.963,4.47-9.963,9.963c0,5.494,4.469,9.963,9.963,9.963c5.494,0,9.963-4.469,9.963-9.963 C381.641,227.668,377.172,223.199,371.678,223.199z"/><path d="M392.598,94.899c-5.494,0-9.963,4.469-9.963,9.963c0,5.493,4.47,9.962,9.963,9.962c5.494,0,9.963-4.469,9.963-9.962 C402.561,99.368,398.092,94.899,392.598,94.899z"/><path d="M317.367,0c-5.494,0-9.963,4.469-9.963,9.963c0,5.493,4.469,9.962,9.963,9.962s9.963-4.469,9.963-9.962 C327.33,4.469,322.861,0,317.367,0z"/><path d="M482.191,63.059c-5.494,0-9.963,4.469-9.963,9.962c0,5.493,4.47,9.963,9.964,9.963c5.494,0,9.963-4.469,9.963-9.963 C492.154,67.528,487.685,63.059,482.191,63.059z"/><path d="M421.006,18.742c-5.493,0-9.962,4.469-9.962,9.963s4.469,9.963,9.962,9.963c5.494,0,9.963-4.469,9.963-9.963 S426.5,18.742,421.006,18.742z"/><path d="M280.212,53.687c-5.494,0-9.963,4.469-9.963,9.963c0,5.492,4.469,9.962,9.963,9.962c5.494,0,9.963-4.469,9.963-9.962 C290.176,58.156,285.707,53.687,280.212,53.687z"/><path d="M485.832,241.589c-5.494,0-9.963,4.469-9.963,9.962c0,5.494,4.469,9.963,9.963,9.963c5.494,0,9.963-4.469,9.963-9.963 C495.796,246.06,491.327,241.589,485.832,241.589z"/><path d="M193.644,131.083c-5.494,0-9.963,4.469-9.963,9.963c0,5.492,4.469,9.962,9.963,9.962c5.493,0,9.962-4.469,9.962-9.962 C203.607,135.552,199.137,131.083,193.644,131.083z"/><path d="M159.442,348.684c-15.223,0-27.609,12.386-27.609,27.609s12.386,27.609,27.609,27.609 c15.223,0,27.609-12.386,27.609-27.609S174.666,348.684,159.442,348.684z M159.442,386.144c-5.432,0-9.851-4.421-9.851-9.851 c0-5.432,4.419-9.851,9.851-9.851s9.851,4.419,9.851,9.851C169.293,381.725,164.874,386.144,159.442,386.144z"/><path d="M114.096,409.146c-2.597-6.902-7.727-12.38-14.444-15.423c-4.47-2.027-9.73-0.045-11.752,4.422 c-2.024,4.467-0.044,9.729,4.422,11.752c2.396,1.087,4.226,3.04,5.153,5.503s0.839,5.139-0.247,7.536 c-2.241,4.946-8.087,7.146-13.038,4.906c-4.466-2.024-9.73-0.044-11.752,4.422c-2.024,4.466-0.044,9.728,4.422,11.752 c3.687,1.67,7.549,2.461,11.356,2.461c10.51-0.001,20.574-6.032,25.187-16.211C116.444,423.549,116.692,416.049,114.096,409.146z"/></svg></button>
        </form>
      </div>
      <?php else: ?>
      <p class="wish-submitted"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/></svg> You've already sent a wish today!</p>
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
  <img src="<?= APP_BASE ?>/assets/img/zdg_logo.jpeg" class="footer-logo" alt="Zambezi Diamond">
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
<script src="<?= APP_BASE ?>/assets/js/app.js"></script>
</body>
</html>
