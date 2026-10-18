<?php
require '../config.php';
require_once '../paymongo.php';
$u = require_role('client');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $conn->prepare("SELECT b.*, s.name service FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.id=? AND b.client_id=?");
$stmt->bind_param('ii', $id, $u['id']);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();
if (!$b) {
    http_response_code(404);
    $title = 'Checkout unavailable';
    require '../layout.php';
    echo '<div class="card"><h1>Booking not found</h1><p class="muted">This booking could not be found on your account.</p><a class="btn" href="bookings.php">Back to bookings</a></div>';
    require '../footer.php';
    exit;
}

if ($b['payment_method'] === 'cash') {
    flash('This booking is set to cash after the job. No online checkout is needed.');
    header('Location: booking.php?id=' . $id);
    exit;
}
if ($b['status'] === 'cancelled' || $b['status'] === 'completed') {
    flash('This booking is not eligible for checkout.', 'error');
    header('Location: booking.php?id=' . $id);
    exit;
}
if (in_array($b['payment_status'], ['paid', 'demo_paid'], true)) {
    flash($b['payment_status'] === 'demo_paid'
        ? 'This booking has a payment simulation record only; no money was transferred.'
        : 'This booking is already paid. Your receipt is available from the booking details.');
    header('Location: receipt.php?id=' . $id);
    exit;
}

$provider = $b['payment_method'] === 'maya' ? 'Maya' : 'GCash';
$return = (string)($_GET['paymongo'] ?? '');
$payError = '';
$demoPaid = false;
$demoSessionKey = 'payment_demo_checkout';
$demoCheckout = $_SESSION[$demoSessionKey] ?? null;
if (!payment_demo_enabled() || paymongo_configured() || !is_array($demoCheckout)
    || (int)($demoCheckout['booking_id'] ?? 0) !== $id
    || ($demoCheckout['method'] ?? '') !== $b['payment_method']
    || (int)($demoCheckout['started_at'] ?? 0) < time() - 900) {
    unset($_SESSION[$demoSessionKey]);
    $demoCheckout = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_demo_payment'])) {
    verify_csrf();
    if (!payment_demo_enabled() || paymongo_configured()) {
        fail('Simulated payments are disabled. Use the configured PayMongo checkout.');
    }
    if (!in_array($b['payment_method'], ['gcash','maya'], true) || $b['payment_status'] !== 'unpaid'
        || !in_array($b['status'], ['pending','assigned'], true)) {
        fail('This booking is not eligible for a simulated wallet payment.');
    }
    $phone = preg_replace('/[\s()-]/', '', trim((string)($_POST['demo_phone'] ?? '')));
    if (!preg_match('/^(?:09\d{9}|\+639\d{9})$/', $phone)) {
        flash('Enter a valid Philippine mobile number for this simulation, such as 09171234567.', 'error');
        header('Location: pay.php?id=' . $id);
        exit;
    }
    $_SESSION[$demoSessionKey] = [
        'booking_id' => $id,
        'method' => $b['payment_method'],
        'started_at' => time(),
        'nonce' => bin2hex(random_bytes(16)),
        'step' => 'otp',
        'phone' => $phone,
        'otp' => (string)random_int(100000, 999999),
        'otp_attempts' => 0,
    ];
    header('Location: pay.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_demo_otp'])) {
    verify_csrf();
    if (!payment_demo_enabled() || paymongo_configured()
        || !is_array($demoCheckout) || ($demoCheckout['step'] ?? '') !== 'otp'
        || !isset($demoCheckout['nonce'], $demoCheckout['otp'])
        || !hash_equals((string)$demoCheckout['nonce'], (string)($_POST['demo_nonce'] ?? ''))) {
        fail('The simulated wallet session expired. Restart checkout and try again.');
    }
    if ((int)($demoCheckout['otp_attempts'] ?? 0) >= 5) {
        unset($_SESSION[$demoSessionKey]);
        fail('Too many incorrect demo verification codes. Restart checkout to try again.');
    }
    $submittedOtp = preg_replace('/\s+/', '', (string)($_POST['demo_otp'] ?? ''));
    if (!hash_equals((string)$demoCheckout['otp'], $submittedOtp)) {
        $_SESSION[$demoSessionKey]['otp_attempts'] = (int)$demoCheckout['otp_attempts'] + 1;
        flash('That demo verification code does not match. Try again.', 'error');
        header('Location: pay.php?id=' . $id);
        exit;
    }
    $_SESSION[$demoSessionKey]['step'] = 'authorize';
    header('Location: pay.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_demo_payment'])) {
    verify_csrf();
    unset($_SESSION[$demoSessionKey]);
    flash('Demo checkout cancelled. No payment was made.', 'error');
    header('Location: pay.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_demo_payment'])) {
    verify_csrf();
    if (!payment_demo_enabled() || paymongo_configured()) {
        fail('Simulated payments are disabled. Use the configured PayMongo checkout.');
    }
    if (!is_array($demoCheckout)
        || ($demoCheckout['step'] ?? '') !== 'authorize'
        || !isset($demoCheckout['nonce'])
        || !hash_equals((string)$demoCheckout['nonce'], (string)($_POST['demo_nonce'] ?? ''))) {
        fail('The simulated checkout expired or was not started. Reopen checkout and try again.');
    }
    if (!in_array($b['payment_method'], ['gcash','maya'], true) || $b['payment_status'] !== 'unpaid'
        || !in_array($b['status'], ['pending','assigned'], true)) {
        fail('This booking is not eligible for a simulated wallet payment.');
    }
    $reference = 'CH-DEMO-' . strtoupper($b['payment_method']) . '-' . $id . '-' . strtoupper(bin2hex(random_bytes(3)));
    $simulated = $conn->prepare("UPDATE bookings SET payment_status='demo_paid',payment_reference=?,paid_at=NOW() WHERE id=? AND client_id=? AND status IN ('pending','assigned') AND payment_method IN ('gcash','maya') AND payment_status='unpaid'");
    $simulated->bind_param('sii', $reference, $id, $u['id']);
    $simulated->execute();
    if ($simulated->affected_rows !== 1) fail('The booking changed before the simulated payment could be recorded. Refresh and try again.');
    unset($_SESSION[$demoSessionKey]);
    $methodName = $b['payment_method'] === 'maya' ? 'Maya' : 'GCash';
    if (!empty($b['worker_id'])) {
        notify_and_email((int)$b['worker_id'], "DEMO ONLY: simulated $methodName payment for booking #$id. No money was transferred.", 'Simulated payment recorded', 'worker/job.php?id=' . $id);
    }
    notify_and_email((int)$u['id'], "DEMO ONLY: simulated $methodName payment for booking #$id. No money was transferred and this is not a real receipt.", 'Simulated payment recorded', 'client/booking.php?id=' . $id);
    audit_log('payment_demo_completed', "SIMULATION ONLY: client {$u['name']} simulated $methodName payment of ₱{$b['total_price']} for booking #$id; no funds transferred");
    flash("Demo $methodName payment approved. No money was transferred.");
    header('Location: receipt.php?id=' . $id);
    exit;
}

