<?php
require '../config.php';
$u = require_role('worker');
$title = 'Worker Dashboard';
require '../layout.php';

$stats = $conn->query("SELECT
  SUM(status IN ('assigned','in_progress')) active,
  SUM(status='awaiting_verification') review,
  SUM(status='completed') completed
  FROM bookings WHERE worker_id={$u['id']}")->fetch_assoc();
$avg = $conn->query("SELECT AVG(f.rating) r, COUNT(f.id) c FROM feedback f
  JOIN bookings b ON b.id=f.booking_id WHERE b.worker_id={$u['id']}")->fetch_assoc();
$badges = $conn->query("SELECT COUNT(*) c FROM badges WHERE worker_id={$u['id']}")->fetch_assoc()['c'];
$workerReview = $conn->query("SELECT worker_approved FROM users WHERE id=" . (int)$u['id'])->fetch_assoc();
$financeReady = ($conn->query("SHOW TABLES LIKE 'worker_finance_entries'")->num_rows > 0);
$workerFinance = ['payout'=>0,'bonus'=>0,'deduction'=>0,'discount'=>0];
if ($financeReady) {
  $fres = $conn->query("SELECT entry_type, COALESCE(SUM(amount),0) total FROM worker_finance_entries WHERE worker_id=".(int)$u['id']." GROUP BY entry_type");
  while ($fr=$fres->fetch_assoc()) $workerFinance[$fr['entry_type']] = (float)$fr['total'];
}
$myjobs = $conn->query("SELECT b.*, s.name service, c.name client FROM bookings b
  JOIN services s ON s.id=b.service_id JOIN users c ON c.id=b.client_id
  WHERE b.worker_id={$u['id']} AND b.status IN ('assigned','in_progress','awaiting_verification')
  ORDER BY FIELD(b.status,'assigned','in_progress','awaiting_verification'), b.schedule");
?>
<h1>Worker Dashboard</h1>
<?php if (empty($workerReview['worker_approved'])): ?>
  <div class="payment-demo-note"><b>Your profile is not listed yet.</b> Complete your profile so an administrator can review it. You will not appear in client searches or be available for new bookings until your profile is approved. Marketplace approval is not an identity or background check.</div>
<?php endif; ?>
<div class="grid">
  <div class="stat"><b><?= (int)($stats['active'] ?? 0) ?></b>Active jobs</div>
  <div class="stat"><b><?= (int)($stats['review'] ?? 0) ?></b>Awaiting client review</div>
  <div class="stat"><b><?= (int)($stats['completed'] ?? 0) ?></b>Completed</div>
  <div class="stat"><b><?= $avg['c'] ? number_format($avg['r'], 1) . ' ⭐' : '—' ?></b>Rating (<?= (int)$avg['c'] ?>)</div>
  <div class="stat"><b><?= (int)$badges ?></b>Administrator-managed badges</div>
</div>
<div class="card" style="margin-top:16px;">
  <h2>My payment summary</h2>
  <p class="muted">These figures reflect financial entries recorded by the administrator. Completed booking value is not automatically a confirmed worker payout.</p>
  <div class="grid">
    <div class="stat"><b>₱<?= number_format((float)($workerFinance['payout'] ?? 0),2) ?></b>Recorded payouts</div>
    <div class="stat"><b>₱<?= number_format((float)($workerFinance['bonus'] ?? 0),2) ?></b>Bonuses / raises</div>
    <div class="stat"><b>₱<?= number_format((float)($workerFinance['deduction'] ?? 0),2) ?></b>Deductions</div>
    <div class="stat"><b>₱<?= number_format((float)($workerFinance['discount'] ?? 0),2) ?></b>Discount adjustments</div>
  </div>
  <?php if (!$financeReady): ?><p class="muted">Payment ledger setup is pending. Ask the administrator to import <code>migration_finance_and_worker_location.sql</code>.</p><?php else: ?>
  <div class="table-wrap"><table><tr><th>Date</th><th>Type</th><th>Amount</th><th>Description</th></tr>
  <?php $myFinance=$conn->query("SELECT entry_type,amount,note,created_at FROM worker_finance_entries WHERE worker_id=".(int)$u['id']." ORDER BY created_at DESC LIMIT 20");
  if (!$myFinance->num_rows): ?><tr><td colspan="4" class="muted">No payment entries recorded yet.</td></tr><?php endif;
  while($pay=$myFinance->fetch_assoc()): ?><tr><td><?= e(date('M j, Y',strtotime($pay['created_at']))) ?></td><td><?= e(ucfirst($pay['entry_type'])) ?></td><td>₱<?= number_format((float)$pay['amount'],2) ?></td><td><?= e($pay['note']) ?></td></tr><?php endwhile; ?></table></div>
  <?php endif; ?>
</div>
<div class="card" style="margin-top:16px;">
  <h2>Quick actions</h2>
  <div class="actions">
    <a class="btn" href="jobs.php">View my jobs</a>
    <a class="btn btn-sec" href="../chat.php">Customer messages</a>
    <a class="btn btn-sec" href="profile.php">My profile &amp; badges</a>
  </div>
</div>
<div class="card" style="margin-top:16px;">
  <h2>My bookings</h2>
  <?php if ((int)$myjobs->num_rows === 0): ?>
    <div class="empty-state">No active bookings right now. New assignments will appear here.</div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <tr><th>Job</th><th>Client</th><th>Schedule</th><th>Status</th><th>Payment</th><th></th></tr>
    <?php while ($j = $myjobs->fetch_assoc()): ?>
    <tr>
      <td>#<?= (int)$j['id'] ?> · <?= e($j['service']) ?></td>
      <td><?= e($j['client']) ?></td>
      <td><?= date('M j, g:i A', strtotime($j['schedule'])) ?></td>
      <td><span class="badge <?= status_class($j['status']) ?>"><?= str_replace('_',' ',$j['status']) ?></span></td>
      <td><?= e(ucfirst($j['payment_method'])) ?> · <?= e($j['payment_status'] === 'demo_paid' ? 'demo-paid · no money transferred' : $j['payment_status']) ?></td>
      <td>
        <a class="btn btn-sm btn-sec" href="job.php?id=<?= (int)$j['id'] ?>">Open</a>
        <?php if ($j['status'] === 'assigned'): ?>
          <?php if (in_array($j['payment_method'], ['gcash','maya'], true) && !in_array($j['payment_status'], ['paid','demo_paid'], true)): ?>
            <span class="payment-waiting-pill" title="Waiting for customer prepayment">Waiting for payment</span>
          <?php else: ?>
            <form method="post" action="jobs.php" style="display:inline" data-confirm="Start booking #<?= (int)$j['id'] ?> now? This changes the booking to In progress and notifies the customer. It does not mark the job complete." data-confirm-title="Start job"><?= csrf_field() ?><input type="hidden" name="return_to" value="dashboard"><button class="btn btn-sm" name="start" value="<?= (int)$j['id'] ?>">Confirm Job</button></form>
          <?php endif; ?>
          <?php if ($j['payment_method'] === 'cash' && $j['payment_status'] === 'unpaid'): ?>
            <form method="post" action="jobs.php" style="display:inline" data-confirm="Reject this job? It will be unassigned and the client will be notified." data-confirm-title="Reject job" data-confirm-yes="Yes, reject"><?= csrf_field() ?><input type="hidden" name="return_to" value="dashboard"><input type="hidden" name="decline" value="<?= (int)$j['id'] ?>"><button class="btn btn-sm btn-outline">Reject</button></form>
          <?php else: ?>
            <span class="payment-waiting-pill" title="Online payments cannot currently be declined or refunded automatically. Contact an administrator for reassignment and refund assistance.">Contact admin: reassignment / refund</span>
          <?php endif; ?>
        <?php elseif ($j['status'] === 'in_progress'): ?>
          <?php if ((int)$j['client_started_ack'] === 1): ?>
            <form method="post" action="jobs.php" style="display:inline" data-confirm="Mark booking #<?= (int)$j['id'] ?> as done? The customer will be asked to review the photos and confirm completion. For cash bookings, this does not record payment; the customer confirms cash separately." data-confirm-title="Mark job done"><?= csrf_field() ?><input type="hidden" name="return_to" value="dashboard"><button class="btn btn-sm" name="finish" value="<?= (int)$j['id'] ?>">Job Done</button></form>
          <?php else: ?>
            <span class="payment-waiting-pill" title="Waiting for the client to confirm the job started">Waiting for client</span>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php require '../footer.php'; ?>
