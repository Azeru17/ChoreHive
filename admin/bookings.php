<?php
require '../config.php';
$u = require_role('admin');

/* ---------- Actions (must run BEFORE any output, or the redirect fails) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    verify_csrf();
    $bid = v_int('assign', 1, PHP_INT_MAX, 'Booking');
    $wid = ($_POST['worker_id'] ?? '') !== '' ? v_int('worker_id', 1, PHP_INT_MAX, 'Worker') : null;
    $currentStmt = $conn->prepare("SELECT id,client_id,status,worker_id,payment_method,payment_status FROM bookings WHERE id=?");
    $currentStmt->bind_param('i', $bid); $currentStmt->execute(); $current = $currentStmt->get_result()->fetch_assoc();
    if (!$current) fail('Booking not found.');
    if (!in_array($current['status'], ['pending','assigned','cancelled'], true)) {
        fail('Only pending, assigned, or unassigned cancelled bookings can be reassigned. Jobs already in progress or awaiting verification must not be reassigned.');
    }
    if ($current['status'] === 'cancelled' && $current['worker_id'] !== null) {
        fail('This booking was cancelled by the customer. Confirm with the customer before attempting a new assignment.');
    }
    if ($wid !== null) {
        $workerCheck = $conn->prepare("SELECT id FROM users WHERE id=? AND role='worker' AND worker_approved=1");
        $workerCheck->bind_param('i', $wid); $workerCheck->execute();
        if (!$workerCheck->get_result()->fetch_assoc()) fail('Select a valid worker account.');
        $busy = $conn->prepare("SELECT COUNT(*) c FROM bookings WHERE worker_id=? AND id<>? AND status IN ('assigned','in_progress','awaiting_verification')");
        $busy->bind_param('ii', $wid, $bid); $busy->execute();
        if ((int)$busy->get_result()->fetch_assoc()['c'] > 0) fail('That worker already has an active job. Choose an available worker.');
    }
    $newStatus = $wid === null ? 'pending' : 'assigned';
    $stmt = $conn->prepare("UPDATE bookings SET worker_id=?, status=? WHERE id=? AND status IN ('pending','assigned','cancelled')");
    $stmt->bind_param('isi', $wid, $newStatus, $bid);
    $stmt->execute();
    if ($stmt->affected_rows < 1 && (int)$current['worker_id'] !== (int)$wid) fail('The booking changed before the assignment could be saved. Refresh and try again.');
    if ($wid) {
        $s = $conn->prepare("SELECT s.name service, b.client_id, b.payment_method, b.payment_status FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.id=?");
        $s->bind_param('i', $bid); $s->execute(); $details = $s->get_result()->fetch_assoc();
        $paymentNote = $details && in_array($details['payment_method'], ['gcash','maya'], true) && !in_array($details['payment_status'], ['paid','demo_paid'], true)
            ? ' This booking is waiting for GCash/Maya payment; do not start until paid.' : '';
        notify_and_email($wid, "Admin assigned you to booking #$bid" . ($details ? ' (' . $details['service'] . ')' : '') . '.' . $paymentNote, 'Job assigned to you', 'worker/job.php?id=' . $bid);
        if ($details) notify_and_email((int)$details['client_id'], "A worker was assigned to your booking #$bid (" . $details['service'] . "). Track it in My Bookings.", 'Worker assigned', 'client/booking.php?id=' . $bid);
    }
    audit_log('worker_assigned', "Admin {$u['name']} set worker #" . ($wid ?? 'none') . " on booking #$bid");
    flash('Worker assignment updated.');
    header('Location: bookings.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verify_csrf();
    $bid = v_int('delete', 1, PHP_INT_MAX, 'Booking');
    $row = $conn->query("SELECT b.*, s.name service FROM bookings b JOIN services s ON s.id=b.service_id
                         WHERE b.id=$bid")->fetch_assoc();
    if (!$row) fail('Booking not found.');
    [$n, $files] = delete_booking_record($bid);
    if ($row['client_id']) notify_and_email($row['client_id'], "Booking #$bid ({$row['service']}) was deleted by an administrator.", 'Booking deleted', 'client/bookings.php');
    audit_log('booking_deleted', "Admin {$u['name']} deleted booking #$bid ({$row['status']}); $files photo file(s) removed");
    flash("Booking #$bid deleted permanently.");
    header('Location: bookings.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_finished'])) {
    verify_csrf();
    $ids = [];
    $r = $conn->query("SELECT id FROM bookings WHERE status IN ('completed','cancelled')");
    while ($x = $r->fetch_assoc()) $ids[] = (int)$x['id'];
    $rows = $files = 0;
    foreach ($ids as $id) { [$a, $f] = delete_booking_record($id); $rows += $a; $files += $f; }
    audit_log('history_cleared', "Admin {$u['name']} purged $rows finished booking(s); $files photo file(s) removed");
    flash($rows ? "Purged $rows finished booking(s)." : 'No finished bookings to purge.', $rows ? 'success' : 'error');
    header('Location: bookings.php'); exit;
}

$title = 'Manage Bookings';
require '../layout.php';

$workers = $conn->query("SELECT u.id,u.name,(SELECT COUNT(*) FROM bookings bx WHERE bx.worker_id=u.id AND bx.status IN ('assigned','in_progress','awaiting_verification')) active_jobs FROM users u WHERE u.role='worker' AND u.worker_approved=1 ORDER BY u.name");
$workers_list = $workers->fetch_all(MYSQLI_ASSOC);
$res = $conn->query("SELECT b.*, s.name service, c.name client, w.name worker FROM bookings b
  JOIN services s ON s.id=b.service_id
  JOIN users c ON c.id=b.client_id LEFT JOIN users w ON w.id=b.worker_id
  ORDER BY b.created_at DESC");
$finished = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE status IN ('completed','cancelled')")->fetch_assoc()['c'];
$unpaidOnline = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE payment_method IN ('gcash','maya') AND payment_status='unpaid' AND status NOT IN ('cancelled','completed')")->fetch_assoc()['c'];
$cashAwaiting = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE payment_method='cash' AND payment_status='unpaid' AND status='awaiting_verification'")->fetch_assoc()['c'];
$pinnedBookings = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE address_lat IS NOT NULL AND address_lng IS NOT NULL AND status NOT IN ('cancelled')")->fetch_assoc()['c'];
?>
<h1>Manage Bookings</h1>
<div class="card">
  <p class="muted" style="margin-bottom:14px;">Monitor worker assignment, payment timing, and the customer's map pin. Online bookings must be prepaid before work starts; cash is confirmed after the job is reviewed. Reassign only jobs that have not started.</p>
  <div class="market-admin-stats booking-admin-stats">
    <div class="market-admin-stat"><span class="market-admin-stat-icon">◷</span><div><b><?= $unpaidOnline ?></b><small>Online payments pending</small></div></div>
    <div class="market-admin-stat"><span class="market-admin-stat-icon">₱</span><div><b><?= $cashAwaiting ?></b><small>Cash jobs awaiting confirmation</small></div></div>
    <div class="market-admin-stat"><span class="market-admin-stat-icon">⌖</span><div><b><?= $pinnedBookings ?></b><small>Bookings with map pins</small></div></div>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
    <h2 style="margin-bottom:0;">All bookings <span class="muted" style="font-weight:400;">(<?= (int)$res->num_rows ?>)</span></h2>
    <?php if ((int)$finished > 0): ?>
      <form method="post" onsubmit="return confirm('Purge all $finished finished booking(s) site-wide?\n\nThis permanently deletes their checklists, photos, feedback and uploaded files.');">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline" name="purge_finished" value="1">🧹 Purge finished (<?= (int)$finished ?>)</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
  <table class="fixed-cols">
    <colgroup><col style="width:4%"><col style="width:10%"><col style="width:13%"><col style="width:11%"><col style="width:15%"><col style="width:9%"><col style="width:11%"><col style="width:20%"><col style="width:7%"></colgroup>
    <tr><th>ID</th><th>Client</th><th>Service</th><th>Schedule</th><th>Location</th><th>Status</th><th>Payment</th><th>Assign worker</th><th></th></tr>
    <?php while ($b = $res->fetch_assoc()): ?>
    <tr>
      <td>#<?= $b['id'] ?></td>
      <td><?= e($b['client']) ?></td>
      <td><?= e($b['service']) ?><br><span class="muted">₱<?= number_format($b['total_price'],2) ?></span></td>
      <td><?= date('M j, Y g:i A', strtotime($b['schedule'])) ?></td>
      <td><?= e($b['address']) ?><?php if ($b['address_lat'] !== null && $b['address_lng'] !== null): ?><br><a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e((string)$b['address_lat']) ?>&amp;mlon=<?= e((string)$b['address_lng']) ?>#map=17/<?= e((string)$b['address_lat']) ?>/<?= e((string)$b['address_lng']) ?>">Open pin ↗</a><?php else: ?><br><small class="muted">No pin</small><?php endif; ?></td>
      <td><span class="badge <?= status_class($b['status']) ?>"><?= str_replace('_',' ',$b['status']) ?></span><?php if ($b['status'] === 'cancelled' && !empty($b['cancel_reason'])): ?><br><small class="muted" title="<?= e($b['cancel_reason']) ?>"><?= e(mb_strimwidth($b['cancel_reason'],0,40,'…')) ?></small><?php endif; ?></td>
      <td><?= e($b['payment_status'] === 'demo_paid' ? 'demo-paid · no money transferred' : $b['payment_status']) ?><br><small class="muted"><?= e(ucfirst($b['payment_method'])) ?></small><?php if ($b['payment_reference']): ?><br><small class="muted"><?= e($b['payment_reference']) ?></small><?php endif; ?></td>
      <td>
        <?php if ($b['status'] === 'cancelled' && $b['worker_id'] !== null): ?><span class="muted">Customer cancelled</span><?php else: ?>
        <form method="post" class="admin-assignment-form" style="display:flex;gap:6px;">
          <?= csrf_field() ?>
          <input type="hidden" name="assign" value="<?= $b['id'] ?>">
          <select name="worker_id" aria-label="Assign worker for booking #<?= (int)$b['id'] ?>" style="margin:0;">
            <option value="">— none —</option>
            <?php foreach ($workers_list as $w): ?>
              <option value="<?= $w['id'] ?>" <?= $b['worker_id'] == $w['id'] ? 'selected' : '' ?> <?= ((int)$w['active_jobs'] > 0 && (int)$b['worker_id'] !== (int)$w['id']) ? 'disabled' : '' ?>><?= e($w['name']) ?> · <?= (int)$w['active_jobs'] > 0 ? 'Busy' : 'Available' ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm">Save</button>
        </form><?php endif; ?>
      </td>
      <td>
        <form method="post" onsubmit="return confirm('Delete booking #<?= $b['id'] ?>\n<?= e($b['client']) ?> · <?= e($b['service']) ?>\n\nThis permanently removes the checklist, photos, feedback and uploaded files.');">
          <?= csrf_field() ?>
          <button class="icon-btn danger" name="delete" value="<?= $b['id'] ?>" title="Delete booking" aria-label="Delete booking">🗑</button>
        </form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  </div>
</div>
<?php require '../footer.php'; ?>
