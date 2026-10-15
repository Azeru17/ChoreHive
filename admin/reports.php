<?php
require '../config.php';
$u = require_role('admin');

/* ---------- CSV export ---------- */
if (isset($_GET['export'])) {
    $kind = $_GET['export'];
    $rows = [];
    $filename = 'chorehive-' . $kind . '-' . date('Ymd') . '.csv';
    if ($kind === 'bookings') {
        $rows[] = ['ID', 'Client', 'Worker', 'Service', 'Schedule', 'Status', 'Payment method', 'Payment status', 'Total (PHP)'];
        $res = $conn->query("SELECT b.id, c.name client, w.name worker, s.name service, b.schedule, b.status, b.payment_method, b.payment_status, b.total_price
            FROM bookings b JOIN services s ON s.id=b.service_id JOIN users c ON c.id=b.client_id LEFT JOIN users w ON w.id=b.worker_id ORDER BY b.id DESC");
        while ($r = $res->fetch_assoc()) $rows[] = [$r['id'], $r['client'], $r['worker'] ?? 'Unassigned', $r['service'], $r['schedule'], $r['status'], $r['payment_method'], $r['payment_status'], $r['total_price']];
    } elseif ($kind === 'workers') {
        $rows[] = ['Worker', 'Completed jobs', 'Recorded paid booking value (PHP)', 'Avg rating', 'Badges'];
        $res = $conn->query("SELECT u.name, COUNT(b.id) jobs, COALESCE(SUM(CASE WHEN b.payment_status='paid' THEN b.total_price ELSE 0 END),0) revenue, ROUND(AVG(f.rating),1) rating,
            (SELECT COUNT(*) FROM badges bd WHERE bd.worker_id=u.id) badges
            FROM users u LEFT JOIN bookings b ON b.worker_id=u.id AND b.status='completed'
            LEFT JOIN feedback f ON f.booking_id=b.id WHERE u.role='worker' GROUP BY u.id ORDER BY jobs DESC");
        while ($r = $res->fetch_assoc()) $rows[] = [$r['name'], $r['jobs'], $r['revenue'], $r['rating'] ?? 0, $r['badges']];
    } elseif ($kind === 'revenue') {
        $rows[] = ['Month', 'Completed bookings', 'Recorded paid booking value (PHP)'];
        $res = $conn->query("SELECT DATE_FORMAT(schedule,'%Y-%m') ym, SUM(CASE WHEN payment_status='paid' THEN total_price ELSE 0 END) rev, COUNT(*) c FROM bookings WHERE status='completed' GROUP BY ym ORDER BY ym DESC");
        while ($r = $res->fetch_assoc()) $rows[] = [$r['ym'], $r['c'], $r['rev']];
    } else {
        header('Location: reports.php');
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads ₱ and accents correctly
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}

$title = 'Reports & Performance';
require '../layout.php';

$revenue = $conn->query("SELECT COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_price ELSE 0 END),0) t, COUNT(*) c FROM bookings WHERE status='completed'")->fetch_assoc();
$by_service = $conn->query("SELECT s.name, COUNT(b.id) c, COALESCE(SUM(CASE WHEN b.payment_status='paid' THEN b.total_price ELSE 0 END),0) rev FROM services s
  LEFT JOIN bookings b ON b.service_id=s.id AND b.status='completed' GROUP BY s.id ORDER BY rev DESC");
$by_month = $conn->query("SELECT DATE_FORMAT(schedule,'%Y-%m') ym, SUM(CASE WHEN payment_status='paid' THEN total_price ELSE 0 END) rev, COUNT(*) c FROM bookings
  WHERE status='completed' GROUP BY ym ORDER BY ym DESC LIMIT 6");
$worker_perf = $conn->query("SELECT u.name, COUNT(b.id) jobs,
  COALESCE(SUM(CASE WHEN b.payment_status='paid' THEN b.total_price ELSE 0 END),0) revenue, ROUND(AVG(f.rating),1) rating
  FROM users u LEFT JOIN bookings b ON b.worker_id=u.id AND b.status='completed'
  LEFT JOIN feedback f ON f.booking_id=b.id
  WHERE u.role='worker' GROUP BY u.id ORDER BY jobs DESC");

// Rebooking insights: identify returning customers from their completed booking history.
$repeat_stats = $conn->query("SELECT
  (SELECT COUNT(*) FROM (
    SELECT client_id FROM bookings WHERE status='completed'
    GROUP BY client_id HAVING COUNT(*) > 1
  ) returning_clients) AS repeat_clients,
  (SELECT COUNT(*) FROM bookings b WHERE b.status='completed' AND b.client_id IN (
    SELECT client_id FROM bookings WHERE status='completed'
    GROUP BY client_id HAVING COUNT(*) > 1
  )) AS completed_by_returning,
  (SELECT COUNT(*) FROM bookings WHERE status='completed') AS completed_total
")->fetch_assoc();
$repeat_services = $conn->query("SELECT s.name, COUNT(b.id) bookings,
  COALESCE(SUM(CASE WHEN b.payment_status='paid' THEN b.total_price ELSE 0 END),0) revenue
  FROM bookings b JOIN services s ON s.id=b.service_id
  WHERE b.status='completed' AND b.client_id IN (
    SELECT client_id FROM bookings WHERE status='completed'
    GROUP BY client_id HAVING COUNT(*) > 1
  )
  GROUP BY s.id ORDER BY bookings DESC, revenue DESC LIMIT 5");
$repeat_rate = (int)$repeat_stats['completed_total'] > 0
  ? round((int)$repeat_stats['completed_by_returning'] / (int)$repeat_stats['completed_total'] * 100)
  : 0;

/* Worker schedule calendar: upcoming assigned/in-progress jobs */
$month = isset($_GET['m']) ? DateTime::createFromFormat('Y-m', $_GET['m']) : new DateTime();
if (!$month) $month = new DateTime();
$month_start = (clone $month)->modify('first day of this month');
$prev = (clone $month)->modify('-1 month')->format('Y-m');
$next = (clone $month)->modify('+1 month')->format('Y-m');

$sched = [];
$q = $conn->query("SELECT b.schedule, b.status, s.name service, u.name worker FROM bookings b
  JOIN services s ON s.id=b.service_id JOIN users u ON u.id=b.worker_id
  WHERE b.status IN ('assigned','in_progress','awaiting_verification')");
while ($r = $q->fetch_assoc()) {
    $sched[date('Y-m-d', strtotime($r['schedule']))][] = $r;
}
$workers_list = $conn->query("SELECT id,name FROM users WHERE role='worker'")->fetch_all(MYSQLI_ASSOC);
$wcolors = [];
$palette = ['#1565c0','#2e7d32','#b26a00','#7b1fa2','#00838f','#c62828'];
foreach ($workers_list as $i => $w) $wcolors[$w['id']] = $palette[$i % count($palette)];

$cursor = clone $month_start;
$cursor->modify((-1 * (int)$cursor->format('w')) . ' days');
$max_rev = 0.0;
$rev_rows = [];
$m2 = $conn->query("SELECT DATE_FORMAT(schedule,'%Y-%m') ym, SUM(CASE WHEN payment_status='paid' THEN total_price ELSE 0 END) rev, COUNT(*) c FROM bookings
  WHERE status='completed' GROUP BY ym ORDER BY ym DESC LIMIT 6");
while ($r = $m2->fetch_assoc()) { $rev_rows[] = $r; $max_rev = max($max_rev, (float)$r['rev']); }
?>
<h1>Reports &amp; Performance Dashboard</h1>
<div class="card" style="margin-bottom:16px;">
  <p style="margin:0 0 10px;"><b>Download data</b> — export any report as a CSV file (opens in Excel, Google Sheets, or LibreOffice).</p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a class="btn btn-sm btn-sec" href="reports.php?export=bookings">⬇ Bookings (CSV)</a>
    <a class="btn btn-sm btn-sec" href="reports.php?export=workers">⬇ Worker performance (CSV)</a>
    <a class="btn btn-sm btn-sec" href="reports.php?export=revenue">⬇ Paid booking value by month (CSV)</a>
  </div>
</div>
<div class="grid">
  <div class="stat"><b>₱<?= number_format($revenue['t'],2) ?></b>Recorded paid booking value</div>
  <div class="stat"><b><?= (int)$revenue['c'] ?></b>Completed jobs</div>
</div>

<div class="card" style="margin-top:16px;">
  <h2>💰 Recorded Paid Booking Value by Month</h2>
  <p class="muted">Simulation-only bookings are excluded. These totals are not ChoreHive platform revenue or confirmed worker payouts.</p>
  <?php if (!$rev_rows): ?><p class="muted">No completed bookings yet.</p><?php endif; ?>
  <?php foreach ($rev_rows as $r): $pct = $max_rev > 0 ? round((float)$r['rev'] / $max_rev * 100) : 0; ?>
    <div style="margin:8px 0;">
      <div style="display:flex;justify-content:space-between;font-size:.88rem;">
        <b><?= DateTime::createFromFormat('!Y-m', $r['ym'])->format('F Y') ?></b>
        <span>₱<?= number_format($r['rev'], 2) ?> · <?= $r['c'] ?> job<?= $r['c'] > 1 ? 's' : '' ?></span>
      </div>
      <div style="background:#e6ecf5;border-radius:8px;height:14px;">
        <div style="width:<?= max($pct, 2) ?>%;background:linear-gradient(90deg,#e0aa33,#c1861a);height:14px;border-radius:8px;"></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>📊 Completed Booking Value by Service</h2>
  <div class="table-wrap">
  <table>
    <tr><th>Service</th><th>Completed bookings</th><th>Recorded paid booking value</th></tr>
    <?php while ($r = $by_service->fetch_assoc()): ?>
      <tr><td><?= e($r['name']) ?></td><td><?= $r['c'] ?></td><td>₱<?= number_format($r['rev'],2) ?></td></tr>
    <?php endwhile; ?>
  </table>
  </div>
</div>

<div class="card">
  <h2>🔁 Rebooking Pattern Insights</h2>
  <p class="muted" style="margin:-4px 0 14px;">Returning customers are identified by having more than one completed booking.</p>
  <div class="grid">
    <div class="stat"><b><?= (int)$repeat_stats['repeat_clients'] ?></b>Returning customers</div>
    <div class="stat"><b><?= (int)$repeat_stats['completed_by_returning'] ?></b>Completed bookings from returning customers</div>
    <div class="stat"><b><?= $repeat_rate ?>%</b>Returning-customer booking share</div>
  </div>
  <h3 style="font-size:1rem;margin:18px 0 10px;color:var(--navy);">Most-booked services by returning customers</h3>
  <div class="table-wrap">
    <table>
      <tr><th>Service</th><th>Completed bookings</th><th>Recorded paid booking value</th></tr>
      <?php if ($repeat_services->num_rows === 0): ?>
        <tr><td colspan="3" class="muted">No repeat-customer booking data yet. This section will populate as customers complete multiple bookings.</td></tr>
      <?php else: ?>
        <?php while ($r = $repeat_services->fetch_assoc()): ?>
          <tr><td><?= e($r['name']) ?></td><td><?= (int)$r['bookings'] ?></td><td>₱<?= number_format((float)$r['revenue'],2) ?></td></tr>
        <?php endwhile; ?>
      <?php endif; ?>
    </table>
  </div>
</div>

<div class="card">
  <h2>👷 Worker Performance</h2>
  <div class="table-wrap">
  <table>
    <tr><th>Worker</th><th>Completed jobs</th><th>Recorded paid booking value</th><th>Avg rating</th></tr>
    <?php while ($r = $worker_perf->fetch_assoc()): ?>
      <tr><td><?= e($r['name']) ?></td><td><?= $r['jobs'] ?></td>
      <td>₱<?= number_format($r['revenue'],2) ?></td>
      <td><?= $r['rating'] ? str_repeat('⭐', (int)round($r['rating'])) . ' ' . $r['rating'] : '—' ?></td></tr>
    <?php endwhile; ?>
  </table>
  </div>
</div>

<div class="card">
  <h2 style="display:flex;justify-content:space-between;align-items:center;">
    <a href="?m=<?= $prev ?>" style="text-decoration:none;">‹</a>
    <span>🗓 Worker Schedule — <?= $month_start->format('F Y') ?></span>
    <a href="?m=<?= $next ?>" style="text-decoration:none;">›</a>
  </h2>
  <p class="muted">Upcoming active jobs per worker. Colors = worker.</p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:6px 0 10px;">
    <?php foreach ($workers_list as $w): ?>
      <span style="font-size:.8rem;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $wcolors[$w['id']] ?>;"></span> <?= e($w['name']) ?></span>
    <?php endforeach; ?>
  </div>
  <table class="calendar" style="min-width:0;">
    <tr><th>S</th><th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th></tr>
    <tr>
    <?php for ($i = 0; $i < 42; $i++):
        $key = $cursor->format('Y-m-d');
        $in_month = $cursor->format('Y-m') === $month_start->format('Y-m');
        $is_today = $key === date('Y-m-d');
        $jobs = $sched[$key] ?? [];
        if ($i > 0 && $i % 7 === 0) echo '</tr><tr>';
        ?>
        <td class="cal <?= $in_month ? '' : 'out' ?> <?= $is_today ? 'today' : '' ?>" title="<?= count($jobs) ?> job(s)">
          <div class="daynum"><?= (int)$cursor->format('j') ?></div>
          <?php foreach ($jobs as $j): ?>
            <div style="width:9px;height:9px;border-radius:50%;margin:2px auto 0;background:<?= $wcolors[$j['worker']] ?? '#888' ?>;" title="<?= e($j['worker']) ?> — <?= e($j['service']) ?> (<?= str_replace('_',' ',$j['status']) ?>)"></div>
          <?php endforeach; ?>
        </td>
        <?php $cursor->modify('+1 day'); endfor; ?>
    </tr>
  </table>
  <?php
  // upcoming list under calendar
  $upcoming = $conn->query("SELECT b.schedule, b.status, s.name service, u.name worker FROM bookings b
    JOIN services s ON s.id=b.service_id JOIN users u ON u.id=b.worker_id
    WHERE b.status IN ('assigned','in_progress','awaiting_verification') AND b.schedule >= NOW()
    ORDER BY b.schedule LIMIT 8");
  if ($upcoming->num_rows): ?>
    <h2 style="margin-top:14px;">Next jobs</h2>
    <div class="table-wrap">
    <table class="narrow">
      <?php while ($r = $upcoming->fetch_assoc()): ?>
        <tr><td><?= date('D, M j g:i A', strtotime($r['schedule'])) ?></td>
        <td><b><?= e($r['worker']) ?></b></td><td><?= e($r['service']) ?></td>
        <td><span class="badge <?= status_class($r['status']) ?>"><?= str_replace('_',' ',$r['status']) ?></span></td></tr>
      <?php endwhile; ?>
    </table>
    </div>
  <?php endif; ?>
</div>
<style>
  .calendar { table-layout:fixed; }
  .calendar td.cal { text-align:center; vertical-align:top; height:46px; padding:3px; border:1px solid #e3e9f2; }
  .calendar .out { color:#a9b6cc; background:#f7f9fd; }
  .calendar .today { background:#eef4fd; }
  .daynum { font-size:.8rem; color:#6b7a90; }
</style>
<?php require '../footer.php'; ?>
