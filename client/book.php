<?php
require '../config.php';
$u = require_role('client');

$edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$edit_id = $edit_id !== false && $edit_id !== null && $edit_id > 0 ? $edit_id : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists('edit_booking_id', $_POST)) {
    $postedEditId = filter_var($_POST['edit_booking_id'], FILTER_VALIDATE_INT);
    if ($postedEditId === false || $postedEditId < 1) fail('The booking to edit is invalid.');
    $edit_id = $postedEditId;
}
$edit_booking = null;
$editTasks = [];
if ($edit_id > 0) {
    $editStmt = $conn->prepare("SELECT b.*,s.name service,(SELECT w.name FROM users w WHERE w.id=b.worker_id) worker_name FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.id=? AND b.client_id=?");
    $editStmt->bind_param('ii', $edit_id, $u['id']);
    $editStmt->execute();
    $edit_booking = $editStmt->get_result()->fetch_assoc();
    if (!$edit_booking || !in_array($edit_booking['status'], ['pending','assigned'], true) || $edit_booking['payment_status'] !== 'unpaid') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            fail('This booking can no longer be edited. Review its current status from My Bookings.');
        }
        flash('This booking can no longer be edited. Review its current status from My Bookings.', 'error');
        header('Location: bookings.php');
        exit;
    }
    $taskStmt = $conn->prepare('SELECT task FROM checklist_items WHERE booking_id=? ORDER BY id');
    $taskStmt->bind_param('i', $edit_id);
    $taskStmt->execute();
    $taskResult = $taskStmt->get_result();
    while ($taskRow = $taskResult->fetch_assoc()) $editTasks[] = $taskRow['task'];
}
$selected_worker_id = $edit_id ? 0 : (filter_input(INPUT_GET, 'worker_id', FILTER_VALIDATE_INT) ?: 0);
if ($selected_worker_id > 0) {
    $check = $conn->prepare("SELECT id,name,bio,avatar,city FROM users WHERE id=? AND role='worker' AND worker_approved=1");
    $check->bind_param('i', $selected_worker_id);
    $check->execute();
    $selected_worker = $check->get_result()->fetch_assoc();
    if (!$selected_worker) {
        flash('That cleaner profile could not be found. Please choose another worker.', 'error');
        header('Location: workers.php');
        exit;
    }
} else {
    $selected_worker = null;
}

$services = $conn->query("SELECT id,name,description,base_price,duration_hours FROM services ORDER BY base_price ASC");

