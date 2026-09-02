<?php
$BASE = '..'; $ROLE = 'customer'; $PAGE_TITLE = 'Delivery Process';
require_once __DIR__ . '/../includes/auth.php';
require_login('customer');
require_once __DIR__ . '/../includes/data.php';

$me = current_user();
$clearCart = isset($_GET['clear_cart']);

include __DIR__ . '/../includes/header.php';

$mine = load_deliveries((int)$me['id']);
$active = null;
foreach ($mine as $delivery) {
    if (in_array($delivery['raw_status'], ['pending', 'confirmed', 'out_for_delivery'], true)) {
        $active = $delivery;
        break;
    }
}
$active = $active ?? ($mine[0] ?? null);

$canCancel = $active && $active['raw_status'] === 'pending';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $row = db_one('SELECT * FROM deliveries WHERE id = ? AND customer_id = ?', [$id, $me['id']]);
        if ($row && $row['status'] === 'pending') {
            $ok = db_exec('UPDATE deliveries SET status = "cancelled" WHERE id = ? AND customer_id = ? AND status = "pending"', [$id, $me['id']], $err);
            if ($ok) {
                db_exec('UPDATE payments SET status = "failed" WHERE delivery_id = ? AND customer_id = ? AND status = "pending"', [$id, $me['id']], $paymentErr);
                restore_delivery_stock($id, $stockErr);
                flash_set('success', 'Your delivery request was cancelled.');
            } else {
                flash_set('error', $err ?: 'Could not cancel this delivery.');
            }
        } else {
            flash_set('error', 'This order can no longer be cancelled.');
        }
    }
    header('Location: delivery.php');
    exit;
}
?>
<?php if ($clearCart): ?>
<script>try { localStorage.removeItem('tubigko:cart'); } catch (e) {}</script>
<?php endif; ?>

<div class="stepper">
  <div class="step is-done"><b>&#10003;</b> Select gallon</div>
  <div class="step is-done"><b>&#10003;</b> Payment</div>
  <div class="step is-active"><b>3</b> Delivery</div>
</div>

<div class="grid grid--2">
  <section class="card">
    <?php if (!$active): ?>
      <div class="card__head"><h3>Delivery Status</h3></div>
      <div class="card__body"><p class="empty">You have no delivery requests yet. <a href="gallons.php" style="color:var(--aqua-700);font-weight:600">Order gallons</a> to get started.</p></div>
    <?php else: ?>
      <div class="card__head"><h3>Delivery Status &mdash; <?= e($active['id']) ?></h3><span class="spacer <?= badge_class($active['status']) ?>"><?= e($active['status']) ?></span></div>
      <div class="card__body">
        <ul class="timeline">
          <li class="done"><span class="dot">&#10003;</span><div><h5>Order received</h5><small>Your order was placed<?= $active['raw_status'] !== 'pending' ? ' and confirmed.' : '.' ?></small></div></li>
          <li class="<?= in_array($active['raw_status'], ['confirmed','out_for_delivery','delivered']) ? 'done' : ($active['raw_status'] === 'cancelled' ? '' : 'current') ?>"><span class="dot"><?= in_array($active['raw_status'], ['confirmed','out_for_delivery','delivered']) ? '&#10003;' : '&#9679;' ?></span><div><h5>Preparing</h5><small>Payment method on file.</small></div></li>
          <li class="<?= $active['raw_status'] === 'out_for_delivery' ? 'current' : (in_array($active['raw_status'], ['delivered']) ? 'done' : '') ?>"><span class="dot"><?= $active['raw_status'] === 'delivered' ? '&#10003;' : ($active['raw_status'] === 'out_for_delivery' ? '&#9679;' : '3') ?></span><div><h5><?= e($active['stage']) ?></h5><small>Rider <?= e($active['rider']) ?> &middot; scheduled <?= e($active['scheduled']) ?></small></div></li>
          <li class="<?= $active['raw_status'] === 'delivered' ? 'done' : '' ?>"><span class="dot"><?= $active['raw_status'] === 'delivered' ? '&#10003;' : '4' ?></span><div><h5>Delivered</h5><small>Please prepare your empty containers.</small></div></li>
        </ul>
        <?php if ($canCancel): ?>
          <form method="post" action="delivery.php" style="margin-top:1rem" onsubmit="return confirm('Cancel this delivery request?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" value="<?= (int)$active['db_id'] ?>">
            <button class="btn btn--ghost btn--sm" type="submit">Cancel this request</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card__head"><h3>My delivery history</h3></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Order</th><th>Items</th><th>Schedule</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$mine): ?><tr><td colspan="4" class="empty">No delivery history yet.</td></tr><?php endif; ?>
        <?php foreach ($mine as $d): ?>
          <tr><td><strong><?= e($d['id']) ?></strong></td><td><?= e($d['items']) ?></td><td><?= e($d['scheduled']) ?></td>
          <td><span class="<?= badge_class($d['status']) ?>"><?= e($d['status']) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
