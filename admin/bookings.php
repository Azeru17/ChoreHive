<?php
require '../config.php';
$u = require_role('admin');

/* Worker assignment is controlled by the booking workflow, not by the admin dashboard. */

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

$bookingSearch = trim((string)($_GET['q'] ?? ''));
$bookingStatus = trim((string)($_GET['status'] ?? ''));
$allowedStatuses = ['pending','assigned','in_progress','awaiting_verification','completed','cancelled'];
$whereParts = [];
if ($bookingSearch !== '') {
  $safeSearch = $conn->real_escape_string($bookingSearch);
  $whereParts[] = "(CAST(b.id AS CHAR) LIKE '%$safeSearch%' OR c.name LIKE '%$safeSearch%' OR w.name LIKE '%$safeSearch%' OR s.name LIKE '%$safeSearch%' OR b.address LIKE '%$safeSearch%')";
}
if (in_array($bookingStatus, $allowedStatuses, true)) $whereParts[] = "b.status='".$conn->real_escape_string($bookingStatus)."'";
$bookingWhere = $whereParts ? ' WHERE '.implode(' AND ', $whereParts) : '';
$res = $conn->query("SELECT b.*, s.name service, c.name client, w.name worker,
  (SELECT COUNT(*) FROM booking_photos p WHERE p.booking_id=b.id AND p.stage='before') before_count,
  (SELECT COUNT(*) FROM booking_photos p WHERE p.booking_id=b.id AND p.stage='after') after_count
  FROM bookings b JOIN services s ON s.id=b.service_id
  JOIN users c ON c.id=b.client_id LEFT JOIN users w ON w.id=b.worker_id
  $bookingWhere ORDER BY b.created_at DESC");
$finished = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE status IN ('completed','cancelled')")->fetch_assoc()['c'];
$unpaidOnline = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE payment_method IN ('gcash','maya') AND payment_status='unpaid' AND status NOT IN ('cancelled','completed')")->fetch_assoc()['c'];
$cashAwaiting = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE payment_method='cash' AND payment_status='unpaid' AND status='awaiting_verification'")->fetch_assoc()['c'];
$pinnedBookings = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE address_lat IS NOT NULL AND address_lng IS NOT NULL AND status NOT IN ('cancelled')")->fetch_assoc()['c'];
?>
<h1>Manage Bookings</h1>
<div class="card">
  <p class="muted" style="margin-bottom:14px;">Monitor worker assignment, payment timing, and the customer's map pin. Worker assignments are shown for visibility; admins cannot change a client's booked worker here.</p>
  <div class="market-admin-stats booking-admin-stats">
    <div class="market-admin-stat"><span class="market-admin-stat-icon">◷</span><div><b><?= $unpaidOnline ?></b><small>Online payments pending</small></div></div>
    <div class="market-admin-stat"><span class="market-admin-stat-icon">₱</span><div><b><?= $cashAwaiting ?></b><small>Cash jobs awaiting confirmation</small></div></div>
    <div class="market-admin-stat"><span class="market-admin-stat-icon">⌖</span><div><b><?= $pinnedBookings ?></b><small>Bookings with map pins</small></div></div>
  </div>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:flex-end;">
    <div style="flex:1;min-width:190px;display:flex;flex-direction:column;">
      <label for="bookingSearch" style="margin-bottom:4px;">Search bookings</label>
      <input id="bookingSearch" name="q" value="<?= e($bookingSearch) ?>" placeholder="Client, worker, service, address or ID">
    </div>
    <div style="display:flex;flex-direction:column;">
      <label for="bookingStatus" style="margin-bottom:4px;">Status</label>
      <select id="bookingStatus" name="status"><option value="">All statuses</option><?php foreach ($allowedStatuses as $st): ?><option value="<?= e($st) ?>" <?= $bookingStatus===$st?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$st))) ?></option><?php endforeach; ?></select>
    </div>
    <div style="display:flex;gap:8px;align-items:flex-end;">
      <button class="btn btn-sm" type="submit">Filter</button>
      <a class="btn btn-sm btn-sec" href="bookings.php">Reset</a>
    </div>
  </form>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
    <h2 style="margin-bottom:0;">All bookings <span class="muted" style="font-weight:400;">(<?= (int)$res->num_rows ?> matching)</span></h2>
    <?php if ((int)$finished > 0): ?>
      <form method="post" data-confirm="Purge all $finished finished booking(s) site-wide?\n\nThis permanently deletes their checklists, photos, feedback and uploaded files." data-confirm-title="Purge bookings" data-confirm-yes="Yes, purge">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline" name="purge_finished" value="1">🧹 Purge finished (<?= (int)$finished ?>)</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if ($res->num_rows === 0): ?>
    <div class="empty-state"><span class="big">📋</span>No bookings yet. New bookings will appear here as clients hire cleaners.</div>
  <?php endif; ?>
  <div class="table-wrap">
  <table class="fixed-cols">
    <colgroup><col style="width:5%"><col style="width:11%"><col style="width:11%"><col style="width:11%"><col style="width:11%"><col style="width:12%"><col style="width:11%"><col style="width:20%"><col style="width:8%"></colgroup>
    <tr><th>ID</th><th>Client</th><th>Service</th><th>Schedule</th><th>Location</th><th>Status</th><th>Payment</th><th>Booked worker</th><th></th></tr>
    <?php while ($b = $res->fetch_assoc()): ?>
    <tr>
      <td>#<?= $b['id'] ?></td>
      <td><?= e($b['client']) ?></td>
      <td><?= e($b['service']) ?><br><span class="muted">₱<?= number_format($b['total_price'],2) ?></span></td>
      <td><?= date('M j, Y g:i A', strtotime($b['schedule'])) ?></td>
      <td><?= e($b['address']) ?><?php if ($b['address_lat'] !== null && $b['address_lng'] !== null): ?><br><a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode((string)$b['address_lat'] . ',' . (string)$b['address_lng']) ?>">Open pin ↗</a><?php else: ?><br><small class="muted">No pin</small><?php endif; ?></td>
      <td><span class="badge <?= status_class($b['status']) ?>"><?= str_replace('_',' ',$b['status']) ?></span><br><a href="photos.php?id=<?= (int)$b['id'] ?>">Photos: Before <?= (int)$b['before_count'] ?> · After <?= (int)$b['after_count'] ?></a><?php if ($b['status'] === 'cancelled' && !empty($b['cancel_reason'])): ?><br><small class="muted" title="<?= e($b['cancel_reason']) ?>"><?= e(mb_strimwidth($b['cancel_reason'],0,40,'…')) ?></small><?php endif; ?></td>
      <td><?= e($b['payment_status'] === 'demo_paid' ? 'demo-paid · no money transferred' : $b['payment_status']) ?><br><small class="muted"><?= e(ucfirst($b['payment_method'])) ?></small><?php if ($b['payment_reference']): ?><br><small class="muted"><?= e($b['payment_reference']) ?></small><?php endif; ?></td>
      <td><?php if (!empty($b['worker'])): ?><span class="booking-worker-name"><?= e($b['worker']) ?></span><?php else: ?><span class="muted">Not assigned</span><?php endif; ?></td>
      <td>
        <form method="post" data-confirm="Delete booking #<?= $b['id'] ?>\n<?= e($b['client']) ?> · <?= e($b['service']) ?>\n\nThis permanently removes the checklist, photos, feedback and uploaded files." data-confirm-title="Delete booking" data-confirm-yes="Yes, delete">
          <?= csrf_field() ?>
          <button class="icon-btn danger" name="delete" value="<?= $b['id'] ?>" title="Delete booking" aria-label="Delete booking">🗑</button>
        </form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  </div>
  <?php if ($res->num_rows > 0): ?><p class="table-scroll-hint">Scroll the table sideways to reach booking actions.</p><?php endif; ?>
</div>
<?php require '../footer.php'; ?>
