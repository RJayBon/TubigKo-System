<?php
$BASE = '..'; $ROLE = 'admin'; $PAGE_TITLE = 'Dashboard';
require_once __DIR__ . '/../includes/auth.php';
require_login('admin');
require_once __DIR__ . '/../includes/data.php';
include __DIR__ . '/../includes/header.php';

$CUSTOMERS = load_customers();
$GALLONS   = load_gallons();
$PAYMENTS  = load_payments();
$DELIVERIES = load_deliveries();

$ongoing = array_values(array_filter($DELIVERIES, fn($d) => $d['status'] === 'Ongoing'));
$sales = array_sum(array_map(fn($p) => $p['raw_status'] === 'paid' ? $p['amount'] : 0, $PAYMENTS));
$pending = count(array_filter($PAYMENTS, fn($p) => $p['raw_status'] === 'pending'));
?>
<div class="grid grid--stats">
  <div class="stat"><p class="stat__label">Registered customers</p><p class="stat__value"><?= count($CUSTOMERS) ?></p><p class="stat__hint"><?= count(array_filter($CUSTOMERS, fn($c)=>$c['status']==='Active')) ?> active accounts</p></div>
  <div class="stat"><p class="stat__label">Gallons in stock</p><p class="stat__value"><?= array_sum(array_column($GALLONS,'stock')) ?></p><p class="stat__hint"><?= count($GALLONS) ?> gallon variants</p></div>
  <div class="stat"><p class="stat__label">Collected payments</p><p class="stat__value"><?= peso($sales) ?></p><p class="stat__hint"><?= $pending ?> pending payments</p></div>
  <div class="stat"><p class="stat__label">Ongoing deliveries</p><p class="stat__value"><?= count($ongoing) ?></p><p class="stat__hint"><?= count($DELIVERIES) - count($ongoing) ?> delivered / cancelled</p></div>
</div>

<div class="grid grid--2" style="margin-top:1.2rem">
  <section class="card">
    <div class="card__head"><h3>Ongoing deliveries</h3><a class="btn btn--ghost btn--sm spacer" href="deliveries.php">View all</a></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Order</th><th>Customer</th><th>Stage</th><th>Schedule</th></tr></thead>
        <tbody>
        <?php if (!$ongoing): ?><tr><td colspan="4" class="empty">No ongoing deliveries.</td></tr><?php endif; ?>
        <?php foreach (array_slice($ongoing, 0, 6) as $d): ?>
          <tr><td><strong><?= e($d['id']) ?></strong></td><td><?= e($d['customer']) ?></td>
          <td><span class="badge badge--warn"><?= e($d['stage']) ?></span></td><td><?= e($d['scheduled']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h3>Recent payments</h3><a class="btn btn--ghost btn--sm spacer" href="payments.php">View all</a></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Payment</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($PAYMENTS, 0, 5) as $p): ?>
          <tr><td><strong><?= e($p['id']) ?></strong></td><td><?= e($p['customer']) ?></td>
          <td><?= peso($p['amount']) ?></td><td><span class="<?= badge_class($p['status']) ?>"><?= e($p['status']) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<section class="card" style="margin-top:1.2rem">
  <div class="card__head"><h3>Gallon stock levels</h3><a class="btn btn--ghost btn--sm spacer" href="gallons.php">Manage gallons</a></div>
  <div class="card__body grid grid--cards">
    <?php foreach ($GALLONS as $g): ?>
      <div class="gcard">
        <div class="gcard__art">&#128167;</div>
        <h4><?= e($g['name']) ?></h4>
        <p class="gcard__meta"><?= e($g['type']) ?> &middot; <?= e($g['size']) ?></p>
        <p class="gcard__price"><?= peso($g['price']) ?></p>
        <p class="gcard__meta"><?= $g['stock'] ?> in stock</p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