function pay_save_checkout_id($conn, $bookingId, $clientId, $checkoutId) {
    $upd = $conn->prepare('UPDATE bookings SET paymongo_checkout_id=? WHERE id=? AND client_id=?');
    $upd->bind_param('sii', $checkoutId, $bookingId, $clientId);
    $upd->execute();
}

if ($return === 'cancel') {
    flash('Checkout was cancelled. You can try again when you are ready. The worker cannot start until payment is complete.', 'error');
}

if ($return === 'success' && !empty($b['paymongo_checkout_id']) && paymongo_configured()) {
    if (paymongo_sync_booking($b)) {
        flash("$provider payment received. Your receipt is ready.");
        header('Location: receipt.php?id=' . $id);
        exit;
    }
}

if ($return !== 'success' && $return !== 'cancel' && paymongo_configured()) {
    try {
        // Self-heal first: a stored checkout may already be paid even if the
        // customer never made it back to the success URL.
        if (!empty($b['paymongo_checkout_id']) && paymongo_sync_booking($b)) {
            flash("$provider payment received. Your receipt is ready.");
            header('Location: receipt.php?id=' . $id);
            exit;
        }
        $session = null;
        if (!empty($b['paymongo_checkout_id'])) {
            try {
                $session = paymongo_get_checkout($b['paymongo_checkout_id']);
                if (paymongo_session_expired($session) || paymongo_checkout_url($session) === '') {
                    $session = null;
                }
            } catch (Throwable $ex) {
                $session = null;
            }
        }
        if ($session === null) {
            $session = paymongo_create_checkout($b, $b['service']);
            $checkoutId = (string)($session['data']['id'] ?? '');
            if ($checkoutId !== '') {
                pay_save_checkout_id($conn, $id, $u['id'], $checkoutId);
            }
        }
        $url = paymongo_checkout_url($session);
        if ($url === '') {
            throw new RuntimeException('PayMongo did not return a checkout URL.');
        }
        header('Location: ' . $url);
        exit;
    } catch (Throwable $ex) {
        $payError = $ex->getMessage();
    }
}

