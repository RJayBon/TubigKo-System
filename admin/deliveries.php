<?php
$BASE = '..'; $ROLE = 'admin'; $PAGE_TITLE = 'Manage Delivery Process';
require_once __DIR__ . '/../includes/auth.php';
require_login('admin');
require_once __DIR__ . '/../includes/data.php';

$statusLabels = [
    'confirmed'        => 'Confirmed',
    'out_for_delivery' => 'Out for delivery',
    'delivered'        => 'Delivered',
    'cancelled'        => 'Cancelled',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $rider = trim($_POST['rider'] ?? '');

        if (isset($statusLabels[$status])) {
            $delivery = db_one('SELECT * FROM deliveries WHERE id = ?', [$id]);
            $ok = update_delivery_status($id, $status, $rider !== '' ? $rider : null, $err);

            if ($ok && $delivery) {
                $messages = [
                    'confirmed'        => "Your order {$delivery['order_code']} has been confirmed and is being prepared.",
                    'out_for_delivery' => "Your order {$delivery['order_code']} is out for delivery" . ($rider !== '' ? " with rider $rider" : '') . ". Please prepare your empty containers.",
                    'delivered'        => "Your order {$delivery['order_code']} has been delivered. Thank you for choosing TubigKo!",
                    'cancelled'        => "Your order {$delivery['order_code']} has been cancelled.",
                ];
                create_notification((int)$delivery['customer_id'], 'Customer', 'Delivery update: ' . $statusLabels[$status], $messages[$status], 'Delivery', $notifErr);
            }

            flash_set($ok ? 'success' : 'error', $ok ? 'Delivery updated.' : ($err ?: 'Update failed.'));
        }
    }
    header('Location: deliveries.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
$DELIVERIES = load_deliveries();
$ongoing = array_values(array_filter($DELIVERIES, fn($d) => $d['status'] === 'Ongoing'));
$delivered = array_values(array_filter($DELIVERIES, fn($d) => $d['status'] !== 'Ongoing'));
?>
<div class="tabs no-print" data-tabs="del">
  <button class="tab is-active" data-tab="ongoing">Ongoing (<?= count($ongoing) ?>)</button>
  <button class="tab" data-tab="delivered">Delivered / Cancelled (<?= count($delivered) ?>)</button>
</div>

<section class="card tabpanel" data-tabpanel="del" data-panel="ongoing" style="margin-top:1rem">
  <div class="card__head">
    <h3>Ongoing deliveries</h3>
    <div class="spacer toolbar no-print">
      <input type="search" data-search="ongoingTable" placeholder="Search order, customer, rider...">
      <button class="btn btn--ghost btn--sm" onclick="exportTable('ongoingTable','tubigko-ongoing-deliveries')">Export Excel File</button>
      <button class="btn btn--sm" onclick="printPage()">Print</button>
    </div>
  </div>
  <div class="table-wrap">
    <table id="ongoingTable">
      <thead><tr><th>Order</th><th>Customer</th><th>Address</th><th>Items</th><th>Rider</th><th>Schedule</th><th>Stage</th><th class="no-print">Action</th></tr></thead>
      <tbody>
      <?php foreach ($ongoing as $d): ?>
        <tr>
          <td><strong><?= e($d['id']) ?></strong></td><td><?= e($d['customer']) ?></td>
          <td><?= e($d['address']) ?></td><td><?= e($d['items']) ?></td>
          <td><?= e($d['rider']) ?></td><td><?= e($d['scheduled']) ?></td>
          <td data-status-cell><span class="badge badge--warn"><?= e($d['stage']) ?></span></td>
          <td class="no-print">
            <button class="btn btn--sm" onclick='openDeliveryUpdate(<?= (int)$d["db_id"] ?>, <?= json_encode($d["id"]) ?>, <?= json_encode($d["rider"] === "Not yet assigned" ? "" : $d["rider"]) ?>)'>Update</button>
          </td>
        </tr>
      <?php endforeach; ?>
        <tr data-empty hidden><td colspan="8" class="empty">No ongoing deliveries match your search.</td></tr>
      </tbody>
    </table>
  </div>
</section>

<section class="card tabpanel" data-tabpanel="del" data-panel="delivered" style="margin-top:1rem" hidden>
  <div class="card__head">
    <h3>Delivered / cancelled orders</h3>
    <div class="spacer toolbar no-print">
      <input type="search" data-search="deliveredTable" placeholder="Search delivered orders...">
      <button class="btn btn--ghost btn--sm" onclick="exportTable('deliveredTable','tubigko-delivered')">Export Excel File</button>
      <button class="btn btn--sm" onclick="printPage()">Print</button>
    </div>
  </div>
  <div class="table-wrap">
    <table id="deliveredTable">
      <thead><tr><th>Order</th><th>Customer</th><th>Address</th><th>Items</th><th>Rider</th><th>Completed</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($delivered as $d): ?>
        <tr>
          <td><strong><?= e($d['id']) ?></strong></td><td><?= e($d['customer']) ?></td>
          <td><?= e($d['address']) ?></td><td><?= e($d['items']) ?></td>
          <td><?= e($d['rider']) ?></td><td><?= e($d['scheduled']) ?></td>
          <td><span class="<?= badge_class($d['status']) ?>"><?= e($d['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
        <tr data-empty hidden><td colspan="7" class="empty">No delivered orders match your search.</td></tr>
      </tbody>
    </table>
  </div>
</section>

<div class="modal" id="updateDeliveryModal" hidden>
  <div class="modal__box">
    <div class="modal__head"><h3 id="updateDeliveryTitle">Update delivery</h3><button class="icon-btn" onclick="closeModal('updateDeliveryModal')">&times;</button></div>
    <div class="modal__body">
      <form method="post" action="deliveries.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="updateDeliveryId">
        <div class="field"><label for="updateRider">Assign rider</label><input id="updateRider" name="rider" placeholder="Rider name"></div>
        <div class="field">
          <label for="updateStatus">New status</label>
          <select id="updateStatus" name="status">
            <?php foreach ($statusLabels as $val => $label): ?>
              <option value="<?= e($val) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn--block" type="submit">Save update</button>
      </form>
    </div>
  </div>
</div>
<script>
function openDeliveryUpdate(id, code, rider) {
  document.getElementById('updateDeliveryId').value = id;
  document.getElementById('updateDeliveryTitle').textContent = 'Update ' + code;
  document.getElementById('updateRider').value = rider || '';
  openModal('updateDeliveryModal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
