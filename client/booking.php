<?php
require '../config.php';
require_once '../paymongo.php';
$u = require_role('client');
$id = (int)($_GET['id'] ?? 0);
$b = $conn->query("SELECT b.*, s.name service, s.description sdesc, u.id worker_user_id, u.name worker, u.phone worker_phone, u.email worker_email FROM bookings b
  JOIN services s ON s.id=b.service_id LEFT JOIN users u ON u.id=b.worker_id
  WHERE b.id=$id AND b.client_id={$u['id']}")->fetch_assoc();
if (!$b) {
    http_response_code(404);
    $title = 'Booking not found';
    require '../layout.php';
    echo '<div class="card" style="text-align:center;padding:40px;"><h1>Booking not found</h1><p class="muted">It may have been removed, or it belongs to another account.</p><p style="margin-top:14px;"><a class="btn" href="bookings.php">← Back to My Bookings</a></p></div>';
    require '../footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Online methods are prepaid before the worker can start. After the job, the client
    // only confirms the work; cash bookings are confirmed separately below.
    if (isset($_POST['verify'])) {
        if ($b['status'] !== 'awaiting_verification') fail('This booking is not awaiting verification.');
        if ($b['payment_method'] === 'cash') fail('For cash bookings, use the cash-payment confirmation after handing cash to the worker.');
        if (!in_array($b['payment_status'], ['paid','demo_paid'], true)) {
            header('Location: pay.php?id=' . $id);
            exit;
        }
        $complete = $conn->prepare("UPDATE bookings SET status='completed' WHERE id=? AND client_id=? AND status='awaiting_verification' AND payment_status IN ('paid','demo_paid')");
        $complete->bind_param('ii', $id, $u['id']);
        $complete->execute();
        if ($complete->affected_rows !== 1) fail('This booking changed before verification. Refresh and try again.');
        $demoPayment = $b['payment_status'] === 'demo_paid';
        $paymentDescription = $demoPayment ? 'The online payment was only simulated; no money was transferred.' : 'Your payment was prepaid online.';
        if ($b['worker_id']) notify_and_email($b['worker_id'], "The customer verified booking #$id and marked it complete. $paymentDescription", 'Job verified', 'worker/job.php?id=' . $id);
        notify_and_email($u['id'], "Booking #$id has been verified and completed. " . ($demoPayment ? 'The payment simulation record is available.' : 'Your payment receipt is available.'), 'Booking completed', 'client/booking.php?id=' . $id);
        audit_log('job_verified', "Client {$u['name']} verified " . ($demoPayment ? 'simulation-only' : 'online-prepaid') . " booking #$id");
        flash($demoPayment ? 'Work verified and booking completed. This payment was only simulated; no money was transferred.' : 'Work verified and booking completed. Your receipt is ready.');
        header('Location: booking.php?id=' . $id); exit;
    }
    if (isset($_POST['confirm_job_start'])) {
        if ($b['status'] !== 'in_progress') fail('This job is not currently in progress.');
        if ((int)($b['client_started_ack'] ?? 0) === 1) fail('You have already confirmed this job started.');
        $ackStmt = $conn->prepare("UPDATE bookings SET client_started_ack=1 WHERE id=? AND client_id=? AND status='in_progress'");
        $ackStmt->bind_param('ii', $id, $u['id']);
        $ackStmt->execute();
        if ($ackStmt->affected_rows !== 1) fail('This booking changed before the confirmation could be saved. Refresh and try again.');
        if ($b['worker_id']) notify_and_email($b['worker_id'], "The customer confirmed that you started booking #$id ({$b['service']}). You can mark the job done once it's finished.", 'Job start confirmed', 'worker/job.php?id=' . $id);
        notify_and_email($u['id'], "You confirmed the worker started booking #$id. You'll be asked to confirm the finished job next.", 'Job start confirmed', 'client/booking.php?id=' . $id);
        audit_log('job_start_confirmed', "Client {$u['name']} confirmed the job start for booking #$id");
        flash('Job start confirmed. The worker can now mark the job done once finished.');
        header('Location: booking.php?id=' . $id); exit;
    }
    if (isset($_POST['confirm_cash_payment'])) {
        if ($b['status'] !== 'awaiting_verification') fail('Cash can only be confirmed after the worker marks the job complete.');
        if ($b['payment_method'] !== 'cash') fail('This booking does not use cash payment.');
        if ($b['payment_status'] === 'paid') fail('This booking is already marked as paid.');
        if (($_POST['cash_confirmation'] ?? '') !== 'yes') fail('Please confirm that you handed the cash to the worker.');
        $reference = 'CH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $complete = $conn->prepare("UPDATE bookings SET status='completed',payment_status='paid',payment_reference=?,paid_at=NOW() WHERE id=? AND client_id=? AND status='awaiting_verification' AND payment_method='cash' AND payment_status='unpaid'");
        $complete->bind_param('sii', $reference, $id, $u['id']);
        $complete->execute();
        if ($complete->affected_rows !== 1) fail('This cash payment changed before it could be recorded. Refresh and try again.');
        if ($b['worker_id']) notify_and_email($b['worker_id'], "The customer confirmed receiving/handing over cash for booking #$id — ₱" . number_format((float)$b['total_price'], 2) . '.', 'Cash payment confirmed', 'worker/job.php?id=' . $id);
        notify_and_email($u['id'], "Cash payment for booking #$id was marked as confirmed. Receipt reference: $reference.", 'Payment receipt available', 'client/receipt.php?id=' . $id);
        audit_log('cash_payment_confirmed', "Client {$u['name']} confirmed physical cash payment of ₱{$b['total_price']} for booking #$id (reference $reference)");
        flash('Cash payment recorded and booking completed. Your ChoreHive receipt is ready.');
        header('Location: receipt.php?id=' . $id); exit;
    }
    // Cancel
    if (isset($_POST['cancel'])) {
        if (!in_array($b['status'], ['pending','assigned','in_progress','awaiting_verification'], true)) fail('Only active bookings can be cancelled.');
        $reason = trim((string)($_POST['cancel_reason'] ?? ''));
        $reasonOther = trim((string)($_POST['cancel_reason_other'] ?? ''));
        if ($reason === 'other' && $reasonOther !== '') {
            $reason = mb_substr('Other: ' . $reasonOther, 0, 255);
        } elseif ($reason === '') {
            $reason = null;
        } else {
            $reason = mb_substr($reason, 0, 255);
        }
        $st = $conn->prepare("UPDATE bookings SET status='cancelled', cancel_reason=? WHERE id=? AND client_id=? AND status IN ('pending','assigned','in_progress','awaiting_verification')");
        $st->bind_param('sii', $reason, $id, $u['id']);
        $st->execute();
        if ($st->affected_rows !== 1) fail('This booking changed before it could be cancelled. Refresh and try again.');
        if ($b['worker_id']) {
            notify_and_email($b['worker_id'], "Booking #$id ({$b['service']}) was cancelled by the client. Do not continue work on this booking.", 'Booking cancelled', 'worker/job.php?id=' . $id);
        }
        $clientNotice = "You cancelled booking #$id ({$b['service']}).";
        if ($b['payment_method'] !== 'cash') {
            $clientNotice .= $b['payment_status'] === 'demo_paid'
                ? ' This was a payment simulation only; no money was transferred.'
                : ($b['payment_status'] === 'paid'
                    ? ' Your payment remains recorded; ChoreHive does not automatically issue refunds. Contact support to request a refund.'
                    : ' If you complete payment through an already-open checkout, it will be recorded, but a refund is not automatic; contact support for help.');
        }
        notify_and_email($u['id'], $clientNotice, 'Booking cancelled', 'client/booking.php?id=' . $id);
        audit_log('booking_cancelled', "Booking #$id cancelled by client {$u['name']}" . ($reason ? " — reason: $reason" : ''));
        flash($b['payment_method'] !== 'cash'
            ? ($b['payment_status'] === 'demo_paid'
                ? 'Booking cancelled. The payment was only simulated; no money was transferred.'
                : 'Booking cancelled. Online payments are not refunded automatically; contact support if you need help with a refund.')
            : 'Booking cancelled.');
        header('Location: booking.php?id=' . $id); exit;
    }
    // Feedback
    if (isset($_POST['rating'])) {
        if ($b['status'] !== 'completed') fail('You can only rate completed jobs.');
        $rating = v_int('rating', 1, 5, 'Rating');
        $comment = v_str('comment', 500, false, 'Comment');
        $stmt = $conn->prepare("INSERT INTO feedback (booking_id,client_id,rating,comment) VALUES (?,?,?,?)
                                ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment)");
        $stmt->bind_param('iiis', $id, $u['id'], $rating, $comment);
        $stmt->execute();
        if ($b['worker_id']) {
            notify_and_email($b['worker_id'], "{$u['name']} rated booking #$id {$rating}/5 stars." . ($comment ? " \"$comment\"" : ''), 'New rating received', 'worker/job.php?id=' . $id);
        }
        notify_and_email($u['id'], "Thanks! Your {$rating}/5 rating for booking #$id was saved.", 'Feedback saved', 'client/booking.php?id=' . $id);
        flash('Feedback submitted — thanks for helping workers earn their badges!');
        header('Location: booking.php?id=' . $id); exit;
    }
    // Remove one checklist task (frozen once the client is verifying/paying)
    if (isset($_POST['delete_task'])) {
        if (!checklist_is_editable($b['status'])) fail('The checklist can no longer be edited on this booking.');
        $cid = v_int('delete_task', 1, PHP_INT_MAX, 'Task');
        $stmt = $conn->prepare("DELETE FROM checklist_items WHERE id=? AND booking_id=?");
        $stmt->bind_param('ii', $cid, $id);
        $stmt->execute();
        audit_log('checklist_task_removed', "Client {$u['name']} removed a task from booking #$id");
        flash('Task removed from your checklist.');
        header('Location: booking.php?id=' . $id); exit;
    }
    // Permanently delete a finished booking
    if (isset($_POST['delete_booking'])) {
        if (!booking_is_finished($b['status'])) fail('Cancel this booking first, then you can delete it.');
        [$deleted, $files] = delete_booking_record($id);
        if ($b['worker_id']) notify_and_email($b['worker_id'], "Booking #$id ({$b['service']}) was deleted by the client.", 'Booking deleted', 'worker/jobs.php');
        audit_log('booking_deleted', "Client {$u['name']} deleted booking #$id ({$b['status']}); $files photo file(s) removed");
        flash('Booking deleted permanently.');
        header('Location: bookings.php'); exit;
    }
    // Manual "Refresh payment status" — re-check PayMongo for an unpaid online booking.
    if (isset($_POST['refresh_payment'])) {
        if ($b['payment_method'] === 'cash' || in_array($b['payment_status'], ['paid','demo_paid'], true)) {
            fail('This booking does not need a payment refresh.');
        }
        if (paymongo_sync_booking($b)) {
            flash('Payment confirmed — your booking is now marked as paid.');
        } else {
            flash('No confirmed payment found yet. If you just paid, wait a few seconds and try again.', 'error');
        }
        header('Location: booking.php?id=' . $id); exit;
    }
    fail('Unknown action.');
}

// Self-heal online payments: if the customer paid on PayMongo but never
// returned to the success URL (closed the tab, lost connection), re-check
// PayMongo now so the booking reflects the real payment state. Safe on every
// load — only calls PayMongo for unpaid GCash/Maya bookings that already have
// a checkout id, and it is a no-op when payments are unconfigured.
if ($b['status'] !== 'completed'
    && $b['payment_method'] !== 'cash'
    && $b['payment_status'] === 'unpaid'
    && !empty($b['paymongo_checkout_id'])
    && paymongo_sync_booking($b)) {
    $b = $conn->query("SELECT b.*, s.name service, s.description sdesc, u.id worker_user_id, u.name worker, u.phone worker_phone, u.email worker_email FROM bookings b
      JOIN services s ON s.id=b.service_id LEFT JOIN users u ON u.id=b.worker_id
      WHERE b.id=" . (int)$id . " AND b.client_id=" . (int)$u['id'])->fetch_assoc();
    $wasCancelled = $b['status'] === 'cancelled';
    flash($wasCancelled
        ? 'Payment received for this cancelled booking. It remains cancelled and the payment is not automatically refunded; contact support for help.'
        : 'Payment confirmed — thank you! Your booking is now marked as paid.', $wasCancelled ? 'error' : 'success');
}

$title = 'Booking #' . $id;
require '../layout.php';
$items = $conn->query("SELECT * FROM checklist_items WHERE booking_id=" . (int)$id);
$photos = $conn->query("SELECT * FROM booking_photos WHERE booking_id=" . (int)$id);
$before = []; $after = [];
while ($p = $photos->fetch_assoc()) {
    if ($p['stage'] === 'before') { $before[] = $p; } else { $after[] = $p; }
}
$fb = $conn->query("SELECT * FROM feedback WHERE booking_id=$id")->fetch_assoc();
?>
<h1>Booking #<?= $id ?> — <?= e($b['service']) ?></h1>
<div class="card">
  <p><b>Worker:</b> <?= e($b['worker'] ?? 'Unassigned') ?> &nbsp; <b>Schedule:</b> <?= date('M j, Y g:i A', strtotime($b['schedule'])) ?></p>
  <?php if (!empty($b['worker_user_id'])): ?>
    <p><b>Worker contact:</b>
      <?php if (!empty($b['worker_phone'])): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $b['worker_phone'])) ?>"><?= e($b['worker_phone']) ?></a><?php else: ?><span class="muted">Phone not provided</span><?php endif; ?>
      <?php if (!empty($b['worker_email'])): ?> · <a href="mailto:<?= e($b['worker_email']) ?>"><?= e($b['worker_email']) ?></a><?php endif; ?>
      · <a href="../chat.php?user_id=<?= (int)$b['worker_user_id'] ?>">Chat with worker</a>
    </p>
  <?php endif; ?>
  <p><b>Address:</b> <?= e($b['address']) ?> &nbsp; <b>Total:</b> ₱<?= number_format($b['total_price'], 2) ?></p>
  <?php if ($b['address_lat'] !== null && $b['address_lng'] !== null): ?><p><b>Map pin:</b> <a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode((string)$b['address_lat'] . ',' . (string)$b['address_lng']) ?>">Open pinned location ↗</a></p><?php endif; ?>
  <p><b>Status:</b> <span class="badge <?= status_class($b['status']) ?>"><?= str_replace('_',' ',$b['status']) ?></span>
     <b style="margin-left:16px;">Payment:</b> <?= e(ucfirst($b['payment_method'])) ?> · <?= e($b['payment_status'] === 'demo_paid' ? 'demo-paid · no money transferred' : $b['payment_status']) ?></p>
  <?php if ($b['status'] === 'cancelled' && !empty($b['cancel_reason'])): ?>
    <p style="background:#fff3d6;border:1px solid #f0d9a0;color:#8a6d1f;padding:10px 14px;border-radius:10px;margin:8px 0;"><b>Reason for cancellation:</b> <?= e($b['cancel_reason']) ?></p>
  <?php endif; ?>
  <?php if (in_array($b['payment_status'], ['paid','demo_paid'], true)): ?><p><b><?= $b['payment_status'] === 'demo_paid' ? 'Demo reference:' : 'Payment reference:' ?></b> <?= e($b['payment_reference'] ?: 'Recorded') ?> · <a class="market-text-link" href="receipt.php?id=<?= (int)$id ?>"><?= $b['payment_status'] === 'demo_paid' ? 'View simulation record' : 'View / print receipt' ?></a></p><?php endif; ?>
  <?php if ($b['notes']): ?><p class="muted">Notes: <?= e($b['notes']) ?></p><?php endif; ?>
  <?php if ($b['payment_method'] !== 'cash' && !in_array($b['payment_status'], ['paid','demo_paid'], true) && !in_array($b['status'], ['cancelled','completed'], true)): ?>
    <div class="payment-demo-note"><b>Online payment still required.</b> This booking cannot start until checkout is complete. <a href="pay.php?id=<?= (int)$id ?>">Continue to <?= e(ucfirst($b['payment_method'])) ?> checkout →</a></div>
  <?php endif; ?>
  <?php if (in_array($b['status'], ['pending','assigned','in_progress','awaiting_verification'], true)): ?>
    <form method="post" style="margin-top:10px;" id="cancelForm"><?= csrf_field() ?>
      <?php if ($b['status'] === 'in_progress'): ?><p class="payment-demo-note">Cancelling stops this active job and notifies the worker.</p><?php endif; ?>
      <?php if ($b['payment_method'] !== 'cash'): ?>
        <p class="payment-demo-note"><b>Online payment:</b> cancelling does not issue a refund.<?php if ($b['payment_status'] === 'demo_paid'): ?> This was a simulation only; no money was transferred.<?php elseif ($b['payment_status'] === 'paid'): ?> Your payment remains recorded; contact support to request a refund.<?php else: ?> If you finish payment through an already-open checkout, the payment will be recorded but not automatically refunded.<?php endif; ?></p>
      <?php endif; ?>
      <div id="cancelReasonWrap" style="display:none;margin-bottom:10px;max-width:440px;">
        <label for="cancelReason">Reason for cancelling</label>
        <select name="cancel_reason" id="cancelReason">
          <option value="">— Select a reason —</option>
          <option value="Change of schedule">Change of schedule</option>
          <option value="Found a different cleaner">Found a different cleaner</option>
          <option value="Budget / pricing">Budget / pricing</option>
          <option value="Address not reachable">Address not reachable</option>
          <option value="Worker not responding">Worker not responding</option>
          <option value="Duplicate booking">Duplicate booking</option>
          <option value="other">Other…</option>
        </select>
        <input name="cancel_reason_other" id="cancelReasonOther" maxlength="200" placeholder="Please tell us why" style="display:none;margin-top:8px;width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:8px;font-family:inherit;">
      </div>
      <div class="booking-cancel-actions">
        <button type="button" id="cancelStartBtn" class="btn-sec">Cancel booking</button>
        <button type="button" id="cancelBackBtn" class="btn-sec" style="display:none;">← Back</button>
        <button type="submit" name="cancel" value="1" id="cancelConfirmBtn" class="btn" style="display:none;background:#c0392b;border-color:#c0392b;" data-confirm="Cancel booking #<?= (int)$id ?>? This will stop the booking and notify the worker. This action cannot be undone.<?= $b['payment_method'] !== 'cash' ? ' Online payments are not refunded automatically.' : '' ?>" data-confirm-title="Cancel booking" data-confirm-yes="Yes, cancel booking">Yes, cancel booking</button>
      </div>
    </form>
    <script>
    (()=>{
      const start=document.getElementById('cancelStartBtn');
      const back=document.getElementById('cancelBackBtn');
      const wrap=document.getElementById('cancelReasonWrap');
      const confirmBtn=document.getElementById('cancelConfirmBtn');
      const sel=document.getElementById('cancelReason');
      const other=document.getElementById('cancelReasonOther');
      if(start&&back&&wrap&&confirmBtn) {
        start.addEventListener('click',()=>{
          wrap.style.display='block';
          start.style.display='none';
          back.style.display='';
          confirmBtn.style.display='';
          sel.focus();
        });
        back.addEventListener('click',()=>{
          wrap.style.display='none';
          start.style.display='';
          back.style.display='none';
          confirmBtn.style.display='none';
          start.focus();
        });
      }
      if(sel&&other) sel.addEventListener('change',()=>{other.style.display=sel.value==='other'?'':'none';});
    })();
    </script>
  <?php endif; ?>
  <?php if (booking_is_finished($b['status'])): ?>
    <div class="danger-zone" style="margin-top:14px;padding:14px 16px;border-radius:12px;">
      <form method="post" data-confirm="Permanently delete booking #<?= (int)$id ?>? Its checklist, photos and feedback will also be deleted. This cannot be undone.<?= in_array($b['payment_status'], ['paid','demo_paid'], true) ? ' Deleting the booking does not refund its payment.' : '' ?>" data-confirm-title="Delete booking" data-confirm-yes="Yes, delete">
        <?= csrf_field() ?>
        <b style="color:var(--err)">Clean up</b>
        <p class="muted" style="margin:4px 0 10px;">This booking is finished. Delete it to clear it from your history.</p>
        <button name="delete_booking" value="1" class="btn-danger btn-sm">🗑 Delete permanently</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Custom Checklist</h2>
  <?php if (!$items->num_rows): ?>
    <p class="muted">No tasks on this checklist.</p>
  <?php else: ?>
    <div>
      <?php $editable = checklist_is_editable($b['status']); while ($it = $items->fetch_assoc()): ?>
        <div class="check-row <?= $it['done'] ? 'is-done' : '' ?>">
          <span><?= $it['done'] ? '✅' : '⬜' ?></span>
          <span class="txt"><?= e($it['task']) ?></span>
          <?php if ($editable): ?>
            <form method="post" data-confirm="Remove this task?" data-confirm-title="Remove task" data-confirm-yes="Yes, remove">
              <?= csrf_field() ?>
              <button class="icon-btn" name="delete_task" value="<?= $it['id'] ?>" title="Remove task" aria-label="Remove task">&times;</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
  <?php if (!checklist_is_editable($b['status'])): ?>
    <p class="muted" style="margin-top:10px;">This checklist is locked because the job is being verified or paid.</p>
  <?php endif; ?>