$title = $provider . ' Checkout';
require '../layout.php';
?>
<section class="page-intro payment-page-intro">
  <span class="eyebrow"><?= payment_demo_enabled() && !paymongo_configured() ? 'PAYMENT SIMULATION' : 'SECURE CHECKOUT' ?></span>
  <h1><?= e($provider) ?> payment<span class="heading-period">.</span></h1>
  <p><?= payment_demo_enabled() && !paymongo_configured() ? 'Follow the simulated wallet steps to test checkout. This is not an official wallet screen and no real payment is made.' : 'Pay with ' . e($provider) . ' through PayMongo before your cleaner starts. You will be redirected to the official checkout page.' ?></p>
</section>
<div class="payment-checkout-layout">
  <section class="card payment-order-card">
    <span class="eyebrow">ORDER SUMMARY</span>
    <h2>Booking #<?= (int)$id ?></h2>
    <div class="payment-order-line"><span>Cleaning service</span><b><?= e($b['service']) ?></b></div>
    <div class="payment-order-line"><span>Scheduled for</span><b><?= date('M j, Y · g:i A', strtotime($b['schedule'])) ?></b></div>
    <div class="payment-order-line"><span>Payment timing</span><b>Before work starts</b></div>
    <div class="payment-order-total"><span>Total to pay</span><strong>₱<?= number_format((float)$b['total_price'], 2) ?></strong></div>
    <?php if ($return === 'success'): ?>
      <p class="payment-demo-note"><b>Payment is still confirming.</b> If you already paid, wait a moment and refresh this page. The worker cannot start until PayMongo confirms the payment.</p>
    <?php elseif (!paymongo_configured() && !payment_demo_enabled()): ?>
      <p class="payment-demo-note"><b>Payments are not configured.</b> Add your PayMongo secret key to <code>config.local.php</code> (copy it from <code>config.local.example.php</code>). Online checkout cannot run without it.</p>
    <?php elseif (!empty($payError)): ?>
      <p class="payment-demo-note"><b>Checkout could not start:</b> <?= e($payError) ?></p>
    <?php endif; ?>
  </section>
  <section class="card payment-form-card">
    <div class="provider-heading"><span class="provider-mark <?= $provider === 'Maya' ? 'maya' : 'gcash' ?>"><?= $provider === 'Maya' ? 'M' : 'G' ?></span><div><h2><?= e($provider) ?></h2><p><?= payment_demo_enabled() && !paymongo_configured() ? 'In-site payment simulation' : 'Processed by PayMongo' ?></p></div></div>
    <?php if (payment_demo_enabled() && !paymongo_configured() && $demoCheckout && ($demoCheckout['step'] ?? '') === 'otp'): ?>
      <div class="demo-wallet-shell <?= $provider === 'Maya' ? 'maya-wallet' : 'gcash-wallet' ?>">
        <div class="demo-wallet-top"><span><?= e($provider) ?></span><b>SIMULATION · STEP 2 OF 3</b></div>
        <h3>Verify your mobile number</h3>
        <p class="muted">A real SMS was not sent. Use the temporary demo code shown below to continue.</p>
        <div class="demo-wallet-row"><span>Code sent to</span><b>•••• ••• <?= e(substr((string)$demoCheckout['phone'], -4)) ?></b></div>
        <div class="demo-otp-box"><span>Simulation verification code</span><b><?= e($demoCheckout['otp']) ?></b><small>Shown here only for testing. This is not an SMS from <?= e($provider) ?>.</small></div>
        <?php if ((int)($demoCheckout['otp_attempts'] ?? 0) > 0): ?><p class="payment-demo-note">Incorrect attempts: <?= (int)$demoCheckout['otp_attempts'] ?> of 5.</p><?php endif; ?>
        <form method="post" class="payment-submit-form">
          <?= csrf_field() ?><input type="hidden" name="demo_nonce" value="<?= e($demoCheckout['nonce']) ?>">
          <label for="demoOtp">Enter the six-digit demo code</label>
          <input id="demoOtp" name="demo_otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
          <button class="btn payment-submit" type="submit" name="verify_demo_otp" value="1">Verify code</button>
        </form>
        <form method="post" class="payment-submit-form">
          <?= csrf_field() ?><button class="btn btn-sec payment-submit" type="submit" name="cancel_demo_payment" value="1">Cancel checkout</button>
        </form>
      </div>
    <?php elseif (payment_demo_enabled() && !paymongo_configured() && $demoCheckout && ($demoCheckout['step'] ?? '') === 'authorize'): ?>
      <div class="demo-wallet-shell">
        <div class="demo-wallet-top"><span><?= e($provider) ?></span><b>SIMULATION · STEP 3 OF 3</b></div>
        <h3>Review and confirm</h3>
        <p class="muted">This simulates the final wallet confirmation. It is not connected to a real <?= e($provider) ?> account.</p>
        <div class="demo-wallet-row"><span>Mobile wallet</span><b>•••• ••• <?= e(substr((string)$demoCheckout['phone'], -4)) ?></b></div>
        <div class="demo-wallet-row"><span>Paying</span><b>ChoreHive</b></div>
        <div class="demo-wallet-row"><span>Booking</span><b>#<?= (int)$id ?> · <?= e($b['service']) ?></b></div>
        <div class="demo-wallet-row demo-wallet-amount"><span>Amount</span><b>₱<?= number_format((float)$b['total_price'], 2) ?></b></div>
        <div class="payment-demo-note"><b>Simulation only:</b> no real wallet balance is checked or charged. No password, PIN, or real OTP is requested.</div>
        <form method="post" class="payment-submit-form" data-confirm="Approve this simulated payment? No real funds will move." data-confirm-title="Approve payment" data-confirm-yes="Yes, approve">
          <?= csrf_field() ?><input type="hidden" name="demo_nonce" value="<?= e($demoCheckout['nonce']) ?>">
          <button class="btn payment-submit" type="submit" name="approve_demo_payment" value="1">Approve demo payment · ₱<?= number_format((float)$b['total_price'], 2) ?></button>
        </form>
        <form method="post" class="payment-submit-form">
          <?= csrf_field() ?><button class="btn btn-sec payment-submit" type="submit" name="cancel_demo_payment" value="1">Cancel and return</button>
        </form>
      </div>
    <?php elseif ($return === 'success' && paymongo_configured()): ?>
      <p>If the amount was deducted from your wallet, confirmation can take a few seconds.</p>
      <a class="btn payment-submit" href="pay.php?id=<?= (int)$id ?>&amp;paymongo=success">Refresh payment status</a>
    <?php elseif (paymongo_configured()): ?>
      <a class="btn payment-submit" href="pay.php?id=<?= (int)$id ?>">Continue to <?= e($provider) ?> · ₱<?= number_format((float)$b['total_price'], 2) ?></a>
    <?php elseif (payment_demo_enabled() && !paymongo_configured()): ?>
      <div class="demo-wallet-shell <?= $provider === 'Maya' ? 'maya-wallet' : 'gcash-wallet' ?>">
        <div class="demo-wallet-top"><span><?= e($provider) ?></span><b>SIMULATION · STEP 1 OF 3</b></div>
        <h3>Enter a test mobile number</h3>
        <p class="muted">This imitates the wallet login hand-off. Use a made-up number; it is kept only in your current browser session and no SMS is sent.</p>
        <form method="post" class="payment-submit-form">
          <?= csrf_field() ?>
          <label for="demoPhone">Philippine mobile number</label>
          <input id="demoPhone" name="demo_phone" type="tel" inputmode="tel" autocomplete="off" placeholder="09XX XXX XXXX" pattern="(?:09[0-9]{9}|\+639[0-9]{9})" required>
          <small class="muted">Example format: 09171234567 or +639171234567. Do not enter a wallet password or PIN.</small>
          <button class="btn payment-submit" type="submit" name="start_demo_payment" value="1">Continue to verification</button>
        </form>
      </div>
    <?php elseif (payment_demo_enabled()): ?>
      <form method="post" class="payment-submit-form">
        <?= csrf_field() ?>
        <button class="btn payment-submit" type="submit" name="start_demo_payment" value="1">Continue to <?= e($provider) ?> demo checkout</button>
      </form>
    <?php endif; ?>
    <a class="btn btn-sec payment-cancel" href="book.php?edit=<?= (int)$id ?>">Back to booking</a>
  </section>
</div>
<?php require '../footer.php'; ?>
