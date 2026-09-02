<?php
$BASE = '..'; $ROLE = 'customer'; $PAGE_TITLE = 'Payment';
require_once __DIR__ . '/../includes/auth.php';
require_login('customer');
require_once __DIR__ . '/../includes/data.php';

$me = current_user();
$enabled = load_payment_methods(true);
$enabledMethodNames = array_column($enabled, 'method');

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
    if (!in_array($method, $enabledMethodNames, true)) {
        flash_set('error', 'That payment method is not currently available. Please choose another method.');
        header('Location: payment.php');
        exit;
    }

    // Test-only payment details are validated for the selected panel but are
    // deliberately never passed to create_order() or stored in the database.
    $methodKey = strtolower($method);
    if (str_contains($methodKey, 'card')) {
        $cardName = trim($_POST['test_card_name'] ?? '');
        $cardNumber = preg_replace('/\D+/', '', (string)($_POST['test_card_number'] ?? ''));
        $cardCvv = preg_replace('/\D+/', '', (string)($_POST['test_card_cvv'] ?? ''));
        if ($cardName === '' || !preg_match('/^\d{13,19}$/', $cardNumber) || !preg_match('/^\d{3,4}$/', $cardCvv)) {
            flash_set('error', 'For the card test panel, enter a cardholder name, a 13–19 digit test card number, and a 3–4 digit test CVV. No card data is stored.');
            header('Location: payment.php');
            exit;
        }
    } elseif (str_contains($methodKey, 'cash')) {
        // Cash on delivery needs no online payment details in test mode.
    } elseif (str_contains($methodKey, 'gcash') || str_contains($methodKey, 'maya') || str_contains($methodKey, 'paymaya') || str_contains($methodKey, 'bank')) {
        $testReference = trim($_POST['test_payment_reference'] ?? '');
        if ($testReference === '') {
            flash_set('error', 'Enter the test payment reference shown after completing the demo payment step.');
            header('Location: payment.php');
            exit;
        }
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
        <?php foreach ($enabled as $i => $m):
          $methodKey = strtolower($m['method']);
          $kind = str_contains($methodKey, 'card') ? 'card' : (str_contains($methodKey, 'cash') ? 'cash' : ((str_contains($methodKey, 'gcash') || str_contains($methodKey, 'maya') || str_contains($methodKey, 'paymaya')) ? 'ewallet' : (str_contains($methodKey, 'bank') ? 'bank' : 'other')));
        ?>
          <label class="method">
            <input type="radio" name="payment_method" value="<?= e($m['method']) ?>" data-payment-kind="<?= e($kind) ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <span>
              <strong><?= e($m['method']) ?></strong><br>
              <small class="muted"><?= e($m['provider']) ?> &middot; <?= $m['fee'] ? 'Service fee ' . peso($m['fee']) : 'No service fee' ?></small>
            </span>
          </label>
        <?php endforeach; ?>
        <div class="payment-test-notice"><strong>Test mode only</strong><span>No real payment is processed, and card details entered below are not stored.</span></div>
        <div class="payment-panel" data-payment-panel="ewallet" hidden>
          <h4>Demo e-wallet payment</h4><p class="muted">Open the demo link or scan the placeholder QR area. This does not connect to GCash or Maya.</p><div class="payment-demo-qr" aria-label="Placeholder test QR code">TEST<br>QR</div><p><a class="btn btn--ghost btn--sm" href="https://example.test/demo-wallet-payment" target="_blank" rel="noopener">Open demo payment link</a></p><div class="field"><label for="testPaymentReference">Test payment reference</label><input id="testPaymentReference" name="test_payment_reference" placeholder="DEMO-GCASH-001" autocomplete="off"></div>
        </div>
        <div class="payment-panel" data-payment-panel="bank" hidden>
          <h4>Demo bank transfer</h4><p class="muted">Use the displayed demo bank instructions. No transfer is initiated.</p><div class="payment-demo-instructions"><strong>TubigKo Demo Bank</strong><span>Account ending: 0000</span><span>Reference: your test reference</span></div><div class="field"><label for="testBankReference">Test transfer reference</label><input id="testBankReference" name="test_payment_reference" placeholder="DEMO-BANK-001" autocomplete="off"></div>
        </div>
        <div class="payment-panel" data-payment-panel="card" hidden>
          <h4>Demo card details</h4><p class="muted">Use test values only. This project does not connect to a card processor or save card information.</p><div class="field"><label for="testCardName">Name on card</label><input id="testCardName" name="test_card_name" autocomplete="off"></div><div class="field"><label for="testCardNumber">Card number</label><input id="testCardNumber" name="test_card_number" inputmode="numeric" maxlength="19" autocomplete="off" placeholder="4111111111111111"></div><div class="form-row"><div class="field"><label for="testCardCvv">CVV</label><input id="testCardCvv" name="test_card_cvv" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="123"></div></div>
        </div>
        <div class="payment-panel" data-payment-panel="cash" hidden><h4>Cash on delivery</h4><p class="muted">No online payment details are needed. The customer will pay upon delivery in this test flow.</p></div>
        <div class="payment-panel" data-payment-panel="other" hidden><h4>Test payment details</h4><p class="muted">Enter a demo reference for this payment method. No real payment is processed.</p><div class="field"><label for="testOtherReference">Test payment reference</label><input id="testOtherReference" name="test_payment_reference" placeholder="DEMO-PAY-001" autocomplete="off"></div></div>
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
