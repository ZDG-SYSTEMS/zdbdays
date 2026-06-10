<?php
// Expects: $w (wish row array), $pdo
// Called inside foreach loop in index.php
$isOwn    = isset($_SESSION) && ($w['session_id'] === session_id());
$elapsed  = time() - strtotime($w['created_at']);
$canAct   = $isOwn && ($elapsed <= WISH_EDIT_WINDOW);
?>
<div class="wish-card" id="wish-card-<?= $w['id'] ?>">
  <div class="wish-bubble">
    <div class="wish-message" id="wish-msg-<?= $w['id'] ?>">
      <?= nl2br(sanitize($w['message'])) ?>
    </div>
  </div>
  <div class="wish-attribution">
    <span class="wish-author"><?= sanitize($w['author_name']) ?></span>
  </div>
  <?php if ($canAct): ?>
  <div class="wish-actions" id="wish-act-<?= $w['id'] ?>">
    <button class="wish-btn edit-btn" onclick="openWishEdit(<?= $w['id'] ?>)">Edit</button>
    <button class="wish-btn delete-btn" onclick="deleteWish(<?= $w['id'] ?>)">Delete</button>
    <span class="wish-countdown" id="wc-<?= $w['id'] ?>">(<?= WISH_EDIT_WINDOW - $elapsed ?>s)</span>
  </div>
  <script>startWishCountdown(<?= $w['id'] ?>, <?= WISH_EDIT_WINDOW - $elapsed ?>);</script>
  <?php endif; ?>
</div>
