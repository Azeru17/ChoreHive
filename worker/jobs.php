<?php
require '../config.php';
$u = require_role('worker');

function advance($conn, $id, $wid, $from, $to) {
    $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=? AND worker_id=? AND status=?");
    $stmt->bind_param('siis', $to, $id, $wid, $from);
    $stmt->execute();
    return $stmt->affected_rows === 1;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $back = (($_POST['return_to'] ?? '') === 'dashboard') ? 'dashboard.php' : 'jobs.php';
    if (isset($_POST['start'])) {
        $jid = v_int('start', 1, PHP_INT_MAX, 'Job');
        $jobStmt = $conn->prepare("SELECT client_id,payment_method,payment_status FROM bookings WHERE id=? AND worker_id=? AND status='assigned'");
        $jobStmt->bind_param('ii', $jid, $u['id']); $jobStmt->execute(); $job = $jobStmt->get_result()->fetch_assoc();
        if (!$job) fail('This job is no longer available to start. Refresh your job list.');
        if (in_array($job['payment_method'], ['gcash','maya'], true) && !in_array($job['payment_status'], ['paid','demo_paid'], true)) {
            fail('The customer must complete GCash or Maya payment before you can start this job.');
        }
        if (!advance($conn, $jid, $u['id'], 'assigned', 'in_progress')) fail('The job status changed before it could be started. Refresh and try again.');
        notify_and_email($job['client_id'], "{$u['name']} started working on your booking #$jid. Open the booking and press Confirm Job to acknowledge the start.", 'Job started', 'client/booking.php?id=' . $jid);
        audit_log('job_started', "Worker {$u['name']} started booking #$jid");
        flash('Job confirmed and started.');
        header('Location: ' . $back); exit;
    }
    if (isset($_POST['finish'])) {
        $jid = v_int('finish', 1, PHP_INT_MAX, 'Job');
        $fin = $conn->prepare("SELECT status, client_started_ack FROM bookings WHERE id=? AND worker_id=?");
        $fin->bind_param('ii', $jid, $u['id']); $fin->execute();
        $finRow = $fin->get_result()->fetch_assoc();
        if (!$finRow || $finRow['status'] !== 'in_progress') fail('The job must be in progress before it can be marked done. Refresh and try again.');
        if ((int)$finRow['client_started_ack'] !== 1) fail('The client has not confirmed the job started yet. Ask them to press Confirm Job before you mark it done.');
        if (!advance($conn, $jid, $u['id'], 'in_progress', 'awaiting_verification')) fail('The job status changed before it could be marked done. Refresh and try again.');
        $c = $conn->query("SELECT client_id FROM bookings WHERE id=$jid")->fetch_assoc();
        if ($c) notify_and_email($c['client_id'], "Your booking #$jid is ready for review — check the after photos and confirm the selected payment flow.", 'Job awaiting verification', 'client/booking.php?id=' . $jid);
        audit_log('job_finished', "Worker {$u['name']} finished booking #$jid");
        flash('Job marked done. The client has been notified to review it.');
        header('Location: ' . $back); exit;
    }
    if (isset($_POST['decline'])) {
        $jid = v_int('decline', 1, PHP_INT_MAX, 'Job');
        $row = $conn->query("SELECT b.*, s.name service FROM bookings b JOIN services s ON s.id=b.service_id
                             WHERE b.id=$jid AND b.worker_id={$u['id']}")->fetch_assoc();
        if (!$row) fail('Job not found.');
        if ($row['status'] !== 'assigned') fail('You can only decline a job that has not been started.');
        if ($row['payment_method'] !== 'cash' || $row['payment_status'] !== 'unpaid') {
            fail('Online-payment bookings cannot be declined here. Ask an administrator to help with reassignment or payment handling.');
        }
        $reason = trim((string)($_POST['decline_reason'] ?? ''));
        $reasonOther = trim((string)($_POST['decline_reason_other'] ?? ''));
        if ($reason === 'other' && $reasonOther !== '') {
            $reason = mb_substr('Other: ' . $reasonOther, 0, 255);
        } elseif ($reason === '') {
            $reason = null;
        } else {
            $reason = mb_substr($reason, 0, 255);
        }
        $st = $conn->prepare("UPDATE bookings SET status='cancelled', worker_id=NULL, cancel_reason=? WHERE id=? AND worker_id=? AND status='assigned' AND payment_method='cash' AND payment_status='unpaid'");
        $st->bind_param('sii', $reason, $jid, $u['id']);
        $st->execute();
        if ($st->affected_rows !== 1) fail('This job changed before it could be declined. Refresh your job list and contact an administrator if payment was made.');
        notify_and_email($row['client_id'], "{$u['name']} declined your booking #$jid ({$row['service']}). Please pick another worker or rebook.", 'Worker declined', 'client/booking.php?id=' . $jid);
        $admins = $conn->query("SELECT id FROM users WHERE role='admin'");
        while ($a = $admins->fetch_assoc()) notify_and_email($a['id'], "Booking #$jid was declined by {$u['name']} — it is unassigned again.", 'Booking declined', 'admin/bookings.php');
        audit_log('booking_declined', "Worker {$u['name']} declined job #$jid" . ($reason ? " — reason: $reason" : ''));
        flash('You declined the job. The client and admins have been notified.');
        header('Location: ' . $back); exit;
    }
    if (isset($_POST['delete_job'])) {
        $jid = v_int('delete_job', 1, PHP_INT_MAX, 'Job');
        $row = $conn->query("SELECT b.*, s.name service FROM bookings b JOIN services s ON s.id=b.service_id
                             WHERE b.id=$jid AND b.worker_id={$u['id']}")->fetch_assoc();
        if (!$row) fail('Job not found.');
        if (!booking_is_finished($row['status'])) fail('Only completed or cancelled jobs can be deleted.');
        [$n, $files] = delete_booking_record($jid);
        notify_and_email($row['client_id'], "Job #$jid ({$row['service']}) was removed from your worker's history.", 'Job record deleted', 'client/bookings.php');
        audit_log('booking_deleted', "Worker {$u['name']} deleted job #$jid ({$row['status']}); $files photo file(s) removed");
        flash('Job deleted permanently.');
        header('Location: jobs.php'); exit;
    }
    if (isset($_POST['clear_history'])) {
        [$n, $files] = purge_user_history($u['id'], 'worker_id');
        audit_log('history_cleared', "Worker {$u['name']} cleared $n finished job(s); $files photo file(s) removed");
        flash($n ? "Cleared $n finished job(s) from your work list." : 'No finished jobs to clear yet.', $n ? 'success' : 'error');
        header('Location: jobs.php'); exit;
    }
}

$title = 'My Jobs';
require '../layout.php';

$res = $conn->query("SELECT b.*, s.name service, u2.name client FROM bookings b
  JOIN services s ON s.id=b.service_id JOIN users u2 ON u2.id=b.client_id
  WHERE b.worker_id={$u['id']} ORDER BY FIELD(b.status,'assigned','in_progress','awaiting_verification','completed'), b.schedule");
$finished = (int)$conn->query("SELECT COUNT(*) c FROM bookings
                WHERE worker_id={$u['id']} AND status IN ('completed','cancelled')")->fetch_assoc()['c'];
?>
<h1>My Jobs</h1>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
    <h2 style="margin-bottom:0;">Work list</h2>
    <?php if ((int)$finished > 0): ?>
      <form method="post" data-confirm="Clear all $finished finished job(s)?\n\nTheir checklists, photos and client feedback will be permanently deleted." data-confirm-title="Clear finished jobs" data-confirm-yes="Yes, clear all">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline" name="clear_history" value="1">🧹 Clear history (<?= (int)$finished ?>)</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if ($res->num_rows === 0): ?><div class="empty-state"><span class="big">📋</span>No jobs assigned yet.</div><?php endif; ?>
  <div class="table-wrap">
  <table>
    <tr><th>ID</th><th>Client</th><th>Service</th><th>Schedule</th><th>Address</th><th>Status</th><th></th></tr>
    <?php while ($b = $res->fetch_assoc()): ?>
    <tr>
      <td>#<?= $b['id'] ?></td>
      <td><?= e($b['client']) ?></td>
      <td><?= e($b['service']) ?></td>
      <td><?= date('M j, Y g:i A', strtotime($b['schedule'])) ?></td>
      <td><?= e($b['address']) ?></td>
      <td><span class="badge <?= status_class($b['status']) ?>"><?= str_replace('_',' ',$b['status']) ?></span></td>
      <td><div class="worker-job-actions">
        <a class="btn btn-sm btn-sec" href="job.php?id=<?= $b['id'] ?>">Open</a>
        <?php if ($b['status'] === 'assigned'): ?>
          <?php if (in_array($b['payment_method'], ['gcash','maya'], true) && !in_array($b['payment_status'], ['paid','demo_paid'], true)): ?><span class="payment-waiting-pill" title="Waiting for customer prepayment">Waiting for payment</span><?php else: ?><form method="post" style="display:inline" data-confirm="Start booking #<?= (int)$b['id'] ?> now? This changes the booking to In progress and notifies the customer. It does not mark the job complete." data-confirm-title="Start job"><?= csrf_field() ?><button class="btn btn-sm" name="start" value="<?= $b['id'] ?>">Confirm Job</button></form><?php endif; ?>
          <?php if ($b['payment_method'] !== 'cash'): ?><span class="payment-waiting-pill" title="Online payments cannot currently be refunded automatically or declined from the worker page. Contact an administrator for reassignment and refund assistance.">Contact admin: reassignment / refund</span><?php else: ?>
          <form method="post" style="display:inline" class="declineForm" data-confirm="Reject this job?\n\nIt will be unassigned and the client will be notified so they can pick someone else." data-confirm-title="Reject job" data-confirm-yes="Yes, reject"><?= csrf_field() ?>
            <input type="hidden" name="decline" value="<?= $b['id'] ?>">
            <button type="button" class="btn btn-sm btn-outline declineToggle" title="Reject this job">Reject</button>
            <span class="declineReason" style="display:none;">
              <select name="decline_reason" style="font-size:.78rem;padding:4px 6px;margin-left:4px;">
                <option value="">Reason…</option>
                <option value="Scheduling conflict">Scheduling conflict</option>
                <option value="Too far / address unreachable">Too far / address unreachable</option>
                <option value="Not qualified for the job">Not qualified for the job</option>
                <option value="Already booked elsewhere">Already booked elsewhere</option>
                <option value="other">Other…</option>
              </select>
              <input name="decline_reason_other" maxlength="200" placeholder="Specify" style="display:none;font-size:.78rem;padding:4px 6px;margin-left:4px;width:120px;">
              <button type="submit" class="btn btn-sm" style="background:#c0392b;border-color:#c0392b;margin-left:4px;">Reject job</button>
            </span>
          </form>
          <?php endif; ?>
        <?php elseif ($b['status'] === 'in_progress'): ?>
          <?php if ((int)$b['client_started_ack'] === 1): ?>
            <form method="post" style="display:inline" data-confirm="Mark booking #<?= (int)$b['id'] ?> as done? The customer will be asked to review the photos and confirm completion. For cash bookings, this does not record payment; the customer confirms cash separately." data-confirm-title="Mark job done"><?= csrf_field() ?><button class="btn btn-sm" name="finish" value="<?= $b['id'] ?>">Job Done</button></form>
          <?php else: ?>
            <span class="payment-waiting-pill" title="Waiting for the client to confirm the job started">Waiting for client</span>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (booking_is_finished($b['status'])): ?>
          <form method="post" style="display:inline" data-confirm="Delete job #<?= $b['id'] ?> from your list?\n\nIts checklist, photos and the client’s feedback will be removed too." data-confirm-title="Delete job" data-confirm-yes="Yes, delete"><?= csrf_field() ?><button class="icon-btn danger" name="delete_job" value="<?= $b['id'] ?>" title="Delete finished job" aria-label="Delete finished job">🗑</button></form>
        <?php endif; ?>
      </div></td>
    </tr>
    <?php endwhile; ?>
  </table>
  </div>
</div>
<script>
(() => {
  document.querySelectorAll('.declineForm').forEach(form => {
    const toggle = form.querySelector('.declineToggle');
    const reason = form.querySelector('.declineReason');
    const sel = form.querySelector('select[name=decline_reason]');
    const other = form.querySelector('input[name=decline_reason_other]');
    if (!toggle || !reason) return;
    toggle.addEventListener('click', () => { reason.style.display = 'inline'; toggle.style.display = 'none'; });
    if (sel && other) sel.addEventListener('change', () => { other.style.display = sel.value === 'other' ? 'inline-block' : 'none'; });
  });
})();
</script>
<?php require '../footer.php'; ?>
