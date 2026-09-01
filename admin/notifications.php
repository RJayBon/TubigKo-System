<?php
$BASE = '..'; $ROLE = 'admin'; $PAGE_TITLE = 'Manage Notification';
require_once __DIR__ . '/../includes/auth.php';
require_login('admin');
require_once __DIR__ . '/../includes/data.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $action = $_POST['action'] ?? 'send';

        if ($action === 'send') {
            $audience = trim($_POST['audience'] ?? 'All Customers');
            $type     = trim($_POST['type'] ?? 'Announcement');
            $title    = trim($_POST['title'] ?? '');
            $message  = trim($_POST['message'] ?? '');

            if ($title === '' || $message === '') {
                flash_set('error', 'Please provide both a title and a message.');
            } else {
                $userId = null;
                if ($audience !== 'All Customers') {
                    $customer = db_one("SELECT id FROM users WHERE full_name = ? AND role = 'customer'", [$audience]);
                    $userId = $customer['id'] ?? null;
                }
                $ok = create_notification($userId, $audience, $title, $message, $type, $err);
                flash_set($ok ? 'success' : 'error', $ok ? 'Notification sent.' : ($err ?: 'Could not send notification.'));
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $ok = db_exec('DELETE FROM notifications WHERE id = ?', [$id], $err);
            flash_set($ok ? 'success' : 'error', $ok ? 'Notification deleted.' : ($err ?: 'Delete failed.'));
        }
    }
    header('Location: notifications.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
$CUSTOMERS = load_customers();
$NOTIFICATIONS = load_notifications();
?>
<div class="grid grid--2">
  <section class="card">
    <div class="card__head"><h3>Send Notification</h3></div>
    <div class="card__body">
      <form method="post" action="notifications.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send">
        <div class="field">
          <label for="audience">Send to</label>
          <select id="audience" name="audience">
            <option>All Customers</option>
            <?php foreach ($CUSTOMERS as $c): ?><option><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="ntype">Notification type</label>
          <select id="ntype" name="type"><option>Announcement</option><option>Delivery</option><option>Payment</option><option>Promo</option></select>
        </div>
        <div class="field"><label for="ntitle">Title</label><input id="ntitle" name="title" required></div>
        <div class="field"><label for="nmsg">Message</label><textarea id="nmsg" name="message" required></textarea></div>
        <button class="btn btn--block" type="submit">Send notification</button>
      </form>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h3>Sent notifications</h3>
      <span class="spacer badge"><?= count($NOTIFICATIONS) ?> total</span>
    </div>
    <div>
      <?php if (!$NOTIFICATIONS): ?><p class="empty">No notifications sent yet.</p><?php endif; ?>
      <?php foreach ($NOTIFICATIONS as $n): ?>
        <article class="notif">
          <div class="notif__icon">&#128276;</div>
          <div style="flex:1">
            <h4><?= e($n['title']) ?></h4>
            <p><?= e($n['message']) ?></p>
            <small><?= e($n['date']) ?> &middot; To: <?= e($n['audience']) ?> &middot; <span class="badge"><?= e($n['type']) ?></span></small>
          </div>
          <form method="post" action="notifications.php" onsubmit="return confirm('Delete this notification?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$n['db_id'] ?>">
            <button class="icon-btn no-print" type="submit" aria-label="Delete">&times;</button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