// Saved addresses for the address-book dropdown
$savedAddresses = [];
{
    $stmt = $conn->prepare("SELECT id, label, address, address_lat, address_lng FROM saved_addresses WHERE client_id=? ORDER BY created_at DESC");
    $stmt->bind_param('i', $u['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $savedAddresses[] = $r;
}
$selected_worker_busy = false;
$selected_worker_city = $selected_worker ? trim((string)($selected_worker['city'] ?? '')) : '';
$selected_worker_near = $selected_worker ? same_city($u['city'] ?? '', $selected_worker['city'] ?? '') : false;
if ($selected_worker_id) {
    $busyStmt = $conn->prepare("SELECT COUNT(*) c FROM bookings WHERE worker_id=? AND status IN ('assigned','in_progress','awaiting_verification')");
    $busyStmt->bind_param('i', $selected_worker_id);
    $busyStmt->execute();
    $selected_worker_busy = (int)$busyStmt->get_result()->fetch_assoc()['c'] > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $service_id = v_int('service_id', 1, PHP_INT_MAX, 'Service');
    // When a customer starts from a worker profile, lock the hire to that worker.
    // Do not trust a modified hidden field or a different worker_id in the request.
    if ($selected_worker_id > 0) {
        $worker_id = $selected_worker_id;
    } elseif ($edit_id) {
        // Editing a booking keeps the cleaner already attached to it.
        $worker_id = $edit_booking['worker_id'] !== null ? (int)$edit_booking['worker_id'] : null;
    } else {
        // New bookings must start from a cleaner profile; enforced before the form renders.
        $worker_id = null;
    }
    $schedule = v_datetime();
    $address = v_str('address', 255, true, 'Service address');
    $notes = v_str('notes', 1000, false, 'Notes');
    $payment_method = strtolower(trim((string)($_POST['payment_method'] ?? 'cash')));
    if (!in_array($payment_method, ['cash', 'gcash', 'maya'], true)) {
        fail('Choose cash, GCash, or Maya.');
    }
    $latRaw = trim((string)($_POST['address_lat'] ?? ''));
    $lngRaw = trim((string)($_POST['address_lng'] ?? ''));
    $address_lat = null;
    $address_lng = null;
    if ($latRaw !== '' || $lngRaw !== '') {
        if ($latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
            fail('Please set a valid map pin, or clear it and keep the manual address only.');
        }
        $latNum = (float)$latRaw;
        $lngNum = (float)$lngRaw;
        if ($latNum < -90 || $latNum > 90 || $lngNum < -180 || $lngNum > 180) {
            fail('The map pin coordinates are outside the valid range.');
        }
        $address_lat = number_format($latNum, 7, '.', '');
        $address_lng = number_format($lngNum, 7, '.', '');
    }
    $tasks = array_slice(array_values(array_filter(array_map('trim', (array)($_POST['tasks'] ?? [])), static fn($task) => $task !== '')), 0, 20);
    foreach ($tasks as $task) {
        if (mb_strlen($task) > 255) fail('Each checklist task must be at most 255 characters.');
    }
    $svcStmt = $conn->prepare('SELECT id,name,base_price FROM services WHERE id=?');
    $svcStmt->bind_param('i', $service_id);
    $svcStmt->execute();
    $svc = $svcStmt->get_result()->fetch_assoc();
    if (!$svc) fail('Selected service does not exist.');

    if ($worker_id) {
        $check = $conn->prepare("SELECT id FROM users WHERE id=? AND role='worker' AND worker_approved=1");
        $check->bind_param('i', $worker_id);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) fail('Selected worker does not exist.');
        $busyStmt = $conn->prepare("SELECT COUNT(*) c FROM bookings WHERE worker_id=? AND status IN ('assigned','in_progress','awaiting_verification')" . ($edit_id ? " AND id<>?" : ''));
        if ($edit_id) $busyStmt->bind_param('ii', $worker_id, $edit_id);
        else $busyStmt->bind_param('i', $worker_id);
        $busyStmt->execute();
        if ((int)$busyStmt->get_result()->fetch_assoc()['c'] > 0) {
            fail('That cleaner has just become unavailable. Please choose another cleaner and try again.');
        }
    }

    $total = (float)$svc['base_price'];
    $status = $worker_id ? 'assigned' : 'pending';
    $latDb = $address_lat;
    $lngDb = $address_lng;
    $conn->begin_transaction();
    try {
        if ($edit_id) {
            $lock = $conn->prepare("SELECT worker_id,status,payment_status FROM bookings WHERE id=? AND client_id=? FOR UPDATE");
            $lock->bind_param('ii', $edit_id, $u['id']);
            $lock->execute();
            $lockedBooking = $lock->get_result()->fetch_assoc();
            if (!$lockedBooking || !in_array($lockedBooking['status'], ['pending','assigned'], true) || $lockedBooking['payment_status'] !== 'unpaid') {
                throw new RuntimeException('This booking can no longer be edited.');
            }
            $oldWorkerId = $lockedBooking['worker_id'] === null ? null : (int)$lockedBooking['worker_id'];
            $stmt = $conn->prepare("UPDATE bookings SET worker_id=?,service_id=?,schedule=?,address=?,notes=?,status=?,total_price=?,payment_method=?,address_lat=?,address_lng=?,paymongo_checkout_id=NULL WHERE id=? AND client_id=?");
            $stmt->bind_param('iissssdsssii', $worker_id, $service_id, $schedule, $address, $notes, $status, $total, $payment_method, $latDb, $lngDb, $edit_id, $u['id']);
            $stmt->execute();
            $bid = $edit_id;
            $removeTasks = $conn->prepare('DELETE FROM checklist_items WHERE booking_id=?');
            $removeTasks->bind_param('i', $bid);
            $removeTasks->execute();
        } else {
            $oldWorkerId = null;
            $stmt = $conn->prepare("INSERT INTO bookings (client_id,worker_id,service_id,schedule,address,notes,status,total_price,payment_method,address_lat,address_lng) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('iiissssdsss', $u['id'], $worker_id, $service_id, $schedule, $address, $notes, $status, $total, $payment_method, $latDb, $lngDb);
            $stmt->execute();
            $bid = (int)$conn->insert_id;
        }
        $ci = $conn->prepare('INSERT INTO checklist_items (booking_id,task) VALUES (?,?)');
        foreach ($tasks as $task) {
            $ci->bind_param('is', $bid, $task);
            $ci->execute();
        }
        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        error_log('ChoreHive booking save failed: ' . $ex->getMessage());
        if ($ex instanceof RuntimeException && $ex->getMessage() === 'This booking can no longer be edited.') {
            fail($ex->getMessage() . ' Review its current status from My Bookings.');
        }
        fail('We could not save this booking. Please check your database migration and try again.');
    }
    if ($edit_id) unset($_SESSION['payment_demo_checkout']);

    // Save to address book if requested
    if (!empty($_POST['save_address']) && !empty($_POST['address_label'])) {
        $label = trim((string)$_POST['address_label']);
        if ($label !== '') {
            $label = mb_substr($label, 0, 60);
            $ins = $conn->prepare("INSERT INTO saved_addresses (client_id, label, address, address_lat, address_lng) VALUES (?,?,?,?,?)");
            $ins->bind_param('issss', $u['id'], $label, $address, $latDb, $lngDb);
            $ins->execute();
        }
    }

    $serviceName = $svc['name'];
    if ($edit_id && $oldWorkerId && $oldWorkerId !== $worker_id) {
        notify_and_email($oldWorkerId, "Booking #$bid was updated and is no longer assigned to you.", 'Booking assignment updated', 'worker/jobs.php');
    }
    if ($worker_id && (!$edit_id || $oldWorkerId !== $worker_id || $edit_booking['payment_method'] !== $payment_method)) {
        if ($payment_method === 'cash') {
            notify_and_email($worker_id, "Booking #$bid: $serviceName on " . date('M j, g:i A', strtotime($schedule)) . '. Cash is due after the job.', $edit_id ? 'Booking updated' : 'New booking assigned', 'worker/job.php?id=' . $bid);
        } else {
            notify_and_email($worker_id, "Booking #$bid is reserved for you but waiting for the customer's GCash/Maya payment before you start work.", $edit_id ? 'Booking updated' : 'Booking awaiting payment', 'worker/job.php?id=' . $bid);
        }
    }
    $methodLabel = ['cash' => 'cash after the job', 'gcash' => 'GCash (PayMongo)', 'maya' => 'Maya (PayMongo)'][$payment_method];
    notify_and_email($u['id'], "Booking #$bid " . ($edit_id ? 'updated' : 'created') . ": $serviceName on " . date('M j, g:i A', strtotime($schedule)) . '. Payment method: ' . $methodLabel . '.', $edit_id ? 'Booking updated' : 'Booking created', 'client/booking.php?id=' . $bid);
    audit_log($edit_id ? 'booking_updated' : 'booking_created', "Booking #$bid " . ($edit_id ? 'updated' : 'created') . " by client {$u['name']} (service $service_id; payment $payment_method; " . ($worker_id ? "worker #$worker_id" : 'worker not yet selected') . ')');
    if ($payment_method !== 'cash') {
        flash('Booking details saved. Complete GCash or Maya checkout before the worker can start.');
        header('Location: pay.php?id=' . $bid);
    } else {
        flash($edit_id ? 'Your booking has been updated. Cash is due after the work is completed.' : 'Your cleaning booking has been created. Cash is due after the work is completed.');
        header('Location: booking.php?id=' . $bid);
    }
    exit;
}

$showBookingForm = $edit_id > 0 || $selected_worker_id > 0;
$title = $edit_id ? 'Edit Booking' : ($selected_worker_id ? 'Hire Your Cleaner' : 'Book a Service');
$enableMapAssets = $showBookingForm;
require '../layout.php';
if (!$showBookingForm) {
    ?>
    <div class="page-intro booking-intro">
      <span class="eyebrow">ONE QUICK STEP FIRST</span>
      <h1>Choose your cleaner first<span class="heading-period">.</span></h1>
      <p>Every ChoreHive booking starts from a cleaner&rsquo;s profile so you always know who is coming to your home.</p>
    </div>
    <div class="card book-first-notice">
      <span class="book-first-icon"><i class="bi bi-person-check-fill"></i></span>
      <h2>Pick a cleaner to unlock the booking form</h2>
      <p>Go to <b>Find a Cleaner</b>, review ratings and experience, then tap <b>Hire this cleaner</b> on the profile you like. You&rsquo;ll land on the booking form with that cleaner already selected.</p>
      <div class="book-first-actions">
        <a class="btn" href="workers.php"><i class="bi bi-people-fill"></i> Click here to find a cleaner</a>
        <a class="btn btn-outline" href="bookings.php">View my bookings</a>
      </div>
    </div>
    <?php
    require '../footer.php';
    exit;
}
$workerAvatar = $selected_worker ? avatar_url($selected_worker, ch_base()) : '';
?>
<div class="page-intro booking-intro">
  <span class="eyebrow">YOUR HOME, YOUR CHOICE</span>
  <h1><?= $edit_id ? 'Update your booking' : ($selected_worker_id ? 'Let’s make it official' : 'Plan your clean') ?><span class="heading-period">.</span></h1>
  <p><?= $edit_id ? 'Review your booking details and change anything before continuing.' : ($selected_worker_id ? 'You found your cleaner. Add the job details below and ChoreHive will take care of the next steps.' : 'Choose your service and schedule, then tell your cleaner what matters most.') ?></p>
</div>
<?php if ($selected_worker): ?>
  <div class="hire-selected-banner hire-selected-banner-large">
    <span>✓</span>
    <div class="hire-selected-person">
      <?php if ($workerAvatar): ?><img src="<?= e($workerAvatar) ?>" alt="" class="booking-worker-avatar"><?php else: ?><span class="booking-worker-avatar booking-worker-initials"><?= e(initials($selected_worker['name'])) ?></span><?php endif; ?>
      <div><b>You’re hiring <?= e($selected_worker['name']) ?></b><small><?= $selected_worker_busy ? 'This cleaner currently has an active job. You can still complete the form, but availability will be checked again before saving.' : 'Your booking will be assigned to this cleaner.' ?><?= $selected_worker_city ? ' · 📍 ' . e($selected_worker_city) . ($selected_worker_near ? ' (near you)' : '') : '' ?></small></div>
    </div>
    <a href="workers.php">Change cleaner</a>
  </div>
<?php endif; ?>
<div class="booking-layout">
  <main class="card booking-form-card">
    <form method="post" id="bookingForm">
      <?= csrf_field() ?>
      <?php if ($edit_id): ?><input type="hidden" name="edit_booking_id" value="<?= (int)$edit_id ?>"><?php endif; ?>
      <?php if ($selected_worker_id): ?><input type="hidden" name="worker_id" value="<?= (int)$selected_worker_id ?>"><?php endif; ?>
      <section class="booking-form-section">
        <span class="booking-section-number">01</span>
        <div class="booking-section-heading"><h2>What needs cleaning?</h2><p>Choose the service that best matches your home.</p></div>
        <label for="service_id">Cleaning service</label>
        <select name="service_id" id="service_id" required>
          <?php while ($s = $services->fetch_assoc()): ?>
            <option value="<?= (int)$s['id'] ?>" data-price="<?= e((string)$s['base_price']) ?>" data-duration="<?= (int)$s['duration_hours'] ?>" <?= (int)($_POST['service_id'] ?? $edit_booking['service_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?> — ₱<?= number_format((float)$s['base_price'], 2) ?></option>
          <?php endwhile; ?>
        </select>
        <?php if (!$selected_worker_id && !empty($edit_booking['worker_id'])): ?>
          <p class="form-hint">Assigned cleaner: <b><?= e($edit_booking['worker_name'] ?: 'your cleaner') ?></b>. Your booking stays with this cleaner when you save changes.</p>
        <?php elseif (!$selected_worker_id && empty($edit_booking['worker_id'])): ?>
          <p class="form-hint">This booking stays pending until an administrator assigns an available cleaner. Want to choose your cleaner yourself? <a href="workers.php">Browse worker profiles first.</a></p>
        <?php endif; ?>
      </section>
      <section class="booking-form-section">
        <span class="booking-section-number">02</span>
        <div class="booking-section-heading"><h2>Where and when?</h2><p>Give your cleaner the details they need to arrive prepared.</p></div>
        <label for="schedule">Preferred date and time</label>
        <div class="schedule-picker">
          <span class="schedule-picker-icon" aria-hidden="true"><i class="bi bi-calendar2-week"></i></span>
          <div class="schedule-picker-content">
            <small>CHOOSE A DATE &amp; TIME</small>
            <input type="datetime-local" name="schedule" id="schedule" required min="<?= min_datetime_attr() ?>" value="<?= e($_POST['schedule'] ?? ($edit_booking ? date('Y-m-d\TH:i', strtotime($edit_booking['schedule'])) : '')) ?>">
            <span id="schedulePreview" class="schedule-picker-preview" aria-live="polite">Choose a date and time for your cleaning</span>
          </div>
        </div>
        <p class="form-hint">Bookings can only be scheduled for today or a future date.</p>
        <?php if ($savedAddresses): ?>
          <label for="saved_address">Use a saved address</label>
          <select id="saved_address" name="saved_address">
            <option value="">— Choose one to fill in the address below —</option>
            <?php foreach ($savedAddresses as $sa): ?>
              <option value="<?= (int)$sa['id'] ?>" data-address="<?= e($sa['address']) ?>" data-lat="<?= e((string)$sa['address_lat']) ?>" data-lng="<?= e((string)$sa['address_lng']) ?>"><?= e($sa['label']) ?> — <?= e($sa['address']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <label for="address">Cleaning address <span class="muted">(required; enter this manually)</span></label>
        <input name="address" id="address" maxlength="255" required autocomplete="street-address" value="<?= e($_POST['address'] ?? $edit_booking['address'] ?? $u['address'] ?? '') ?>" placeholder="House number, street, barangay, city">
        <div class="map-picker-card">
          <div class="map-picker-heading"><div><b>Pin the exact location</b><p>Tap the map or drag the pin. Your typed address will not be overwritten.</p></div><button type="button" class="btn btn-outline btn-sm" id="useCurrentLocation">Use my location</button></div>
          <div id="addressMap" class="address-map" role="application" aria-label="Map to pin cleaning location"></div>
          <div class="map-picker-footer"><span id="mapPinStatus" role="status" aria-live="polite">No map pin selected. The manual address will still be saved.</span><button type="button" class="map-clear-button" id="clearMapPin">Clear pin</button></div>
          <small class="map-privacy-note">Map tiles provided by OpenStreetMap contributors. The pin coordinates are shared with the assigned worker to help them find the address. Manual address entry remains the source of truth. <a href="https://www.openstreetmap.org/fixthemap" target="_blank" rel="noopener">Report a map issue</a>.</small>
          <input type="hidden" name="address_lat" id="address_lat" value="<?= e($_POST['address_lat'] ?? $edit_booking['address_lat'] ?? '') ?>">
          <input type="hidden" name="address_lng" id="address_lng" value="<?= e($_POST['address_lng'] ?? $edit_booking['address_lng'] ?? '') ?>">
        </div>
        <label for="notes">Additional instructions <span class="muted">(optional)</span></label>
        <textarea name="notes" id="notes" rows="3" maxlength="1000" placeholder="Gate instructions, pets at home, supplies available…"><?= e($_POST['notes'] ?? $edit_booking['notes'] ?? '') ?></textarea>
        <div class="save-address-row" style="margin-top:14px;padding:12px 14px;background:#f5f8ff;border:1px solid #e3e9f2;border-radius:10px;">
          <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-weight:600;">
            <input type="checkbox" name="save_address" value="1" id="save_address" style="margin-top:3px;" <?= !empty($_POST['save_address']) ? 'checked' : '' ?>>
            <span>Save this address to my address book</span>
          </label>
          <div id="saveAddressFields" style="margin-top:8px;display:none;">
            <input name="address_label" id="address_label" maxlength="60" placeholder="Label (e.g. Home, Office, Condo)" value="<?= e($_POST['address_label'] ?? '') ?>" style="width:100%;padding:9px 12px;border:1px solid #d6dff0;border-radius:8px;font-family:inherit;font-size:.9rem;">
          </div>
        </div>
      </section>
      <section class="booking-form-section">
        <span class="booking-section-number">03</span>
        <div class="booking-section-heading"><h2>Your cleaning checklist</h2><p>List the tasks you want completed. You can add up to 20.</p></div>
        <div id="taskFields" class="booking-task-fields">
          <?php $postedTasks = array_slice((array)($_POST['tasks'] ?? ($editTasks ?: ['', '', ''])), 0, 20); if (!$postedTasks) $postedTasks = ['', '', '']; foreach ($postedTasks as $i => $task): ?>
            <div class="booking-task-row"><span class="booking-task-check">✓</span><input name="tasks[]" maxlength="255" value="<?= e($task) ?>" placeholder="<?= ['e.g. Clean kitchen sink','e.g. Mop bedroom floor','e.g. Wipe living room surfaces'][$i] ?? 'Add a cleaning task' ?>"><button type="button" class="booking-remove-task" aria-label="Remove task" title="Remove task">×</button></div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline btn-sm" id="addTask">+ Add another task</button>
      </section>
      <section class="booking-form-section">
        <span class="booking-section-number">04</span>
        <div class="booking-section-heading"><h2>How would you like to pay?</h2><p>Cash is confirmed after the job. GCash or Maya must be completed through PayMongo before work starts.</p></div>
        <div class="payment-choice-grid">
          <label class="payment-choice"><input type="radio" name="payment_method" value="cash" <?= (($_POST['payment_method'] ?? $edit_booking['payment_method'] ?? 'cash') === 'cash') ? 'checked' : '' ?>><span class="payment-choice-copy"><b>Cash after cleaning</b><small>Pay the worker physically after the job is complete and you've reviewed it.</small></span><span class="payment-choice-tag">Pay later</span></label>
          <label class="payment-choice"><input type="radio" name="payment_method" value="gcash" <?= (($_POST['payment_method'] ?? $edit_booking['payment_method'] ?? '') === 'gcash') ? 'checked' : '' ?>><span class="payment-choice-copy"><b>GCash</b><small>Pay securely via PayMongo before the worker starts.</small></span><span class="payment-choice-tag online">Pay first</span></label>
          <label class="payment-choice"><input type="radio" name="payment_method" value="maya" <?= (($_POST['payment_method'] ?? $edit_booking['payment_method'] ?? '') === 'maya') ? 'checked' : '' ?>><span class="payment-choice-copy"><b>Maya</b><small>Pay securely via PayMongo before the worker starts.</small></span><span class="payment-choice-tag online">Pay first</span></label>
        </div>
        <p class="payment-demo-note"><b>Online payments</b> are processed by PayMongo. You will complete checkout on GCash or Maya. The worker cannot start until payment is confirmed.</p>
      </section>
      <div class="booking-submit-row"><div><b id="paymentSummaryTitle">Cash after the job</b><small id="paymentSummaryHint">Pay the worker after the job is complete and you have reviewed it.</small></div><button type="submit" class="btn booking-submit-button"><?= $edit_id ? 'Save changes' : 'Continue' ?> <span>→</span></button></div>
    </form>
  </main>
  <aside class="booking-summary-column">
    <div class="card booking-summary-card"><span class="eyebrow">BOOKING SUMMARY</span><h2>Your cleaning plan</h2><div class="booking-summary-line"><span>Service</span><b id="summaryService">Choose a service</b></div><div class="booking-summary-line"><span>Estimated duration</span><b id="summaryDuration">—</b></div><div class="booking-summary-total"><span>Service price</span><strong id="summaryPrice">₱0.00</strong></div><p class="form-hint">Final price is based on the selected service. Any changes should be confirmed before the job begins.</p></div>
  </aside>
</div>
<script>
(()=>{
  const scheduleInput = document.getElementById('schedule');
  const schedulePreview = document.getElementById('schedulePreview');
  const updateSchedulePreview = () => {
    if (!scheduleInput || !schedulePreview) return;
    if (!scheduleInput.value) {
      schedulePreview.textContent = 'Choose a date and time for your cleaning';
      return;
    }
    const date = new Date(scheduleInput.value);
    schedulePreview.textContent = Number.isNaN(date.getTime())
      ? 'Choose a date and time for your cleaning'
      : new Intl.DateTimeFormat(undefined, { dateStyle: 'full', timeStyle: 'short' }).format(date);
  };
  if (scheduleInput) {
    scheduleInput.addEventListener('input', updateSchedulePreview);
    scheduleInput.addEventListener('change', updateSchedulePreview);
    updateSchedulePreview();
  }

  const form=document.getElementById('bookingForm');if(!form)return;
  const service=document.getElementById('service_id'),price=document.getElementById('summaryPrice'),duration=document.getElementById('summaryDuration'),summary=document.getElementById('summaryService'),tasks=document.getElementById('taskFields'),add=document.getElementById('addTask');
  const paymentTitle=document.getElementById('paymentSummaryTitle'),paymentHint=document.getElementById('paymentSummaryHint');
  function updatePaymentSummary(){const choice=form.querySelector('input[name=payment_method]:checked');const method=choice?choice.value:'cash';if(method==='cash'){paymentTitle.textContent='Cash after the job';paymentHint.textContent='Pay the worker physically after the job is complete and you have reviewed it.';}else{paymentTitle.textContent=method==='gcash'?'GCash via PayMongo':'Maya via PayMongo';paymentHint.textContent='You will be taken to PayMongo checkout now; the worker cannot start until payment is complete.';}}
  function updateSummary(){const o=service.options[service.selectedIndex];if(!o)return;summary.textContent=o.text.split(' — ')[0];price.textContent='₱'+Number(o.dataset.price||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});duration.textContent=(o.dataset.duration||'—')+' hour'+(Number(o.dataset.duration)===1?'':'s');}
  function updateRemoveButtons(){const rows=tasks.querySelectorAll('.booking-task-row');rows.forEach((r)=>{r.querySelector('.booking-remove-task').disabled=rows.length<=1;});add.disabled=rows.length>=20;add.textContent=rows.length>=20?'Maximum 20 tasks reached':'+ Add another task';}
  function bindRemove(btn){btn.addEventListener('click',()=>{if(tasks.querySelectorAll('.booking-task-row').length>1){btn.closest('.booking-task-row').remove();updateRemoveButtons();}});}
  tasks.querySelectorAll('.booking-remove-task').forEach(bindRemove);service.addEventListener('change',updateSummary);form.querySelectorAll('input[name=payment_method]').forEach(el=>el.addEventListener('change',updatePaymentSummary));add.addEventListener('click',()=>{if(tasks.querySelectorAll('.booking-task-row').length>=20)return;const row=document.createElement('div');row.className='booking-task-row';row.innerHTML='<span class="booking-task-check">✓</span><input name="tasks[]" maxlength="255" placeholder="Add a cleaning task"><button type="button" class="booking-remove-task" aria-label="Remove task" title="Remove task">×</button>';tasks.appendChild(row);bindRemove(row.querySelector('.booking-remove-task'));updateRemoveButtons();row.querySelector('input').focus();});updateSummary();updateRemoveButtons();updatePaymentSummary();

  // Saved address dropdown: fill address + map pin when chosen
  const savedSel = document.getElementById('saved_address');
  if (savedSel) {
    savedSel.addEventListener('change', () => {
      const opt = savedSel.options[savedSel.selectedIndex];
      if (!opt || !opt.value) return;
      const addrInput = document.getElementById('address');
      const latInput = document.getElementById('address_lat');
      const lngInput = document.getElementById('address_lng');
      const status = document.getElementById('mapPinStatus');
      if (opt.dataset.address) addrInput.value = opt.dataset.address;
      if (opt.dataset.lat && opt.dataset.lng) {
        latInput.value = opt.dataset.lat;
        lngInput.value = opt.dataset.lng;
        if (status) status.textContent = 'Pinned location loaded from saved address.';
        if (typeof window.__choreMapSetPin === 'function') {
          window.__choreMapSetPin(parseFloat(opt.dataset.lat), parseFloat(opt.dataset.lng));
        }
      }
    });
  }
  // Save-address checkbox toggle
  const saveChk = document.getElementById('save_address');
  const saveFields = document.getElementById('saveAddressFields');
  if (saveChk && saveFields) {
    const toggle = () => { saveFields.style.display = saveChk.checked ? '' : 'none'; };
    saveChk.addEventListener('change', toggle);
    toggle();
  }
})();
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(()=>{
 const el=document.getElementById('addressMap'); if(!el) return;
 const latInput=document.getElementById('address_lat'),lngInput=document.getElementById('address_lng'),status=document.getElementById('mapPinStatus');
 const currentBtn=document.getElementById('useCurrentLocation'),clearBtn=document.getElementById('clearMapPin');
 if(!window.L){el.innerHTML='<div class="map-unavailable">The map could not load. You can still enter the full address manually.</div>';currentBtn.disabled=true;return;}
 const savedLat=Number(latInput.value),savedLng=Number(lngInput.value),hasSaved=latInput.value!==''&&lngInput.value!==''&&Number.isFinite(savedLat)&&Number.isFinite(savedLng);
 const map=L.map(el,{scrollWheelZoom:false}).setView(hasSaved?[savedLat,savedLng]:[6.5009,124.8469],hasSaved?16:12);
 L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap contributors</a>'}).addTo(map);
 let marker=null;
 function label(lat,lng){return 'Pinned coordinates: '+lat.toFixed(6)+', '+lng.toFixed(6)+'. Confirm the typed address separately.';}
 function setPin(latlng,zoom){const lat=Number(latlng.lat.toFixed(7)),lng=Number(latlng.lng.toFixed(7));latInput.value=lat;lngInput.value=lng;if(!marker){marker=L.marker([lat,lng],{draggable:true}).addTo(map);marker.on('dragend',()=>{const pos=marker.getLatLng();setPin(pos,0);});}else{marker.setLatLng([lat,lng]);}if(zoom)map.setView([lat,lng],Math.max(map.getZoom(),15));status.textContent=label(lat,lng);status.classList.add('has-pin');}
 function clearPin(){if(marker){map.removeLayer(marker);marker=null;}latInput.value='';lngInput.value='';status.textContent='No map pin selected. The manual address will still be saved.';status.classList.remove('has-pin');}
 window.__choreMapSetPin=function(lat,lng){if(!Number.isFinite(lat)||!Number.isFinite(lng))return;setPin({lat:lat,lng:lng},true);};
 if(hasSaved){setPin({lat:savedLat,lng:savedLng},false);}map.on('click',e=>setPin(e.latlng,false));
 currentBtn.addEventListener('click',()=>{if(!navigator.geolocation){status.textContent='This browser does not support location. You can pin the map manually.';return;}currentBtn.disabled=true;currentBtn.textContent='Locating…';navigator.geolocation.getCurrentPosition(pos=>{setPin({lat:pos.coords.latitude,lng:pos.coords.longitude},true);currentBtn.disabled=false;currentBtn.textContent='Use my location';},err=>{status.textContent=err.code===1?'Location permission was denied. You can still click or drag a pin on the map.':'Your location could not be determined. Please pin the map manually.';currentBtn.disabled=false;currentBtn.textContent='Use my location';},{enableHighAccuracy:true,timeout:10000,maximumAge:60000});});
 clearBtn.addEventListener('click',clearPin);setTimeout(()=>map.invalidateSize(),150);
})();
</script>
<?php require '../footer.php'; ?>
