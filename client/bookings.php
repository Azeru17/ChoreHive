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
$res2 = $conn->query("SELECT b.schedule, b.status, s.name service FROM bookings b
  JOIN services s ON s.id=b.service_id WHERE b.client_id={$u['id']}
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
      <form method="post" onsubmit="return confirm('Clear all $finished finished booking(s)?\n\nTheir checklists, photos and feedback will be permanently deleted.');">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline" name="clear_history" value="1">🧹 Clear history (<?= (int)$finished ?>)</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if ($res->num_rows === 0): ?>
    <div class="empty-state"><span class="big">🧹</span>No bookings yet.<br><a href="book.php">Book your first service</a>.</div>
  <?php endif; ?>
  <div class="table-wrap">
  <table>
    <tr><th>ID</th><th>Service</th><th>Schedule</th><th>Worker</th><th>Status</th><th>Payment</th><th>Method</th><th></th></tr>
    <?php while ($b = $res->fetch_assoc()): ?>
    <tr>
      <td>#<?= $b['id'] ?></td>
      <td><?= e($b['service']) ?></td>
      <td><?= date('M j, Y g:i A', strtotime($b['schedule'])) ?></td>
      <td><?= e($b['worker'] ?? 'Unassigned') ?></td>
      <td><span class="badge <?= status_class($b['status']) ?>"><?= str_replace('_',' ',$b['status']) ?></span></td>
      <td><?= e($b['payment_status'] === 'demo_paid' ? 'demo-paid · no money transferred' : $b['payment_status']) ?></td>
      <td><?= e(ucfirst($b['payment_method'])) ?></td>
      <td>
        <a class="btn btn-sm btn-sec" href="booking.php?id=<?= $b['id'] ?>">View</a>
        <?php if ($b['status'] === 'awaiting_verification'): ?>
          <?php if ($b['payment_method'] === 'cash'): ?>
            <a class="btn btn-sm" href="booking.php?id=<?= $b['id'] ?>">Review &amp; confirm</a>
          <?php elseif (in_array($b['payment_status'], ['paid','demo_paid'], true)): ?>
            <form method="post" action="booking.php?id=<?= $b['id'] ?>" style="display:inline" onsubmit="return confirm('Verify the completed work and finish this booking?');"><?= csrf_field() ?><button class="btn btn-sm" name="verify" value="1">✔ Confirm Job Finish</button></form>
          <?php else: ?>
            <a class="btn btn-sm btn-sec" href="pay.php?id=<?= $b['id'] ?>">Pay now</a>
          <?php endif; ?>
        <?php elseif ($b['status'] === 'in_progress'): ?>
          <?php if ((int)($b['client_started_ack'] ?? 0) === 1): ?>
            <span class="muted" style="font-size:.78rem;">✓ Start confirmed</span>
          <?php else: ?>
            <form method="post" action="booking.php?id=<?= $b['id'] ?>" style="display:inline" onsubmit="return confirm('Confirm the worker has started this job?');"><?= csrf_field() ?><button class="btn btn-sm" name="confirm_job_start" value="1">Confirm Job</button></form>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (in_array($b['status'], ['pending','assigned','in_progress','awaiting_verification'], true)): ?>
          <a class="btn btn-sm btn-sec" href="booking.php?id=<?= $b['id'] ?>">Cancel</a>
        <?php endif; ?>
        <form method="post" style="display:inline"><?= csrf_field() ?><button class="btn btn-sm" name="rebook" value="<?= $b['id'] ?>">Rebook</button></form>
        <?php if (booking_is_finished($b['status'])): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete booking #<?= $b['id'] ?> permanently?\n\nIts checklist, photos and feedback will be removed too.');"><?= csrf_field() ?><button class="icon-btn danger" name="delete_booking" value="<?= $b['id'] ?>" title="Delete booking" aria-label="Delete booking">🗑</button></form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  </div>
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
          <div class="daynum"><?= (int)$cursor->format('j') ?></div>
          <?php foreach ($has as $ev): ?>
            <div class="ev ev-<?= status_class($ev['status']) ?: 'info' ?>" title="<?= e($ev['service']) ?> — <?= e($ev['status']) ?>"></div>
          <?php endforeach; ?>
        </td>
        <?php $cursor->modify('+1 day'); endfor; ?>
    </tr>
  </table>
  <p class="muted" style="margin-top:6px;">Dots mark scheduled bookings — hover for details.</p>
</div>
</div>
<style>
  .two-col { display:grid; grid-template-columns:1.4fr 1fr; gap:16px; align-items:start; }
  .two-col > * { min-width:0; }
  @media (max-width:800px) { .two-col { grid-template-columns:1fr; } }
  .card > .calendar, .card .calendar { min-width:0; width:100%; display:block; }
  .calendar { display:table; }
  @media (max-width:800px) {
    .calendar { min-width:0; }
    .calendar th, .calendar td { padding:2px; font-size:.75rem; }
  }
  .calendar td { text-align:center; vertical-align:top; height:44px; padding:3px; border:1px solid #e3e9f2; }
  .calendar .out { color:#a9b6cc; background:#f7f9fd; }
  .calendar .today { background:#eef4fd; }
  .daynum { font-size:.8rem; color:#6b7a90; }
  .ev { width:8px; height:8px; border-radius:50%; margin:2px auto 0; }
  .ev-ok{background:#2e7d32} .ev-warn{background:#b7791f} .ev-info{background:#1565c0} .ev-err{background:#c62828}
</style>
<div id="lightbox"><img id="lightbox-img" src="" alt="Enlarged photo"></div>
<?php require '../footer.php'; ?>
