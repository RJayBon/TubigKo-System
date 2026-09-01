<?php
$BASE = '..'; $ROLE = 'admin'; $PAGE_TITLE = 'Manage Payment';
require_once __DIR__ . '/../includes/auth.php';
require_login('admin');
require_once __DIR__ . '/../includes/data.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['paid', 'failed', 'pending', 'refunded'], true)) {
            $ok = update_payment_status($id, $status, $err);
            flash_set($ok ? 'success' : 'error', $ok ? 'Payment status updated.' : ($err ?: 'Update failed.'));
        }
    }
    header('Location: payments.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
$PAYMENT_METHODS = load_payment_methods();
$PAYMENTS = load_payments();
?>
<div class="tabs no-print" data-tabs="pay">
  <button class="tab is-active" data-tab="methods">List Of Payment Method</button>
  <button class="tab" data-tab="status">List Of Payment Status</button>
</div>

<section class="card tabpanel" data-tabpanel="pay" data-panel="methods" style="margin-top:1rem">
  <div class="card__head">
    <h3>List Of Payment Method</h3>
    <div class="spacer toolbar no-print">
      <button class="btn btn--ghost btn--sm" onclick="exportTable('methodTable','tubigko-payment-methods')">Export Excel File</button>
      <button class="btn btn--sm" onclick="printPage()">Print</button>
    </div>
  </div>
  <div class="table-wrap">
    <table id="methodTable">
      <thead><tr><th>Code</th><th>Method</th><th>Provider</th><th>Service fee</th><th>Availability</th></tr></thead>
      <tbody>
      <?php foreach ($PAYMENT_METHODS as $m): ?>
        <tr>
          <td><strong><?= e($m['id']) ?></strong></td>
          <td><?= e($m['method']) ?></td>
          <td><?= e($m['provider']) ?></td>
          <td><?= $m['fee'] ? peso($m['fee']) : 'No fee' ?></td>
          <td><span class="<?= badge_class($m['status']) ?>"><?= e($m['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card tabpanel" data-tabpanel="pay" data-panel="status" style="margin-top:1rem" hidden>
  <div class="card__head">
    <h3>List Of Payment Status</h3>
    <div class="spacer toolbar no-print">
      <input type="search" data-search="statusTable" placeholder="Search customer or order...">
      <select data-filter="statusTable">
        <option value="">All status</option><option value="Paid">Paid</option><option value="Pending">Pending</option><option value="Failed">Failed</option><option value="Refunded">Refunded</option>
      </select>
      <button class="btn btn--ghost btn--sm" onclick="exportTable('statusTable','tubigko-payment-status')">Export Excel File</button>
      <button class="btn btn--sm" onclick="printPage()">Print</button>
    </div>
  </div>
  <div class="table-wrap">
    <table id="statusTable">
      <thead><tr><th>Payment ID</th><th>Order</th><th>Customer</th><th>Method</th><th>Amount</th><th>Date</th><th>Status</th><th class="no-print">Action</th></tr></thead>
      <tbody>
      <?php foreach ($PAYMENTS as $p): ?>
        <tr data-filter-value="<?= e($p['status']) ?>">
          <td><strong><?= e($p['id']) ?></strong></td>
          <td><?= e($p['order']) ?></td>
          <td><?= e($p['customer']) ?></td>
          <td><?= e($p['method']) ?><?= $p['reference'] ? '<br><small class="muted">Ref: ' . e($p['reference']) . '</small>' : '' ?></td>
          <td><?= peso($p['amount']) ?></td>
          <td><?= e($p['date']) ?></td>
          <td><span class="<?= badge_class($p['status']) ?>"><?= e($p['status']) ?></span></td>
          <td class="no-print" style="display:flex;gap:.35rem;flex-wrap:wrap">
            <?php if ($p['raw_status'] !== 'paid'): ?>
              <form method="post" action="payments.php" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$p['db_id'] ?>">
                <input type="hidden" name="status" value="paid">
                <button class="btn btn--sm" type="submit">Mark paid</button>
              </form>
            <?php endif; ?>
            <?php if ($p['raw_status'] !== 'failed'): ?>
              <form method="post" action="payments.php" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$p['db_id'] ?>">
                <input type="hidden" name="status" value="failed">
                <button class="btn btn--ghost btn--sm" type="submit">Mark failed</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
        <tr data-empty hidden><td colspan="8" class="empty">No payments match your search.</td></tr>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
