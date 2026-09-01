<?php
$BASE = '..'; $ROLE = 'admin'; $PAGE_TITLE = 'Manage Gallons';
require_once __DIR__ . '/../includes/auth.php';
require_login('admin');
require_once __DIR__ . '/../includes/data.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $action = $_POST['action'] ?? 'add';

        if ($action === 'add') {
            $name  = trim($_POST['name'] ?? '');
            $type  = trim($_POST['type'] ?? '');
            $size  = trim($_POST['size'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $desc  = trim($_POST['description'] ?? '');

            if ($name === '' || $price < 0 || $stock < 0) {
                flash_set('error', 'Please provide a valid gallon name, price, and stock.');
            } else {
                $ok = create_gallon(['name' => $name, 'type' => $type, 'size' => $size, 'price' => $price, 'stock' => $stock, 'description' => $desc], $err);
                flash_set($ok ? 'success' : 'error', $ok ? 'Gallon saved.' : ($err ?: 'Could not save gallon.'));
            }
        } elseif ($action === 'update_stock') {
            $id = (int)($_POST['id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $ok = db_exec('UPDATE gallons SET price_per_gallon = ?, stock = ? WHERE id = ?', [$price, $stock, $id], $err);
            flash_set($ok ? 'success' : 'error', $ok ? 'Gallon updated.' : ($err ?: 'Update failed.'));
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $used = db_one('SELECT COUNT(*) AS c FROM delivery_items WHERE gallon_id = ?', [$id]);
            if ($used && (int)$used['c'] > 0) {
                flash_set('error', 'This gallon has delivery history and cannot be deleted. Mark it unavailable instead.');
            } else {
                $ok = db_exec('DELETE FROM gallons WHERE id = ?', [$id], $err);
                flash_set($ok ? 'success' : 'error', $ok ? 'Gallon deleted.' : ($err ?: 'Delete failed.'));
            }
        }
    }
    header('Location: gallons.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
$GALLONS = load_gallons();
?>
<section class="card">
  <div class="card__head">
    <h3>List Per Gallon</h3>
    <div class="spacer toolbar no-print">
      <input type="search" data-search="gallonTable" placeholder="Search gallon...">
      <select data-filter="gallonTable">
        <option value="">All types</option>
        <?php foreach (array_unique(array_column($GALLONS,'type')) as $t): ?>
          <option value="<?= e($t) ?>"><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--ghost btn--sm" onclick="exportTable('gallonTable','tubigko-gallons')">Export Excel File</button>
      <button class="btn btn--sm" onclick="openModal('addGallonModal')">Add gallon</button>
    </div>
  </div>
  <div class="table-wrap">
    <table id="gallonTable">
      <thead><tr><th>Code</th><th>Gallon</th><th>Type</th><th>Size</th><th>Price</th><th>Stock</th><th class="no-print">Action</th></tr></thead>
      <tbody>
      <?php foreach ($GALLONS as $g):
        $detail = json_encode([
          '__title' => $g['name'],
          'Gallon code' => $g['id'], 'Name' => $g['name'], 'Water type' => $g['type'],
          'Size' => $g['size'], 'Price' => 'PHP ' . number_format($g['price'], 2),
          'Available stock' => $g['stock'], 'Description' => $g['desc'],
        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?>
        <tr data-filter-value="<?= e($g['type']) ?>">
          <td><strong><?= e($g['id']) ?></strong></td>
          <td><?= e($g['name']) ?><br><small class="muted"><?= e($g['desc']) ?></small></td>
          <td><?= e($g['type']) ?></td>
          <td><?= e($g['size']) ?></td>
          <td><?= peso($g['price']) ?></td>
          <td><span class="<?= $g['stock'] < 50 ? 'badge badge--warn' : 'badge badge--ok' ?>"><?= $g['stock'] ?> pcs</span></td>
          <td class="no-print" style="display:flex;gap:.35rem;flex-wrap:wrap">
            <button class="btn btn--ghost btn--sm" data-detail='<?= $detail ?>'>View</button>
            <button class="btn btn--ghost btn--sm" onclick='openEditGallon(<?= (int)$g["db_id"] ?>, <?= json_encode((string)$g["price"]) ?>, <?= json_encode((string)$g["stock"]) ?>)'>Edit</button>
            <form method="post" action="gallons.php" style="display:inline" onsubmit="return confirm('Delete this gallon?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$g['db_id'] ?>">
              <button class="btn btn--ghost btn--sm" type="submit" style="color:var(--bad)">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
        <tr data-empty hidden><td colspan="7" class="empty">No gallons match your search.</td></tr>
      </tbody>
    </table>
  </div>
</section>

<div class="modal" id="detailModal" hidden>
  <div class="modal__box">
    <div class="modal__head"><h3 id="detailTitle">Gallon details</h3><button class="icon-btn" onclick="closeModal('detailModal')">&times;</button></div>
    <div class="modal__body" id="detailBody"></div>
  </div>
</div>

<div class="modal" id="addGallonModal" hidden>
  <div class="modal__box">
    <div class="modal__head"><h3>Add new gallon</h3><button class="icon-btn" onclick="closeModal('addGallonModal')">&times;</button></div>
    <div class="modal__body">
      <form method="post" action="gallons.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field"><label for="gname">Gallon name</label><input id="gname" name="name" required></div>
        <div class="form-row">
          <div class="field"><label for="gtype">Water type</label>
            <select id="gtype" name="type"><option>Purified</option><option>Mineral</option><option>Alkaline</option><option>Distilled</option><option>Container</option></select>
          </div>
          <div class="field"><label for="gsize">Size</label>
            <select id="gsize" name="size"><option>5 Gallons</option><option>1 Gallon</option></select>
          </div>
        </div>
        <div class="form-row">
          <div class="field"><label for="gprice">Price (PHP)</label><input id="gprice" name="price" type="number" min="0" step="1" required></div>
          <div class="field"><label for="gstock">Stock</label><input id="gstock" name="stock" type="number" min="0" required></div>
        </div>
        <div class="field"><label for="gdesc">Description</label><textarea id="gdesc" name="description"></textarea></div>
        <button class="btn btn--block" type="submit">Save gallon</button>
      </form>
    </div>
  </div>
</div>

<div class="modal" id="editGallonModal" hidden>
  <div class="modal__box">
    <div class="modal__head"><h3>Update price &amp; stock</h3><button class="icon-btn" onclick="closeModal('editGallonModal')">&times;</button></div>
    <div class="modal__body">
      <form method="post" action="gallons.php" id="editGallonForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_stock">
        <input type="hidden" name="id" id="editGallonId">
        <div class="form-row">
          <div class="field"><label for="editPrice">Price (PHP)</label><input id="editPrice" name="price" type="number" min="0" step="0.01" required></div>
          <div class="field"><label for="editStock">Stock</label><input id="editStock" name="stock" type="number" min="0" required></div>
        </div>
        <button class="btn btn--block" type="submit">Save changes</button>
      </form>
    </div>
  </div>
</div>
<script>
function openEditGallon(id, price, stock) {
  document.getElementById('editGallonId').value = id;
  document.getElementById('editPrice').value = price;
  document.getElementById('editStock').value = stock;
  openModal('editGallonModal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
