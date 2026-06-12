<?php
// Admin sidebar navigation — included on every admin page
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

function navLink(string $href, string $icon, string $label, string $current_page, string $match_page): string {
    $active = ($current_page === $match_page) ? ' active' : '';
    $url    = APP_BASE . $href;
    return "<a href=\"{$url}\" class=\"nav-link{$active}\">{$icon} <span>{$label}</span></a>";
}
?>
<script>window.APP_BASE = '<?= APP_BASE ?>';</script>

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

<aside class="admin-sidebar">
  <div class="sidebar-brand">
    <img src="/zdbdays/assets/img/zdg_logo.jpeg" class="brand-logo" alt="Zambezi Diamond">
    <div class="brand-text">
      <strong>ZD</strong>
      <small>Birthdays</small>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php
    $dashboardIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house" viewBox="0 0 16 16">'
                  . '<path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293zM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5z"/>'
                  . '</svg>';
    $accountsIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-circle" viewBox="0 0 16 16">'
                  . '<path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>'
                  . '<path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1"/>'
                  . '</svg>';
    $csvIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-upload" viewBox="0 0 16 16">
                  <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"/>
                  <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708z"/>
                  </svg>';

    $viewIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tv" viewBox="0 0 16 16">'
                  . '<path d="M2.5 13.5A.5.5 0 0 1 3 13h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5M13.991 3l.024.001a1.5 1.5 0 0 1 .538.143.76.76 0 0 1 .302.254c.067.1.145.277.145.602v5.991l-.001.024a1.5 1.5 0 0 1-.143.538.76.76 0 0 1-.254.302c-.1.067-.277.145-.602.145H2.009l-.024-.001a1.5 1.5 0 0 1-.538-.143.76.76 0 0 1-.302-.254C1.078 10.502 1 10.325 1 10V4.009l.001-.024a1.5 1.5 0 0 1 .143-.538.76.76 0 0 1 .254-.302C1.498 3.078 1.675 3 2 3zM14 2H2C0 2 0 4 0 4v6c0 2 2 2 2 2h12c2 0 2-2 2-2V4c0-2-2-2-2-2"/>'
                  . '</svg>';
    ?>
    <a href="<?= APP_BASE ?>/" class="nav-link" target="_blank" rel="noopener"><?= $viewIcon ?> <span>View</span></a>
    <?= navLink('/admin/dashboard.php', $dashboardIcon, 'Dashboard',   $current_page, 'dashboard.php') ?>
    <?= navLink('/admin/employees/import.php',    $csvIcon, 'Import CSV',   $current_page, 'import.php') ?>
    <?= navLink('/admin/accounts.php', $accountsIcon, 'Accounts',      $current_page, 'accounts.php') ?>
  </nav>

  <div class="sidebar-footer">
    <span class="sidebar-user"><?= $accountsIcon ?> <?= htmlspecialchars(getAdminUsername()) ?></span>
    <form method="POST" action="<?= APP_BASE ?>/admin/logout.php" class="logout-form">
      <?= csrfField() ?>
      <button type="submit" class="logout-link">Sign out</button>
    </form>
  </div>
</aside>
