<?php
require '../config.php';
$u = require_role('client');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rebook'])) {
    verify_csrf();
    $id = v_int('rebook', 1, PHP_INT_MAX, 'Booking');
    $b = $conn->query("SELECT * FROM bookings WHERE id=$id AND client_id={$u['id']}")->fetch_assoc();
    if (!$b) die('Booking not found.');
    $stmt = $conn->prepare("INSERT INTO bookings (client_id,worker_id,service_id,schedule,address,notes,total_price,payment_method,address_lat,address_lng)
                            VALUES (?,?,?,?,?,?,?,?,?,?)");
    $new_sched = date('Y-m-d H:i:s', strtotime('+7 days', max(strtotime($b['schedule']), time())));
    $lat = $b['address_lat'] === null ? null : (string)$b['address_lat'];
    $lng = $b['address_lng'] === null ? null : (string)$b['address_lng'];
    $method = in_array($b['payment_method'], ['cash','gcash','maya'], true) ? $b['payment_method'] : 'cash';
    $stmt->bind_param('iiisssdsss', $b['client_id'], $b['worker_id'], $b['service_id'],
                      $new_sched, $b['address'], $b['notes'], $b['total_price'], $method, $lat, $lng);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt2 = $conn->prepare("INSERT INTO checklist_items (booking_id,task) SELECT ?, task FROM checklist_items WHERE booking_id=?");
    $stmt2->bind_param('ii', $new_id, $id);
    $stmt2->execute();
    if ($b['worker_id'] && $method === 'cash') {
        notify_and_email($b['worker_id'], "{$u['name']} rebooked you for booking #$new_id on " . date('M j, g:i A', strtotime($new_sched)) . '.', 'Rebooked by client', 'worker/job.php?id=' . $new_id);
    } elseif ($b['worker_id']) {
        notify_and_email($b['worker_id'], "Rebooking #$new_id is reserved but waiting for the customer's GCash/Maya payment.", 'Rebooking awaiting payment', 'worker/job.php?id=' . $new_id);
    }
    notify_and_email($u['id'], "Rebooked as #$new_id on " . date('M j, g:i A', strtotime($new_sched)) . ". Same worker, checklist, map pin and payment preference carried over.", 'Rebook confirmed', 'client/booking.php?id=' . $new_id);
    audit_log('booking_rebooked', "Client {$u['name']} rebooked #$id as #$new_id (payment method $method)");
    flash($method === 'cash' ? 'Rebooked! Cash will be due after the job.' : 'Rebooked. Complete GCash or Maya checkout before the worker starts.');
    header('Location: ' . ($method === 'cash' ? 'booking.php?id=' . $new_id : 'pay.php?id=' . $new_id)); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    verify_csrf();
    $id = v_int('delete_booking', 1, PHP_INT_MAX, 'Booking');
    $b = $conn->query("SELECT b.*, s.name service FROM bookings b JOIN services s ON s.id=b.service_id
                       WHERE b.id=$id AND b.client_id={$u['id']}")->fetch_assoc();
    if (!$b) fail('Booking not found.');
    if (!booking_is_finished($b['status'])) fail('Only completed or cancelled bookings can be deleted. Cancel it first.');
    [$n, $files] = delete_booking_record($id);
    if ($b['worker_id']) notify_and_email($b['worker_id'], "Booking #$id ({$b['service']}) was deleted by the client.", 'Booking deleted', 'worker/jobs.php');
    audit_log('booking_deleted', "Client {$u['name']} deleted booking #$id from their list; $files photo file(s) removed");
    flash('Booking deleted permanently.');
    header('Location: bookings.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_history'])) {
    verify_csrf();
    [$n, $files] = purge_user_history($u['id'], 'client_id');
    audit_log('history_cleared', "Client {$u['name']} cleared $n finished booking(s); $files photo file(s) removed");
    flash($n ? "Cleared $n finished booking(s) from your history." : 'Nothing to clear yet.', $n ? 'success' : 'error');
    header('Location: bookings.php'); exit;
}

$title = 'My Bookings';
require '../layout.php';

