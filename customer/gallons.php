<?php
$BASE = '..'; $ROLE = 'customer'; $PAGE_TITLE = 'Gallon';
require_once __DIR__ . '/../includes/auth.php';
require_login('customer');
require_once __DIR__ . '/../includes/data.php';
include __DIR__ . '/../includes/header.php';

$GALLONS = load_gallons();
?>
<div class="stepper">
  <div class="step is-active"><b>1</b> Select gallon</div>
  <div class="step"><b>2</b> Payment</div>
  <div class="step"><b>3</b> Delivery</div>
</div>

<div class="grid gallon-layout">
  <section class="card">
    <div class="card__head">
      <h3>View Per Gallon</h3>
      <div class="spacer toolbar">
        <select id="typeFilter">
          <option value="">All types</option>
          <?php foreach (array_unique(array_column($GALLONS,'type')) as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="card__body grid grid--cards" id="gallonGrid">
      <?php foreach ($GALLONS as $g):
        $item = json_encode(['id'=>$g['db_id'],'name'=>$g['name'],'price'=>$g['price']], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?>
        <article class="gcard" data-type="<?= e($g['type']) ?>">
          <div class="gcard__art">&#128167;</div>
          <h4><?= e($g['name']) ?></h4>
          <p class="gcard__meta"><?= e($g['type']) ?> &middot; <?= e($g['size']) ?></p>
          <p class="gcard__meta"><?= e($g['desc']) ?></p>
          <p class="gcard__price"><?= peso($g['price']) ?></p>
          <p class="gcard__meta"><?= $g['stock'] ?> available</p>
          <?php if ($g['stock'] > 0 && $g['status'] === 'available'): ?>
            <button class="btn btn--sm" data-add-gallon='<?= $item ?>'>Select</button>
          <?php else: ?>
            <button class="btn btn--sm" disabled>Out of stock</button>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="card gallon-selection">
    <div class="card__head"><h3>My selection</h3><span class="spacer badge" id="cartCount">0</span></div>
    <div class="card__body">
      <div id="cartLines"></div>
      <div class="cart-total"><span>Total</span><span id="cartTotal">&#8369;0.00</span></div>
      <a class="btn btn--block" href="payment.php" style="margin-top:1rem">Continue to payment</a>
      <p class="muted" style="font-size:.78rem;margin-top:.7rem">Your selection is kept in this browser until you submit it on the payment step.</p>
    </div>
  </aside>
</div>

<script>
document.getElementById('typeFilter').addEventListener('change', function () {
  var v = this.value;
  document.querySelectorAll('#gallonGrid .gcard').forEach(function (c) {
    c.hidden = v && c.getAttribute('data-type') !== v;
  });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