</div>

<div class="card photos">
  <h2>Before &amp; After Photos <span class="muted" style="font-weight:400">(click a photo to enlarge)</span></h2>
  <div class="photo-grid">
    <?php foreach ($before as $f): ?>
      <figure><img class="lightbox-img" src="../photo.php?id=<?= (int)$f['id'] ?>" alt="Before photo" loading="lazy" tabindex="0" role="button" aria-label="Enlarge before photo"><figcaption>before</figcaption></figure>
    <?php endforeach; ?>
    <?php foreach ($after as $f): ?>
      <figure><img class="lightbox-img" src="../photo.php?id=<?= (int)$f['id'] ?>" alt="After photo" loading="lazy" tabindex="0" role="button" aria-label="Enlarge after photo"><figcaption>after</figcaption></figure>
    <?php endforeach; ?>
  </div>
  <?php if (!$before && !$after) echo "<p class='muted'>No photos yet.</p>"; ?>
</div>

<div class="card">
  <?php if ($b['status'] === 'awaiting_verification'): ?>
    <h2>Review the completed work</h2>
    <p>Check the after photos and checklist before confirming.</p>
    <?php if ($b['payment_method'] === 'cash'): ?>
      <p class="payment-demo-note">Cash is due after the job. Only confirm once you have actually handed the cash to the worker.</p>
      <form method="post" data-confirm="Confirm that the cleaning is complete and you have handed ₱<?= number_format((float)$b['total_price'],2) ?> in cash to the worker?" data-confirm-title="Confirm cash payment" class="cash-confirm-form">
        <?= csrf_field() ?><input type="hidden" name="cash_confirmation" value="yes">
        <button name="confirm_cash_payment" value="1">✔ Confirm Job Finish &amp; complete</button>
      </form>
    <?php elseif (in_array($b['payment_status'], ['paid','demo_paid'], true)): ?>
      <p class="payment-demo-note"><?= $b['payment_status'] === 'demo_paid' ? 'This is a payment simulation only; no money was transferred. Verifying the work will complete the booking.' : 'Your online payment is already recorded. Verifying the work will complete the booking.' ?></p>
      <form method="post"><?= csrf_field() ?><button name="verify" value="1">✔ Confirm Job Finish &amp; complete</button></form>
    <?php else: ?>
      <p class="payment-demo-note">This online payment is still unpaid. Complete checkout before confirming this job.</p>
      <a class="btn" href="pay.php?id=<?= (int)$id ?>">Continue to <?= e(ucfirst($b['payment_method'])) ?> checkout</a>
    <?php endif; ?>
  <?php elseif ($b['status'] === 'in_progress'): ?>
    <h2>Job in progress</h2>
    <?php if ((int)($b['client_started_ack'] ?? 0) === 1): ?>
      <p class="payment-demo-note">✓ You confirmed the worker started. They'll press <b>Job Done</b> when finished — you'll then confirm the finished job.</p>
    <?php else: ?>
      <p class="payment-demo-note">The worker has started your job. Confirm to acknowledge so they can mark it done once finished.</p>
      <form method="post" data-confirm="Confirm that the worker has arrived and started booking #<?= (int)$id ?>? This only acknowledges the start; it does not confirm that the cleaning is finished or that payment was made." data-confirm-title="Confirm job start"><?= csrf_field() ?><button name="confirm_job_start" value="1">Confirm Job</button></form>
    <?php endif; ?>
  <?php elseif ($b['status'] === 'completed'): ?>
    <h2>Feedback</h2>
    <?php if ($fb): ?>
      <p>Your rating: <?= str_repeat('⭐', $fb['rating']) ?> (<?= $fb['rating'] ?>/5)</p>
      <?php if ($fb['comment']): ?><p class="muted">"<?= e($fb['comment']) ?>"</p><?php endif; ?>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label>Rating (1–5)</label>
      <select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option <?= ($fb['rating'] ?? 0) == $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select>
      <label>Comment</label><textarea name="comment" rows="2"><?= e($fb['comment'] ?? '') ?></textarea>
      <button>Submit feedback</button>
    </form>
    <form method="post" action="bookings.php" style="margin-top:10px;"><?= csrf_field() ?><button class="btn" name="rebook" value="<?= (int)$id ?>">🔁 Book this again</button></form>
  <?php endif; ?>
</div>
<div id="lightbox" role="dialog" aria-modal="true" aria-label="Enlarged booking photo" aria-hidden="true"><img id="lightbox-img" src="" alt="Enlarged photo" tabindex="-1"></div>
<?php if (in_array($b['payment_status'], ['paid','demo_paid'], true)): ?><p class="receipt-cta"><a class="btn btn-sec" href="receipt.php?id=<?= (int)$id ?>"><?= $b['payment_status'] === 'demo_paid' ? 'View simulation record' : 'View / print / save receipt' ?></a></p><?php endif; ?>
<?php require '../footer.php'; ?>