$res = $conn->query("SELECT b.*, s.name service, u.name worker FROM bookings b
  JOIN services s ON s.id=b.service_id LEFT JOIN users u ON u.id=b.worker_id
  WHERE b.client_id={$u['id']} ORDER BY b.created_at DESC");
$finished = (int)$conn->query("SELECT COUNT(*) c FROM bookings
                WHERE client_id={$u['id']} AND status IN ('completed','cancelled')")->fetch_assoc()['c'];

// Build calendar for current + next month
$bookings_by_day = [];
$res2 = $conn->query("SELECT b.id,b.schedule,b.status,b.payment_status,b.payment_method,b.total_price,s.name service,w.name worker FROM bookings b
  JOIN services s ON s.id=b.service_id LEFT JOIN users w ON w.id=b.worker_id WHERE b.client_id={$u['id']}
  AND b.status NOT IN ('cancelled')");
while ($r = $res2->fetch_assoc()) {
    $day = date('Y-m-d', strtotime($r['schedule']));
    $bookings_by_day[$day][] = $r;
}
$today = new DateTime('first day of this month');
$month = isset($_GET['m']) ? DateTime::createFromFormat('Y-m', $_GET['m']) : $today;
if (!$month) $month = $today;
$month_start = (clone $month)->modify('first day of this month');
$month_end   = (clone $month)->modify('last day of this month');
$prev = (clone $month)->modify('-1 month')->format('Y-m');
$next = (clone $month)->modify('+1 month')->format('Y-m');
?>
<h1>My Bookings</h1>
<div class="two-col">
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
    <h2 style="margin-bottom:0;">All Bookings</h2>
    <?php if ((int)$finished > 0): ?>
      <form method="post" data-confirm="Permanently clear all <?= (int)$finished ?> finished or cancelled bookings from your history? Their checklists, photos and feedback will also be deleted. This cannot be undone. Payments are not refunded by deleting booking records." data-confirm-title="Clear booking history" data-confirm-yes="Yes, clear all">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline" name="clear_history" value="1">🧹 Clear history (<?= (int)$finished ?>)</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if ($res->num_rows === 0): ?>
    <div class="empty-state"><span class="big">🧹</span>No bookings yet.<br><a href="workers.php">Find a cleaner to book your first service</a>.</div>
  <?php else: ?>
  <div class="table-wrap booking-history-scroll" role="region" aria-label="Booking history" tabindex="0">
  <table class="booking-history-table">
    <thead><tr><th>ID</th><th>Service</th><th>Schedule</th><th>Worker</th><th>Status</th><th>Payment</th><th>Method</th><th>Actions</th></tr></thead>
    <tbody>
    <?php while ($b = $res->fetch_assoc()): ?>
    <tr>
      <td>#<?= $b['id'] ?></td>
      <td><?= e($b['service']) ?></td>
      <td><?= date('M j, Y g:i A', strtotime($b['schedule'])) ?></td>
      <td><?= e($b['worker'] ?? 'Unassigned') ?></td>
      <td><span class="badge <?= status_class($b['status']) ?>"><?= str_replace('_',' ',$b['status']) ?></span></td>
      <td><?= e($b['payment_status'] === 'demo_paid' ? 'demo-paid · no money transferred' : $b['payment_status']) ?></td>
      <td><?= e(ucfirst($b['payment_method'])) ?></td>
      <td class="booking-row-actions"><div class="booking-action-list">
        <a class="btn btn-sm btn-sec" href="booking.php?id=<?= $b['id'] ?>">View</a>
        <?php if ($b['status'] === 'awaiting_verification'): ?>
          <?php if ($b['payment_method'] === 'cash'): ?>
            <a class="btn btn-sm" href="booking.php?id=<?= $b['id'] ?>">Review &amp; confirm</a>
          <?php elseif (in_array($b['payment_status'], ['paid','demo_paid'], true)): ?>
            <form class="booking-action-form" method="post" action="booking.php?id=<?= $b['id'] ?>" data-confirm="Verify the completed work and finish this booking?" data-confirm-title="Confirm job finish" data-confirm-yes="Yes, finish"><?= csrf_field() ?><button class="btn btn-sm" name="verify" value="1">✔ Confirm Job Finish</button></form>
          <?php else: ?>
            <a class="btn btn-sm btn-sec" href="pay.php?id=<?= $b['id'] ?>">Pay now</a>
          <?php endif; ?>
        <?php elseif ($b['status'] === 'in_progress'): ?>
          <?php if ((int)($b['client_started_ack'] ?? 0) === 1): ?>
            <span class="muted" style="font-size:.78rem;">✓ Start confirmed</span>
          <?php else: ?>
            <form class="booking-action-form" method="post" action="booking.php?id=<?= $b['id'] ?>" data-confirm="Confirm that the worker has arrived and started booking #<?= (int)$b['id'] ?>? This only acknowledges the start; it does not confirm that the cleaning is finished or that payment was made." data-confirm-title="Confirm job start"><?= csrf_field() ?><button class="btn btn-sm" name="confirm_job_start" value="1">Confirm Job</button></form>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (in_array($b['status'], ['pending','assigned','in_progress','awaiting_verification'], true)): ?>
          <a class="btn btn-sm btn-sec" href="booking.php?id=<?= $b['id'] ?>">Cancel</a>
        <?php endif; ?>
        <form class="booking-action-form" method="post"><?= csrf_field() ?><button class="btn btn-sm" name="rebook" value="<?= $b['id'] ?>">Rebook</button></form>
        <?php if (booking_is_finished($b['status'])): ?>
          <form class="booking-action-form" method="post" data-confirm="Permanently delete booking #<?= (int)$b['id'] ?>? Its checklist, photos and feedback will also be deleted. This cannot be undone.<?= in_array($b['payment_status'], ['paid','demo_paid'], true) ? ' Deleting the booking does not refund its payment.' : '' ?>" data-confirm-title="Delete booking" data-confirm-yes="Yes, delete"><?= csrf_field() ?><button class="icon-btn danger" name="delete_booking" value="<?= $b['id'] ?>" title="Delete booking" aria-label="Delete booking">🗑</button></form>
        <?php endif; ?>
      </div></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  </div>
  <p class="booking-history-hint">Scroll the booking table horizontally to see all details and actions.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2 style="display:flex;justify-content:space-between;align-items:center;">
    <a href="?m=<?= $prev ?>" style="text-decoration:none;">‹</a>
    <span><?= $month_start->format('F Y') ?></span>
    <a href="?m=<?= $next ?>" style="text-decoration:none;">›</a>
  </h2>
  <table class="calendar">
    <tr><th>S</th><th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th></tr>
    <tr>
    <?php
    $cursor = clone $month_start;
    $cursor->modify(('0' - (int)$cursor->format('w')) . ' days'); // back to Sunday
    for ($i = 0; $i < 42; $i++):
        $key = $cursor->format('Y-m-d');
        $in_month = $cursor->format('Y-m') === $month_start->format('Y-m');
        $is_today = $key === date('Y-m-d');
        $has = $bookings_by_day[$key] ?? [];
        if ($i > 0 && $i % 7 === 0) echo '</tr><tr>';
        ?>
        <td class="<?= $in_month ? '' : 'out' ?> <?= $is_today ? 'today' : '' ?>">
          <?php if ($has): ?>
            <button type="button" class="calendar-day-button" data-day="<?= e($key) ?>" data-bookings="<?= e(json_encode($has, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE)) ?>" aria-pressed="false" aria-label="<?= e($cursor->format('F j, Y')) ?>, <?= count($has) ?> scheduled booking<?= count($has) === 1 ? '' : 's' ?>">
              <span class="daynum"><?= (int)$cursor->format('j') ?></span>
              <span class="calendar-day-events"><?php foreach ($has as $ev): ?><span class="ev ev-<?= status_class($ev['status']) ?: 'info' ?>"></span><?php endforeach; ?></span>
            </button>
          <?php else: ?>
            <span class="calendar-day-empty"><span class="daynum"><?= (int)$cursor->format('j') ?></span></span>
          <?php endif; ?>
        </td>
        <?php $cursor->modify('+1 day'); endfor; ?>
    </tr>
  </table>
  <p class="muted" style="margin-top:10px;">Select a date with a dot to view its scheduled bookings.</p>
  <section class="calendar-booking-details" id="calendarBookingDetails" aria-live="polite" hidden>
    <h3 id="calendarDetailsTitle"></h3>
    <div id="calendarDetailsList"></div>
  </section>
</div>
</div>
<script>
(() => {
  const details = document.getElementById('calendarBookingDetails');
  const title = document.getElementById('calendarDetailsTitle');
  const list = document.getElementById('calendarDetailsList');
  if (!details || !title || !list) return;
  document.querySelectorAll('.calendar-day-button').forEach(button => {
    button.addEventListener('click', () => {
      document.querySelectorAll('.calendar-day-button[aria-pressed="true"]').forEach(active => active.setAttribute('aria-pressed', 'false'));
      button.setAttribute('aria-pressed', 'true');
      const bookings = JSON.parse(button.dataset.bookings || '[]');
      const selectedDate = new Date(button.dataset.day + 'T12:00:00');
      title.textContent = new Intl.DateTimeFormat(undefined, { dateStyle: 'full' }).format(selectedDate);
      list.replaceChildren();
      bookings.forEach(booking => {
        const card = document.createElement('article');
        card.className = 'calendar-booking-item';
        const heading = document.createElement('div');
        heading.className = 'calendar-booking-heading';
        const service = document.createElement('strong');
        service.textContent = booking.service;
        const time = document.createElement('span');
        time.textContent = new Intl.DateTimeFormat(undefined, { timeStyle: 'short' }).format(new Date(booking.schedule.replace(' ', 'T')));
        heading.append(service, time);
        const meta = document.createElement('p');
        meta.textContent = `Booking #${booking.id} · ${booking.status.replaceAll('_', ' ')} · ${booking.worker || 'Cleaner not assigned yet'}`;
        const payment = document.createElement('p');
        payment.textContent = `${booking.payment_method.toUpperCase()} · ${booking.payment_status.replaceAll('_', ' ')} · ₱${Number(booking.total_price).toFixed(2)}`;
        const link = document.createElement('a');
        link.className = 'market-text-link';
        link.href = `booking.php?id=${encodeURIComponent(booking.id)}`;
        link.textContent = 'View booking details →';
        card.append(heading, meta, payment, link);
        list.append(card);
      });
      details.hidden = false;
      details.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });
})();
</script>
<style>
  .two-col { display:grid; grid-template-columns:1.4fr 1fr; gap:16px; align-items:start; }
  .two-col > * { min-width:0; }
  .booking-history-scroll { scrollbar-width:thin; }
  .table-wrap .booking-history-table { width:100%; min-width:980px; table-layout:auto; }
  .booking-history-table th, .booking-history-table td { white-space:normal; overflow-wrap:break-word; word-break:normal; vertical-align:middle; }
  .booking-history-table th:nth-child(6), .booking-history-table td:nth-child(6) { min-width:150px; }
  .booking-history-table th:last-child, .booking-history-table td:last-child { min-width:240px; }
  .booking-action-list { display:flex; align-items:center; flex-wrap:wrap; gap:7px; min-width:220px; }
  .booking-action-form { display:inline-flex; flex:0 0 auto; margin:0; }
  .booking-action-list .btn { flex:0 0 auto; max-width:none; white-space:nowrap; }
  .booking-history-hint { margin:8px 0 0; color:#718198; font-size:.76rem; }
  @media (max-width:800px) { .two-col { grid-template-columns:1fr; } }
  .card > .calendar, .card .calendar { min-width:0; width:100%; display:block; }
  .calendar { display:table; }
  @media (max-width:800px) {
    .calendar { min-width:0; }
    .calendar th, .calendar td { padding:2px; font-size:.75rem; }
  }
  .calendar td { text-align:center; vertical-align:middle; height:54px; padding:2px; border:1px solid #e3e9f2; background:#fff; }
  .calendar .out { background:#f7f9fd; }
  .calendar .today { background:#eef4fd; }
  .daynum { font-size:.8rem; color:#6b7a90; }
  .calendar-day-button,.calendar-day-empty { width:100%;height:100%;min-height:48px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;border:0;border-radius:8px;background:transparent;color:inherit;font:inherit; }
  .calendar-day-button { cursor:pointer; }
  .calendar-day-button:hover,.calendar-day-button[aria-pressed="true"] { background:#eaf6ed; }
  .calendar-day-button:focus-visible { outline:2px solid #22814a;outline-offset:-2px; }
  .calendar-day-button[aria-pressed="true"] .daynum { color:#176c3b;font-weight:850; }
  .calendar-day-events { display:flex;align-items:center;justify-content:center;gap:3px; }
  .ev { width:6px; height:6px; border-radius:50%; }
  .ev-ok{background:#2e7d32} .ev-warn{background:#b7791f} .ev-info{background:#1565c0} .ev-err{background:#c62828}
  .calendar-booking-details { margin-top:16px;padding:15px;border:1px solid #dcebe0;border-radius:14px;background:linear-gradient(145deg,#f5fbf6,#fff); }
  .calendar-booking-details h3 { margin:0 0 10px;color:#234d36;font-size:1rem; }
  .calendar-booking-item { padding:12px 0;border-top:1px solid #e6efe8; }
  .calendar-booking-item:first-child { border-top:0; }
  .calendar-booking-heading { display:flex;justify-content:space-between;gap:8px;color:#28435f; }
  .calendar-booking-heading span,.calendar-booking-item p { color:#718198;font-size:.78rem; }
  .calendar-booking-item p { margin:5px 0; }
  .calendar-booking-item .market-text-link { font-size:.8rem; }
</style>
<div id="lightbox"><img id="lightbox-img" src="" alt="Enlarged photo"></div>
<?php require '../footer.php'; ?>
