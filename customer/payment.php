<?php
$BASE = '..'; $ROLE = 'customer'; $PAGE_TITLE = 'Payment';
require_once __DIR__ . '/../includes/auth.php';
require_login('customer');
require_once __DIR__ . '/../includes/data.php';

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
        header('Location: payment.php');
        exit;
    }

    $address = trim($_POST['address'] ?? '');
    $date    = trim($_POST['delivery_date'] ?? '');
    $time    = trim($_POST['delivery_time'] ?? '');
    $note    = trim($_POST['note'] ?? '');
    $method  = trim($_POST['payment_method'] ?? '');
    $cartJson = $_POST['cart_json'] ?? '[]';
    $cart = json_decode($cartJson, true);

    if ($address === '' || $date === '' || $time === '' || $method === '') {
        flash_set('error', 'Please complete the delivery address, date, time, and payment method.');
        header('Location: payment.php');
        exit;
    }
    if (!is_array($cart) || empty($cart)) {
        flash_set('error', 'Your cart is empty. Please select gallons first.');
        header('Location: gallons.php');
        exit;
    }

    $orderCode = create_order((int)$me['id'], $address, $date, $time, $note ?: null, $cart, $method, null, $err);

    if ($orderCode) {
        flash_set('success', "Payment submitted. Order $orderCode is being prepared.");
        header('Location: delivery.php?clear_cart=1');
        exit;
    }

    flash_set('error', $err ?: 'Could not submit your order.');
    header('Location: payment.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
$enabled = load_payment_methods(true);
?>
<div class="stepper">
  <div class="step is-done"><b>&#10003;</b> Select gallon</div>
  <div class="step is-active"><b>2</b> Payment</div>
  <div class="step"><b>3</b> Delivery</div>
</div>

<div class="grid grid--2">
  <section class="card">
    <div class="card__head"><h3>Select Payment Method</h3></div>
    <div class="card__body">
      <form method="post" action="payment.php" id="checkoutForm">
        <?= csrf_field() ?>
        <input type="hidden" name="cart_json" id="cartJsonField" value="[]">
        <?php foreach ($enabled as $i => $m): ?>
          <label class="method">
            <input type="radio" name="payment_method" value="<?= e($m['method']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <span>
              <strong><?= e($m['method']) ?></strong><br>
              <small class="muted"><?= e($m['provider']) ?> &middot; <?= $m['fee'] ? 'Service fee ' . peso($m['fee']) : 'No service fee' ?></small>
            </span>
          </label>
        <?php endforeach; ?>
        <div class="field" style="margin-top:1rem"><label for="addr">Delivery address</label><input id="addr" name="address" value="<?= e($me['address'] ?? '') ?>" required></div>
        <div class="form-row">
          <div class="field"><label for="date">Preferred delivery date</label><input id="date" name="delivery_date" type="date" min="<?= date('Y-m-d') ?>" required></div>
          <div class="field"><label for="time">Preferred time</label>
            <select id="time" name="delivery_time"><option>8:00 AM - 10:00 AM</option><option>10:00 AM - 12:00 NN</option><option>1:00 PM - 3:00 PM</option><option>3:00 PM - 5:00 PM</option></select>
          </div>
        </div>
        <div class="field"><label for="note">Note for the rider (optional)</label><input id="note" name="note"></div>
        <button class="btn btn--block" type="submit" id="checkoutSubmit">Submit</button>
      </form>
    </div>
  </section>

  <aside class="card" style="align-self:start">
    <div class="card__head"><h3>Order summary</h3><span class="spacer badge" id="cartCount">0</span></div>
    <div class="card__body">
      <div id="cartLines"></div>
      <div class="cart-total"><span>Amount due</span><span id="cartTotal">&#8369;0.00</span></div>
      <a class="btn btn--ghost btn--block" href="gallons.php" style="margin-top:1rem">Edit selection</a>
    </div>
  </aside>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
