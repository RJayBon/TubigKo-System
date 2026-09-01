<?php
$BASE = '..'; $ROLE = 'admin'; $PAGE_TITLE = 'Manage Customer Account';
require_once __DIR__ . '/../includes/auth.php';
require_login('admin');
require_once __DIR__ . '/../includes/data.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $phone    = trim($_POST['phone'] ?? '');
            $address  = trim($_POST['address'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            if ($fullName === '' || $email === '' || strlen($password) < 8) {
                flash_set('error', 'Please provide a full name, email, and a password of at least 8 characters.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash_set('error', 'Please enter a valid email address.');
            } elseif (db_one('SELECT id FROM users WHERE email = ?', [$email])) {
                flash_set('error', 'A customer with that email already exists.');
            } else {
                $base = strtolower(preg_replace('/[^a-z0-9._-]/i', '', strstr($email, '@', true) ?: 'user')) ?: 'user';
                $username = $base; $i = 1;
                while (db_one('SELECT id FROM users WHERE username = ?', [$username])) { $username = $base . $i; $i++; }
                $ok = db_exec(
                    'INSERT INTO users (full_name, username, email, password, phone, address, role, status) VALUES (?,?,?,?,?,?,\'customer\',\'active\')',
                    [$fullName, $username, $email, password_hash($password, PASSWORD_DEFAULT), $phone, $address],
                    $err
                );
                flash_set($ok ? 'success' : 'error', $ok ? "Customer added (username: $username)." : ($err ?: 'Could not add customer.'));
            }
        } elseif ($action === 'activate' || $action === 'deactivate') {
            $id = (int)($_POST['id'] ?? 0);
            $status = $action === 'activate' ? 'active' : 'inactive';
            $ok = update_customer_status($id, $status, $err);
            flash_set($ok ? 'success' : 'error', $ok ? 'Customer status updated.' : ($err ?: 'Update failed.'));
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $ok = delete_customer($id, $err);
            flash_set($ok ? 'success' : 'error', $ok ? 'Customer deleted.' : ($err ?: 'Delete failed.'));
        }
    }
    header('Location: customers.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
$CUSTOMERS = load_customers();
?>
<section class="card">
  <div class="card__head">
    <h3>View List Customer Info</h3>
    <div class="spacer toolbar no-print">
      <input type="search" data-search="customerTable" placeholder="Search name, email, address...">
      <select data-filter="customerTable">
        <option value="">All status</option><option value="Active">Active</option><option value="Inactive">Inactive</option>
      </select>
      <button class="btn btn--ghost btn--sm" onclick="exportTable('customerTable','tubigko-customers')">Export Excel File</button>
      <button class="btn btn--sm" onclick="printPage()">Print</button>
      <button class="btn btn--sm" onclick="openModal('addCustomerModal')">Add customer</button>
    </div>
  </div>
  <div class="table-wrap">
    <table id="customerTable">
      <thead><tr><th>ID</th><th>Name</th><th>Contact</th><th>Address</th><th>Joined</th><th>Orders</th><th>Status</th><th class="no-print">Action</th></tr></thead>
      <tbody>
      <?php foreach ($CUSTOMERS as $c):
        $detail = json_encode([
          '__title' => $c['name'],
          'Customer ID' => $c['id'], 'Full name' => $c['name'], 'Mobile' => $c['phone'],
          'Email' => $c['email'], 'Address' => $c['address'], 'Date joined' => $c['joined'],
          'Total orders' => $c['orders'], 'Account status' => $c['status'],
        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?>
        <tr data-filter-value="<?= e($c['status']) ?>">
          <td><strong><?= e($c['id']) ?></strong></td>
          <td><?= e($c['name']) ?></td>
          <td><?= e($c['phone']) ?><br><small class="muted"><?= e($c['email']) ?></small></td>
          <td><?= e($c['address']) ?></td>
          <td><?= e($c['joined']) ?></td>
          <td><?= (int)$c['orders'] ?></td>
          <td><span class="<?= badge_class($c['status']) ?>"><?= e($c['status']) ?></span></td>
          <td class="no-print" style="display:flex;gap:.35rem;flex-wrap:wrap">
            <button class="btn btn--ghost btn--sm" data-detail='<?= $detail ?>'>View</button>
            <form method="post" action="customers.php" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$c['db_id'] ?>">
              <?php if ($c['status'] === 'Active'): ?>
                <input type="hidden" name="action" value="deactivate">
                <button class="btn btn--ghost btn--sm" type="submit">Deactivate</button>
              <?php else: ?>
                <input type="hidden" name="action" value="activate">
                <button class="btn btn--ghost btn--sm" type="submit">Activate</button>
              <?php endif; ?>
            </form>
            <form method="post" action="customers.php" style="display:inline" onsubmit="return confirm('Delete this customer? This cannot be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$c['db_id'] ?>">
              <button class="btn btn--ghost btn--sm" type="submit" style="color:var(--bad)">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
        <tr data-empty hidden><td colspan="8" class="empty">No customers match your search.</td></tr>
      </tbody>
    </table>
  </div>
</section>

<div class="modal" id="detailModal" hidden>
  <div class="modal__box">
    <div class="modal__head"><h3 id="detailTitle">Customer info</h3><button class="icon-btn" onclick="closeModal('detailModal')">&times;</button></div>
    <div class="modal__body" id="detailBody"></div>
  </div>
</div>

<div class="modal" id="addCustomerModal" hidden>
  <div class="modal__box">
    <div class="modal__head"><h3>Add new customer</h3><button class="icon-btn" onclick="closeModal('addCustomerModal')">&times;</button></div>
    <div class="modal__body">
      <form method="post" action="customers.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field"><label for="cname">Full name</label><input id="cname" name="full_name" required></div>
        <div class="form-row">
          <div class="field"><label for="cemail">Email</label><input id="cemail" name="email" type="email" required></div>
          <div class="field"><label for="cphone">Mobile number</label><input id="cphone" name="phone"></div>
        </div>
        <div class="field"><label for="caddress">Address</label><input id="caddress" name="address"></div>
        <div class="field"><label for="cpass">Temporary password</label><input id="cpass" name="password" type="password" minlength="8" required></div>
        <button class="btn btn--block" type="submit">Save customer</button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
