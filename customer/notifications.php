<?php
$BASE = '..'; $ROLE = 'customer'; $PAGE_TITLE = 'Notification';
require_once __DIR__ . '/../includes/auth.php';
require_login('customer');
require_once __DIR__ . '/../includes/data.php';

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    if (csrf_verify()) {
        mark_notifications_read((int)$me['id']);
    }
    header('Location: notifications.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
$mine = load_notifications((int)$me['id']);
$unreadCount = count(array_filter($mine, fn($n) => !$n['read']));
?>
<section class="card">
    <div class="card__head">
      <h3>View Notification</h3>
      <span class="realtime-status realtime-status--checking notification-live-status" data-notification-status role="status" aria-live="polite">
        <span class="realtime-status__dot" aria-hidden="true"></span><span data-notification-status-text>Checking updates...</span>
      </span>
      <div class="spacer toolbar no-print" style="gap:.6rem">
      <span class="badge badge--warn"><?= $unreadCount ?> unread</span>
      <?php if ($unreadCount > 0): ?>
        <form method="post" action="notifications.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="mark_all_read">
          <button class="btn btn--ghost btn--sm" type="submit">Mark all as read</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <div>
    <?php if (!$mine): ?>
      <p class="empty">You have no notifications yet.</p>
    <?php endif; ?>
    <?php foreach ($mine as $n): ?>
      <article class="notif<?= $n['read'] ? '' : ' is-unread' ?>">
        <div class="notif__icon"><?= $n['type'] === 'Delivery' ? '&#128666;' : ($n['type'] === 'Payment' ? '&#128179;' : '&#128276;') ?></div>
        <div>
          <h4><?= e($n['title']) ?></h4>
          <p><?= e($n['message']) ?></p>
          <small><?= e($n['date']) ?> &middot; <span class="badge"><?= e($n['type']) ?></span></small>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php';
